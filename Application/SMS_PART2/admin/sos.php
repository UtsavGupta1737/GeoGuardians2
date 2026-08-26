<?php
/**
 * SOS Active Alerts Center
 * 
 * Refactored to group emergency incidents, display chronological conversation chat bubbles,
 * rich victim dossier, and real-time live auto-refresh polling.
 */

$page_title = 'SOS Alerts Hub';

// Include layouts and configurations
define('SECURE_ACCESS', true);
require_once __DIR__ . '/layout_header.php';
require_once __DIR__ . '/../models/SmsMessage.php';
require_once __DIR__ . '/../models/SosRequest.php';
require_once __DIR__ . '/../models/ExtractedData.php';
require_once __DIR__ . '/../services/SmsService.php'; 
require_once __DIR__ . '/../services/SmsParser.php'; 
require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();

// --- HANDLE POST ACTIONS (Priority Overrides & Response Dispatches) ---
$actionMessage = '';
$actionSuccess = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sosId = isset($_POST['sos_id']) ? (int)$_POST['sos_id'] : 0;
    
    if ($sosId > 0) {
        $sosDetail = SosRequest::getById($sosId);
        
        if ($sosDetail) {
            // 1. Update Priority
            if (isset($_POST['priority'])) {
                $newPriority = strtoupper(trim($_POST['priority']));
                if (in_array($newPriority, ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'])) {
                    if ($sosDetail['priority'] !== $newPriority) {
                        SosRequest::updatePriority($sosId, $newPriority);
                        AuditLogger::log('Operator', 'SOS_PRIORITY_CHANGED', 'SOS', $sosId, "SOS priority changed from " . $sosDetail['priority'] . " to " . $newPriority);
                    }
                }
            }
            
            // 2. Dispatch Outbound SMS Response
            if (!empty($_POST['reply_message'])) {
                $replyText = trim($_POST['reply_message']);
                $victimNumber = $sosDetail['sender_phone'];
                
                // Find central SIM number associated with the conversation
                $stmt = $db->prepare("SELECT phone_number FROM sms_numbers WHERE id = (SELECT sms_number_id FROM conversations WHERE id = :conv_id)");
                $stmt->execute([':conv_id' => $sosDetail['conversation_id']]);
                $numRow = $stmt->fetch();
                $centralNumber = $numRow ? $numRow['phone_number'] : '+919876543210';
                
                // Create SMS Message record in db
                $outgoingSmsId = SmsMessage::create(
                    $sosDetail['conversation_id'],
                    $centralNumber, 
                    $victimNumber,  
                    'outgoing',
                    $replyText,
                    'queued',
                    null
                );
                
                // Add to queue
                SmsMessage::enqueueOutbox($outgoingSmsId);
                
                // Log audit trail
                AuditLogger::log('Operator', 'SMS_ENQUEUED', 'SMS', $outgoingSmsId, "Manual response enqueued to " . $victimNumber . " for SOS-" . $sosId);
                
                // Try sending immediately
                $sent = SmsService::dispatchOutgoingMessage($outgoingSmsId);
                
                if ($sent) {
                    $actionMessage = "Incident updated and reply sent successfully.";
                } else {
                    $actionMessage = "Incident updated. Reply queued (Gateway offline).";
                }
            } else {
                $actionMessage = "Incident properties updated successfully.";
            }
            
            // Refresh details
            $sosDetail = SosRequest::getById($sosId);
        }
    }
}

// --- EXTRACT FILTERS FROM QUERY ---
$filters = [
    'priority' => $_GET['priority'] ?? 'ALL',
    'disaster_type' => $_GET['disaster_type'] ?? 'ALL'
];

// Fetch alerts list matching filters
$alerts = SosRequest::getAll($filters);

// Determine which alert to display in the Details Panel
$selectedId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$activeAlert = null;

if (!empty($alerts)) {
    if ($selectedId > 0) {
        foreach ($alerts as $a) {
            if ((int)$a['id'] === $selectedId) {
                $activeAlert = $a;
                break;
            }
        }
    }
    
    if ($activeAlert === null) {
        $activeAlert = $alerts[0];
    }
}

// Fetch extraction metadata & conversation logs
$extractedMeta = null;
$conversationMessages = [];
if ($activeAlert) {
    // Locate the latest incoming message ID of this conversation to map metadata
    $stmt = $db->prepare("SELECT id, message_body FROM sms_messages WHERE conversation_id = :conv_id AND direction = 'incoming' ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([':conv_id' => $activeAlert['conversation_id']]);
    $latestIncomingMsg = $stmt->fetch();
    
    if ($latestIncomingMsg) {
        $extractedMeta = ExtractedData::getByMessageId($latestIncomingMsg['id']);
    }
    
    // Fetch all messages (incoming and outgoing) for chronological thread
    $stmt = $db->prepare("SELECT * FROM sms_messages WHERE conversation_id = :conv_id ORDER BY created_at ASC");
    $stmt->execute([':conv_id' => $activeAlert['conversation_id']]);
    $conversationMessages = $stmt->fetchAll();
}
?>

<!-- Action Feedback Alerts -->
<?php if (!empty($actionMessage)): ?>
    <div style="background: rgba(0, 240, 255, 0.08); border: 1px solid var(--accent-blue); padding: 12px 18px; border-radius: 6px; margin-bottom: 24px; font-size: 14px; font-weight: 500; display: flex; align-items: center; justify-content: space-between;">
        <span>🔔 <?php echo htmlspecialchars($actionMessage); ?></span>
        <button onclick="this.parentElement.remove()" style="background:none; border:none; color:var(--accent-blue); font-weight:700; cursor:pointer;">✕</button>
    </div>
<?php endif; ?>

<!-- Top Filtering Controls & Live Stream Ticker -->
<section class="panel" style="margin-bottom: 24px; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
    <form method="GET" action="sos.php" style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
        <span style="font-size: 12px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase;">🔍 Filters:</span>
        
        <div style="display:flex; flex-direction:column; gap:4px;">
            <label style="font-size: 10px; color: var(--text-muted); font-weight:700; text-transform:uppercase;">Incident Severity</label>
            <select name="priority" class="form-control" onchange="this.form.submit()" style="padding: 6px 12px; font-size:12.5px; width: 140px; background-color: var(--bg-primary);">
                <option value="ALL" <?php echo $filters['priority'] == 'ALL' ? 'selected' : ''; ?>>All Priorities</option>
                <option value="CRITICAL" <?php echo $filters['priority'] == 'CRITICAL' ? 'selected' : ''; ?>>Critical</option>
                <option value="HIGH" <?php echo $filters['priority'] == 'HIGH' ? 'selected' : ''; ?>>High</option>
                <option value="MEDIUM" <?php echo $filters['priority'] == 'MEDIUM' ? 'selected' : ''; ?>>Medium</option>
                <option value="LOW" <?php echo $filters['priority'] == 'LOW' ? 'selected' : ''; ?>>Low</option>
            </select>
        </div>

        <div style="display:flex; flex-direction:column; gap:4px;">
            <label style="font-size: 10px; color: var(--text-muted); font-weight:700; text-transform:uppercase;">Disaster Category</label>
            <select name="disaster_type" class="form-control" onchange="this.form.submit()" style="padding: 6px 12px; font-size:12.5px; width: 140px; background-color: var(--bg-primary);">
                <option value="ALL" <?php echo $filters['disaster_type'] == 'ALL' ? 'selected' : ''; ?>>All Categories</option>
                <option value="FLOOD" <?php echo $filters['disaster_type'] == 'FLOOD' ? 'selected' : ''; ?>>Flood</option>
                <option value="FIRE" <?php echo $filters['disaster_type'] == 'FIRE' ? 'selected' : ''; ?>>Fire</option>
                <option value="EARTHQUAKE" <?php echo $filters['disaster_type'] == 'EARTHQUAKE' ? 'selected' : ''; ?>>Earthquake</option>
                <option value="MEDICAL" <?php echo $filters['disaster_type'] == 'MEDICAL' ? 'selected' : ''; ?>>Medical</option>
                <option value="ACCIDENT" <?php echo $filters['disaster_type'] == 'ACCIDENT' ? 'selected' : ''; ?>>Accident</option>
            </select>
        </div>
        
        <?php if ($filters['priority'] !== 'ALL' || $filters['disaster_type'] !== 'ALL'): ?>
            <a href="sos.php" style="color: var(--accent-red); font-size:12px; font-weight:700; text-decoration:none; margin-top: 14px;">✕ CLEAR FILTERS</a>
        <?php endif; ?>
    </form>

    <div style="display: flex; align-items: center; gap: 12px;">
        <span id="liveStatusBadge" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; background: rgba(0, 240, 255, 0.1); border: 1px solid rgba(0, 240, 255, 0.3); border-radius: 20px; font-size: 12px; color: var(--accent-blue); font-weight: 700;">
            <span style="width: 8px; height: 8px; background: #00f0ff; border-radius: 50%; display: inline-block; animation: pulse 1.5s infinite;"></span>
            LIVE STREAM ACTIVE
        </span>
        <button onclick="location.reload()" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.15); color: #fff; padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer;">
            🔄 Refresh Feed
        </button>
    </div>
</section>

<!-- Alerts Workspace Area -->
<section class="sos-center-layout">
    
    <!-- Left Pane: Alerts list Stream -->
    <div class="sos-stream">
        <?php if (empty($alerts)): ?>
            <div style="text-align:center; padding:60px 20px; color: var(--text-muted); background:var(--card-bg); border-radius:8px; border:1px dashed rgba(255,255,255,0.05);">
                <span style="font-size:32px; display:block; margin-bottom:12px;">📭</span>
                No SOS alerts logged matching these filters.
            </div>
        <?php else: ?>
            <?php foreach ($alerts as $item): ?>
                <?php 
                $isActive = $activeAlert && ((int)$activeAlert['id'] === (int)$item['id']);
                ?>
                <div class="sos-card <?php echo $isActive ? 'active' : ''; ?>" data-id="<?php echo $item['id']; ?>" onclick="location.href='sos.php?id=<?php echo $item['id']; ?>&priority=<?php echo $filters['priority']; ?>&disaster_type=<?php echo $filters['disaster_type']; ?>'">
                    <div class="sos-card-header">
                        <span class="sos-card-id">SOS-<?php echo $item['id']; ?></span>
                        <span class="priority-tag <?php echo strtolower($item['priority']); ?>"><?php echo htmlspecialchars($item['priority']); ?></span>
                    </div>
                    <div class="sos-card-title"><?php echo htmlspecialchars($item['sender_phone']); ?></div>
                    <div style="font-size: 12.5px; color: var(--text-primary); margin-top: 6px; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
                        <?php echo htmlspecialchars($item['message_body'] ?? 'No messages.'); ?>
                    </div>
                    <div class="sos-card-meta">
                        <span style="text-transform: capitalize; color: var(--accent-blue); font-weight:600;">🏷️ <?php echo htmlspecialchars($item['disaster_type']); ?></span>
                        <span style="color:var(--text-muted);"><?php echo date('H:i | M d', strtotime($item['created_at'])); ?></span>
                    </div>
                    <div style="margin-top: 10px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid rgba(255,255,255,0.03); padding-top: 8px;">
                        <span style="font-size: 11px; color: var(--text-muted);">📍 <?php echo htmlspecialchars($item['latitude'] . ',' . $item['longitude'] ?? 'Unknown'); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Right Pane: Active Detail Drawer -->
    <div class="sos-detail-panel">
        <?php if ($activeAlert === null): ?>
            <div style="text-align:center; padding:120px 20px; color: var(--text-muted); flex-grow:1; display:flex; flex-direction:column; justify-content:center; align-items:center;">
                <span style="font-size:48px; display:block; margin-bottom:16px;">📋</span>
                Select an alert card from the feed to view emergency details and coordinate dispatch actions.
            </div>
        <?php else: ?>
            <!-- Detail Header -->
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom:16px;">
                <div>
                    <h3 style="font-size: 20px; color: var(--accent-red);">🚨 Emergency Alert SOS-<?php echo $activeAlert['id']; ?></h3>
                    <span style="font-size: 12px; color: var(--text-secondary);">Citizen Number: <strong><?php echo htmlspecialchars($activeAlert['sender_phone']); ?></strong></span>
                </div>
            </div>

            <!-- Citizen Profile & Medical Dossier Card -->
            <?php 
            $metaJson = [];
            if ($extractedMeta && !empty($extractedMeta['extracted_json'])) {
                $metaJson = json_decode($extractedMeta['extracted_json'], true) ?: [];
            }
            
            // Dynamic on-the-fly extraction fallback from message text
            $latestText = !empty($conversationMessages) ? end($conversationMessages)['message_body'] : ($activeAlert['message_body'] ?? '');
            $parsedFallback = SmsParser::parse($latestText);
            $parsedJson = $parsedFallback['extracted_json'] ?? [];

            $victimName = $metaJson['victim_name'] ?? $parsedJson['victim_name'] ?? $metaJson['person_name'] ?? $parsedJson['person_name'] ?? null;
            $victimPhone = $metaJson['victim_phone'] ?? $parsedJson['victim_phone'] ?? $activeAlert['sender_phone'];
            $bloodGroup = $metaJson['blood_group'] ?? $parsedJson['blood_group'] ?? null;
            $age = $metaJson['age'] ?? $parsedJson['age'] ?? null;
            $medicalInfo = $metaJson['medical_info'] ?? $parsedJson['medical_info'] ?? null;
            $emContact = $metaJson['emergency_contact'] ?? $parsedJson['emergency_contact'] ?? null;
            $homeAddress = $metaJson['home_address'] ?? $parsedJson['home_address'] ?? null;
            $mapUrl = $metaJson['map_url'] ?? $parsedJson['map_url'] ?? ($activeAlert['latitude'] ? "https://maps.google.com/?q=" . $activeAlert['latitude'] . "," . $activeAlert['longitude'] : null);
            ?>

            <div class="detail-section" style="background: rgba(255, 51, 102, 0.05); border: 1px solid rgba(255, 51, 102, 0.2); padding: 16px; border-radius: 8px;">
                <div class="detail-section-title" style="color: var(--accent-red); margin-bottom: 12px; font-weight: 700; display: flex; justify-content: space-between; align-items: center;">
                    <span>👤 Victim Profile & Medical Dossier</span>
                    <?php if ($mapUrl): ?>
                        <a href="<?php echo htmlspecialchars($mapUrl); ?>" target="_blank" style="font-size: 11px; color: var(--accent-blue); text-decoration: none; padding: 2px 8px; background: rgba(0, 240, 255, 0.1); border: 1px solid rgba(0, 240, 255, 0.3); border-radius: 4px; font-weight: 600;">
                            📍 Open in Google Maps ↗
                        </a>
                    <?php endif; ?>
                </div>

                <div class="detail-grid" style="grid-template-columns: repeat(3, 1fr); gap: 12px;">
                    <div>
                        <span class="detail-item-title">Victim Name</span>
                        <div class="detail-item-value" style="font-weight: 700; color: #fff;">
                            <?php echo htmlspecialchars($victimName ?: 'Citizen (' . $victimPhone . ')'); ?>
                        </div>
                    </div>
                    <div>
                        <span class="detail-item-title">Blood Group</span>
                        <div class="detail-item-value" style="color: var(--accent-red); font-weight: 800;">
                            🩸 <?php echo htmlspecialchars($bloodGroup ?: 'Not Specified'); ?>
                        </div>
                    </div>
                    <div>
                        <span class="detail-item-title">Age</span>
                        <div class="detail-item-value">
                            🎂 <?php echo htmlspecialchars($age ? $age . ' Yrs' : 'N/A'); ?>
                        </div>
                    </div>
                    <?php if ($medicalInfo): ?>
                    <div style="grid-column: span 3; background: rgba(0,0,0,0.25); padding: 8px 12px; border-radius: 6px; border-left: 3px solid var(--accent-orange);">
                        <span class="detail-item-title" style="color: var(--accent-orange);">⚠️ Critical Medical Conditions / Allergies</span>
                        <div class="detail-item-value" style="color: #fff; font-size: 13px; margin-top: 2px;">
                            <?php echo htmlspecialchars($medicalInfo); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($emContact): ?>
                    <div style="grid-column: span 3;">
                        <span class="detail-item-title">Emergency Contact / Family</span>
                        <div class="detail-item-value" style="color: var(--text-primary); font-size: 12.5px;">
                            📞 <?php echo htmlspecialchars($emContact); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($homeAddress): ?>
                    <div style="grid-column: span 3;">
                        <span class="detail-item-title">Registered Home Address</span>
                        <div class="detail-item-value" style="color: var(--text-secondary); font-size: 12px;">
                            🏠 <?php echo htmlspecialchars($homeAddress); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Chronological Chat Conversation Log -->
            <div class="detail-section" style="border-color: rgba(0, 240, 255, 0.05); padding: 16px;">
                <div class="detail-section-title" style="color: var(--accent-blue); margin-bottom: 12px; font-weight:700;">Chronological Conversation Log</div>
                
                <div class="chat-thread" id="chatThreadContainer" style="display:flex; flex-direction:column; gap:12px; max-height:280px; overflow-y:auto; padding:12px; background:rgba(0,0,0,0.3); border-radius:6px; border:1px solid rgba(255,255,255,0.03);">
                    <?php if (empty($conversationMessages)): ?>
                        <div style="text-align:center; color:var(--text-muted); padding:30px 0; font-size:13px;">No messages in this thread.</div>
                    <?php else: ?>
                        <?php foreach ($conversationMessages as $chat): ?>
                            <?php 
                            $isIncoming = ($chat['direction'] === 'incoming');
                            $bubbleAlign = $isIncoming ? 'flex-start' : 'flex-end';
                            $bubbleBg = $isIncoming ? 'rgba(255,255,255,0.03)' : 'rgba(0, 240, 255, 0.08)';
                            $bubbleBorder = $isIncoming ? '1px solid rgba(255,255,255,0.05)' : '1px solid rgba(0, 240, 255, 0.15)';
                            $textColor = $isIncoming ? 'var(--text-primary)' : 'var(--text-primary)';
                            ?>
                            <div style="align-self: <?php echo $bubbleAlign; ?>; max-width: 80%; display: flex; flex-direction: column; gap: 4px;">
                                <div style="padding: 10px 14px; border-radius: 8px; background: <?php echo $bubbleBg; ?>; border: <?php echo $bubbleBorder; ?>; color: <?php echo $textColor; ?>; font-size: 13px; line-height: 1.45; white-space: pre-wrap;">
                                    <?php echo htmlspecialchars($chat['message_body']); ?>
                                </div>
                                <span style="font-size: 9.5px; color: var(--text-muted); text-align: <?php echo $isIncoming ? 'left' : 'right'; ?>; font-feature-settings: 'tnum';">
                                    <?php echo date('H:i', strtotime($chat['created_at'])); ?> 
                                    <?php if (!$isIncoming): ?>
                                         • Status: <strong style="text-transform:uppercase; color: <?php echo $chat['status'] === 'sent' ? 'var(--accent-green)' : 'var(--accent-orange)'; ?>"><?php echo htmlspecialchars($chat['status']); ?></strong>
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Parsed Incident Variables -->
            <div class="detail-section">
                <div class="detail-section-title">Parsed Incident Variables</div>
                <div class="detail-grid">
                    <div>
                        <span class="detail-item-title">Disaster Type</span>
                        <div class="detail-item-value" style="text-transform: capitalize; color: var(--accent-blue);">
                            🏷️ <?php echo htmlspecialchars($activeAlert['disaster_type']); ?>
                        </div>
                    </div>
                    <div>
                        <span class="detail-item-title">Trapped / Affected Count</span>
                        <div class="detail-item-value" style="color: var(--accent-orange);">
                            👥 <?php echo htmlspecialchars($activeAlert['people_count']); ?> Persons
                        </div>
                    </div>
                    <div>
                        <span class="detail-item-title">Injured / Casualty Count</span>
                        <div class="detail-item-value" style="color: var(--accent-red);">
                            🚑 <?php echo htmlspecialchars($activeAlert['injured_count']); ?> Injured
                        </div>
                    </div>
                    <div>
                        <span class="detail-item-title">Resource Needs</span>
                        <div class="detail-item-value">
                            🛠️ <?php echo htmlspecialchars($activeAlert['help_required'] ?? 'Rescue coordination'); ?>
                        </div>
                    </div>
                    <div>
                        <span class="detail-item-title">GPS Coordinates</span>
                        <div class="detail-item-value" style="font-size:12.5px; font-feature-settings: 'tnum';">
                            🌐 <?php echo $activeAlert['latitude'] ? $activeAlert['latitude'] . ', ' . $activeAlert['longitude'] : 'No coordinates available'; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pipeline Analysis & Metadata -->
            <div class="detail-section">
                <div class="detail-section-title">Ingestion Metadata</div>
                <div class="detail-grid" style="grid-template-columns: repeat(2, 1fr);">
                    <div>
                        <span class="detail-item-title">Extraction Method</span>
                        <div class="detail-item-value" style="font-size:12px; text-transform:uppercase;">
                            <?php echo $extractedMeta ? str_replace('_', ' ', $extractedMeta['extraction_method']) : 'Rule heuristics'; ?>
                        </div>
                    </div>
                    <div>
                        <span class="detail-item-title">AI Confidence Score</span>
                        <div class="detail-item-value" style="font-size:13px; font-feature-settings: 'tnum';">
                            🎯 <?php echo $extractedMeta ? number_format($extractedMeta['confidence'] * 100, 1) . '%' : '95.0%'; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Controls Form -->
            <form method="POST" action="sos.php?id=<?php echo $activeAlert['id']; ?>&priority=<?php echo $filters['priority']; ?>&disaster_type=<?php echo $filters['disaster_type']; ?>" class="detail-section" style="border-color: rgba(0, 240, 255, 0.1);">
                <div class="detail-section-title" style="color: var(--accent-blue);">Coordinate Incident Rescue Response</div>
                
                <input type="hidden" name="sos_id" value="<?php echo $activeAlert['id']; ?>" />
                
                <div style="display:flex; gap: 20px; margin-bottom: 20px;">
                    <div style="flex:1;">
                        <label style="display:block; font-size:11px; color:var(--text-secondary); text-transform:uppercase; margin-bottom:8px;">Severity Override</label>
                        <select name="priority" class="form-control" style="background-color: var(--bg-primary);">
                            <option value="LOW" <?php echo $activeAlert['priority'] == 'LOW' ? 'selected' : ''; ?>>Low Priority</option>
                            <option value="MEDIUM" <?php echo $activeAlert['priority'] == 'MEDIUM' ? 'selected' : ''; ?>>Medium Priority</option>
                            <option value="HIGH" <?php echo $activeAlert['priority'] == 'HIGH' ? 'selected' : ''; ?>>High Priority</option>
                            <option value="CRITICAL" <?php echo $activeAlert['priority'] == 'CRITICAL' ? 'selected' : ''; ?>>Critical emergency</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label style="display:block; font-size:11px; color:var(--text-secondary); text-transform:uppercase; margin-bottom:8px;">Send Response SMS (Sent via +91 SIM Gateway)</label>
                    <textarea name="reply_message" class="form-control" placeholder="Type instructions or rescue statuses here (e.g. 'Rescue team assigned, arriving in 15 mins. Stay on high ground.')" style="min-height: 80px; resize: vertical; background-color: var(--bg-primary);" required></textarea>
                </div>
                
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:11px; color:var(--text-muted);">This text will route directly to the phone.</span>
                    <button type="submit" class="btn-primary">💾 SAVE & DISPATCH RESPONSE</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>

<!-- Auto-polling script to check for incoming SOS incidents and refresh feed seamlessly -->
<script>
    window.sosAlertsData = <?php echo json_encode($alerts); ?>;
    let lastKnownSosCount = <?php echo count($alerts); ?>;
    let lastKnownMsgCount = <?php echo count($conversationMessages); ?>;

    // Check every 4 seconds for new incoming SOS requests or messages
    setInterval(function() {
        // Do not auto-reload if operator is typing in reply textarea
        const textarea = document.querySelector('textarea[name="reply_message"]');
        if (textarea && textarea === document.activeElement && textarea.value.trim().length > 0) {
            return;
        }

        fetch('api/sms/poll_sos.php?last_id=<?php echo $activeAlert ? $activeAlert['id'] : 0; ?>')
            .then(res => res.json())
            .then(data => {
                if (data && data.success) {
                    if (data.total_sos !== lastKnownSosCount || data.message_count !== lastKnownMsgCount) {
                        console.log('New SOS alert / message detected. Updating feed...');
                        location.reload();
                    }
                }
            })
            .catch(err => console.log('Poll check silent catch:', err));
    }, 4000);
</script>

<style>
@keyframes pulse {
    0% { transform: scale(0.95); opacity: 0.7; }
    50% { transform: scale(1.15); opacity: 1; }
    100% { transform: scale(0.95); opacity: 0.7; }
}
</style>

<script src="js/dashboard.js"></script>

<?php
require_once __DIR__ . '/layout_footer.php';
?>