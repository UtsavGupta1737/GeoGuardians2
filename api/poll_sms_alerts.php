<?php
/**
 * api/poll_sms_alerts.php - Lightweight Real-Time Polling Endpoint for Incoming SMS SOS Alerts
 */

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

require_once __DIR__ . '/../db.php';

$lastSeenId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

try {
    // If lastSeenId is 0 (initial page load), just return the current max ID so we don't alert on past incidents
    if ($lastSeenId === 0) {
        $maxId = (int)$pdo->query("SELECT MAX(id) FROM emergency_sos")->fetchColumn();
        echo json_encode([
            'success' => true,
            'has_new' => false,
            'latest_id' => $maxId,
            'timestamp' => time()
        ]);
        exit;
    }

    // Check for any new SOS alert with id > lastSeenId
    $stmt = $pdo->prepare("SELECT * FROM emergency_sos WHERE id > :last_id ORDER BY id ASC LIMIT 5");
    $stmt->execute([':last_id' => $lastSeenId]);
    $newAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($newAlerts)) {
        $latestAlert = end($newAlerts);
        $newMaxId = (int)$latestAlert['id'];

        echo json_encode([
            'success' => true,
            'has_new' => true,
            'latest_id' => $newMaxId,
            'alert' => $latestAlert,
            'alerts_count' => count($newAlerts),
            'timestamp' => time()
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'has_new' => false,
            'latest_id' => $lastSeenId,
            'timestamp' => time()
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
