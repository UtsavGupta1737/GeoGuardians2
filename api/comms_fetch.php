<?php
/**
 * Volunteer Comms Messages Fetch API
 */
if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }
require_once __DIR__ . '/../auth.php';

try {
    $stmt = $pdo->query("
        SELECT c.id, c.sender_id, u.name as sender_name, r.slug as sender_role,
               c.channel, c.message, c.created_at
        FROM comms_messages c
        LEFT JOIN users u ON c.sender_id = u.id
        LEFT JOIN roles r ON u.role_id = r.id
        ORDER BY c.created_at ASC, c.id ASC
        LIMIT 50
    ");
    $messages = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => [
            'messages' => $messages
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
