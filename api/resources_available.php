<?php
/**
 * Volunteer Resources Available API
 */
if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }
require_once __DIR__ . '/../auth.php';

try {
    $resources = $pdo->query("
        SELECT id, name, category, total_stock as total_quantity, available_stock as quantity,
               unit, primary_warehouse as location_name, status, icon, color
        FROM master_resources
        ORDER BY category ASC, name ASC
    ")->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => [
            'resources' => $resources
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
