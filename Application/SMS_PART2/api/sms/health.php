<?php
/**
 * Asynchronous Gateway Health Checker API
 * 
 * Verifies gateway reachability, basic auth authentication, webhook heartbeats, and updates telemetry.
 * Decouples reachability checks from authentication to isolate credentials errors from network timeouts.
 */

header('Content-Type: application/json');

// Enable security access check
define('SECURE_ACCESS', true);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/gateway.php';
require_once __DIR__ . '/../../services/GatewayService.php';

$db = Database::getConnection();
$gatewayConfig = GatewayConfig::get();
$url = GatewayService::getGatewayUrl($gatewayConfig['url']);

// 1. Run Active Handshake Ping (Curl)
$reachability = 'FAILED';
$authentication = 'UNTESTED';
$httpCode = 0;
$curlError = '';

$ch = curl_init(rtrim($url, '/'));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true); // Include headers in output to parse server types
curl_setopt($ch, CURLOPT_TIMEOUT, 3); // 3 seconds timeout
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Basic ' . base64_encode($gatewayConfig['username'] . ':' . $gatewayConfig['password'])
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
curl_close($ch);

if ($error && (strpos($error, 'Failed to connect') !== false || strpos($error, 'Could not resolve') !== false || strpos($error, 'timed out') !== false)) {
    $reachability = 'FAILED';
    $authentication = 'UNTESTED';
    $curlError = $error;
} else {
    $reachability = 'SUCCESS';
    
    // Parse response headers and body
    $responseHeaders = substr($response, 0, $headerSize);
    $responseBody = substr($response, $headerSize);
    
    // Parse JSON body if present
    $responseJson = json_decode($responseBody, true);
    
    $hasGatewayHeader = (
        stripos($responseHeaders, 'Server: Ktor') !== false || 
        stripos($responseHeaders, 'Server: AndroidGateway') !== false
    );
    
    $hasGatewayBody = false;
    $detectedModel = null;
    
    if (is_array($responseJson)) {
        // Match status-based signatures
        if (isset($responseJson['status']) && in_array(strtolower($responseJson['status']), ['ok', 'pass', 'success'], true)) {
            $hasGatewayBody = true;
        }
        // Match health check-based signatures (checks AND version/releaseId)
        if (isset($responseJson['checks']) && is_array($responseJson['checks']) && (isset($responseJson['version']) || isset($responseJson['releaseId']))) {
            $hasGatewayBody = true;
        }
        
        // Extract model or deviceId for diagnostic display
        if (isset($responseJson['model'])) {
            $detectedModel = $responseJson['model'];
        } elseif (isset($responseJson['deviceId'])) {
            $detectedModel = $responseJson['deviceId'];
        }
    }
    
    $isGateway = ($hasGatewayHeader || $hasGatewayBody);
    $isLocalSimulator = (stripos($url, 'localhost/SMS_PART2') !== false);
    
    if ($httpCode === 401) {
        $authentication = 'FAILED';
    } elseif ($isGateway) {
        if ($httpCode === 405 || $httpCode === 200 || $httpCode === 201 || $httpCode === 404) {
            $authentication = 'PASSED';
            if ($detectedModel) {
                $authentication .= ' (Device: ' . $detectedModel . ')';
            }
        } else {
            $authentication = 'UNVERIFIED (Not a Gateway)';
        }
    } elseif ($isLocalSimulator) {
        $authentication = 'PASSED (Simulated / Test Mode)';
    } else {
        $authentication = 'UNVERIFIED (Not a Gateway)';
    }
}

// 2. Fetch Heartbeat Telemetry from DB
$telemetry = [
    'last_seen' => null,
    'last_event' => 'N/A',
    'last_device_id' => 'N/A',
    'last_error' => null
];

try {
    $stmt = $db->query("SELECT config_key, config_value FROM system_config WHERE config_key IN ('gateway_last_seen', 'gateway_last_event', 'gateway_last_device_id', 'gateway_last_error')");
    $rows = $stmt->fetchAll();
    
    foreach ($rows as $row) {
        $key = str_replace('gateway_', '', $row['config_key']);
        $telemetry[$key] = $row['config_value'];
    }
    
    // Inject transient device model if detected during active health check
    if (!empty($detectedModel)) {
        $telemetry['last_device_id'] = $detectedModel;
    }
} catch (Exception $e) {
    // Fail silently
}

// 3. Compute Time Difference
$timeDiffSeconds = null;
if (!empty($telemetry['last_seen'])) {
    $timeDiffSeconds = time() - strtotime($telemetry['last_seen']);
}

// 4. Calculate Unified Status String
$status = 'OFFLINE';
if ($reachability === 'SUCCESS') {
    if ($authentication === 'FAILED') {
        $status = 'AUTH_FAILED';
    } elseif ($authentication === 'UNVERIFIED (Not a Gateway)') {
        $status = 'AUTH_FAILED'; // Flag as authentication/configuration error
    } elseif ($timeDiffSeconds !== null && $timeDiffSeconds < 120) {
        $status = 'ONLINE';
    } else {
        $status = 'ONLINE_NO_EVENTS';
    }
} else {
    if ($timeDiffSeconds !== null && $timeDiffSeconds < 300) {
        $status = 'CONNECTION_ISSUE';
    } else {
        $status = 'OFFLINE';
    }
}

// Format readable last_seen counter
$lastSeenReadable = 'Never';
if ($timeDiffSeconds !== null) {
    if ($timeDiffSeconds < 60) {
        $lastSeenReadable = $timeDiffSeconds . 's ago';
    } else {
        $lastSeenReadable = round($timeDiffSeconds / 60) . 'm ago';
    }
}

// 5. Output Response
echo json_encode([
    'success' => true,
    'status' => $status,
    'endpoint' => $url,
    'reachability' => $reachability,
    'authentication' => $authentication,
    'http_code' => $httpCode,
    'telemetry' => [
        'last_seen' => $telemetry['last_seen'],
        'last_seen_readable' => $lastSeenReadable,
        'last_event' => $telemetry['last_event'],
        'last_device_id' => $telemetry['last_device_id'],
        'last_error' => $telemetry['last_error']
    ],
    'timestamp' => date('c')
]);
?>
