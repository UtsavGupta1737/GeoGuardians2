<?php
/**
 * Volunteer Direct Request Respond API
 */
if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$requestId = (int)($input['request_id'] ?? 0);
$action = trim($input['action'] ?? 'accept'); // 'accept' or 'decline'
$volunteerId = $_SESSION['user_id'] ?? 3;

try {
    $status = ($action === 'accept') ? 'accepted' : 'declined';
    $pdo->prepare("UPDATE direct_requests SET status = ? WHERE id = ?")->execute([$status, $requestId]);

    if ($action === 'accept') {
        $pdo->prepare("UPDATE assignments SET status = 'en_route' WHERE assigned_to_user_id = ?")->execute([$volunteerId]);
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'request_id' => $requestId,
            'status' => $status
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
