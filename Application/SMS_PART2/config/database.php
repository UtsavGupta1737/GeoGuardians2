<?php
/**
 * Database Connection Helper
 */

if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

class Database {
    private static $connection = null;

    /**
     * Get active database connection (Singleton pattern)
     * 
     * @return PDO
     */
    public static function getConnection() {
        if (self::$connection === null) {
            $credentialsPath = __DIR__ . '/credentials.php';
            if (!file_exists($credentialsPath)) {
                die("Configuration error: credentials.php not found.");
            }
            
            $credentials = require $credentialsPath;
            $dbConfig = $credentials['db'];
            
            try {
                $dsn = "mysql:host=" . $dbConfig['host'] . ";dbname=" . $dbConfig['dbname'] . ";charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ];
                self::$connection = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $options);
            } catch (PDOException $e) {
                die("Database connection failed: " . $e->getMessage());
            }
        }
        return self::$connection;
    }
}
