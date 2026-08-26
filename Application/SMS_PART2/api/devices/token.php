<?php
/**
 * Device FCM Token Registration Endpoint
 *
 * POST /SMS_PART2/api/devices/token
 * Body: {"userId": "...", "token": "..."}
 *
 * Stores/refreshes the association between an app user and their FCM token so
 * the backend can address devices individually in addition to topic broadcast.
 * The endpoint never returns tokens and never logs them (only masked prefix).
 */

if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/AuditLogger.php';

header('Content-Type: application/json');

function respond($code, $arr) {
    http_response_code($code);
    echo json_encode($arr);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, ['success' => false, 'error' => 'Method not allowed. Use POST.']);
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    respond(400, ['success' => false, 'error' => 'Invalid JSON body.']);
}

$userId = isset($input['userId']) ? trim((string)$input['userId']) : '';
$token  = isset($input['token']) ? trim((string)$input['token']) : '';

// userId: app-generated identifier (guest_... or UUID-style). Restrict charset.
if ($userId === '' || !preg_match('/^[a-zA-Z0-9_\-]{1,64}$/', $userId)) {
    respond(400, ['success' => false, 'error' => 'Invalid userId format.']);
}

// FCM tokens are long opaque strings; enforce a sane bounded charset/length.
if ($token === '' || strlen($token) < 32 || strlen($token) > 4096 || !preg_match('/^[a-zA-Z0-9_:\-\.]+$/', $token)) {
    respond(400, ['success' => false, 'error' => 'Invalid token format.']);
}

try {
    $db = Database::getConnection();

    // Minimal registry table (idempotent): one row per device token.
    // Necessary infrastructure for per-device FCM fan-out; no other schema change.
    $db->exec("CREATE TABLE IF NOT EXISTS fcm_tokens (
        token VARCHAR(512) PRIMARY KEY,
        user_id VARCHAR(64) NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_fcm_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // A token identifies ONE device: if it moved to another user, take it over.
    $stmt = $db->prepare(
        "INSERT INTO fcm_tokens (token, user_id) VALUES (:token, :user_id)
         ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)"
    );
    $stmt->execute([':token' => substr($token, 0, 512), ':user_id' => $userId]);

    $masked = substr($token, 0, 6) . '...' . substr($token, -4);
    AuditLogger::log('System', 'FCM_TOKEN_REGISTERED', 'Device', 0, "FCM token registered for user (token: {$masked})");

    respond(200, ['success' => true]);
} catch (Exception $e) {
    AuditLogger::log('System', 'FCM_TOKEN_FAILED', 'Device', 0, "Token registration failed: " . $e->getMessage());
    respond(500, ['success' => false, 'error' => 'Registration failed.']);
}
