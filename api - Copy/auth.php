<?php
/**
 * Auth API Endpoint
 * POST /api/auth.php?action=login
 * POST /api/auth.php?action=logout
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$action = $_GET['action'] ?? '';

if ($action === 'login') {
    $data = getJsonInput();
    $id = strtolower(trim($data['login_id'] ?? ''));

    if ($id === 'a' || $id === 'commander@ndrf.gov.in' || $id === 'admin@geoguardians.org') {
        $_SESSION['user'] = ['name' => 'NDRF Command Chief', 'role' => 'authority', 'badge' => 'AUTH-NDRF-01'];
        jsonOutput('success', 'Authority authenticated', ['role' => 'authority', 'redirect' => 'dashboard.php']);
    } elseif ($id === 'v' || $id === 'volunteer@ngo.org') {
        $_SESSION['user'] = ['name' => 'Field Volunteer', 'role' => 'volunteer', 'badge' => 'VOL-TEAM-4'];
        jsonOutput('success', 'Volunteer authenticated', ['role' => 'volunteer', 'redirect' => 'volunteer.php']);
    } elseif ($id === 'c' || $id === 'citizen@example.com') {
        $_SESSION['user'] = ['name' => 'Citizen User', 'role' => 'citizen', 'badge' => 'CITIZEN'];
        jsonOutput('success', 'Citizen authenticated', ['role' => 'citizen', 'redirect' => 'citizen.php']);
    } else {
        jsonOutput('error', 'Credentials not recognized. Use "a", "v", or "c" for demo.', null, 401);
    }
}

if ($action === 'logout') {
    unset($_SESSION['user']);
    session_destroy();
    jsonOutput('success', 'Logged out');
}

jsonOutput('error', 'Invalid action', null, 400);
