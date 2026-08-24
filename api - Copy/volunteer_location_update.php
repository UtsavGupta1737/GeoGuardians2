<?php
/**
 * Volunteer GPS Location Update API
 */
if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$volunteerId = $_SESSION['user_id'] ?? 3;
$lat = filter_var($input['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
$lng = filter_var($input['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
$status = trim($input['status'] ?? 'available');

if ($lat === null || $lng === null) {
    echo json_encode(['success' => false, 'error' => 'Valid latitude and longitude required.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO volunteer_locations (user_id, latitude, longitude, status, updated_at)
        VALUES (?, ?, ?, ?, datetime('now'))
        ON CONFLICT(user_id) DO UPDATE SET
            latitude = excluded.latitude,
            longitude = excluded.longitude,
            status = excluded.status,
            updated_at = datetime('now')
    ");
    $stmt->execute([$volunteerId, $lat, $lng, $status]);

    echo json_encode([
        'success' => true,
        'data' => [
            'user_id' => $volunteerId,
            'latitude' => $lat,
            'longitude' => $lng,
            'status' => $status
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
