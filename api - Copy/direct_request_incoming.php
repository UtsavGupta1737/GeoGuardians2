<?php
/**
 * Volunteer Direct Requests Incoming API
 */
if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }
require_once __DIR__ . '/../auth.php';

try {
    $requests = $pdo->query("
        SELECT d.id as request_id, d.sos_request_id, s.sender_name as victim_name,
               s.sender_phone as phone, d.victim_lat, d.victim_lng,
               s.emergency_type, s.priority, s.persons_count as people_count,
               s.message as description, d.status
        FROM direct_requests d
        JOIN emergency_sos s ON d.sos_request_id = s.id
        WHERE d.status = 'pending'
        ORDER BY d.id DESC LIMIT 5
    ")->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => [
            'requests' => $requests
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
