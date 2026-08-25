<?php
// api/esp32_sos_ingest.php - Direct Ingestion Endpoint for ESP32 Serial & Gateway SOS Alerts
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../db.php';

// Accept raw JSON payload or POST fields
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data || !is_array($data)) {
    $data = $_POST;
}

if (empty($data)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'No SOS data received. Send JSON payload conforming to ESP32 serial protocol.'
    ]);
    exit;
}

try {
    // Standardize ESP32 fields without altering database schema format
    $senderName = trim($data['sender_name'] ?? $data['victim_name'] ?? 'Emergency Citizen');
    $senderPhone = trim($data['sender_phone'] ?? $data['phone'] ?? '+91 98765 43210');
    
    // Coordinates
    $gpsLat = floatval($data['gps_lat'] ?? $data['latitude'] ?? 28.613900);
    $gpsLng = floatval($data['gps_lng'] ?? $data['longitude'] ?? 77.209000);
    
    $bloodType = trim($data['blood_type'] ?? 'Unknown');
    $age = isset($data['age']) && is_numeric($data['age']) ? (int)$data['age'] : null;
    $personsCount = trim($data['persons_count'] ?? $data['people_count'] ?? '1 - 4');
    $priority = trim($data['priority'] ?? $data['severity'] ?? 'Critical');
    $emergencyType = trim($data['emergency_type'] ?? 'General Emergency');
    
    // Needs
    $medicalNeeds = $data['medical_needs'] ?? $data['quick_needs'] ?? null;
    if (is_array($medicalNeeds)) {
        $medicalNeeds = implode(', ', $medicalNeeds);
    } else {
        $medicalNeeds = trim((string)$medicalNeeds);
    }
    
    // Notes & Voice info
    $message = trim($data['message'] ?? 'SOS alert dispatched via ESP32 emergency beacon.');
    $beaconId = trim($data['beacon_node_id'] ?? 'RESCUE-BEACON-04');
    $isVoiceSos = !empty($data['is_voice_sos']) || !empty($data['voice_note']);
    $voiceDuration = isset($data['voice_duration_sec']) ? (int)$data['voice_duration_sec'] : 0;
    
    // If voice note attached, append note tag to message if not already present
    if ($isVoiceSos && !str_contains($message, '[Voice SOS Note Attached]')) {
        $message .= " [Voice SOS Note Attached ({$voiceDuration}s) via {$beaconId}]";
    }

    // Auto-determine triage dispatch agency and default medical needs if blank
    $triage = determineSystemTriage($emergencyType, $priority, $personsCount);
    $dispatchAgency = $data['dispatch_agency'] ?? $triage['agency'] ?? 'Regional Quick Reaction Team';
    if (empty($medicalNeeds)) {
        $medicalNeeds = $triage['needs'] ?? 'Emergency Life Support & First Aid';
    }

    // 1. Insert into live SQLite database table: emergency_sos
    $stmt = $pdo->prepare("
        INSERT INTO emergency_sos (
            sender_name, sender_phone, gps_lat, gps_lng, blood_type, 
            age, persons_count, priority, emergency_type, medical_needs, 
            dispatch_agency, message, status
        ) VALUES (
            :sender_name, :sender_phone, :gps_lat, :gps_lng, :blood_type,
            :age, :persons_count, :priority, :emergency_type, :medical_needs,
            :dispatch_agency, :message, 'Pending'
        )
    ");

    $stmt->execute([
        ':sender_name' => $senderName,
        ':sender_phone' => $senderPhone,
        ':gps_lat' => $gpsLat,
        ':gps_lng' => $gpsLng,
        ':blood_type' => $bloodType,
        ':age' => $age,
        ':persons_count' => $personsCount,
        ':priority' => $priority,
        ':emergency_type' => $emergencyType,
        ':medical_needs' => $medicalNeeds,
        ':dispatch_agency' => $dispatchAgency,
        ':message' => $message
    ]);

    $sosId = (int)$pdo->lastInsertId();

    // Log in audit activity logs safely
    try {
        $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, user_name, action, details, ip_address) VALUES (NULL, 'ESP32 Hardware Gateway', 'ESP32_SOS_INGESTED', :details, :ip)");
        $logStmt->execute([
            ':details' => "ESP32 SOS #{$sosId} ingested from {$senderName} ({$emergencyType}) via {$beaconId}",
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);
    } catch (Exception $e) {
        // Non-blocking log
    }

    // 2. Also Sync to MySQL/WAMP sos_alerts table if present
    try {
        if (file_exists(__DIR__ . '/../config/db.php')) {
            $myHost = '127.0.0.1';
            $myPort = '3306';
            $myDb = 'disastersafe';
            $myUser = 'root';
            $myPass = '';
            $myPdo = new PDO("mysql:host={$myHost};port={$myPort};dbname={$myDb};charset=utf8mb4", $myUser, $myPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
                PDO::ATTR_TIMEOUT => 1
            ]);
            $mysqlSosId = 'ESP32-' . str_pad((string)$sosId, 3, '0', STR_PAD_LEFT);
            $myStmt = $myPdo->prepare("
                INSERT INTO `sos_alerts` (
                    `id`, `victim_name`, `phone`, `latitude`, `longitude`, 
                    `emergency_type`, `severity`, `status`, `people_count`, 
                    `quick_needs`, `message`, `assigned_unit`, `eta_minutes`
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?, ?, ?, 15)
                ON DUPLICATE KEY UPDATE `status`='Pending'
            ");
            $myStmt->execute([
                $mysqlSosId, $senderName, $senderPhone, $gpsLat, $gpsLng, 
                $emergencyType, $priority, $personsCount, 
                $medicalNeeds, $message, $dispatchAgency
            ]);
        }
    } catch (Exception $e) {
        // Non-blocking fallback
    }

    // 3. Write transaction log
    @file_put_contents(
        __DIR__ . '/../database/esp32_ingest_log.txt',
        "[" . date('Y-m-d H:i:s') . "] INGESTED SOS #{$sosId}: {$senderName} | {$emergencyType} | GPS: {$gpsLat}, {$gpsLng} | Node: {$beaconId}\n",
        FILE_APPEND
    );

    echo json_encode([
        'status' => 'success',
        'message' => 'ESP32 Serial SOS successfully added to DisasterSafe database.',
        'sos_id' => $sosId,
        'data' => [
            'id' => $sosId,
            'sender_name' => $senderName,
            'sender_phone' => $senderPhone,
            'gps_lat' => $gpsLat,
            'gps_lng' => $gpsLng,
            'emergency_type' => $emergencyType,
            'priority' => $priority,
            'dispatch_agency' => $dispatchAgency,
            'beacon_node_id' => $beaconId,
            'is_voice_sos' => $isVoiceSos,
            'voice_duration_sec' => $voiceDuration,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error while ingesting ESP32 SOS alert: ' . $e->getMessage()
    ]);
}
