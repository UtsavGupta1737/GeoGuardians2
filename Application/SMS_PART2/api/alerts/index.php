<?php
/**
 * Alerts API Endpoint
 * 
 * GET /api/alerts - Returns active/recent alerts
 * GET /api/alerts/{id} - Returns a single alert
 * Supports ?since=<timestamp>
 */

header('Content-Type: application/json');

define('SECURE_ACCESS', true);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Alert.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$id = $_GET['id'] ?? null;
$since = $_GET['since'] ?? null;

try {
    if ($id !== null) {
        $alert = Alert::getById($id);
        if ($alert) {
            echo json_encode(formatAlert($alert));
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Alert not found']);
        }
    } else {
        if ($since !== null) {
            $alerts = Alert::getSince((int)$since);
        } else {
            $alerts = Alert::getActive();
        }
        
        $formattedList = array_map('formatAlert', $alerts);
        echo json_encode($formattedList);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal Server Error: ' . $e->getMessage()]);
}

function formatAlert($row) {
    return [
        'alertId' => $row['alertId'],
        'title' => $row['title'],
        'message' => $row['message'],
        'disasterType' => $row['disasterType'],
        'severity' => $row['severity'],
        'sourceType' => $row['sourceType'],
        'sourceAuthority' => $row['sourceAuthority'],
        'createdTimestamp' => (int)$row['createdTimestamp'],
        'publishedTimestamp' => (int)$row['publishedTimestamp'],
        'cancelledTimestamp' => $row['cancelledTimestamp'] !== null ? (int)$row['cancelledTimestamp'] : null,
        'expiresTimestamp' => (int)$row['expiresTimestamp'],
        'lifecycleStatus' => $row['lifecycleStatus'],
        'safetyInstructions' => $row['safetyInstructions'],
        'area' => $row['areaType'] !== null ? [
            'type' => $row['areaType'],
            'latitude' => $row['areaLatitude'] !== null ? (float)$row['areaLatitude'] : null,
            'longitude' => $row['areaLongitude'] !== null ? (float)$row['areaLongitude'] : null,
            'radiusMeters' => $row['areaRadiusMeters'] !== null ? (float)$row['areaRadiusMeters'] : null,
            'polygonCoordinates' => $row['areaPolygonCoordinatesJson'] !== null ? json_decode($row['areaPolygonCoordinatesJson'], true) : null,
            'administrativeArea' => $row['areaAdministrativeArea']
        ] : null,
        'receivedTimestamp' => (int)$row['receivedTimestamp'],
        'imageUrl' => !empty($row['image_url']) ? $row['image_url'] : null
    ];
}
?>
