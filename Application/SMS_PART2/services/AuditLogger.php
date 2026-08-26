<?php
/**
 * AuditLogger Service Class
 * 
 * Records system actions and user events in the database audit log.
 */

if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

require_once __DIR__ . '/../config/database.php';

class AuditLogger {
    /**
     * Log a system or operator action
     */
    public static function log($userIdentifier, $action, $targetType, $targetId, $details) {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("INSERT INTO audit_logs (user_identifier, action, target_type, target_id, details) 
                                  VALUES (:user, :action, :type, :id, :details)");
            return $stmt->execute([
                ':user' => $userIdentifier,
                ':action' => $action,
                ':type' => $targetType,
                ':id' => (int)$targetId,
                ':details' => $details
            ]);
        } catch (Exception $e) {
            // Fail silently to prevent audit logging from blocking core loops
            return false;
        }
    }
}
?>
