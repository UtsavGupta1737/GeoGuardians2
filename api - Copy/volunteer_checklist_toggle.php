<?php
/**
 * Volunteer Checklist Item Toggle API
 */
if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$itemId = (int)($input['item_id'] ?? 0);
$isChecked = !empty($input['is_checked']) ? 1 : 0;

try {
    if ($itemId > 0) {
        $stmt = $pdo->prepare("UPDATE assignment_checklist_items SET is_checked = ? WHERE id = ?");
        $stmt->execute([$isChecked, $itemId]);
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'item_id' => $itemId,
            'is_checked' => $isChecked
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
