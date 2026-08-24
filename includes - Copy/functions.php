<?php
/**
 * Global Helper Functions
 * GeoGuardians - DisasterSafe
 */

function jsonOutput($status = 'success', $message = '', $data = null, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    $res = ['status' => $status, 'message' => $message];
    if ($data !== null) {
        $res['data'] = $data;
    }
    echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function getJsonInput() {
    $input = file_get_contents('php://input');
    $decoded = json_decode($input, true);
    return is_array($decoded) ? $decoded : $_POST;
}

function isAuthenticated() {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['role']);
}

function getUserRole() {
    return $_SESSION['user']['role'] ?? 'guest';
}

function getUserName() {
    return $_SESSION['user']['name'] ?? 'Guest';
}

function calculateDistanceKm($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // Radius in km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return round($earthRadius * $c, 2);
}
