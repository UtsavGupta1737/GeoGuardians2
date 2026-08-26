<?php
/**
 * Database Connection Helper for DisasterSafe SMS SOS Gateway
 * 
 * Supports both MySQL and DisasterSafe Native SQLite database engine.
 */

if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

class Database {
    private static $connection = null;
    private static $disasterSafePdo = null;

    /**
     * Get active SMS gateway database connection
     * Tries MySQL first if credentials exist, falls back seamlessly to SQLite.
     * 
     * @return PDO
     */
    public static function getConnection() {
        if (self::$connection === null) {
            $credentialsPath = __DIR__ . '/credentials.php';
            $dbConfig = null;
            if (file_exists($credentialsPath)) {
                $credentials = require $credentialsPath;
                $dbConfig = $credentials['db'] ?? null;
            }
            
            // 1. Try MySQL Connection if configured
            if ($dbConfig && !empty($dbConfig['host']) && !empty($dbConfig['dbname'])) {
                try {
                    $dsn = "mysql:host=" . $dbConfig['host'] . ";dbname=" . $dbConfig['dbname'] . ";charset=utf8mb4";
                    $options = [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                    ];
                    self::$connection = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $options);
                    return self::$connection;
                } catch (PDOException $e) {
                    // Fall back to SQLite engine seamlessly
                }
            }

            // 2. Fallback: Standalone SQLite Engine for SMS Gateway
            $sqlitePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'sms_gateway.sqlite';
            self::$connection = new PDO("sqlite:" . $sqlitePath, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            self::initializeSqliteTables(self::$connection);
        }
        return self::$connection;
    }

    /**
     * Get active connection to the primary DisasterSafe SQLite Database (emergency_sos, users, etc.)
     * 
     * @return PDO
     */
    public static function getDisasterSafePdo() {
        if (self::$disasterSafePdo === null) {
            $rootDbFile = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'app.sqlite';
            self::$disasterSafePdo = new PDO("sqlite:" . $rootDbFile, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            self::$disasterSafePdo->exec("PRAGMA journal_mode = WAL;");
            self::$disasterSafePdo->exec("PRAGMA busy_timeout = 10000;");
        }
        return self::$disasterSafePdo;
    }

    /**
     * Auto-initialize SQLite tables for standalone operation
     */
    private static function initializeSqliteTables(PDO $pdo) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS sms_numbers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                phone_number TEXT NOT NULL UNIQUE,
                alias TEXT DEFAULT 'Primary Central SOS',
                is_primary INTEGER DEFAULT 1,
                status TEXT DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS processed_gateway_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                gateway_message_id TEXT NOT NULL UNIQUE,
                is_sos INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS conversations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sender_phone TEXT NOT NULL,
                sms_number_id INTEGER NOT NULL,
                last_message_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS sms_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                gateway_message_id TEXT,
                conversation_id INTEGER NOT NULL,
                from_number TEXT NOT NULL,
                to_number TEXT NOT NULL,
                direction TEXT NOT NULL,
                message_body TEXT NOT NULL,
                status TEXT NOT NULL,
                received_at DATETIME,
                sent_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS sos_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                conversation_id INTEGER NOT NULL,
                disaster_type TEXT DEFAULT 'unknown',
                latitude REAL,
                longitude REAL,
                people_count INTEGER DEFAULT 1,
                injured_count INTEGER DEFAULT 0,
                priority TEXT DEFAULT 'CRITICAL',
                help_required TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS sms_extracted_data (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sms_message_id INTEGER NOT NULL,
                latitude REAL,
                longitude REAL,
                people_count INTEGER DEFAULT 1,
                injured_count INTEGER DEFAULT 0,
                disaster_type TEXT,
                help_required TEXT,
                priority TEXT,
                confidence REAL,
                extraction_method TEXT,
                extracted_json TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS sms_outbox (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sms_message_id INTEGER NOT NULL,
                attempt_count INTEGER DEFAULT 0,
                next_attempt_at DATETIME,
                locked_at DATETIME,
                last_error TEXT,
                status TEXT DEFAULT 'queued',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS system_config (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                config_key TEXT NOT NULL UNIQUE,
                config_value TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                actor TEXT,
                action TEXT,
                entity_type TEXT,
                entity_id INTEGER,
                details TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // Seed default primary SMS number if not present
        $count = (int)$pdo->query("SELECT COUNT(*) FROM sms_numbers")->fetchColumn();
        if ($count === 0) {
            $pdo->exec("INSERT INTO sms_numbers (phone_number, alias, is_primary, status) VALUES ('+919999999999', 'Primary Central SOS', 1, 'active');");
        }
    }
}
