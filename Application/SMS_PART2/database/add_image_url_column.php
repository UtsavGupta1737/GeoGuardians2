<?php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getConnection();
    
    // Check if column image_url exists
    $stmt = $db->query("SHOW COLUMNS FROM disaster_alerts LIKE 'image_url'");
    $column = $stmt->fetch();
    
    if (!$column) {
        $db->exec("ALTER TABLE disaster_alerts ADD COLUMN image_url VARCHAR(255) DEFAULT NULL AFTER recipient_phone");
        echo "Successfully added 'image_url' column to 'disaster_alerts' table.\n";
    } else {
        echo "'image_url' column already exists in 'disaster_alerts' table.\n";
    }
} catch (Exception $e) {
    echo "Error migrating database: " . $e->getMessage() . "\n";
}
?>
