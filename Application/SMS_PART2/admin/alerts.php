<?php
/**
 * Disaster Alerts Management & Hybrid Delivery Dashboard
 */

session_start();

$page_title = 'Disaster Alerts';
define('SECURE_ACCESS', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Alert.php';
require_once __DIR__ . '/../models/SmsMessage.php';
require_once __DIR__ . '/../models/Contact.php';
require_once __DIR__ . '/../models/SmsNumber.php';
require_once __DIR__ . '/../services/SmsService.php';

$db = Database::getConnection();
$login_error = '';

// 1. Password Protection Check (Secure session-based verification with BCRYPT)
if (isset($_POST['login_password'])) {
    $entered = $_POST['login_password'];
    
    // Fetch hashed password from DB
    $stmt = $db->prepare("SELECT config_value FROM system_config WHERE config_key = 'admin_password_hash' LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch();
    $storedHash = $row ? $row['config_value'] : '';
    
    if (!empty($storedHash) && password_verify($entered, $storedHash)) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        $login_error = "Invalid administrator password.";
        AuditLogger::log('System', 'LOGIN_FAILURE', 'Auth', 0, "Unauthorized command center login attempt.");
    }
}

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    header("Location: alerts.php");
    exit;
}

$isAuthenticated = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

require_once __DIR__ . '/layout_header.php';

if (!$isAuthenticated) {
    ?>
    <div style="max-width: 400px; margin: 80px auto;">
        <?php if (!empty($login_error)): ?>
            <div style="background: rgba(220, 53, 69, 0.08); border: 1px solid var(--accent-red); padding: 12px 18px; border-radius: 6px; margin-bottom: 24px; font-size: 14.5px; font-weight: 500; color: #ff6b6b;">
                ❌ <?php echo htmlspecialchars($login_error); ?>
            </div>
        <?php endif; ?>
        <div class="panel">
            <div class="panel-title">
                <span>🔐 Command Center Authorization Required</span>
            </div>
            <form method="POST" action="alerts.php">
                <div class="form-group">
                    <label for="login_password">Enter Command Center Password</label>
                    <input type="password" name="login_password" id="login_password" class="form-control" placeholder="••••••••" required />
                </div>
                <button type="submit" class="btn-primary" style="width:100%; margin-top: 16px;">AUTHENTICATE OPERATOR</button>
            </form>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/layout_footer.php';
    exit;
}

// 2. Handle Actions (Cancel / Publish Draft)
$actionMessage = '';
$actionSuccess = true;

if (isset($_GET['action']) && isset($_GET['id'])) {
    $targetId = trim($_GET['id']);
    $action = $_GET['action'];
    $now = round(microtime(true) * 1000);

    if ($action === 'cancel') {
        try {
            Alert::cancel($targetId, $now);
            AuditLogger::log('Operator', 'ALERT_CANCELLED', 'Alert', 0, "Disaster Alert cancelled: " . $targetId);
            
            // Broadcast cancellation via FCM
            $updated = Alert::getById($targetId);
            sendFcmAlert($updated);
            
            $actionMessage = "Alert $targetId has been cancelled successfully.";
            $actionSuccess = true;
        } catch (Exception $e) {
            $actionMessage = "Failed to cancel alert: " . $e->getMessage();
            $actionSuccess = false;
        }
    } elseif ($action === 'publish_draft') {
        try {
            $draft = Alert::getById($targetId);
            if ($draft && $draft['lifecycleStatus'] === 'DRAFT') {
                $db->prepare("UPDATE disaster_alerts SET lifecycleStatus = 'PUBLISHED', publishedTimestamp = :pub WHERE alertId = :id")
                   ->execute([':pub' => $now, ':id' => $targetId]);

                $updated = Alert::getById($targetId);
                AuditLogger::log('Operator', 'ALERT_PUBLISHED', 'Alert', 0, "Draft alert published: " . $targetId);

                // Hybrid dispatch
                $fcmSent = sendFcmAlert($updated);
                $recipientPhone = $updated['recipient_phone'] ?? '';
                if (!empty($recipientPhone)) {
                    if ($recipientPhone === 'BROADCAST') {
                        $result = sendSmsBroadcast($updated);
                        if ($result['success'] || $result['failed'] > 0) {
                            $actionMessage = "✓ DRAFT PUBLISHED AND BROADCAST COMPLETED\n\nAlert ID: $targetId\nSent: " . $result['sent'] . "\nFailed: " . $result['failed'];
                            $actionSuccess = $result['sent'] > 0;
                        } else {
                            $actionMessage = "✕ DRAFT PUBLISHED BUT BROADCAST FAILED\n\nReason: " . ($result['error'] ?? 'All dispatches rejected');
                            $actionSuccess = false;
                        }
                    } else {
                        $result = sendSmsFallback($updated, $recipientPhone);
                        if ($result['success']) {
                            $actionMessage = "✓ DRAFT PUBLISHED AND SENT\n\nAlert ID: $targetId\nRecipient: $recipientPhone\nStatus: SENT";
                            $actionSuccess = true;
                        } else {
                            $actionMessage = "✕ DRAFT PUBLISHED BUT SEND FAILED\n\nRecipient: $recipientPhone\nReason: " . ($result['error'] ?? 'Gateway rejected request') . "\nStatus: FAILED";
                            $actionSuccess = false;
                        }
                    }
                } else {
                    $actionMessage = "Draft alert $targetId published successfully, but no recipient phone was registered. Status: SENT";
                    $actionSuccess = true;
                }
            } else {
                $actionMessage = "Draft alert not found or already published.";
                $actionSuccess = false;
            }
        } catch (Exception $e) {
            $actionMessage = "Failed to publish draft alert: " . $e->getMessage();
            $actionSuccess = false;
        }
    }
}

// 3. Handle Alert Creation/Publishing Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alert_id'])) {
    $alertId = trim($_POST['alert_id'] ?? '');
    if (empty($alertId)) {
        $alertId = 'DS-' . mt_rand(10000, 99999);
    }
    $title = trim($_POST['title']);
    $disasterType = $_POST['disaster_type'];
    $severity = $_POST['severity'];
    $sourceType = $_POST['source_type'];
    $sourceAuthority = trim($_POST['source_authority']);
    $message = trim($_POST['message']);
    $safetyInstructions = trim($_POST['safety_instructions']);
    $expiryHours = (int)$_POST['expiry_hours'];
    $areaType = $_POST['area_type'];
    $isDraft = isset($_POST['save_draft']);
    $recipientMode = $_POST['recipient_mode'] ?? 'single';
    $recipientPhone = '';
    $recipientId = 0;
    
    if ($recipientMode === 'broadcast') {
        $recipientPhone = 'BROADCAST';
    } else {
        $recipientId = isset($_POST['recipient_id']) ? (int)$_POST['recipient_id'] : 0;
    }

    $contact = null;
    if ($recipientMode === 'single') {
        $contact = Contact::getById($recipientId);
    }
    
    if ($recipientMode === 'single' && !$contact) {
        $actionMessage = "Error: Please select a valid recipient contact.";
        $actionSuccess = false;
    } else {
        if ($recipientMode === 'single') {
            $recipientPhone = preg_replace('/[^\d+]/', '', $contact['phone_number']);
            if (strpos($recipientPhone, '+') !== 0) {
                $recipientPhone = '+' . $recipientPhone;
            }
        }

        // Server-side validation
        if (empty($alertId) || empty($title) || empty($message)) {
            $actionMessage = "Please complete all required fields.";
            $actionSuccess = false;
        } else {
            // Check for duplicates
            $existing = Alert::getById($alertId);
            if ($existing) {
                $actionMessage = "Conflict Error: Alert ID '$alertId' already exists. Duplicate IDs are rejected.";
                $actionSuccess = false;
            } else {
                // Optional image upload processing
                $imageUrl = null;
                $uploadSuccess = true;
                
                if (isset($_FILES['alert_image']) && $_FILES['alert_image']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['alert_image'];
                    $tmpPath = $file['tmp_name'];
                    
                    // 1. File Size Verification (Max 2MB)
                    if ($file['size'] > 2 * 1024 * 1024) {
                        $actionMessage = "Image upload failed: Size exceeds 2MB limit.";
                        $uploadSuccess = false;
                    }
                    
                    // 2. MIME type verification using PHP finfo (Inspecting file signature)
                    if ($uploadSuccess) {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime = finfo_file($finfo, $tmpPath);
                        finfo_close($finfo);
                        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
                        if (!in_array($mime, $allowedMimes)) {
                            $actionMessage = "Image upload failed: Invalid file type $mime. Only JPG, PNG, and WebP are allowed.";
                            $uploadSuccess = false;
                        }
                    }
                    
                    // 3. Extension check
                    if ($uploadSuccess) {
                        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
                        if (!in_array($ext, $allowedExts)) {
                            $actionMessage = "Image upload failed: Unsupported file extension.";
                            $uploadSuccess = false;
                        }
                    }
                    
                    // 4. Decode image validation
                    if ($uploadSuccess) {
                        $imgSize = getimagesize($tmpPath);
                        if ($imgSize === false) {
                            $actionMessage = "Image upload failed: Corrupted image file.";
                            $uploadSuccess = false;
                        }
                    }
                    
                    // 5. Generate secure filename to prevent path traversal
                    if ($uploadSuccess) {
                        $uniqueName = $alertId . "_" . bin2hex(random_bytes(8)) . "." . $ext;
                        // Ensure destination is outside code execution path
                        $uploadDir = __DIR__ . '/../uploads/alerts/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        $destPath = $uploadDir . $uniqueName;
                        
                        if (move_uploaded_file($tmpPath, $destPath)) {
                            $imageUrl = "uploads/alerts/" . $uniqueName;
                        } else {
                            $actionMessage = "Image upload failed: Unable to save file to destination.";
                            $uploadSuccess = false;
                        }
                    }
                } elseif (isset($_FILES['alert_image']) && $_FILES['alert_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $actionMessage = "Image upload failed: Error code " . $_FILES['alert_image']['error'];
                    $uploadSuccess = false;
                }

                if ($uploadSuccess) {
                    $now = round(microtime(true) * 1000);
                    $expires = $now + ($expiryHours * 3600 * 1000);

                    $polygonJson = null;
                    $latVal = null;
                    $lngVal = null;
                    $radiusVal = null;

                    if ($areaType === 'RADIUS') {
                        $latVal = (float)($_POST['latitude'] ?? 0);
                        $lngVal = (float)($_POST['longitude'] ?? 0);
                        $radiusVal = (float)($_POST['radius'] ?? 2000);
                    } elseif ($areaType === 'POLYGON') {
                        $polygonJson = trim($_POST['polygon_json'] ?? '');
                        $latVal = (float)($_POST['latitude'] ?? 0);
                        $lngVal = (float)($_POST['longitude'] ?? 0);
                        $radiusVal = (float)($_POST['radius'] ?? 2000);
                    }

                    $alertData = [
                        'alertId' => $alertId,
                        'title' => $title,
                        'message' => $message,
                        'disasterType' => $disasterType,
                        'severity' => $severity,
                        'sourceType' => $sourceType,
                        'sourceAuthority' => $sourceAuthority,
                        'createdTimestamp' => $now,
                        'publishedTimestamp' => $isDraft ? 0 : $now,
                        'cancelledTimestamp' => null,
                        'expiresTimestamp' => $expires,
                        'lifecycleStatus' => $isDraft ? 'DRAFT' : 'PUBLISHED',
                        'safetyInstructions' => $safetyInstructions,
                        'areaType' => $areaType,
                        'areaLatitude' => $latVal,
                        'areaLongitude' => $lngVal,
                        'areaRadiusMeters' => $radiusVal,
                        'areaPolygonCoordinatesJson' => $polygonJson,
                        'areaAdministrativeArea' => $areaType === 'ADMINISTRATIVE' ? trim($_POST['admin_area']) : null,
                        'receivedTimestamp' => $now,
                        'imageUrl' => $imageUrl,
                        'recipientPhone' => $recipientPhone,
                        'status' => $isDraft ? 'draft' : 'queued'
                    ];

                    try {
                        Alert::create($alertData);
                        AuditLogger::log('Operator', $isDraft ? 'ALERT_CREATED' : 'ALERT_PUBLISHED', 'Alert', 0, ($isDraft ? "Draft created: " : "Alert published: ") . $alertId);

                        if (!$isDraft) {
                            // Trigger real hybrid delivery
                            $fcmSent = sendFcmAlert($alertData);
                            
                            if ($recipientPhone === 'BROADCAST') {
                                $result = sendSmsBroadcast($alertData);
                                if ($result['success'] || $result['failed'] > 0) {
                                    $actionMessage = "✓ BROADCAST COMPLETED\n\nAlert ID: $alertId\nSent: " . $result['sent'] . "\nFailed: " . $result['failed'];
                                    $actionSuccess = $result['sent'] > 0;
                                } else {
                                    $actionMessage = "✕ BROADCAST FAILED\n\nReason: " . ($result['error'] ?? 'All dispatches rejected');
                                    $actionSuccess = false;
                                }
                            } else {
                                $result = sendSmsFallback($alertData, $recipientPhone);
                                if ($result['success']) {
                                    $actionMessage = "✓ ALERT SENT\n\nAlert ID: $alertId\nRecipient: $recipientPhone\nStatus: SENT";
                                    $actionSuccess = true;
                                } else {
                                    $actionMessage = "✕ ALERT FAILED\n\nRecipient: $recipientPhone\nReason: " . ($result['error'] ?? 'Gateway rejected request') . "\nStatus: FAILED";
                                    $actionSuccess = false;
                                }
                            }
                        } else {
                            $actionMessage = "Alert draft saved successfully.";
                            $actionSuccess = true;
                        }
                    } catch (Exception $e) {
                        $actionMessage = "Failed to store alert: " . $e->getMessage();
                        $actionSuccess = false;
                    }
                } else {
                    $actionSuccess = false;
                }
            }
        }
    }
}

// Helper FCM function
function sendFcmAlert($alert) {
    $db = Database::getConnection();
    $stmt = $db->prepare("SELECT config_value FROM system_config WHERE config_key = 'fcm_api_key' LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch();
    $apiKey = $row ? $row['config_value'] : '';

    if (empty($apiKey)) {
        AuditLogger::log('System', 'FCM_SKIPPED', 'FCM', 0, "FCM push dispatch skipped (no API key configured for alert: " . $alert['alertId'] . ")");
        return false;
    }

    $payload = [
        'to' => '/topics/disaster_alerts',
        'data' => [
            'alertId' => $alert['alertId'],
            'title' => $alert['title'],
            'message' => $alert['message'],
            'disasterType' => $alert['disasterType'],
            'severity' => $alert['severity'],
            'sourceType' => $alert['sourceType'],
            'sourceAuthority' => $alert['sourceAuthority'],
            'createdTimestamp' => (string)$alert['createdTimestamp'],
            'publishedTimestamp' => (string)$alert['publishedTimestamp'],
            'expiresTimestamp' => (string)$alert['expiresTimestamp'],
            'lifecycleStatus' => $alert['lifecycleStatus'],
            'safetyInstructions' => $alert['safetyInstructions'],
            'imageUrl' => $alert['imageUrl'] !== null ? "/SMS_PART2/" . $alert['imageUrl'] : ""
        ]
    ];

    if ($alert['areaType'] !== null) {
        $payload['data']['areaType'] = $alert['areaType'];
        if ($alert['areaType'] === 'RADIUS') {
            $payload['data']['areaLatitude'] = (string)$alert['areaLatitude'];
            $payload['data']['areaLongitude'] = (string)$alert['areaLongitude'];
            $payload['data']['areaRadiusMeters'] = (string)$alert['areaRadiusMeters'];
        } else {
            $payload['data']['areaAdministrativeArea'] = (string)$alert['areaAdministrativeArea'];
        }
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: key=' . $apiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        AuditLogger::log('System', 'FCM_DISPATCHED', 'FCM', 0, "FCM push successfully broadcasted for alert: " . $alert['alertId']);
        return true;
    } else {
        AuditLogger::log('System', 'FCM_FAILED', 'FCM', 0, "FCM push failed with HTTP code $httpCode for alert: " . $alert['alertId']);
        return false;
    }
}

// Helper SMS fallback function
function sendSmsFallback($alert, $recipientPhone) {
    $db = Database::getConnection();
    
    $smsText = "[DISASTERSAFE]\n";
    $smsText .= "ID:" . $alert['alertId'] . "\n";
    $smsText .= "TYPE:" . $alert['disasterType'] . "\n";
    $smsText .= "SEVERITY:" . $alert['severity'] . "\n";
    $smsText .= "TITLE:" . $alert['title'] . "\n";
    $smsText .= "MESSAGE:" . $alert['message'];
    
    if ($alert['areaType'] === 'RADIUS') {
        $areaStr = $alert['areaLatitude'] . "," . $alert['areaLongitude'] . "," . $alert['areaRadiusMeters'];
        $smsText .= "\nAREA:" . $areaStr;
    } elseif ($alert['areaType'] === 'ADMINISTRATIVE' && !empty($alert['areaAdministrativeArea'])) {
        $smsText .= "\nAREA:" . $alert['areaAdministrativeArea'];
    }
    
    if (!empty($alert['expiresTimestamp'])) {
        $smsText .= "\nEXPIRES:" . $alert['expiresTimestamp'];
    }
    if (!empty($alert['safetyInstructions'])) {
        $smsText .= "\nINSTRUCTIONS:" . $alert['safetyInstructions'];
    }
    
    $signature = "gateway_sig_stub_" . hash("sha256", $alert['alertId'] . $alert['expiresTimestamp']);
    $smsText .= "\nSIGNATURE:" . $signature;
    
    $primarySIM = SmsNumber::getPrimary();
    $centralNumber = $primarySIM ? $primarySIM['phone_number'] : '+919876543210';
    $smsNumberId = $primarySIM ? (int)$primarySIM['id'] : 1;
    
    $stmt = $db->prepare("SELECT id FROM conversations WHERE sender_phone = :phone LIMIT 1");
    $stmt->execute([':phone' => $recipientPhone]);
    $convRow = $stmt->fetch();
    $conversationId = null;
    if ($convRow) {
        $conversationId = (int)$convRow['id'];
    } else {
        $stmt = $db->prepare("INSERT INTO conversations (sender_phone, sms_number_id, last_message_at) VALUES (:phone, :num_id, NOW())");
        $stmt->execute([':phone' => $recipientPhone, ':num_id' => $smsNumberId]);
        $conversationId = (int)$db->lastInsertId();
    }
    
    $smsId = SmsMessage::create(
        $conversationId,
        $centralNumber,
        $recipientPhone,
        'outgoing',
        $smsText,
        'queued',
        null
    );
    
    SmsMessage::enqueueOutbox($smsId);
    
    $dispatched = SmsService::dispatchOutgoingMessage($smsId);
    
    $status = 'queued';
    $error = null;
    
    $stmt = $db->prepare("SELECT status, last_error FROM sms_outbox WHERE sms_message_id = :sms_message_id LIMIT 1");
    $stmt->execute([':sms_message_id' => $smsId]);
    $outbox = $stmt->fetch();
    if ($outbox) {
        $status = $outbox['status'];
        $error = $outbox['last_error'];
    }
    
    $alertStatus = 'queued';
    if ($status === 'sent') {
        $alertStatus = 'sent';
    } elseif ($status === 'failed') {
        $alertStatus = 'failed';
    }
    
    $stmt = $db->prepare("UPDATE disaster_alerts SET status = :status WHERE alertId = :id");
    $stmt->execute([':status' => $alertStatus, ':id' => $alert['alertId']]);
    
    AuditLogger::log('System', 'SMS_FALLBACK_SEND', 'SMS', 0, "Alert SMS fallback queued/dispatched to " . $recipientPhone . " for alert " . $alert['alertId'] . ". Status: " . $alertStatus);
    
    return [
        'success' => $dispatched,
        'status' => $alertStatus,
        'error' => $error
    ];
}

function sendSmsBroadcast($alert) {
    $db = Database::getConnection();
    $contacts = Contact::getAll();
    if (empty($contacts)) {
        return [
            'success' => false,
            'sent' => 0,
            'failed' => 0,
            'error' => 'No registered contacts found.'
        ];
    }
    
    $sent = 0;
    $failed = 0;
    
    foreach ($contacts as $c) {
        $phone = preg_replace('/[^\d+]/', '', $c['phone_number']);
        if (strpos($phone, '+') !== 0) {
            $phone = '+' . $phone;
        }
        
        // Duplicate check: Alert ID + Recipient Phone unique sending identity
        $stmtDup = $db->prepare("
            SELECT id FROM sms_messages 
            WHERE direction = 'outgoing' AND to_number = :phone AND message_body LIKE :pattern AND status IN ('sent', 'delivered', 'sending') 
            LIMIT 1
        ");
        $stmtDup->execute([':phone' => $phone, ':pattern' => "%\nID:" . $alert['alertId'] . "\n%"]);
        if ($stmtDup->fetch()) {
            continue; // Skip: already sent/sending
        }
        
        // Dispatch
        $result = sendSmsFallback($alert, $phone);
        if ($result['success']) {
            $sent++;
        } else {
            $failed++;
        }
    }
    
    // Update global alert status based on counts
    $alertStatus = 'queued';
    if ($failed === 0) {
        $alertStatus = 'sent';
    } elseif ($sent === 0 && $failed > 0) {
        $alertStatus = 'failed';
    } else {
        $alertStatus = 'partial';
    }
    
    $stmt = $db->prepare("UPDATE disaster_alerts SET status = :status WHERE alertId = :id");
    $stmt->execute([':status' => $alertStatus, ':id' => $alert['alertId']]);
    
    AuditLogger::log('System', 'SMS_FALLBACK_BROADCAST', 'SMS', 0, "Alert SMS fallback broadcast to " . count($contacts) . " contacts. Alert ID: " . $alert['alertId'] . ". Status: " . $alertStatus);
    
    return [
        'success' => $sent > 0,
        'sent' => $sent,
        'failed' => $failed,
        'status' => $alertStatus
    ];
}

$history = Alert::getAll();
$contacts = Contact::getAll();
?>

<!-- UI Layout Grid -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
    
    <!-- LEFT: Compose Alert Form Panel -->
    <div>
        <?php if (!empty($actionMessage)): ?>
            <div style="background: <?php echo $actionSuccess ? 'rgba(57, 211, 83, 0.08)' : 'rgba(255, 153, 0, 0.08)'; ?>; 
                        border: 1px solid <?php echo $actionSuccess ? 'var(--accent-green)' : 'var(--accent-orange)'; ?>; 
                        padding: 12px 18px; border-radius: 6px; margin-bottom: 24px; font-size: 14.5px; font-weight: 500; white-space: pre-wrap;">
                📢 <?php echo htmlspecialchars($actionMessage); ?>
            </div>
        <?php endif; ?>

        <div class="panel">
            <div class="panel-title">
                <span>📢 Compose Emergency Disaster Alert</span>
                <a href="alerts.php?logout=1" style="font-size: 12px; color: var(--accent-orange); text-decoration:none;">Logout</a>
            </div>
            
            <form method="POST" action="alerts.php" enctype="multipart/form-data" id="alert_form" onsubmit="return confirmAlertSubmission(event)">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label for="alert_id">Unique Alert ID</label>
                        <input type="text" name="alert_id" id="alert_id" class="form-control" placeholder="e.g. FIRE-001 (Optional)" />
                    </div>
                    <div class="form-group">
                        <label for="title">Alert Headline / Title</label>
                        <input type="text" name="title" id="title" class="form-control" placeholder="e.g. Severe Forest Fire" required />
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 12px;">
                    <div class="form-group">
                        <label for="disaster_type">Disaster Classification</label>
                        <select name="disaster_type" id="disaster_type" class="form-control">
                            <option value="FIRE">Fire</option>
                            <option value="FLOOD">Flood</option>
                            <option value="EARTHQUAKE">Earthquake</option>
                            <option value="CYCLONE">Cyclone</option>
                            <option value="LANDSLIDE">Landslide</option>
                            <option value="TSUNAMI">Tsunami</option>
                            <option value="EXTREME_WEATHER">Extreme Weather</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="severity">Severity Rank</label>
                        <select name="severity" id="severity" class="form-control" onchange="checkSeverity(this.value)">
                            <option value="EMERGENCY">EMERGENCY (Critical)</option>
                            <option value="WARNING">WARNING (High)</option>
                            <option value="ADVISORY">ADVISORY (Medium)</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 12px;">
                    <div class="form-group">
                        <label for="source_type">Source Scope</label>
                        <select name="source_type" id="source_type" class="form-control">
                            <option value="OFFICIAL">Official Agency</option>
                            <option value="COMMUNITY">Community Report</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="source_authority">Publishing Authority</label>
                        <input type="text" name="source_authority" id="source_authority" class="form-control" value="National Command Center" required />
                    </div>
                </div>

                <div class="form-group" style="margin-top: 12px;">
                    <label for="message">Detailed Warning Text</label>
                    <textarea name="message" id="message" class="form-control" placeholder="Enter full details of warning scope..." required style="min-height: 80px;"></textarea>
                </div>

                <div class="form-group" style="margin-top: 12px;">
                    <label for="safety_instructions">Safety Evacuation Instructions</label>
                    <textarea name="safety_instructions" id="safety_instructions" class="form-control" placeholder="Assemble at designated shelter areas..." required style="min-height: 80px;"></textarea>
                </div>

                <!-- Alert Image Attachment Field with Live Preview -->
                <div class="form-group" style="margin-top: 12px; border: 1px solid rgba(255,255,255,0.05); padding: 12px; border-radius: 6px; background: rgba(0,0,0,0.1);">
                    <label for="alert_image">Alert Banner Image (Optional)</label>
                    <input type="file" name="alert_image" id="alert_image" accept="image/jpeg,image/png,image/webp" class="form-control" onchange="previewImage(event)" />
                    <span style="font-size: 11px; color: var(--text-muted); margin-top: 4px; display: block;">
                        Supported types: JPG, PNG, WebP. Max file size: 2MB.
                    </span>
                    <div id="image-preview-container" style="display:none; margin-top: 10px; border: 1px solid rgba(255,255,255,0.1); padding: 5px; border-radius: 4px; max-width: 220px; background: #000;">
                        <img id="image-preview" src="#" alt="Preview" style="max-width: 100%; border-radius: 4px;" />
                    </div>
                </div>

                <!-- Enhanced Interactive Map-Driven Target Area Selection with 4-Dots Geofence -->
                <div style="border: 1px solid rgba(255,255,255,0.08); padding: 16px; border-radius: 8px; margin-top: 16px; background: rgba(0,0,0,0.25);">
                    <div class="form-group">
                        <label for="area_type" style="font-weight: 700; color: var(--accent-orange); display: flex; align-items: center; gap: 6px; margin-bottom: 8px;">
                            <span>🗺️</span> Target Area Selection Mode
                        </label>
                        <select name="area_type" id="area_type" class="form-control" onchange="toggleAreaFields(this.value)" style="font-size: 14px; font-weight: 600; background: rgba(255,255,255,0.06);">
                            <option value="POLYGON" selected>📐 1. Four Dots / 4-Corner Polygon (Drag 4 Points on Map to Define Boundary)</option>
                            <option value="RADIUS">🎯 2. Coordinates & Area Radius (Center Epicenter + Range Circle on Map)</option>
                            <option value="ADMINISTRATIVE">📍 3. Manual Area Name (Click Map to Auto-Detect City/District/Zone)</option>
                        </select>
                    </div>

                    <!-- Live Interactive Map Canvas for ALL Modes -->
                    <div style="margin-top: 12px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 6px;">
                            <span id="map-instructions" style="font-size: 12px; color: var(--accent-orange); font-weight: 600;">
                                📐 Drag any of the 4 corner dots (P1, P2, P3, P4) on the map to define the hazard zone
                            </span>
                            <div style="display:flex; gap:6px;">
                                <button type="button" id="btn-reset-dots" onclick="resetFourDots()" class="btn-primary" style="padding: 4px 10px; font-size: 11px; background: rgba(255, 153, 0, 0.15); color: var(--accent-orange); border: 1px solid var(--accent-orange);">
                                    🔄 Reset 4 Dots Box
                                </button>
                                <button type="button" onclick="detectAdminLocation()" class="btn-primary" style="padding: 4px 10px; font-size: 11px; background: rgba(255,255,255,0.08); color: var(--text-primary); border: 1px solid rgba(255,255,255,0.15);">
                                    📍 My GPS
                                </button>
                            </div>
                        </div>

                        <!-- Map Canvas -->
                        <div id="alert-map-picker" style="height: 280px; width: 100%; border-radius: 8px; border: 1px solid rgba(255,255,255,0.15); overflow: hidden; background: #111; z-index: 1;"></div>
                    </div>

                    <!-- Hidden JSON field for storing 4-point polygon coordinates -->
                    <input type="hidden" name="polygon_json" id="polygon_json" value="" />

                    <!-- 1. FOUR DOTS / POLYGON CONTROLS -->
                    <div id="area-polygon-fields" style="display: block; margin-top: 14px;">
                        <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); padding: 12px; border-radius: 6px; margin-bottom: 12px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <span style="font-size: 12px; font-weight: 700; color: #fff;">📍 4 Corner Points Coordinates (Live Tracking):</span>
                                <span style="font-size: 11px; color: var(--accent-green);">4-Point Geofence Active</span>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 11.5px; font-feature-settings: 'tnum';">
                                <div style="background: rgba(0,0,0,0.3); padding: 6px 10px; border-radius: 4px; border-left: 3px solid #ff3366;">
                                    <strong style="color: #ff3366;">Dot 1 (NW):</strong> <span id="lbl_p1">--</span>
                                </div>
                                <div style="background: rgba(0,0,0,0.3); padding: 6px 10px; border-radius: 4px; border-left: 3px solid #ff9900;">
                                    <strong style="color: #ff9900;">Dot 2 (NE):</strong> <span id="lbl_p2">--</span>
                                </div>
                                <div style="background: rgba(0,0,0,0.3); padding: 6px 10px; border-radius: 4px; border-left: 3px solid #00e676;">
                                    <strong style="color: #00e676;">Dot 3 (SE):</strong> <span id="lbl_p3">--</span>
                                </div>
                                <div style="background: rgba(0,0,0,0.3); padding: 6px 10px; border-radius: 4px; border-left: 3px solid #00b0ff;">
                                    <strong style="color: #00b0ff;">Dot 4 (SW):</strong> <span id="lbl_p4">--</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. COORDINATES & AREA RADIUS CONTROLS -->
                    <div id="area-radius-fields" style="display: none; margin-top: 14px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                            <div class="form-group">
                                <label>Latitude <span style="color: var(--accent-red);">*</span></label>
                                <input type="number" step="any" name="latitude" id="geo_latitude" class="form-control" placeholder="12.9715" value="12.971598" oninput="updateMapFromInputs()" />
                            </div>
                            <div class="form-group">
                                <label>Longitude <span style="color: var(--accent-red);">*</span></label>
                                <input type="number" step="any" name="longitude" id="geo_longitude" class="form-control" placeholder="77.5945" value="77.594562" oninput="updateMapFromInputs()" />
                            </div>
                            <div class="form-group">
                                <label>Radius (Meters) <span style="color: var(--accent-red);">*</span></label>
                                <input type="number" step="any" name="radius" id="geo_radius" class="form-control" placeholder="2000" value="2000" oninput="updateMapFromInputs()" />
                            </div>
                        </div>

                        <!-- Quick Radius Chips -->
                        <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                            <span style="font-size: 11.5px; color: var(--text-muted); margin-right: 4px;">Quick Radius:</span>
                            <button type="button" onclick="setQuickRadius(500)" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: var(--text-secondary); padding: 3px 10px; border-radius: 4px; font-size: 11px; cursor: pointer;">500 m</button>
                            <button type="button" onclick="setQuickRadius(1000)" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: var(--text-secondary); padding: 3px 10px; border-radius: 4px; font-size: 11px; cursor: pointer;">1 km</button>
                            <button type="button" onclick="setQuickRadius(2000)" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: var(--text-secondary); padding: 3px 10px; border-radius: 4px; font-size: 11px; cursor: pointer;">2 km</button>
                            <button type="button" onclick="setQuickRadius(5000)" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: var(--text-secondary); padding: 3px 10px; border-radius: 4px; font-size: 11px; cursor: pointer;">5 km</button>
                            <button type="button" onclick="setQuickRadius(10000)" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: var(--text-secondary); padding: 3px 10px; border-radius: 4px; font-size: 11px; cursor: pointer;">10 km</button>
                            <button type="button" onclick="setQuickRadius(25000)" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: var(--text-secondary); padding: 3px 10px; border-radius: 4px; font-size: 11px; cursor: pointer;">25 km</button>
                        </div>
                    </div>

                    <!-- 3. MANUAL AREA SELECTION CONTROLS -->
                    <div id="area-admin-fields" style="display:none; margin-top: 14px; padding: 14px; background: rgba(255,255,255,0.03); border-radius: 6px; border: 1px solid rgba(255,255,255,0.05);">
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label for="admin_area" style="font-weight: 700; color: #fff; display: flex; justify-content: space-between; align-items: center;">
                                <span>Selected Area / City / District Name <span style="color: var(--accent-red);">*</span></span>
                                <span id="reverse-geo-status" style="font-size: 11px; color: var(--accent-green); font-weight: normal; display:none;">✓ Area detected from map</span>
                            </label>
                            <input type="text" name="admin_area" id="admin_area" class="form-control" placeholder="Click on the map above or type Area Name (e.g. Bandra West, Indiranagar, Salt Lake Sector 5)" style="font-weight: 600; font-size: 14px; border-color: rgba(255, 153, 0, 0.3);" />
                            <span style="font-size: 11px; color: var(--text-muted); margin-top: 4px; display: block;">
                                💡 <strong>Tip:</strong> Clicking anywhere on the map automatically detects and populates the City, District, or Locality name!
                            </span>
                        </div>

                        <div class="form-group">
                            <label style="font-size: 11.5px; color: var(--text-secondary); margin-bottom: 4px;">Or choose a Quick Preset Zone:</label>
                            <select id="preset_admin_zones" class="form-control" onchange="applyPresetZone(this.value)">
                                <option value="">-- Select Preset Municipal Zone --</option>
                                <option value="Downtown Central Commercial Hub">Downtown Central Commercial Hub</option>
                                <option value="North Industrial Zone & Factories">North Industrial Zone & Factories</option>
                                <option value="South River Basin & Floodplain">South River Basin & Floodplain</option>
                                <option value="Coastal Lowland Sector">Coastal Lowland Sector</option>
                                <option value="West Hill & Landslide Sector">West Hill & Landslide Sector</option>
                                <option value="Metro Airport & Transit Corridor">Metro Airport & Transit Corridor</option>
                                <option value="All City Sectors (Whole Metropolitan Area)">All City Sectors (Whole Metropolitan Area)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 16px;">
                    <div class="form-group">
                        <label for="expiry_hours">Active Expiry Duration</label>
                        <select name="expiry_hours" id="expiry_hours" class="form-control">
                            <option value="1">1 Hour</option>
                            <option value="2">2 Hours</option>
                            <option value="6">6 Hours</option>
                            <option value="12">12 Hours</option>
                            <option value="24">24 Hours</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="recipient_mode">Recipient Mode</label>
                        <select name="recipient_mode" id="recipient_mode" class="form-control" onchange="toggleRecipientField(this.value)">
                            <option value="single">Specific Contact</option>
                            <option value="broadcast">Broadcast to All</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" id="single_recipient_group" style="margin-top: 16px;">
                    <label for="recipient_id">Recipient Contact</label>
                    <select name="recipient_id" id="recipient_id" class="form-control">
                        <option value="">-- Select Recipient Contact --</option>
                        <?php foreach ($contacts as $c): ?>
                            <option value="<?php echo $c['id']; ?>">
                                <?php echo htmlspecialchars($c['name'] . ' (' . $c['phone_number'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" id="broadcast_info_group" style="display:none; background: rgba(255, 153, 0, 0.08); border: 1px dashed var(--accent-orange); padding: 12px; border-radius: 6px; margin-top: 16px;">
                    <p style="margin: 0; font-size: 13px; color: var(--text-secondary); font-weight: 500;">
                        ⚠️ <strong>Broadcast Mode</strong>: This alert will be sent to all registered contacts.<br>
                        Eligible Recipients: <strong id="recipient_count_badge"><?php echo count($contacts); ?></strong>
                    </p>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-top: 24px; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 16px;">
                    <button type="submit" name="save_draft" class="btn-primary" style="background:rgba(255,255,255,0.05); border:1px solid var(--card-border); color:var(--text-primary);">💾 SAVE DRAFT</button>
                    <button type="submit" name="publish_alert" class="btn-primary">🚀 PUBLISH DISASTER ALERT</button>
                </div>
            </form>
        </div>
    </div>

    <!-- RIGHT: Alerts Registry & Status Panel -->
    <div>
        <div class="panel">
            <div class="panel-title">
                <span>📋 Active & History Alerts Registry</span>
            </div>
            
            <div style="max-height: 600px; overflow-y: auto;">
                <table class="table-control" style="width:100%; border-collapse: collapse; text-align: left; font-size:12.5px;">
                    <thead>
                        <tr style="border-bottom: 2px solid rgba(255,255,255,0.05); color: var(--text-muted);">
                            <th style="padding: 10px;">Alert ID</th>
                            <th style="padding: 10px;">Classification</th>
                            <th style="padding: 10px;">Severity</th>
                            <th style="padding: 10px;">Lifecycle</th>
                            <th style="padding: 10px;">SMS Counts</th>
                            <th style="padding: 10px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($history)): ?>
                            <tr>
                                <td colspan="6" style="padding: 20px; text-align:center; color: var(--text-muted);">No alerts registered.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($history as $row): 
                                $severityClass = 'badge-normal';
                                if ($row['severity'] === 'EMERGENCY') $severityClass = 'badge-alert';
                                elseif ($row['severity'] === 'WARNING') $severityClass = 'badge-warning';
                                
                                // Fetch SMS statistics dynamically
                                $alertBodyPattern = "%\nID:" . $row['alertId'] . "\n%";
                                $stmtCount = $db->prepare("SELECT status, COUNT(*) as count FROM sms_messages WHERE direction = 'outgoing' AND message_body LIKE :pattern GROUP BY status");
                                $stmtCount->execute([':pattern' => $alertBodyPattern]);
                                $counts = ['queued' => 0, 'sending' => 0, 'sent' => 0, 'delivered' => 0, 'failed' => 0];
                                while ($countRow = $stmtCount->fetch()) {
                                    $counts[$countRow['status']] = (int)$countRow['count'];
                                }
                                $smsCountStr = "Q:" . $counts['queued'] . " | S:" . $counts['sent'] . " | D:" . $counts['delivered'];
                            ?>
                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.03); vertical-align: middle;">
                                    <td style="padding: 10px; font-weight: 600; font-feature-settings: 'tnum'; cursor: pointer; color: var(--accent-orange);"
                                        onclick="toggleDetailRow('<?php echo htmlspecialchars($row['alertId']); ?>')">
                                        🔍 <?php echo htmlspecialchars($row['alertId']); ?>
                                        <?php if (!empty($row['image_url'])): ?>
                                            <span title="Contains image attachment" style="cursor:help;">🖼️</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 10px;"><?php echo htmlspecialchars($row['disasterType']); ?></td>
                                    <td style="padding: 10px;"><span class="badge <?php echo $severityClass; ?>"><?php echo htmlspecialchars($row['severity']); ?></span></td>
                                    <td style="padding: 10px;">
                                        <span style="font-weight: 700; color: <?php 
                                            echo $row['lifecycleStatus'] === 'PUBLISHED' ? 'var(--accent-green)' : 
                                                ($row['lifecycleStatus'] === 'CANCELLED' ? 'var(--accent-orange)' : 'var(--text-muted)');
                                        ?>;">
                                            <?php echo htmlspecialchars($row['lifecycleStatus']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 10px; font-feature-settings: 'tnum'; font-size: 11px; color: var(--text-secondary);"><?php echo $smsCountStr; ?></td>
                                    <td style="padding: 10px; display:flex; gap: 8px;">
                                        <?php if ($row['lifecycleStatus'] === 'DRAFT'): ?>
                                            <a href="alerts.php?action=publish_draft&id=<?php echo urlencode($row['alertId']); ?>" 
                                               class="btn-primary" style="padding: 4px 8px; font-size:11px; text-decoration:none; background: var(--accent-green); color:#000;">Publish</a>
                                        <?php elseif ($row['lifecycleStatus'] === 'PUBLISHED'): ?>
                                            <a href="alerts.php?action=cancel&id=<?php echo urlencode($row['alertId']); ?>" 
                                               class="btn-primary" style="padding: 4px 8px; font-size:11px; text-decoration:none; background: var(--accent-orange); color:#000;">Cancel</a>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-size:11px;">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr id="details-<?php echo htmlspecialchars($row['alertId']); ?>" style="display: none; background: rgba(0,0,0,0.15);">
                                    <td colspan="6" style="padding: 15px;">
                                        <h4 style="margin: 0 0 10px 0; color: var(--accent-orange); font-size: 13.5px;">
                                            Recipient Dispatch Details — <?php echo htmlspecialchars($row['alertId'] . ' — ' . $row['disasterType']); ?>
                                        </h4>
                                        <div style="max-height: 200px; overflow-y: auto; font-feature-settings: 'tnum'; font-size: 12.5px;">
                                            <?php
                                            // Query recipient level status from sms_messages table
                                            $stmtDetails = $db->prepare("
                                                SELECT m.to_number, m.status, c.name 
                                                FROM sms_messages m 
                                                LEFT JOIN contacts c ON m.to_number = c.phone_number 
                                                WHERE m.direction = 'outgoing' AND m.message_body LIKE :pattern
                                                ORDER BY m.id ASC
                                            ");
                                            $stmtDetails->execute([':pattern' => $alertBodyPattern]);
                                            $detailsList = $stmtDetails->fetchAll();
                                            
                                            if (empty($detailsList)):
                                            ?>
                                                <span style="color: var(--text-muted);">No outgoing dispatch records found.</span>
                                            <?php else: ?>
                                                <table style="width: 100%; border-collapse: collapse;">
                                                    <thead>
                                                        <tr style="border-bottom: 1px dashed rgba(255,255,255,0.05); text-align: left; font-size: 11px; color: var(--text-secondary);">
                                                            <th style="padding: 4px 8px;">Recipient</th>
                                                            <th style="padding: 4px 8px;">Phone Number</th>
                                                            <th style="padding: 4px 8px;">Gateway Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($detailsList as $detail): 
                                                            $statusColor = 'var(--text-muted)';
                                                            if ($detail['status'] === 'sent' || $detail['status'] === 'delivered') $statusColor = 'var(--accent-green)';
                                                            elseif ($detail['status'] === 'failed') $statusColor = 'var(--accent-red)';
                                                        ?>
                                                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.02);">
                                                                <td style="padding: 4px 8px;"><?php echo htmlspecialchars($detail['name'] ?? 'Unknown'); ?></td>
                                                                <td style="padding: 4px 8px;"><?php echo htmlspecialchars($detail['to_number']); ?></td>
                                                                <td style="padding: 4px 8px; font-weight: bold; color: <?php echo $statusColor; ?>;">
                                                                    <?php echo strtoupper(htmlspecialchars($detail['status'])); ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
            let alertMap = null;
    let alertMarker = null;
    let alertCircle = null;
    let currentAreaMode = 'POLYGON';

    // 4 Corner Dots State
    let dotMarkers = [];
    let polygonLayer = null;
    let fourPoints = [
        { lat: 12.9850, lng: 77.5800, label: 'P1 (NW)', color: '#ff3366' },
        { lat: 12.9850, lng: 77.6100, label: 'P2 (NE)', color: '#ff9900' },
        { lat: 12.9600, lng: 77.6100, label: 'P3 (SE)', color: '#00e676' },
        { lat: 12.9600, lng: 77.5800, label: 'P4 (SW)', color: '#00b0ff' }
    ];

    function createDotIcon(label, color) {
        return L.divIcon({
            className: 'custom-dot-pin',
            html: `<div style="background:${color}; width:24px; height:24px; border-radius:50%; border:2px solid #fff; box-shadow:0 0 8px rgba(0,0,0,0.8); display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:800; color:#fff; cursor:grab;">${label.split(' ')[0]}</div>`,
            iconSize: [24, 24],
            iconAnchor: [12, 12]
        });
    }

    function initAlertMap() {
        if (alertMap) return;
        const lat = 12.971598;
        const lng = 77.594562;

        try {
            alertMap = L.map('alert-map-picker').setView([lat, lng], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© OpenStreetMap'
            }).addTo(alertMap);

            // Single Center Marker (for Radius & Manual)
            alertMarker = L.marker([lat, lng], { draggable: true }).addTo(alertMap);
            alertCircle = L.circle([lat, lng], {
                color: '#ff3366',
                fillColor: '#ff3366',
                fillOpacity: 0.25,
                radius: 2000
            }).addTo(alertMap);

            // Initialize 4 Corner Dots
            initFourDots(lat, lng);

            // Map Click handler
            alertMap.on('click', function(e) {
                if (currentAreaMode === 'POLYGON') {
                    // Center the 4 dots box around clicked location
                    centerFourDotsAround(e.latlng.lat, e.latlng.lng);
                } else {
                    handleMapSelection(e.latlng.lat, e.latlng.lng);
                }
            });

            // Center marker drag handler
            alertMarker.on('dragend', function(e) {
                const pos = alertMarker.getLatLng();
                handleMapSelection(pos.lat, pos.lng);
            });

            syncPolygonState();
        } catch (e) {
            console.error("Leaflet init error:", e);
        }
    }

    function initFourDots(centerLat, centerLng) {
        const offset = 0.015;
        fourPoints = [
            { lat: centerLat + offset, lng: centerLng - offset, label: 'P1 (NW)', color: '#ff3366' },
            { lat: centerLat + offset, lng: centerLng + offset, label: 'P2 (NE)', color: '#ff9900' },
            { lat: centerLat - offset, lng: centerLng + offset, label: 'P3 (SE)', color: '#00e676' },
            { lat: centerLat - offset, lng: centerLng - offset, label: 'P4 (SW)', color: '#00b0ff' }
        ];

        dotMarkers.forEach(m => alertMap.removeLayer(m));
        dotMarkers = [];

        fourPoints.forEach((p, idx) => {
            const marker = L.marker([p.lat, p.lng], {
                draggable: true,
                icon: createDotIcon(p.label, p.color)
            }).addTo(alertMap);

            marker.bindTooltip(`<strong>${p.label}</strong> (Drag me)`, { direction: 'top', offset: [0, -10] });

            marker.on('drag', function(e) {
                const pos = marker.getLatLng();
                fourPoints[idx].lat = pos.lat;
                fourPoints[idx].lng = pos.lng;
                redrawPolygon();
            });

            marker.on('dragend', function(e) {
                syncPolygonState();
            });

            dotMarkers.push(marker);
        });

        redrawPolygon();
    }

    function redrawPolygon() {
        const latlngs = fourPoints.map(p => [p.lat, p.lng]);
        if (polygonLayer) {
            polygonLayer.setLatLngs(latlngs);
        } else {
            polygonLayer = L.polygon(latlngs, {
                color: '#ff9900',
                weight: 3,
                dashArray: '5, 5',
                fillColor: '#ff9900',
                fillOpacity: 0.2
            }).addTo(alertMap);
        }

        // Update Coordinate labels
        document.getElementById('lbl_p1').textContent = `${fourPoints[0].lat.toFixed(5)}, ${fourPoints[0].lng.toFixed(5)}`;
        document.getElementById('lbl_p2').textContent = `${fourPoints[1].lat.toFixed(5)}, ${fourPoints[1].lng.toFixed(5)}`;
        document.getElementById('lbl_p3').textContent = `${fourPoints[2].lat.toFixed(5)}, ${fourPoints[2].lng.toFixed(5)}`;
        document.getElementById('lbl_p4').textContent = `${fourPoints[3].lat.toFixed(5)}, ${fourPoints[3].lng.toFixed(5)}`;
    }

    function syncPolygonState() {
        redrawPolygon();
        // Calculate Centroid
        const avgLat = (fourPoints[0].lat + fourPoints[1].lat + fourPoints[2].lat + fourPoints[3].lat) / 4;
        const avgLng = (fourPoints[0].lng + fourPoints[1].lng + fourPoints[2].lng + fourPoints[3].lng) / 4;

        // Populate hidden JSON & center inputs
        const coordsArray = fourPoints.map(p => [parseFloat(p.lat.toFixed(6)), parseFloat(p.lng.toFixed(6))]);
        document.getElementById('polygon_json').value = JSON.stringify(coordsArray);
        document.getElementById('geo_latitude').value = avgLat.toFixed(6);
        document.getElementById('geo_longitude').value = avgLng.toFixed(6);
    }

    function centerFourDotsAround(lat, lng) {
        initFourDots(lat, lng);
        syncPolygonState();
    }

    function resetFourDots() {
        const center = alertMap ? alertMap.getCenter() : { lat: 12.971598, lng: 77.594562 };
        centerFourDotsAround(center.lat, center.lng);
    }

    function handleMapSelection(lat, lng) {
        setMapCoordinates(lat, lng);

        if (currentAreaMode === 'ADMINISTRATIVE') {
            const statusBadge = document.getElementById('reverse-geo-status');
            const adminInput = document.getElementById('admin_area');
            if (statusBadge) {
                statusBadge.style.display = 'inline';
                statusBadge.textContent = '⏳ Resolving area name from map...';
                statusBadge.style.color = 'var(--accent-orange)';
            }

            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=14&addressdetails=1`)
                .then(res => res.json())
                .then(data => {
                    if (data && data.address) {
                        const addr = data.address;
                        const placeName = addr.suburb || addr.neighbourhood || addr.city_district || addr.residential || addr.town || addr.city || addr.county || addr.state_district || data.display_name.split(',')[0];
                        const cityName = addr.city || addr.town || addr.county || addr.state || '';
                        const formatted = placeName + (cityName && placeName !== cityName ? ', ' + cityName : '');
                        
                        adminInput.value = formatted;
                        if (statusBadge) {
                            statusBadge.textContent = '✓ ' + formatted;
                            statusBadge.style.color = 'var(--accent-green)';
                        }
                    }
                })
                .catch(err => {
                    if (statusBadge) {
                        statusBadge.textContent = `📍 Selected Point (${lat.toFixed(4)}, ${lng.toFixed(4)})`;
                        statusBadge.style.color = 'var(--accent-green)';
                    }
                    if (!adminInput.value) {
                        adminInput.value = `Zone at (${lat.toFixed(4)}, ${lng.toFixed(4)})`;
                    }
                });
        }
    }

    function setMapCoordinates(lat, lng) {
        const latInput = document.getElementById('geo_latitude');
        const lngInput = document.getElementById('geo_longitude');
        if (latInput) latInput.value = lat.toFixed(6);
        if (lngInput) lngInput.value = lng.toFixed(6);
        const radius = parseFloat(document.getElementById('geo_radius').value) || 2000;

        if (alertMarker) alertMarker.setLatLng([lat, lng]);
        if (alertCircle) alertCircle.setLatLng([lat, lng]).setRadius(radius);
        if (alertMap) alertMap.panTo([lat, lng]);
    }

    function updateMapFromInputs() {
        const lat = parseFloat(document.getElementById('geo_latitude').value);
        const lng = parseFloat(document.getElementById('geo_longitude').value);
        const radius = parseFloat(document.getElementById('geo_radius').value) || 2000;

        if (!isNaN(lat) && !isNaN(lng)) {
            if (alertMarker) alertMarker.setLatLng([lat, lng]);
            if (alertCircle) alertCircle.setLatLng([lat, lng]).setRadius(radius);
            if (alertMap) alertMap.panTo([lat, lng]);
        }
    }

    function setQuickRadius(meters) {
        document.getElementById('geo_radius').value = meters;
        updateMapFromInputs();
    }

    function detectAdminLocation() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(pos) {
                if (currentAreaMode === 'POLYGON') {
                    centerFourDotsAround(pos.coords.latitude, pos.coords.longitude);
                } else {
                    handleMapSelection(pos.coords.latitude, pos.coords.longitude);
                }
                if (alertMap) alertMap.setView([pos.coords.latitude, pos.coords.longitude], 13);
            }, function(err) {
                alert("Could not retrieve GPS location: " + err.message);
            });
        } else {
            alert("Geolocation is not supported by your browser.");
        }
    }

    function applyPresetZone(val) {
        if (val) {
            document.getElementById('admin_area').value = val;
            const statusBadge = document.getElementById('reverse-geo-status');
            if (statusBadge) {
                statusBadge.style.display = 'inline';
                statusBadge.textContent = '✓ Preset: ' + val;
                statusBadge.style.color = 'var(--accent-green)';
            }
        }
    }

    function toggleAreaFields(type) {
        currentAreaMode = type;
        const polygonDiv = document.getElementById('area-polygon-fields');
        const radiusDiv = document.getElementById('area-radius-fields');
        const adminDiv = document.getElementById('area-admin-fields');
        const instructions = document.getElementById('map-instructions');
        const resetDotsBtn = document.getElementById('btn-reset-dots');
        if (!radiusDiv || !adminDiv || !polygonDiv) return;

        if (type === 'POLYGON') {
            polygonDiv.style.display = 'block';
            radiusDiv.style.display = 'none';
            adminDiv.style.display = 'none';
            if (resetDotsBtn) resetDotsBtn.style.display = 'inline-block';
            if (instructions) instructions.textContent = '📐 Drag any of the 4 corner dots (P1, P2, P3, P4) on map to define perimeter';

            // Show 4 dots & polygon layer, hide circle/center marker
            dotMarkers.forEach(m => { if (alertMap && !alertMap.hasLayer(m)) alertMap.addLayer(m); });
            if (polygonLayer && alertMap && !alertMap.hasLayer(polygonLayer)) alertMap.addLayer(polygonLayer);
            if (alertMarker && alertMap && alertMap.hasLayer(alertMarker)) alertMap.removeLayer(alertMarker);
            if (alertCircle && alertMap && alertMap.hasLayer(alertCircle)) alertMap.removeLayer(alertCircle);
            syncPolygonState();
        } else if (type === 'RADIUS') {
            polygonDiv.style.display = 'none';
            radiusDiv.style.display = 'block';
            adminDiv.style.display = 'none';
            if (resetDotsBtn) resetDotsBtn.style.display = 'none';
            if (instructions) instructions.textContent = '🎯 Click on map to set Epicenter & adjust Radius';

            // Hide 4 dots, show circle & center marker
            dotMarkers.forEach(m => { if (alertMap && alertMap.hasLayer(m)) alertMap.removeLayer(m); });
            if (polygonLayer && alertMap && alertMap.hasLayer(polygonLayer)) alertMap.removeLayer(polygonLayer);
            if (alertMarker && alertMap && !alertMap.hasLayer(alertMarker)) alertMap.addLayer(alertMarker);
            if (alertCircle && alertMap && !alertMap.hasLayer(alertCircle)) alertMap.addLayer(alertCircle);
            if (alertCircle) alertCircle.setStyle({ opacity: 1, fillOpacity: 0.25 });
        } else {
            polygonDiv.style.display = 'none';
            radiusDiv.style.display = 'none';
            adminDiv.style.display = 'block';
            if (resetDotsBtn) resetDotsBtn.style.display = 'none';
            if (instructions) instructions.textContent = '📍 Click anywhere on map to auto-select City/District name';

            // Show center marker only
            dotMarkers.forEach(m => { if (alertMap && alertMap.hasLayer(m)) alertMap.removeLayer(m); });
            if (polygonLayer && alertMap && alertMap.hasLayer(polygonLayer)) alertMap.removeLayer(polygonLayer);
            if (alertMarker && alertMap && !alertMap.hasLayer(alertMarker)) alertMap.addLayer(alertMarker);
            if (alertCircle && alertMap && alertMap.hasLayer(alertCircle)) alertMap.removeLayer(alertCircle);
        }

        setTimeout(function() {
            if (alertMap) {
                alertMap.invalidateSize();
            } else {
                initAlertMap();
            }
        }, 150);
    }

    document.addEventListener('DOMContentLoaded', function() {
        const areaTypeSelect = document.getElementById('area_type');
        if (areaTypeSelect) {
            toggleAreaFields(areaTypeSelect.value);
        }
    });

    function checkSeverity(value) {
        // No-op in Phase 2
    }

    function toggleRecipientField(value) {
        const singleGrp = document.getElementById('single_recipient_group');
        const broadcastGrp = document.getElementById('broadcast_info_group');
        const recipientIdSelect = document.getElementById('recipient_id');
        
        if (value === 'single') {
            singleGrp.style.display = 'block';
            broadcastGrp.style.display = 'none';
            recipientIdSelect.required = true;
        } else {
            singleGrp.style.display = 'none';
            broadcastGrp.style.display = 'block';
            recipientIdSelect.required = false;
            recipientIdSelect.value = ''; // Reset
        }
    }

    function confirmAlertSubmission(event) {
        const mode = document.getElementById('recipient_mode').value;
        if (mode === 'broadcast') {
            const count = <?php echo count($contacts); ?>;
            return confirm("Send this emergency alert to " + count + " contacts?");
        }
        return true;
    }

    function toggleDetailRow(id) {
        const row = document.getElementById('details-' + id);
        if (row.style.display === 'none') {
            row.style.display = 'table-row';
        } else {
            row.style.display = 'none';
        }
    }

    function previewImage(event) {
        var reader = new FileReader();
        reader.onload = function(){
            var output = document.getElementById('image-preview');
            output.src = reader.result;
            document.getElementById('image-preview-container').style.display = 'block';
        };
        if (event.target.files.length > 0) {
            reader.readAsDataURL(event.target.files[0]);
        } else {
            document.getElementById('image-preview-container').style.display = 'none';
        }
    }
</script>

<?php
require_once __DIR__ . '/layout_footer.php';
?>
