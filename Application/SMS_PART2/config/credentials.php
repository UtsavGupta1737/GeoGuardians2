<?php
/**
 * SECURE CREDENTIALS FILE
 * 
 * Keep this file secure. It contains passwords and API keys.
 */

// Define access lock
if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

return [
    'db' => [
        'host' => '127.0.0.1',
        'dbname' => 'sms_sos_gateway',
        'username' => 'root',
        'password' => '',
    ],
    'gateway' => [
        'url' => 'http://192.168.1.100:8080', // Default local server IP of the Android Gateway
        'username' => 'sms',
        'password' => '4Vv_Qs3F',
        'webhook_secret' => 'sih_webhook_secret_key_2026',
    ],
    'gemini' => [
        'api_key' => '', // Fill with your Gemini API key for natural language extraction
    ]
];
