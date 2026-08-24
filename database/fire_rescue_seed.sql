-- database/fire_rescue_seed.sql - Initial Mock Data for Fire & Rescue CAD

-- Stations
INSERT OR IGNORE INTO stations (id, name, code, address, lat, lng, coverage_radius_km, phone, active_status) VALUES
(1, 'Delhi Central Fire Station HQ', 'STN-HQ-01', 'Connaught Place Outer Circle, New Delhi', 28.6315, 77.2167, 2.0, '+91-11-23412222', 'Operational'),
(2, 'Okhla Industrial Hazmat Depot', 'STN-IND-04', 'Okhla Phase III Industrial Area, New Delhi', 28.5355, 77.2680, 3.5, '+91-11-26814400', 'Operational'),
(3, 'Rohini Sector-9 Emergency Station', 'STN-NORTH-07', 'Sector 9, Rohini, North West Delhi', 28.7120, 77.1230, 2.8, '+91-11-27551010', 'Operational'),
(4, 'Mayur Vihar Fire Station', 'STN-EAST-11', 'Mayur Vihar Phase 1, East Delhi', 28.6080, 77.2940, 2.5, '+91-11-22753300', 'Operational');

-- Vehicles (Apparatus)
INSERT OR IGNORE INTO vehicles (id, unit_name, type, station_id, status, water_capacity_gal, current_water_gal, foam_capacity_gal, current_foam_gal, crew_count, lat, lng, commander_name) VALUES
(1, 'Engine 41 (Type-1 Heavy Pumper)', 'Pumper', 1, 'In Service', 750, 750, 50, 50, 5, 28.6320, 77.2175, 'Capt. Marcus Vance'),
(2, 'Tower Ladder 12 (100ft Aerial)', 'Ladder', 1, 'In Service', 300, 300, 30, 30, 4, 28.6310, 77.2160, 'Lt. Rajesh Kumar'),
(3, 'Hazmat Tender 3 (Chemical Response)', 'Heavy Rescue', 2, 'In Service', 1000, 1000, 200, 200, 6, 28.5360, 77.2690, 'Capt. Anil Deshmukh'),
(4, 'Rescue Squad 7 (Technical USAR)', 'Heavy Rescue', 3, 'In Service', 500, 500, 50, 50, 5, 28.7115, 77.1240, 'Lt. Vikram Rao'),
(5, 'Engine 28 (Rapid Attack Pumper)', 'Pumper', 4, 'In Service', 600, 600, 40, 40, 4, 28.6075, 77.2935, 'Capt. S. K. Sharma');

-- Firefighters
INSERT OR IGNORE INTO firefighters (id, name, badge_number, rank, station_id, status, phone, certifications) VALUES
(1, 'Marcus Vance', 'FF-8801', 'Captain / Commander', 1, 'On Duty', '+91 98110 44551', 'Incident Command Level 3, Structural Firefighting Master, Hazmat Technician'),
(2, 'Rajesh Kumar', 'FF-8814', 'Lieutenant', 1, 'On Duty', '+91 98110 44552', 'Aerial Ladder Operator, Flashover Survival, Extrication Specialist'),
(3, 'Devendra Singh', 'FF-8822', 'Senior Firefighter', 1, 'On Duty', '+91 98110 44553', 'High-Rise Fire Tactics, SCBA Specialist, Water Supply Engineer'),
(4, 'Anita Roy', 'FF-8835', 'Firefighter / Paramedic', 1, 'On Duty', '+91 98110 44554', 'Emergency Medical Technician (EMT), Burn Trauma Management, Rope Rescue'),
(5, 'Vikram Rao', 'FF-8849', 'Firefighter / Driver', 1, 'On Duty', '+91 98110 44555', 'Heavy Apparatus Driver/Pump Operator, Thermal Imaging Specialist'),
(6, 'Anil Deshmukh', 'FF-7701', 'Hazmat Specialist Captain', 2, 'On Duty', '+91 98220 11223', 'CBRN Specialist, Toxic Gas Scrubbing, Industrial Safety NFPA 472'),
(7, 'Suresh Joshi', 'FF-7708', 'Firefighter', 2, 'On Duty', '+91 98220 11224', 'Foam Induction, SCBA Specialist'),
(8, 'Preeti Verma', 'FF-9912', 'Lieutenant (USAR)', 3, 'On Duty', '+91 98330 99881', 'Urban Search & Rescue (USAR), Trench Collapse, Canine Handler');

-- Hydrants
INSERT OR IGNORE INTO hydrants (id, hydrant_code, lat, lng, pressure_psi, flow_gpm, status) VALUES
(1, 'HYD-DEL-101', 28.6335, 77.2185, 82, 1400, 'Operational'),
(2, 'HYD-DEL-102', 28.6290, 77.2140, 78, 1250, 'Operational'),
(3, 'HYD-DEL-103', 28.6360, 77.2210, 85, 1500, 'Operational'),
(4, 'HYD-DEL-104', 28.5380, 77.2710, 74, 1100, 'Operational'),
(5, 'HYD-DEL-105', 28.5320, 77.2650, 80, 1350, 'Operational'),
(6, 'HYD-DEL-106', 28.7140, 77.1260, 76, 1200, 'Operational'),
(7, 'HYD-DEL-107', 28.6095, 77.2965, 79, 1300, 'Operational');

-- Active Incidents
INSERT OR IGNORE INTO incidents (id, incident_number, caller_name, caller_phone, fire_type, address, lat, lng, trapped_count, notes, status, stage_index, assigned_vehicle_id, assigned_hydrant_id) VALUES
(1, 'INC-2026-0811', 'Arun Mehra (Security Chief)', '+91 98711 22334', 'Structure Fire', 'Tower B, Barakhamba Commercial Complex, Connaught Place', 28.6305, 28.6305, 3, 'Heavy black smoke observed emanating from 4th floor electrical duct. 3 occupants trapped on terrace.', 'Active', 3, 1, 1),
(2, 'INC-2026-0812', 'Priya Nambiar', '+91 98100 88776', 'Chemical / Hazmat / Gas Leak', 'Plot 42, Chemical Packaging Unit, Okhla Industrial Area Ph-III', 28.5370, 77.2700, 0, 'Ammonia cylinder valve burst during transfer. Strong pungent vapor cloud drifting northeast.', 'Active', 2, 3, 4),
(3, 'INC-2026-0810', 'Control Room DEOC', '+91 11 23412222', 'Vehicle & Tanker Crash Fire', 'Outer Ring Road near Mayur Vihar Flyover', 28.6050, 77.2910, 0, 'LPG road tanker overturned with minor undercarriage flame. Cooled with water fog curtain.', 'Resolved', 5, 5, 7);

-- Dispatches
INSERT OR IGNORE INTO dispatches (id, incident_id, vehicle_id, station_id, stage, stage_index, dispatched_at, en_route_at, on_scene_at, closed_at, notes) VALUES
(1, 1, 1, 1, 'En Route', 3, datetime('now', '-18 minutes'), datetime('now', '-12 minutes'), NULL, NULL, 'Engine 41 rolling code 3 with 5 crew members. Hydrant HYD-DEL-101 targeted.'),
(2, 2, 3, 2, 'Primary Units Rolling', 2, datetime('now', '-8 minutes'), NULL, NULL, NULL, 'Hazmat Tender 3 rolling with Level-A encapsulating suits.'),
(3, 3, 5, 4, 'Under Control / Fire Knockdown', 5, datetime('now', '-2 hours'), datetime('now', '-110 minutes'), datetime('now', '-95 minutes'), datetime('now', '-20 minutes'), 'LPG containment achieved. Fire extinguished with foam blanket.');
