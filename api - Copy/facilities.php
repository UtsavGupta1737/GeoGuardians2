<?php
/**
 * Facilities API Endpoint
 * GET  /api/facilities.php?action=list
 * POST /api/facilities.php?action=update_capacity
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$action = $_GET['action'] ?? '';

if ($action === 'list') {
    $facilities = [];
    if ($dbConnected) {
        $stmt = $pdo->query("SELECT * FROM `facilities` ORDER BY type ASC, name ASC");
        $facilities = $stmt->fetchAll();
    }
    jsonOutput('success', 'Facilities retrieved', ['facilities' => $facilities]);
}

if ($action === 'update_capacity') {
    $data = getJsonInput();
    $id = $data['id'] ?? '';
    $avail = intval($data['available_capacity'] ?? 0);

    if ($dbConnected && !empty($id)) {
        $stmt = $pdo->prepare("UPDATE `facilities` SET `available_capacity` = ? WHERE `id` = ?");
        $stmt->execute([$avail, $id]);
    }
    jsonOutput('success', 'Facility capacity updated');
}

jsonOutput('error', 'Invalid action', null, 400);
