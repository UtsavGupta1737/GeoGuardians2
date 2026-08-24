<?php
/**
 * SOS Emergency Alerts API Endpoint
 * GET  /api/sos.php?action=get_all
 * POST /api/sos.php?action=create
 * POST /api/sos.php?action=update_status
 * GET  /api/sos.php?action=export_csv
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$action = $_GET['action'] ?? '';

if ($action === 'get_all') {
    $alerts = [];
    if ($dbConnected) {
        $stmt = $pdo->query("SELECT * FROM `sos_alerts` ORDER BY CASE severity WHEN 'Critical' THEN 1 WHEN 'High' THEN 2 WHEN 'Medium' THEN 3 ELSE 4 END, created_at DESC");
        $alerts = $stmt->fetchAll();
    } else {
        $alerts = $_SESSION['mock_alerts'];
    }
    jsonOutput('success', 'Alerts retrieved', ['alerts' => $alerts, 'db' => $dbConnected]);
}

if ($action === 'create') {
    $data = getJsonInput();
    
    $newId = 'SOS-' . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
    $name = !empty($data['victim_name']) ? trim($data['victim_name']) : 'Anonymous Citizen';
    $phone = trim($data['phone'] ?? '+91 99999 00000');
    $lat = floatval($data['latitude'] ?? 28.468);
    $lng = floatval($data['longitude'] ?? 77.497);
    $type = trim($data['emergency_type'] ?? 'General Emergency');
    $severity = trim($data['severity'] ?? 'Critical');
    $people = trim($data['people_count'] ?? '1-3');
    $needs = is_array($data['quick_needs'] ?? null) ? implode(', ', $data['quick_needs']) : trim($data['quick_needs'] ?? 'Ambulance');
    $msg = trim($data['message'] ?? 'Distress alert triggered via Citizen SOS Portal.');

    if ($dbConnected) {
        $stmt = $pdo->prepare("INSERT INTO `sos_alerts` (`id`, `victim_name`, `phone`, `latitude`, `longitude`, `emergency_type`, `severity`, `status`, `people_count`, `quick_needs`, `message`) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?, ?)");
        $stmt->execute([$newId, $name, $phone, $lat, $lng, $type, $severity, $people, $needs, $msg]);
    }

    array_unshift($_SESSION['mock_alerts'], [
        'id' => $newId,
        'victim_name' => $name,
        'phone' => $phone,
        'latitude' => $lat,
        'longitude' => $lng,
        'emergency_type' => $type,
        'severity' => $severity,
        'status' => 'Pending',
        'people_count' => $people,
        'quick_needs' => $needs,
        'message' => $msg,
        'assigned_unit' => '',
        'eta_minutes' => 0,
        'created_at' => date('Y-m-d H:i:s')
    ]);

    jsonOutput('success', 'Emergency SOS distress broadcast transmitted!', ['sos_id' => $newId], 201);
}

if ($action === 'update_status') {
    $data = getJsonInput();
    $id = $data['id'] ?? '';
    $status = $data['status'] ?? 'Pending';

    if ($dbConnected) {
        $stmt = $pdo->prepare("UPDATE `sos_alerts` SET `status` = ? WHERE `id` = ?");
        $stmt->execute([$status, $id]);
    }
    foreach ($_SESSION['mock_alerts'] as &$a) {
        if ($a['id'] === $id) {
            $a['status'] = $status;
            break;
        }
    }
    jsonOutput('success', 'Status updated', ['id' => $id, 'new_status' => $status]);
}

if ($action === 'export_csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="disastersafe_sos_alerts_' . date('Y-m-d_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Victim Name', 'Phone', 'Latitude', 'Longitude', 'Emergency Type', 'Severity', 'Status', 'People Count', 'Needs', 'Message', 'Assigned Unit', 'Timestamp']);
    
    $list = $dbConnected ? $pdo->query("SELECT * FROM `sos_alerts` ORDER BY created_at DESC")->fetchAll() : $_SESSION['mock_alerts'];
    foreach ($list as $r) {
        fputcsv($out, [
            $r['id'], $r['victim_name'], $r['phone'], $r['latitude'], $r['longitude'],
            $r['emergency_type'], $r['severity'], $r['status'], $r['people_count'],
            $r['quick_needs'], $r['message'], $r['assigned_unit'] ?? '', $r['created_at']
        ]);
    }
    fclose($out);
    exit;
}

jsonOutput('error', 'Invalid action', null, 400);
