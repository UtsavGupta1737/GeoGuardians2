<?php
/**
 * Volunteer Active Assignment API
 * Fetches the volunteer's current in-progress assignment with victim info + checklist items.
 */
if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }
require_once __DIR__ . '/../auth.php';

$volunteerId = $_SESSION['user_id'] ?? 3;

try {
    // 1. Fetch active assignment
    $stmt = $pdo->prepare("
        SELECT a.id as assignment_id,
               a.task_notes,
               a.status as assignment_status,
               a.created_at as assigned_at,
               s.id as sos_id,
               s.sender_name as victim_name,
               s.sender_phone as victim_phone,
               s.gps_lat as victim_lat,
               s.gps_lng as victim_lng,
               'Sector 4 Lowlands, Delhi-NCR' as target_location,
               s.emergency_type,
               s.priority,
               s.persons_count as people_count,
               s.message as incident_details,
               s.status as sos_status
        FROM assignments a
        LEFT JOIN emergency_sos s ON (a.sos_request_id = s.id OR s.id = 1)
        WHERE a.assigned_to_user_id = :uid
          AND a.status NOT IN ('completed', 'reassigned')
        ORDER BY a.created_at DESC
        LIMIT 1
    ");
    $stmt->execute(['uid' => $volunteerId]);
    $assignment = $stmt->fetch();

    // Fallback if no specific assignment exists yet: fetch the first active SOS
    if (!$assignment) {
        $firstSos = $pdo->query("SELECT * FROM emergency_sos WHERE status != 'Resolved' ORDER BY id DESC LIMIT 1")->fetch();
        if ($firstSos) {
            $assignment = [
                'assignment_id' => 1,
                'task_notes' => 'Deliver emergency relief supplies and first-aid support',
                'assignment_status' => 'assigned',
                'assigned_at' => date('Y-m-d H:i:s'),
                'sos_id' => $firstSos['id'],
                'victim_name' => $firstSos['sender_name'],
                'victim_phone' => $firstSos['sender_phone'],
                'victim_lat' => (float)$firstSos['gps_lat'],
                'victim_lng' => (float)$firstSos['gps_lng'],
                'target_location' => 'Sector 4 Flood Relief Zone',
                'emergency_type' => $firstSos['emergency_type'],
                'priority' => $firstSos['priority'],
                'people_count' => $firstSos['persons_count'],
                'incident_details' => $firstSos['message'] ?: 'Assistance requested at flood perimeter.',
                'sos_status' => $firstSos['status']
            ];
        }
    }

    $checklist = [];
    if ($assignment && !empty($assignment['assignment_id'])) {
        $clStmt = $pdo->prepare("
            SELECT id, label, is_checked
            FROM assignment_checklist_items
            WHERE assignment_id = :aid
            ORDER BY id ASC
        ");
        $clStmt->execute(['aid' => $assignment['assignment_id']]);
        $checklist = $clStmt->fetchAll();
    }

    // Default checklist if none
    if (empty($checklist)) {
        $checklist = [
            ['id' => 1, 'label' => 'Trauma First Aid Kits (50 Kits)', 'is_checked' => 1],
            ['id' => 2, 'label' => 'Potable Mineral Water (200 Litres)', 'is_checked' => 0],
            ['id' => 3, 'label' => 'Thermal Blankets & Ground Mats (100 Pcs)', 'is_checked' => 0],
            ['id' => 4, 'label' => 'High-Intensity LED Emergency Torches', 'is_checked' => 0]
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'assignment' => $assignment ?: null,
            'checklist' => $checklist
        ],
        'error' => null
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'data' => null, 'error' => $e->getMessage()]);
}
