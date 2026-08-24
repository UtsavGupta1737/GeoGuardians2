-- =======================================================
-- GeoGuardians / DisasterSafe MySQL Database Schema
-- Compatible with WAMP Server / phpMyAdmin
-- =======================================================

CREATE DATABASE IF NOT EXISTS `disastersafe` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `disastersafe`;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('citizen', 'authority', 'volunteer', 'admin') DEFAULT 'citizen',
    `phone` VARCHAR(20) NULL,
    `badge_number` VARCHAR(50) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. SOS Emergency Alerts Table
CREATE TABLE IF NOT EXISTS `sos_alerts` (
    `id` VARCHAR(50) PRIMARY KEY,
    `victim_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) NULL,
    `latitude` DECIMAL(10, 8) NOT NULL,
    `longitude` DECIMAL(11, 8) NOT NULL,
    `emergency_type` VARCHAR(50) DEFAULT 'General',
    `severity` ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Critical',
    `status` ENUM('Pending', 'Assigned', 'Resolved') DEFAULT 'Pending',
    `people_count` VARCHAR(20) DEFAULT '1',
    `quick_needs` TEXT NULL,
    `message` TEXT NULL,
    `assigned_unit` VARCHAR(100) NULL,
    `eta_minutes` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Facilities Table
CREATE TABLE IF NOT EXISTS `facilities` (
    `id` VARCHAR(50) PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `latitude` DECIMAL(10, 8) NOT NULL,
    `longitude` DECIMAL(11, 8) NOT NULL,
    `total_capacity` INT DEFAULT 100,
    `available_capacity` INT DEFAULT 50,
    `contact` VARCHAR(50) NULL,
    `status` VARCHAR(50) DEFAULT 'Operational'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Dispatches Table
CREATE TABLE IF NOT EXISTS `dispatches` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sos_id` VARCHAR(50) NOT NULL,
    `team_name` VARCHAR(100) NOT NULL,
    `vehicle_type` VARCHAR(50) DEFAULT 'Ambulance / Rescue Van',
    `responder_count` INT DEFAULT 4,
    `eta_minutes` INT DEFAULT 10,
    `dispatched_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Master Disaster Resources Catalog Table
CREATE TABLE IF NOT EXISTS `master_resources` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `resource_code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `category` VARCHAR(100) NOT NULL,
    `total_stock` INT NOT NULL,
    `available_stock` INT NOT NULL,
    `distributed_stock` INT DEFAULT 0,
    `unit` VARCHAR(50) DEFAULT 'units',
    `primary_warehouse` VARCHAR(150) NOT NULL,
    `status` VARCHAR(50) DEFAULT 'In Stock',
    `icon` VARCHAR(50) DEFAULT 'fa-box',
    `color` VARCHAR(50) DEFAULT 'indigo',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Resource Distributions Tracking Ledger Table
CREATE TABLE IF NOT EXISTS `resource_distributions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `resource_id` INT NOT NULL,
    `destination_type` VARCHAR(100) NOT NULL,
    `destination_name` VARCHAR(150) NOT NULL,
    `location_address` VARCHAR(255) NOT NULL,
    `gps_lat` DECIMAL(10, 8) NOT NULL,
    `gps_lng` DECIMAL(11, 8) NOT NULL,
    `quantity_distributed` INT NOT NULL,
    `unit` VARCHAR(50) NOT NULL,
    `dispatched_by` VARCHAR(100) DEFAULT 'Superadmin Tactical Command',
    `contact_officer` VARCHAR(100) NOT NULL,
    `distribution_status` VARCHAR(50) DEFAULT 'Delivered / On-Site',
    `distributed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `notes` TEXT NULL,
    FOREIGN KEY (`resource_id`) REFERENCES `master_resources`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Sample Data
INSERT INTO `facilities` (`id`, `name`, `type`, `latitude`, `longitude`, `total_capacity`, `available_capacity`, `contact`, `status`) VALUES
('HOSP-01', 'District Multi-Specialty Hospital', 'Hospital', 28.4800, 77.4800, 150, 42, '+91 120-2500111', 'Operational'),
('HOSP-02', 'Apex Trauma & Emergency Care', 'Hospital', 28.5200, 77.3800, 80, 12, '+91 120-2500222', 'Near Capacity'),
('SHELTER-01', 'Sector 4 Community Relief Shelter', 'Relief Shelter', 28.4400, 77.5200, 300, 120, '+91 9876500001', 'Operational'),
('SHELTER-02', 'Govt. Stadium Evacuation Camp', 'Relief Shelter', 28.6000, 77.2200, 500, 80, '+91 9876500002', 'Near Capacity'),
('FIRE-01', 'Central Fire & Rescue Depot', 'Fire Station', 28.5400, 77.4100, 50, 50, '101', 'Operational'),
('POLICE-01', 'Sector 12 Police Command Hub', 'Police Station', 28.4600, 77.4900, 60, 60, '112', 'Operational')
ON DUPLICATE KEY UPDATE `id`=`id`;
