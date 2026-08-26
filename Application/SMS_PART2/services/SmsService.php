<?php
/**
 * SmsService Coordination Pipeline Service
 * 
 * Orchestrates incoming SMS classification, extraction fallback, intelligent grouping, 
 * outbox retry queue dispatches, and heartbeat telemetry tracking.
 */

if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/SmsMessage.php';
require_once __DIR__ . '/../models/SosRequest.php';
require_once __DIR__ . '/../models/ExtractedData.php';
require_once __DIR__ . '/SmsParser.php';
require_once __DIR__ . '/AiExtractionService.php';
require_once __DIR__ . '/GatewayService.php';
require_once __DIR__ . '/AuditLogger.php';

class SmsService {
    /**
     * Update dynamic gateway telemetry keys in system_config
     */
    public static function updateGatewayHeartbeat($event, $deviceId = null, $error = null) {
        $db = Database::getConnection();
        $fields = [
            'gateway_last_seen' => date('Y-m-d H:i:s'),
            'gateway_last_event' => $event,
            'gateway_last_device_id' => $deviceId,
            'gateway_last_error' => $error
        ];
        
        foreach ($fields as $key => $val) {
            $stmt = $db->prepare("INSERT INTO system_config (config_key, config_value) 
                                  VALUES (:key, :val) 
                                  ON DUPLICATE KEY UPDATE config_value = :val2");
            $stmt->execute([
                ':key' => $key,
                ':val' => $val,
                ':val2' => $val
            ]);
        }
        return true;
    }

    /**
     * Process incoming Webhook SMS payloads from Android Gateway
     * 
     * Decouples deduplication, privacy checks, conversations, and smart incident grouping.
     */
    public static function processIncoming($gatewayMsgId, $fromNumber, $toNumber, $body, $receivedAt = null) {
        $db = Database::getConnection();

        // 1. Webhook Deduplication Check (Runs before any privacy shield parsing)
        if ($gatewayMsgId && SmsMessage::isDuplicate($gatewayMsgId)) {
            return [
                'status' => 'ignored',
                'reason' => 'duplicate',
                'message_id' => null
            ];
        }

        // Insert message ID to processed log immediately to block subsequent retries
        if ($gatewayMsgId) {
            $stmt = $db->prepare("INSERT INTO processed_gateway_messages (gateway_message_id, is_sos) VALUES (:id, 0)");
            $stmt->execute([':id' => $gatewayMsgId]);
        }

        // 2. Personal SIM Privacy Filter
        $isEmergency = SmsParser::isEmergency($body);
        if (!$isEmergency) {
            // Discard civilian SMS body and return immediately.
            // Heartbeat telemetry was updated, but no personal contents enter the database.
            return [
                'status' => 'discarded',
                'message_type' => 'normal',
                'message_id' => null
            ];
        }

        // Flag message reference as an SOS event in the deduplication log
        if ($gatewayMsgId) {
            $stmt = $db->prepare("UPDATE processed_gateway_messages SET is_sos = 1 WHERE gateway_message_id = :id");
            $stmt->execute([':id' => $gatewayMsgId]);
        }

                // 2b. Extract Real Victim Phone Number from body if present
        if (preg_match('/(?:Tel|Phone|Mobile|Contact):\s*([+0-9\s-]+)/i', $body, $pm)) {
            $cleaned = preg_replace('/[^\d+]/', '', $pm[1]);
            if (strlen($cleaned) >= 10 && ($fromNumber === '+919999999999' || empty($fromNumber) || strpos($fromNumber, '999999') !== false)) {
                if (strpos($cleaned, '+') !== 0 && strlen($cleaned) == 10) {
                    $cleaned = '+91' . $cleaned;
                }
                $fromNumber = $cleaned;
            }
        }

        // Convert receivedAt format to MySQL datetime
        $dbReceivedAt = $receivedAt ? date('Y-m-d H:i:s', strtotime($receivedAt)) : date('Y-m-d H:i:s');

        // 3. Map or Create Conversation Thread
        // Get primary central SOS number configuration
        $stmt = $db->query("SELECT id FROM sms_numbers WHERE is_primary = 1 LIMIT 1");
        $numRow = $stmt->fetch();
        $smsNumberId = $numRow ? (int)$numRow['id'] : 1;

        // Check if there is an active conversation for this sender phone number in the last 15 minutes
        $stmt = $db->prepare("SELECT id FROM conversations 
                              WHERE sender_phone = :from 
                                AND last_message_at >= NOW() - INTERVAL 15 MINUTE 
                              LIMIT 1");
        $stmt->execute([':from' => $fromNumber]);
        $convRow = $stmt->fetch();
        
        $conversationId = null;
        if ($convRow) {
            $conversationId = (int)$convRow['id'];
            $stmt = $db->prepare("UPDATE conversations SET last_message_at = NOW() WHERE id = :id");
            $stmt->execute([':id' => $conversationId]);
        } else {
            // Register a new conversation thread
            $stmt = $db->prepare("INSERT INTO conversations (sender_phone, sms_number_id, last_message_at) 
                                  VALUES (:from, :num_id, NOW())");
            $stmt->execute([':from' => $fromNumber, ':num_id' => $smsNumberId]);
            $conversationId = (int)$db->lastInsertId();
        }

        // 4. Save SMS message inside conversation thread
        $smsId = SmsMessage::create(
            $conversationId,
            $fromNumber,
            $toNumber,
            'incoming',
            $body,
            'processed',
            $gatewayMsgId,
            $dbReceivedAt
        );

        // 5. Emergency SOS Details Extraction
        $parseResult = SmsParser::parse($body);
        $extractionMethod = 'rule_based';
        $extractedFields = $parseResult;

        // Trigger Gemini AI fallback if rules fail to resolve coordinates or disaster type
        if ($parseResult['needs_ai_fallback']) {
            $aiResult = AiExtractionService::extract($body);
            if ($aiResult) {
                $extractionMethod = 'ai_gemini';
                $extractedFields = array_merge($parseResult, $aiResult);
                // Preserve rules-extracted coordinates to prevent fallback overrides
                if ($parseResult['latitude'] !== null && $parseResult['longitude'] !== null) {
                    $extractedFields['latitude'] = $parseResult['latitude'];
                    $extractedFields['longitude'] = $parseResult['longitude'];
                }
            }
        }

        // Save Extracted Metadata
        ExtractedData::create(
            $smsId,
            $extractedFields['latitude'],
            $extractedFields['longitude'],
            $extractedFields['people_count'],
            $extractedFields['injured_count'],
            $extractedFields['disaster_type'],
            $extractedFields['help_required'],
            $extractedFields['priority'],
            $extractedFields['confidence'],
            $extractionMethod,
            json_encode($extractedFields)
        );

        // 6. Smart SOS Incident Grouping
        // Retrieve the latest active SOS request for this conversation within the last 15 minutes
        $stmt = $db->prepare("SELECT * FROM sos_requests 
                              WHERE conversation_id = :conv_id 
                                AND created_at >= NOW() - INTERVAL 15 MINUTE 
                              ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([':conv_id' => $conversationId]);
        $latestSos = $stmt->fetch();

        $sosId = null;
        $isNewIncident = true;

        if ($latestSos) {
            // Calculate actual Haversine distance in kilometers
            $distanceKm = self::calculateHaversineDistance(
                $latestSos['latitude'], 
                $latestSos['longitude'], 
                $extractedFields['latitude'], 
                $extractedFields['longitude']
            );
            
            // Get grouping radius from system config (defaults to 2.0 km)
            $stmtRadius = $db->prepare("SELECT config_value FROM system_config WHERE config_key = 'grouping_radius_km' LIMIT 1");
            $stmtRadius->execute();
            $radiusRow = $stmtRadius->fetch();
            $groupingRadius = $radiusRow ? (float)$radiusRow['config_value'] : 2.0;

            $sameLocation = ($extractedFields['latitude'] === null || $latestSos['latitude'] === null || ($distanceKm <= $groupingRadius));
            
            $sameDisaster = ($extractedFields['disaster_type'] === 'unknown' || 
                             $latestSos['disaster_type'] === 'unknown' || 
                             strtolower($latestSos['disaster_type']) === strtolower($extractedFields['disaster_type']));

            if ($sameLocation && $sameDisaster) {
                // Group message into existing SOS incident - update fields (merge/override)
                $sosId = (int)$latestSos['id'];
                $isNewIncident = false;

                $updateFields = [];
                if ($extractedFields['latitude'] !== null) $updateFields['latitude'] = $extractedFields['latitude'];
                if ($extractedFields['longitude'] !== null) $updateFields['longitude'] = $extractedFields['longitude'];
                if ($extractedFields['disaster_type'] !== 'unknown') $updateFields['disaster_type'] = $extractedFields['disaster_type'];
                
                // Additive counts
                if ($extractedFields['people_count'] > 1) {
                    $updateFields['people_count'] = max((int)$latestSos['people_count'], $extractedFields['people_count']);
                }
                if ($extractedFields['injured_count'] > 0) {
                    $updateFields['injured_count'] = max((int)$latestSos['injured_count'], $extractedFields['injured_count']);
                }
                
                // Override priority if new one is higher
                $prioRank = ['LOW' => 1, 'MEDIUM' => 2, 'HIGH' => 3, 'CRITICAL' => 4];
                $oldPrioRank = $prioRank[$latestSos['priority']] ?? 2;
                $newPrioRank = $prioRank[$extractedFields['priority']] ?? 2;
                if ($newPrioRank > $oldPrioRank) {
                    $updateFields['priority'] = $extractedFields['priority'];
                }

                if (!empty($extractedFields['help_required'])) {
                    $updateFields['help_required'] = !empty($latestSos['help_required']) ? 
                        $latestSos['help_required'] . ', ' . $extractedFields['help_required'] : 
                        $extractedFields['help_required'];
                }

                if (!empty($updateFields)) {
                    SosRequest::updateIncident($sosId, $updateFields);
                }
            }
        }

        if ($isNewIncident) {
            // Create a brand new incident under this conversation thread
            $sosId = SosRequest::create(
                $conversationId,
                $extractedFields['disaster_type'],
                $extractedFields['latitude'],
                $extractedFields['longitude'],
                $extractedFields['people_count'],
                $extractedFields['injured_count'],
                $extractedFields['priority'],
                $extractedFields['help_required']
            );
        }

        // Audit Log entry
        AuditLogger::log(
            'System', 
            $isNewIncident ? 'SOS_CREATED' : 'SOS_UPDATED', 
            'SOS', 
            $sosId, 
            ($isNewIncident ? "Created new SOS incident " : "Updated existing SOS incident ") . 
            "SOS-" . $sosId . " from " . $fromNumber . " via " . $extractionMethod . ". Priority: " . $extractedFields['priority']
        );

        // Q7 = B: No automatic SMS acknowledgements will be sent.
        return [
            'status' => 'processed',
            'message_type' => 'SOS',
            'message_id' => $smsId,
            'sos_id' => $sosId
        ];
    }

    /**
     * Dispatch a single outgoing message by its SMS id
     */
    public static function dispatchOutgoingMessage($smsMessageId) {
        $db = Database::getConnection();
        
        // Fetch outbox item
        $stmt = $db->prepare("SELECT o.id as outbox_id, o.attempt_count, m.to_number, m.message_body 
                              FROM sms_outbox o 
                              JOIN sms_messages m ON o.sms_message_id = m.id 
                              WHERE o.sms_message_id = :sms_message_id AND o.status = 'queued' LIMIT 1");
        $stmt->execute([':sms_message_id' => $smsMessageId]);
        $outboxItem = $stmt->fetch();

        if (!$outboxItem) {
            return false;
        }

        $outboxId = $outboxItem['outbox_id'];
        
        // Lock outbox
        $db->prepare("UPDATE sms_outbox SET status = 'sending', locked_at = NOW() WHERE id = :id")->execute([':id' => $outboxId]);
        SmsMessage::updateStatus($smsMessageId, 'sending');

        // Call REST Gateway
        $response = GatewayService::sendSms($outboxItem['to_number'], $outboxItem['message_body']);

        if ($response['success']) {
            // Update structures
            $db->prepare("UPDATE sms_outbox SET status = 'sent', updated_at = NOW() WHERE id = :id")->execute([':id' => $outboxId]);
            
            $stmt = $db->prepare("UPDATE sms_messages SET status = 'sent', gateway_message_id = :gateway_id, sent_at = NOW() WHERE id = :id");
            $stmt->execute([
                ':gateway_id' => $response['gateway_id'],
                ':id' => $smsMessageId
            ]);

            AuditLogger::log('System', 'SMS_SENT', 'SMS', $smsMessageId, "SMS successfully sent to " . $outboxItem['to_number'] . " (GW ID: " . $response['gateway_id'] . ")");
            self::updateGatewayHeartbeat('sms:sent', null, null);
            self::syncAlertStatusFromMessage($smsMessageId, 'sent');
            return true;
        } else {
            // Increment attempts and schedule retry
            $nextAttemptDelay = pow(2, $outboxItem['attempt_count'] + 1) * 30; // Exponential backup
            $nextAttemptAt = date('Y-m-d H:i:s', time() + $nextAttemptDelay);
            $newAttempts = $outboxItem['attempt_count'] + 1;
            
            $status = ($newAttempts >= 3) ? 'failed' : 'queued';
            
            $stmt = $db->prepare("UPDATE sms_outbox SET 
                                  status = :status, 
                                  attempt_count = :attempts, 
                                  next_attempt_at = :next_attempt, 
                                  last_error = :error,
                                  locked_at = NULL 
                                  WHERE id = :id");
            $stmt->execute([
                ':status' => $status,
                ':attempts' => $newAttempts,
                ':next_attempt' => $nextAttemptAt,
                ':error' => $response['error'],
                ':id' => $outboxId
            ]);

            $smsStatus = ($status === 'failed') ? 'failed' : 'queued';
            SmsMessage::updateStatus($smsMessageId, $smsStatus);
            self::syncAlertStatusFromMessage($smsMessageId, $smsStatus);

            AuditLogger::log('System', 'SMS_SEND_FAILED', 'SMS', $smsMessageId, "SMS delivery failed: " . $response['error'] . " (Retrying in " . $nextAttemptDelay . "s)");
            self::updateGatewayHeartbeat('sms:failed', null, $response['error']);
            return false;
        }
    }

    /**
     * Cron/Worker runner scanning for pending outbox items
     */
    public static function processOutboxQueue() {
        $db = Database::getConnection();
        
        // Scan for unlocked items scheduled for retry
        $sql = "SELECT sms_message_id FROM sms_outbox 
                WHERE status = 'queued' 
                  AND (next_attempt_at IS NULL OR next_attempt_at <= NOW()) 
                  AND locked_at IS NULL 
                ORDER BY created_at ASC LIMIT 10";
                
        $stmt = $db->query($sql);
        $items = $stmt->fetchAll();
        $processedCount = 0;
        
        foreach ($items as $item) {
            $success = self::dispatchOutgoingMessage($item['sms_message_id']);
            if ($success) {
                $processedCount++;
            }
        }
        
        return $processedCount;
    }

    /**
     * Calculate geographical distance between two points in kilometers using Haversine
     */
    public static function calculateHaversineDistance($lat1, $lng1, $lat2, $lng2) {
        if ($lat1 === null || $lng1 === null || $lat2 === null || $lng2 === null) {
            return 0.0;
        }

        $earthRadius = 6371.0; // Mean Earth Radius in Kilometers

        $latDelta = deg2rad((float)$lat2 - (float)$lat1);
        $lonDelta = deg2rad((float)$lng2 - (float)$lng1);

        $a = sin($latDelta / 2.0) * sin($latDelta / 2.0) +
             cos(deg2rad((float)$lat1)) * cos(deg2rad((float)$lat2)) *
             sin($lonDelta / 2.0) * sin($lonDelta / 2.0);

        $c = 2.0 * atan2(sqrt($a), sqrt(1.0 - $a));
        return $earthRadius * $c;
    }

    /**
     * Synchronize associated Disaster Alert status based on SMS message status transitions
     */
    public static function syncAlertStatusFromMessage($smsMessageId, $newStatus) {
        $db = Database::getConnection();
        
        // Fetch message body
        $stmt = $db->prepare("SELECT message_body FROM sms_messages WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $smsMessageId]);
        $body = $stmt->fetchColumn();
        
        if ($body && preg_match('/ID:([A-Za-z0-9_-]+)/', $body, $matches)) {
            $alertId = $matches[1];
            
            // Fetch recipient mode of the alert
            $stmtAlert = $db->prepare("SELECT recipient_phone FROM disaster_alerts WHERE alertId = :id LIMIT 1");
            $stmtAlert->execute([':id' => $alertId]);
            $recipientPhone = $stmtAlert->fetchColumn();
            
            if ($recipientPhone === 'BROADCAST') {
                // Fetch total contacts
                $stmtContacts = $db->query("SELECT COUNT(*) FROM contacts");
                $totalContacts = (int)$stmtContacts->fetchColumn();
                if ($totalContacts === 0) {
                    $totalContacts = 1;
                }
                
                // Fetch stats counts using exact match patterns
                $alertBodyPattern = "%\nID:" . $alertId . "\n%";
                $stmtCount = $db->prepare("SELECT status, COUNT(*) as count FROM sms_messages WHERE direction = 'outgoing' AND message_body LIKE :pattern GROUP BY status");
                $stmtCount->execute([':pattern' => $alertBodyPattern]);
                $counts = ['queued' => 0, 'sending' => 0, 'sent' => 0, 'delivered' => 0, 'failed' => 0];
                while ($countRow = $stmtCount->fetch()) {
                    $counts[$countRow['status']] = (int)$countRow['count'];
                }
                
                $sentCount = $counts['sent'] + $counts['delivered'];
                $failedCount = $counts['failed'];
                $queuedCount = $counts['queued'];
                $sendingCount = $counts['sending'];
                
                if ($queuedCount > 0 || $sendingCount > 0) {
                    $globalStatus = 'sending';
                } elseif ($sentCount === $totalContacts) {
                    $globalStatus = 'sent';
                } elseif ($failedCount === $totalContacts) {
                    $globalStatus = 'failed';
                } elseif ($sentCount > 0) {
                    $globalStatus = 'partial';
                } else {
                    $globalStatus = 'failed';
                }
                
                $stmtUpdate = $db->prepare("UPDATE disaster_alerts SET status = :status WHERE alertId = :id");
                $stmtUpdate->execute([':status' => $globalStatus, ':id' => $alertId]);
            } else {
                // Single contact alert: map directly
                $alertStatus = 'queued';
                if ($newStatus === 'sent' || $newStatus === 'delivered') {
                    $alertStatus = 'sent';
                } elseif ($newStatus === 'failed') {
                    $alertStatus = 'failed';
                } elseif ($newStatus === 'sending') {
                    $alertStatus = 'sending';
                }
                
                $stmtUpdate = $db->prepare("UPDATE disaster_alerts SET status = :status WHERE alertId = :id");
                $stmtUpdate->execute([':status' => $alertStatus, ':id' => $alertId]);
            }
        }
    }
}
?>
