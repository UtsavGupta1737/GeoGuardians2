<?php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getConnection();
    
    echo "=== SYSTEM CONFIG ===\n";
    $stmt = $db->query("SELECT * FROM system_config");
    while ($row = $stmt->fetch()) {
        echo "{$row['config_key']}: {$row['config_value']}\n";
    }
    
    echo "\n=== LAST 15 AUDIT LOGS ===\n";
    $stmt = $db->query("SELECT * FROM audit_logs ORDER BY id DESC LIMIT 15");
    while ($row = $stmt->fetch()) {
        echo "[{$row['created_at']}] {$row['user_identifier']} - {$row['action']}: {$row['details']}\n";
    }
    
    echo "\n=== LAST 5 SMS MESSAGES ===\n";
    $stmt = $db->query("SELECT * FROM sms_messages ORDER BY id DESC LIMIT 5");
    while ($row = $stmt->fetch()) {
        echo "[{$row['created_at']}] ID: {$row['id']}, Direction: {$row['direction']}, Status: {$row['status']}, Body: {$row['message_body']}\n";
    }
    
    echo "\n=== LAST 5 OUTBOX QUEUE ITEMS ===\n";
    $stmt = $db->query("SELECT * FROM sms_outbox ORDER BY id DESC LIMIT 5");
    while ($row = $stmt->fetch()) {
        echo "[{$row['created_at']}] ID: {$row['id']}, Msg ID: {$row['sms_message_id']}, Status: {$row['status']}, Attempts: {$row['attempt_count']}, Last Error: {$row['last_error']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
