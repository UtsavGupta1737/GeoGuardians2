<?php
/**
 * SosRequest Model Class
 * 
 * Manages operations on the sos_requests table.
 */

if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

require_once __DIR__ . '/../config/database.php';

class SosRequest {
    /**
     * Create an emergency SOS request record
     */
    public static function create($conversationId, $disasterType = 'unknown', $latitude = null, $longitude = null, $peopleCount = 1, $injuredCount = 0, $priority = 'MEDIUM', $helpRequired = null) {
        $db = Database::getConnection();
        
        $sql = "INSERT INTO sos_requests 
                (conversation_id, disaster_type, latitude, longitude, people_count, injured_count, priority, help_required) 
                VALUES (:conversation_id, :disaster_type, :latitude, :longitude, :people_count, :injured_count, :priority, :help_required)";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':conversation_id' => $conversationId,
            ':disaster_type' => strtolower($disasterType),
            ':latitude' => $latitude,
            ':longitude' => $longitude,
            ':people_count' => (int)$peopleCount,
            ':injured_count' => (int)$injuredCount,
            ':priority' => strtoupper($priority),
            ':help_required' => $helpRequired
        ]);
        
        return $db->lastInsertId();
    }

    /**
     * Retrieve detailed SOS alert with conversation info
     */
    public static function getById($id) {
        $db = Database::getConnection();
        $sql = "SELECT s.*, c.sender_phone 
                FROM sos_requests s
                JOIN conversations c ON s.conversation_id = c.id
                WHERE s.id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Update priority severity
     */
    public static function updatePriority($id, $priority) {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE sos_requests SET priority = :priority WHERE id = :id");
        return $stmt->execute([
            ':id' => $id,
            ':priority' => $priority
        ]);
    }

    /**
     * Update incident fields (latitude, longitude, counts, help_required, disaster_type)
     */
    public static function updateIncident($id, $fields) {
        $db = Database::getConnection();
        $sets = [];
        $params = [':id' => $id];
        
        foreach ($fields as $key => $val) {
            $sets[] = "$key = :$key";
            $params[":$key"] = $val;
        }
        
        if (empty($sets)) return false;
        
        $sql = "UPDATE sos_requests SET " . implode(", ", $sets) . " WHERE id = :id";
        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Fetch all SOS alerts with filtering options
     */
    public static function getAll($filters = []) {
        $db = Database::getConnection();
        // Return SOS requests joined with conversation sender, and the snippet of the most recent message in the conversation
        $sql = "SELECT s.*, c.sender_phone, 
                      (SELECT message_body FROM sms_messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as message_body
                FROM sos_requests s
                JOIN conversations c ON s.conversation_id = c.id";
        
        $where = [];
        $params = [];
        
        if (isset($filters['priority']) && $filters['priority'] !== 'ALL') {
            $where[] = "s.priority = :priority";
            $params[':priority'] = $filters['priority'];
        }
        
        if (isset($filters['disaster_type']) && $filters['disaster_type'] !== 'ALL') {
            $where[] = "s.disaster_type = :disaster_type";
            $params[':disaster_type'] = strtolower($filters['disaster_type']);
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        
        // Order critical first, then high, then medium, then low
        $sql .= " ORDER BY s.created_at DESC, s.id DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get real-time metric counts for the dashboard
     */
    public static function getMetrics() {
        $db = Database::getConnection();
        
        $metrics = [
            'total_messages' => 0,
            'total_sos' => 0,
            'critical_sos' => 0,
            'pending_sos' => 0
        ];
        
        try {
            // Count total messages
            $stmt = $db->query("SELECT COUNT(*) FROM sms_messages");
            $metrics['total_messages'] = (int)$stmt->fetchColumn();
            
            // Count total SOS requests
            $stmt = $db->query("SELECT COUNT(*) FROM sos_requests");
            $metrics['total_sos'] = (int)$stmt->fetchColumn();
            
            // Count Critical SOS requests
            $stmt = $db->query("SELECT COUNT(*) FROM sos_requests WHERE priority = 'CRITICAL'");
            $metrics['critical_sos'] = (int)$stmt->fetchColumn();
            
            // In the simplified model, active/pending SOS is the total registered SOS count
            $metrics['pending_sos'] = $metrics['total_sos'];
        } catch (PDOException $e) {
            // Quiet fail
        }
        
        return $metrics;
    }
}
?>
