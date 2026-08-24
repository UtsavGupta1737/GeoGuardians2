<?php
// dashboard.php - DisasterSafe Tactical Geospatial GIS Command Center
define('PAGE_TITLE', 'Tactical GIS Map');
require_once __DIR__ . '/auth.php';

requireLogin();
$currentUser = getCurrentUser($pdo);

// Role & Permission Checks
$isSuperAdmin = isSuperAdmin($currentUser);
$hasSosAccess = $isSuperAdmin || hasPermission($currentUser, 'access_sos_database');

// Live Aggregate Counts from SQLite Database
$totalSosCount = (int) $pdo->query("SELECT COUNT(*) FROM emergency_sos")->fetchColumn();
$activeRescuesCount = (int) $pdo->query("SELECT COUNT(*) FROM emergency_sos WHERE status != 'Resolved'")->fetchColumn();
$safeResolvedCount = (int) $pdo->query("SELECT COUNT(*) FROM emergency_sos WHERE status = 'Resolved'")->fetchColumn();
$activeDeploymentsCount = (int) $pdo->query("SELECT COUNT(*) FROM police_deployments WHERE status = 'Active'")->fetchColumn();
$openTasksCount = (int) $pdo->query("SELECT COUNT(*) FROM volunteer_tasks WHERE status != 'Completed'")->fetchColumn();
$criticalPriorityCount = (int) $pdo->query("SELECT COUNT(*) FROM emergency_sos WHERE priority = 'Critical' OR status = 'Pending'")->fetchColumn();

// Fetch live SOS calls with coordinates
$sosList = $pdo->query("SELECT id, sender_name, sender_phone, emergency_type, priority, status, blood_type, gps_lat, gps_lng, persons_count, medical_needs, dispatch_agency, message FROM emergency_sos ORDER BY id DESC")->fetchAll();

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div class="flex-1 flex flex-col min-w-0 bg-[#0a0f1d] h-screen overflow-hidden">
    <?php require_once __DIR__ . '/navbar.php'; ?>

    <main class="flex-1 flex flex-col p-3 sm:p-4 lg:p-6 space-y-3.5 overflow-hidden">
        
        <!-- COMPACT TOP METRICS HUD STRIP -->
        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5 shrink-0" aria-label="Incident Statistics">
            <a href="sos.php" class="stat-card-accent p-2.5 sm:p-3 rounded-xl border-t-2 border-t-[#ba1a1a] shadow-md flex items-center justify-between hover:border-rose-500 transition-all">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total SOS</p>
                    <h3 class="text-lg sm:text-xl font-extrabold text-white leading-tight"><?= $totalSosCount ?></h3>
                </div>
                <div class="w-8 h-8 rounded-lg bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400">
                    <i class="fa-solid fa-tower-broadcast text-xs"></i>
                </div>
            </a>

            <a href="sos.php?status=active" class="stat-card-accent p-2.5 sm:p-3 rounded-xl border-t-2 border-t-[#d97706] shadow-md flex items-center justify-between hover:border-amber-500 transition-all">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Active Rescues</p>
                    <h3 class="text-lg sm:text-xl font-extrabold text-amber-400 leading-tight"><?= $activeRescuesCount ?></h3>
                </div>
                <div class="w-8 h-8 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                    <i class="fa-solid fa-person-running text-xs"></i>
                </div>
            </a>

            <a href="sos.php?status=Resolved" class="stat-card-accent p-2.5 sm:p-3 rounded-xl border-t-2 border-t-[#16a34a] shadow-md flex items-center justify-between hover:border-emerald-500 transition-all">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Safe / Rescued</p>
                    <h3 class="text-lg sm:text-xl font-extrabold text-emerald-400 leading-tight"><?= $safeResolvedCount ?></h3>
                </div>
                <div class="w-8 h-8 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                    <i class="fa-solid fa-circle-check text-xs"></i>
                </div>
            </a>

            <a href="tasks.php" class="stat-card-accent p-2.5 sm:p-3 rounded-xl border-t-2 border-t-emerald-500 shadow-md flex items-center justify-between hover:border-emerald-500 transition-all">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Missions Open</p>
                    <h3 class="text-lg sm:text-xl font-extrabold text-emerald-400 leading-tight"><?= $openTasksCount ?></h3>
                </div>
                <div class="w-8 h-8 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                    <i class="fa-solid fa-list-check text-xs"></i>
                </div>
            </a>

            <a href="deployments.php" class="stat-card-accent p-2.5 sm:p-3 rounded-xl border-t-2 border-t-[#6366f1] shadow-md flex items-center justify-between hover:border-indigo-500 transition-all">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Deployed Squads</p>
                    <h3 class="text-lg sm:text-xl font-extrabold text-indigo-400 leading-tight"><?= $activeDeploymentsCount ?></h3>
                </div>
                <div class="w-8 h-8 rounded-lg bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400">
                    <i class="fa-solid fa-shield-halved text-xs"></i>
                </div>
            </a>

            <a href="sos.php?priority=Critical" class="stat-card-accent p-2.5 sm:p-3 rounded-xl border-t-2 border-t-[#dc2626] shadow-md flex items-center justify-between hover:border-rose-600 transition-all">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Critical Priority</p>
                    <h3 class="text-lg sm:text-xl font-extrabold text-rose-500 leading-tight"><?= $criticalPriorityCount ?></h3>
                </div>
                <div class="w-8 h-8 rounded-lg bg-rose-600/10 border border-rose-600/20 flex items-center justify-center text-rose-500 animate-pulse">
                    <i class="fa-solid fa-bell text-xs"></i>
                </div>
            </a>
        </section>

        <!-- IMMERSIVE, EXPANSIVE FULL-VIEW TACTICAL GIS MAP -->
        <section id="mapWrapper" class="flex-1 w-full glass-panel rounded-2xl border border-[#243049] flex flex-col relative shadow-2xl overflow-hidden min-h-[480px]">
            
            <!-- Map Top Control Bar -->
            <div class="h-12 px-4 bg-[#0c1326]/95 backdrop-blur-md border-b border-[#243049] flex items-center justify-between shrink-0 z-20">
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2">
                        <span class="live-dot"></span>
                        <h2 class="text-xs sm:text-sm font-extrabold text-white tracking-tight flex items-center gap-2">
                            <span>Delhi-NCR Tactical Crisis Grid</span>
                        </h2>
                    </div>
                    <span class="hidden md:inline text-[10px] font-mono text-indigo-400 bg-indigo-500/10 border border-indigo-500/20 px-2 py-0.5 rounded">
                        GPS: 28.6139° N, 77.2090° E
                    </span>
                </div>

                <!-- Right Quick Controls -->
                <div class="flex items-center gap-2 text-xs">
                    <button type="button" onclick="setMapTile('dark')" id="btnTileDark" class="px-2.5 py-1 rounded-lg bg-indigo-600 text-white font-bold text-[11px] shadow-sm transition-all">
                        <i class="fa-solid fa-moon mr-1"></i> Dark
                    </button>
                    <button type="button" onclick="setMapTile('satellite')" id="btnTileSat" class="px-2.5 py-1 rounded-lg bg-[#11192e] hover:bg-slate-700 text-slate-300 font-semibold text-[11px] border border-[#243049] transition-all">
                        <i class="fa-solid fa-satellite mr-1"></i> Satellite
                    </button>
                    <button type="button" onclick="setMapTile('street')" id="btnTileStreet" class="px-2.5 py-1 rounded-lg bg-[#11192e] hover:bg-slate-700 text-slate-300 font-semibold text-[11px] border border-[#243049] transition-all">
                        <i class="fa-solid fa-map mr-1"></i> Streets
                    </button>

                    <div class="h-4 w-[1px] bg-[#243049] mx-1"></div>

                    <button type="button" onclick="resetMapCenter()" class="px-2.5 py-1 rounded-lg bg-indigo-600/20 hover:bg-indigo-600/40 text-indigo-300 border border-indigo-500/30 text-[11px] font-bold transition-all" title="Recenter on Delhi-NCR">
                        <i class="fa-solid fa-crosshairs mr-1"></i> Center
                    </button>
                    
                    <button type="button" onclick="toggleFullscreenMap()" class="p-1.5 rounded-lg bg-[#11192e] hover:bg-slate-700 text-slate-300 border border-[#243049] transition-all" title="Toggle Maximize">
                        <i class="fa-solid fa-expand text-xs"></i>
                    </button>
                </div>
            </div>

            <!-- FLOATING INTERACTIVE LAYER TOGGLE PILLS (TOP-LEFT OVERLAY) -->
            <div id="layerFilterBox" class="absolute top-14 left-3 sm:left-4 z-[1000] bg-[#0c1326]/95 backdrop-blur-md p-2 rounded-xl border border-[#243049] shadow-2xl flex flex-wrap items-center gap-2 max-w-[calc(100%-2rem)]">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider px-1 hidden sm:inline">Layers:</span>
                
                <!-- SOS Calls Toggle -->
                <button type="button" id="pill_sos" onclick="toggleLayer('sos')" class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-[#11192e] border border-rose-500/50 hover:border-rose-400 text-xs font-bold text-rose-300 transition-all select-none shadow-sm cursor-pointer">
                    <input type="checkbox" id="chk_sos" checked class="accent-rose-500 rounded pointer-events-none">
                    <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                    <span>SOS Alerts (<?= count($sosList) ?>)</span>
                </button>

                <!-- Police Toggle -->
                <button type="button" id="pill_police" onclick="toggleLayer('police')" class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-[#11192e] border border-blue-500/50 hover:border-blue-400 text-xs font-bold text-blue-300 transition-all select-none shadow-sm cursor-pointer">
                    <input type="checkbox" id="chk_police" checked class="accent-blue-500 rounded pointer-events-none">
                    <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                    <span>Police Units</span>
                </button>

                <!-- Fire & Hazmat Toggle -->
                <button type="button" id="pill_fire" onclick="toggleLayer('fire')" class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-[#11192e] border border-red-500/50 hover:border-red-400 text-xs font-bold text-red-300 transition-all select-none shadow-sm cursor-pointer">
                    <input type="checkbox" id="chk_fire" checked class="accent-red-500 rounded pointer-events-none">
                    <span class="w-2 h-2 rounded-full bg-red-500"></span>
                    <span>Fire & Hazmat</span>
                </button>

                <!-- EMS Ambulances Toggle -->
                <button type="button" id="pill_ems" onclick="toggleLayer('ems')" class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-[#11192e] border border-teal-500/50 hover:border-teal-400 text-xs font-bold text-teal-300 transition-all select-none shadow-sm cursor-pointer">
                    <input type="checkbox" id="chk_ems" checked class="accent-teal-500 rounded pointer-events-none">
                    <span class="w-2 h-2 rounded-full bg-teal-400"></span>
                    <span>Ambulance / EMS</span>
                </button>

                <!-- Camps & Hospitals Toggle -->
                <button type="button" id="pill_camps" onclick="toggleLayer('camps')" class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-[#11192e] border border-amber-500/50 hover:border-amber-400 text-xs font-bold text-amber-300 transition-all select-none shadow-sm cursor-pointer">
                    <input type="checkbox" id="chk_camps" checked class="accent-amber-500 rounded pointer-events-none">
                    <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                    <span>Hospitals & Shelters</span>
                </button>
            </div>

            <!-- FLOATING LIVE INCIDENT JUMP-TO HUD (BOTTOM-RIGHT OVERLAY) -->
            <div id="quickJumpBox" class="absolute bottom-12 right-3 sm:right-4 z-[1000] bg-[#0c1326]/90 backdrop-blur-md p-3 rounded-2xl border border-[#243049] shadow-2xl max-w-xs hidden sm:block">
                <div class="flex items-center justify-between mb-2 pb-1.5 border-b border-[#243049]">
                    <span class="text-[11px] font-bold text-white flex items-center gap-1.5">
                        <i class="fa-solid fa-crosshairs text-indigo-400"></i> Tactical Quick Jump
                    </span>
                    <span class="text-[9px] font-mono text-slate-400">NCR Hub</span>
                </div>
                <div class="space-y-1.5 text-xs">
                    <button type="button" onclick="flyToLocation(28.6050, 77.2950, 15)" class="w-full p-1.5 rounded-lg bg-[#11192e] hover:bg-slate-800 text-left flex items-center justify-between text-slate-300 hover:text-white transition-colors">
                        <span>🌊 Mayur Vihar Floodplain</span>
                        <span class="text-[9px] font-bold text-rose-400">Critical SOS</span>
                    </button>
                    <button type="button" onclick="flyToLocation(28.6750, 77.3650, 15)" class="w-full p-1.5 rounded-lg bg-[#11192e] hover:bg-slate-800 text-left flex items-center justify-between text-slate-300 hover:text-white transition-colors">
                        <span>🔥 Sahibabad Hazmat Blaze</span>
                        <span class="text-[9px] font-bold text-amber-400">Fire Squad</span>
                    </button>
                    <button type="button" onclick="flyToLocation(28.4950, 77.0890, 15)" class="w-full p-1.5 rounded-lg bg-[#11192e] hover:bg-slate-800 text-left flex items-center justify-between text-slate-300 hover:text-white transition-colors">
                        <span>🚗 Gurugram Underpass</span>
                        <span class="text-[9px] font-bold text-blue-400">Water Rescue</span>
                    </button>
                    <button type="button" onclick="flyToLocation(28.5672, 77.2100, 15)" class="w-full p-1.5 rounded-lg bg-[#11192e] hover:bg-slate-800 text-left flex items-center justify-between text-slate-300 hover:text-white transition-colors">
                        <span>🏥 AIIMS Trauma Center</span>
                        <span class="text-[9px] font-bold text-emerald-400">38 Beds Open</span>
                    </button>
                </div>
            </div>

            <!-- Leaflet Map Container -->
            <div id="gisCommandMap" class="flex-1 w-full h-full relative z-10"></div>

            <!-- Bottom Map Legend Bar -->
            <div class="h-10 px-4 bg-[#0c1326] border-t border-[#243049] flex items-center justify-between text-xs text-slate-400 shrink-0 z-20">
                <div class="flex items-center gap-3 sm:gap-5 overflow-x-auto py-1">
                    <span class="flex items-center gap-1.5 font-bold text-rose-400 whitespace-nowrap"><i class="w-2.5 h-2.5 rounded-full bg-[#ba1a1a] animate-pulse inline-block"></i> SOS Alert</span>
                    <span class="flex items-center gap-1.5 font-medium whitespace-nowrap"><i class="w-2.5 h-2.5 rounded-full bg-[#2563eb] inline-block"></i> Police Squad</span>
                    <span class="flex items-center gap-1.5 font-medium whitespace-nowrap"><i class="w-2.5 h-2.5 rounded-full bg-[#dc2626] inline-block"></i> Fire Unit</span>
                    <span class="flex items-center gap-1.5 font-medium whitespace-nowrap"><i class="w-2.5 h-2.5 rounded-full bg-[#0d9488] inline-block"></i> Ambulance</span>
                    <span class="flex items-center gap-1.5 font-medium whitespace-nowrap"><i class="w-2.5 h-2.5 rounded-full bg-[#16a34a] inline-block"></i> Hospital</span>
                    <span class="flex items-center gap-1.5 font-medium whitespace-nowrap"><i class="w-2.5 h-2.5 rounded-full bg-[#eab308] inline-block"></i> Relief Camp</span>
                </div>
                <div class="text-[11px] font-mono text-slate-500 hidden md:block whitespace-nowrap">
                    Active Leaflet Engine • WGS84 Datum
                </div>
            </div>

        </section>

    </main>
</div>

<!-- LEAFLET GIS MAP ENGINE SCRIPT -->
<script>
let map = null;
let currentTileLayer = null;
const mapCenter = [28.6139, 77.2090]; // Delhi-NCR Center

// Layer Groups
const layerSos = L.layerGroup();
const layerPolice = L.layerGroup();
const layerFire = L.layerGroup();
const layerEms = L.layerGroup();
const layerCamps = L.layerGroup();

const tileUrls = {
    dark: 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
    satellite: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
    street: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'
};

document.addEventListener('DOMContentLoaded', () => {
    map = L.map('gisCommandMap', {
        zoomControl: false,
        attributionControl: false
    }).setView(mapCenter, 12);

    // Initial Tile Layer (Dark Tactical)
    setMapTile('dark');

    // Add all layer groups to map
    layerSos.addTo(map);
    layerPolice.addTo(map);
    layerFire.addTo(map);
    layerEms.addTo(map);
    layerCamps.addTo(map);

    // Disable click propagation on custom overlay boxes so Leaflet does not swallow clicks
    const filterBox = document.getElementById('layerFilterBox');
    if (filterBox) {
        L.DomEvent.disableClickPropagation(filterBox);
        L.DomEvent.disableScrollPropagation(filterBox);
    }
    const jumpBox = document.getElementById('quickJumpBox');
    if (jumpBox) {
        L.DomEvent.disableClickPropagation(jumpBox);
        L.DomEvent.disableScrollPropagation(jumpBox);
    }

    // 1. POPULATE SOS DISTRESS RADAR PINS (LIVE FROM DATABASE)
    const sosIncidents = <?= json_encode($sosList) ?>;

    sosIncidents.forEach(sos => {
        const radarIcon = L.divIcon({
            className: 'custom-radar-icon',
            html: '<div class="radar-pulse-marker">!</div>',
            iconSize: [26, 26]
        });

        L.marker([sos.gps_lat, sos.gps_lng], { icon: radarIcon }).addTo(layerSos)
            .bindPopup(`
                <div style="color:#0f172a; font-family:'Inter', sans-serif; font-size:12px; min-width:220px; line-height:1.4;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                        <strong style="color:#ba1a1a; font-size:12px; font-weight:800;">🚨 SOS DISTRESS BEACON</strong>
                        <span style="font-size:10px; background:#fee2e2; color:#991b1b; padding:1px 5px; border-radius:4px; font-weight:700;">${(sos.priority || 'CRITICAL').toUpperCase()}</span>
                    </div>
                    <b style="font-size:13px; color:#1e293b;">${sos.sender_name}</b><br/>
                    <span style="color:#2563eb; font-weight:600;">${sos.emergency_type}</span> • <span style="color:#64748b;">${sos.persons_count || '1 - 4'} Persons</span><br/>
                    <p style="margin:4px 0; color:#334155; font-family:monospace; font-size:11px;"><b>GPS:</b> ${Number(sos.gps_lat).toFixed(4)}°, ${Number(sos.gps_lng).toFixed(4)}°</p>
                    ${sos.dispatch_agency ? `<p style="margin:2px 0; color:#0369a1; font-weight:700; font-size:11px;">🛡️ Assigned: ${sos.dispatch_agency}</p>` : ''}
                    ${sos.message ? `<p style="margin:0 0 6px; color:#64748b; font-size:11px;"><i>${sos.message}</i></p>` : ''}
                    <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #e2e8f0; padding-top:6px;">
                        <span style="color:#e11d48; font-weight:700;">Blood: ${sos.blood_type || 'Unknown'}</span>
                        <a href="sos.php?id=${sos.id}" style="background:#2563eb; color:#fff; text-decoration:none; padding:3px 8px; border-radius:5px; font-size:10px; font-weight:700;">Triage Dossier →</a>
                    </div>
                </div>
            `);
    });

    // 2. POLICE TACTICAL SQUADS
    const policeSquads = [
        { lat: 28.6250, lng: 77.2600, callsign: "Delta-NCR-1 (Delhi Police)", mission: "ITO Bridge & Geeta Colony Flood Cordon", officers: 18, radio: "VHF Ch-4" },
        { lat: 28.6650, lng: 77.2300, callsign: "Echo-Patrol-3 (Delhi Police)", mission: "Kashmere Gate ISBT Convoy Escort", officers: 12, radio: "VHF Ch-2" },
        { lat: 28.4500, lng: 77.0400, callsign: "Alpha-Gurgaon-2 (Haryana Police)", mission: "Hero Honda Chowk Underpass Blockage", officers: 14, radio: "VHF Ch-7" },
        { lat: 28.6720, lng: 77.3680, callsign: "Charlie-Ghaziabad-1 (UP Police)", mission: "Mohan Nagar Hazmat Perimeter", officers: 15, radio: "VHF Ch-1" }
    ];

    policeSquads.forEach(p => {
        L.circleMarker([p.lat, p.lng], {
            radius: 8,
            fillColor: '#2563eb',
            color: '#ffffff',
            weight: 2,
            fillOpacity: 0.95
        }).addTo(layerPolice)
        .bindPopup(`
            <div style="color:#0f172a; font-family:'Inter', sans-serif; font-size:12px; min-width:180px;">
                <strong style="color:#2563eb; font-size:12px;">🚓 ${p.callsign}</strong><br/>
                <b>Mission:</b> ${p.mission}<br/>
                <span>Officers: <b>${p.officers} On-Duty</b></span><br/>
                <span style="color:#64748b; font-size:10px;">Radio: ${p.radio}</span>
            </div>
        `);
    });

    // 3. FIRE & HAZMAT SQUADS
    const fireSquads = [
        { lat: 28.6780, lng: 77.3620, callsign: "Fire & Rescue Squad 2", mission: "Sahibabad Chemical Solvent Suppression", tenders: 6 },
        { lat: 28.6290, lng: 77.3750, callsign: "Noida Fire Station Unit 4", mission: "Sector 62 Structural Collapse Standby", tenders: 3 }
    ];

    fireSquads.forEach(f => {
        L.circleMarker([f.lat, f.lng], {
            radius: 8,
            fillColor: '#dc2626',
            color: '#ffffff',
            weight: 2,
            fillOpacity: 0.95
        }).addTo(layerFire)
        .bindPopup(`
            <div style="color:#0f172a; font-family:'Inter', sans-serif; font-size:12px; min-width:180px;">
                <strong style="color:#dc2626; font-size:12px;">🚒 ${f.callsign}</strong><br/>
                <b>Mission:</b> ${f.mission}<br/>
                <span>Tenders Active: <b>${f.tenders} Units</b></span>
            </div>
        `);
    });

    // 4. AMBULANCE & EMS TEAMS
    const emsUnits = [
        { lat: 28.5720, lng: 77.2150, callsign: "ALS Ambulance Unit 1 (AIIMS)", status: "En-Route Mayur Vihar", beds: "ICU On-Board" },
        { lat: 28.4420, lng: 77.0450, callsign: "Medanta Trauma Response 3", status: "On-Standby DLF Phase 3", beds: "Paramedic Ready" }
    ];

    emsUnits.forEach(e => {
        L.circleMarker([e.lat, e.lng], {
            radius: 8,
            fillColor: '#0d9488',
            color: '#ffffff',
            weight: 2,
            fillOpacity: 0.95
        }).addTo(layerEms)
        .bindPopup(`
            <div style="color:#0f172a; font-family:'Inter', sans-serif; font-size:12px; min-width:180px;">
                <strong style="color:#0d9488; font-size:12px;">🚑 ${e.callsign}</strong><br/>
                <b>Status:</b> ${e.status}<br/>
                <span>Equipment: <b>${e.beds}</b></span>
            </div>
        `);
    });

    // 5. HOSPITALS & RELIEF SHELTERS
    const facilities = [
        { lat: 28.5672, lng: 77.2100, name: "AIIMS New Delhi Trauma Center", capacity: "38 Trauma Beds Open", type: "hospital" },
        { lat: 28.5700, lng: 77.2065, name: "Safdarjung Emergency Hospital", capacity: "45 ICU Beds Open", type: "hospital" },
        { lat: 28.5200, lng: 77.3680, name: "Jaypee Hospital Sector 128 Noida", capacity: "28 Emergency Beds Open", type: "hospital" },
        { lat: 28.4390, lng: 77.0420, name: "Medanta The Medicity Gurugram", capacity: "32 Critical Beds Open", type: "hospital" },
        { lat: 28.5800, lng: 77.2150, name: "Thyagaraj Stadium Relief Camp", capacity: "180 Evacuee Slots", type: "shelter" },
        { lat: 28.6080, lng: 77.2980, name: "Mayur Vihar Flood Relief Tent City", capacity: "300 Evacuee Slots", type: "shelter" },
        { lat: 28.5920, lng: 77.3400, name: "Noida Stadium Sector 21 Shelter", capacity: "240 Evacuee Slots", type: "shelter" },
        { lat: 28.4280, lng: 77.0320, name: "Tau Devi Lal Stadium Shelter Gurugram", capacity: "150 Evacuee Slots", type: "shelter" }
    ];

    facilities.forEach(fac => {
        const isHosp = fac.type === 'hospital';
        const color = isHosp ? '#16a34a' : '#eab308';
        L.circleMarker([fac.lat, fac.lng], {
            radius: 8,
            fillColor: color,
            color: '#ffffff',
            weight: 2,
            fillOpacity: 0.95
        }).addTo(layerCamps)
        .bindPopup(`
            <div style="color:#0f172a; font-family:'Inter', sans-serif; font-size:12px; min-width:190px;">
                <strong style="color:${color}; font-size:12px;">${isHosp ? '🏥 Hospital' : '🏕️ Relief Shelter'}</strong><br/>
                <b>${fac.name}</b><br/>
                <span style="color:#1e293b; font-weight:700;">Capacity: ${fac.capacity}</span><br/>
                <span style="color:#64748b; font-size:10px;">Emergency triage & food distribution operational</span>
            </div>
        `);
    });
});

// Switch Base Tile Map
function setMapTile(type) {
    if (currentTileLayer) {
        map.removeLayer(currentTileLayer);
    }
    currentTileLayer = L.tileLayer(tileUrls[type], {
        maxZoom: 19
    }).addTo(map);

    // Update active button state
    ['btnTileDark', 'btnTileSat', 'btnTileStreet'].forEach(btnId => {
        const el = document.getElementById(btnId);
        if (el) {
            el.className = 'px-2.5 py-1 rounded-lg bg-[#11192e] hover:bg-slate-700 text-slate-300 font-semibold text-[11px] border border-[#243049] transition-all';
        }
    });

    const activeMap = { dark: 'btnTileDark', satellite: 'btnTileSat', street: 'btnTileStreet' };
    const activeEl = document.getElementById(activeMap[type]);
    if (activeEl) {
        activeEl.className = 'px-2.5 py-1 rounded-lg bg-indigo-600 text-white font-bold text-[11px] shadow-sm transition-all';
    }
}

// Layer active states
const layerStates = {
    sos: true,
    police: true,
    fire: true,
    ems: true,
    camps: true
};

// Layer Toggle Helper
function toggleLayer(layerName) {
    if (!map) return;
    layerStates[layerName] = !layerStates[layerName];
    const isVisible = layerStates[layerName];

    const groups = {
        sos: layerSos,
        police: layerPolice,
        fire: layerFire,
        ems: layerEms,
        camps: layerCamps
    };

    const targetGroup = groups[layerName];
    if (targetGroup) {
        if (isVisible) {
            if (!map.hasLayer(targetGroup)) map.addLayer(targetGroup);
        } else {
            if (map.hasLayer(targetGroup)) map.removeLayer(targetGroup);
        }
    }

    // Update checkbox
    const chk = document.getElementById('chk_' + layerName);
    if (chk) chk.checked = isVisible;

    // Update Pill Button Style
    const pill = document.getElementById('pill_' + layerName);
    if (pill) {
        if (isVisible) {
            pill.style.opacity = '1';
            pill.style.filter = 'none';
        } else {
            pill.style.opacity = '0.4';
            pill.style.filter = 'grayscale(0.8)';
        }
    }
}

// Reset Map Center
function resetMapCenter() {
    if (map) {
        map.setView(mapCenter, 12, { animate: true });
    }
}

// Fly to specific coordinates
function flyToLocation(lat, lng, zoom = 15) {
    if (map) {
        map.flyTo([lat, lng], zoom, {
            animate: true,
            duration: 1.2
        });
    }
}

// Toggle Maximize Map View
function toggleFullscreenMap() {
    const el = document.getElementById('mapWrapper');
    if (!document.fullscreenElement) {
        el.requestFullscreen().catch(err => {
            alert(`Error attempting to enable full-screen mode: ${err.message}`);
        });
    } else {
        document.exitFullscreen();
    }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
