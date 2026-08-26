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
        
        <!-- SECTION 1: GIS MAP + SOS QUEUE (SIDE-BY-SIDE) -->
        <section id="mapWorkspaceSection" class="flex flex-col lg:flex-row gap-0 border border-slate-200 bg-white shadow-2xs overflow-hidden rounded-xl">
            
            <!-- LEFT / CENTER: GIS MAP CONTAINER (~65% ON DESKTOP) -->
            <div class="flex-1 flex flex-col min-w-0 relative h-[520px] lg:h-[640px]">
                
                <!-- Map Top Control Bar -->
                <div class="h-11 px-4 bg-white border-b border-slate-200 flex items-center justify-between shrink-0 z-20">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-2">
                            <span class="live-dot"></span>
                            <h2 class="text-xs sm:text-sm font-extrabold text-slate-900 tracking-tight">Delhi-NCR Tactical Crisis Grid</h2>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 text-xs">
                        <button type="button" onclick="setMapTile('street')" id="btnTileStreet" class="px-2.5 py-1 bg-[#1d63d8] text-white font-bold text-[11px] rounded transition-all cursor-pointer" title="Street Map">
                            Streets
                        </button>
                        <button type="button" onclick="setMapTile('satellite')" id="btnTileSat" class="px-2.5 py-1 bg-slate-50 hover:bg-slate-100 text-slate-700 font-bold text-[11px] border border-slate-200 rounded transition-all cursor-pointer" title="Satellite Imagery">
                            Satellite
                        </button>
                        <button type="button" onclick="setMapTile('dark')" id="btnTileDark" class="px-2.5 py-1 bg-slate-50 hover:bg-slate-100 text-slate-700 font-bold text-[11px] border border-slate-200 rounded transition-all cursor-pointer" title="Dark Carto Map">
                            Dark
                        </button>
                        <div class="h-4 w-[1px] bg-slate-200 mx-1"></div>
                        <button type="button" onclick="resetMapCenter()" class="px-2.5 py-1 bg-blue-50 hover:bg-blue-100 text-blue-800 border border-blue-200 text-[11px] font-bold rounded transition-all cursor-pointer" title="Recenter on Delhi-NCR">
                            Center
                        </button>
                        <button type="button" onclick="toggleFullscreenMap()" class="p-1.5 bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 rounded transition-all cursor-pointer" title="Toggle Fullscreen">
                            <i class="fa-solid fa-expand text-xs"></i>
                        </button>
                    </div>
                </div>

                <!-- Map Layers Dropdown Button (Floating on top-left of Map) -->
                <div id="layerDropdownWrap" class="absolute top-14 left-3 sm:left-4 z-[1000]">
                    <button type="button" id="layerDropdownBtn" onclick="toggleLayerDropdown()" class="flex items-center gap-2 px-3 py-1.5 bg-white/95 backdrop-blur-md border border-slate-200 shadow-xl text-xs font-bold text-slate-800 hover:bg-slate-50 transition-all cursor-pointer select-none rounded-lg">
                        <i class="fa-solid fa-layer-group text-[#1d63d8] text-[11px]"></i>
                        <span>Map Layers</span>
                        <i class="fa-solid fa-chevron-down text-[9px] text-slate-400 transition-transform" id="layerChevron"></i>
                    </button>
                    <div id="layerDropdownMenu" class="hidden absolute top-full left-0 mt-1 w-56 bg-white border border-slate-200 shadow-2xl overflow-hidden z-50 rounded-xl">
                        <div class="px-3 py-2 border-b border-slate-100 bg-[#F8FAFC]">
                            <span class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider mono">Toggle Map Layers</span>
                        </div>
                        <div class="py-1">
                            <label class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer select-none" id="row_sos">
                                <input type="checkbox" id="chk_sos" checked onchange="toggleLayer('sos')" class="accent-red-600 pointer-events-none">
                                <span class="w-2.5 h-2.5 bg-red-600 animate-pulse inline-block shrink-0 rounded-full"></span>
                                <span class="text-xs font-bold text-slate-800">SOS Distress Alerts</span>
                                <span class="ml-auto text-[10px] font-mono font-bold text-slate-400"><?= $totalSosCount ?></span>
                            </label>
                            <label class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer select-none" id="row_police">
                                <input type="checkbox" id="chk_police" checked onchange="toggleLayer('police')" class="accent-blue-600 pointer-events-none">
                                <span class="w-2.5 h-2.5 bg-blue-600 inline-block shrink-0 rounded-full"></span>
                                <span class="text-xs font-bold text-slate-800">Police Units</span>
                                <span class="ml-auto text-[10px] font-mono font-bold text-slate-400">4</span>
                            </label>
                            <label class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer select-none" id="row_fire">
                                <input type="checkbox" id="chk_fire" checked onchange="toggleLayer('fire')" class="accent-orange-600 pointer-events-none">
                                <span class="w-2.5 h-2.5 bg-orange-600 inline-block shrink-0 rounded-full"></span>
                                <span class="text-xs font-bold text-slate-800">Fire &amp; Hazmat</span>
                                <span class="ml-auto text-[10px] font-mono font-bold text-slate-400">2</span>
                            </label>
                            <label class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer select-none" id="row_ems">
                                <input type="checkbox" id="chk_ems" checked onchange="toggleLayer('ems')" class="accent-teal-600 pointer-events-none">
                                <span class="w-2.5 h-2.5 bg-teal-600 inline-block shrink-0 rounded-full"></span>
                                <span class="text-xs font-bold text-slate-800">Ambulance / EMS</span>
                                <span class="ml-auto text-[10px] font-mono font-bold text-slate-400">2</span>
                            </label>
                            <label class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer select-none" id="row_camps">
                                <input type="checkbox" id="chk_camps" checked onchange="toggleLayer('camps')" class="accent-amber-600 pointer-events-none">
                                <span class="w-2.5 h-2.5 bg-amber-500 inline-block shrink-0 rounded-full"></span>
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
                    <span class="flex items-center gap-1.5 font-bold text-red-600 whitespace-nowrap"><i class="w-2 h-2 bg-red-600 animate-pulse inline-block rounded-full"></i> SOS Alert</span>
                    <span class="flex items-center gap-1.5 font-medium whitespace-nowrap"><i class="w-2 h-2 bg-blue-600 inline-block rounded-full"></i> Police Squad</span>
                    <span class="flex items-center gap-1.5 font-medium whitespace-nowrap"><i class="w-2 h-2 bg-red-600 inline-block rounded-full"></i> Fire Unit</span>
                    <span class="flex items-center gap-1.5 font-medium whitespace-nowrap"><i class="w-2 h-2 bg-teal-600 inline-block rounded-full"></i> Ambulance</span>
                    <span class="flex items-center gap-1.5 font-medium whitespace-nowrap"><i class="w-2 h-2 inline-block rounded-full" style="background:#00FF00;"></i> Hospital</span>
                    <span class="flex items-center gap-1.5 font-medium whitespace-nowrap"><i class="w-2 h-2 bg-amber-500 inline-block rounded-full"></i> Relief Shelter</span>
                </div>
            </div>

            <!-- RIGHT: SOS DISTRESS QUEUE & INCIDENT FEED (IN PLACE OF MARKER INSPECTOR) -->
            <aside id="sosQueuePanel" class="w-full lg:w-[420px] xl:w-[480px] shrink-0 border-t lg:border-t-0 lg:border-l border-slate-200 bg-white flex flex-col h-[520px] lg:h-[640px] z-20 shadow-none">
                
                <!-- Panel Header & Summary -->
                <div class="p-3 border-b border-slate-200 bg-[#F8FAFC] flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 bg-red-600 animate-pulse inline-block rounded-full"></span>
                        <h2 class="text-xs font-black text-[#0F172A] uppercase tracking-wider mono">SOS Distress Queue</h2>
                    </div>
                    <span class="px-2 py-0.5 bg-red-100 text-red-700 text-[10px] font-bold font-mono rounded" id="queueCountLabel">
                        <?= $totalSosCount ?> Reports
                    </span>
                </div>

                <!-- Search Input & Category Filter Chips -->
                <div class="p-2.5 border-b border-slate-200 bg-white space-y-2 shrink-0">
                    <!-- Search Field -->
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-2 text-slate-400 text-[10px]"></i>
                        <input type="text" id="queueSearchInput" oninput="handleQueueSearch(this.value)" placeholder="Search citizen, phone, or type..." 
                               class="w-full pl-7 pr-2.5 py-1 bg-[#F8FAFC] border border-slate-300 text-xs font-medium text-slate-800 placeholder-slate-400 rounded focus:outline-none focus:border-blue-500 focus:bg-white transition-all">
                    </div>

                    <!-- Filter Tabs -->
                    <div class="flex items-center gap-1.5 overflow-x-auto text-[11px]">
                        <button type="button" onclick="setFilter('all')" id="chip_all" class="px-2 py-0.5 rounded text-[10px] font-black bg-[#1d63d8] text-white border border-[#1d63d8] transition-all cursor-pointer shrink-0">
                            ALL (<?= $totalSosCount ?>)
                        </button>
                        <button type="button" onclick="setFilter('active')" id="chip_active" class="px-2 py-0.5 rounded text-[10px] font-black bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 transition-all cursor-pointer shrink-0">
                            ACTIVE (<?= $activeRescuesCount ?>)
                        </button>
                        <button type="button" onclick="setFilter('rescued')" id="chip_rescued" class="px-2 py-0.5 rounded text-[10px] font-black bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 transition-all cursor-pointer shrink-0">
                            RESCUED (<?= $safeResolvedCount ?>)
                        </button>
                    </div>
                </div>

                <!-- Scrollable SOS Incidents Feed -->
                <div id="incidentListContainer" class="flex-1 overflow-y-auto divide-y divide-slate-100 bg-white">
                    <!-- Populated dynamically by renderIncidentList() -->
                </div>

                <!-- Panel Footer Information -->
                <div class="h-9 px-3 border-t border-slate-200 flex items-center justify-between shrink-0 bg-[#F8FAFC] text-[10px] font-mono text-slate-500">
                    <span class="flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full inline-block"></span>
                        <span>Live Sync Active</span>
                    </span>
                    <a href="sos.php" class="text-blue-600 hover:text-blue-800 font-bold font-sans flex items-center gap-1">
                        Open SOS Database &rarr;
                    </a>
                </div>

            </aside>

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
let currentCategory = 'all'; // 'all', 'active', 'rescued'
let searchFilterVal = '';

/* ---- RICH POPUP BUILDERS FOR MARKERS ---- */
function buildSosPopup(s) {
    const isResolved = (s.status && s.status.trim().toLowerCase() === 'resolved');
    const priorityColor = (s.priority === 'Critical') ? 'background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5;' : 'background:#dbeafe;color:#1d4ed8;border:1px solid #bfdbfe;';
    const statusBadge = isResolved ? 'background:#dcfce7;color:#15803d;' : 'background:#fef3c7;color:#b45309;';

    return `
        <div style="min-width:240px;max-width:300px;font-family:Inter,sans-serif;color:#0f172a;" class="p-1">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                <span style="font-size:10px;font-weight:900;background:#0f172a;color:#fff;padding:2px 6px;border-radius:4px;font-family:monospace;">
                    SOS #${s.id}
                </span>
                <span style="font-size:9px;font-weight:800;text-transform:uppercase;padding:2px 6px;border-radius:4px;${priorityColor}">
                    ${s.priority || 'CRITICAL'}
                </span>
            </div>

            <h4 style="font-size:14px;font-weight:900;margin:0 0 2px 0;color:#0f172a;line-height:1.2;">
                ${s.sender_name}
            </h4>
            <div style="font-size:11px;color:#64748b;margin-bottom:8px;font-family:monospace;">
                📞 <a href="tel:${s.sender_phone || '112'}" style="color:#2563eb;font-weight:700;text-decoration:none;">${s.sender_phone || 'Emergency Signal'}</a>
            </div>

            <div style="background:#f8fafc;border:1px solid #e2e8f0;padding:6px 8px;border-radius:6px;font-size:11px;margin-bottom:8px;">
                <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                    <span style="color:#64748b;">Emergency:</span>
                    <strong style="color:#dc2626;">${s.emergency_type}</strong>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                    <span style="color:#64748b;">Persons:</span>
                    <strong>${s.persons_count || '1-4'} Affected</strong>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                    <span style="color:#64748b;">Status:</span>
                    <span style="font-weight:800;padding:1px 5px;border-radius:3px;font-size:10px;${statusBadge}">${s.status}</span>
                </div>
                ${s.dispatch_agency ? `
                <div style="display:flex;justify-content:space-between;border-top:1px dashed #cbd5e1;padding-top:4px;margin-top:4px;">
                    <span style="color:#64748b;">Responder:</span>
                    <strong style="color:#2563eb;">${s.dispatch_agency}</strong>
                </div>` : ''}
            </div>

            ${s.message ? `
            <div style="font-size:11px;color:#475569;font-style:italic;background:#fff;border-left:2px solid #3b82f6;padding:4px 6px;margin-bottom:8px;">
                "${s.message}"
            </div>` : ''}

            <div style="display:flex;gap:4px;margin-top:6px;">
                <a href="sos.php?id=${s.id}" style="flex:1;background:#2563eb;color:#fff;text-align:center;padding:6px;font-size:11px;font-weight:800;text-decoration:none;border-radius:4px;display:block;">
                    Assign Responder / Chat &rarr;
                </a>
            </div>
        </div>`;
}

function buildPolicePopup(p) {
    return `
        <div style="min-width:220px;font-family:Inter,sans-serif;color:#0f172a;" class="p-1">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                <span style="font-size:10px;font-weight:900;background:#1d4ed8;color:#fff;padding:2px 6px;border-radius:4px;">POLICE UNIT</span>
                <span style="font-size:10px;font-weight:700;color:#1d4ed8;font-family:monospace;">${p.radio}</span>
            </div>
            <h4 style="font-size:13px;font-weight:900;margin:0 0 4px 0;">${p.callsign}</h4>
            <div style="background:#f8fafc;border:1px solid #e2e8f0;padding:6px 8px;border-radius:6px;font-size:11px;margin-bottom:6px;">
                <div style="color:#64748b;font-size:10px;text-transform:uppercase;font-weight:700;">Active Task</div>
                <div style="font-weight:700;color:#0f172a;margin-top:2px;">${p.mission}</div>
                <div style="color:#64748b;margin-top:4px;">Officers: <strong>${p.officers} Active</strong></div>
            </div>
            <a href="police_hub.php" style="display:block;text-align:center;background:#1e293b;color:#fff;padding:5px;font-size:10px;font-weight:800;text-decoration:none;border-radius:4px;">
                Open Police Command &rarr;
            </a>
        </div>`;
}

function buildFirePopup(f) {
    return `
        <div style="min-width:220px;font-family:Inter,sans-serif;color:#0f172a;" class="p-1">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                <span style="font-size:10px;font-weight:900;background:#dc2626;color:#fff;padding:2px 6px;border-radius:4px;">FIRE &amp; HAZMAT</span>
                <span style="font-size:10px;font-weight:700;color:#dc2626;font-family:monospace;">Tenders: ${f.tenders}</span>
            </div>
            <h4 style="font-size:13px;font-weight:900;margin:0 0 4px 0;">${f.callsign}</h4>
            <div style="background:#f8fafc;border:1px solid #e2e8f0;padding:6px 8px;border-radius:6px;font-size:11px;margin-bottom:6px;">
                <div style="color:#64748b;font-size:10px;text-transform:uppercase;font-weight:700;">Deployment</div>
                <div style="font-weight:700;color:#0f172a;margin-top:2px;">${f.mission}</div>
            </div>
            <a href="fire_hub.php" style="display:block;text-align:center;background:#991b1b;color:#fff;padding:5px;font-size:10px;font-weight:800;text-decoration:none;border-radius:4px;">
                Open Fire Rescue Hub &rarr;
            </a>
        </div>`;
}

function buildEmsPopup(e) {
    return `
        <div style="min-width:220px;font-family:Inter,sans-serif;color:#0f172a;" class="p-1">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                <span style="font-size:10px;font-weight:900;background:#0d9488;color:#fff;padding:2px 6px;border-radius:4px;">ALS AMBULANCE</span>
                <span style="font-size:10px;font-weight:700;color:#0d9488;">${e.beds}</span>
            </div>
            <h4 style="font-size:13px;font-weight:900;margin:0 0 4px 0;">${e.callsign}</h4>
            <div style="background:#f8fafc;border:1px solid #e2e8f0;padding:6px 8px;border-radius:6px;font-size:11px;margin-bottom:6px;">
                <div style="color:#64748b;font-size:10px;text-transform:uppercase;font-weight:700;">Status</div>
                <div style="font-weight:700;color:#0f172a;margin-top:2px;">${e.status}</div>
            </div>
            <a href="medical_hub.php" style="display:block;text-align:center;background:#115e59;color:#fff;padding:5px;font-size:10px;font-weight:800;text-decoration:none;border-radius:4px;">
                Open Medical EMS Hub &rarr;
            </a>
        </div>`;
}

function buildFacilityPopup(fac) {
    const isHosp = fac.type === 'hospital';
    return `
        <div style="min-width:220px;font-family:Inter,sans-serif;color:#0f172a;" class="p-1">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                <span style="font-size:10px;font-weight:900;background:${isHosp ? '#16a34a' : '#d97706'};color:#fff;padding:2px 6px;border-radius:4px;">
                    ${isHosp ? 'HOSPITAL' : 'RELIEF SHELTER'}
                </span>
            </div>
            <h4 style="font-size:13px;font-weight:900;margin:0 0 4px 0;">${fac.name}</h4>
            <div style="background:#f8fafc;border:1px solid #e2e8f0;padding:6px 8px;border-radius:6px;font-size:11px;">
                <div style="color:#64748b;font-size:10px;text-transform:uppercase;font-weight:700;">Capacity / Beds</div>
                <div style="font-weight:700;color:#0f172a;margin-top:2px;">${fac.capacity}</div>
            </div>
        </div>`;
}

/* ---- BIDIRECTIONAL SELECTION & POPUP TRIGGER ---- */
function selectIncident(id, panToMap = true) {
    activeSelectedId = id;
    const item = sosMarkerMap.get(Number(id));
    if (!item) return;

    const s = item.data;

    // Pan map to location and trigger popup
    if (map) {
        if (panToMap) {
            map.flyTo([s.gps_lat, s.gps_lng], 15, { animate: true, duration: 0.8 });
        }
        item.marker.openPopup();
    }

    // Highlight row in right SOS queue feed
    document.querySelectorAll('.sos-queue-card').forEach(el => {
        if (el.getAttribute('data-id') == id) {
            el.classList.add('bg-blue-50/90', 'border-l-4', 'border-l-[#2563EB]');
            el.classList.remove('bg-white', 'hover:bg-slate-50', 'border-l-transparent');
            el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            el.classList.remove('bg-blue-50/90', 'border-l-4', 'border-l-[#2563EB]');
            el.classList.add('bg-white', 'hover:bg-slate-50', 'border-l-transparent');
        }
    });
}

function renderIncidentList() {
    const container = document.getElementById('incidentListContainer');
    if (!container) return;

    // Filter dataset
    const filtered = sosIncidents.filter(sos => {
        const isResolved = (sos.status && sos.status.trim().toLowerCase() === 'resolved');

        if (currentCategory === 'active' && isResolved) return false;
        if (currentCategory === 'rescued' && !isResolved) return false;

        if (searchFilterVal) {
            const query = searchFilterVal.toLowerCase();
            const text = (sos.sender_name + ' ' + sos.sender_phone + ' ' + sos.emergency_type + ' ' + (sos.message || '') + ' ' + (sos.dispatch_agency || '')).toLowerCase();
            if (!text.includes(query)) return false;
        }

        return true;
    });

    const queueLabel = document.getElementById('queueCountLabel');
    if (queueLabel) queueLabel.textContent = filtered.length + ' Reports';

    if (filtered.length === 0) {
        container.innerHTML = `
            <div class="text-center py-10 text-slate-400 bg-white">
                <i class="fa-solid fa-inbox text-2xl mb-2 text-slate-300"></i>
                <p class="text-xs font-bold text-slate-700">No Incidents Found</p>
                <p class="text-[10px] text-slate-400 mt-0.5">Try resetting search keywords.</p>
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
            <div data-id="${sos.id}" onclick="selectIncident(${sos.id}, true)" class="sos-queue-card p-3 transition-all cursor-pointer ${selectedClasses}">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex items-center gap-1.5 min-w-0">
                        <span class="w-2 h-2 ${isResolved ? 'bg-green-600' : 'bg-red-600 animate-pulse'} inline-block shrink-0 rounded-full"></span>
                        <span class="font-black font-mono text-[11px] text-slate-900">#${sos.id}</span>
                        <span class="font-extrabold text-slate-900 truncate text-xs">${sos.sender_name}</span>
                    </div>
                    <span class="text-[9px] font-black uppercase px-1.5 py-0.5 border ${priorityBadge} mono rounded shrink-0">
                        ${sos.priority || 'CRITICAL'}
                    </span>
                </div>

                <div class="flex items-center justify-between text-[11px] text-slate-500 mt-1">
                    <span class="font-bold text-[#1d63d8] truncate">
                        <i class="fa-solid fa-triangle-exclamation text-[10px] mr-1"></i>${sos.emergency_type}
                    </span>
                    <span class="font-mono text-[10px] text-slate-400">
                        ${sos.persons_count || '1-4'} Pers
                    </span>
                </div>

                ${sos.message ? `
                    <div class="text-[10px] text-slate-500 italic truncate mt-1">
                        "${sos.message}"
                    </div>
                ` : ''}

                <div class="flex items-center justify-between mt-2 pt-1 border-t border-slate-100 text-[10px]">
                    <span class="px-1.5 py-0.5 border ${statusBadge} mono font-bold rounded">
                        ${sos.status}
                    </span>
                    <span class="text-blue-600 hover:text-blue-800 font-bold flex items-center gap-1">
                        Locate &amp; Popup &rarr;
                    </span>
                </div>
            </div>`;
    }).join('');
}

function handleQueueSearch(val) {
    searchFilterVal = val.trim();
    applyAllFilters();
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

    /* 1. SOS INCIDENTS FROM DATABASE WITH RICH POPUPS */
    sosIncidents.forEach(sos => {
        const isResolved = (sos.status && sos.status.trim().toLowerCase() === 'resolved');
        const radarIcon = L.divIcon({
            className: 'custom-radar-icon',
            html: isResolved 
                ? '<div class="radar-pulse-marker" style="background:#16a34a;box-shadow:none;animation:none;border-radius:50%;"><i class="fa-solid fa-check text-[10px] text-white"></i></div>' 
                : '<div class="radar-pulse-marker" style="border-radius:50%;">!</div>',
            iconSize: [26, 26]
        });

        const marker = L.marker([sos.gps_lat, sos.gps_lng], { icon: radarIcon })
            .bindPopup(buildSosPopup(sos), { minWidth: 240, maxWidth: 300 })
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

    /* 2. POLICE SQUADS WITH POPUPS */
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
        .bindPopup(buildPolicePopup(p), { minWidth: 220 });
    });

    /* 3. FIRE & RESCUE SQUADS WITH POPUPS */
    const fireSquads = [
        { lat: 28.6780, lng: 77.3620, callsign: "Fire & Rescue Squad 2", mission: "Sahibabad Chemical Solvent Suppression", tenders: 6 },
        { lat: 28.6290, lng: 77.3750, callsign: "Noida Fire Station Unit 4", mission: "Sector 62 Structural Collapse Standby", tenders: 3 }
    ];

    fireSquads.forEach(f => {
        L.circleMarker([f.lat, f.lng], {
            radius: 8, fillColor: '#dc2626', color: '#ffffff', weight: 2, fillOpacity: 0.95
        }).addTo(layerFire)
        .bindPopup(buildFirePopup(f), { minWidth: 220 });
    });

    /* 4. EMS UNITS WITH POPUPS */
    const emsUnits = [
        { lat: 28.5720, lng: 77.2150, callsign: "ALS Ambulance Unit 1 (AIIMS)", status: "En-Route Mayur Vihar", beds: "ICU On-Board" },
        { lat: 28.4420, lng: 77.0450, callsign: "Medanta Trauma Response 3", status: "On-Standby DLF Phase 3", beds: "Paramedic Ready" }
    ];

    emsUnits.forEach(e => {
        L.circleMarker([e.lat, e.lng], {
            radius: 8, fillColor: '#0d9488', color: '#ffffff', weight: 2, fillOpacity: 0.95
        }).addTo(layerEms)
        .bindPopup(buildEmsPopup(e), { minWidth: 220 });
    });

    /* 5. FACILITIES & SHELTERS WITH POPUPS */
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
        .bindPopup(buildFacilityPopup(fac), { minWidth: 220 });
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

    // Update Tab Styles
    ['all', 'active', 'rescued'].forEach(c => {
        const chip = document.getElementById('chip_' + c);
        if (!chip) return;
        if (c === cat) {
            chip.className = 'px-2 py-0.5 rounded text-[10px] font-black bg-[#1d63d8] text-white border border-[#1d63d8] transition-all cursor-pointer shrink-0';
        } else {
            chip.className = 'px-2 py-0.5 rounded text-[10px] font-black bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 transition-all cursor-pointer shrink-0';
        }
    });

    applyAllFilters();
}

function applyAllFilters() {
    if (!map) return;

    // Filter SOS Markers on Map
    layerSos.clearLayers();

    sosMarkerMap.forEach(item => {
        const sos = item.data;
        const isResolved = item.isResolved;

        // Category filter check
        if (currentCategory === 'active' && isResolved) return;
        if (currentCategory === 'rescued' && !isResolved) return;

        // Search filter
        if (searchFilterVal) {
            const query = searchFilterVal.toLowerCase();
            const text = (sos.sender_name + ' ' + sos.sender_phone + ' ' + sos.emergency_type + ' ' + (sos.message || '')).toLowerCase();
            if (!text.includes(query)) return;
        }

        layerSos.addLayer(item.marker);
    });

    // Refresh SOS Queue
    renderIncidentList();
}

/* ---- MAP CONTROLS & LAYERS ---- */
function setMapTile(type) {
    if (currentTileLayer) { map.removeLayer(currentTileLayer); }
    currentTileLayer = L.tileLayer(tileUrls[type], { maxZoom: 19 }).addTo(map);
    ['btnTileStreet', 'btnTileSat', 'btnTileDark'].forEach(btnId => {
        const el = document.getElementById(btnId);
        if (el) { el.className = 'px-2.5 py-1 bg-slate-50 hover:bg-slate-100 text-slate-700 font-bold text-[11px] border border-slate-200 rounded transition-all cursor-pointer'; }
    });
    const activeMap = { street: 'btnTileStreet', satellite: 'btnTileSat', dark: 'btnTileDark' };
    const activeEl = document.getElementById(activeMap[type]);
    if (activeEl) { activeEl.className = 'px-2.5 py-1 bg-[#1d63d8] text-white font-bold text-[11px] rounded transition-all cursor-pointer'; }
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

// Global click dismiss for layer dropdown
document.addEventListener('click', function(e) {
    const layerBtn = document.getElementById('layerDropdownBtn');
    const layerMenu = document.getElementById('layerDropdownMenu');
    if (layerBtn && layerMenu && !layerBtn.contains(e.target) && !layerMenu.contains(e.target)) {
        layerMenu.classList.add('hidden');
    }
});

function resetMapCenter() { if (map) map.setView(mapCenter, 12, { animate: true }); }
function toggleFullscreenMap() {
    const el = document.getElementById('mapWorkspaceSection');
    if (!document.fullscreenElement) { el.requestFullscreen().catch(() => {}); }
    else { document.exitFullscreen(); }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
