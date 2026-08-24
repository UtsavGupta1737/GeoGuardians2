<?php
/**
 * Volunteer Notifications Feed API
 */
if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }
require_once __DIR__ . '/../auth.php';

$notifs = [
    [
        'id' => 1,
        'type' => 'alert',
        'title' => 'Sector 4 Flood Alert',
        'message' => 'Yamuna water level rising at Sector 4 lowlands. Maintain safe distance from river embankment.',
        'time' => '10 mins ago'
    ],
    [
        'id' => 2,
        'type' => 'dispatch',
        'title' => 'Medical Supply Resupply',
        'message' => '50 new trauma burn kits delivered to Central Relief Depot tent #4.',
        'time' => '25 mins ago'
    ]
];

echo json_encode([
    'success' => true,
    'data' => [
        'notifications' => $notifs
    ]
]);
