<?php
/**
 * Lightweight Poll Endpoint for SOS Center
 * 
 * Returns real-time metrics, total SOS incident counts, and message counts for active conversation.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

define('SECURE_ACCESS', true);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/SosRequest.php';

try {
    $db = Database::getConnection();
    
    // Count total SOS requests
    $stmt = $db->query("SELECT COUNT(*) FROM sos_requests");
    $totalSos = (int)$stmt->fetchColumn();

    // Count total messages
    $stmt = $db->query("SELECT COUNT(*) FROM sms_messages");
    $totalMsg = (int)$stmt->fetchColumn();

    // Get max SOS ID
    $stmt = $db->query("SELECT MAX(id) FROM sos_requests");
    $maxSosId = (int)$stmt->fetchColumn();

    $lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
    $activeMsgCount = 0;
    if ($lastId > 0) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM sms_messages WHERE conversation_id = (SELECT conversation_id FROM sos_requests WHERE id = :id)");
        $stmt->execute([':id' => $lastId]);
        $activeMsgCount = (int)$stmt->fetchColumn();
    }

    echo json_encode([
        'success' => true,
        'total_sos' => $totalSos,
        'max_sos_id' => $maxSosId,
        'total_messages' => $totalMsg,
        'message_count' => $activeMsgCount,
        'timestamp' => time()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>