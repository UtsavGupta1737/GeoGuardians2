CREATE DATABASE IF NOT EXISTS sms_sos_gateway;
USE sms_sos_gateway;

-- Disable foreign key checks temporarily during setup
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Registered SMS Numbers (Central Gateway SIMs)
DROP TABLE IF EXISTS sms_numbers;
CREATE TABLE sms_numbers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone_number VARCHAR(20) NOT NULL UNIQUE,
    alias VARCHAR(100) DEFAULT 'Primary Central SOS',
    is_primary TINYINT(1) DEFAULT 1,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Gateway Devices
DROP TABLE IF EXISTS gateway_devices;
CREATE TABLE gateway_devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(100) NOT NULL UNIQUE,
    sms_number_id INT NOT NULL,
    model VARCHAR(100) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sms_number_id) REFERENCES sms_numbers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Webhook Deduplication Logging
DROP TABLE IF EXISTS processed_gateway_messages;
CREATE TABLE processed_gateway_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gateway_message_id VARCHAR(100) NOT NULL UNIQUE,
    is_sos TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Conversations (The communication thread with a citizen)
DROP TABLE IF EXISTS conversations;
CREATE TABLE conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_phone VARCHAR(20) NOT NULL,
    sms_number_id INT NOT NULL,
    last_message_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sms_number_id) REFERENCES sms_numbers(id) ON DELETE CASCADE,
    INDEX idx_sender_phone (sender_phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. SMS Messages (Persisted SOS only)
DROP TABLE IF EXISTS sms_messages;
CREATE TABLE sms_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gateway_message_id VARCHAR(100) DEFAULT NULL,
    conversation_id INT NOT NULL,
    from_number VARCHAR(20) NOT NULL,
    to_number VARCHAR(20) NOT NULL,
    direction ENUM('incoming', 'outgoing') NOT NULL,
    message_body TEXT NOT NULL,
    status ENUM('queued', 'sending', 'sent', 'delivered', 'failed', 'received', 'processed') NOT NULL,
    received_at DATETIME DEFAULT NULL,
    sent_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    INDEX idx_direction (direction)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. SOS Requests (The Extracted Emergency Incident - many-to-one conversation)
DROP TABLE IF EXISTS sos_requests;
CREATE TABLE sos_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    disaster_type VARCHAR(50) DEFAULT 'unknown',
    latitude DECIMAL(10, 8) DEFAULT NULL,
    longitude DECIMAL(11, 8) DEFAULT NULL,
    people_count INT DEFAULT 1,
    injured_count INT DEFAULT 0,
    priority ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') DEFAULT 'MEDIUM',
    help_required VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Extracted Metadata
DROP TABLE IF EXISTS sms_extracted_data;
CREATE TABLE sms_extracted_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sms_message_id INT NOT NULL,
    latitude DECIMAL(10, 8) DEFAULT NULL,
    longitude DECIMAL(11, 8) DEFAULT NULL,
    people_count INT DEFAULT 1,
    injured_count INT DEFAULT 0,
    disaster_type VARCHAR(50) DEFAULT NULL,
    help_required VARCHAR(255) DEFAULT NULL,
    priority VARCHAR(20) DEFAULT NULL,
    confidence DECIMAL(5,2) DEFAULT NULL,
    extraction_method ENUM('rule_based', 'ai_gemini', 'fallback') NOT NULL,
    extracted_json TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sms_message_id) REFERENCES sms_messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Outbound Queue Table
DROP TABLE IF EXISTS sms_outbox;
CREATE TABLE sms_outbox (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sms_message_id INT NOT NULL,
    attempt_count INT DEFAULT 0,
    next_attempt_at DATETIME DEFAULT NULL,
    locked_at DATETIME DEFAULT NULL,
    last_error TEXT DEFAULT NULL,
    status ENUM('queued', 'sending', 'sent', 'failed') DEFAULT 'queued',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sms_message_id) REFERENCES sms_messages(id) ON DELETE CASCADE,
    INDEX idx_status_next_attempt (status, next_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Audit Logs Table
DROP TABLE IF EXISTS audit_logs;
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_identifier VARCHAR(100) NOT NULL DEFAULT 'System',
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50) DEFAULT NULL,
    target_id INT DEFAULT NULL,
    details TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. System Config Table
DROP TABLE IF EXISTS system_config;
CREATE TABLE system_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(50) UNIQUE NOT NULL,
    config_value TEXT DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed initial configs
INSERT INTO system_config (config_key, config_value) VALUES 
('gateway_url', 'http://192.168.1.100:8080'),
('gateway_username', 'admin'),
('gateway_password', 'password123'),
('webhook_secret', 'sih_webhook_secret_key_2026'),
('gemini_api_key', ''),
('grouping_radius_km', '2.0');

INSERT INTO sms_numbers (phone_number, alias, is_primary, status) VALUES 
('+919876543210', 'Primary Central SOS', 1, 'active');

-- 11. Contacts Registry
DROP TABLE IF EXISTS contacts;
CREATE TABLE contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone_number VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL DEFAULT 'Unknown Sender',
    organization VARCHAR(150) DEFAULT NULL,
    location VARCHAR(150) DEFAULT NULL,
    total_messages INT DEFAULT 0,
    total_sos INT DEFAULT 0,
    last_message_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_phone (phone_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Enable foreign key checks again
SET FOREIGN_KEY_CHECKS = 1;
