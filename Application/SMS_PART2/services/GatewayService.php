<?php
/**
 * GatewayService Class
 * 
 * Manages communication with the Android SMS Gateway REST API.
 */

if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

require_once __DIR__ . '/../config/gateway.php';
require_once __DIR__ . '/../config/database.php';

class GatewayService {
    /**
     * Dispatch an SMS message to the Android device for cellular transmission
     * 
     * @param string $toNumber Recipient mobile number
     * @param string $messageText Body text
     * @return array [success => bool, gateway_id => string|null, error => string|null]
     */
    public static function sendSms($toNumber, $messageText) {
        $config = GatewayConfig::get();
        
        // Dynamically load the gateway URL from DB (allows admin dashboard edits)
        $url = self::getGatewayUrl($config['url']);
        
        // If running in local simulation / test mode, return success immediately
        if (stripos($url, 'localhost/SMS_PART2') !== false || stripos($url, '127.0.0.1/SMS_PART2') !== false) {
            return [
                'success' => true,
                'gateway_id' => 'sim_gw_' . mt_rand(10000, 99999),
                'error' => null
            ];
        }

        $username = $config['username'];
        $password = $config['password'];

        $endpoint = rtrim($url, '/') . '/message';

        $payload = [
            'textMessage' => [
                'text' => $messageText
            ],
            'phoneNumbers' => [
                $toNumber
            ]
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($username . ':' . $password)
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 200 || $httpCode === 201 || $httpCode === 202) {
            $data = json_decode($response, true);
            // Capcom6 structure wraps message metadata inside the return structure
            $gatewayId = null;
            if (isset($data['id'])) {
                $gatewayId = $data['id'];
            } elseif (isset($data['messageId'])) {
                $gatewayId = $data['messageId'];
            } elseif (is_array($data) && count($data) > 0 && isset($data[0]['id'])) {
                $gatewayId = $data[0]['id']; // local mode returns list of sent items
            } else {
                $gatewayId = uniqid('gwmsg_');
            }

            return [
                'success' => true,
                'gateway_id' => $gatewayId,
                'error' => null
            ];
        } else {
            return [
                'success' => false,
                'gateway_id' => null,
                'error' => $error ? $error : "Server HTTP Error: " . $httpCode . " (Response: " . substr($response, 0, 100) . ")"
            ];
        }
    }

    /**
     * Retrieve current gateway URL configuration from system settings
     */
    public static function getGatewayUrl($defaultUrl) {
        $db = Database::getConnection();
        try {
            $stmt = $db->prepare("SELECT config_value FROM system_config WHERE config_key = 'gateway_url' LIMIT 1");
            $stmt->execute();
            $val = $stmt->fetchColumn();
            return !empty($val) ? $val : $defaultUrl;
        } catch (Exception $e) {
            return $defaultUrl;
        }
    }
}
