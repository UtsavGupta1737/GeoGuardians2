<?php
/**
 * Volunteer Checklist Item Delete API
 */
if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$itemId = (int)($input['item_id'] ?? 0);

try {
    if ($itemId > 0) {
        $stmt = $pdo->prepare("DELETE FROM assignment_checklist_items WHERE id = ?");
        $stmt->execute([$itemId]);
    }
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
