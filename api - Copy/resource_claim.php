<?php
/**
 * Volunteer Resource Claim API
 */
if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$resourceId = (int)($input['resource_id'] ?? 0);
$quantity = max(1, (int)($input['quantity'] ?? 1));

try {
    $res = $pdo->prepare("SELECT * FROM master_resources WHERE id = ?");
    $res->execute([$resourceId]);
    $row = $res->fetch();

    if ($row && $row['available_stock'] >= $quantity) {
        $pdo->prepare("UPDATE master_resources SET available_stock = available_stock - ?, distributed_stock = distributed_stock + ? WHERE id = ?")
            ->execute([$quantity, $quantity, $resourceId]);

        echo json_encode([
            'success' => true,
            'data' => [
                'resource_id' => $resourceId,
                'claimed_quantity' => $quantity,
                'remaining_stock' => $row['available_stock'] - $quantity
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Insufficient stock available for claim.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
