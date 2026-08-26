<?php
/**
 * Outbound Queue Worker Trigger
 * 
 * Invoked periodically to process the sms_outbox retry queue.
 * Can be called via cron job or background AJAX requests from the dashboard.
 */

// Enable security access check
define('SECURE_ACCESS', true);

require_once __DIR__ . '/../../services/SmsService.php';

header('Content-Type: application/json');

try {
    $processedCount = SmsService::processOutboxQueue();
    echo json_encode([
        'success' => true,
        'processed_count' => $processedCount,
        'timestamp' => date('c')
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Queue processing failed: ' . $e->getMessage()
    ]);
}
?>
