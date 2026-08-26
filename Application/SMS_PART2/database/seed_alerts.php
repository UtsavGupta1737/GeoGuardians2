<?php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../models/Alert.php';

$now = round(microtime(true) * 1000);

$alert1 = [
    'alertId' => 'FIRE-001',
    'title' => 'Wildfire Threat',
    'message' => 'A fast-moving wildfire is threatening residential structures in Sector 5.',
    'disasterType' => 'FIRE',
    'severity' => 'EMERGENCY',
    'sourceType' => 'OFFICIAL',
    'sourceAuthority' => 'National Fire Authority',
    'createdTimestamp' => $now - 60000,
    'publishedTimestamp' => $now - 30000,
    'cancelledTimestamp' => null,
    'expiresTimestamp' => $now + 3600000 * 2, // 2 hours
    'lifecycleStatus' => 'PUBLISHED',
    'safetyInstructions' => 'Evacuate Sector 5 immediately. Assemble at Sector 5 stadium.',
    'areaType' => 'RADIUS',
    'areaLatitude' => 12.971598,
    'areaLongitude' => 77.594562,
    'areaRadiusMeters' => 2000.0,
    'areaPolygonCoordinatesJson' => null,
    'areaAdministrativeArea' => 'Sector 5',
    'receivedTimestamp' => $now
];

$alert2 = [
    'alertId' => 'FLOOD-002',
    'title' => 'Flash Flood Advisory',
    'message' => 'Heavy rainfall has triggered rapid water level increases in River Main.',
    'disasterType' => 'FLOOD',
    'severity' => 'WARNING',
    'sourceType' => 'OFFICIAL',
    'sourceAuthority' => 'Water Resource Dept',
    'createdTimestamp' => $now - 120000,
    'publishedTimestamp' => $now - 60000,
    'cancelledTimestamp' => null,
    'expiresTimestamp' => $now + 3600000 * 5, // 5 hours
    'lifecycleStatus' => 'PUBLISHED',
    'safetyInstructions' => 'Move all vehicles and livestock to elevated structures.',
    'areaType' => 'RADIUS',
    'areaLatitude' => 12.981598,
    'areaLongitude' => 77.604562,
    'areaRadiusMeters' => 5000.0,
    'areaPolygonCoordinatesJson' => null,
    'areaAdministrativeArea' => 'Main River Valley',
    'receivedTimestamp' => $now
];

try {
    Alert::create($alert1);
    Alert::create($alert2);
    echo "Seed alerts inserted successfully.\n";
} catch (Exception $e) {
    echo "Error seeding: " . $e->getMessage() . "\n";
}
?>
