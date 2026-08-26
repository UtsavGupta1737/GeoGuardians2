<?php
/**
 * Public Webhook Endpoint - Outgoing Delivery Reports
 * 
 * Handles callbacks from the Android Gateway informing the server when messages are sent/delivered/failed.
 * Example Webhook URL: http://<your-server-ip>/SMS_PART2/api/sms/status.php?secret=sih_webhook_secret_key_2026
 */

header('Content-Type: application/json');

// Enable security access check
define('SECURE_ACCESS', true);

require_once __DIR__ . '/../../config/gateway.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/SmsMessage.php';
require_once __DIR__ . '/../../services/SmsService.php'; // Loads AuditLogger

// 1. Authenticate Request
$gatewayConfig = GatewayConfig::get();
$expectedSecret = $gatewayConfig['webhook_secret'];
$providedSecret = isset($_GET['secret']) ? $_GET['secret'] : ($_SERVER['HTTP_X_GATEWAY_SECRET'] ?? '');

if (!empty($expectedSecret) && $providedSecret !== $expectedSecret) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized: Invalid secret key']);
    exit;
}

// 2. Parse JSON Payload
$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);

if (!$data || !isset($data['event'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Bad Request: Invalid JSON body']);
    exit;
}

$event = $data['event'] ?? 'unknown';
$payload = $data['payload'] ?? null;

// Update telemetry heartbeat logs
SmsService::updateGatewayHeartbeat($event, $data['deviceId'] ?? null, ($event === 'sms:failed' ? ($payload['error'] ?? null) : null));

if (!$payload || !isset($payload['messageId'])) {
    echo json_encode(['success' => true, 'status' => 'ignored: missing payload messageId']);
    exit;
}

$gatewayMsgId = $payload['messageId'];
$db = Database::getConnection();

// 3. Retrieve correlated database record
$stmt = $db->prepare("SELECT id, status, to_number FROM sms_messages WHERE gateway_message_id = :gateway_message_id LIMIT 1");
$stmt->execute([':gateway_message_id' => $gatewayMsgId]);
$msg = $stmt->fetch();

if (!$msg) {
    echo json_encode(['success' => true, 'status' => 'ignored: messageId not found in local db']);
    exit;
}

$smsId = $msg['id'];
$statusText = 'sent';

// 4. Update status according to gateway events
if ($event === 'sms:delivered') {
    $statusText = 'delivered';
    
    // Update queue outbox table
    $stmt = $db->prepare("UPDATE sms_outbox SET status = 'sent' WHERE sms_message_id = :sms_id");
    $stmt->execute([':sms_id' => $smsId]);
    
    // Update core SMS log
    SmsMessage::updateStatus($smsId, 'delivered');
    
    AuditLogger::log('System', 'SMS_DELIVERED', 'SMS', $smsId, "SMS delivered successfully to " . $msg['to_number'] . " (GW ID: " . $gatewayMsgId . ")");
} 
elseif ($event === 'sms:failed') {
    $statusText = 'failed';
    $errorMessage = $payload['error'] ?? 'Unknown network failure';
    
    // Update queue outbox table
    $stmt = $db->prepare("UPDATE sms_outbox SET status = 'failed', last_error = :error WHERE sms_message_id = :sms_id");
    $stmt->execute([
        ':error' => $errorMessage,
        ':sms_id' => $smsId
    ]);
    
    // Update core SMS log
    SmsMessage::updateStatus($smsId, 'failed');
    
    AuditLogger::log('System', 'SMS_DELIVERY_FAILED', 'SMS', $smsId, "SMS delivery failed to " . $msg['to_number'] . ": " . $errorMessage);
} 
elseif ($event === 'sms:sent') {
    $statusText = 'sent';
    
    // Update queue outbox table
    $stmt = $db->prepare("UPDATE sms_outbox SET status = 'sent' WHERE sms_message_id = :sms_id");
    $stmt->execute([':sms_id' => $smsId]);
    
    SmsMessage::updateStatus($smsId, 'sent');
    AuditLogger::log('System', 'SMS_SENT_CONFIRMED', 'SMS', $smsId, "Gateway confirmed cellular transmission for " . $msg['to_number']);
}

// Synchronize associated disaster alerts status
SmsService::syncAlertStatusFromMessage($smsId, $statusText);

echo json_encode(['success' => true, 'event' => $event, 'message_id' => $smsId, 'new_status' => $statusText]);
?>
