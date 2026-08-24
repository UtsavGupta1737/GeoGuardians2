<?php
// api/citizen_api.php - AJAX API for Citizen Emergency Portal
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$currentUser = getCurrentUser($pdo);

switch ($action) {
    case 'get_live_state':
        // Fetch current active SOS for the logged in user or by phone/IP
        $phone = $currentUser['phone'] ?? '';
        $name = $currentUser['name'] ?? '';
        
        $activeSos = null;
        if (!empty($phone) || !empty($name)) {
            $stmt = $pdo->prepare("
                SELECT * FROM emergency_sos 
                WHERE (sender_phone = ? OR sender_name = ?) 
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute([$phone, $name]);
            $activeSos = $stmt->fetch();
        }

        // Also fetch latest public reports
        $recentReports = $pdo->query("SELECT * FROM emergency_sos ORDER BY id DESC LIMIT 10")->fetchAll();

        // Facilities for map
        $facilities = $pdo->query("SELECT * FROM facilities WHERE status != 'Closed'")->fetchAll();

        // Disasters for danger zones
        $disasters = $pdo->query("SELECT * FROM disasters WHERE status = 'Active'")->fetchAll();

        // Stats
        $totalSos = $pdo->query("SELECT COUNT(*) FROM emergency_sos")->fetchColumn();
        $inProgressSos = $pdo->query("SELECT COUNT(*) FROM emergency_sos WHERE status != 'Resolved'")->fetchColumn();
        $resolvedSos = $pdo->query("SELECT COUNT(*) FROM emergency_sos WHERE status = 'Resolved'")->fetchColumn();

        echo json_encode([
            'success' => true,
            'data' => [
                'active_sos' => $activeSos,
                'recent_reports' => $recentReports,
                'facilities' => $facilities,
                'disasters' => $disasters,
                'stats' => [
                    'total' => (int)$totalSos,
                    'in_progress' => (int)$inProgressSos,
                    'resolved' => (int)$resolvedSos
                ]
            ]
        ]);
        break;

    case 'submit_sos':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

        $senderName = trim($input['sender_name'] ?? ($currentUser['name'] ?? 'Citizen in Distress'));
        $senderPhone = trim($input['sender_phone'] ?? ($currentUser['phone'] ?? '+91 98765 43210'));
        $latitude = filter_var($input['latitude'] ?? 28.6139, FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($input['longitude'] ?? 77.2090, FILTER_VALIDATE_FLOAT);
        $emergencyType = trim($input['emergency_type'] ?? 'Flood');
        $personsCount = trim($input['persons_count'] ?? '1');
        $bloodType = trim($input['blood_type'] ?? 'Unknown');
        $age = !empty($input['age']) ? (int)$input['age'] : null;
        $priority = trim($input['priority'] ?? 'Critical');
        $message = trim($input['message'] ?? '');

        // Auto triage
        $triage = determineSystemTriage($emergencyType, $priority, $personsCount);
        $dispatchAgency = $triage['agency'];
        $medicalNeeds = $triage['needs'];

        try {
            $stmt = $pdo->prepare("
                INSERT INTO emergency_sos (sender_name, sender_phone, gps_lat, gps_lng, blood_type, age, persons_count, priority, emergency_type, medical_needs, dispatch_agency, message, status, eta_minutes, assigned_unit)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 8, ?)
            ");
            $stmt->execute([$senderName, $senderPhone, $latitude, $longitude, $bloodType, $age, $personsCount, $priority, $emergencyType, $medicalNeeds, $dispatchAgency, $message, $dispatchAgency]);
            $sosId = $pdo->lastInsertId();

            // Also create direct request for volunteer radar if applicable
            try {
                $pdo->prepare("INSERT INTO direct_requests (sos_request_id, victim_lat, victim_lng, status) VALUES (?, ?, ?, 'pending')")
                    ->execute([$sosId, $latitude, $longitude]);
            } catch (Exception $ex) {}

            logActivity($pdo, 'CITIZEN_SOS_BROADCAST', "Public SOS #{$sosId} transmitted by {$senderName} ({$emergencyType}) -> Auto-assigned to {$dispatchAgency}");

            echo json_encode([
                'success' => true,
                'data' => [
                    'sos_id' => $sosId,
                    'dispatch_agency' => $dispatchAgency,
                    'medical_needs' => $medicalNeeds,
                    'status' => 'Pending',
                    'eta_minutes' => 8
                ]
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'resolve_sos':
        $sosId = (int)($_POST['sos_id'] ?? $_GET['sos_id'] ?? 0);
        if ($sosId > 0) {
            $pdo->prepare("UPDATE emergency_sos SET status = 'Resolved' WHERE id = ?")->execute([$sosId]);
            logActivity($pdo, 'SOS_RESOLVED_CITIZEN', "SOS #{$sosId} marked resolved by citizen");
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid SOS ID']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
        break;
}
