<?php
/**
 * Manual Compose / Broadcast SMS
 */

$page_title = 'Send Broadcast';

// Include layouts and configurations
define('SECURE_ACCESS', true);
require_once __DIR__ . '/layout_header.php';
require_once __DIR__ . '/../models/SmsMessage.php';
require_once __DIR__ . '/../services/SmsService.php'; 
require_once __DIR__ . '/../config/database.php';

$toPrefill = $_GET['to'] ?? '';
$actionMessage = '';
$actionSuccess = true;

// Handle manual compose POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $toNumber = trim($_POST['to_number']);
    $messageText = trim($_POST['message_body']);
    
    if (!empty($toNumber) && !empty($messageText)) {
        // Clean number representation
        $toNumber = preg_replace('/[^\d+]/', '', $toNumber);
        
        try {
            $db = Database::getConnection();
            
            // Find or create conversation for manual broadcast
            $stmt = $db->prepare("SELECT id FROM conversations WHERE sender_phone = :phone LIMIT 1");
            $stmt->execute([':phone' => $toNumber]);
            $convRow = $stmt->fetch();
            $conversationId = null;
            
            if ($convRow) {
                $conversationId = (int)$convRow['id'];
                $db->prepare("UPDATE conversations SET last_message_at = NOW() WHERE id = :id")->execute([':id' => $conversationId]);
            } else {
                // Find primary central registered number
                $stmt = $db->query("SELECT id FROM sms_numbers WHERE is_primary = 1 LIMIT 1");
                $numRow = $stmt->fetch();
                $smsNumberId = $numRow ? (int)$numRow['id'] : 1;
                
                $stmt = $db->prepare("INSERT INTO conversations (sender_phone, sms_number_id, last_message_at) 
                                      VALUES (:from, :num_id, NOW())");
                $stmt->execute([':from' => $toNumber, ':num_id' => $smsNumberId]);
                $conversationId = (int)$db->lastInsertId();
            }
            
            // Get central phone number
            $stmt = $db->prepare("SELECT phone_number FROM sms_numbers WHERE id = (SELECT sms_number_id FROM conversations WHERE id = :conv_id)");
            $stmt->execute([':conv_id' => $conversationId]);
            $numRow = $stmt->fetch();
            $centralNumber = $numRow ? $numRow['phone_number'] : '+919876543210';
            
            // 1. Create message entry
            $smsId = SmsMessage::create(
                $conversationId,
                $centralNumber,
                $toNumber,
                'outgoing',
                $messageText,
                'queued',
                null
            );
            
            // 2. Queue in outbox
            SmsMessage::enqueueOutbox($smsId);
            
            // 3. Log event
            AuditLogger::log('Operator', 'SMS_ENQUEUED', 'SMS', $smsId, "Manual SMS enqueued to " . $toNumber);
            
            // 4. Dispatch immediately
            $dispatched = SmsService::dispatchOutgoingMessage($smsId);
            
            if ($dispatched) {
                $actionMessage = "SMS successfully dispatched through gateway device.";
                $actionSuccess = true;
            } else {
                $actionMessage = "SMS added to queue. Gateway device is currently offline (buffered for auto-retry).";
                $actionSuccess = false;
            }
        } catch (Exception $e) {
            $actionMessage = "Error dispatching message: " . $e->getMessage();
            $actionSuccess = false;
        }
    } else {
        $actionMessage = "Please complete all fields.";
        $actionSuccess = false;
    }
}

require_once __DIR__ . '/../models/SmsNumber.php';

$primarySIM = SmsNumber::getPrimary();
$fromDisplay = $primarySIM ? $primarySIM['phone_number'] . ' (' . $primarySIM['alias'] . ')' : 'No Primary SIM Registered';
?>

<div style="max-width: 600px; margin: 0 auto;">
    
    <!-- Action feedback notifications -->
    <?php if (!empty($actionMessage)): ?>
        <div style="background: <?php echo $actionSuccess ? 'rgba(57, 211, 83, 0.08)' : 'rgba(255, 153, 0, 0.08)'; ?>; 
                    border: 1px solid <?php echo $actionSuccess ? 'var(--accent-green)' : 'var(--accent-orange)'; ?>; 
                    padding: 12px 18px; border-radius: 6px; margin-bottom: 24px; font-size: 14.5px; font-weight: 500;">
            🔔 <?php echo htmlspecialchars($actionMessage); ?>
        </div>
    <?php endif; ?>

    <div class="panel">
        <div class="panel-title">
            <span>📤 Compose Outbound SMS Message</span>
            <span style="font-size: 11px; color: var(--text-secondary);">Sent via Android Gateway SIM</span>
        </div>
        
        <form method="POST" action="send_message.php">
            <div class="form-group">
                <label>From (Sender Address)</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($fromDisplay); ?>" readonly style="opacity: 0.7; background: rgba(0,0,0,0.15); cursor: not-allowed; font-feature-settings: 'tnum';" />
            </div>

            <div class="form-group">
                <label for="to_number">Recipient Mobile Number</label>
                <input type="text" name="to_number" id="to_number" class="form-control" 
                       value="<?php echo htmlspecialchars($toPrefill); ?>" 
                       placeholder="e.g. +919876543210" required style="font-feature-settings: 'tnum';" />
                <span style="font-size: 11px; color: var(--text-muted); margin-top: 4px; display: block;">
                    Always include the country code prefix (e.g. +91 for India).
                </span>
            </div>

            <div class="form-group">
                <label for="message_body">SMS Text Content</label>
                <textarea name="message_body" id="message_body" class="form-control" 
                          placeholder="Type your alert broadcast or direct message here..." 
                          required style="min-height: 140px; resize: vertical;"></textarea>
                <div style="display:flex; justify-content:space-between; margin-top: 6px; font-size: 11px; color: var(--text-muted);">
                    <span id="char-counter">0 characters</span>
                    <span>160 chars / SMS</span>
                </div>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-top: 24px; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 16px;">
                <a href="dashboard.php" class="btn-primary" style="background:rgba(255,255,255,0.05); border:1px solid var(--card-border); color:var(--text-primary); text-decoration:none; padding:11px 20px; text-align:center; font-size:13.5px;">✕ CANCEL</a>
                <button type="submit" class="btn-primary">⚡ DISPATCH BROADCAST</button>
            </div>
        </form>
    </div>
</div>

<script>
    const textarea = document.getElementById('message_body');
    const counter = document.getElementById('char-counter');
    if (textarea && counter) {
        textarea.addEventListener('input', () => {
            const count = textarea.value.length;
            const smsCount = Math.ceil(count / 160) || 1;
            counter.textContent = `${count} characters (${smsCount} SMS)`;
        });
    }
</script>

<?php
require_once __DIR__ . '/layout_footer.php';
?>
