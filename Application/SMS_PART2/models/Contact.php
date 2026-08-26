<?php
/**
 * Contact Model Class
 */

if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

require_once __DIR__ . '/../config/database.php';

class Contact {
    /**
     * Find contact by phone number
     */
    public static function getByPhoneNumber($phoneNumber) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM contacts WHERE phone_number = :phone_number");
        $stmt->execute([':phone_number' => $phoneNumber]);
        return $stmt->fetch();
    }

    /**
     * Get contact by ID
     */
    public static function getById($id) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM contacts WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Create contact if not exists, or update statistics
     */
    public static function createOrUpdate($phoneNumber, $name = 'Unknown Sender') {
        $db = Database::getConnection();
        $contact = self::getByPhoneNumber($phoneNumber);
        
        if ($contact) {
            $stmt = $db->prepare("UPDATE contacts SET 
                                  total_messages = total_messages + 1, 
                                  last_message_at = NOW() 
                                  WHERE id = :id");
            $stmt->execute([':id' => $contact['id']]);
            return $contact['id'];
        } else {
            $stmt = $db->prepare("INSERT INTO contacts (phone_number, name, total_messages, last_message_at) 
                                  VALUES (:phone_number, :name, 1, NOW())");
            $stmt->execute([
                ':phone_number' => $phoneNumber,
                ':name' => $name
            ]);
            return $db->lastInsertId();
        }
    }

    /**
     * Increment total SOS alerts sent by contact
     */
    public static function incrementSosCounter($contactId) {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE contacts SET total_sos = total_sos + 1 WHERE id = :id");
        return $stmt->execute([':id' => $contactId]);
    }

    /**
     * Edit full contact metadata
     */
    public static function updateDetails($id, $name, $organization = null, $location = null) {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE contacts SET 
                              name = :name, 
                              organization = :organization, 
                              location = :location 
                              WHERE id = :id");
        return $stmt->execute([
            ':id' => $id,
            ':name' => $name,
            ':organization' => $organization,
            ':location' => $location
        ]);
    }

    /**
     * Get list of all contacts
     */
    public static function getAll() {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT * FROM contacts ORDER BY last_message_at DESC");
        return $stmt->fetchAll();
    }

    /**
     * Delete a single contact by ID
     */
    public static function delete($id) {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM contacts WHERE id = :id");
        return $stmt->execute([':id' => (int)$id]);
    }

    /**
     * Delete multiple contacts by array of IDs
     */
    public static function deleteByIds($ids) {
        if (empty($ids)) return false;
        $db = Database::getConnection();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("DELETE FROM contacts WHERE id IN ($placeholders)");
        return $stmt->execute($ids);
    }
}
