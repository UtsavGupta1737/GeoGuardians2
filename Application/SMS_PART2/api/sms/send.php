<?php
/**
 * Local API Endpoint - Enqueue and Send Outbound SMS
 * 
 * Invoked by dashboard actions to send alerts or replies.
 */

header('Content-Type: application/json');

// Enable security access check
define('SECURE_ACCESS', true);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/SmsMessage.php';
require_once __DIR__ . '/../../services/SmsService.php'; // Loads AuditLogger

// 1. Parse Input
$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true) ?? $_POST;

$toNumber = $data['to_number'] ?? '';
$messageText = $data['message'] ?? '';

if (empty($toNumber) || empty($messageText)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Bad Request: Missing to_number or message text']);
    exit;
}

// Clean phone format (allow + and digits)
$toNumber = preg_replace('/[^\d+]/', '', $toNumber);

try {
    // Resolve conversation thread for outgoing message
    $db = Database::getConnection();
    $stmt = $db->prepare("SELECT id FROM conversations WHERE sender_phone = :phone LIMIT 1");
    $stmt->execute([':phone' => $toNumber]);
    $convRow = $stmt->fetch();
    $conversationId = null;

    if ($convRow) {
        $conversationId = (int)$convRow['id'];
        $db->prepare("UPDATE conversations SET last_message_at = NOW() WHERE id = :id")->execute([':id' => $conversationId]);
    } else {
        $stmt = $db->query("SELECT id FROM sms_numbers WHERE is_primary = 1 LIMIT 1");
        $numRow = $stmt->fetch();
        $smsNumberId = $numRow ? (int)$numRow['id'] : 1;
        
        $stmt = $db->prepare("INSERT INTO conversations (sender_phone, sms_number_id, last_message_at) 
                              VALUES (:from, :num_id, NOW())");
        $stmt->execute([':from' => $toNumber, ':num_id' => $smsNumberId]);
        $conversationId = (int)$db->lastInsertId();
    }

    $stmt = $db->prepare("SELECT phone_number FROM sms_numbers WHERE id = (SELECT sms_number_id FROM conversations WHERE id = :conv_id)");
    $stmt->execute([':conv_id' => $conversationId]);
    $numRow = $stmt->fetch();
    $centralNumber = $numRow ? $numRow['phone_number'] : '+919876543210';

    // 2. Insert into sms_messages log using correct signature
    $smsId = SmsMessage::create(
        $conversationId,
        $centralNumber,
        $toNumber,
        'outgoing',
        $messageText,
        'queued',
        null
    );

    // 3. Insert into sms_outbox queue
    SmsMessage::enqueueOutbox($smsId);

    // 4. Log the audit event
    AuditLogger::log('Operator', 'SMS_ENQUEUED', 'SMS', $smsId, "Manual reply queued to " . $toNumber);

    // 5. Attempt Immediate Local LAN Dispatch
    $success = SmsService::dispatchOutgoingMessage($smsId);

    if ($success) {
        echo json_encode([
            'success' => true, 
            'status' => 'sent', 
            'message_id' => $smsId,
            'details' => 'SMS dispatched immediately through local gateway'
        ]);
    } else {
        echo json_encode([
            'success' => true, 
            'status' => 'queued', 
            'message_id' => $smsId, 
            'details' => 'Message queued. Gateway device is currently unreachable (buffered for retry).'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal Server Error: ' . $e->getMessage()]);
}
?>
