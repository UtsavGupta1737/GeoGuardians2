<?php
/**
 * Tactical Field Map Data API
 */
if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }
require_once __DIR__ . '/../auth.php';

try {
    // 1. Citizens in need (Active / Pending SOS)
    $sosStmt = $pdo->query("
        SELECT id, sender_name as victim_name, sender_phone as phone,
               gps_lat as location_lat, gps_lng as location_lng,
               'Sector 4' as address, emergency_type, dispatch_agency as department_target,
               persons_count as people_count, priority, status, message as description, created_at
        FROM emergency_sos
        WHERE status != 'Resolved'
        ORDER BY id DESC
    ");
    $citizens = $sosStmt->fetchAll();

    // 2. Shelters & Facilities
    $shelterStmt = $pdo->query("
        SELECT id, name, type, total_capacity as capacity,
               (total_capacity - available_capacity) as occupancy,
               'Delhi-NCR Zone' as address, latitude as location_lat, longitude as location_lng,
               status, 'Food, Medical, Bedding' as facilities, contact as contact_phone,
               'Officer in Charge' as manager_name
        FROM facilities
    ");
    $shelters = $shelterStmt->fetchAll();

    // 3. Resource Depots & Stockpiles
    $depots = [
        [
            'location_name' => 'Mayur Vihar Central Relief Depot',
            'location_lat' => 28.6080,
            'location_lng' => 77.2980,
            'total_items' => 4,
            'total_units' => 1200,
            'items_summary' => 'Trauma Kits (150), Water Bottles (600L), Blankets (400), Rations (200)'
        ],
        [
            'location_name' => 'Noida Sector 21 Logistics Base',
            'location_lat' => 28.5920,
            'location_lng' => 77.3400,
            'total_items' => 3,
            'total_units' => 850,
            'items_summary' => 'Life Jackets (200), Inflatable Boats (6), Portable Gensets (8)'
        ]
    ];

    // 4. Hazard Exclusion Zones
    $hazards = [
        [
            'id' => 1,
            'title' => 'Yamuna Flood Basin Hazard Zone',
            'hazard_type' => 'Flood',
            'risk_level' => 'critical',
            'center_lat' => 28.6189,
            'center_lng' => 77.2250,
            'radius_meters' => 600,
            'description' => 'River overflow with 1.8m water level. High current speed.',
            'evacuation_status' => 'Mandatory Evacuation'
        ]
    ];

    // 5. Aggregate stats
    $activeCount = (int)$pdo->query("SELECT COUNT(*) FROM emergency_sos WHERE status != 'Resolved'")->fetchColumn();
    $resolvedCount = (int)$pdo->query("SELECT COUNT(*) FROM emergency_sos WHERE status = 'Resolved'")->fetchColumn();

    echo json_encode([
        'success' => true,
        'data' => [
            'citizens' => $citizens,
            'shelters' => $shelters,
            'depots' => $depots,
            'hazards' => $hazards,
            'stats' => [
                'active_rescues' => $activeCount ?: count($citizens),
                'resolved_count' => $resolvedCount ?: 12,
                'shelters_count' => count($shelters),
                'depots_count' => count($depots),
                'hazards_count' => count($hazards)
            ]
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
