<?php
/**
 * Victim-Volunteer Chat Send API
 */
if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$sosId = (int)($input['sos_id'] ?? 1);
$message = trim($input['message'] ?? '');
$messageType = trim($input['message_type'] ?? 'text');

$senderId = $_SESSION['user_id'] ?? 3;
$senderName = $_SESSION['user_name'] ?? 'Elena (Volunteer)';
$senderRole = in_array($_SESSION['user_role'] ?? '', ['user', 'citizen', 'victim']) ? 'victim' : 'volunteer';

if (empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Message cannot be empty.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO victim_volunteer_chats (sos_id, sender_id, sender_name, sender_role, message, message_type)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$sosId, $senderId, $senderName, $senderRole, $message, $messageType]);
    $newId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $newId,
            'sos_id' => $sosId,
            'sender_id' => $senderId,
            'sender_name' => $senderName,
            'sender_role' => $senderRole,
            'message' => $message,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
