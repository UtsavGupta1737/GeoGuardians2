<?php
/**
 * Volunteer Profile API
 */
if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }
require_once __DIR__ . '/../auth.php';

$volunteerId = $_SESSION['user_id'] ?? 3;
$user = getCurrentUser($pdo);

$profile = [
    'id' => $user['id'] ?? $volunteerId,
    'name' => $user['name'] ?? 'Elena Rostova (Lead Volunteer)',
    'email' => $user['email'] ?? 'volunteer@disaster.local',
    'phone' => $user['phone'] ?? '+91 98101 22334',
    'role' => 'Disaster Relief Volunteer Lead',
    'team_name' => 'Water & Food Relief Team Alpha',
    'assigned_area' => 'Sector 4 Flood Basin, Delhi-NCR',
    'status' => 'On Duty (Active)',
    'total_missions' => 14,
    'people_assisted' => 48,
    'skills' => ['First Aid & CPR', 'Water Rescue Support', 'Logistics Distribution', 'Emergency Triage'],
    'badge' => 'Certified Crisis Responder'
];

echo json_encode([
    'success' => true,
    'data' => [
        'profile' => $profile
    ]
]);
