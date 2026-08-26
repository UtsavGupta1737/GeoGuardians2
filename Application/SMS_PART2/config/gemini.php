<?php
/**
 * Gemini API Configuration Wrapper
 */

if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

class GeminiConfig {
    /**
     * Retrieve Gemini API configuration
     * 
     * @return array
     */
    public static function get() {
        $credentialsPath = __DIR__ . '/credentials.php';
        if (!file_exists($credentialsPath)) {
            die("Configuration error: credentials.php not found.");
        }
        $credentials = require $credentialsPath;
        return $credentials['gemini'];
    }
}
