<?php
/**
 * SmsNumber Model Class
 * 
 * Manages registered SIM numbers and enforces the primary-active receiver constraints.
 */

if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

require_once __DIR__ . '/../config/database.php';

class SmsNumber {
    /**
     * Set a specific registered number as the active central primary SOS receiver
     */
    public static function setPrimary($id) {
        $db = Database::getConnection();
        try {
            $db->beginTransaction();
            
            // Set all active numbers to non-primary
            $db->prepare("UPDATE sms_numbers SET is_primary = 0 WHERE status = 'active'")->execute();
            
            // Activate and set the chosen number to primary
            $stmt = $db->prepare("UPDATE sms_numbers SET is_primary = 1 WHERE id = :id AND status = 'active'");
            $success = $stmt->execute([':id' => $id]);
            
            $db->commit();
            return $success;
        } catch (Exception $e) {
            $db->rollBack();
            return false;
        }
    }

    /**
     * Get the active primary SIM number record
     */
    public static function getPrimary() {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT * FROM sms_numbers WHERE is_primary = 1 AND status = 'active' LIMIT 1");
        return $stmt->fetch();
    }
}
?>
