<?php
/**
 * ExtractedData Model Class
 * 
 * Manages operations on the sms_extracted_data table.
 */

if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

require_once __DIR__ . '/../config/database.php';

class ExtractedData {
    /**
     * Create an entry storing parsed SMS information
     */
    public static function create($smsMessageId, $latitude, $longitude, $peopleCount, $injuredCount, $disasterType, $helpRequired, $priority, $confidence, $method, $rawJson) {
        $db = Database::getConnection();
        
        $sql = "INSERT INTO sms_extracted_data 
                (sms_message_id, latitude, longitude, people_count, injured_count, disaster_type, help_required, priority, confidence, extraction_method, extracted_json) 
                VALUES (:sms_message_id, :latitude, :longitude, :people_count, :injured_count, :disaster_type, :help_required, :priority, :confidence, :extraction_method, :extracted_json)";
        
        $stmt = $db->prepare($sql);
        return $stmt->execute([
            ':sms_message_id' => $smsMessageId,
            ':latitude' => $latitude,
            ':longitude' => $longitude,
            ':people_count' => (int)$peopleCount,
            ':injured_count' => (int)$injuredCount,
            ':disaster_type' => $disasterType,
            ':help_required' => $helpRequired,
            ':priority' => $priority,
            ':confidence' => $confidence,
            ':extraction_method' => $method,
            ':extracted_json' => $rawJson
        ]);
    }

    /**
     * Retrieve extraction payload by message primary key
     */
    public static function getByMessageId($smsMessageId) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM sms_extracted_data WHERE sms_message_id = :sms_message_id LIMIT 1");
        $stmt->execute([':sms_message_id' => $smsMessageId]);
        return $stmt->fetch();
    }
}
?>
