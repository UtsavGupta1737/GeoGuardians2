-- database/fire_rescue_schema.sql - Fire & Rescue Tactical CAD & Incident Command Schema

CREATE TABLE IF NOT EXISTS stations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    code TEXT NOT NULL UNIQUE,
    address TEXT NOT NULL,
    lat REAL NOT NULL,
    lng REAL NOT NULL,
    coverage_radius_km REAL DEFAULT 2.5,
    phone TEXT NOT NULL,
    active_status TEXT DEFAULT 'Operational',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS incidents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    incident_number TEXT NOT NULL UNIQUE,
    caller_name TEXT NOT NULL,
    caller_phone TEXT NOT NULL,
    fire_type TEXT NOT NULL,
    address TEXT NOT NULL,
    lat REAL NOT NULL,
    lng REAL NOT NULL,
    trapped_count INTEGER DEFAULT 0,
    notes TEXT,
    status TEXT DEFAULT 'Active',
    stage_index INTEGER DEFAULT 1,
    assigned_vehicle_id INTEGER,
    assigned_hydrant_id INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS vehicles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    unit_name TEXT NOT NULL UNIQUE,
    type TEXT NOT NULL,
    station_id INTEGER,
    status TEXT DEFAULT 'In Service',
    water_capacity_gal INTEGER DEFAULT 750,
    current_water_gal INTEGER DEFAULT 750,
    foam_capacity_gal INTEGER DEFAULT 50,
    current_foam_gal INTEGER DEFAULT 50,
    crew_count INTEGER DEFAULT 5,
    lat REAL,
    lng REAL,
    commander_name TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (station_id) REFERENCES stations(id)
);

CREATE TABLE IF NOT EXISTS firefighters (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    badge_number TEXT NOT NULL UNIQUE,
    rank TEXT NOT NULL,
    station_id INTEGER,
    status TEXT DEFAULT 'On Duty',
    phone TEXT,
    certifications TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (station_id) REFERENCES stations(id)
);

CREATE TABLE IF NOT EXISTS hydrants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    hydrant_code TEXT NOT NULL UNIQUE,
    lat REAL NOT NULL,
    lng REAL NOT NULL,
    pressure_psi INTEGER DEFAULT 75,
    flow_gpm INTEGER DEFAULT 1250,
    status TEXT DEFAULT 'Operational',
    last_inspected DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS dispatches (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    incident_id INTEGER NOT NULL,
    vehicle_id INTEGER NOT NULL,
    station_id INTEGER,
    stage TEXT DEFAULT 'Alarm Paged & Geocoded',
    stage_index INTEGER DEFAULT 1,
    dispatched_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    en_route_at DATETIME,
    on_scene_at DATETIME,
    closed_at DATETIME,
    notes TEXT,
    FOREIGN KEY (incident_id) REFERENCES incidents(id),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id)
);
