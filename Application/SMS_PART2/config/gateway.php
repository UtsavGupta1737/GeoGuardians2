<?php
/**
 * Gateway Configuration Wrapper
 */

if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

class GatewayConfig {
    /**
     * Retrieve gateway credentials and connection details
     * 
     * @return array
     */
    public static function get() {
        $credentialsPath = __DIR__ . '/credentials.php';
        if (!file_exists($credentialsPath)) {
            die("Configuration error: credentials.php not found.");
        }
        $credentials = require $credentialsPath;
        return $credentials['gateway'];
    }
}
