<?php
/**
 * Volunteer Update Assignment Status API
 * POST: Updates status ('assigned', 'en_route', 'on_scene', 'completed')
 */
if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$assignmentId = (int)($input['assignment_id'] ?? 0);
$newStatus = trim($input['status'] ?? '');
$volunteerId = $_SESSION['user_id'] ?? 3;

$validStatuses = ['assigned', 'en_route', 'on_scene', 'completed'];

if (!in_array($newStatus, $validStatuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid status transition.']);
    exit();
}

try {
    if ($assignmentId > 0) {
        $stmt = $pdo->prepare("UPDATE assignments SET status = ?, updated_at = datetime('now') WHERE id = ?");
        $stmt->execute([$newStatus, $assignmentId]);
    }

    // Also update associated SOS if completed
    if ($newStatus === 'completed') {
        $pdo->exec("UPDATE emergency_sos SET status = 'Resolved' WHERE id = 1");
    }

    logActivity($pdo, 'VOLUNTEER_STATUS_UPDATE', "Assignment #{$assignmentId} status changed to {$newStatus}");

    echo json_encode([
        'success' => true,
        'data' => [
            'assignment_id' => $assignmentId,
            'status' => $newStatus,
            'updated_at' => date('Y-m-d H:i:s')
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
