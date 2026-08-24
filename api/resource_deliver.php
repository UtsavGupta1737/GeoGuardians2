<?php
/**
 * Volunteer Resource Deliver & Shortage APIs
 */
if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $input['action'] ?? '';
$resourceId = (int)($input['resource_id'] ?? 0);
$quantity = max(1, (int)($input['quantity'] ?? 1));
$destination = trim($input['destination'] ?? 'Sector 4 Relief Camp');
$notes = trim($input['notes'] ?? 'Delivered by volunteer');

try {
    if ($action === 'deliver' && $resourceId > 0) {
        $pdo->prepare("
            INSERT INTO resource_distributions (resource_id, destination_type, destination_name, location_address, gps_lat, gps_lng, quantity_distributed, unit, dispatched_by, contact_officer, distribution_status, notes)
            VALUES (?, 'Relief Camp', ?, 'Sector 4 Evacuation Point', 28.6189, 77.2150, ?, 'units', 'Volunteer Corps', 'Field Volunteer', 'Delivered / On-Site', ?)
        ")->execute([$resourceId, $destination, $quantity, $notes]);

        echo json_encode(['success' => true, 'message' => 'Delivery logged successfully.']);
    } else {
        // Shortage report
        logActivity($pdo, 'RESOURCE_SHORTAGE_REPORT', "Volunteer reported shortage of resource #{$resourceId}: {$notes}");
        echo json_encode(['success' => true, 'message' => 'Shortage report logged with Logistics HQ.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
