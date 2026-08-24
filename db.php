<?php
// config/db.php - Standalone File-Based SQLite Database Connection & Initialization

$db_dir = __DIR__ . DIRECTORY_SEPARATOR . 'database';
if (!file_exists($db_dir)) {
    mkdir($db_dir, 0777, true);
}

$db_file = $db_dir . DIRECTORY_SEPARATOR . 'app.sqlite';

try {
    $pdo = new PDO("sqlite:" . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("PRAGMA foreign_keys = ON;");
} catch (PDOException $e) {
    die("Database Connection Error: " . htmlspecialchars($e->getMessage()));
}

// Auto-initialize schema & tables if not present
function initializeDatabase(PDO $pdo) {
    // 1. Roles table
    $pdo->exec("CREATE TABLE IF NOT EXISTS roles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        slug TEXT NOT NULL UNIQUE,
        description TEXT,
        permissions TEXT NOT NULL, -- JSON array of permissions
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    // 2. Users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        role_id INTEGER NOT NULL,
        status TEXT NOT NULL DEFAULT 'active', -- 'active', 'inactive', 'suspended'
        phone TEXT,
        avatar TEXT,
        custom_permissions TEXT, -- JSON array of user-specific permission overrides
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
    );");

    // Ensure custom_permissions column exists
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN custom_permissions TEXT;");
    } catch (Exception $e) {}

    // 3. Activity Logs table
    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        user_name TEXT,
        action TEXT NOT NULL,
        details TEXT,
        ip_address TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    // 4. Disasters table
    $pdo->exec("CREATE TABLE IF NOT EXISTS disasters (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        type TEXT NOT NULL,
        location TEXT NOT NULL,
        severity TEXT NOT NULL DEFAULT 'Critical',
        status TEXT NOT NULL DEFAULT 'Active',
        casualties INTEGER DEFAULT 0,
        displaced_people INTEGER DEFAULT 0,
        description TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    // 5. Volunteer Tasks table
    $pdo->exec("CREATE TABLE IF NOT EXISTS volunteer_tasks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        disaster_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        category TEXT NOT NULL,
        location TEXT NOT NULL,
        required_volunteers INTEGER DEFAULT 5,
        assigned_volunteers_count INTEGER DEFAULT 0,
        status TEXT NOT NULL DEFAULT 'Open',
        description TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (disaster_id) REFERENCES disasters(id) ON DELETE CASCADE
    );");

    // 6. Task Assignments table
    $pdo->exec("CREATE TABLE IF NOT EXISTS task_assignments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        task_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        status TEXT NOT NULL DEFAULT 'Accepted',
        notes TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (task_id) REFERENCES volunteer_tasks(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );");

    // 7. Police Deployments table
    $pdo->exec("CREATE TABLE IF NOT EXISTS police_deployments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        disaster_id INTEGER NOT NULL,
        zone_name TEXT NOT NULL,
        unit_callsign TEXT NOT NULL,
        officers_count INTEGER DEFAULT 4,
        mission_type TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'Active',
        contact_radio TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (disaster_id) REFERENCES disasters(id) ON DELETE CASCADE
    );");

    // 8. Missing Persons Registry table
    $pdo->exec("CREATE TABLE IF NOT EXISTS missing_persons (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        disaster_id INTEGER NOT NULL,
        full_name TEXT NOT NULL,
        age INTEGER,
        gender TEXT,
        last_seen_location TEXT NOT NULL,
        reported_by TEXT NOT NULL,
        contact_phone TEXT NOT NULL,
        photo TEXT,
        status TEXT NOT NULL DEFAULT 'Missing',
        notes TEXT,
        reported_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (disaster_id) REFERENCES disasters(id) ON DELETE CASCADE
    );");

    // Helper function to automatically determine system triage (medical needs & dispatch agency)
    function determineSystemTriage($emergencyType, $priority = 'Critical', $personsCount = '1 - 4') {
        return match($emergencyType) {
            'Flood' => [
                'agency' => 'NDRF Tactical Boat Squad',
                'needs' => 'Inflatable rescue boats, life jackets, dry ration packs & potable water'
            ],
            'Fire' => [
                'agency' => 'Fire & Hazmat Rescue Unit',
                'needs' => 'Burn dressing kits, self-contained breathing apparatus, smoke ventilation'
            ],
            'Earthquake' => [
                'agency' => 'NDRF Heavy Search & Rescue (USAR)',
                'needs' => 'Hydraulic rubble cutters, acoustic victim locators, trauma stretchers'
            ],
            'Building Collapse' => [
                'agency' => 'Fire Extrication & NDRF Heavy Squad',
                'needs' => 'Hydraulic spreaders, spine immobilization boards, ALS paramedic unit'
            ],
            'Medical Trauma' => [
                'agency' => 'Advanced Life Support (ALS) Ambulance / EMS',
                'needs' => 'High-flow oxygen cylinders, emergency defibrillator, hemorrhage trauma kit'
            ],
            'Cyclone / Storm' => [
                'agency' => 'NDRF & Civil Defence Rapid Response',
                'needs' => 'Debris clearance equipment, portable generators, emergency shelter tarpaulins'
            ],
            default => [
                'agency' => 'Regional Police & Quick Reaction Team',
                'needs' => 'Standard First-Aid Kit & Tactical Perimeter Escort'
            ]
        };
    }

    // 9. Emergency SOS Distress Alerts table (Streamlined GPS Triage Schema)
    $pdo->exec("CREATE TABLE IF NOT EXISTS emergency_sos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sender_name TEXT NOT NULL,
        sender_phone TEXT NOT NULL,
        gps_lat REAL NOT NULL,
        gps_lng REAL NOT NULL,
        blood_type TEXT DEFAULT 'Unknown',
        age INTEGER,
        persons_count TEXT DEFAULT '1 - 4',
        priority TEXT DEFAULT 'Critical',
        emergency_type TEXT NOT NULL,
        medical_needs TEXT,
        dispatch_agency TEXT,
        message TEXT,
        status TEXT NOT NULL DEFAULT 'Pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    // Dynamic Column Migration if existing table lacked columns or had legacy columns
    $sosCols = $pdo->query("PRAGMA table_info(emergency_sos)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (in_array('disaster_id', $sosCols) || in_array('location', $sosCols)) {
        // Drop legacy table structure and recreate with clean GPS-only schema
        $pdo->exec("DROP TABLE IF EXISTS emergency_sos;");
        $pdo->exec("CREATE TABLE emergency_sos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sender_name TEXT NOT NULL,
            sender_phone TEXT NOT NULL,
            gps_lat REAL NOT NULL,
            gps_lng REAL NOT NULL,
            blood_type TEXT DEFAULT 'Unknown',
            age INTEGER,
            persons_count TEXT DEFAULT '1 - 4',
            priority TEXT DEFAULT 'Critical',
            emergency_type TEXT NOT NULL,
            medical_needs TEXT,
            dispatch_agency TEXT,
            message TEXT,
            status TEXT NOT NULL DEFAULT 'Pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );");
    }

    // 10. Relief Supplies Distributed table
    $pdo->exec("CREATE TABLE IF NOT EXISTS relief_supplies (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        disaster_id INTEGER NOT NULL,
        item_name TEXT NOT NULL,
        quantity INTEGER NOT NULL,
        unit TEXT NOT NULL DEFAULT 'kits',
        distributed_by_user_id INTEGER NOT NULL,
        location TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (disaster_id) REFERENCES disasters(id) ON DELETE CASCADE,
        FOREIGN KEY (distributed_by_user_id) REFERENCES users(id) ON DELETE CASCADE
    );");

    // 11. Volunteers & NGO Personnel Roster table
    $pdo->exec("CREATE TABLE IF NOT EXISTS volunteers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        full_name TEXT NOT NULL,
        phone TEXT NOT NULL,
        email TEXT,
        skills TEXT,
        qualifications TEXT,
        team_name TEXT DEFAULT 'Independent Volunteer',
        current_location TEXT NOT NULL,
        availability_status TEXT DEFAULT 'Available / Standby',
        assigned_task_id INTEGER,
        blood_type TEXT DEFAULT 'Unknown',
        application_status TEXT DEFAULT 'Approved',
        experience_years INTEGER DEFAULT 1,
        avatar TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (assigned_task_id) REFERENCES volunteer_tasks(id) ON DELETE SET NULL
    );");

    // 12. Department Agency Stations / Centers table
    $pdo->exec("CREATE TABLE IF NOT EXISTS agency_stations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        agency_type TEXT NOT NULL, -- 'Police', 'Fire', 'Medical'
        station_name TEXT NOT NULL,
        zone_name TEXT NOT NULL,
        commander_name TEXT NOT NULL,
        contact_phone TEXT NOT NULL,
        radio_channel TEXT NOT NULL,
        gps_lat REAL NOT NULL,
        gps_lng REAL NOT NULL,
        vehicles_count INTEGER DEFAULT 5,
        personnel_count INTEGER DEFAULT 25,
        address TEXT NOT NULL,
        status TEXT DEFAULT 'Operational', -- 'Operational', 'High Alert', 'Overwhelmed'
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    // 13. Department Agency Teams & Squads table
    $pdo->exec("CREATE TABLE IF NOT EXISTS agency_teams (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        agency_type TEXT NOT NULL, -- 'Police', 'Fire', 'Medical'
        station_id INTEGER,
        callsign TEXT NOT NULL,
        team_lead TEXT NOT NULL,
        members_count INTEGER DEFAULT 6,
        vehicle_equipment TEXT NOT NULL,
        status TEXT DEFAULT 'Available', -- 'Available', 'Dispatched', 'On-Scene', 'Standby', 'Maintenance'
        current_task TEXT,
        contact_radio TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (station_id) REFERENCES agency_stations(id) ON DELETE SET NULL
    );");

    // 14. Department Agency Tasks & Incident Missions table
    $pdo->exec("CREATE TABLE IF NOT EXISTS agency_tasks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        agency_type TEXT NOT NULL, -- 'Police', 'Fire', 'Medical'
        title TEXT NOT NULL,
        priority TEXT DEFAULT 'Critical', -- 'Critical', 'High', 'Medium'
        location TEXT NOT NULL,
        assigned_team TEXT,
        status TEXT DEFAULT 'Pending', -- 'Pending', 'In Progress', 'Completed'
        description TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    // 15. Department Agency Resources & Capacity Inventory table
    $pdo->exec("CREATE TABLE IF NOT EXISTS agency_resources (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        agency_type TEXT NOT NULL, -- 'Police', 'Fire', 'Medical'
        station_id INTEGER,
        item_name TEXT NOT NULL,
        category TEXT NOT NULL,
        total_quantity INTEGER NOT NULL,
        available_quantity INTEGER NOT NULL,
        allocated_quantity INTEGER DEFAULT 0,
        unit TEXT NOT NULL DEFAULT 'units',
        status TEXT DEFAULT 'In Stock',
        FOREIGN KEY (station_id) REFERENCES agency_stations(id) ON DELETE SET NULL
    );");

    // 16. Master Disaster Resources Catalog table
    $pdo->exec("CREATE TABLE IF NOT EXISTS master_resources (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        resource_code TEXT UNIQUE NOT NULL,
        name TEXT NOT NULL,
        category TEXT NOT NULL, -- 'Food & Water', 'Medical Supplies', 'Vehicles & Mobility', 'Power & Energy', 'Shelter & Bedding', 'Tactical & Rescue Gear'
        total_stock INTEGER NOT NULL,
        available_stock INTEGER NOT NULL,
        distributed_stock INTEGER DEFAULT 0,
        unit TEXT NOT NULL DEFAULT 'units',
        primary_warehouse TEXT NOT NULL,
        status TEXT DEFAULT 'In Stock', -- 'In Stock', 'Low Stock', 'Critical Depleted'
        icon TEXT DEFAULT 'fa-box',
        color TEXT DEFAULT 'indigo',
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    // 17. Resource Distribution Tracking Ledger table
    $pdo->exec("CREATE TABLE IF NOT EXISTS resource_distributions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        resource_id INTEGER NOT NULL,
        destination_type TEXT NOT NULL, -- 'Relief Camp', 'Hospital', 'Police Cordon', 'Fire Unit', 'Field Clinic', 'Evacuation Zone'
        destination_name TEXT NOT NULL,
        location_address TEXT NOT NULL,
        gps_lat REAL NOT NULL,
        gps_lng REAL NOT NULL,
        quantity_distributed INTEGER NOT NULL,
        unit TEXT NOT NULL,
        dispatched_by TEXT NOT NULL DEFAULT 'Superadmin Tactical Command',
        contact_officer TEXT NOT NULL,
        distribution_status TEXT DEFAULT 'Delivered / On-Site', -- 'Delivered / On-Site', 'In-Transit / En-Route', 'Allocated'
        distributed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        notes TEXT,
        FOREIGN KEY (resource_id) REFERENCES master_resources(id) ON DELETE CASCADE
    );");

    // 18. Volunteer & Relief Broadcast Announcements table
    $pdo->exec("CREATE TABLE IF NOT EXISTS volunteer_broadcasts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sender_name TEXT NOT NULL,
        target_type TEXT NOT NULL, -- 'ALL', 'TEAM', 'VOLUNTEER'
        target_team TEXT,
        target_volunteer_id INTEGER,
        priority TEXT DEFAULT 'High', -- 'Critical Emergency', 'High Priority', 'General Advisory'
        title TEXT NOT NULL,
        message TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    // Self-healing column checks for volunteers table
    try {
        $pdo->exec("ALTER TABLE volunteers ADD COLUMN organization TEXT DEFAULT 'DisasterSafe Relief Volunteers'");
    } catch (Exception $e) {}

    // Define Standardized 7-Tier Role Permission Sets
    $rolesToEnsure = [
        [
            'name' => 'Superadmin (Supreme Controller)',
            'slug' => 'superadmin',
            'description' => 'Unrestricted full operational & administrative control across all agencies, universal SOS, resources, and database.',
            'permissions' => json_encode([
                'access_sos_database', 'access_disasters', 'access_ndrf', 'access_police', 'access_fire', 
                'access_medical', 'access_volunteer', 'access_missing_persons', 'manage_users', 
                'manage_roles', 'view_dashboard', 'view_analytics', 'manage_settings', 'view_activity_logs', 
                'export_data', 'edit_profile'
            ])
        ],
        [
            'name' => 'NDRF Force Commander',
            'slug' => 'ndrf',
            'description' => 'National Disaster Response Force tactical operations, heavy extraction, flood evacuation, and multi-agency mission control.',
            'permissions' => json_encode([
                'access_sos_database', 'access_disasters', 'access_ndrf', 'access_police', 'access_fire', 
                'access_medical', 'view_dashboard', 'view_analytics', 'edit_profile'
            ])
        ],
        [
            'name' => 'Police Commander',
            'slug' => 'police',
            'description' => 'Law enforcement, perimeter cordons, road/highway blockages, evacuation convoys, and missing person search.',
            'permissions' => json_encode([
                'access_sos_database', 'access_police', 'access_missing_persons', 'access_disasters', 'view_dashboard', 'edit_profile'
            ])
        ],
        [
            'name' => 'Fire & Rescue Chief',
            'slug' => 'fire',
            'description' => 'Firefighting squads, smoke ventilation, hazardous material mitigation, structure collapse cutting, and high-angle extraction.',
            'permissions' => json_encode([
                'access_sos_database', 'access_fire', 'access_disasters', 'view_dashboard', 'edit_profile'
            ])
        ],
        [
            'name' => 'Medical / EMS Director',
            'slug' => 'medical',
            'description' => 'Hospital ICU and trauma bed availability, paramedic dispatch, ambulance triage, oxygen logistics, and field clinics.',
            'permissions' => json_encode([
                'access_sos_database', 'access_medical', 'access_disasters', 'view_dashboard', 'edit_profile'
            ])
        ],
        [
            'name' => 'Disaster Volunteer Corps',
            'slug' => 'volunteer',
            'description' => 'On-ground disaster relief, search & rescue aid, first aid triage, shelter bedding, and food/water supply distribution.',
            'permissions' => json_encode([
                'access_volunteer', 'access_disasters', 'view_dashboard', 'edit_profile'
            ])
        ],
        [
            'name' => 'Public Citizen',
            'slug' => 'user',
            'description' => 'General civilian access: one-touch GPS SOS beacon, verified safe shelter locators, and personal profile (No internal SOS database access).',
            'permissions' => json_encode([
                'view_dashboard', 'edit_profile'
            ])
        ]
    ];

    foreach ($rolesToEnsure as $role) {
        $exists = $pdo->prepare("SELECT id FROM roles WHERE slug = ?");
        $exists->execute([$role['slug']]);
        $row = $exists->fetch();
        if (!$row) {
            $pdo->prepare("INSERT INTO roles (name, slug, description, permissions) VALUES (?, ?, ?, ?)")->execute([$role['name'], $role['slug'], $role['description'], $role['permissions']]);
        } else {
            $pdo->prepare("UPDATE roles SET name = ?, description = ?, permissions = ? WHERE slug = ?")->execute([$role['name'], $role['description'], $role['permissions'], $role['slug']]);
        }
    }

    // Clean up old obsolete users & reassign foreign keys before deleting obsolete roles
    $citizenRoleId = $pdo->query("SELECT id FROM roles WHERE slug = 'user'")->fetchColumn();
    $pdo->exec("DELETE FROM users WHERE email IN ('alex.admin@system.local', 'sarah.manager@system.local', 'david.user@system.local')");
    if ($citizenRoleId) {
        $pdo->exec("UPDATE users SET role_id = {$citizenRoleId} WHERE role_id IN (SELECT id FROM roles WHERE slug IN ('admin', 'manager'))");
    }
    $pdo->exec("DELETE FROM roles WHERE slug IN ('admin', 'manager')");

    // Fetch Role IDs
    $superadminRoleId = $pdo->query("SELECT id FROM roles WHERE slug = 'superadmin'")->fetchColumn();
    $ndrfRoleId = $pdo->query("SELECT id FROM roles WHERE slug = 'ndrf'")->fetchColumn();
    $policeRoleId = $pdo->query("SELECT id FROM roles WHERE slug = 'police'")->fetchColumn();
    $fireRoleId = $pdo->query("SELECT id FROM roles WHERE slug = 'fire'")->fetchColumn();
    $medicalRoleId = $pdo->query("SELECT id FROM roles WHERE slug = 'medical'")->fetchColumn();
    $volunteerRoleId = $pdo->query("SELECT id FROM roles WHERE slug = 'volunteer'")->fetchColumn();

    // 7 Specialized Demo Accounts
    $specialUsers = [
        [
            'name' => 'Super Administrator',
            'email' => 'superadmin@system.local',
            'password' => password_hash('admin123', PASSWORD_BCRYPT),
            'role_id' => $superadminRoleId,
            'status' => 'active',
            'phone' => '+1 (555) 019-2834',
            'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80'
        ],
        [
            'name' => 'Brig. Rajiv Sharma (NDRF Commander)',
            'email' => 'ndrf.commander@disaster.local',
            'password' => password_hash('admin123', PASSWORD_BCRYPT),
            'role_id' => $ndrfRoleId,
            'status' => 'active',
            'phone' => '+91 (011) 2436-3260',
            'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&auto=format&fit=crop&q=80'
        ],
        [
            'name' => 'Capt. Marcus Vance (Police Command)',
            'email' => 'police.command@disaster.local',
            'password' => password_hash('admin123', PASSWORD_BCRYPT),
            'role_id' => $policeRoleId,
            'status' => 'active',
            'phone' => '+1 (555) 911-0422',
            'avatar' => 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=150&auto=format&fit=crop&q=80'
        ],
        [
            'name' => 'Chief Thomas Sterling (Fire & Rescue)',
            'email' => 'fire.chief@disaster.local',
            'password' => password_hash('admin123', PASSWORD_BCRYPT),
            'role_id' => $fireRoleId,
            'status' => 'active',
            'phone' => '+1 (555) 101-4490',
            'avatar' => 'https://images.unsplash.com/photo-1522075469751-3a6694fb2f61?w=150&auto=format&fit=crop&q=80'
        ],
        [
            'name' => 'Dr. Ananya Roy (EMS & Medical Chief)',
            'email' => 'medical.ems@disaster.local',
            'password' => password_hash('admin123', PASSWORD_BCRYPT),
            'role_id' => $medicalRoleId,
            'status' => 'active',
            'phone' => '+1 (555) 108-7721',
            'avatar' => 'https://images.unsplash.com/photo-1594824813620-4a0b2241cfd1?w=150&auto=format&fit=crop&q=80'
        ],
        [
            'name' => 'Elena Rostova (Lead Volunteer)',
            'email' => 'volunteer@disaster.local',
            'password' => password_hash('admin123', PASSWORD_BCRYPT),
            'role_id' => $volunteerRoleId,
            'status' => 'active',
            'phone' => '+1 (555) 830-4921',
            'avatar' => 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=150&auto=format&fit=crop&q=80'
        ],
        [
            'name' => 'Aarav Patel (Public Citizen)',
            'email' => 'citizen@example.com',
            'password' => password_hash('admin123', PASSWORD_BCRYPT),
            'role_id' => $citizenRoleId,
            'status' => 'active',
            'phone' => '+91 98765 43210',
            'avatar' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&auto=format&fit=crop&q=80'
        ]
    ];

    // Clean up old obsolete users
    $pdo->exec("DELETE FROM users WHERE email IN ('alex.admin@system.local', 'sarah.manager@system.local', 'david.user@system.local')");

    foreach ($specialUsers as $sUser) {
        $uCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $uCheck->execute([$sUser['email']]);
        $existing = $uCheck->fetch();
        if (!$existing) {
            $pdo->prepare("INSERT INTO users (name, email, password, role_id, status, phone, avatar) VALUES (?, ?, ?, ?, ?, ?, ?)")->execute([
                $sUser['name'], $sUser['email'], $sUser['password'], $sUser['role_id'], $sUser['status'], $sUser['phone'], $sUser['avatar']
            ]);
        } else {
            $pdo->prepare("UPDATE users SET name = ?, role_id = ?, password = ?, phone = ?, avatar = ? WHERE email = ?")->execute([
                $sUser['name'], $sUser['role_id'], $sUser['password'], $sUser['phone'], $sUser['avatar'], $sUser['email']
            ]);
        }
    }

    // Ensure Delhi-NCR Localized Demo Dataset is populated
    $sosCount = (int) $pdo->query("SELECT COUNT(*) FROM emergency_sos")->fetchColumn();
    $disasterCount = (int) $pdo->query("SELECT COUNT(*) FROM disasters")->fetchColumn();
    if ($disasterCount == 0 || $sosCount == 0 || $pdo->query("SELECT COUNT(*) FROM disasters WHERE location LIKE '%Yamuna%' OR location LIKE '%Delhi%' OR location LIKE '%Noida%'")->fetchColumn() == 0) {
        
        // Clean out older child rows first to satisfy foreign keys
        $pdo->exec("DELETE FROM task_assignments");
        $pdo->exec("DELETE FROM emergency_sos");
        $pdo->exec("DELETE FROM police_deployments");
        $pdo->exec("DELETE FROM missing_persons");
        $pdo->exec("DELETE FROM volunteer_tasks");
        $pdo->exec("DELETE FROM relief_supplies");
        $pdo->exec("DELETE FROM disasters");

        // 1. DELHI-NCR ACTIVE DISASTERS
        $sampleDisasters = [
            [
                'title' => 'Yamuna River Surge & Lowland Inundation',
                'type' => 'Flood',
                'location' => 'Yamuna Floodplains, Mayur Vihar & Kashmere Gate, East Delhi',
                'severity' => 'Critical',
                'status' => 'Active',
                'casualties' => 4,
                'displaced_people' => 4800,
                'description' => 'Heavy rainfall in upper catchment caused Yamuna water level to cross the 208.6m danger mark. Widespread waterlogging across Mayur Vihar, Usmanpur, and Ring Road lowlands. NDRF boat teams deployed for mass evacuation.'
            ],
            [
                'title' => 'Sahibabad Industrial Area Chemical Fire & Hazmat Alert',
                'type' => 'Fire',
                'location' => 'Site 4 Industrial Area, Mohan Nagar, Ghaziabad',
                'severity' => 'Critical',
                'status' => 'Active',
                'casualties' => 2,
                'displaced_people' => 1200,
                'description' => 'Major blaze erupting in a chemical solvent warehouse. Dense toxic smoke billowing over Mohan Nagar. 12 Fire tenders on scene; multi-agency exclusion zone established.'
            ],
            [
                'title' => 'Gurugram Submerged Underpasses & Flash Waterlogging',
                'type' => 'Urban Flood',
                'location' => 'Hero Honda Chowk & Golf Course Road Underpasses, Gurugram',
                'severity' => 'High',
                'status' => 'Active',
                'casualties' => 0,
                'displaced_people' => 850,
                'description' => 'Severe 120mm downpour overwhelmed stormwater drains. Underpasses flooded up to 6 feet, causing massive vehicle gridlocks on NH-48. Water pumps and towing squads active.'
            ],
            [
                'title' => 'Noida Sector 62 Structural Wall Collapse',
                'type' => 'Structural Collapse',
                'location' => 'Block B Institutional Area, Sector 62, Noida',
                'severity' => 'Medium',
                'status' => 'Under Control',
                'casualties' => 1,
                'displaced_people' => 220,
                'description' => 'Boundary wall of commercial complex gave way during heavy downpour. Police perimeter established; search & rescue confirmed no further victims under rubble.'
            ]
        ];

        $disasterStmt = $pdo->prepare("INSERT INTO disasters (title, type, location, severity, status, casualties, displaced_people, description) VALUES (:title, :type, :location, :severity, :status, :casualties, :displaced_people, :description)");
        foreach ($sampleDisasters as $d) {
            $disasterStmt->execute($d);
        }

        // Fetch freshly generated disaster IDs
        $floodId = (int) $pdo->query("SELECT id FROM disasters WHERE type = 'Flood' LIMIT 1")->fetchColumn();
        $fireId = (int) $pdo->query("SELECT id FROM disasters WHERE type = 'Fire' LIMIT 1")->fetchColumn();
        $urbanId = (int) $pdo->query("SELECT id FROM disasters WHERE type = 'Urban Flood' LIMIT 1")->fetchColumn() ?: $floodId;
        $collapseId = (int) $pdo->query("SELECT id FROM disasters WHERE type = 'Structural Collapse' LIMIT 1")->fetchColumn() ?: $floodId;

        // 2. DELHI-NCR VOLUNTEER MISSIONS
        $tasks = [
            [
                'disaster_id' => $floodId,
                'title' => 'Distribute Clean Drinking Water & Packaged Rations',
                'category' => 'Food & Water',
                'location' => 'Mayur Vihar Relief Camp Tent City, East Delhi',
                'required_volunteers' => 10,
                'assigned_volunteers_count' => 6,
                'status' => 'In Progress',
                'description' => 'Distribute potable water cartons, ORS packets, baby formula, and dry khichdi ration kits to displaced flood victims.'
            ],
            [
                'disaster_id' => $floodId,
                'title' => 'First Aid & Medical Triage Assistance',
                'category' => 'Medical Aid',
                'location' => 'Thyagaraj Stadium Evacuation Camp, New Delhi',
                'required_volunteers' => 8,
                'assigned_volunteers_count' => 5,
                'status' => 'Open',
                'description' => 'Assist AIIMS and Safdarjung paramedics in treating minor lacerations, distributing fever medication, and hygiene kits.'
            ],
            [
                'disaster_id' => $floodId,
                'title' => 'Inflatable Rescue Boat Escort & Evacuation',
                'category' => 'Search & Rescue',
                'location' => 'Yamuna Bank & Usmanpur Lowlands',
                'required_volunteers' => 6,
                'assigned_volunteers_count' => 4,
                'status' => 'In Progress',
                'description' => 'Guiding NDRF rubber boats, securing life vests on trapped families, and escorting senior citizens to dry landing points.'
            ],
            [
                'disaster_id' => $fireId,
                'title' => 'Respiratory Mask & Burn Dressing Distribution',
                'category' => 'Medical Aid',
                'location' => 'Mohan Nagar Community Center, Ghaziabad',
                'required_volunteers' => 6,
                'assigned_volunteers_count' => 3,
                'status' => 'Open',
                'description' => 'Hand out N95 breathing masks and burn ointment kits to factory workers and nearby residents affected by smoke.'
            ]
        ];

        $taskStmt = $pdo->prepare("INSERT INTO volunteer_tasks (disaster_id, title, category, location, required_volunteers, assigned_volunteers_count, status, description) VALUES (:disaster_id, :title, :category, :location, :required_volunteers, :assigned_volunteers_count, :status, :description)");
        foreach ($tasks as $t) {
            $taskStmt->execute($t);
        }

        // 3. DELHI-NCR POLICE TACTICAL DEPLOYMENTS
        $deployments = [
            [
                'disaster_id' => $floodId,
                'zone_name' => 'ITO Bridge & Geeta Colony Flyover Access',
                'unit_callsign' => 'Delta-NCR-1 (Delhi Police)',
                'officers_count' => 18,
                'mission_type' => 'Traffic Diversion & Flood Cordon',
                'status' => 'Active',
                'contact_radio' => 'Freq 154.80 MHz (VHF Ch-4)'
            ],
            [
                'disaster_id' => $floodId,
                'zone_name' => 'Kashmere Gate ISBT & Ring Road Corridor',
                'unit_callsign' => 'Echo-Patrol-3 (Delhi Police)',
                'officers_count' => 12,
                'mission_type' => 'Evacuation Convoy Escort',
                'status' => 'Active',
                'contact_radio' => 'Freq 154.85 MHz (VHF Ch-2)'
            ],
            [
                'disaster_id' => $urbanId,
                'zone_name' => 'Hero Honda Chowk & Rajiv Chowk Underpass',
                'unit_callsign' => 'Alpha-Gurgaon-2 (Haryana Police)',
                'officers_count' => 14,
                'mission_type' => 'Highway Drainage Gridlock Control',
                'status' => 'Active',
                'contact_radio' => 'Freq 155.10 MHz (VHF Ch-7)'
            ],
            [
                'disaster_id' => $fireId,
                'zone_name' => 'Mohan Nagar Metro Intersection & Site 4',
                'unit_callsign' => 'Charlie-Ghaziabad-1 (UP Police)',
                'officers_count' => 15,
                'mission_type' => 'Chemical Hazmat Exclusion Zone',
                'status' => 'Active',
                'contact_radio' => 'Freq 154.70 MHz (VHF Ch-1)'
            ]
        ];

        $depStmt = $pdo->prepare("INSERT INTO police_deployments (disaster_id, zone_name, unit_callsign, officers_count, mission_type, status, contact_radio) VALUES (:disaster_id, :zone_name, :unit_callsign, :officers_count, :mission_type, :status, :contact_radio)");
        foreach ($deployments as $dep) {
            $depStmt->execute($dep);
        }

        // 4. DELHI-NCR MISSING PERSONS REGISTRY (INDIAN NAMES)
        $missing = [
            [
                'disaster_id' => $floodId,
                'full_name' => 'Ramesh Chandra Sharma',
                'age' => 68,
                'gender' => 'Male',
                'last_seen_location' => 'Mayur Vihar Phase 1 Metro Station during evacuation',
                'reported_by' => 'Alok Sharma (Son)',
                'contact_phone' => '+91 98112 00123',
                'status' => 'Missing',
                'photo' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&auto=format&fit=crop&q=80',
                'notes' => 'Wearing white kurta pajama and brown frame glasses. Diabetic and needs daily insulin injections.'
            ],
            [
                'disaster_id' => $floodId,
                'full_name' => 'Aarav & Ananya Joshi',
                'age' => 8,
                'gender' => 'Child',
                'last_seen_location' => 'Kashmiri Gate Ring Road Temporary Camp',
                'reported_by' => 'Meenakshi Joshi (Mother)',
                'contact_phone' => '+91 98711 33445',
                'status' => 'Rescued',
                'photo' => 'https://images.unsplash.com/photo-1543610892-0b1f7e6d8ac1?w=150&auto=format&fit=crop&q=80',
                'notes' => 'Safely located and rescued by NDRF boat squad. Reunited with parents at Thyagaraj Stadium Camp.'
            ],
            [
                'disaster_id' => $fireId,
                'full_name' => 'Deepak Kumar Yadav',
                'age' => 32,
                'gender' => 'Male',
                'last_seen_location' => 'Site 4 Industrial Warehouse, Sahibabad',
                'reported_by' => 'Suresh Yadav (Brother)',
                'contact_phone' => '+91 99532 99881',
                'status' => 'Missing',
                'photo' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&auto=format&fit=crop&q=80',
                'notes' => 'Factory shift supervisor wearing navy blue safety uniform and steel-toe boots.'
            ],
            [
                'disaster_id' => $urbanId,
                'full_name' => 'Sunita Devi',
                'age' => 54,
                'gender' => 'Female',
                'last_seen_location' => 'DLF Phase 3 Residential Block, Gurugram',
                'reported_by' => 'Pooja Verma (Daughter)',
                'contact_phone' => '+91 98108 55667',
                'status' => 'In Medical Care',
                'photo' => 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=150&auto=format&fit=crop&q=80',
                'notes' => 'Located by Ambulance EMS Unit with mild hypothermia. Currently receiving care at Medanta Hospital.'
            ]
        ];

        $missStmt = $pdo->prepare("INSERT INTO missing_persons (disaster_id, full_name, age, gender, last_seen_location, reported_by, contact_phone, status, photo, notes) VALUES (:disaster_id, :full_name, :age, :gender, :last_seen_location, :reported_by, :contact_phone, :status, :photo, :notes)");
        foreach ($missing as $m) {
            $missStmt->execute($m);
        }

        // 5. DELHI-NCR EMERGENCY SOS DISTRESS CALLS (INDIAN CITIZENS - GPS FOCUSED & SYSTEM TRIAGED)
        $sosAlerts = [
            [
                'sender_name' => 'Rajesh & Sunita Sharma',
                'sender_phone' => '+91 98112 34567',
                'gps_lat' => 28.6050,
                'gps_lng' => 77.2950,
                'blood_type' => 'O+',
                'age' => 44,
                'persons_count' => '1 - 4',
                'priority' => 'Critical',
                'emergency_type' => 'Flood',
                'medical_needs' => 'Inflatable rescue boats, life jackets, dry ration packs & potable water',
                'dispatch_agency' => 'NDRF Tactical Boat Squad',
                'message' => 'Ground floor submerged under 5.5ft water. Family of 4 trapped on terrace.',
                'status' => 'Pending'
            ],
            [
                'sender_name' => 'Dr. Vikramaditya Sen',
                'sender_phone' => '+91 98710 88234',
                'gps_lat' => 28.5850,
                'gps_lng' => 77.3820,
                'blood_type' => 'B+',
                'age' => 72,
                'persons_count' => '1 - 4',
                'priority' => 'Critical',
                'emergency_type' => 'Medical Trauma',
                'medical_needs' => 'High-flow oxygen cylinders, emergency defibrillator, hemorrhage trauma kit',
                'dispatch_agency' => 'Advanced Life Support (ALS) Ambulance / EMS',
                'message' => 'Severe respiratory failure, home oxygen concentrator failed due to power cut.',
                'status' => 'Police Dispatched'
            ],
            [
                'sender_name' => 'Amitabh Verma',
                'sender_phone' => '+91 99580 12890',
                'gps_lat' => 28.6750,
                'gps_lng' => 77.3650,
                'blood_type' => 'A+',
                'age' => 38,
                'persons_count' => '4 - 8',
                'priority' => 'Critical',
                'emergency_type' => 'Fire',
                'medical_needs' => 'Burn dressing kits, self-contained breathing apparatus, smoke ventilation',
                'dispatch_agency' => 'Fire & Hazmat Rescue Unit',
                'message' => 'Solvent chemical storage unit burning fiercely with toxic dense smoke.',
                'status' => 'Pending'
            ],
            [
                'sender_name' => 'Priya Malhotra',
                'sender_phone' => '+91 98100 45678',
                'gps_lat' => 28.4950,
                'gps_lng' => 77.0890,
                'blood_type' => 'AB+',
                'age' => 29,
                'persons_count' => '4 - 8',
                'priority' => 'High',
                'emergency_type' => 'Flood',
                'medical_needs' => 'Inflatable rescue boats, life jackets, dry ration packs & potable water',
                'dispatch_agency' => 'Gurugram Volunteer Water Rescue',
                'message' => 'School transport van stalled in 4ft deep underpass water with children inside.',
                'status' => 'Volunteer Responding'
            ],
            [
                'sender_name' => 'Mohammad Imran Khan',
                'sender_phone' => '+91 99114 78201',
                'gps_lat' => 28.6667,
                'gps_lng' => 77.2280,
                'blood_type' => 'O-',
                'age' => 52,
                'persons_count' => '1 - 4',
                'priority' => 'High',
                'emergency_type' => 'Building Collapse',
                'medical_needs' => 'Hydraulic spreaders, spine immobilization boards, ALS paramedic unit',
                'dispatch_agency' => 'Fire Extrication & NDRF Heavy Squad',
                'message' => 'Masonry perimeter wall collapsed during heavy rain. 2 people pinned beneath debris.',
                'status' => 'Pending'
            ],
            [
                'sender_name' => 'Kavita Sundaram',
                'sender_phone' => '+91 98991 67230',
                'gps_lat' => 28.4680,
                'gps_lng' => 77.4970,
                'blood_type' => 'B-',
                'age' => 21,
                'persons_count' => '12+',
                'priority' => 'Medium',
                'emergency_type' => 'Flood',
                'medical_needs' => 'Inflatable rescue boats, life jackets, dry ration packs & potable water',
                'dispatch_agency' => 'Disaster Volunteer Corps - Noida',
                'message' => 'Hostel basement submerged up to ceiling, power outage for 12 hours.',
                'status' => 'Volunteer Responding'
            ]
        ];

        $sosStmt = $pdo->prepare("INSERT INTO emergency_sos (sender_name, sender_phone, gps_lat, gps_lng, blood_type, age, persons_count, priority, emergency_type, medical_needs, dispatch_agency, message, status) VALUES (:sender_name, :sender_phone, :gps_lat, :gps_lng, :blood_type, :age, :persons_count, :priority, :emergency_type, :medical_needs, :dispatch_agency, :message, :status)");
        foreach ($sosAlerts as $sos) {
            $sosStmt->execute($sos);
        }

        // 6. DELHI-NCR RELIEF SUPPLIES LEDGER
        $supplies = [
            [
                'disaster_id' => $floodId,
                'item_name' => 'Packaged Drinking Water (20L Cans)',
                'quantity' => 850,
                'unit' => 'cans',
                'distributed_by_user_id' => 1,
                'location' => 'Mayur Vihar Phase 1 Relief Depot, East Delhi'
            ],
            [
                'disaster_id' => $floodId,
                'item_name' => 'Dry Ration Meal Kits (Rice, Dal, Biscuits)',
                'quantity' => 1400,
                'unit' => 'kits',
                'distributed_by_user_id' => 1,
                'location' => 'Thyagaraj Stadium Evacuation Camp, New Delhi'
            ],
            [
                'disaster_id' => $fireId,
                'item_name' => 'Burn Dressing Kits & N95 Respirators',
                'quantity' => 350,
                'unit' => 'bundles',
                'distributed_by_user_id' => 1,
                'location' => 'Mohan Nagar Community Hall, Ghaziabad'
            ],
            [
                'disaster_id' => $floodId,
                'item_name' => 'Woolen Blankets & Tarpaulin Sheets',
                'quantity' => 600,
                'unit' => 'pieces',
                'distributed_by_user_id' => 1,
                'location' => 'Noida Stadium Sector 21 Relief Camp'
            ]
        ];

        $supStmt = $pdo->prepare("INSERT INTO relief_supplies (disaster_id, item_name, quantity, unit, distributed_by_user_id, location) VALUES (:disaster_id, :item_name, :quantity, :unit, :distributed_by_user_id, :location)");
        foreach ($supplies as $sup) {
            $supStmt->execute($sup);
        }
    }

    // Auto-seed volunteers if table is empty
    $volCount = (int) $pdo->query("SELECT COUNT(*) FROM volunteers")->fetchColumn();
    if ($volCount === 0) {
        $volunteers = [
            [
                'user_id' => 3,
                'full_name' => 'Elena Rostova (Lead Coordinator)',
                'phone' => '+91 98101 22334',
                'email' => 'volunteer@disaster.local',
                'skills' => 'First Aid & CPR, Search & Rescue Diver, Rapid Triage',
                'qualifications' => 'Certified Disaster Management Specialist (NDMA / Red Cross Level 3)',
                'team_name' => 'Red Cross Delhi Crisis Chapter',
                'current_location' => 'Mayur Vihar Relief Tent City, East Delhi',
                'availability_status' => 'Available / Standby',
                'assigned_task_id' => null,
                'blood_type' => 'O+',
                'application_status' => 'Approved',
                'experience_years' => 5,
                'avatar' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=150&auto=format&fit=crop&q=80'
            ],
            [
                'user_id' => null,
                'full_name' => 'Karan Mehra',
                'phone' => '+91 98712 44556',
                'email' => 'karan.mehra@goonj.ngo',
                'skills' => 'Supply Chain Logistics, Heavy Truck Driver, Warehouse Ops',
                'qualifications' => 'Logistics & Disaster Relief Operations Diploma, Commercial Heavy Driving License',
                'team_name' => 'Goonj Disaster Relief Unit',
                'current_location' => 'Noida Sector 62 Relief Hub',
                'availability_status' => 'Available / Standby',
                'assigned_task_id' => null,
                'blood_type' => 'B+',
                'application_status' => 'Approved',
                'experience_years' => 3,
                'avatar' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&auto=format&fit=crop&q=80'
            ],
            [
                'user_id' => null,
                'full_name' => 'Rohit Verma',
                'phone' => '+91 99103 55667',
                'email' => 'rohit.v@civildefence.delhi.gov',
                'skills' => 'Ham Radio Operator, Flood Evacuation Escort, Crowd Management',
                'qualifications' => 'National Civil Defence Instructor Certificate, VHF Comms Class 1',
                'team_name' => 'Civil Defence Corps Delhi',
                'current_location' => 'ITO Bridge Command Cordon',
                'availability_status' => 'Available / Standby',
                'assigned_task_id' => null,
                'blood_type' => 'A+',
                'application_status' => 'Approved',
                'experience_years' => 4,
                'avatar' => 'https://images.unsplash.com/photo-1492562080023-ab3db95bfbce?w=150&auto=format&fit=crop&q=80'
            ],
            [
                'user_id' => null,
                'full_name' => 'Dr. Simran Kaur',
                'phone' => '+91 98118 77889',
                'email' => 'simran.kaur@khalsaaid.org',
                'skills' => 'Emergency Medical Responder (EMR), Trauma Care, Wound Dressing',
                'qualifications' => 'MBBS, BLS/ACLS Certified Emergency Physician',
                'team_name' => 'Khalsa Aid India Relief Unit',
                'current_location' => 'AIIMS Triage Hub & Field Clinic',
                'availability_status' => 'Available / Standby',
                'assigned_task_id' => null,
                'blood_type' => 'AB+',
                'application_status' => 'Approved',
                'experience_years' => 6,
                'avatar' => 'https://images.unsplash.com/photo-1594824813585-80252518e001?w=150&auto=format&fit=crop&q=80'
            ],
            [
                'user_id' => null,
                'full_name' => 'Vikas Pandey',
                'phone' => '+91 98990 11223',
                'email' => 'vikas.p@robinhoodarmy.com',
                'skills' => 'Food Ration Distribution, Evacuee Registration, Shelter Management',
                'qualifications' => 'Community Outreach Leader, First Responder Certified',
                'team_name' => 'Robin Hood Army Delhi-NCR',
                'current_location' => 'Thyagaraj Stadium Evacuee Camp',
                'availability_status' => 'Available / Standby',
                'assigned_task_id' => null,
                'blood_type' => 'O-',
                'application_status' => 'Approved',
                'experience_years' => 2,
                'avatar' => 'https://images.unsplash.com/photo-1522075469751-3a6694fb2f61?w=150&auto=format&fit=crop&q=80'
            ],
            [
                'user_id' => null,
                'full_name' => 'Pooja Aggarwal',
                'phone' => '+91 98733 99881',
                'email' => 'pooja.agg@gmail.com',
                'skills' => 'Nursing Assistant, Child Care, Hindi & English Translator',
                'qualifications' => 'B.Sc. Nursing 3rd Year, Basic First Aid & Child Psychology',
                'team_name' => 'Unassigned (Applicant)',
                'current_location' => 'Indirapuram, Ghaziabad',
                'availability_status' => 'Pending Verification',
                'assigned_task_id' => null,
                'blood_type' => 'B-',
                'application_status' => 'Pending Approval',
                'experience_years' => 1,
                'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80'
            ],
            [
                'user_id' => null,
                'full_name' => 'Aditya Singhania',
                'phone' => '+91 99991 44332',
                'email' => 'aditya.singhania@dronevision.in',
                'skills' => 'Drone Pilot & Aerial Survey, 4x4 Offroad Driver, Power Generator Tech',
                'qualifications' => 'DGCA Certified Remote Drone Pilot, Mechanical Engineer',
                'team_name' => 'Unassigned (Applicant)',
                'current_location' => 'Sushant Lok 1, Gurugram',
                'availability_status' => 'Pending Verification',
                'assigned_task_id' => null,
                'blood_type' => 'A+',
                'application_status' => 'Pending Approval',
                'experience_years' => 3,
                'avatar' => 'https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?w=150&auto=format&fit=crop&q=80'
            ]
        ];

        $volStmt = $pdo->prepare("INSERT INTO volunteers (user_id, full_name, phone, email, skills, qualifications, team_name, current_location, availability_status, assigned_task_id, blood_type, application_status, experience_years, avatar) VALUES (:user_id, :full_name, :phone, :email, :skills, :qualifications, :team_name, :current_location, :availability_status, :assigned_task_id, :blood_type, :application_status, :experience_years, :avatar)");
        foreach ($volunteers as $v) {
            $volStmt->execute($v);
        }
    }

    // Auto-seed emergency_sos if table is empty
    $sosCount = (int) $pdo->query("SELECT COUNT(*) FROM emergency_sos")->fetchColumn();
    if ($sosCount === 0) {
        $sosAlerts = [
            [
                'sender_name' => 'Rajesh & Sunita Sharma',
                'sender_phone' => '+91 98112 34567',
                'gps_lat' => 28.6050,
                'gps_lng' => 77.2950,
                'blood_type' => 'O+',
                'age' => 44,
                'persons_count' => '1 - 4',
                'priority' => 'Critical',
                'emergency_type' => 'Flood',
                'medical_needs' => 'Inflatable rescue boats, life jackets, dry ration packs & potable water',
                'dispatch_agency' => 'NDRF Tactical Boat Squad',
                'message' => 'Ground floor submerged under 5.5ft water. Family of 4 trapped on terrace.',
                'status' => 'Pending'
            ],
            [
                'sender_name' => 'Dr. Vikramaditya Sen',
                'sender_phone' => '+91 98710 88234',
                'gps_lat' => 28.5850,
                'gps_lng' => 77.3820,
                'blood_type' => 'B+',
                'age' => 72,
                'persons_count' => '1 - 4',
                'priority' => 'Critical',
                'emergency_type' => 'Medical Trauma',
                'medical_needs' => 'High-flow oxygen cylinders, emergency defibrillator, hemorrhage trauma kit',
                'dispatch_agency' => 'Advanced Life Support (ALS) Ambulance / EMS',
                'message' => 'Severe respiratory failure, home oxygen concentrator failed due to power cut.',
                'status' => 'Police Dispatched'
            ],
            [
                'sender_name' => 'Amitabh Verma',
                'sender_phone' => '+91 99580 12890',
                'gps_lat' => 28.6750,
                'gps_lng' => 77.3650,
                'blood_type' => 'A+',
                'age' => 38,
                'persons_count' => '4 - 8',
                'priority' => 'Critical',
                'emergency_type' => 'Fire',
                'medical_needs' => 'Burn dressing kits, self-contained breathing apparatus, smoke ventilation',
                'dispatch_agency' => 'Fire & Hazmat Rescue Unit',
                'message' => 'Solvent chemical storage unit burning fiercely with toxic dense smoke.',
                'status' => 'Pending'
            ],
            [
                'sender_name' => 'Priya Malhotra',
                'sender_phone' => '+91 98100 45678',
                'gps_lat' => 28.4950,
                'gps_lng' => 77.0890,
                'blood_type' => 'AB+',
                'age' => 29,
                'persons_count' => '4 - 8',
                'priority' => 'High',
                'emergency_type' => 'Flood',
                'medical_needs' => 'Inflatable rescue boats, life jackets, dry ration packs & potable water',
                'dispatch_agency' => 'Gurugram Volunteer Water Rescue',
                'message' => 'School transport van stalled in 4ft deep underpass water with children inside.',
                'status' => 'Volunteer Responding'
            ],
            [
                'sender_name' => 'Mohammad Imran Khan',
                'sender_phone' => '+91 99114 78201',
                'gps_lat' => 28.6667,
                'gps_lng' => 77.2280,
                'blood_type' => 'O-',
                'age' => 52,
                'persons_count' => '1 - 4',
                'priority' => 'High',
                'emergency_type' => 'Building Collapse',
                'medical_needs' => 'Hydraulic spreaders, spine immobilization boards, ALS paramedic unit',
                'dispatch_agency' => 'Fire Extrication & NDRF Heavy Squad',
                'message' => 'Masonry perimeter wall collapsed during heavy rain. 2 people pinned beneath debris.',
                'status' => 'Pending'
            ],
            [
                'sender_name' => 'Kavita Sundaram',
                'sender_phone' => '+91 98991 67230',
                'gps_lat' => 28.4680,
                'gps_lng' => 77.4970,
                'blood_type' => 'B-',
                'age' => 21,
                'persons_count' => '12+',
                'priority' => 'Medium',
                'emergency_type' => 'Flood',
                'medical_needs' => 'Inflatable rescue boats, life jackets, dry ration packs & potable water',
                'dispatch_agency' => 'Disaster Volunteer Corps - Noida',
                'message' => 'Hostel basement submerged up to ceiling, power outage for 12 hours.',
                'status' => 'Volunteer Responding'
            ]
        ];

        $sosStmt = $pdo->prepare("INSERT INTO emergency_sos (sender_name, sender_phone, gps_lat, gps_lng, blood_type, age, persons_count, priority, emergency_type, medical_needs, dispatch_agency, message, status) VALUES (:sender_name, :sender_phone, :gps_lat, :gps_lng, :blood_type, :age, :persons_count, :priority, :emergency_type, :medical_needs, :dispatch_agency, :message, :status)");
        foreach ($sosAlerts as $sos) {
            $sosStmt->execute($sos);
        }
    }

    // Auto-seed Agency Stations if table is empty
    $stationsCount = (int) $pdo->query("SELECT COUNT(*) FROM agency_stations")->fetchColumn();
    if ($stationsCount === 0) {
        $agencyStations = [
            // POLICE
            ['agency_type' => 'Police', 'station_name' => 'Mayur Vihar Police Station', 'zone_name' => 'East Delhi Sector 1', 'commander_name' => 'ACP Rajesh Mehra', 'contact_phone' => '+91 11 2275 8899', 'radio_channel' => 'VHF Ch-4 (154.80 MHz)', 'gps_lat' => 28.6050, 'gps_lng' => 77.2950, 'vehicles_count' => 8, 'personnel_count' => 42, 'address' => 'Pocket 1, Mayur Vihar Phase 1, East Delhi', 'status' => 'Operational'],
            ['agency_type' => 'Police', 'station_name' => 'Cyber City Police Precinct 4', 'zone_name' => 'Gurugram Cyber Corridor', 'commander_name' => 'Inspector Anil Hooda', 'contact_phone' => '+91 124 238 9100', 'radio_channel' => 'VHF Ch-7 (155.10 MHz)', 'gps_lat' => 28.4950, 'gps_lng' => 77.0890, 'vehicles_count' => 6, 'personnel_count' => 35, 'address' => 'DLF Phase 3, Cyber City, Gurugram', 'status' => 'Operational'],
            ['agency_type' => 'Police', 'station_name' => 'Kashmere Gate Police Station', 'zone_name' => 'North & Ring Road Sector', 'commander_name' => 'SHO Virender Singh', 'contact_phone' => '+91 11 2386 1122', 'radio_channel' => 'VHF Ch-2 (154.85 MHz)', 'gps_lat' => 28.6667, 'gps_lng' => 77.2280, 'vehicles_count' => 5, 'personnel_count' => 30, 'address' => 'Near ISBT Kashmiri Gate, Old Delhi', 'status' => 'High Alert'],
            ['agency_type' => 'Police', 'station_name' => 'Mohan Nagar Police Station', 'zone_name' => 'Ghaziabad Industrial Zone', 'commander_name' => 'CO Pradeep Sharma', 'contact_phone' => '+91 120 273 4455', 'radio_channel' => 'VHF Ch-1 (154.70 MHz)', 'gps_lat' => 28.6720, 'gps_lng' => 77.3680, 'vehicles_count' => 7, 'personnel_count' => 38, 'address' => 'Mohan Nagar Link Road, Ghaziabad', 'status' => 'High Alert'],

            // FIRE
            ['agency_type' => 'Fire', 'station_name' => 'Sahibabad Industrial Fire & Hazmat Station', 'zone_name' => 'Ghaziabad Hazmat Hub', 'commander_name' => 'Station Officer R. K. Tyagi', 'contact_phone' => '101 / +91 120 289 1101', 'radio_channel' => 'VHF Fire Ch-1 (156.40 MHz)', 'gps_lat' => 28.6750, 'gps_lng' => 77.3650, 'vehicles_count' => 8, 'personnel_count' => 45, 'address' => 'Site 4 Industrial Area, Sahibabad', 'status' => 'High Alert'],
            ['agency_type' => 'Fire', 'station_name' => 'Noida Sector 62 Fire Station', 'zone_name' => 'Noida Urban Sector', 'commander_name' => 'Chief Fire Officer Alok Sinha', 'contact_phone' => '+91 120 240 2101', 'radio_channel' => 'VHF Fire Ch-3 (156.55 MHz)', 'gps_lat' => 28.6290, 'gps_lng' => 77.3750, 'vehicles_count' => 6, 'personnel_count' => 36, 'address' => 'Block B Institutional Area, Sector 62, Noida', 'status' => 'Operational'],
            ['agency_type' => 'Fire', 'station_name' => 'Connaught Place HQ Fire Station', 'zone_name' => 'Central Delhi Command', 'commander_name' => 'ADO Manoj Kumar', 'contact_phone' => '+91 11 2341 2222', 'radio_channel' => 'VHF Fire Ch-5 (156.80 MHz)', 'gps_lat' => 28.6315, 'gps_lng' => 77.2167, 'vehicles_count' => 10, 'personnel_count' => 60, 'address' => 'Barakhamba Road, Connaught Place, New Delhi', 'status' => 'Operational'],

            // MEDICAL
            ['agency_type' => 'Medical', 'station_name' => 'AIIMS Apex Trauma Center & EMS Hub', 'zone_name' => 'South Delhi Trauma Corridor', 'commander_name' => 'Dr. Ananya Roy (Medical Supt.)', 'contact_phone' => '108 / +91 11 2659 8800', 'radio_channel' => 'EMS Dispatch Ch-1 (155.45 MHz)', 'gps_lat' => 28.5672, 'gps_lng' => 77.2100, 'vehicles_count' => 12, 'personnel_count' => 120, 'address' => 'Ring Road, Safdarjung Enclave, New Delhi', 'status' => 'High Alert'],
            ['agency_type' => 'Medical', 'station_name' => 'Safdarjung Emergency Hospital & Burn Unit', 'zone_name' => 'Central-South Medical Sector', 'commander_name' => 'Dr. Vikram Sethi', 'contact_phone' => '+91 11 2616 5060', 'radio_channel' => 'EMS Dispatch Ch-2 (155.50 MHz)', 'gps_lat' => 28.5700, 'gps_lng' => 77.2065, 'vehicles_count' => 8, 'personnel_count' => 95, 'address' => 'Ansari Nagar East, New Delhi', 'status' => 'Operational'],
            ['agency_type' => 'Medical', 'station_name' => 'Jaypee Hospital Emergency Trauma Base', 'zone_name' => 'Noida Expressway Corridor', 'commander_name' => 'Dr. Preeti Varma', 'contact_phone' => '+91 120 412 2222', 'radio_channel' => 'EMS Dispatch Ch-4 (155.70 MHz)', 'gps_lat' => 28.5200, 'gps_lng' => 77.3680, 'vehicles_count' => 6, 'personnel_count' => 70, 'address' => 'Sector 128, Noida-Greater Noida Expressway', 'status' => 'Operational'],
            ['agency_type' => 'Medical', 'station_name' => 'Medanta The Medicity Trauma Wing', 'zone_name' => 'Gurugram Medical Hub', 'commander_name' => 'Dr. Naresh Chawla', 'contact_phone' => '+91 124 414 1414', 'radio_channel' => 'EMS Dispatch Ch-3 (155.60 MHz)', 'gps_lat' => 28.4390, 'gps_lng' => 77.0420, 'vehicles_count' => 10, 'personnel_count' => 110, 'address' => 'CH Bakhtawar Singh Road, Sector 38, Gurugram', 'status' => 'Operational']
        ];

        $stStmt = $pdo->prepare("INSERT INTO agency_stations (agency_type, station_name, zone_name, commander_name, contact_phone, radio_channel, gps_lat, gps_lng, vehicles_count, personnel_count, address, status) VALUES (:agency_type, :station_name, :zone_name, :commander_name, :contact_phone, :radio_channel, :gps_lat, :gps_lng, :vehicles_count, :personnel_count, :address, :status)");
        foreach ($agencyStations as $st) {
            $stStmt->execute($st);
        }
    }

    // Auto-seed Agency Teams if table is empty
    $teamsCount = (int) $pdo->query("SELECT COUNT(*) FROM agency_teams")->fetchColumn();
    if ($teamsCount === 0) {
        $agencyTeams = [
            // POLICE TEAMS
            ['agency_type' => 'Police', 'station_id' => 1, 'callsign' => 'Delta-NCR-1', 'team_lead' => 'SI Ramesh Verma', 'members_count' => 18, 'vehicle_equipment' => '4x Mahindra Scorpio Patrol + Barricades', 'status' => 'On-Scene', 'current_task' => 'ITO Bridge & Geeta Colony Flood Cordon', 'contact_radio' => 'VHF Ch-4'],
            ['agency_type' => 'Police', 'station_id' => 3, 'callsign' => 'Echo-Patrol-3', 'team_lead' => 'ASI Devendra Pal', 'members_count' => 12, 'vehicle_equipment' => '2x Tata Xenon Escort + VHF Comms', 'status' => 'On-Scene', 'current_task' => 'Kashmere Gate ISBT Convoy Escort', 'contact_radio' => 'VHF Ch-2'],
            ['agency_type' => 'Police', 'station_id' => 2, 'callsign' => 'Alpha-Gurgaon-2', 'team_lead' => 'SI Rohit Khatri', 'members_count' => 14, 'vehicle_equipment' => '3x Bolero Patrol + Mobile Barriers', 'status' => 'Dispatched', 'current_task' => 'Hero Honda Chowk Underpass Blockage', 'contact_radio' => 'VHF Ch-7'],
            ['agency_type' => 'Police', 'station_id' => 4, 'callsign' => 'Charlie-Ghaziabad-1', 'team_lead' => 'Inspector Vijay Rawat', 'members_count' => 15, 'vehicle_equipment' => '4x PCR Vans + Loudspeaker Systems', 'status' => 'On-Scene', 'current_task' => 'Mohan Nagar Hazmat Perimeter & Evacuation', 'contact_radio' => 'VHF Ch-1'],
            ['agency_type' => 'Police', 'station_id' => 1, 'callsign' => 'Quick-Reaction-Cordon-5', 'team_lead' => 'SI Kuldeep Singh', 'members_count' => 10, 'vehicle_equipment' => '2x Armored Patrol Cruisers', 'status' => 'Available', 'current_task' => 'East Delhi Strategic Reserve', 'contact_radio' => 'VHF Ch-4'],

            // FIRE TEAMS
            ['agency_type' => 'Fire', 'station_id' => 5, 'callsign' => 'Hazmat Foam Squad 1', 'team_lead' => 'Station Officer R. K. Tyagi', 'members_count' => 12, 'vehicle_equipment' => '2x 4000L Foam Tenders + SCBA Gear', 'status' => 'On-Scene', 'current_task' => 'Sahibabad Solvent Storage Tank 3 Cooling', 'contact_radio' => 'VHF Fire Ch-1'],
            ['agency_type' => 'Fire', 'station_id' => 6, 'callsign' => 'Extrication & Ladder Unit 4', 'team_lead' => 'Leading Fireman S. Negi', 'members_count' => 8, 'vehicle_equipment' => '1x 42m Hydraulic Turntable + Rubble Cutters', 'status' => 'Dispatched', 'current_task' => 'Sector 62 Structural Wall Collapse Standby', 'contact_radio' => 'VHF Fire Ch-3'],
            ['agency_type' => 'Fire', 'station_id' => 7, 'callsign' => 'Heavy Rescue Engine 2', 'team_lead' => 'Station Officer Manoj Kumar', 'members_count' => 10, 'vehicle_equipment' => '2x Multi-Purpose Heavy Rescue Tenders', 'status' => 'Available', 'current_task' => 'Central Delhi Strategic Fire Reserve', 'contact_radio' => 'VHF Fire Ch-5'],
            ['agency_type' => 'Fire', 'station_id' => 5, 'callsign' => 'Smoke Ventilation Unit 2', 'team_lead' => 'Sub-Officer P. Sharma', 'members_count' => 6, 'vehicle_equipment' => '1x Positive Pressure Ventilation Truck', 'status' => 'On-Scene', 'current_task' => 'Site 4 Industrial Smoke Dispersion', 'contact_radio' => 'VHF Fire Ch-1'],

            // MEDICAL TEAMS
            ['agency_type' => 'Medical', 'station_id' => 8, 'callsign' => 'ALS Mobile ICU Unit 1', 'team_lead' => 'Dr. Sonia Kaul (Trauma Lead)', 'members_count' => 4, 'vehicle_equipment' => '1x Type-C ALS Ambulance + Defibrillator/Ventilator', 'status' => 'On-Scene', 'current_task' => 'Mayur Vihar Respiratory Patient Evacuation', 'contact_radio' => 'EMS Dispatch Ch-1'],
            ['agency_type' => 'Medical', 'station_id' => 8, 'callsign' => 'Emergency Transit Ambulance 3', 'team_lead' => 'Paramedic Lead A. Sharma', 'members_count' => 4, 'vehicle_equipment' => '2x Transit Ambulances + O2 System', 'status' => 'Dispatched', 'current_task' => 'Transferring 6 COPD Patients to Trauma Hub', 'contact_radio' => 'EMS Dispatch Ch-1'],
            ['agency_type' => 'Medical', 'station_id' => 9, 'callsign' => 'Burn Trauma Triage Team', 'team_lead' => 'Dr. Vikram Sethi', 'members_count' => 5, 'vehicle_equipment' => '1x Specialized Burn Ambulance + Dressing Packs', 'status' => 'On-Scene', 'current_task' => 'Sahibabad Smoke Inhalation Field Triage', 'contact_radio' => 'EMS Dispatch Ch-2'],
            ['agency_type' => 'Medical', 'station_id' => 11, 'callsign' => 'Medanta Critical Care Cruiser', 'team_lead' => 'Paramedic Lead K. Rao', 'members_count' => 4, 'vehicle_equipment' => '1x Advanced Paramedic Cruiser', 'status' => 'Available', 'current_task' => 'DLF Phase 3 Casualty Standby', 'contact_radio' => 'EMS Dispatch Ch-3']
        ];

        $tmStmt = $pdo->prepare("INSERT INTO agency_teams (agency_type, station_id, callsign, team_lead, members_count, vehicle_equipment, status, current_task, contact_radio) VALUES (:agency_type, :station_id, :callsign, :team_lead, :members_count, :vehicle_equipment, :status, :current_task, :contact_radio)");
        foreach ($agencyTeams as $tm) {
            $tmStmt->execute($tm);
        }
    }

    // Auto-seed Agency Tasks if table is empty
    $tasksCount = (int) $pdo->query("SELECT COUNT(*) FROM agency_tasks")->fetchColumn();
    if ($tasksCount === 0) {
        $agencyTasks = [
            // POLICE TASKS
            ['agency_type' => 'Police', 'title' => 'ITO Bridge Flood Cordon & Traffic Diversion', 'priority' => 'Critical', 'location' => 'ITO Flyover & Geeta Colony Access', 'assigned_team' => 'Delta-NCR-1', 'status' => 'In Progress', 'description' => 'Block all civilian vehicle access towards inundated Yamuna banks and guide ambulances via Vikas Marg.'],
            ['agency_type' => 'Police', 'title' => 'Kashmere Gate Evacuation Convoy Escort', 'priority' => 'High', 'location' => 'Kashmere Gate ISBT Ring Road Corridor', 'assigned_team' => 'Echo-Patrol-3', 'status' => 'In Progress', 'description' => 'Pilot civilian bus convoys carrying 350 displaced citizens safely to Thyagaraj Stadium Shelter.'],
            ['agency_type' => 'Police', 'title' => 'Hero Honda Highway Drainage Gridlock Control', 'priority' => 'High', 'location' => 'Hero Honda Chowk Underpasses, Gurugram', 'assigned_team' => 'Alpha-Gurgaon-2', 'status' => 'In Progress', 'description' => 'Divert NH-48 express traffic onto elevated lanes away from submerged 4ft underpasses.'],
            ['agency_type' => 'Police', 'title' => 'Mohan Nagar Chemical Hazmat Exclusion Zone', 'priority' => 'Critical', 'location' => 'Mohan Nagar Metro & Site 4 Perimeter', 'assigned_team' => 'Charlie-Ghaziabad-1', 'status' => 'In Progress', 'description' => 'Establish 1.2km strict exclusion perimeter to keep civilians clear of hazardous chemical solvent fumes.'],
            ['agency_type' => 'Police', 'title' => 'Drone Surveillance on Yamuna Lowland Encroachment', 'priority' => 'Medium', 'location' => 'Usmanpur & Shastri Park Banks', 'assigned_team' => 'Quick-Reaction-Cordon-5', 'status' => 'Pending', 'description' => 'Deploy aerial surveillance drone to identify remaining stranded cattle herders.'],

            // FIRE TASKS
            ['agency_type' => 'Fire', 'title' => 'Sahibabad Chemical Solvent Blaze Suppression', 'priority' => 'Critical', 'location' => 'Site 4 Industrial Area, Ghaziabad', 'assigned_team' => 'Hazmat Foam Squad 1', 'status' => 'In Progress', 'description' => 'Deploy continuous high-expansion AFFF chemical foam onto burning storage tanks to suppress toxic vapor cloud.'],
            ['agency_type' => 'Fire', 'title' => 'Structural Debris Extrication & Wall Collapse Standby', 'priority' => 'High', 'location' => 'Block B Institutional Area, Sector 62 Noida', 'assigned_team' => 'Extrication & Ladder Unit 4', 'status' => 'In Progress', 'description' => 'Hydraulic spreaders deployed to lift unstable concrete slabs and ensure structural safety of adjacent buildings.'],
            ['agency_type' => 'Fire', 'title' => 'Toxic Fume Dispersion & Smoke Extraction', 'priority' => 'High', 'location' => 'Factory Lane 8, Site 4 Industrial Area', 'assigned_team' => 'Smoke Ventilation Unit 2', 'status' => 'In Progress', 'description' => 'Positive pressure industrial blowers active to redirect smoke away from nearby residential colonies.'],
            ['agency_type' => 'Fire', 'title' => 'Industrial Substation Cooling Standby', 'priority' => 'Medium', 'location' => 'Okhla Phase 2 Electrical Substation', 'assigned_team' => 'Heavy Rescue Engine 2', 'status' => 'Pending', 'description' => 'Pre-position 2 water tenders near high-voltage transformer yard prone to flashover.'],

            // MEDICAL TASKS
            ['agency_type' => 'Medical', 'title' => 'Rapid Triage & High-Flow O2 Supply for COPD Casualty', 'priority' => 'Critical', 'location' => 'Supertech Capetown, Sector 74 Noida', 'assigned_team' => 'ALS Mobile ICU Unit 1', 'status' => 'In Progress', 'description' => 'Administer high-flow emergency oxygen and transport 72yo respiratory failure patient to AIIMS Trauma ICU.'],
            ['agency_type' => 'Medical', 'title' => 'Factory Burn Casualty Stabilization & Field Care', 'priority' => 'Critical', 'location' => 'Mohan Nagar Community Medical Post', 'assigned_team' => 'Burn Trauma Triage Team', 'status' => 'In Progress', 'description' => 'Treat 14 factory workers with 2nd-degree chemical burns, apply hydrogel dressing, and prep for Safdarjung burn ward.'],
            ['agency_type' => 'Medical', 'title' => 'Evacuee Health Screening & Infection Control', 'priority' => 'Medium', 'location' => 'Thyagaraj Stadium Relief Camp', 'assigned_team' => 'Emergency Transit Ambulance 3', 'status' => 'In Progress', 'description' => 'Screen 180 flood evacuees for waterborne gastroenteritis, distribute ORS, and isolate fever cases.'],
            ['agency_type' => 'Medical', 'title' => 'Submerged Van Children Hypothermia Check', 'priority' => 'High', 'location' => 'Cyber City DLF Phase 3 Underpass', 'assigned_team' => 'Medanta Critical Care Cruiser', 'status' => 'Completed', 'description' => 'Evaluated 6 rescued schoolchildren for mild hypothermia; provided thermal blankets and discharged to parents.'],
            ['agency_type' => 'Medical', 'title' => 'Bulk Oxygen Cylinder Replenishment Logistics', 'priority' => 'High', 'location' => 'East Delhi Emergency Depots', 'assigned_team' => 'ALS Mobile ICU Unit 1', 'status' => 'Pending', 'description' => 'Coordinate delivery of 50 D-type oxygen cylinders from central medical store to field shelters.']
        ];

        $tskStmt = $pdo->prepare("INSERT INTO agency_tasks (agency_type, title, priority, location, assigned_team, status, description) VALUES (:agency_type, :title, :priority, :location, :assigned_team, :status, :description)");
        foreach ($agencyTasks as $tsk) {
            $tskStmt->execute($tsk);
        }
    }

    // Auto-seed Agency Resources & Capacity if table is empty
    $resCount = (int) $pdo->query("SELECT COUNT(*) FROM agency_resources")->fetchColumn();
    if ($resCount === 0) {
        $agencyResources = [
            // POLICE RESOURCES
            ['agency_type' => 'Police', 'station_id' => 1, 'item_name' => 'Crowd Control Steel Barricades', 'category' => 'Equipment', 'total_quantity' => 400, 'available_quantity' => 280, 'allocated_quantity' => 120, 'unit' => 'barricades', 'status' => 'In Stock'],
            ['agency_type' => 'Police', 'station_id' => 1, 'item_name' => 'Tactical Police Patrol Cruisers (Scorpio / Bolero)', 'category' => 'Vehicles', 'total_quantity' => 26, 'available_quantity' => 18, 'allocated_quantity' => 8, 'unit' => 'vehicles', 'status' => 'In Stock'],
            ['agency_type' => 'Police', 'station_id' => 2, 'item_name' => 'Surveillance & Night-Vision Drones', 'category' => 'Equipment', 'total_quantity' => 14, 'available_quantity' => 9, 'allocated_quantity' => 5, 'unit' => 'units', 'status' => 'In Stock'],
            ['agency_type' => 'Police', 'station_id' => 3, 'item_name' => 'VHF Encrypted Handheld Radios', 'category' => 'Communication', 'total_quantity' => 160, 'available_quantity' => 110, 'allocated_quantity' => 50, 'unit' => 'radios', 'status' => 'In Stock'],
            ['agency_type' => 'Police', 'station_id' => 4, 'item_name' => 'High-Intensity Searchlight Beacons', 'category' => 'Equipment', 'total_quantity' => 45, 'available_quantity' => 30, 'allocated_quantity' => 15, 'unit' => 'units', 'status' => 'In Stock'],
            ['agency_type' => 'Police', 'station_id' => 1, 'item_name' => 'Inflatable River Patrol Speedboats', 'category' => 'Vehicles', 'total_quantity' => 6, 'available_quantity' => 2, 'allocated_quantity' => 4, 'unit' => 'boats', 'status' => 'In Stock'],

            // FIRE RESOURCES
            ['agency_type' => 'Fire', 'station_id' => 5, 'item_name' => 'Heavy Water Tenders (4000L - 6000L)', 'category' => 'Vehicles', 'total_quantity' => 24, 'available_quantity' => 16, 'allocated_quantity' => 8, 'unit' => 'tenders', 'status' => 'In Stock'],
            ['agency_type' => 'Fire', 'station_id' => 5, 'item_name' => 'AFFF Chemical Fire Foam Barrels (200L)', 'category' => 'Suppression', 'total_quantity' => 180, 'available_quantity' => 95, 'allocated_quantity' => 85, 'unit' => 'barrels', 'status' => 'In Stock'],
            ['agency_type' => 'Fire', 'station_id' => 6, 'item_name' => 'Self-Contained Breathing Apparatus (SCBA Sets)', 'category' => 'Protection', 'total_quantity' => 120, 'available_quantity' => 75, 'allocated_quantity' => 45, 'unit' => 'sets', 'status' => 'In Stock'],
            ['agency_type' => 'Fire', 'station_id' => 6, 'item_name' => 'Hydraulic Rubble Cutters & Spreaders (Holmatro)', 'category' => 'Equipment', 'total_quantity' => 18, 'available_quantity' => 11, 'allocated_quantity' => 7, 'unit' => 'sets', 'status' => 'In Stock'],
            ['agency_type' => 'Fire', 'station_id' => 7, 'item_name' => 'High-Angle Rescue Ropes & Harness Kits', 'category' => 'Equipment', 'total_quantity' => 60, 'available_quantity' => 42, 'allocated_quantity' => 18, 'unit' => 'kits', 'status' => 'In Stock'],
            ['agency_type' => 'Fire', 'station_id' => 5, 'item_name' => 'Positive Pressure Smoke Ventilation Fans', 'category' => 'Equipment', 'total_quantity' => 16, 'available_quantity' => 8, 'allocated_quantity' => 8, 'unit' => 'units', 'status' => 'In Stock'],

            // MEDICAL RESOURCES & HOSPITAL CAPACITIES
            ['agency_type' => 'Medical', 'station_id' => 8, 'item_name' => 'ICU & Critical Trauma Beds (Multi-Hospital Reserve)', 'category' => 'Capacity', 'total_quantity' => 143, 'available_quantity' => 98, 'allocated_quantity' => 45, 'unit' => 'beds', 'status' => 'In Stock'],
            ['agency_type' => 'Medical', 'station_id' => 8, 'item_name' => 'Emergency High-Flow Oxygen Cylinders (D-Type 47L)', 'category' => 'Life Support', 'total_quantity' => 250, 'available_quantity' => 165, 'allocated_quantity' => 85, 'unit' => 'cylinders', 'status' => 'In Stock'],
            ['agency_type' => 'Medical', 'station_id' => 8, 'item_name' => 'Advanced Life Support (ALS) Ambulances', 'category' => 'Vehicles', 'total_quantity' => 36, 'available_quantity' => 22, 'allocated_quantity' => 14, 'unit' => 'ambulances', 'status' => 'In Stock'],
            ['agency_type' => 'Medical', 'station_id' => 9, 'item_name' => 'Hemostatic Gauze & Burn Trauma Dressing Packs', 'category' => 'Medical Supplies', 'total_quantity' => 500, 'available_quantity' => 320, 'allocated_quantity' => 180, 'unit' => 'packs', 'status' => 'In Stock'],
            ['agency_type' => 'Medical', 'station_id' => 8, 'item_name' => 'Emergency O+ & B+ Blood Units (Crisis Reserve)', 'category' => 'Medical Supplies', 'total_quantity' => 120, 'available_quantity' => 68, 'allocated_quantity' => 52, 'unit' => 'units', 'status' => 'In Stock'],
            ['agency_type' => 'Medical', 'station_id' => 10, 'item_name' => 'Transport Ventilators & Defibrillator Kits', 'category' => 'Life Support', 'total_quantity' => 40, 'available_quantity' => 26, 'allocated_quantity' => 14, 'unit' => 'units', 'status' => 'In Stock'],
            ['agency_type' => 'Medical', 'station_id' => 9, 'item_name' => 'Specialized Severe Burn Ward Beds', 'category' => 'Capacity', 'total_quantity' => 35, 'available_quantity' => 21, 'allocated_quantity' => 14, 'unit' => 'beds', 'status' => 'In Stock']
        ];

        $resStmt = $pdo->prepare("INSERT INTO agency_resources (agency_type, station_id, item_name, category, total_quantity, available_quantity, allocated_quantity, unit, status) VALUES (:agency_type, :station_id, :item_name, :category, :total_quantity, :available_quantity, :allocated_quantity, :unit, :status)");
        foreach ($agencyResources as $res) {
            $resStmt->execute($res);
        }
    }

    // Auto-seed Master Disaster Resources Catalog if empty
    $masterCount = (int) $pdo->query("SELECT COUNT(*) FROM master_resources")->fetchColumn();
    if ($masterCount === 0) {
        $masterList = [
            // FOOD & WATER
            [
                'resource_code' => 'RES-FD-01',
                'name' => 'Packaged Potable Drinking Water (20L Cans)',
                'category' => 'Food & Water',
                'total_stock' => 2500,
                'available_stock' => 1100,
                'distributed_stock' => 1400,
                'unit' => 'cans',
                'primary_warehouse' => 'Delhi Central Disaster Logistics Base, Okhla',
                'status' => 'In Stock',
                'icon' => 'fa-bottle-water',
                'color' => 'blue',
                'notes' => 'BIS certified mineral drinking water for evacuees and frontline emergency crews.'
            ],
            [
                'resource_code' => 'RES-FD-02',
                'name' => 'Dry Ration Family Meal Packs (5-Day Kits)',
                'category' => 'Food & Water',
                'total_stock' => 3000,
                'available_stock' => 1200,
                'distributed_stock' => 1800,
                'unit' => 'kits',
                'primary_warehouse' => 'East Delhi Relief Depot, Mayur Vihar',
                'status' => 'In Stock',
                'icon' => 'fa-bowl-rice',
                'color' => 'amber',
                'notes' => 'Contains rice, lentils, wheat flour, high-protein biscuits, ORS, and cooking oil.'
            ],

            // MEDICAL SUPPLIES
            [
                'resource_code' => 'RES-MED-01',
                'name' => 'Emergency O+ & B+ Blood Units (Crisis Reserve)',
                'category' => 'Medical Supplies',
                'total_stock' => 240,
                'available_stock' => 110,
                'distributed_stock' => 130,
                'unit' => 'units',
                'primary_warehouse' => 'AIIMS Apex Blood Transfusion Center, New Delhi',
                'status' => 'Low Stock',
                'icon' => 'fa-droplet',
                'color' => 'rose',
                'notes' => 'Cold-chain stored PRBC and whole blood units for severe hemorrhage trauma.'
            ],
            [
                'resource_code' => 'RES-MED-02',
                'name' => 'High-Flow Medical Oxygen Cylinders (47L D-Type)',
                'category' => 'Medical Supplies',
                'total_stock' => 400,
                'available_stock' => 180,
                'distributed_stock' => 220,
                'unit' => 'cylinders',
                'primary_warehouse' => 'Safdarjung Central Medical Gas Depot',
                'status' => 'In Stock',
                'icon' => 'fa-lungs',
                'color' => 'teal',
                'notes' => 'High-purity medical oxygen for COPD, respiratory distress, and smoke inhalation victims.'
            ],
            [
                'resource_code' => 'RES-MED-03',
                'name' => 'Hemostatic Gauze & Burn Trauma Dressing Packs',
                'category' => 'Medical Supplies',
                'total_stock' => 1200,
                'available_stock' => 550,
                'distributed_stock' => 650,
                'unit' => 'packs',
                'primary_warehouse' => 'Ghaziabad Medical Supply Reserve Hub',
                'status' => 'In Stock',
                'icon' => 'fa-kit-medical',
                'color' => 'emerald',
                'notes' => 'QuikClot combat gauze, silver sulfadiazine hydrogel dressings, and sterile bandages.'
            ],

            // POWER & ENERGY
            [
                'resource_code' => 'RES-PWR-01',
                'name' => 'Heavy Diesel Power Generators (75 kVA Silent)',
                'category' => 'Power & Energy',
                'total_stock' => 30,
                'available_stock' => 12,
                'distributed_stock' => 18,
                'unit' => 'generators',
                'primary_warehouse' => 'Delhi Disaster Power Logistics Yard, Okhla',
                'status' => 'In Stock',
                'icon' => 'fa-bolt',
                'color' => 'yellow',
                'notes' => 'Skid-mounted diesel gensets to power flood shelters, water pumps, and field clinics.'
            ],
            [
                'resource_code' => 'RES-PWR-02',
                'name' => 'Mobile Solar Charging & Battery Hubs',
                'category' => 'Power & Energy',
                'total_stock' => 50,
                'available_stock' => 22,
                'distributed_stock' => 28,
                'unit' => 'hubs',
                'primary_warehouse' => 'Noida Sector 62 Energy Depots',
                'status' => 'In Stock',
                'icon' => 'fa-solar-panel',
                'color' => 'amber',
                'notes' => '5000Wh lithium portable power banks with high-power searchlights and VHF radio chargers.'
            ],

            // VEHICLES & MOBILITY
            [
                'resource_code' => 'RES-VEH-01',
                'name' => 'Advanced Life Support (ALS) Type-C Ambulances',
                'category' => 'Vehicles & Mobility',
                'total_stock' => 45,
                'available_stock' => 18,
                'distributed_stock' => 27,
                'unit' => 'ambulances',
                'primary_warehouse' => 'Central Emergency Medical Vehicle Depot, Delhi',
                'status' => 'In Stock',
                'icon' => 'fa-truck-medical',
                'color' => 'teal',
                'notes' => 'Equipped with transport ventilators, multipara cardiac monitors, and defibrillators.'
            ],
            [
                'resource_code' => 'RES-VEH-02',
                'name' => 'Heavy Inflatable Rescue Motor Speedboats (Zodiac)',
                'category' => 'Vehicles & Mobility',
                'total_stock' => 20,
                'available_stock' => 6,
                'distributed_stock' => 14,
                'unit' => 'boats',
                'primary_warehouse' => 'Yamuna Bank NDRF Water Rescue Staging Post',
                'status' => 'Low Stock',
                'icon' => 'fa-ship',
                'color' => 'blue',
                'notes' => 'Rigid inflatable hull with 40HP Yamaha outboard motors for flood evacuation.'
            ],

            // SHELTER & BEDDING
            [
                'resource_code' => 'RES-SHL-01',
                'name' => 'Weatherproof Disaster Relief Tents (10-Person)',
                'category' => 'Shelter & Bedding',
                'total_stock' => 450,
                'available_stock' => 160,
                'distributed_stock' => 290,
                'unit' => 'tents',
                'primary_warehouse' => 'Mayur Vihar Central Relief Depot, East Delhi',
                'status' => 'In Stock',
                'icon' => 'fa-campground',
                'color' => 'emerald',
                'notes' => 'Fire-retardant, waterproof multi-layer canvas shelter tents with ground insulation.'
            ],
            [
                'resource_code' => 'RES-SHL-02',
                'name' => 'Thermal Woolen Blankets & Waterproof Mats',
                'category' => 'Shelter & Bedding',
                'total_stock' => 3500,
                'available_stock' => 1400,
                'distributed_stock' => 2100,
                'unit' => 'pieces',
                'primary_warehouse' => 'Goonj Disaster Logistics Center, Sarita Vihar',
                'status' => 'In Stock',
                'icon' => 'fa-mattress-pillow',
                'color' => 'indigo',
                'notes' => 'High-warmth wool blankets and heavy-gauge PVC waterproof ground tarpaulins.'
            ],

            // TACTICAL & RESCUE GEAR
            [
                'resource_code' => 'RES-TAC-01',
                'name' => 'Self-Contained Breathing Apparatus (SCBA 300 Bar)',
                'category' => 'Tactical & Rescue Gear',
                'total_stock' => 180,
                'available_stock' => 85,
                'distributed_stock' => 95,
                'unit' => 'sets',
                'primary_warehouse' => 'Connaught Place HQ Fire Safety Depot',
                'status' => 'In Stock',
                'icon' => 'fa-mask-ventilator',
                'color' => 'red',
                'notes' => 'Positive pressure carbon composite breathing apparatus for toxic chemical smoke zones.'
            ],
            [
                'resource_code' => 'RES-TAC-02',
                'name' => 'Holmatro Hydraulic Rubble Cutters & Spreaders',
                'category' => 'Tactical & Rescue Gear',
                'total_stock' => 24,
                'available_stock' => 10,
                'distributed_stock' => 14,
                'unit' => 'sets',
                'primary_warehouse' => 'Sahibabad Hazmat & Heavy Extrication Yard',
                'status' => 'In Stock',
                'icon' => 'fa-screwdriver-wrench',
                'color' => 'orange',
                'notes' => 'Cordless battery-powered hydraulic rescue tool kits for vehicle/collapse extrication.'
            ]
        ];

        $mstStmt = $pdo->prepare("INSERT INTO master_resources (resource_code, name, category, total_stock, available_stock, distributed_stock, unit, primary_warehouse, status, icon, color, notes) VALUES (:resource_code, :name, :category, :total_stock, :available_stock, :distributed_stock, :unit, :primary_warehouse, :status, :icon, :color, :notes)");
        foreach ($masterList as $mst) {
            $mstStmt->execute($mst);
        }

        // Fetch inserted IDs to link distribution logs
        $waterId = (int)$pdo->query("SELECT id FROM master_resources WHERE resource_code = 'RES-FD-01'")->fetchColumn();
        $foodId = (int)$pdo->query("SELECT id FROM master_resources WHERE resource_code = 'RES-FD-02'")->fetchColumn();
        $bloodId = (int)$pdo->query("SELECT id FROM master_resources WHERE resource_code = 'RES-MED-01'")->fetchColumn();
        $oxygenId = (int)$pdo->query("SELECT id FROM master_resources WHERE resource_code = 'RES-MED-02'")->fetchColumn();
        $dressingId = (int)$pdo->query("SELECT id FROM master_resources WHERE resource_code = 'RES-MED-03'")->fetchColumn();
        $gensetId = (int)$pdo->query("SELECT id FROM master_resources WHERE resource_code = 'RES-PWR-01'")->fetchColumn();
        $solarId = (int)$pdo->query("SELECT id FROM master_resources WHERE resource_code = 'RES-PWR-02'")->fetchColumn();
        $ambId = (int)$pdo->query("SELECT id FROM master_resources WHERE resource_code = 'RES-VEH-01'")->fetchColumn();
        $boatId = (int)$pdo->query("SELECT id FROM master_resources WHERE resource_code = 'RES-VEH-02'")->fetchColumn();
        $tentId = (int)$pdo->query("SELECT id FROM master_resources WHERE resource_code = 'RES-SHL-01'")->fetchColumn();
        $blanketId = (int)$pdo->query("SELECT id FROM master_resources WHERE resource_code = 'RES-SHL-02'")->fetchColumn();
        $scbaId = (int)$pdo->query("SELECT id FROM master_resources WHERE resource_code = 'RES-TAC-01'")->fetchColumn();
        $cutterId = (int)$pdo->query("SELECT id FROM master_resources WHERE resource_code = 'RES-TAC-02'")->fetchColumn();

        $distributions = [
            // Water Distributions
            ['resource_id' => $waterId, 'destination_type' => 'Relief Camp', 'destination_name' => 'Mayur Vihar Flood Relief Tent City', 'location_address' => 'Pocket 1, Mayur Vihar Phase 1, East Delhi', 'gps_lat' => 28.6080, 'gps_lng' => 77.2980, 'quantity_distributed' => 600, 'unit' => 'cans', 'dispatched_by' => 'Superadmin Logistics Command', 'contact_officer' => 'Lead Vol. Elena Rostova (+91 98101 22334)', 'distribution_status' => 'Delivered / On-Site', 'notes' => 'Direct distribution to 4,800 displaced flood evacuees.'],
            ['resource_id' => $waterId, 'destination_type' => 'Relief Camp', 'destination_name' => 'Thyagaraj Stadium Evacuation Camp', 'location_address' => 'Thyagaraj Nagar, Near INA, New Delhi', 'gps_lat' => 28.5800, 'gps_lng' => 77.2150, 'quantity_distributed' => 500, 'unit' => 'cans', 'dispatched_by' => 'National Disaster Relief Unit', 'contact_officer' => 'Vikas Pandey (+91 98990 11223)', 'distribution_status' => 'Delivered / On-Site', 'notes' => 'Camp supply for 850 registered victims.'],
            ['resource_id' => $waterId, 'destination_type' => 'Relief Camp', 'destination_name' => 'Noida Stadium Sector 21 Evacuation Camp', 'location_address' => 'Sector 21, Noida, Gautam Buddha Nagar', 'gps_lat' => 28.5920, 'gps_lng' => 77.3400, 'quantity_distributed' => 300, 'unit' => 'cans', 'dispatched_by' => 'UP Disaster Management Cell', 'contact_officer' => 'Karan Mehra (+91 98712 44556)', 'distribution_status' => 'Delivered / On-Site', 'notes' => 'Replenishment for lowland evacuees.'],

            // Food Distributions
            ['resource_id' => $foodId, 'destination_type' => 'Relief Camp', 'destination_name' => 'Mayur Vihar Flood Relief Tent City', 'location_address' => 'Pocket 1, Mayur Vihar Phase 1, East Delhi', 'gps_lat' => 28.6080, 'gps_lng' => 77.2980, 'quantity_distributed' => 800, 'unit' => 'kits', 'dispatched_by' => 'Superadmin Logistics Command', 'contact_officer' => 'Lead Vol. Elena Rostova (+91 98101 22334)', 'distribution_status' => 'Delivered / On-Site', 'notes' => 'Ration distribution to 800 affected families.'],
            ['resource_id' => $foodId, 'destination_type' => 'Relief Camp', 'destination_name' => 'Thyagaraj Stadium Evacuation Camp', 'location_address' => 'Thyagaraj Nagar, Near INA, New Delhi', 'gps_lat' => 28.5800, 'gps_lng' => 77.2150, 'quantity_distributed' => 600, 'unit' => 'kits', 'dispatched_by' => 'Robin Hood Army Delhi-NCR', 'contact_officer' => 'Vikas Pandey (+91 98990 11223)', 'distribution_status' => 'Delivered / On-Site', 'notes' => 'Cooked meal ration kits.'],
            ['resource_id' => $foodId, 'destination_type' => 'Relief Camp', 'destination_name' => 'Tau Devi Lal Stadium Shelter Gurugram', 'location_address' => 'Sector 38, Near Rajiv Chowk, Gurugram', 'gps_lat' => 28.4280, 'gps_lng' => 77.0320, 'quantity_distributed' => 400, 'unit' => 'kits', 'dispatched_by' => 'Haryana Relief Operations', 'contact_officer' => 'Aditya Singhania (+91 99991 44332)', 'distribution_status' => 'Delivered / On-Site', 'notes' => 'For flash flood displaced families.'],

            // Blood Distributions
            ['resource_id' => $bloodId, 'destination_type' => 'Hospital', 'destination_name' => 'AIIMS Apex Trauma Center & EMS Hub', 'location_address' => 'Ring Road, Safdarjung Enclave, New Delhi', 'gps_lat' => 28.5672, 'gps_lng' => 77.2100, 'quantity_distributed' => 70, 'unit' => 'units', 'dispatched_by' => 'EMS Medical Director Dr. Roy', 'contact_officer' => 'Dr. Sonia Kaul (+91 98118 77889)', 'distribution_status' => 'Delivered / On-Site', 'notes' => 'Emergency reserve for critical surgical trauma cases.'],
            ['resource_id' => $bloodId, 'destination_type' => 'Hospital', 'destination_name' => 'Safdarjung Emergency Hospital & Burn Unit', 'location_address' => 'Ansari Nagar East, New Delhi', 'gps_lat' => 28.5700, 'gps_lng' => 77.2065, 'quantity_distributed' => 40, 'unit' => 'units', 'dispatched_by' => 'Central Blood Bank Control', 'contact_officer' => 'Dr. Vikram Sethi (+91 11 2616 5060)', 'distribution_status' => 'Delivered / On-Site', 'notes' => 'Severe burn casualty transfusions.'],
            ['resource_id' => $bloodId, 'destination_type' => 'Hospital', 'destination_name' => 'Jaypee Hospital Emergency Trauma Base', 'location_address' => 'Sector 128, Noida Expressway', 'gps_lat' => 28.5200, 'gps_lng' => 77.3680, 'quantity_distributed' => 20, 'unit' => 'units', 'dispatched_by' => 'Superadmin Health Desk', 'contact_officer' => 'Dr. Preeti Varma (+91 120 412 2222)', 'distribution_status' => 'Delivered / On-Site', 'notes' => 'Expressway trauma victims reserve.'],

            // Oxygen Distributions
            ['resource_id' => $oxygenId, 'destination_type' => 'Hospital', 'destination_name' => 'AIIMS Apex Trauma Center & EMS Hub', 'location_address' => 'Ring Road, Safdarjung Enclave, New Delhi', 'gps_lat' => 28.5672, 'gps_lng' => 77.2100, 'quantity_distributed' => 100, 'unit' => 'cylinders', 'dispatched_by' => 'AIIMS Medical Supply Desk', 'contact_officer' => 'Dr. Sonia Kaul (+91 98118 77889)', 'distribution_status' => 'Delivered / On-Site', 'notes' => 'ICU ventilator manifolds and trauma beds.'],
            ['resource_id' => $oxygenId, 'destination_type' => 'Hospital', 'destination_name' => 'Safdarjung Emergency Hospital & Burn Unit', 'location_address' => 'Ansari Nagar East, New Delhi', 'gps_lat' => 28.5700, 'gps_lng' => 77.2065, 'quantity_distributed' => 60, 'unit' => 'cylinders', 'dispatched_by' => 'Central Medical Gas Ops', 'contact_officer' => 'Dr. Vikram Sethi (+91 11 2616 5060)', 'distribution_status' => 'Delivered / On-Site', 'notes' => 'Smoke inhalation acute care.'],
            ['resource_id' => $oxygenId, 'destination_type' => 'Field Clinic', 'destination_name' => 'Mohan Nagar Community Medical Post', 'location_address' => 'Mohan Nagar Community Hall, Ghaziabad', 'gps_lat' => 28.6720, 'gps_lng' => 77.3680, 'quantity_distributed' => 40, 'unit' => 'cylinders', 'dispatched_by' => 'Ghaziabad Crisis Logistics', 'contact_officer' => 'Dr. Simran Kaur (+91 98118 77889)', 'distribution_status' => 'Delivered / On-Site', 'notes' => 'On-site oxygenation for factory smoke casualties.'],
            ['resource_id' => $oxygenId, 'destination_type' => 'Relief Camp', 'destination_name' => 'Supertech Capetown Transit Medical Unit', 'location_address' => 'Sector 74, Noida', 'gps_lat' => 28.5850, 'gps_lng' => 77.3820, 'quantity_distributed' => 20, 'unit' => 'cylinders', 'dispatched_by' => 'ALS Ambulance Dispatch', 'contact_officer' => 'Paramedic Lead A. Sharma', 'distribution_status' => 'Delivered / On-Site', 'notes' => 'COPD elderly patient home-support.'],

            // Generators Distributions
            ['resource_id' => $gensetId, 'destination_type' => 'Relief Camp', 'destination_name' => 'Mayur Vihar Flood Relief Tent City', 'location_address' => 'Pocket 1, Mayur Vihar Phase 1, East Delhi', 'gps_lat' => 28.6080, 'gps_lng' => 77.2980, 'quantity_distributed' => 6, 'unit' => 'generators', 'dispatched_by' => 'Superadmin Power Grid Command', 'contact_officer' => 'Lead Vol. Elena Rostova (+91 98101 22334)', 'distribution_status' => 'Delivered / On-Site', 'notes' => 'High-power lighting and dewatering pumps power.'],
            ['resource_id' => $gensetId, 'destination_type' => 'Relief Camp', 'destination_name' => 'Thyagaraj Stadium Evacuation Camp', 'location_address' => 'Thyagaraj Nagar, Near INA, New Delhi', 'gps_lat' => 28.5800, 'gps_lng' => 77.2150, 'quantity_distributed' => 4, 'unit' => 'generators', 'dispatched_by' => 'Delhi Civil Defence', 'contact_officer' => 'Rohit Verma (+91 99103 55667)', 'distribution_status' => 'Delivered / On-Site', 'notes' => 'Shelter refrigeration and ventilation.'],
            ['resource_id' => $gensetId, 'destination_type' => 'Fire Station', 'destination_name' => 'Sahibabad Industrial Fire & Hazmat Station', 'location_address' => 'Site 4 Industrial Area, Sahibabad', 'gps_lat' => 28.6750, 'gps_lng' => 77.3650, 'quantity_distributed' => 4, 'unit' => 'generators', 'dispatched_by' => 'Fire Chief Thomas Sterling', 'contact_officer' => 'Station Officer R. K. Tyagi', 'distribution_status' => 'Delivered / On-Site', 'notes' => 'Industrial high-pressure smoke exhaust fans power.'],
            ['resource_id' => $gensetId, 'destination_type' => 'Police Station', 'destination_name' => 'Kashmere Gate ISBT Police Command', 'location_address' => 'Near ISBT Kashmiri Gate, Old Delhi', 'gps_lat' => 28.6667, 'gps_lng' => 77.2280, 'quantity_distributed' => 4, 'unit' => 'generators', 'dispatched_by' => 'Delhi Police HQ', 'contact_officer' => 'SHO Virender Singh (+91 11 2386 1122)', 'distribution_status' => 'Delivered / On-Site', 'notes' => 'Night evacuation perimeter floodlights.'],

            // Rescue Speedboats Distributions
            ['resource_id' => $boatId, 'destination_type' => 'Disaster Evacuation Zone', 'destination_name' => 'Mayur Vihar Yamuna Lowlands & River Bank', 'location_address' => 'Yamuna Floodplains, East Delhi', 'gps_lat' => 28.6050, 'gps_lng' => 77.2950, 'quantity_distributed' => 8, 'unit' => 'boats', 'dispatched_by' => 'NDRF Tactical Boat Squad', 'contact_officer' => 'Brig. Rajiv Sharma (+91 011 2436 3260)', 'distribution_status' => 'Delivered / On-Site', 'notes' => 'Continuous extraction of families trapped on rooftops.'],
            ['resource_id' => $boatId, 'destination_type' => 'Disaster Evacuation Zone', 'destination_name' => 'Usmanpur & Shastri Park Lowland Banks', 'location_address' => 'Usmanpur Yamuna Basin, North-East Delhi', 'gps_lat' => 28.6700, 'gps_lng' => 77.2600, 'quantity_distributed' => 6, 'unit' => 'boats', 'dispatched_by' => 'NDRF Tactical Boat Squad', 'contact_officer' => 'Commander R. K. Mehra', 'distribution_status' => 'Delivered / On-Site', 'notes' => 'River rescue and livestock evacuation.'],

            // Tents Distributions
            ['resource_id' => $tentId, 'destination_type' => 'Relief Camp', 'destination_name' => 'Mayur Vihar Flood Relief Tent City', 'location_address' => 'Pocket 1, Mayur Vihar Phase 1, East Delhi', 'gps_lat' => 28.6080, 'gps_lng' => 77.2980, 'quantity_distributed' => 180, 'unit' => 'tents', 'dispatched_by' => 'Superadmin Logistics Command', 'contact_officer' => 'Lead Vol. Elena Rostova (+91 98101 22334)', 'distribution_status' => 'Delivered / On-Site', 'notes' => '180 10-person tents housing 1,800 evacuees.'],
            ['resource_id' => $tentId, 'destination_type' => 'Relief Camp', 'destination_name' => 'Noida Stadium Sector 21 Shelter', 'location_address' => 'Sector 21, Noida', 'gps_lat' => 28.5920, 'gps_lng' => 77.3400, 'quantity_distributed' => 70, 'unit' => 'tents', 'dispatched_by' => 'Noida Authority Disaster Cell', 'contact_officer' => 'Karan Mehra (+91 98712 44556)', 'distribution_status' => 'Delivered / On-Site', 'notes' => 'Emergency bedding tents.'],
            ['resource_id' => $tentId, 'destination_type' => 'Relief Camp', 'destination_name' => 'Tau Devi Lal Stadium Shelter Gurugram', 'location_address' => 'Sector 38, Gurugram', 'gps_lat' => 28.4280, 'gps_lng' => 77.0320, 'quantity_distributed' => 40, 'unit' => 'tents', 'dispatched_by' => 'Gurugram Municipal Disaster Corp', 'contact_officer' => 'Aditya Singhania (+91 99991 44332)', 'distribution_status' => 'Delivered / On-Site', 'notes' => 'Urban flood evacuee shelters.'],

            // Blankets Distributions
            ['resource_id' => $blanketId, 'destination_type' => 'Relief Camp', 'destination_name' => 'Mayur Vihar Flood Relief Tent City', 'location_address' => 'Pocket 1, Mayur Vihar Phase 1, East Delhi', 'gps_lat' => 28.6080, 'gps_lng' => 77.2980, 'quantity_distributed' => 900, 'unit' => 'pieces', 'dispatched_by' => 'Red Cross Delhi Chapter', 'contact_officer' => 'Elena Rostova (+91 98101 22334)', 'distribution_status' => 'Delivered / On-Site', 'notes' => 'Woolen blankets & mats distributed.'],
            ['resource_id' => $blanketId, 'destination_type' => 'Relief Camp', 'destination_name' => 'Thyagaraj Stadium Evacuation Camp', 'location_address' => 'Thyagaraj Nagar, New Delhi', 'gps_lat' => 28.5800, 'gps_lng' => 77.2150, 'quantity_distributed' => 800, 'unit' => 'pieces', 'dispatched_by' => 'Goonj Relief Org', 'contact_officer' => 'Vikas Pandey (+91 98990 11223)', 'distribution_status' => 'Delivered / On-Site', 'notes' => 'Disaster victim bedding support.'],
            ['resource_id' => $blanketId, 'destination_type' => 'Relief Camp', 'destination_name' => 'Noida Stadium Sector 21 Shelter', 'location_address' => 'Sector 21, Noida', 'gps_lat' => 28.5920, 'gps_lng' => 77.3400, 'quantity_distributed' => 400, 'unit' => 'pieces', 'dispatched_by' => 'Civil Defence Corps', 'contact_officer' => 'Rohit Verma (+91 99103 55667)', 'distribution_status' => 'Delivered / On-Site', 'notes' => 'Night warmth packs.']
        ];

        $distStmt = $pdo->prepare("INSERT INTO resource_distributions (resource_id, destination_type, destination_name, location_address, gps_lat, gps_lng, quantity_distributed, unit, dispatched_by, contact_officer, distribution_status, notes) VALUES (:resource_id, :destination_type, :destination_name, :location_address, :gps_lat, :gps_lng, :quantity_distributed, :unit, :dispatched_by, :contact_officer, :distribution_status, :notes)");
        foreach ($distributions as $dst) {
            $distStmt->execute($dst);
        }
    }
}

// Run DB initialization
initializeDatabase($pdo);
