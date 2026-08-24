<?php
/**
 * Volunteer Resource Shortage Report API
 */
if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$resourceId = (int)($input['resource_id'] ?? 0);
$neededQty = (int)($input['needed_quantity'] ?? 10);
$reason = trim($input['reason'] ?? 'Field supply depletion');

try {
    logActivity($pdo, 'RESOURCE_SHORTAGE', "Shortage reported for resource #{$resourceId}, Needed: {$neededQty}, Reason: {$reason}");
    echo json_encode(['success' => true, 'message' => 'Shortage report transmitted to Command Logistics.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
