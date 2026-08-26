<?php
/**
 * Gateway Configuration and Verification Settings
 */

$page_title = 'Gateway Settings';

// Enable security access check
define('SECURE_ACCESS', true);
require_once __DIR__ . '/layout_header.php';
require_once __DIR__ . '/../config/gateway.php';
require_once __DIR__ . '/../config/gemini.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/SmsMessage.php';
require_once __DIR__ . '/../models/SmsNumber.php';
require_once __DIR__ . '/../services/GatewayService.php';
require_once __DIR__ . '/../services/SmsService.php'; 

$db = Database::getConnection();
$gatewayConfig = GatewayConfig::get();
$geminiConfig = GeminiConfig::get();

$actionMessage = '';
$actionSuccess = true;

// 1. Process URL overrides save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $gatewayUrl = trim($_POST['gateway_url']);
    if (!empty($gatewayUrl)) {
        $stmt = $db->prepare("INSERT INTO system_config (config_key, config_value) 
                              VALUES ('gateway_url', :val) 
                              ON DUPLICATE KEY UPDATE config_value = :val2");
        $stmt->execute([':val' => $gatewayUrl, ':val2' => $gatewayUrl]);
        AuditLogger::log('Operator', 'GATEWAY_URL_CHANGED', 'CONFIG', null, "Changed gateway local URL to " . $gatewayUrl);
        $actionMessage = "Gateway endpoint settings saved.";
        $actionSuccess = true;
    }
}

// 2. Process SMS Number Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_number'])) {
    $phone = trim($_POST['phone_number']);
    $alias = trim($_POST['alias']);
    $makePrimary = isset($_POST['make_primary']) ? 1 : 0;
    
    // Clean number: retain digits and '+' prefix
    $phone = preg_replace('/[^\d+]/', '', $phone);
    if (strpos($phone, '+') !== 0) {
        $phone = '+' . $phone; 
    }
    
    if (strlen($phone) >= 11 && strlen($phone) <= 15) {
        try {
            $stmt = $db->prepare("INSERT INTO sms_numbers (phone_number, alias, is_primary, status) 
                                  VALUES (:phone, :alias, 0, 'active')");
            $stmt->execute([':phone' => $phone, ':alias' => $alias]);
            $newId = (int)$db->lastInsertId();
            
            if ($makePrimary) {
                SmsNumber::setPrimary($newId);
            }
            
            AuditLogger::log('Operator', 'NUMBER_REGISTERED', 'SMS_NUMBER', $newId, "Registered SIM number " . $phone . " (" . $alias . ")");
            $actionMessage = "SIM number registered successfully.";
            $actionSuccess = true;
        } catch (Exception $e) {
            $actionMessage = "Error registering SIM number: " . $e->getMessage();
            $actionSuccess = false;
        }
    } else {
        $actionMessage = "Invalid phone number format. Please ensure it has country code prefix (e.g. +91).";
        $actionSuccess = false;
    }
}

// 3. Process Make Primary
if (isset($_GET['make_primary_id'])) {
    $numId = (int)$_GET['make_primary_id'];
    if (SmsNumber::setPrimary($numId)) {
        AuditLogger::log('Operator', 'NUMBER_SET_PRIMARY', 'SMS_NUMBER', $numId, "Set number ID " . $numId . " as primary receiver");
        $actionMessage = "Primary central SOS number updated successfully.";
        $actionSuccess = true;
    } else {
        $actionMessage = "Error updating primary status.";
        $actionSuccess = false;
    }
}

// 4. Process Delete Number
if (isset($_GET['delete_number_id'])) {
    $numId = (int)$_GET['delete_number_id'];
    $stmt = $db->prepare("SELECT is_primary, phone_number FROM sms_numbers WHERE id = :id");
    $stmt->execute([':id' => $numId]);
    $num = $stmt->fetch();
    
    if ($num) {
        if ($num['is_primary']) {
            $actionMessage = "Cannot delete the active primary SOS number. Assign another primary number first.";
            $actionSuccess = false;
        } else {
            $db->prepare("DELETE FROM sms_numbers WHERE id = :id")->execute([':id' => $numId]);
            AuditLogger::log('Operator', 'NUMBER_DELETED', 'SMS_NUMBER', $numId, "Deleted SIM number " . $num['phone_number']);
            $actionMessage = "SIM number removed successfully.";
            $actionSuccess = true;
        }
    }
}

// 5. Process Send Test SMS
if (isset($_GET['test_send_id'])) {
    $numId = (int)$_GET['test_send_id'];
    $stmt = $db->prepare("SELECT phone_number FROM sms_numbers WHERE id = :id");
    $stmt->execute([':id' => $numId]);
    $num = $stmt->fetch();
    
    if ($num) {
        $primary = SmsNumber::getPrimary();
        $fromNum = $primary ? $primary['phone_number'] : $num['phone_number'];
        
        try {
            // Find or create conversation for test
            $stmt = $db->prepare("SELECT id FROM conversations WHERE sender_phone = :phone LIMIT 1");
            $stmt->execute([':phone' => $num['phone_number']]);
            $convRow = $stmt->fetch();
            $conversationId = null;
            
            if ($convRow) {
                $conversationId = (int)$convRow['id'];
            } else {
                $stmt = $db->prepare("INSERT INTO conversations (sender_phone, sms_number_id, last_message_at) VALUES (:from, :num_id, NOW())");
                $stmt->execute([':from' => $num['phone_number'], ':num_id' => $primary ? $primary['id'] : $numId]);
                $conversationId = (int)$db->lastInsertId();
            }
            
            $smsId = SmsMessage::create(
                $conversationId,
                $fromNum,
                $num['phone_number'], 
                'outgoing',
                "GATEWAY VERIFICATION TEST: outgoing transmission successful.",
                'queued',
                null
            );
            
            SmsMessage::enqueueOutbox($smsId);
            $sent = SmsService::dispatchOutgoingMessage($smsId);
            
            if ($sent) {
                $actionMessage = "Test SMS successfully sent to " . $num['phone_number'];
                $actionSuccess = true;
            } else {
                $actionMessage = "Test SMS queued in outbox (Gateway offline).";
                $actionSuccess = false;
            }
        } catch (Exception $e) {
            $actionMessage = "Error dispatching test: " . $e->getMessage();
            $actionSuccess = false;
        }
    }
}

// Retrieve properties
$currentGatewayUrl = GatewayService::getGatewayUrl($gatewayConfig['url']);
$geminiConfigured = !empty($geminiConfig['api_key']);
$registeredNumbers = $db->query("SELECT * FROM sms_numbers ORDER BY is_primary DESC, id ASC")->fetchAll();

// Get current server Host IP dynamically to construct copying webhook links
$serverHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$serverProtocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$webhookBaseUrl = $serverProtocol . '://' . $serverHost . '/SMS_PART2/api/sms';
$secretParam = '?secret=' . urlencode($gatewayConfig['webhook_secret']);
?>

<!-- Action feedback notifications -->
<?php if (!empty($actionMessage)): ?>
    <div style="background: <?php echo $actionSuccess ? 'rgba(57, 211, 83, 0.08)' : 'rgba(255, 153, 0, 0.08)'; ?>; 
                border: 1px solid <?php echo $actionSuccess ? 'var(--accent-green)' : 'var(--accent-orange)'; ?>; 
                padding: 12px 18px; border-radius: 6px; margin-bottom: 24px; font-size: 14.5px; font-weight: 500;">
        🔔 <?php echo htmlspecialchars($actionMessage); ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items: start;">
    
    <!-- Connection parameters -->
    <div class="panel">
        <div class="panel-title">
            <span>⚙️ Android Gateway API Configurations</span>
            <span style="font-size: 11px; color: var(--text-secondary);">Local network properties</span>
        </div>
        
        <form method="POST" action="settings.php">
            <input type="hidden" name="save_settings" value="1" />
            
            <div class="form-group">
                <label>Android Server URL</label>
                <input type="url" name="gateway_url" class="form-control" 
                       value="<?php echo htmlspecialchars($currentGatewayUrl); ?>" 
                       placeholder="e.g. http://192.168.1.100:8080" required />
                <span style="font-size: 11px; color: var(--text-muted); margin-top: 6px; display: block;">
                    Verify this matches the URL shown in your Android app's "Local Server" menu.
                </span>
            </div>

            <div class="form-group">
                <label>API Username (Basic Auth)</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($gatewayConfig['username']); ?>" readonly style="opacity: 0.6; cursor: not-allowed;" />
            </div>

            <div class="form-group">
                <label>API Password (Basic Auth)</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($gatewayConfig['password']); ?>" readonly style="opacity: 0.6; cursor: not-allowed;" />
                <span style="font-size: 11px; color: var(--text-muted); margin-top: 6px; display: block;">
                    These Basic Auth credentials are set inside `config/credentials.php` for security.
                </span>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-top: 24px; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 16px;">
                <button type="button" id="btn-test-connection" class="btn-primary" style="background:rgba(255,255,255,0.05); border:1px solid var(--card-border); color:var(--text-primary); padding:11px 20px; font-size:13.5px; cursor:pointer; transition:all 0.2s ease;">⚡ TEST CONNECTIVITY</button>
                <button type="submit" class="btn-primary">💾 SAVE ENDPOINT</button>
            </div>
        </form>
        
        <!-- Live Diagnostic Telemetry details panel -->
        <div style="margin-top: 24px; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 20px;">
            <strong style="display:block; font-size:13.5px; margin-bottom:12px; color:var(--text-primary);">📡 Live Gateway Diagnostics</strong>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                <div>
                    <span style="font-size:11px; color:var(--text-muted); text-transform:uppercase; display:block; font-weight:600;">TCP Reachability</span>
                    <span id="settings-reachability" class="status-tag low" style="margin-top:4px;">Syncing...</span>
                </div>
                <div>
                    <span style="font-size:11px; color:var(--text-muted); text-transform:uppercase; display:block; font-weight:600;">API Authentication</span>
                    <span id="settings-authentication" class="status-tag low" style="margin-top:4px;">Syncing...</span>
                </div>
                <div>
                    <span style="font-size:11px; color:var(--text-muted); text-transform:uppercase; display:block; font-weight:600;">Last Event Type</span>
                    <div id="settings-last-event" style="font-size:13px; font-weight:700; color:var(--accent-blue); margin-top:4px;">
                        Syncing...
                    </div>
                </div>
                <div>
                    <span style="font-size:11px; color:var(--text-muted); text-transform:uppercase; display:block; font-weight:600;">Last Seen Heartbeat</span>
                    <div id="settings-last-seen" style="font-size:13.0px; color:var(--text-primary); margin-top:4px; font-feature-settings: 'tnum';">
                        Syncing...
                    </div>
                </div>
            </div>
        </div>
        
        <script>
            document.getElementById('btn-test-connection').addEventListener('click', function() {
                const btn = this;
                const origText = btn.textContent;
                btn.disabled = true;
                btn.textContent = '⚡ TESTING...';
                
                // Call global telemetry polling function immediately
                if (typeof updateGatewayTelemetry === 'function') {
                    updateGatewayTelemetry();
                    setTimeout(() => {
                        btn.disabled = false;
                        btn.textContent = origText;
                    }, 1500);
                } else {
                    btn.disabled = false;
                    btn.textContent = origText;
                }
            });
        </script>
    </div>

    <!-- Webhook details copy -->
    <div style="display:flex; flex-direction:column; gap:24px;">
        
        <div class="panel">
            <div class="panel-title">
                <span>🔗 Webhook Configuration URLs</span>
                <span style="font-size: 11px; color: var(--text-secondary);">Copy to Android App configuration</span>
            </div>
            
            <div class="form-group">
                <label>1. Incoming Webhook URL (For sms:received)</label>
                <input type="text" class="form-control" value="<?php echo $webhookBaseUrl . '/receive.php' . $secretParam; ?>" readonly onclick="this.select()" style="font-size: 12px; cursor: pointer; color: var(--accent-blue);" />
                <span style="font-size: 11px; color: var(--text-muted); margin-top: 4px; display: block;">
                    Register this URL inside the Android app's webhook settings under the `sms:received` event.
                </span>
            </div>

            <div class="form-group">
                <label>2. Status Webhook URL (For sms:sent, sms:delivered, sms:failed)</label>
                <input type="text" class="form-control" value="<?php echo $webhookBaseUrl . '/status.php' . $secretParam; ?>" readonly onclick="this.select()" style="font-size: 12px; cursor: pointer; color: var(--accent-blue);" />
                <span style="font-size: 11px; color: var(--text-muted); margin-top: 4px; display: block;">
                    Register this URL inside the Android app's webhook settings to track status delivery receipts.
                </span>
            </div>
        </div>

        <div class="panel">
            <div class="panel-title">
                <span>🤖 Gemini AI Details Extraction</span>
                <span style="font-size: 11px; color: var(--text-secondary);">LLM NLP variables parsing</span>
            </div>
            
            <div style="display:flex; align-items:center; gap:12px; margin-bottom: 12px;">
                <span style="font-size: 24px;"><?php echo $geminiConfigured ? '🟢' : '🔴'; ?></span>
                <div>
                    <strong style="font-size:14px;"><?php echo $geminiConfigured ? 'Gemini AI Fallback Enabled' : 'Gemini AI Fallback Offline (Demo Mode)'; ?></strong>
                    <span style="display:block; font-size:11px; color:var(--text-muted);">
                        <?php echo $geminiConfigured ? 'Model gemini-1.5-flash parses natural language' : 'Using rule-based text heuristics instead'; ?>
                    </span>
                </div>
            </div>
            
            <p style="font-size:12.5px; color:var(--text-secondary); line-height:1.5; border-top:1px solid rgba(255,255,255,0.05); padding-top:12px;">
                To enable deep structural extraction of locations, counts, and priorities from complex conversational SMS, enter your Gemini API key in `config/credentials.php` under:
                <code style="display:block; background:rgba(0,0,0,0.3); padding:8px; border-radius:4px; font-family:monospace; margin-top:6px; color:var(--accent-blue); font-size:11px;">
                    'gemini' => [ 'api_key' => 'YOUR_API_KEY_HERE' ]
                </code>
            </p>
        </div>

    </div>
</div>

<!-- Row 2: Central SIM Number Registration and Configuration -->
<div class="panel" style="margin-top: 24px;">
    <div class="panel-title">
        <span>📱 Registered SIM Numbers & Verification Channels</span>
        <span style="font-size: 11px; color: var(--text-secondary);">Manage phone numbers and verify round-trip capabilities</span>
    </div>
    
    <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 32px; align-items: start; margin-top: 16px;">
        
        <!-- Registration Form -->
        <div>
            <strong style="display:block; font-size:13.5px; margin-bottom:14px; color:var(--text-primary);">➕ Register New SIM Number</strong>
            <form method="POST" action="settings.php">
                <input type="hidden" name="register_number" value="1" />
                
                <div class="form-group">
                    <label>Phone Number (with Country Code)</label>
                    <input type="text" name="phone_number" class="form-control" placeholder="e.g. +919876543210" required />
                    <span style="font-size:11px; color:var(--text-muted); margin-top:4px; display:block;">Always prefix with '+' and country code (e.g. +91 for India).</span>
                </div>
                
                <div class="form-group">
                    <label>Alias / Label</label>
                    <input type="text" name="alias" class="form-control" placeholder="e.g. Backup Rescue / Operator SIM" required />
                </div>
                
                <div class="form-group" style="display:flex; align-items:center; gap:8px;">
                    <input type="checkbox" name="make_primary" id="make_primary" value="1" />
                    <label for="make_primary" style="margin:0; font-size:12.5px; cursor:pointer;">Set instantly as active Primary Central SOS number</label>
                </div>
                
                <button type="submit" class="btn-primary" style="margin-top:16px;">💾 REGISTER SIM NUMBER</button>
            </form>
        </div>
        
        <!-- Registered Numbers Directory -->
        <div>
            <strong style="display:block; font-size:13.5px; margin-bottom:14px; color:var(--text-primary);">📱 Registered SIM Directory</strong>
            <div class="custom-table-container">
                <table class="custom-table" style="font-size: 12.5px;">
                    <thead>
                        <tr>
                            <th>Number</th>
                            <th>Alias</th>
                            <th>Role / State</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($registeredNumbers)): ?>
                            <tr>
                                <td colspan="4" style="text-align:center; color:var(--text-muted); padding:30px;">No registered SIM channels.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($registeredNumbers as $num): ?>
                                <tr>
                                    <td>
                                        <strong style="color:var(--text-primary); font-feature-settings: 'tnum'; font-size:13.5px;"><?php echo htmlspecialchars($num['phone_number']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($num['alias']); ?></td>
                                    <td>
                                        <?php if ($num['is_primary']): ?>
                                            <span class="status-tag critical" style="font-size:8px; padding:2px 6px;">PRIMARY CENTRAL</span>
                                        <?php else: ?>
                                            <span class="status-tag low" style="font-size:8px; padding:2px 6px; background:rgba(255,255,255,0.03);">INACTIVE</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display:flex; gap:12px;">
                                            <?php if (!$num['is_primary']): ?>
                                                <a href="settings.php?make_primary_id=<?php echo $num['id']; ?>" style="color:var(--accent-blue); text-decoration:none; font-weight:700; font-size:11.5px;">Set Primary</a>
                                                <a href="settings.php?delete_number_id=<?php echo $num['id']; ?>" style="color:var(--accent-red); text-decoration:none; font-weight:700; font-size:11.5px;" onclick="return confirm('Remove this SIM number?')">Remove</a>
                                            <?php endif; ?>
                                            <a href="settings.php?test_send_id=<?php echo $num['id']; ?>" style="color:var(--accent-green); text-decoration:none; font-weight:700; font-size:11.5px;">Test Send</a>
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

<?php
require_once __DIR__ . '/layout_footer.php';
?>
