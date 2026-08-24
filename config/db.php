<?php
/**
 * Database Configuration & Auto-Setup (PDO MySQL)
 * GeoGuardians - DisasterSafe
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$dbHost = '127.0.0.1';
$dbPort = '3306'; // Default WAMP MySQL port
$dbName = 'disastersafe';
$dbUser = 'root';
$dbPass = '';     // Default WAMP password

$pdo = null;
$dbConnected = false;

try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
    $rawPdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

    // Ensure database exists
    $rawPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $rawPdo->exec("USE `{$dbName}`");
    $pdo = $rawPdo;
    $dbConnected = true;

    // Create tables if they do not exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(150) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `role` ENUM('citizen', 'authority', 'volunteer', 'admin') DEFAULT 'citizen',
            `phone` VARCHAR(20) NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

        CREATE TABLE IF NOT EXISTS `dispatches` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `sos_id` VARCHAR(50) NOT NULL,
            `team_name` VARCHAR(100) NOT NULL,
            `vehicle_type` VARCHAR(50) DEFAULT 'Ambulance / Rescue Van',
            `responder_count` INT DEFAULT 4,
            `eta_minutes` INT DEFAULT 10,
            `dispatched_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Populate initial seed data if table is empty
    $alertCount = $pdo->query("SELECT COUNT(*) FROM `sos_alerts`")->fetchColumn();
    if ($alertCount == 0) {
        $seedAlerts = [
            ['SOS-001', 'Amit Kumar', '+91 9876511001', 28.5355, 77.3910, 'Fire', 'Critical', 'Pending', '4-10', 'Fire Truck, Ambulance, Oxygen', 'Building caught fire on the 3rd floor, stairs blocked.'],
            ['SOS-002', 'Neha Sharma', '+91 9876511002', 28.4595, 77.0266, 'Flood', 'High', 'Assigned', '10+', 'Evacuation Boat, Food/Water', 'Water is above waist level, elderly people trapped.'],
            ['SOS-003', 'Rohan Gupta', '+91 9876511003', 28.6139, 77.2090, 'Medical', 'Critical', 'Pending', '1-3', 'Ambulance', 'Severe trauma & cardiac emergency symptoms.'],
            ['SOS-004', 'Pooja Singh', '+91 9876511004', 28.4089, 77.3178, 'Structure Collapse', 'Critical', 'Pending', '1-3', 'Heavy Machinery, Trapped, Medical Aid', 'Roof caved in due to rain, hearing voices under debris.'],
            ['SOS-005', 'Vikram Malhotra', '+91 9876511005', 28.5041, 77.0902, 'Accident', 'Medium', 'Resolved', '1', 'Police, Ambulance', 'Road collision safely transported to trauma care.'],
            ['SOS-006', 'Kavita Reddy', '+91 9876511006', 28.6500, 77.2300, 'Fire', 'High', 'Assigned', '4-10', 'Fire Truck, Evacuation', 'Electrical fire in basement spreading upwards.'],
            ['SOS-007', 'Suresh Tiwari', '+91 9876511007', 28.5300, 77.1200, 'Gas Leak', 'Critical', 'Pending', '1-3', 'Hazmat Team, Ambulance', 'Strong smell of chemical gas, citizens dizzy.'],
            ['SOS-008', 'Meera Das', '+91 9876511008', 28.4743, 77.5039, 'Flood', 'Medium', 'Pending', '10+', 'Food/Water, Shelter', 'Stuck on terrace, surrounded by flood water.']
        ];
        $insAlert = $pdo->prepare("INSERT INTO `sos_alerts` (`id`, `victim_name`, `phone`, `latitude`, `longitude`, `emergency_type`, `severity`, `status`, `people_count`, `quick_needs`, `message`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($seedAlerts as $sa) {
            $insAlert->execute($sa);
        }
    }

    $facCount = $pdo->query("SELECT COUNT(*) FROM `facilities`")->fetchColumn();
    if ($facCount == 0) {
        $seedFac = [
            ['HOSP-01', 'District Multi-Specialty Hospital', 'Hospital', 28.4800, 77.4800, 150, 42, '+91 120-2500111', 'Operational'],
            ['HOSP-02', 'Apex Trauma & Emergency Care', 'Hospital', 28.5200, 77.3800, 80, 12, '+91 120-2500222', 'Near Capacity'],
            ['SHELTER-01', 'Sector 4 Community Relief Shelter', 'Relief Shelter', 28.4400, 77.5200, 300, 120, '+91 9876500001', 'Operational'],
            ['SHELTER-02', 'Govt. Stadium Evacuation Camp', 'Relief Shelter', 28.6000, 77.2200, 500, 80, '+91 9876500002', 'Near Capacity'],
            ['FIRE-01', 'Central Fire & Rescue Depot', 'Fire Station', 28.5400, 77.4100, 50, 50, '101', 'Operational'],
            ['POLICE-01', 'Sector 12 Police Command Hub', 'Police Station', 28.4600, 77.4900, 60, 60, '112', 'Operational']
        ];
        $insFac = $pdo->prepare("INSERT INTO `facilities` (`id`, `name`, `type`, `latitude`, `longitude`, `total_capacity`, `available_capacity`, `contact`, `status`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($seedFac as $sf) {
            $insFac->execute($sf);
        }
    }
} catch (Exception $e) {
    $dbConnected = false;
}

// In-Memory Session Fallback (Guarantees demo works even if MySQL service is paused)
if (!isset($_SESSION['mock_alerts'])) {
    $_SESSION['mock_alerts'] = [
        ['id' => 'SOS-001', 'victim_name' => 'Amit Kumar', 'phone' => '+91 9876511001', 'latitude' => 28.5355, 'longitude' => 77.3910, 'emergency_type' => 'Fire', 'severity' => 'Critical', 'status' => 'Pending', 'people_count' => '4-10', 'quick_needs' => 'Fire Truck, Ambulance, Oxygen', 'message' => 'Building caught fire on 3rd floor, stairs blocked.', 'assigned_unit' => '', 'eta_minutes' => 0, 'created_at' => date('Y-m-d H:i:s', time() - 120)],
        ['id' => 'SOS-002', 'victim_name' => 'Neha Sharma', 'phone' => '+91 9876511002', 'latitude' => 28.4595, 'longitude' => 77.0266, 'emergency_type' => 'Flood', 'severity' => 'High', 'status' => 'Assigned', 'people_count' => '10+', 'quick_needs' => 'Evacuation Boat, Food/Water', 'message' => 'Water is above waist level, elderly trapped.', 'assigned_unit' => 'SDRF Boat #03', 'eta_minutes' => 8, 'created_at' => date('Y-m-d H:i:s', time() - 840)],
        ['id' => 'SOS-003', 'victim_name' => 'Rohan Gupta', 'phone' => '+91 9876511003', 'latitude' => 28.6139, 'longitude' => 77.2090, 'emergency_type' => 'Medical', 'severity' => 'Critical', 'status' => 'Pending', 'people_count' => '1-3', 'quick_needs' => 'Ambulance', 'message' => 'Cardiac emergency, unresponsive.', 'assigned_unit' => '', 'eta_minutes' => 0, 'created_at' => date('Y-m-d H:i:s', time() - 300)],
        ['id' => 'SOS-004', 'victim_name' => 'Pooja Singh', 'phone' => '+91 9876511004', 'latitude' => 28.4089, 'longitude' => 77.3178, 'emergency_type' => 'Structure Collapse', 'severity' => 'Critical', 'status' => 'Pending', 'people_count' => '1-3', 'quick_needs' => 'Heavy Machinery, Trapped, Medical Aid', 'message' => 'Roof caved in due to heavy rain.', 'assigned_unit' => '', 'eta_minutes' => 0, 'created_at' => date('Y-m-d H:i:s', time() - 1500)],
        ['id' => 'SOS-005', 'victim_name' => 'Vikram Malhotra', 'phone' => '+91 9876511005', 'latitude' => 28.5041, 'longitude' => 77.0902, 'emergency_type' => 'Accident', 'severity' => 'Medium', 'status' => 'Resolved', 'people_count' => '1', 'quick_needs' => 'Police, Ambulance', 'message' => 'Collision cleared safely.', 'assigned_unit' => 'ALS Ambulance #04', 'eta_minutes' => 0, 'created_at' => date('Y-m-d H:i:s', time() - 2700)]
    ];
}
