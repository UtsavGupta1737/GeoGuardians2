<?php
// dashboard.php - DisasterSafe Tactical Geospatial GIS Command Center (Government Theme)
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

<div class="flex-1 flex flex-col min-w-0 bg-[#f8fafc] h-screen overflow-hidden">
    <?php require_once __DIR__ . '/navbar.php'; ?>

    <main class="flex-1 flex flex-col p-3 sm:p-4 lg:p-6 space-y-3.5 overflow-hidden">
        
        <!-- COMPACT TOP METRICS HUD STRIP - MONOCHROME BLUE -->
        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5 shrink-0" aria-label="Incident Statistics">
            <a href="sos.php" class="bg-white border border-slate-200 p-2.5 sm:p-3 shadow-2xs flex items-center justify-between hover:border-blue-300 hover:shadow-xs transition-all">
                <div>
                    <p class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider mono">Total SOS</p>
                    <h3 class="text-lg sm:text-xl font-black text-[#0F172A] leading-tight"><?= $totalSosCount ?></h3>
                </div>
                <div class="w-9 h-9 bg-[#EFF6FF] border border-blue-200 flex items-center justify-center text-[#2563EB] shrink-0">
                    <i class="fa-solid fa-tower-broadcast text-xs"></i>
                </div>
            </a>

            <a href="sos.php?status=active" class="bg-white border border-slate-200 p-2.5 sm:p-3 shadow-2xs flex items-center justify-between hover:border-blue-300 hover:shadow-xs transition-all">
                <div>
                    <p class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider mono">Active Rescues</p>
                    <h3 class="text-lg sm:text-xl font-black text-[#0F172A] leading-tight"><?= $activeRescuesCount ?></h3>
                </div>
                <div class="w-9 h-9 bg-[#EFF6FF] border border-blue-200 flex items-center justify-center text-[#2563EB] shrink-0">
                    <i class="fa-solid fa-person-running text-xs"></i>
                </div>
            </a>

            <a href="sos.php?status=Resolved" class="bg-white border border-slate-200 p-2.5 sm:p-3 shadow-2xs flex items-center justify-between hover:border-blue-300 hover:shadow-xs transition-all">
                <div>
                    <p class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider mono">Safe / Rescued</p>
                    <h3 class="text-lg sm:text-xl font-black text-[#0F172A] leading-tight"><?= $safeResolvedCount ?></h3>
                </div>
                <div class="w-9 h-9 bg-[#EFF6FF] border border-blue-200 flex items-center justify-center text-[#2563EB] shrink-0">
                    <i class="fa-solid fa-circle-check text-xs"></i>
                </div>
            </a>

            <a href="tasks.php" class="bg-white border border-slate-200 p-2.5 sm:p-3 shadow-2xs flex items-center justify-between hover:border-blue-300 hover:shadow-xs transition-all">
                <div>
                    <p class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider mono">Missions Open</p>
                    <h3 class="text-lg sm:text-xl font-black text-[#0F172A] leading-tight"><?= $openTasksCount ?></h3>
                </div>
                <div class="w-9 h-9 bg-[#EFF6FF] border border-blue-200 flex items-center justify-center text-[#2563EB] shrink-0">
                    <i class="fa-solid fa-list-check text-xs"></i>
                </div>
            </a>

            <a href="deployments.php" class="bg-white border border-slate-200 p-2.5 sm:p-3 shadow-2xs flex items-center justify-between hover:border-blue-300 hover:shadow-xs transition-all">
                <div>
                    <p class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider mono">Deployed Squads</p>
                    <h3 class="text-lg sm:text-xl font-black text-[#0F172A] leading-tight"><?= $activeDeploymentsCount ?></h3>
                </div>
                <div class="w-9 h-9 bg-[#EFF6FF] border border-blue-200 flex items-center justify-center text-[#2563EB] shrink-0">
                    <i class="fa-solid fa-shield-halved text-xs"></i>
                </div>
            </a>

            <a href="sos.php?priority=Critical" class="bg-white border border-slate-200 p-2.5 sm:p-3 shadow-2xs flex items-center justify-between hover:border-blue-300 hover:shadow-xs transition-all">
                <div>
                    <p class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider mono">Critical Priority</p>
                    <h3 class="text-lg sm:text-xl font-black text-[#0F172A] leading-tight"><?= $criticalPriorityCount ?></h3>
                </div>
                <div class="w-9 h-9 bg-[#EFF6FF] border border-blue-200 flex items-center justify-center text-[#2563EB] shrink-0">
                    <i class="fa-solid fa-bell text-xs"></i>
                </div>
            </a>
        </section>

        <!-- SPLIT LAYOUT: MAP LEFT + DETAIL PANEL RIGHT -->
        <section id="mapSection" class="flex-1 w-full flex gap-0 border border-slate-200 bg-white shadow-2xs overflow-hidden min-h-0" style="height: calc(100vh - 200px);">
            
            <!-- LEFT: MAP COLUMN -->
            <div class="flex-1 flex flex-col min-w-0 relative">
                
                <!-- Map Top Control Bar -->
                <div class="h-11 px-4 bg-white border-b border-slate-200 flex items-center justify-between shrink-0 z-20">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-2">
                            <span class="live-dot"></span>
                            <h2 class="text-xs sm:text-sm font-extrabold text-slate-900 tracking-tight">Delhi-NCR Tactical Crisis Grid</h2>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 text-xs">
                        <button type="button" onclick="setMapTile('street')" id="btnTileStreet" class="px-2.5 py-1 bg-[#1d63d8] text-white font-bold text-[11px] transition-all cursor-pointer">
                            <i class="fa-solid fa-map mr-1"></i> Streets
                        </button>
                        <button type="button" onclick="setMapTile('satellite')" id="btnTileSat" class="px-2.5 py-1 bg-slate-50 hover:bg-slate-100 text-slate-700 font-bold text-[11px] border border-slate-200 transition-all cursor-pointer">
                            <i class="fa-solid fa-satellite mr-1"></i> Satellite
                        </button>
                        <button type="button" onclick="setMapTile('dark')" id="btnTileDark" class="px-2.5 py-1 bg-slate-50 hover:bg-slate-100 text-slate-700 font-bold text-[11px] border border-slate-200 transition-all cursor-pointer">
                            <i class="fa-solid fa-moon mr-1"></i> Dark
                        </button>
                        <div class="h-4 w-[1px] bg-slate-200 mx-1"></div>
                        <button type="button" onclick="resetMapCenter()" class="px-2.5 py-1 bg-blue-50 hover:bg-blue-100 text-blue-800 border border-blue-200 text-[11px] font-bold transition-all cursor-pointer" title="Recenter on Delhi-NCR">
                            <i class="fa-solid fa-crosshairs mr-1"></i> Center
                        </button>
                        <button type="button" onclick="toggleFullscreenMap()" class="p-1.5 bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 transition-all cursor-pointer" title="Toggle Maximize">
                            <i class="fa-solid fa-expand text-xs"></i>
                        </button>
                    </div>
                </div>

                <!-- FLOATING LAYER TOGGLE PILLS -->
                <div id="layerFilterBox" class="absolute top-14 left-3 sm:left-4 z-[1000] bg-white/95 backdrop-blur-md p-2 border border-slate-200 shadow-xl flex flex-wrap items-center gap-2 max-w-[calc(100%-2rem)]">
                    <span class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider px-1 hidden sm:inline mono">Layers:</span>
                    <button type="button" id="pill_sos" onclick="toggleLayer('sos')" class="flex items-center gap-1.5 px-2.5 py-1 bg-red-50 border border-red-200 text-xs font-bold text-red-800 transition-all select-none shadow-2xs cursor-pointer">
                        <input type="checkbox" id="chk_sos" checked class="accent-red-600 rounded pointer-events-none">
                        <span class="w-2 h-2 bg-red-600 animate-pulse inline-block"></span>
                        <span>SOS Alerts (<?= count($sosList) ?>)</span>
                    </button>
                    <button type="button" id="pill_police" onclick="toggleLayer('police')" class="flex items-center gap-1.5 px-2.5 py-1 bg-blue-50 border border-blue-200 text-xs font-bold text-blue-800 transition-all select-none shadow-2xs cursor-pointer">
                        <input type="checkbox" id="chk_police" checked class="accent-blue-600 rounded pointer-events-none">
                        <span class="w-2 h-2 bg-blue-600 inline-block"></span>
                        <span>Police Units</span>
                    </button>
                    <button type="button" id="pill_fire" onclick="toggleLayer('fire')" class="flex items-center gap-1.5 px-2.5 py-1 bg-orange-50 border border-orange-200 text-xs font-bold text-orange-800 transition-all select-none shadow-2xs cursor-pointer">
                        <input type="checkbox" id="chk_fire" checked class="accent-orange-600 rounded pointer-events-none">
                        <span class="w-2 h-2 bg-orange-600 inline-block"></span>
                        <span>Fire &amp; Hazmat</span>
                    </button>
                    <button type="button" id="pill_ems" onclick="toggleLayer('ems')" class="flex items-center gap-1.5 px-2.5 py-1 bg-teal-50 border border-teal-200 text-xs font-bold text-teal-800 transition-all select-none shadow-2xs cursor-pointer">
                        <input type="checkbox" id="chk_ems" checked class="accent-teal-600 rounded pointer-events-none">
                        <span class="w-2 h-2 bg-teal-600 inline-block"></span>
                        <span>Ambulance / EMS</span>
                    </button>
                    <button type="button" id="pill_camps" onclick="toggleLayer('camps')" class="flex items-center gap-1.5 px-2.5 py-1 bg-amber-50 border border-amber-200 text-xs font-bold text-amber-800 transition-all select-none shadow-2xs cursor-pointer">
                        <input type="checkbox" id="chk_camps" checked class="accent-amber-600 rounded pointer-events-none">
                        <span class="w-2 h-2 bg-amber-500 inline-block"></span>
                        <span>Hospitals &amp; Shelters</span>
                    </button>
                </div>

                <!-- Leaflet Map Container -->
                <div id="gisCommandMap" class="flex-1 w-full relative z-10 bg-slate-100"></div>

                <!-- Bottom Map Legend Bar -->
                <div class="h-9 px-4 bg-white border-t border-slate-200 flex items-center gap-3 sm:gap-5 text-[11px] text-slate-600 shrink-0 z-20 overflow-x-auto">
                    <span class="flex items-center gap-1.5 font-bold text-red-600 whitespace-nowrap"><i class="w-2 h-2 bg-red-600 animate-pulse inline-block"></i> SOS Alert</span>
                    <span class="flex items-center gap-1.5 font-medium whitespace-nowrap"><i class="w-2 h-2 bg-blue-600 inline-block"></i> Police Squad</span>
                    <span class="flex items-center gap-1.5 font-medium whitespace-nowrap"><i class="w-2 h-2 bg-red-600 inline-block"></i> Fire Unit</span>
                    <span class="flex items-center gap-1.5 font-medium whitespace-nowrap"><i class="w-2 h-2 bg-teal-600 inline-block"></i> Ambulance</span>
                    <span class="flex items-center gap-1.5 font-medium whitespace-nowrap"><i class="w-2 h-2 inline-block" style="background:#00FF00;"></i> Hospital</span>
                    <span class="flex items-center gap-1.5 font-medium whitespace-nowrap"><i class="w-2 h-2 bg-amber-500 inline-block"></i> Relief Camp</span>
                </div>
            </div>

            <!-- RIGHT: DETAIL PANEL -->
            <div id="detailPanel" class="w-[380px] flex-shrink-0 border-l border-slate-200 bg-white flex flex-col overflow-hidden hidden lg:flex">
                
                <!-- Panel Header -->
                <div class="h-11 px-4 border-b border-slate-200 flex items-center justify-between shrink-0 bg-[#F8FAFC]">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-circle-info text-[#2563EB] text-xs"></i>
                        <span class="text-xs font-extrabold text-[#0F172A] uppercase tracking-wider mono">Marker Inspector</span>
                    </div>
                    <button type="button" onclick="clearDetailPanel()" class="text-slate-400 hover:text-slate-700 text-xs cursor-pointer" title="Clear">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <!-- Panel Content (scrollable) -->
                <div id="detailPanelContent" class="flex-1 overflow-y-auto p-4">
                    
                    <!-- Default empty state -->
                    <div id="detailEmpty" class="h-full flex flex-col items-center justify-center text-center px-6">
                        <div class="w-16 h-16 bg-[#EFF6FF] border border-blue-200 flex items-center justify-center mb-4">
                            <i class="fa-solid fa-hand-pointer text-[#2563EB] text-xl"></i>
                        </div>
                        <p class="text-sm font-bold text-[#0F172A] mb-1">No Marker Selected</p>
                        <p class="text-xs text-slate-500 leading-relaxed">Click any marker on the map to view its full details here.</p>
                    </div>

                    <!-- Detail content (hidden by default, filled by JS) -->
                    <div id="detailBody" class="space-y-4 hidden"></div>

                </div>

                <!-- Panel Footer -->
                <div class="h-10 px-4 border-t border-slate-200 flex items-center justify-between shrink-0 bg-[#F8FAFC]">
                    <span id="detailLayerLabel" class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mono">--</span>
                    <span id="detailCoords" class="text-[10px] font-mono text-slate-400">--</span>
                </div>
            </div>

        </section>

    </main>
</div>

<!-- LEAFLET GIS MAP ENGINE SCRIPT -->
<script>
let map = null;
let currentTileLayer = null;
const mapCenter = [28.6139, 77.2090];

const layerSos = L.layerGroup();
const layerPolice = L.layerGroup();
const layerFire = L.layerGroup();
const layerEms = L.layerGroup();
const layerCamps = L.layerGroup();

const tileUrls = {
    street: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    satellite: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
    dark: 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png'
};

/* ---- DETAIL PANEL HELPERS ---- */
function clearDetailPanel() {
    document.getElementById('detailEmpty').classList.remove('hidden');
    document.getElementById('detailBody').classList.add('hidden');
    document.getElementById('detailBody').innerHTML = '';
    document.getElementById('detailLayerLabel').textContent = '--';
    document.getElementById('detailCoords').textContent = '--';
}

function showDetailPanel(type, lat, lng, html) {
    document.getElementById('detailEmpty').classList.add('hidden');
    const body = document.getElementById('detailBody');
    body.innerHTML = html;
    body.classList.remove('hidden');
    document.getElementById('detailLayerLabel').textContent = type;
    document.getElementById('detailCoords').textContent = Number(lat).toFixed(4) + ', ' + Number(lng).toFixed(4);
}

/* ---- SOS DETAIL BUILDER ---- */
function buildSosDetail(s) {
    return `
        <div class="border border-slate-200 bg-white p-4 space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-extrabold text-white bg-[#0F172A] px-2 py-0.5 uppercase tracking-wider">SOS Beacon</span>
                <span class="text-[10px] font-bold text-[#2563EB] bg-[#EFF6FF] border border-blue-200 px-2 py-0.5 uppercase">${(s.priority || 'CRITICAL')}</span>
            </div>
            <h3 class="text-base font-black text-[#0F172A]">${s.sender_name}</h3>
            <div class="grid grid-cols-2 gap-2 text-xs">
                <div class="bg-[#F8FAFC] border border-slate-200 p-2">
                    <span class="text-[10px] font-bold text-slate-500 uppercase block">Emergency</span>
                    <span class="font-bold text-[#0F172A]">${s.emergency_type}</span>
                </div>
                <div class="bg-[#F8FAFC] border border-slate-200 p-2">
                    <span class="text-[10px] font-bold text-slate-500 uppercase block">Persons</span>
                    <span class="font-bold text-[#0F172A]">${s.persons_count || '1 - 4'}</span>
                </div>
                <div class="bg-[#F8FAFC] border border-slate-200 p-2">
                    <span class="text-[10px] font-bold text-slate-500 uppercase block">Blood Type</span>
                    <span class="font-bold text-[#0F172A]">${s.blood_type || 'Unknown'}</span>
                </div>
                <div class="bg-[#F8FAFC] border border-slate-200 p-2">
                    <span class="text-[10px] font-bold text-slate-500 uppercase block">Status</span>
                    <span class="font-bold text-[#0F172A]">${s.status}</span>
                </div>
            </div>
            <div class="bg-[#F8FAFC] border border-slate-200 p-2 text-xs">
                <span class="text-[10px] font-bold text-slate-500 uppercase block mb-0.5">GPS Coordinates</span>
                <span class="font-mono font-bold text-[#0F172A]">${Number(s.gps_lat).toFixed(4)}° N, ${Number(s.gps_lng).toFixed(4)}° E</span>
            </div>
            ${s.dispatch_agency ? `<div class="bg-[#EFF6FF] border border-blue-200 p-2 text-xs"><span class="text-[10px] font-bold text-blue-600 uppercase block mb-0.5">Assigned Agency</span><span class="font-bold text-[#1E3A8A]">${s.dispatch_agency}</span></div>` : ''}
            ${s.medical_needs ? `<div class="bg-[#F8FAFC] border border-slate-200 p-2 text-xs"><span class="text-[10px] font-bold text-slate-500 uppercase block mb-0.5">Medical Needs</span><span class="text-[#0F172A]">${s.medical_needs}</span></div>` : ''}
            ${s.message ? `<div class="bg-[#F8FAFC] border border-slate-200 p-2 text-xs"><span class="text-[10px] font-bold text-slate-500 uppercase block mb-0.5">Message</span><span class="text-slate-600 italic">${s.message}</span></div>` : ''}
            <a href="sos.php?id=${s.id}" class="block w-full text-center py-2 bg-[#2563EB] hover:bg-[#1D4ED8] text-white text-xs font-bold transition-colors">Open Full Triage Dossier &rarr;</a>
        </div>`;
}

function buildPoliceDetail(p) {
    return `
        <div class="border border-slate-200 bg-white p-4 space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-extrabold text-white bg-[#0F172A] px-2 py-0.5 uppercase tracking-wider">Police Unit</span>
                <span class="text-[10px] font-bold text-[#2563EB] bg-[#EFF6FF] border border-blue-200 px-2 py-0.5">Active</span>
            </div>
            <h3 class="text-sm font-black text-[#0F172A]">${p.callsign}</h3>
            <div class="bg-[#F8FAFC] border border-slate-200 p-2 text-xs">
                <span class="text-[10px] font-bold text-slate-500 uppercase block mb-0.5">Mission</span>
                <span class="text-[#0F172A]">${p.mission}</span>
            </div>
            <div class="grid grid-cols-2 gap-2 text-xs">
                <div class="bg-[#F8FAFC] border border-slate-200 p-2">
                    <span class="text-[10px] font-bold text-slate-500 uppercase block">Officers</span>
                    <span class="font-bold text-[#0F172A]">${p.officers} On-Duty</span>
                </div>
                <div class="bg-[#F8FAFC] border border-slate-200 p-2">
                    <span class="text-[10px] font-bold text-slate-500 uppercase block">Radio</span>
                    <span class="font-bold text-[#0F172A]">${p.radio}</span>
                </div>
            </div>
        </div>`;
}

function buildFireDetail(f) {
    return `
        <div class="border border-slate-200 bg-white p-4 space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-extrabold text-white bg-[#0F172A] px-2 py-0.5 uppercase tracking-wider">Fire & Rescue</span>
                <span class="text-[10px] font-bold text-[#2563EB] bg-[#EFF6FF] border border-blue-200 px-2 py-0.5">Active</span>
            </div>
            <h3 class="text-sm font-black text-[#0F172A]">${f.callsign}</h3>
            <div class="bg-[#F8FAFC] border border-slate-200 p-2 text-xs">
                <span class="text-[10px] font-bold text-slate-500 uppercase block mb-0.5">Mission</span>
                <span class="text-[#0F172A]">${f.mission}</span>
            </div>
            <div class="bg-[#F8FAFC] border border-slate-200 p-2 text-xs">
                <span class="text-[10px] font-bold text-slate-500 uppercase block mb-0.5">Active Tenders</span>
                <span class="font-bold text-[#0F172A]">${f.tenders} Units</span>
            </div>
        </div>`;
}

function buildEmsDetail(e) {
    return `
        <div class="border border-slate-200 bg-white p-4 space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-extrabold text-white bg-[#0F172A] px-2 py-0.5 uppercase tracking-wider">EMS / Ambulance</span>
                <span class="text-[10px] font-bold text-[#2563EB] bg-[#EFF6FF] border border-blue-200 px-2 py-0.5">${e.status}</span>
            </div>
            <h3 class="text-sm font-black text-[#0F172A]">${e.callsign}</h3>
            <div class="bg-[#F8FAFC] border border-slate-200 p-2 text-xs">
                <span class="text-[10px] font-bold text-slate-500 uppercase block mb-0.5">Current Status</span>
                <span class="text-[#0F172A]">${e.status}</span>
            </div>
            <div class="bg-[#F8FAFC] border border-slate-200 p-2 text-xs">
                <span class="text-[10px] font-bold text-slate-500 uppercase block mb-0.5">Equipment</span>
                <span class="font-bold text-[#0F172A]">${e.beds}</span>
            </div>
        </div>`;
}

function buildFacilityDetail(f) {
    const isHosp = f.type === 'hospital';
    return `
        <div class="border border-slate-200 bg-white p-4 space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-extrabold text-white bg-[#0F172A] px-2 py-0.5 uppercase tracking-wider">${isHosp ? 'Hospital' : 'Relief Shelter'}</span>
                <span class="text-[10px] font-bold text-[#2563EB] bg-[#EFF6FF] border border-blue-200 px-2 py-0.5">${isHosp ? 'Medical' : 'Shelter'}</span>
            </div>
            <h3 class="text-sm font-black text-[#0F172A]">${f.name}</h3>
            <div class="bg-[#F8FAFC] border border-slate-200 p-2 text-xs">
                <span class="text-[10px] font-bold text-slate-500 uppercase block mb-0.5">Capacity</span>
                <span class="font-bold text-[#0F172A]">${f.capacity}</span>
            </div>
            <div class="bg-[#F8FAFC] border border-slate-200 p-2 text-xs">
                <span class="text-[10px] font-bold text-slate-500 uppercase block mb-0.5">Operations</span>
                <span class="text-slate-600">Emergency triage & relief distribution operational</span>
            </div>
        </div>`;
}

/* ---- SOS DATA (server-rendered) ---- */
const sosIncidents = <?= json_encode($sosList) ?>;

document.addEventListener('DOMContentLoaded', () => {
    map = L.map('gisCommandMap', {
        zoomControl: false,
        attributionControl: false
    }).setView(mapCenter, 12);

    setMapTile('street');

    layerSos.addTo(map);
    layerPolice.addTo(map);
    layerFire.addTo(map);
    layerEms.addTo(map);
    layerCamps.addTo(map);

    const filterBox = document.getElementById('layerFilterBox');
    if (filterBox) { L.DomEvent.disableClickPropagation(filterBox); L.DomEvent.disableScrollPropagation(filterBox); }

    /* 1. SOS MARKERS */
    sosIncidents.forEach(sos => {
        const radarIcon = L.divIcon({
            className: 'custom-radar-icon',
            html: '<div class="radar-pulse-marker">!</div>',
            iconSize: [26, 26]
        });

        L.marker([sos.gps_lat, sos.gps_lng], { icon: radarIcon }).addTo(layerSos)
            .on('click', function() {
                showDetailPanel('SOS Alert', sos.gps_lat, sos.gps_lng, buildSosDetail(sos));
            });
    });

    /* 2. POLICE */
    const policeSquads = [
        { lat: 28.6250, lng: 77.2600, callsign: "Delta-NCR-1 (Delhi Police)", mission: "ITO Bridge & Geeta Colony Flood Cordon", officers: 18, radio: "VHF Ch-4" },
        { lat: 28.6650, lng: 77.2300, callsign: "Echo-Patrol-3 (Delhi Police)", mission: "Kashmere Gate ISBT Convoy Escort", officers: 12, radio: "VHF Ch-2" },
        { lat: 28.4500, lng: 77.0400, callsign: "Alpha-Gurgaon-2 (Haryana Police)", mission: "Hero Honda Chowk Underpass Blockage", officers: 14, radio: "VHF Ch-7" },
        { lat: 28.6720, lng: 77.3680, callsign: "Charlie-Ghaziabad-1 (UP Police)", mission: "Mohan Nagar Hazmat Perimeter", officers: 15, radio: "VHF Ch-1" }
    ];

    policeSquads.forEach(p => {
        L.circleMarker([p.lat, p.lng], {
            radius: 8, fillColor: '#2563eb', color: '#ffffff', weight: 2, fillOpacity: 0.95
        }).addTo(layerPolice)
        .on('click', function() {
            showDetailPanel('Police Unit', p.lat, p.lng, buildPoliceDetail(p));
        });
    });

    /* 3. FIRE */
    const fireSquads = [
        { lat: 28.6780, lng: 77.3620, callsign: "Fire & Rescue Squad 2", mission: "Sahibabad Chemical Solvent Suppression", tenders: 6 },
        { lat: 28.6290, lng: 77.3750, callsign: "Noida Fire Station Unit 4", mission: "Sector 62 Structural Collapse Standby", tenders: 3 }
    ];

    fireSquads.forEach(f => {
        L.circleMarker([f.lat, f.lng], {
            radius: 8, fillColor: '#dc2626', color: '#ffffff', weight: 2, fillOpacity: 0.95
        }).addTo(layerFire)
        .on('click', function() {
            showDetailPanel('Fire & Rescue', f.lat, f.lng, buildFireDetail(f));
        });
    });

    /* 4. EMS */
    const emsUnits = [
        { lat: 28.5720, lng: 77.2150, callsign: "ALS Ambulance Unit 1 (AIIMS)", status: "En-Route Mayur Vihar", beds: "ICU On-Board" },
        { lat: 28.4420, lng: 77.0450, callsign: "Medanta Trauma Response 3", status: "On-Standby DLF Phase 3", beds: "Paramedic Ready" }
    ];

    emsUnits.forEach(e => {
        L.circleMarker([e.lat, e.lng], {
            radius: 8, fillColor: '#0d9488', color: '#ffffff', weight: 2, fillOpacity: 0.95
        }).addTo(layerEms)
        .on('click', function() {
            showDetailPanel('EMS / Ambulance', e.lat, e.lng, buildEmsDetail(e));
        });
    });

    /* 5. FACILITIES */
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
        const color = isHosp ? '#00FF00' : '#eab308';
        L.circleMarker([fac.lat, fac.lng], {
            radius: 8, fillColor: color, color: '#ffffff', weight: 2, fillOpacity: 0.95
        }).addTo(layerCamps)
        .on('click', function() {
            showDetailPanel(isHosp ? 'Hospital' : 'Relief Shelter', fac.lat, fac.lng, buildFacilityDetail(fac));
        });
    });
});

function setMapTile(type) {
    if (currentTileLayer) { map.removeLayer(currentTileLayer); }
    currentTileLayer = L.tileLayer(tileUrls[type], { maxZoom: 19 }).addTo(map);
    ['btnTileStreet', 'btnTileSat', 'btnTileDark'].forEach(btnId => {
        const el = document.getElementById(btnId);
        if (el) { el.className = 'px-2.5 py-1 bg-slate-50 hover:bg-slate-100 text-slate-700 font-bold text-[11px] border border-slate-200 transition-all cursor-pointer'; }
    });
    const activeMap = { street: 'btnTileStreet', satellite: 'btnTileSat', dark: 'btnTileDark' };
    const activeEl = document.getElementById(activeMap[type]);
    if (activeEl) { activeEl.className = 'px-2.5 py-1 bg-[#1d63d8] text-white font-bold text-[11px] shadow-2xs transition-all cursor-pointer'; }
}

const layerStates = { sos: true, police: true, fire: true, ems: true, camps: true };

function toggleLayer(layerName) {
    if (!map) return;
    layerStates[layerName] = !layerStates[layerName];
    const isVisible = layerStates[layerName];
    const groups = { sos: layerSos, police: layerPolice, fire: layerFire, ems: layerEms, camps: layerCamps };
    const targetGroup = groups[layerName];
    if (targetGroup) {
        if (isVisible) { if (!map.hasLayer(targetGroup)) map.addLayer(targetGroup); }
        else { if (map.hasLayer(targetGroup)) map.removeLayer(targetGroup); }
    }
    const chk = document.getElementById('chk_' + layerName);
    if (chk) chk.checked = isVisible;
    const pill = document.getElementById('pill_' + layerName);
    if (pill) {
        pill.style.opacity = isVisible ? '1' : '0.4';
        pill.style.filter = isVisible ? 'none' : 'grayscale(0.8)';
    }
}

function resetMapCenter() { if (map) map.setView(mapCenter, 12, { animate: true }); }
function flyToLocation(lat, lng, zoom) { if (map) map.flyTo([lat, lng], zoom || 15, { animate: true, duration: 1.2 }); }
function toggleFullscreenMap() {
    const el = document.getElementById('mapSection');
    if (!document.fullscreenElement) { el.requestFullscreen().catch(() => {}); }
    else { document.exitFullscreen(); }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
