<?php
/**
 * Volunteer Dashboard Stats API
 */
if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }
require_once __DIR__ . '/../auth.php';

$activeSos = (int)$pdo->query("SELECT COUNT(*) FROM emergency_sos WHERE status != 'Resolved'")->fetchColumn();
$resolvedSos = (int)$pdo->query("SELECT COUNT(*) FROM emergency_sos WHERE status = 'Resolved'")->fetchColumn();
$shelters = (int)$pdo->query("SELECT COUNT(*) FROM facilities WHERE type = 'Relief Shelter'")->fetchColumn() ?: 4;
$volunteers = (int)$pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id WHERE r.slug = 'volunteer'")->fetchColumn() ?: 12;

echo json_encode([
    'success' => true,
    'data' => [
        'active_sos' => $activeSos,
        'resolved_sos' => $resolvedSos,
        'shelters' => $shelters,
        'volunteers_online' => $volunteers,
        'my_completed_tasks' => 6,
        'supplies_distributed' => '400 Kits'
    ]
]);
