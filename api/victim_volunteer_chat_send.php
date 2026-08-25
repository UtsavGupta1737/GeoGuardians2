<?php
/**
 * Victim-Volunteer & Admin Chat Send API
 */
if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$sosId = (int)($input['sos_id'] ?? 1);
$message = trim($input['message'] ?? '');
$messageType = trim($input['message_type'] ?? 'text');

$currentUser = getCurrentUser($pdo);
$roleSlug = $currentUser['role_slug'] ?? ($_SESSION['user_role'] ?? 'volunteer');

if ($roleSlug === 'superadmin' || $roleSlug === 'admin') {
    $senderRole = 'admin';
    $defaultName = $currentUser['name'] ?? 'Disaster Command (Admin)';
} elseif (in_array($roleSlug, ['user', 'citizen', 'victim'])) {
    $senderRole = 'victim';
    $defaultName = $currentUser['name'] ?? 'Citizen / Victim';
} else {
    $senderRole = 'volunteer';
    $defaultName = $currentUser['name'] ?? 'Rajesh Kumar (Volunteer)';
}

$senderId = $currentUser['id'] ?? ($_SESSION['user_id'] ?? 11);
$senderName = !empty($input['sender_name']) ? trim($input['sender_name']) : $defaultName;

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
            'message_type' => $messageType,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
