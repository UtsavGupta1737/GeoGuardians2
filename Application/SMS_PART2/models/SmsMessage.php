<?php
/**
 * SmsMessage Model Class
 * 
 * Manages operations on the sms_messages table.
 */

if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

require_once __DIR__ . '/../config/database.php';

class SmsMessage {
    /**
     * Create a new SMS record
     */
    public static function create($conversationId, $fromNumber, $toNumber, $direction, $body, $status = 'received', $gatewayMsgId = null, $receivedAt = null) {
        $db = Database::getConnection();
        
        $sql = "INSERT INTO sms_messages 
                (gateway_message_id, conversation_id, from_number, to_number, direction, message_body, status, received_at) 
                VALUES (:gateway_message_id, :conversation_id, :from_number, :to_number, :direction, :message_body, :status, :received_at)";
        
        $stmt = $db->prepare($sql);
        
        // If receivedAt is not provided, use current time
        $dbReceivedAt = $receivedAt ? $receivedAt : date('Y-m-d H:i:s');
        
        $stmt->execute([
            ':gateway_message_id' => $gatewayMsgId,
            ':conversation_id' => $conversationId,
            ':from_number' => $fromNumber,
            ':to_number' => $toNumber,
            ':direction' => $direction,
            ':message_body' => $body,
            ':status' => $status,
            ':received_at' => $direction === 'incoming' ? $dbReceivedAt : null
        ]);
        
        return $db->lastInsertId();
    }

    /**
     * Check if gateway message ID already exists (to prevent duplicate webhook processing)
     */
    public static function isDuplicate($gatewayMsgId) {
        if (empty($gatewayMsgId)) {
            return false;
        }
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id FROM processed_gateway_messages WHERE gateway_message_id = :id LIMIT 1");
        $stmt->execute([':id' => $gatewayMsgId]);
        return $stmt->fetch() !== false;
    }

    /**
     * Find message by ID
     */
    public static function getById($id) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM sms_messages WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Update message delivery status
     */
    public static function updateStatus($id, $status, $sentAt = null) {
        $db = Database::getConnection();
        $sql = "UPDATE sms_messages SET status = :status";
        $params = [':id' => $id, ':status' => $status];
        
        if ($sentAt !== null) {
            $sql .= ", sent_at = :sent_at";
            $params[':sent_at'] = $sentAt;
        }
        
        $sql .= " WHERE id = :id";
        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Enqueue a message to the outbound queue
     */
    public static function enqueueOutbox($smsMessageId, $nextAttemptDelaySeconds = 0) {
        $db = Database::getConnection();
        $nextAttemptAt = date('Y-m-d H:i:s', time() + $nextAttemptDelaySeconds);
        
        $sql = "INSERT INTO sms_outbox (sms_message_id, next_attempt_at, status) 
                VALUES (:sms_message_id, :next_attempt_at, 'queued')";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':sms_message_id' => $smsMessageId,
            ':next_attempt_at' => $nextAttemptAt
        ]);
        return $db->lastInsertId();
    }

    /**
     * Get a list of messages based on filters
     */
    public static function getAll($limit = 100, $offset = 0, $direction = null) {
        $db = Database::getConnection();
        $sql = "SELECT m.*, c.sender_phone 
                FROM sms_messages m 
                LEFT JOIN conversations c ON m.conversation_id = c.id ";
        
        $where = [];
        $params = [];
        
        if ($direction !== null) {
            $where[] = "m.direction = :direction";
            $params[':direction'] = $direction;
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        
        $sql .= " ORDER BY m.created_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
?>
