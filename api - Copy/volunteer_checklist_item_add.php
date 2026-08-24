<?php
/**
 * Volunteer Checklist Item Add API
 */
if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$assignmentId = (int)($input['assignment_id'] ?? 1);
$label = trim($input['label'] ?? '');

if (empty($label)) {
    echo json_encode(['success' => false, 'error' => 'Checklist item label cannot be empty.']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO assignment_checklist_items (assignment_id, label, is_checked) VALUES (?, ?, 0)");
    $stmt->execute([$assignmentId, $label]);
    $newId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $newId,
            'label' => $label,
            'is_checked' => 0
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
