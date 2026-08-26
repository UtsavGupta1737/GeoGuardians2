<?php
/**
 * Alert Model Class
 * 
 * Manages disaster alerts published by the admin console.
 */

if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

require_once __DIR__ . '/../config/database.php';

class Alert {
    public static function create($data) {
        $db = Database::getConnection();
        $sql = "INSERT INTO disaster_alerts (
            alertId, title, message, disasterType, severity, sourceType, sourceAuthority,
            createdTimestamp, publishedTimestamp, cancelledTimestamp, expiresTimestamp,
            lifecycleStatus, safetyInstructions, areaType, areaLatitude, areaLongitude,
            areaRadiusMeters, areaPolygonCoordinatesJson, areaAdministrativeArea, receivedTimestamp,
            image_url, recipient_phone, status
        ) VALUES (
            :alertId, :title, :message, :disasterType, :severity, :sourceType, :sourceAuthority,
            :createdTimestamp, :publishedTimestamp, :cancelledTimestamp, :expiresTimestamp,
            :lifecycleStatus, :safetyInstructions, :areaType, :areaLatitude, :areaLongitude,
            :areaRadiusMeters, :areaPolygonCoordinatesJson, :areaAdministrativeArea, :receivedTimestamp,
            :imageUrl, :recipientPhone, :status
        )";
        $stmt = $db->prepare($sql);
        return $stmt->execute($data);
    }

    public static function getById($alertId) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM disaster_alerts WHERE alertId = :alertId");
        $stmt->execute([':alertId' => $alertId]);
        return $stmt->fetch();
    }

    public static function getSince($timestamp) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM disaster_alerts WHERE publishedTimestamp >= :timestamp ORDER BY publishedTimestamp DESC");
        $stmt->execute([':timestamp' => $timestamp]);
        return $stmt->fetchAll();
    }

    public static function getActive() {
        $db = Database::getConnection();
        $now = round(microtime(true) * 1000);
        $stmt = $db->prepare("SELECT * FROM disaster_alerts WHERE lifecycleStatus = 'PUBLISHED' AND expiresTimestamp > :now AND cancelledTimestamp IS NULL ORDER BY publishedTimestamp DESC");
        $stmt->execute([':now' => $now]);
        return $stmt->fetchAll();
    }

    public static function cancel($alertId, $cancelledTimestamp) {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE disaster_alerts SET lifecycleStatus = 'CANCELLED', cancelledTimestamp = :cancelledTimestamp WHERE alertId = :alertId");
        return $stmt->execute([':alertId' => $alertId, ':cancelledTimestamp' => $cancelledTimestamp]);
    }

    public static function getAll() {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT * FROM disaster_alerts ORDER BY createdTimestamp DESC");
        return $stmt->fetchAll();
    }
}
?>
