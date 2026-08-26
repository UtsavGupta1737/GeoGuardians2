<?php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getConnection();
    $sql = "CREATE TABLE IF NOT EXISTS disaster_alerts (
        alertId VARCHAR(100) PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        disasterType VARCHAR(50) NOT NULL,
        severity VARCHAR(20) NOT NULL,
        sourceType VARCHAR(20) NOT NULL,
        sourceAuthority VARCHAR(255) NOT NULL,
        createdTimestamp BIGINT NOT NULL,
        publishedTimestamp BIGINT NOT NULL,
        cancelledTimestamp BIGINT DEFAULT NULL,
        expiresTimestamp BIGINT NOT NULL,
        lifecycleStatus VARCHAR(20) NOT NULL,
        safetyInstructions TEXT NOT NULL,
        areaType VARCHAR(20) DEFAULT NULL,
        areaLatitude DECIMAL(10, 8) DEFAULT NULL,
        areaLongitude DECIMAL(11, 8) DEFAULT NULL,
        areaRadiusMeters DOUBLE DEFAULT NULL,
        areaPolygonCoordinatesJson TEXT DEFAULT NULL,
        areaAdministrativeArea VARCHAR(255) DEFAULT NULL,
        receivedTimestamp BIGINT NOT NULL,
        recipient_phone VARCHAR(20) DEFAULT NULL,
        image_url VARCHAR(255) DEFAULT NULL,
        status VARCHAR(20) DEFAULT 'queued'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $db->exec($sql);
    echo "disaster_alerts table created successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
