<?php
/**
 * Public Webhook & Direct Endpoint - Receive Incoming SMS & App SOS
 * 
 * Supports Android SMS Gateway Webhook (Capcom6), direct mobile app POSTs, and form submissions.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Gateway-Secret');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

define('SECURE_ACCESS', true);

require_once __DIR__ . '/../../config/gateway.php';
require_once __DIR__ . '/../../services/SmsService.php';

// 1. Authenticate Request (Graceful: validate if secret is passed, otherwise allow LAN app posts)
$gatewayConfig = GatewayConfig::get();
$expectedSecret = $gatewayConfig['webhook_secret'] ?? '';
$providedSecret = $_GET['secret'] ?? ($_SERVER['HTTP_X_GATEWAY_SECRET'] ?? ($_POST['secret'] ?? ''));

if (!empty($expectedSecret) && !empty($providedSecret) && $providedSecret !== $expectedSecret) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized: Invalid secret key']);
    exit;
}

// 2. Parse Payload (JSON body or $_POST)
$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true) ?: $_POST;

if (empty($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Bad Request: Empty request body']);
    exit;
}

// 3. Handle System Ping Verification
if (isset($data['event']) && $data['event'] === 'system:ping') {
    SmsService::updateGatewayHeartbeat('system:ping', $data['deviceId'] ?? null);
    echo json_encode(['success' => true, 'status' => 'pong', 'timestamp' => date('c')]);
    exit;
}

// Update telemetry heartbeat
SmsService::updateGatewayHeartbeat($data['event'] ?? 'sms:received', $data['deviceId'] ?? null);

// 4. Normalize Payload Structure
$payload = $data['payload'] ?? $data;

$gatewayMsgId = $payload['messageId'] ?? ($payload['id'] ?? ('msg_' . time() . '_' . mt_rand(100, 999)));
$messageText  = $payload['message'] ?? ($payload['text'] ?? ($payload['body'] ?? ($payload['msg'] ?? '')));
$fromNumber   = $payload['phoneNumber'] ?? ($payload['from'] ?? ($payload['phone'] ?? ($payload['sender'] ?? '')));
$receivedAt   = $payload['receivedAt'] ?? date('Y-m-d H:i:s');

if (empty($fromNumber)) {
    $fromNumber = '+919999999999'; // Default mobile fallback if app doesn't send sender
}

if (empty($messageText)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Bad Request: Missing message text']);
    exit;
}

// 5. Coordinate Processing & Sync into DisasterSafe Live Emergency Command Center
try {
    $result = SmsService::processIncoming($gatewayMsgId, $fromNumber, 'GatewaySIM', $messageText, $receivedAt);
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'status' => 'processed',
        'result' => $result
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal Server Error: ' . $e->getMessage()]);
}
