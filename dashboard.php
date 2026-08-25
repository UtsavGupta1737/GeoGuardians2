<?php
// dashboard.php - DisasterSafe Tactical Geospatial GIS Command Center (Government Theme)
define('PAGE_TITLE', 'Tactical GIS Map');
require_once __DIR__ . '/auth.php';

requireLogin();
$currentUser = getCurrentUser($pdo);

// Role & Permission Checks
$isSuperAdmin = isSuperAdmin($currentUser);
$hasSosAccess = $isSuperAdmin || hasPermission($currentUser, 'access_sos_database');

// Fetch live SOS calls with coordinates from SQLite database
$sosList = $pdo->query("SELECT id, sender_name, sender_phone, emergency_type, priority, status, blood_type, gps_lat, gps_lng, persons_count, medical_needs, dispatch_agency, message, created_at FROM emergency_sos ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// Live aggregate counts for tactical filter badges
$totalSosCount = count($sosList);
$activeRescuesCount = count(array_filter($sosList, fn($s) => ($s['status'] ?? '') !== 'Resolved'));
$safeResolvedCount = count(array_filter($sosList, fn($s) => ($s['status'] ?? '') === 'Resolved'));

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div class="flex-1 flex flex-col min-w-0 bg-[#f8fafc] h-screen overflow-hidden">
    <?php require_once __DIR__ . '/navbar.php'; ?>

    <!-- MAIN SCROLLABLE DASHBOARD WORKSPACE -->
    <main class="flex-1 overflow-y-auto bg-[#f8fafc] p-3 sm:p-4 lg:p-6 space-y-4">
        
        <!-- SECTION 1: UPPER WORKSPACE (MAP + MARKER INSPECTOR SIDE-BY-SIDE) -->
        <section id="mapWorkspaceSection" class="flex flex-col lg:flex-row gap-0 border border-slate-200 bg-white shadow-2xs overflow-hidden">
            
            <!-- LEFT / CENTER: GIS MAP CONTAINER (~70% ON DESKTOP) -->
            <div class="flex-1 flex flex-col min-w-0 relative h-[500px] lg:h-[560px]">
                
                <!-- Map Top Control Bar -->
                <div class="h-11 px-4 bg-white border-b border-slate-200 flex items-center justify-between shrink-0 z-20">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-2">
                            <span class="live-dot"></span>
                            <h2 class="text-xs sm:text-sm font-extrabold text-slate-900 tracking-tight">Delhi-NCR Tactical Crisis Grid</h2>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 text-xs">
                        <button type="button" onclick="setMapTile('street')" id="btnTileStreet" class="px-2.5 py-1 bg-[#1d63d8] text-white font-bold text-[11px] transition-all cursor-pointer" title="Street Map">
                            <i class="fa-solid fa-map mr-1"></i> Streets
                        </button>
                        <button type="button" onclick="setMapTile('satellite')" id="btnTileSat" class="px-2.5 py-1 bg-slate-50 hover:bg-slate-100 text-slate-700 font-bold text-[11px] border border-slate-200 transition-all cursor-pointer" title="Satellite Imagery">
                            <i class="fa-solid fa-satellite mr-1"></i> Satellite
                        </button>
                        <button type="button" onclick="setMapTile('dark')" id="btnTileDark" class="px-2.5 py-1 bg-slate-50 hover:bg-slate-100 text-slate-700 font-bold text-[11px] border border-slate-200 transition-all cursor-pointer" title="Dark Carto Map">
                            <i class="fa-solid fa-moon mr-1"></i> Dark
                        </button>
                        <div class="h-4 w-[1px] bg-slate-200 mx-1"></div>
                        <button type="button" onclick="resetMapCenter()" class="px-2.5 py-1 bg-blue-50 hover:bg-blue-100 text-blue-800 border border-blue-200 text-[11px] font-bold transition-all cursor-pointer" title="Recenter on Delhi-NCR">
                            <i class="fa-solid fa-crosshairs mr-1"></i> Center
                        </button>
                        <button type="button" onclick="toggleFullscreenMap()" class="p-1.5 bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 transition-all cursor-pointer" title="Toggle Fullscreen">
                            <i class="fa-solid fa-expand text-xs"></i>
                        </button>
                    </div>
                </div>

                <!-- Map Layers Dropdown Button (Floating on top-left of Map) -->
                <div id="layerDropdownWrap" class="absolute top-14 left-3 sm:left-4 z-[1000]">
                    <button type="button" id="layerDropdownBtn" onclick="toggleLayerDropdown()" class="flex items-center gap-2 px-3 py-1.5 bg-white/95 backdrop-blur-md border border-slate-200 shadow-xl text-xs font-bold text-slate-800 hover:bg-slate-50 transition-all cursor-pointer select-none">
                        <i class="fa-solid fa-layer-group text-[#1d63d8] text-[11px]"></i>
                        <span>Map Layers</span>
                        <i class="fa-solid fa-chevron-down text-[9px] text-slate-400 transition-transform" id="layerChevron"></i>
                    </button>
                    <div id="layerDropdownMenu" class="hidden absolute top-full left-0 mt-1 w-56 bg-white border border-slate-200 shadow-2xl overflow-hidden z-50">
                        <div class="px-3 py-2 border-b border-slate-100 bg-[#F8FAFC]">
                            <span class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider mono">Toggle Map Layers</span>
                        </div>
                        <div class="py-1">
                            <label class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer select-none" id="row_sos">
                                <input type="checkbox" id="chk_sos" checked onchange="toggleLayer('sos')" class="accent-red-600 pointer-events-none">
                                <span class="w-2.5 h-2.5 bg-red-600 animate-pulse inline-block shrink-0"></span>
                                <span class="text-xs font-bold text-slate-800">SOS Distress Alerts</span>
                                <span class="ml-auto text-[10px] font-mono font-bold text-slate-400"><?= $totalSosCount ?></span>
                            </label>
                            <label class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer select-none" id="row_police">
                                <input type="checkbox" id="chk_police" checked onchange="toggleLayer('police')" class="accent-blue-600 pointer-events-none">
                                <span class="w-2.5 h-2.5 bg-blue-600 inline-block shrink-0"></span>
                                <span class="text-xs font-bold text-slate-800">Police Units</span>
                                <span class="ml-auto text-[10px] font-mono font-bold text-slate-400">4</span>
                            </label>
                            <label class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer select-none" id="row_fire">
                                <input type="checkbox" id="chk_fire" checked onchange="toggleLayer('fire')" class="accent-orange-600 pointer-events-none">
                                <span class="w-2.5 h-2.5 bg-orange-600 inline-block shrink-0"></span>
                                <span class="text-xs font-bold text-slate-800">Fire &amp; Hazmat</span>
                                <span class="ml-auto text-[10px] font-mono font-bold text-slate-400">2</span>
                            </label>
                            <label class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer select-none" id="row_ems">
                                <input type="checkbox" id="chk_ems" checked onchange="toggleLayer('ems')" class="accent-teal-600 pointer-events-none">
                                <span class="w-2.5 h-2.5 bg-teal-600 inline-block shrink-0"></span>
                                <span class="text-xs font-bold text-slate-800">Ambulance / EMS</span>
                                <span class="ml-auto text-[10px] font-mono font-bold text-slate-400">2</span>
                            </label>
                            <label class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer select-none" id="row_camps">
                                <input type="checkbox" id="chk_camps" checked onchange="toggleLayer('camps')" class="accent-amber-600 pointer-events-none">
                                <span class="w-2.5 h-2.5 bg-amber-500 inline-block shrink-0"></span>
                                <span class="text-xs font-bold text-slate-800">Hospitals &amp; Shelters</span>
                                <span class="ml-auto text-[10px] font-mono font-bold text-slate-400">8</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Leaflet Map Canvas -->
                <div id="gisCommandMap" class="flex-1 w-full h-full relative z-10 bg-slate-100"></div>

                <!-- Map Bottom Legend Strip -->
                <div class="h-9 px-4 bg-white border-t border-slate-200 flex items-center gap-3 sm:gap-5 text-[11px] text-slate-600 shrink-0 z-20 overflow-x-auto select-none">
                    <span class="flex items-center gap-1.5 font-bold text-red-600 whitespace-nowrap"><i class="w-2 h-2 bg-red-600 animate-pulse inline-block"></i> SOS Alert</span>
                    <span class="flex items-center gap-1.5 font-medium whitespace-nowrap"><i class="w-2 h-2 bg-blue-600 inline-block"></i> Police Squad</span>
                    <span class="flex items-center gap-1.5 font-medium whitespace-nowrap"><i class="w-2 h-2 bg-red-600 inline-block"></i> Fire Unit</span>
                    <span class="flex items-center gap-1.5 font-medium whitespace-nowrap"><i class="w-2 h-2 bg-teal-600 inline-block"></i> Ambulance</span>
                    <span class="flex items-center gap-1.5 font-medium whitespace-nowrap"><i class="w-2 h-2 inline-block" style="background:#00FF00;"></i> Hospital</span>
                    <span class="flex items-center gap-1.5 font-medium whitespace-nowrap"><i class="w-2 h-2 bg-amber-500 inline-block"></i> Relief Shelter</span>
                </div>
            </div>

            <!-- RIGHT: PERSISTENT MARKER INSPECTOR PANEL (~30% ON DESKTOP) -->
            <aside id="detailPanel" class="w-full lg:w-[380px] xl:w-[420px] shrink-0 border-t lg:border-t-0 lg:border-l border-slate-200 bg-white flex flex-col h-[420px] lg:h-[560px] z-20 shadow-none">
                
                <!-- Panel Header -->
                <div class="h-11 px-4 border-b border-slate-200 flex items-center justify-between shrink-0 bg-[#F8FAFC]">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 bg-blue-600 inline-block"></span>
                        <span class="text-xs font-black text-[#0F172A] uppercase tracking-wider mono">Marker Inspector</span>
                    </div>
                    <button type="button" onclick="clearDetailPanel()" class="text-slate-400 hover:text-slate-700 text-xs px-2 py-0.5 border border-slate-200 bg-white cursor-pointer" title="Clear inspector selection">
                        <i class="fa-solid fa-xmark mr-1"></i> Clear
                    </button>
                </div>

                <!-- Panel Content (Scrollable) -->
                <div id="detailPanelContent" class="flex-1 overflow-y-auto p-4">
                    
                    <!-- Default Empty State -->
                    <div id="detailEmpty" class="h-full flex flex-col items-center justify-center text-center px-6 py-10">
                        <div class="w-14 h-14 bg-[#EFF6FF] border border-blue-200 flex items-center justify-center mb-3 text-[#2563EB]">
                            <i class="fa-solid fa-crosshairs text-2xl animate-pulse"></i>
                        </div>
                        <h3 class="text-sm font-black text-[#0F172A] mb-1">Tactical Inspector Ready</h3>
                        <p class="text-xs text-slate-500 leading-relaxed max-w-[260px]">
                            Click any marker on the Delhi-NCR map or select a report from the Incident Queue below to inspect its complete dossier.
                        </p>
                    </div>

                    <!-- Detail Content (Filled by JS) -->
                    <div id="detailBody" class="space-y-4 hidden"></div>

                </div>

                <!-- Panel Footer -->
                <div class="h-10 px-4 border-t border-slate-200 flex items-center justify-between shrink-0 bg-[#F8FAFC]">
                    <span id="detailLayerLabel" class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mono">Ready</span>
                    <span id="detailCoords" class="text-[10px] font-mono text-slate-500">28.6139 N, 77.2090 E</span>
                </div>
            </aside>

        </section>

        <!-- SECTION 2: FULL-WIDTH INCIDENT QUEUE (BELOW THE MAP) -->
        <section id="incidentQueueSection" class="border border-slate-200 bg-white shadow-2xs flex flex-col">
            
            <!-- Section Header & Filter Toolbar -->
            <div class="p-3 sm:p-4 border-b border-slate-200 bg-white flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                
                <!-- Left: Title & Summary -->
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-red-50 border border-red-200 flex items-center justify-center text-red-600 shrink-0">
                        <i class="fa-solid fa-tower-broadcast text-xs"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-sm font-black text-slate-900 uppercase tracking-tight mono">Incident Queue</h2>
                            <span class="px-2 py-0.5 bg-slate-100 text-slate-700 text-[10px] font-bold font-mono" id="queueCountLabel"><?= $totalSosCount ?> Reports</span>
                        </div>
                        <p class="text-[11px] text-slate-500">Click any incident to center on map and load full dossier in Marker Inspector.</p>
                    </div>
                </div>

                <!-- Right: Search Input, Quick Filter Chips & Advanced Filter Dropdown -->
                <div class="flex flex-wrap items-center gap-2">
                    
                    <!-- Search Input -->
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-2 text-slate-400 text-[10px]"></i>
                        <input type="text" id="queueSearchInput" oninput="handleQueueSearch(this.value)" placeholder="Search incidents..." 
                               class="pl-7 pr-2.5 py-1 bg-[#F8FAFC] border border-slate-300 text-xs font-medium text-slate-800 placeholder-slate-400 w-44 sm:w-56 focus:outline-none focus:border-blue-500 focus:bg-white transition-all">
                    </div>

                    <!-- Filter Chip: ALL -->
                    <button type="button" onclick="setFilter('all')" id="chip_all" class="filter-chip px-2.5 py-1 text-xs font-black bg-[#1d63d8] text-white border border-[#1d63d8] flex items-center gap-1.5 transition-all cursor-pointer select-none">
                        <span>ALL</span>
                        <span class="px-1.5 py-0.2 bg-white/20 text-[10px] font-mono font-bold" id="badge_all_count"><?= $totalSosCount + 16 ?></span>
                    </button>

                    <!-- Filter Chip: SOS -->
                    <button type="button" onclick="setFilter('sos')" id="chip_sos" class="filter-chip px-2.5 py-1 text-xs font-black bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 flex items-center gap-1.5 transition-all cursor-pointer select-none">
                        <i class="fa-solid fa-tower-broadcast text-red-600 text-[10px]"></i>
                        <span>SOS</span>
                        <span class="px-1.5 py-0.2 bg-red-100 text-red-700 text-[10px] font-mono font-bold" id="badge_sos_count"><?= $totalSosCount ?></span>
                    </button>

                    <!-- Filter Chip: ACTIVE RESCUES -->
                    <button type="button" onclick="setFilter('active')" id="chip_active" class="filter-chip px-2.5 py-1 text-xs font-black bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 flex items-center gap-1.5 transition-all cursor-pointer select-none">
                        <i class="fa-solid fa-person-running text-amber-600 text-[10px]"></i>
                        <span>ACTIVE</span>
                        <span class="px-1.5 py-0.2 bg-amber-100 text-amber-800 text-[10px] font-mono font-bold" id="badge_active_count"><?= $activeRescuesCount ?></span>
                    </button>

                    <!-- Filter Chip: RESCUED / SAFE -->
                    <button type="button" onclick="setFilter('rescued')" id="chip_rescued" class="filter-chip px-2.5 py-1 text-xs font-black bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 flex items-center gap-1.5 transition-all cursor-pointer select-none">
                        <i class="fa-solid fa-circle-check text-green-600 text-[10px]"></i>
                        <span>RESCUED</span>
                        <span class="px-1.5 py-0.2 bg-green-100 text-green-800 text-[10px] font-mono font-bold" id="badge_rescued_count"><?= $safeResolvedCount ?></span>
                    </button>

                    <!-- Advanced Filters Dropdown Toggle -->
                    <div class="relative">
                        <button type="button" onclick="toggleSecondaryFilters()" id="btnSecondaryFilters" class="px-2.5 py-1 text-xs font-black bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 flex items-center gap-1.5 transition-all cursor-pointer select-none">
                            <i class="fa-solid fa-sliders text-[#1d63d8] text-[10px]"></i>
                            <span>Filters</span>
                            <i class="fa-solid fa-chevron-down text-[9px] text-slate-400" id="filterChevron"></i>
                        </button>

                        <!-- Secondary Filters Popover -->
                        <div id="secondaryFiltersMenu" class="hidden absolute top-full right-0 mt-1 w-64 bg-white border border-slate-200 shadow-2xl p-3 z-50 space-y-3">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-1.5">
                                <span class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider mono">Filter Options</span>
                                <button type="button" onclick="resetAdvancedFilters()" class="text-[10px] text-blue-600 hover:underline font-bold">Reset</button>
                            </div>
                            
                            <!-- Priority Filter -->
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mono mb-1">Priority Level</label>
                                <select id="filterPriority" onchange="applyAllFilters()" class="w-full bg-[#F8FAFC] border border-slate-300 text-xs font-bold p-1.5 focus:outline-none focus:border-blue-500 cursor-pointer">
                                    <option value="">All Priorities</option>
                                    <option value="Critical">Critical</option>
                                    <option value="High">High</option>
                                    <option value="Medium">Medium</option>
                                    <option value="Low">Low</option>
                                </select>
                            </div>

                            <!-- Emergency Type Filter -->
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mono mb-1">Emergency Type</label>
                                <select id="filterEmergencyType" onchange="applyAllFilters()" class="w-full bg-[#F8FAFC] border border-slate-300 text-xs font-bold p-1.5 focus:outline-none focus:border-blue-500 cursor-pointer">
                                    <option value="">All Emergency Types</option>
                                    <option value="Flood">Flood</option>
                                    <option value="Fire">Fire</option>
                                    <option value="Building Collapse">Building Collapse</option>
                                    <option value="Medical Trauma">Medical Trauma</option>
                                    <option value="General Emergency">General Emergency</option>
                                </select>
                            </div>

                            <!-- Status Filter -->
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mono mb-1">Response Status</label>
                                <select id="filterStatus" onchange="applyAllFilters()" class="w-full bg-[#F8FAFC] border border-slate-300 text-xs font-bold p-1.5 focus:outline-none focus:border-blue-500 cursor-pointer">
                                    <option value="">All Statuses</option>
                                    <option value="Pending">Pending Triage</option>
                                    <option value="Police Dispatched">Police Dispatched</option>
                                    <option value="NDRF Dispatched">NDRF Dispatched</option>
                                    <option value="Volunteer Responding">Volunteer Responding</option>
                                    <option value="Resolved">Resolved / Safe</option>
                                </select>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Table Header Strip (Desktop) -->
            <div class="hidden lg:grid grid-cols-12 gap-2 px-4 py-2 bg-slate-100/90 border-b border-slate-200 text-[10px] font-extrabold text-slate-500 uppercase tracking-wider mono select-none">
                <div class="col-span-1">Incident</div>
                <div class="col-span-3">Citizen / Contact</div>
                <div class="col-span-2">Emergency Type</div>
                <div class="col-span-1 text-center">People</div>
                <div class="col-span-1 text-center">Priority</div>
                <div class="col-span-2 text-center">Status</div>
                <div class="col-span-2 text-right">GPS &amp; Action</div>
            </div>

            <!-- Incident Rows Container (Full-Width Horizontal Feed) -->
            <div id="incidentListContainer" class="divide-y divide-slate-200 bg-white">
                <!-- Dynamically populated as horizontal rows by JavaScript -->
            </div>

        </section>

    </main>
</div>

<!-- LEAFLET GIS MAP & TACTICAL INCIDENT ENGINE SCRIPT -->
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

/* ---- LIVE SERVER DATASETS ---- */
const sosIncidents = <?= json_encode($sosList) ?>;
const sosMarkerMap = new Map(); // id -> { marker, data, isResolved }
let activeSelectedId = null;

// Filter State
let currentCategory = 'all'; // 'all', 'sos', 'active', 'rescued'
let filterPriorityVal = '';
let filterEmergencyVal = '';
let filterStatusVal = '';
let searchFilterVal = '';

/* ---- DETAIL PANEL HELPERS ---- */
function clearDetailPanel() {
    activeSelectedId = null;
    document.getElementById('detailEmpty').classList.remove('hidden');
    document.getElementById('detailBody').classList.add('hidden');
    document.getElementById('detailBody').innerHTML = '';
    document.getElementById('detailLayerLabel').textContent = 'Ready';
    document.getElementById('detailCoords').textContent = '28.6139 N, 77.2090 E';

    // Remove active highlight from incident cards
    document.querySelectorAll('.incident-card').forEach(el => {
        el.classList.remove('border-[#2563EB]', 'ring-2', 'ring-[#2563EB]/40', 'bg-blue-50/60');
    });
}

function showDetailPanel(type, lat, lng, html) {
    document.getElementById('detailEmpty').classList.add('hidden');
    const body = document.getElementById('detailBody');
    body.innerHTML = html;
    body.classList.remove('hidden');
    document.getElementById('detailLayerLabel').textContent = type;
    document.getElementById('detailCoords').textContent = Number(lat).toFixed(4) + ' N, ' + Number(lng).toFixed(4) + ' E';
}

/* ---- SOS DETAIL DOSSIER BUILDER ---- */
function buildSosDetail(s) {
    const isResolved = (s.status && s.status.trim().toLowerCase() === 'resolved');
    const priorityColor = (s.priority === 'Critical') ? 'bg-red-100 text-red-700 border-red-200' : 'bg-blue-100 text-blue-700 border-blue-200';
    const statusColor = isResolved ? 'bg-green-100 text-green-700 border-green-200' : 'bg-amber-100 text-amber-800 border-amber-200';

    return `
        <div class="border border-slate-200 bg-white p-4 space-y-3.5 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black text-white ${isResolved ? 'bg-green-700' : 'bg-[#0F172A]'} px-2 py-0.5 uppercase tracking-wider mono">
                    ${isResolved ? 'SAFE / RESCUED' : 'SOS BEACON #' + s.id}
                </span>
                <span class="text-[10px] font-extrabold ${priorityColor} border px-2 py-0.5 uppercase mono">${(s.priority || 'CRITICAL')}</span>
            </div>

            <div>
                <h3 class="text-base font-black text-[#0F172A] leading-snug">${s.sender_name}</h3>
                <p class="text-xs text-slate-500 font-mono mt-0.5"><i class="fa-solid fa-phone text-[10px] mr-1"></i> ${s.sender_phone || 'Emergency Signal'}</p>
            </div>

            <div class="grid grid-cols-2 gap-2 text-xs">
                <div class="bg-[#F8FAFC] border border-slate-200 p-2">
                    <span class="text-[10px] font-bold text-slate-500 uppercase block mono">Emergency</span>
                    <span class="font-black text-[#0F172A]">${s.emergency_type}</span>
                </div>
                <div class="bg-[#F8FAFC] border border-slate-200 p-2">
                    <span class="text-[10px] font-bold text-slate-500 uppercase block mono">People Affected</span>
                    <span class="font-black text-[#0F172A]">${s.persons_count || '1 - 4'} Persons</span>
                </div>
                <div class="bg-[#F8FAFC] border border-slate-200 p-2">
                    <span class="text-[10px] font-bold text-slate-500 uppercase block mono">Blood Group</span>
                    <span class="font-black text-[#0F172A]">${s.blood_type || 'Unknown'}</span>
                </div>
                <div class="bg-[#F8FAFC] border border-slate-200 p-2">
                    <span class="text-[10px] font-bold text-slate-500 uppercase block mono">Current Status</span>
                    <span class="font-black ${isResolved ? 'text-green-600' : 'text-amber-700'}">${s.status}</span>
                </div>
            </div>

            <div class="bg-[#F8FAFC] border border-slate-200 p-2.5 text-xs">
                <span class="text-[10px] font-bold text-slate-500 uppercase block mono mb-0.5">GPS Coordinates</span>
                <span class="font-mono font-black text-[#0F172A]">${Number(s.gps_lat).toFixed(5)} N, ${Number(s.gps_lng).toFixed(5)} E</span>
            </div>

            ${s.dispatch_agency ? `
                <div class="bg-[#EFF6FF] border border-blue-200 p-2.5 text-xs">
                    <span class="text-[10px] font-bold text-blue-700 uppercase block mono mb-0.5">Assigned Response Unit</span>
                    <span class="font-black text-[#1E3A8A]">${s.dispatch_agency}</span>
                </div>
            ` : ''}

            ${s.medical_needs ? `
                <div class="bg-[#F8FAFC] border border-slate-200 p-2.5 text-xs">
                    <span class="text-[10px] font-bold text-slate-500 uppercase block mono mb-0.5">Medical Needs</span>
                    <span class="text-[#0F172A] font-medium">${s.medical_needs}</span>
                </div>
            ` : ''}

            ${s.message ? `
                <div class="bg-[#F8FAFC] border border-slate-200 p-2.5 text-xs">
                    <span class="text-[10px] font-bold text-slate-500 uppercase block mono mb-0.5">Distress Message</span>
                    <span class="text-slate-700 italic">"${s.message}"</span>
                </div>
            ` : ''}

            <div class="pt-1 flex gap-2">
                <a href="sos.php?id=${s.id}" class="flex-1 text-center py-2 bg-[#2563EB] hover:bg-[#1D4ED8] text-white text-xs font-black transition-colors">
                    Open Full Dossier &rarr;
                </a>
                <button type="button" onclick="flyToLocation(${s.gps_lat}, ${s.gps_lng}, 16)" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold transition-colors" title="Zoom to marker">
                    <i class="fa-solid fa-crosshairs"></i>
                </button>
            </div>
        </div>`;
}

function buildPoliceDetail(p) {
    return `
        <div class="border border-slate-200 bg-white p-4 space-y-3 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black text-white bg-[#0F172A] px-2 py-0.5 uppercase tracking-wider mono">Police Squad</span>
                <span class="text-[10px] font-bold text-[#2563EB] bg-[#EFF6FF] border border-blue-200 px-2 py-0.5 uppercase mono">Active Unit</span>
            </div>
            <h3 class="text-sm font-black text-[#0F172A]">${p.callsign}</h3>
            <div class="bg-[#F8FAFC] border border-slate-200 p-2 text-xs">
                <span class="text-[10px] font-bold text-slate-500 uppercase block mono mb-0.5">Mission</span>
                <span class="text-[#0F172A] font-bold">${p.mission}</span>
            </div>
            <div class="grid grid-cols-2 gap-2 text-xs">
                <div class="bg-[#F8FAFC] border border-slate-200 p-2">
                    <span class="text-[10px] font-bold text-slate-500 uppercase block mono">Officers</span>
                    <span class="font-bold text-[#0F172A]">${p.officers} On-Duty</span>
                </div>
                <div class="bg-[#F8FAFC] border border-slate-200 p-2">
                    <span class="text-[10px] font-bold text-slate-500 uppercase block mono">Radio</span>
                    <span class="font-bold text-[#0F172A]">${p.radio}</span>
                </div>
            </div>
        </div>`;
}

function buildFireDetail(f) {
    return `
        <div class="border border-slate-200 bg-white p-4 space-y-3 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black text-white bg-[#0F172A] px-2 py-0.5 uppercase tracking-wider mono">Fire & Rescue</span>
                <span class="text-[10px] font-bold text-[#2563EB] bg-[#EFF6FF] border border-blue-200 px-2 py-0.5 uppercase mono">Active</span>
            </div>
            <h3 class="text-sm font-black text-[#0F172A]">${f.callsign}</h3>
            <div class="bg-[#F8FAFC] border border-slate-200 p-2 text-xs">
                <span class="text-[10px] font-bold text-slate-500 uppercase block mono mb-0.5">Mission</span>
                <span class="text-[#0F172A] font-bold">${f.mission}</span>
            </div>
            <div class="bg-[#F8FAFC] border border-slate-200 p-2 text-xs">
                <span class="text-[10px] font-bold text-slate-500 uppercase block mono mb-0.5">Active Tenders</span>
                <span class="font-bold text-[#0F172A]">${f.tenders} Units Deployed</span>
            </div>
        </div>`;
}

function buildEmsDetail(e) {
    return `
        <div class="border border-slate-200 bg-white p-4 space-y-3 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black text-white bg-[#0F172A] px-2 py-0.5 uppercase tracking-wider mono">EMS / Ambulance</span>
                <span class="text-[10px] font-bold text-[#2563EB] bg-[#EFF6FF] border border-blue-200 px-2 py-0.5 uppercase mono">${e.status}</span>
            </div>
            <h3 class="text-sm font-black text-[#0F172A]">${e.callsign}</h3>
            <div class="bg-[#F8FAFC] border border-slate-200 p-2 text-xs">
                <span class="text-[10px] font-bold text-slate-500 uppercase block mono mb-0.5">Current Status</span>
                <span class="text-[#0F172A] font-bold">${e.status}</span>
            </div>
            <div class="bg-[#F8FAFC] border border-slate-200 p-2 text-xs">
                <span class="text-[10px] font-bold text-slate-500 uppercase block mono mb-0.5">Equipment</span>
                <span class="font-bold text-[#0F172A]">${e.beds}</span>
            </div>
        </div>`;
}

function buildFacilityDetail(f) {
    const isHosp = f.type === 'hospital';
    return `
        <div class="border border-slate-200 bg-white p-4 space-y-3 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black text-white bg-[#0F172A] px-2 py-0.5 uppercase tracking-wider mono">${isHosp ? 'Hospital' : 'Relief Shelter'}</span>
                <span class="text-[10px] font-bold text-[#2563EB] bg-[#EFF6FF] border border-blue-200 px-2 py-0.5 uppercase mono">${isHosp ? 'Medical' : 'Shelter'}</span>
            </div>
            <h3 class="text-sm font-black text-[#0F172A]">${f.name}</h3>
            <div class="bg-[#F8FAFC] border border-slate-200 p-2 text-xs">
                <span class="text-[10px] font-bold text-slate-500 uppercase block mono mb-0.5">Capacity</span>
                <span class="font-bold text-[#0F172A]">${f.capacity}</span>
            </div>
            <div class="bg-[#F8FAFC] border border-slate-200 p-2 text-xs">
                <span class="text-[10px] font-bold text-slate-500 uppercase block mono mb-0.5">Operations</span>
                <span class="text-slate-600">Emergency triage, bedding &amp; relief distribution operational</span>
            </div>
        </div>`;
}

/* ---- BIDIRECTIONAL SELECTION ENGINE ---- */
function selectIncident(id, panToMap = true) {
    activeSelectedId = id;
    const item = sosMarkerMap.get(Number(id));
    if (!item) return;

    const s = item.data;
    const isResolved = item.isResolved;

    // Show dossier in right Inspector
    showDetailPanel(isResolved ? 'Safe / Rescued' : 'SOS Distress Alert #' + s.id, s.gps_lat, s.gps_lng, buildSosDetail(s));

    // Pan map if requested & smoothly scroll view to map if user is further down the page
    if (panToMap && map) {
        map.flyTo([s.gps_lat, s.gps_lng], 15, { animate: true, duration: 0.8 });
        const mapElem = document.getElementById('mapWorkspaceSection');
        if (mapElem) {
            mapElem.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    // Highlight corresponding row in the Incident Feed
    document.querySelectorAll('.incident-row').forEach(el => {
        if (el.getAttribute('data-id') == id) {
            el.classList.add('bg-blue-50/90', 'border-l-4', 'border-l-[#2563EB]');
            el.classList.remove('bg-white', 'hover:bg-slate-50', 'border-l-transparent');
        } else {
            el.classList.remove('bg-blue-50/90', 'border-l-4', 'border-l-[#2563EB]');
            el.classList.add('bg-white', 'hover:bg-slate-50', 'border-l-transparent');
        }
    });
}

function renderIncidentList() {
    const container = document.getElementById('incidentListContainer');
    if (!container) return;

    // Filter dataset according to active filters
    const filtered = sosIncidents.filter(sos => {
        const isResolved = (sos.status && sos.status.trim().toLowerCase() === 'resolved');

        // Category Filter
        if (currentCategory === 'sos') {
            // all sos
        } else if (currentCategory === 'active' && isResolved) {
            return false;
        } else if (currentCategory === 'rescued' && !isResolved) {
            return false;
        }

        // Secondary Filters
        if (filterPriorityVal && sos.priority !== filterPriorityVal) return false;
        if (filterEmergencyVal && sos.emergency_type !== filterEmergencyVal) return false;
        if (filterStatusVal && sos.status !== filterStatusVal) return false;

        // Search Query Filter
        if (searchFilterVal) {
            const query = searchFilterVal.toLowerCase();
            const text = (sos.sender_name + ' ' + sos.sender_phone + ' ' + sos.emergency_type + ' ' + (sos.message || '') + ' ' + (sos.dispatch_agency || '')).toLowerCase();
            if (!text.includes(query)) return false;
        }

        return true;
    });

    // Update count labels
    const queueLabel = document.getElementById('queueCountLabel');
    if (queueLabel) queueLabel.textContent = filtered.length + ' Reports';

    if (filtered.length === 0) {
        container.innerHTML = `
            <div class="text-center py-12 text-slate-400 bg-white">
                <i class="fa-solid fa-inbox text-3xl mb-2 text-slate-300"></i>
                <p class="text-xs font-bold text-slate-700">No Incidents Match Selected Filters</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Try resetting search keywords or category filters.</p>
                <button type="button" onclick="clearQueueFilters()" class="mt-3 px-3 py-1 bg-blue-50 text-blue-700 border border-blue-200 text-xs font-bold hover:bg-blue-100 transition-colors">
                    Reset All Filters
                </button>
            </div>`;
        return;
    }

    container.innerHTML = filtered.map(sos => {
        const isResolved = (sos.status && sos.status.trim().toLowerCase() === 'resolved');
        const isSelected = activeSelectedId == sos.id;
        const selectedClasses = isSelected ? 'bg-blue-50/90 border-l-4 border-l-[#2563EB]' : 'bg-white hover:bg-slate-50 border-l-4 border-l-transparent';
        const priorityBadge = (sos.priority === 'Critical') ? 'bg-red-100 text-red-700 border-red-200' : 'bg-blue-100 text-blue-700 border-blue-200';
        const statusBadge = isResolved ? 'bg-green-100 text-green-800 border-green-200' : 'bg-amber-100 text-amber-800 border-amber-200';

        return `
            <div data-id="${sos.id}" onclick="selectIncident(${sos.id}, true)" class="incident-row px-4 py-3 transition-all cursor-pointer ${selectedClasses}">
                <!-- Desktop Full Horizontal Row (Grid Columns) -->
                <div class="hidden lg:grid grid-cols-12 gap-2 items-center text-xs">
                    <!-- Col 1: ID -->
                    <div class="col-span-1 flex items-center gap-1.5">
                        <span class="w-2 h-2 ${isResolved ? 'bg-green-600' : 'bg-red-600 animate-pulse'} inline-block shrink-0"></span>
                        <span class="font-black font-mono text-slate-900">#${sos.id}</span>
                    </div>

                    <!-- Col 2: Citizen Name & Phone / Unit -->
                    <div class="col-span-3 min-w-0 pr-2">
                        <div class="font-black text-[#0F172A] truncate leading-tight text-xs sm:text-sm">${sos.sender_name}</div>
                        <div class="text-[10px] text-slate-500 font-mono truncate flex items-center gap-1 mt-0.5">
                            <span><i class="fa-solid fa-phone text-[9px] mr-0.5"></i> ${sos.sender_phone || 'Emergency Signal'}</span>
                            ${sos.dispatch_agency ? `<span class="text-blue-700 font-bold">• ${sos.dispatch_agency}</span>` : ''}
                        </div>
                    </div>

                    <!-- Col 3: Emergency Type & Message preview -->
                    <div class="col-span-2 min-w-0 pr-2">
                        <div class="font-bold text-[#1d63d8] truncate flex items-center gap-1">
                            <i class="fa-solid fa-triangle-exclamation text-[10px]"></i>
                            <span>${sos.emergency_type}</span>
                        </div>
                        ${sos.message ? `<div class="text-[10px] text-slate-500 italic truncate mt-0.5">"${sos.message}"</div>` : ''}
                    </div>

                    <!-- Col 4: People Affected -->
                    <div class="col-span-1 text-center font-bold text-slate-700 text-xs mono">
                        ${sos.persons_count || '1-4'} <span class="text-[10px] text-slate-400 font-normal">Pers</span>
                    </div>

                    <!-- Col 5: Priority Badge -->
                    <div class="col-span-1 text-center">
                        <span class="inline-block text-[9px] font-black uppercase px-2 py-0.5 border ${priorityBadge} mono">${sos.priority || 'CRITICAL'}</span>
                    </div>

                    <!-- Col 6: Status Badge -->
                    <div class="col-span-2 text-center">
                        <span class="inline-block text-[9px] font-black uppercase px-2 py-0.5 border ${statusBadge} mono truncate max-w-full">${sos.status}</span>
                    </div>

                    <!-- Col 7: GPS Coordinates & Focus Action -->
                    <div class="col-span-2 flex items-center justify-end gap-2 text-xs">
                        <span class="font-mono text-slate-400 text-[10px] hidden xl:inline">${Number(sos.gps_lat).toFixed(3)}, ${Number(sos.gps_lng).toFixed(3)}</span>
                        <span class="text-blue-600 hover:text-blue-800 font-bold text-[11px] flex items-center gap-1 shrink-0">
                            Focus Map <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                        </span>
                    </div>
                </div>

                <!-- Mobile / Tablet Adapted Horizontal Row -->
                <div class="lg:hidden flex flex-col sm:flex-row sm:items-center justify-between gap-1.5 text-xs">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="w-2 h-2 ${isResolved ? 'bg-green-600' : 'bg-red-600 animate-pulse'} inline-block shrink-0"></span>
                        <span class="font-black font-mono text-slate-900">#${sos.id}</span>
                        <span class="font-black text-[#0F172A] truncate">${sos.sender_name}</span>
                        <span class="text-[10px] font-bold text-[#1d63d8] truncate">• ${sos.emergency_type}</span>
                    </div>
                    <div class="flex items-center gap-1.5 ml-4 sm:ml-0 shrink-0">
                        <span class="text-[9px] font-black uppercase px-1.5 py-0.5 border ${priorityBadge} mono">${sos.priority || 'CRITICAL'}</span>
                        <span class="text-[9px] font-black uppercase px-1.5 py-0.5 border ${statusBadge} mono">${sos.status}</span>
                        <span class="text-blue-600 font-bold text-[10px] ml-1">Focus &rarr;</span>
                    </div>
                </div>
            </div>`;
    }).join('');
}

function handleQueueSearch(val) {
    searchFilterVal = val.trim();
    applyAllFilters();
}

function clearQueueFilters() {
    searchFilterVal = '';
    const q = document.getElementById('queueSearchInput');
    if (q) q.value = '';
    if (document.getElementById('filterPriority')) document.getElementById('filterPriority').value = '';
    if (document.getElementById('filterEmergencyType')) document.getElementById('filterEmergencyType').value = '';
    if (document.getElementById('filterStatus')) document.getElementById('filterStatus').value = '';
    setFilter('all');
}

/* ---- MAP INITIALIZATION & POPULATION ---- */
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

    // Prevent map dragging when interacting with layer dropdown
    const layerWrap = document.getElementById('layerDropdownWrap');
    if (layerWrap) {
        L.DomEvent.disableClickPropagation(layerWrap);
        L.DomEvent.disableScrollPropagation(layerWrap);
    }

    /* 1. SOS INCIDENTS FROM DATABASE */
    sosIncidents.forEach(sos => {
        const isResolved = (sos.status && sos.status.trim().toLowerCase() === 'resolved');
        const radarIcon = L.divIcon({
            className: 'custom-radar-icon',
            html: isResolved 
                ? '<div class="radar-pulse-marker" style="background:#16a34a;box-shadow:none;animation:none;"><i class="fa-solid fa-check text-[10px] text-white"></i></div>' 
                : '<div class="radar-pulse-marker">!</div>',
            iconSize: [26, 26]
        });

        const marker = L.marker([sos.gps_lat, sos.gps_lng], { icon: radarIcon })
            .on('click', function() {
                selectIncident(sos.id, false);
            });

        layerSos.addLayer(marker);

        sosMarkerMap.set(Number(sos.id), {
            marker: marker,
            data: sos,
            isResolved: isResolved
        });
    });

    /* 2. POLICE SQUADS */
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

    /* 3. FIRE & RESCUE SQUADS */
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

    /* 4. EMS UNITS */
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

    /* 5. FACILITIES & SHELTERS */
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

    // Populate incident list initially
    renderIncidentList();

    // Invalidate map size on load and resize
    setTimeout(() => {
        if (map) map.invalidateSize();
    }, 250);

    window.addEventListener('resize', () => {
        if (map) map.invalidateSize();
    });
});

/* ---- FILTERING SYSTEM ---- */
function setFilter(cat) {
    currentCategory = cat;

    // Update Chip Styles
    ['all', 'sos', 'active', 'rescued'].forEach(c => {
        const chip = document.getElementById('chip_' + c);
        if (!chip) return;
        if (c === cat) {
            chip.className = 'filter-chip px-2.5 py-1 text-xs font-black bg-[#1d63d8] text-white border border-[#1d63d8] flex items-center gap-1.5 transition-all cursor-pointer select-none';
        } else {
            chip.className = 'filter-chip px-2.5 py-1 text-xs font-black bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 flex items-center gap-1.5 transition-all cursor-pointer select-none';
        }
    });

    applyAllFilters();
}

function applyAllFilters() {
    filterPriorityVal = document.getElementById('filterPriority') ? document.getElementById('filterPriority').value : '';
    filterEmergencyVal = document.getElementById('filterEmergencyType') ? document.getElementById('filterEmergencyType').value : '';
    filterStatusVal = document.getElementById('filterStatus') ? document.getElementById('filterStatus').value : '';

    if (!map) return;

    // Filter SOS Markers on Map
    layerSos.clearLayers();

    sosMarkerMap.forEach(item => {
        const sos = item.data;
        const isResolved = item.isResolved;

        // Category filter check
        if (currentCategory === 'active' && isResolved) return;
        if (currentCategory === 'rescued' && !isResolved) return;

        // Secondary filters
        if (filterPriorityVal && sos.priority !== filterPriorityVal) return;
        if (filterEmergencyVal && sos.emergency_type !== filterEmergencyVal) return;
        if (filterStatusVal && sos.status !== filterStatusVal) return;

        // Search filter
        if (searchFilterVal) {
            const query = searchFilterVal.toLowerCase();
            const text = (sos.sender_name + ' ' + sos.sender_phone + ' ' + sos.emergency_type + ' ' + (sos.message || '')).toLowerCase();
            if (!text.includes(query)) return;
        }

        layerSos.addLayer(item.marker);
    });

    // Operational layers visibility
    if (currentCategory === 'all') {
        if (!map.hasLayer(layerSos) && layerStates.sos) map.addLayer(layerSos);
        if (!map.hasLayer(layerPolice) && layerStates.police) map.addLayer(layerPolice);
        if (!map.hasLayer(layerFire) && layerStates.fire) map.addLayer(layerFire);
        if (!map.hasLayer(layerEms) && layerStates.ems) map.addLayer(layerEms);
        if (!map.hasLayer(layerCamps) && layerStates.camps) map.addLayer(layerCamps);
    } else {
        // When focusing on SOS categories, focus map on SOS beacons
        if (!map.hasLayer(layerSos)) map.addLayer(layerSos);
        if (map.hasLayer(layerPolice)) map.removeLayer(layerPolice);
        if (map.hasLayer(layerFire)) map.removeLayer(layerFire);
        if (map.hasLayer(layerEms)) map.removeLayer(layerEms);
        if (map.hasLayer(layerCamps)) map.removeLayer(layerCamps);
    }

    // Refresh Incident List
    renderIncidentList();
}

function toggleSecondaryFilters() {
    const menu = document.getElementById('secondaryFiltersMenu');
    const chevron = document.getElementById('filterChevron');
    if (!menu) return;
    const isHidden = menu.classList.contains('hidden');
    if (isHidden) {
        menu.classList.remove('hidden');
        if (chevron) chevron.style.transform = 'rotate(180deg)';
    } else {
        menu.classList.add('hidden');
        if (chevron) chevron.style.transform = '';
    }
}

function resetAdvancedFilters() {
    if (document.getElementById('filterPriority')) document.getElementById('filterPriority').value = '';
    if (document.getElementById('filterEmergencyType')) document.getElementById('filterEmergencyType').value = '';
    if (document.getElementById('filterStatus')) document.getElementById('filterStatus').value = '';
    applyAllFilters();
}

/* ---- MAP CONTROLS & LAYERS ---- */
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

function toggleLayerDropdown() {
    const menu = document.getElementById('layerDropdownMenu');
    if (!menu) return;
    menu.classList.toggle('hidden');
}

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
    const row = document.getElementById('row_' + layerName);
    if (row) {
        row.style.opacity = isVisible ? '1' : '0.4';
    }
}

// Global click dismiss for popovers
document.addEventListener('click', function(e) {
    const secWrap = document.getElementById('btnSecondaryFilters');
    const secMenu = document.getElementById('secondaryFiltersMenu');
    if (secWrap && secMenu && !secWrap.contains(e.target) && !secMenu.contains(e.target)) {
        secMenu.classList.add('hidden');
        const chevron = document.getElementById('filterChevron');
        if (chevron) chevron.style.transform = '';
    }

    const layerBtn = document.getElementById('layerDropdownBtn');
    const layerMenu = document.getElementById('layerDropdownMenu');
    if (layerBtn && layerMenu && !layerBtn.contains(e.target) && !layerMenu.contains(e.target)) {
        layerMenu.classList.add('hidden');
    }
});

function resetMapCenter() { if (map) map.setView(mapCenter, 12, { animate: true }); }
function flyToLocation(lat, lng, zoom) { if (map) map.flyTo([lat, lng], zoom || 15, { animate: true, duration: 1.0 }); }
function toggleFullscreenMap() {
    const el = document.getElementById('mapWorkspaceSection');
    if (!document.fullscreenElement) { el.requestFullscreen().catch(() => {}); }
    else { document.exitFullscreen(); }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
