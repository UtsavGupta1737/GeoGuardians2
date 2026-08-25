<?php
/**
 * Victim-Volunteer & Admin Chat Fetch API
 */
if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }
require_once __DIR__ . '/../auth.php';

$sosId = (int)($_GET['sos_id'] ?? 1);

try {
    // 1. Fetch victim & responder information
    $sosStmt = $pdo->prepare("
        SELECT id as sos_id, sender_name as victim_name, sender_phone as victim_phone,
               gps_lat as victim_lat, gps_lng as victim_lng, emergency_type, priority,
               persons_count as people_count, 'Sector 4 Lowlands, Delhi-NCR' as victim_address,
               status, assigned_unit, responder_name, responder_phone, eta_minutes, dispatch_agency
        FROM emergency_sos
        WHERE id = ?
    ");
    $sosStmt->execute([$sosId]);
    $victimInfo = $sosStmt->fetch();

    if (!$victimInfo) {
        $victimInfo = [
            'sos_id' => $sosId,
            'victim_name' => 'Aarav Patel (Citizen)',
            'victim_phone' => '+91 98765 43210',
            'victim_lat' => 28.6129,
            'victim_lng' => 77.2295,
            'emergency_type' => 'Flood',
            'priority' => 'Critical',
            'people_count' => 4,
            'victim_address' => 'Sector 4 Flood Relief Lowlands',
            'status' => 'Pending',
            'assigned_unit' => 'Rajesh Kumar (Volunteer Corps)',
            'responder_name' => 'Rajesh Kumar',
            'responder_phone' => '+91 98765 43210',
            'eta_minutes' => 5,
            'dispatch_agency' => 'Volunteers'
        ];
    }

    // 2. Fetch conversation messages
    $msgStmt = $pdo->prepare("
        SELECT id, sos_id, sender_id, sender_name, sender_role, message, message_type, created_at
        FROM victim_volunteer_chats
        WHERE sos_id = ?
        ORDER BY created_at ASC, id ASC
    ");
    $msgStmt->execute([$sosId]);
    $messages = $msgStmt->fetchAll();

    // 3. Fetch all active victims for multi-victim chat sidebar
    $allVictims = $pdo->query("
        SELECT id as sos_id, sender_name as victim_name, sender_phone as victim_phone, emergency_type, priority,
               gps_lat, gps_lng, assigned_unit, responder_name, eta_minutes,
               'Sector 4' as victim_address, status
        FROM emergency_sos
        WHERE status != 'Resolved'
        ORDER BY id DESC LIMIT 10
    ")->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => [
            'victim_info' => $victimInfo,
            'messages' => $messages,
            'active_victims' => $allVictims
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
