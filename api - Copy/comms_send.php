<?php
/**
 * Volunteer Comms Message Send API
 */
if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$message = trim($input['message'] ?? '');
$channel = trim($input['channel'] ?? 'ops');
$senderId = $_SESSION['user_id'] ?? 3;

if (empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Message content cannot be empty.']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO comms_messages (sender_id, channel, message) VALUES (?, ?, ?)");
    $stmt->execute([$senderId, $channel, $message]);
    $newId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $newId,
            'sender_id' => $senderId,
            'message' => $message,
            'channel' => $channel,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
