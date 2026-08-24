<?php
// api/fire_cad_api.php - RESTful JSON API for Fire & Rescue Incident Command & Emergency CAD System
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/fire_db.php';
$pdo = getFireRescuePdo();

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
if (empty($action) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = json_decode(file_get_contents('php://input'), true);
    if (is_array($raw) && isset($raw['action'])) {
        $action = $raw['action'];
        $_POST = array_merge($_POST, $raw);
    }
}

try {
    // 1. GET ALL SYSTEM DATA
    if ($action === 'get_all_data') {
        $stations = $pdo->query("SELECT * FROM stations ORDER BY id ASC")->fetchAll();
        $incidents = $pdo->query("
            SELECT i.*, 
                   v.unit_name as assigned_vehicle_name, 
                   v.type as assigned_vehicle_type,
                   h.hydrant_code as assigned_hydrant_code,
                   h.pressure_psi as assigned_hydrant_psi,
                   h.flow_gpm as assigned_hydrant_gpm
            FROM incidents i
            LEFT JOIN vehicles v ON i.assigned_vehicle_id = v.id
            LEFT JOIN hydrants h ON i.assigned_hydrant_id = h.id
            ORDER BY i.id DESC
        ")->fetchAll();

        $vehicles = $pdo->query("
            SELECT v.*, s.name as station_name 
            FROM vehicles v 
            LEFT JOIN stations s ON v.station_id = s.id 
            ORDER BY v.id ASC
        ")->fetchAll();

        $firefighters = $pdo->query("
            SELECT f.*, s.name as station_name 
            FROM firefighters f 
            LEFT JOIN stations s ON f.station_id = s.id 
            ORDER BY f.id ASC
        ")->fetchAll();

        $hydrants = $pdo->query("SELECT * FROM hydrants ORDER BY id ASC")->fetchAll();
        
        $dispatches = $pdo->query("
            SELECT d.*, i.incident_number, i.fire_type, i.address as incident_address, v.unit_name
            FROM dispatches d
            JOIN incidents i ON d.incident_id = i.id
            JOIN vehicles v ON d.vehicle_id = v.id
            ORDER BY d.id DESC
        ")->fetchAll();

        echo json_encode([
            'success' => true,
            'timestamp' => date('c'),
            'stations' => $stations,
            'incidents' => $incidents,
            'vehicles' => $vehicles,
            'firefighters' => $firefighters,
            'hydrants' => $hydrants,
            'dispatches' => $dispatches,
            'metrics' => [
                'active_incidents' => count(array_filter($incidents, fn($i) => $i['status'] === 'Active')),
                'units_rolling' => count(array_filter($vehicles, fn($v) => in_array($v['status'], ['En Route', 'On Scene']))),
                'active_firefighters' => count(array_filter($firefighters, fn($f) => $f['status'] === 'On Duty')),
                'hydrants_ready' => count(array_filter($hydrants, fn($h) => $h['status'] === 'Operational'))
            ]
        ], JSON_PRETTY_PRINT);
        exit;
    }

    // 2. CREATE NEW INCIDENT REPORT (CITIZEN OR DISPATCHER)
    if ($action === 'create_incident') {
        $callerName = trim($_POST['caller_name'] ?? 'Anonymous Caller');
        $callerPhone = trim($_POST['caller_phone'] ?? '101');
        $fireType = trim($_POST['fire_type'] ?? 'Structure Fire');
        $address = trim($_POST['address'] ?? 'Emergency Ground Zero, Delhi');
        $lat = floatval($_POST['lat'] ?? 28.6315);
        $lng = floatval($_POST['lng'] ?? 77.2167);
        $trappedCount = intval($_POST['trapped_count'] ?? 0);
        $notes = trim($_POST['notes'] ?? 'Emergency alarm paged via Fire CAD system.');
        $assignedVehicleId = intval($_POST['assigned_vehicle_id'] ?? 1);

        // Find nearest hydrant
        $hydrant = $pdo->query("SELECT id FROM hydrants ORDER BY ( (lat - {$lat})*(lat - {$lat}) + (lng - {$lng})*(lng - {$lng}) ) ASC LIMIT 1")->fetch();
        $assignedHydrantId = $hydrant ? $hydrant['id'] : 1;

        $incNumber = 'INC-' . date('Y') . '-' . str_pad(mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("
            INSERT INTO incidents (incident_number, caller_name, caller_phone, fire_type, address, lat, lng, trapped_count, notes, status, stage_index, assigned_vehicle_id, assigned_hydrant_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', 1, ?, ?)
        ");
        $stmt->execute([$incNumber, $callerName, $callerPhone, $fireType, $address, $lat, $lng, $trappedCount, $notes, $assignedVehicleId, $assignedHydrantId]);
        $incidentId = (int)$pdo->lastInsertId();

        // Create initial dispatch record (Stage 1)
        $dispStmt = $pdo->prepare("
            INSERT INTO dispatches (incident_id, vehicle_id, station_id, stage, stage_index, dispatched_at, notes)
            VALUES (?, ?, 1, 'Alarm Paged & Geocoded', 1, CURRENT_TIMESTAMP, ?)
        ");
        $dispStmt->execute([$incidentId, $assignedVehicleId, "Initial call intake by {$callerName}. Unit assigned."]);

        // Mark vehicle as Rolling
        $pdo->prepare("UPDATE vehicles SET status = 'En Route', lat = ?, lng = ? WHERE id = ?")->execute([$lat, $lng, $assignedVehicleId]);

        echo json_encode([
            'success' => true,
            'incident_id' => $incidentId,
            'incident_number' => $incNumber,
            'stage_index' => 1,
            'stage_name' => 'Alarm Paged & Geocoded',
            'assigned_vehicle_id' => $assignedVehicleId,
            'assigned_hydrant_id' => $assignedHydrantId,
            'message' => "Emergency Distress Incident {$incNumber} registered and paged to Fire Command."
        ]);
        exit;
    }

    // 3. ADVANCE 5-STAGE DISPATCH PIPELINE (CAD)
    if ($action === 'advance_stage') {
        $incidentId = intval($_POST['incident_id'] ?? 0);
        $targetStage = intval($_POST['target_stage'] ?? 0);

        $stages = [
            1 => 'Alarm Paged & Geocoded',
            2 => 'Primary Units Rolling',
            3 => 'En Route',
            4 => 'On Scene & Water Supply Connected',
            5 => 'Under Control / Fire Knockdown'
        ];

        if (!isset($stages[$targetStage]) || $incidentId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid incident ID or stage index (1-5).']);
            exit;
        }

        $stageName = $stages[$targetStage];
        $isResolved = ($targetStage === 5);
        $newStatus = $isResolved ? 'Resolved' : 'Active';

        $stmt = $pdo->prepare("UPDATE incidents SET stage_index = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$targetStage, $newStatus, $incidentId]);

        // Update timestamps on dispatch record
        if ($targetStage === 3) {
            $pdo->prepare("UPDATE dispatches SET stage = ?, stage_index = ?, en_route_at = CURRENT_TIMESTAMP WHERE incident_id = ?")->execute([$stageName, $targetStage, $incidentId]);
        } elseif ($targetStage === 4) {
            $pdo->prepare("UPDATE dispatches SET stage = ?, stage_index = ?, on_scene_at = CURRENT_TIMESTAMP WHERE incident_id = ?")->execute([$stageName, $targetStage, $incidentId]);
        } elseif ($targetStage === 5) {
            $pdo->prepare("UPDATE dispatches SET stage = ?, stage_index = ?, closed_at = CURRENT_TIMESTAMP WHERE incident_id = ?")->execute([$stageName, $targetStage, $incidentId]);
            
            // Set vehicle back to In Service
            $inc = $pdo->query("SELECT assigned_vehicle_id FROM incidents WHERE id = {$incidentId}")->fetch();
            if ($inc && $inc['assigned_vehicle_id']) {
                $pdo->prepare("UPDATE vehicles SET status = 'In Service' WHERE id = ?")->execute([$inc['assigned_vehicle_id']]);
            }
        } else {
            $pdo->prepare("UPDATE dispatches SET stage = ?, stage_index = ? WHERE incident_id = ?")->execute([$stageName, $targetStage, $incidentId]);
        }

        echo json_encode([
            'success' => true,
            'incident_id' => $incidentId,
            'stage_index' => $targetStage,
            'stage_name' => $stageName,
            'status' => $newStatus,
            'message' => "CAD Stage advanced to: [{$targetStage}/5] {$stageName}"
        ]);
        exit;
    }

    // 4. UPDATE VEHICLE READINESS STATUS
    if ($action === 'update_vehicle_status') {
        $vehicleId = intval($_POST['vehicle_id'] ?? 0);
        $status = trim($_POST['status'] ?? 'In Service');
        
        $pdo->prepare("UPDATE vehicles SET status = ? WHERE id = ?")->execute([$status, $vehicleId]);
        echo json_encode(['success' => true, 'message' => "Apparatus unit #{$vehicleId} status updated to '{$status}'."]);
        exit;
    }

    // 5. UPDATE FIREFIGHTER STATUS
    if ($action === 'update_firefighter_status') {
        $firefighterId = intval($_POST['firefighter_id'] ?? 0);
        $status = trim($_POST['status'] ?? 'On Duty');

        $pdo->prepare("UPDATE firefighters SET status = ? WHERE id = ?")->execute([$status, $firefighterId]);
        echo json_encode(['success' => true, 'message' => "Firefighter #{$firefighterId} status updated to '{$status}'."]);
        exit;
    }

    // 6. REFILL WATER & FOAM RESERVES
    if ($action === 'refill_apparatus') {
        $vehicleId = intval($_POST['vehicle_id'] ?? 0);
        $water = intval($_POST['water_gal'] ?? 750);
        $foam = intval($_POST['foam_gal'] ?? 50);

        $pdo->prepare("UPDATE vehicles SET current_water_gal = ?, current_foam_gal = ?, status = 'In Service' WHERE id = ?")->execute([$water, $foam, $vehicleId]);
        echo json_encode(['success' => true, 'message' => "Apparatus #{$vehicleId} replenished with {$water} Gal Water and {$foam} Gal AFFF Foam."]);
        exit;
    }

    // Default Fallback
    echo json_encode([
        'success' => false,
        'message' => 'Unknown action or endpoint.',
        'available_actions' => ['get_all_data', 'create_incident', 'advance_stage', 'update_vehicle_status', 'update_firefighter_status', 'refill_apparatus']
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
