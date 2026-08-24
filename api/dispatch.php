<?php
/**
 * Dispatch API Endpoint
 * POST /api/dispatch.php?action=assign
 * GET  /api/dispatch.php?action=list
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$action = $_GET['action'] ?? '';

if ($action === 'assign') {
    $data = getJsonInput();
    $sosId = $data['sos_id'] ?? '';
    $teamName = $data['team_name'] ?? 'NDRF Rapid Response Squad';
    $eta = intval($data['eta_minutes'] ?? 10);
    $vehicle = $data['vehicle_type'] ?? 'Rescue Ambulance';

    if ($dbConnected && !empty($sosId)) {
        $stmt = $pdo->prepare("UPDATE `sos_alerts` SET `status` = 'Assigned', `assigned_unit` = ?, `eta_minutes` = ? WHERE `id` = ?");
        $stmt->execute([$teamName, $eta, $sosId]);

        $disp = $pdo->prepare("INSERT INTO `dispatches` (`sos_id`, `team_name`, `vehicle_type`, `eta_minutes`) VALUES (?, ?, ?, ?)");
        $disp->execute([$sosId, $teamName, $vehicle, $eta]);
    }

    foreach ($_SESSION['mock_alerts'] as &$a) {
        if ($a['id'] === $sosId) {
            $a['status'] = 'Assigned';
            $a['assigned_unit'] = $teamName;
            $a['eta_minutes'] = $eta;
            break;
        }
    }

    jsonOutput('success', "Dispatched {$teamName} (ETA: {$eta}m)");
}

if ($action === 'list') {
    $dispatches = [];
    if ($dbConnected) {
        $stmt = $pdo->query("SELECT * FROM `dispatches` ORDER BY dispatched_at DESC");
        $dispatches = $stmt->fetchAll();
    }
    jsonOutput('success', 'Dispatches retrieved', ['dispatches' => $dispatches]);
}

jsonOutput('error', 'Invalid action', null, 400);
