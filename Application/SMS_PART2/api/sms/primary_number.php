<?php
/**
 * API Endpoint: Get Central Primary SOS Gateway Number
 * 
 * Returns the currently active primary phone number registered in the SMS Gateway system.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

define('SECURE_ACCESS', true);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/SmsNumber.php';

try {
    $primary = SmsNumber::getPrimary();
    if ($primary && !empty($primary['phone_number'])) {
        echo json_encode([
            'success' => true,
            'primary_number' => $primary['phone_number'],
            'alias' => $primary['alias'] ?? 'Disaster Command'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'primary_number' => '+919876543210',
            'alias' => 'Disaster Command Center'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'primary_number' => '+919876543210',
        'alias' => 'Disaster Command Center'
    ]);
}
?>