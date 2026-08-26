<?php
/**
 * Public Webhook Endpoint - Receive Incoming SMS
 * 
 * Target URL for the Android SMS Gateway Application.
 * Example Webhook URL: http://<your-server-ip>/SMS_PART2/api/sms/receive.php?secret=sih_webhook_secret_key_2026
 */

header('Content-Type: application/json');

// Enable security access check
define('SECURE_ACCESS', true);

require_once __DIR__ . '/../../config/gateway.php';
require_once __DIR__ . '/../../services/SmsService.php';

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

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Bad Request: Invalid JSON body']);
    exit;
}

// Update telemetry heartbeat logs
SmsService::updateGatewayHeartbeat($data['event'] ?? 'unknown', $data['deviceId'] ?? null);


// 3. Handle System Ping Verification
if (isset($data['event']) && $data['event'] === 'system:ping') {
    echo json_encode(['success' => true, 'status' => 'pong', 'timestamp' => date('c')]);
    exit;
}

// 4. Validate Event Type
if (!isset($data['event']) || $data['event'] !== 'sms:received') {
    // Acknowledge other event types (e.g. system logs) without processing
    echo json_encode(['success' => true, 'status' => 'ignored', 'event' => $data['event']]);
    exit;
}

$payload = $data['payload'] ?? null;
if (!$payload) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Bad Request: Missing payload object']);
    exit;
}

// 5. Extract SMS Properties
$gatewayMsgId = $payload['messageId'] ?? null;
$messageText = $payload['message'] ?? '';
$fromNumber = $payload['phoneNumber'] ?? null;
$receivedAt = $payload['receivedAt'] ?? null;

if (empty($fromNumber) || empty($messageText)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Bad Request: Missing phoneNumber or message text']);
    exit;
}

// 6. Coordinate Processing
try {
    $result = SmsService::processIncoming($gatewayMsgId, $fromNumber, 'GatewaySIM', $messageText, $receivedAt);
    http_response_code(200);
    echo json_encode(['success' => true, 'result' => $result]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal Server Error: ' . $e->getMessage()]);
}
