<?php
// fire_hub.php - FIRE & RESCUE: Tactical Incident Command & Emergency CAD System
// Production-ready, zero-configuration, self-contained standalone and embeddable module

define('PAGE_TITLE', 'Fire & Rescue Tactical CAD');
require_once __DIR__ . '/auth.php';

requireLogin();
$currentUser = getCurrentUser($pdo);

// Role & Permission Checks
$isSuperAdmin = isSuperAdmin($currentUser);
$hasFireAccess = $isSuperAdmin || hasPermission($currentUser, 'access_fire');
if (!$hasFireAccess) {
    setFlash('error', 'Access denied. You do not have permission to view Fire & Rescue Department Operations.');
    header("Location: dashboard.php");
    exit;
}

require_once __DIR__ . '/config/fire_db.php';
$pdo = getFireRescuePdo();

// Check if embedded in an iframe or standalone view
$isEmbed = isset($_GET['embed']) && $_GET['embed'] === 'true';

// Fetch initial data
$stations = $pdo->query("SELECT * FROM stations ORDER BY id ASC")->fetchAll();
$vehicles = $pdo->query("SELECT * FROM vehicles ORDER BY id ASC")->fetchAll();
$incidents = $pdo->query("SELECT * FROM incidents ORDER BY id DESC")->fetchAll();
$hydrants = $pdo->query("SELECT * FROM hydrants ORDER BY id ASC")->fetchAll();
$firefighters = $pdo->query("SELECT * FROM firefighters ORDER BY id ASC")->fetchAll();

$activeIncidentsCount = count(array_filter($incidents, fn($i) => $i['status'] === 'Active'));
$unitsRollingCount = count(array_filter($vehicles, fn($v) => in_array($v['status'], ['En Route', 'On Scene'])));
$onDutyStaffCount = count(array_filter($firefighters, fn($f) => $f['status'] === 'On Duty'));
$readyHydrantsCount = count(array_filter($hydrants, fn($h) => $h['status'] === 'Operational'));
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-[#020617] text-slate-100" data-role="fire">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FIRE & RESCUE | Tactical Incident Command & Emergency CAD</title>
    
    <!-- Google Fonts: Inter & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <!-- FontAwesome 6 CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        tactical: {
                            950: '#020617',
                            900: '#090d16',
                            850: '#0f172a',
                            800: '#1e293b',
                            700: '#334155',
                            600: '#475569'
                        },
                        flame: {
                            red: '#e11d48',
                            crimson: '#dc2626',
                            amber: '#f59e0b',
                            gold: '#d97706',
                            emerald: '#10b981',
                            sky: '#0284c7'
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    }
                }
            }
        }
    </script>
    
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Leaflet GIS Map CDN -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    
    <!-- Custom Tactical Stylesheet -->
    <link rel="stylesheet" href="assets/css/fire_cad.css">
</head>
<body class="h-full bg-[#020617] text-slate-100 antialiased overflow-hidden flex flex-col selection:bg-rose-600 selection:text-white">

    <!-- TOP TACTICAL COMMAND RIBBON -->
    <header class="h-16 bg-[#090d16] border-b border-slate-800 px-4 sm:px-6 flex items-center justify-between shrink-0 z-30 shadow-md">
        
        <!-- Left Brand & Status -->
        <div class="flex items-center gap-3.5">
            <a href="fire_hub.php" class="flex items-center gap-3 group">
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-rose-600 to-amber-600 text-white flex items-center justify-center font-black text-lg shadow-lg shadow-rose-900/50 group-hover:scale-105 transition-transform border border-rose-500/40 shrink-0">
                    <i class="fa-solid fa-fire-extinguisher"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-base font-black text-white tracking-tight">FIRE &amp; RESCUE</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-rose-950 text-rose-300 border border-rose-800 mono animate-pulse">
                            CAD LIVE
                        </span>
                    </div>
                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mono">Tactical Incident Command</span>
                </div>
            </a>
        </div>

        <!-- Center Navigation Tabs -->
        <nav class="hidden md:flex items-center gap-1.5 bg-slate-950/80 p-1.5 rounded-2xl border border-slate-800/80 text-xs font-bold font-mono">
            <button type="button" data-tab="cad_board" class="tab-nav-btn px-3.5 py-1.5 rounded-xl transition-all bg-rose-950/70 text-rose-400 border border-rose-700/80 shadow-xs flex items-center gap-2">
                <i class="fa-solid fa-tower-broadcast"></i>
                <span>Operational CAD</span>
            </button>
            <button type="button" data-tab="gis_radar" class="tab-nav-btn px-3.5 py-1.5 rounded-xl transition-all text-slate-400 hover:text-slate-200 border border-transparent flex items-center gap-2">
                <i class="fa-solid fa-map-location-dot"></i>
                <span>Tactical GIS Radar</span>
            </button>
            <button type="button" data-tab="apparatus_readiness" class="tab-nav-btn px-3.5 py-1.5 rounded-xl transition-all text-slate-400 hover:text-slate-200 border border-transparent flex items-center gap-2">
                <i class="fa-solid fa-truck-droplet"></i>
                <span>Apparatus &amp; Crew</span>
            </button>
            <button type="button" data-tab="sop_protocols" class="tab-nav-btn px-3.5 py-1.5 rounded-xl transition-all text-slate-400 hover:text-slate-200 border border-transparent flex items-center gap-2">
                <i class="fa-solid fa-book-bookmark"></i>
                <span>SOP &amp; P.A.S.S. Guide</span>
            </button>
            <button type="button" data-tab="quick_dial" class="tab-nav-btn px-3.5 py-1.5 rounded-xl transition-all text-slate-400 hover:text-slate-200 border border-transparent flex items-center gap-2">
                <i class="fa-solid fa-phone-volume"></i>
                <span>Emergency Hotlines</span>
            </button>
        </nav>

        <!-- Right Quick Actions & Embed Modal Trigger -->
        <div class="flex items-center gap-2.5">
            <!-- UTC Live Clock -->
            <div class="hidden xl:flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-950 border border-slate-800 text-[11px] font-mono font-bold text-slate-400">
                <i class="fa-solid fa-clock text-rose-500"></i>
                <span id="cadLiveClock">00:00:00 UTC</span>
            </div>

            <!-- Report Fire Emergency Button (Trigger Modal) -->
            <button type="button" onclick="document.getElementById('intakeModal').classList.remove('hidden')" 
                    class="px-3.5 sm:px-4 py-2 rounded-xl bg-[#e11d48] hover:bg-[#be123c] text-white font-extrabold text-xs shadow-lg shadow-rose-950/60 transition-all flex items-center gap-2 cursor-pointer border border-rose-500/40">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span>Report Fire Distress</span>
            </button>

            <!-- Embed Code Generator Button -->
            <button type="button" onclick="document.getElementById('embedModal').classList.remove('hidden')" 
                    class="p-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-400 hover:text-slate-200 border border-slate-800 transition-colors" title="Embed into Parent Dashboard">
                <i class="fa-solid fa-code text-xs"></i>
            </button>

            <?php if (file_exists(__DIR__ . '/dashboard.php') && !$isEmbed): ?>
                <a href="dashboard.php" class="p-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-400 hover:text-slate-200 border border-slate-800 transition-colors" title="Return to Main Command Center">
                    <i class="fa-solid fa-house text-xs"></i>
                </a>
            <?php endif; ?>

            <!-- Dedicated Header Sign Out Button -->
            <a href="logout.php" title="Sign Out of DisasterSafe" class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-rose-950/80 hover:bg-rose-900 border border-rose-800 text-xs font-bold text-rose-300 transition-colors shadow-2xs">
                <i class="fa-solid fa-arrow-right-from-bracket text-rose-400"></i>
                <span class="hidden sm:inline">Logout</span>
            </a>
        </div>
    </header>

    <!-- METRICS STRIP -->
    <section class="h-12 bg-[#090d16]/90 border-b border-slate-800 px-4 sm:px-6 flex items-center justify-between gap-4 shrink-0 text-xs font-mono">
        <div class="flex items-center gap-6 overflow-x-auto py-1">
            <div class="flex items-center gap-2 shrink-0">
                <span class="w-2.5 h-2.5 rounded-full bg-rose-500 animate-ping"></span>
                <span class="text-slate-400">Active Fire Alarms:</span>
                <strong id="metricActiveIncidents" class="text-rose-400 font-extrabold text-sm"><?= $activeIncidentsCount ?></strong>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <i class="fa-solid fa-truck text-amber-500 text-xs"></i>
                <span class="text-slate-400">Rolling Apparatus:</span>
                <strong id="metricUnitsRolling" class="text-amber-400 font-extrabold text-sm"><?= $unitsRollingCount ?></strong>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <i class="fa-solid fa-user-shield text-emerald-500 text-xs"></i>
                <span class="text-slate-400">Firefighters On Duty:</span>
                <strong id="metricActiveFirefighters" class="text-emerald-400 font-extrabold text-sm"><?= $onDutyStaffCount ?></strong>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <i class="fa-solid fa-faucet text-sky-400 text-xs"></i>
                <span class="text-slate-400">Operational Hydrants:</span>
                <strong id="metricHydrantsReady" class="text-sky-400 font-extrabold text-sm"><?= $readyHydrantsCount ?></strong>
            </div>
        </div>

        <!-- Silent Audio Policy Badge -->
        <div class="hidden sm:flex items-center gap-2 text-[10px] text-slate-500 uppercase tracking-wider font-bold shrink-0">
            <i class="fa-solid fa-volume-xmark text-slate-400"></i>
            <span>Silent Visual CAD Policy</span>
        </div>
    </section>

    <!-- MAIN INTERFACE VIEW CONTAINER -->
    <main class="flex-1 overflow-hidden relative">

        <!-- ========================================================================= -->
        <!-- TAB 1: OPERATIONAL CAD BOARD (DEFAULT) -->
        <!-- ========================================================================= -->
        <section id="view_cad_board" class="cad-tab-view h-full flex flex-col lg:flex-row overflow-hidden">
            
            <!-- Left Side: Incidents Live Queue -->
            <div class="w-full lg:w-96 border-b lg:border-b-0 lg:border-r border-slate-800 flex flex-col bg-[#060a12] shrink-0 h-64 lg:h-full">
                <div class="p-3.5 border-b border-slate-800 flex items-center justify-between shrink-0 bg-[#090d16]">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-list-check text-rose-500 text-xs"></i>
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-200 mono">Emergency Incident Queue</h3>
                    </div>
                    <button type="button" onclick="FireApp.loadData()" class="text-slate-400 hover:text-white text-xs p-1" title="Refresh Live Queue">
                        <i class="fa-solid fa-rotate"></i>
                    </button>
                </div>

                <div id="cadIncidentsList" class="flex-1 overflow-y-auto p-3 space-y-2.5">
                    <!-- Loaded dynamically via app.js -->
                </div>
            </div>

            <!-- Right Side: Selected Incident CAD Stepper & Live Details -->
            <div class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-6 bg-[#020617]" id="cadIncidentDetailPane">
                <!-- Loaded dynamically via app.js -->
            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- TAB 2: TACTICAL RESPONSE GIS MAP -->
        <!-- ========================================================================= -->
        <section id="view_gis_radar" class="cad-tab-view h-full hidden flex flex-col relative">
            
            <!-- Map Top Overlay Bar -->
            <div class="h-12 bg-[#090d16]/95 backdrop-blur-md border-b border-slate-800 px-4 flex items-center justify-between shrink-0 z-10 text-xs">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-rose-500 animate-ping"></span>
                    <span class="font-extrabold text-white font-mono">Delhi-NCR GIS Tactical Response Radar</span>
                </div>

                <div class="flex items-center gap-2 font-mono text-[11px]">
                    <button type="button" onclick="TacticalMap.setTileStyle('dark')" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-white font-bold">
                        Dark CAD
                    </button>
                    <button type="button" onclick="TacticalMap.setTileStyle('street')" class="px-2.5 py-1 rounded-lg bg-slate-900 hover:bg-slate-800 text-slate-300">
                        Streets
                    </button>
                    <button type="button" onclick="TacticalMap.setTileStyle('satellite')" class="px-2.5 py-1 rounded-lg bg-slate-900 hover:bg-slate-800 text-slate-300">
                        Satellite
                    </button>
                    <button type="button" onclick="TacticalMap.focus(28.6315, 77.2167, 13)" class="px-2.5 py-1 rounded-lg bg-rose-950 text-rose-300 border border-rose-800 hover:bg-rose-900 font-bold ml-2">
                        <i class="fa-solid fa-crosshairs mr-1"></i> Recenter
                    </button>
                </div>
            </div>

            <!-- Full-View Leaflet Map Container -->
            <div id="tacticalLeafletMap" class="flex-1 w-full h-full"></div>
        </section>

        <!-- ========================================================================= -->
        <!-- TAB 3: ACTIVE APPARATUS & STATION READINESS -->
        <!-- ========================================================================= -->
        <section id="view_apparatus_readiness" class="cad-tab-view h-full hidden overflow-y-auto p-4 sm:p-6 lg:p-8 space-y-6 bg-[#020617]">
            
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-800 pb-4">
                <div>
                    <h3 class="text-xl font-black text-white flex items-center gap-2.5">
                        <i class="fa-solid fa-truck-droplet text-amber-500"></i>
                        <span>Active Apparatus Fleet &amp; Fire Station Readiness</span>
                    </h3>
                    <p class="text-xs text-slate-400 mt-1 font-mono">Real-time water tank capacities, foam inductors, station bay ready-state &amp; crew rosters</p>
                </div>
                <button type="button" onclick="FireApp.loadData()" class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold font-mono">
                    <i class="fa-solid fa-arrows-rotate mr-1"></i> Refresh Apparatus Status
                </button>
            </div>

            <!-- Vehicles Readiness Grid -->
            <div id="apparatusCardsGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- Loaded dynamically via app.js -->
            </div>

            <!-- Firefighters On-Duty Roster -->
            <div class="bg-[#090d16] border border-slate-800 rounded-3xl p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <h4 class="text-sm font-extrabold text-white flex items-center gap-2">
                        <i class="fa-solid fa-users text-emerald-400"></i>
                        <span>Active Firefighters &amp; Incident Commanders On-Duty</span>
                    </h4>
                    <span class="text-xs font-mono text-slate-400"><?= count($firefighters) ?> Registered Operatives</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-800 text-slate-400 font-mono font-bold uppercase text-[10px]">
                                <th class="py-2.5 px-4">Name</th>
                                <th class="py-2.5 px-4">Badge Number</th>
                                <th class="py-2.5 px-4">Rank</th>
                                <th class="py-2.5 px-4">Direct Contact</th>
                                <th class="py-2.5 px-4">Status</th>
                                <th class="py-2.5 px-4">Certifications</th>
                            </tr>
                        </thead>
                        <tbody id="firefightersTableBody" class="divide-y divide-slate-800/60 text-slate-300">
                            <!-- Loaded dynamically via app.js -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Municipal Hydrants Grid -->
            <div class="bg-[#090d16] border border-slate-800 rounded-3xl p-5 space-y-4">
                <h4 class="text-sm font-extrabold text-white flex items-center gap-2">
                    <i class="fa-solid fa-faucet text-sky-400"></i>
                    <span>Municipal Fire Hydrant Grid &amp; Pressure Telemetry</span>
                </h4>
                <div id="hydrantsListGrid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <!-- Loaded dynamically via app.js -->
                </div>
            </div>

        </section>

        <!-- ========================================================================= -->
        <!-- TAB 4: FIRE SAFETY & SOP PROTOCOL LIBRARY -->
        <!-- ========================================================================= -->
        <section id="view_sop_protocols" class="cad-tab-view h-full hidden overflow-y-auto p-4 sm:p-6 lg:p-8 space-y-6 bg-[#020617]">
            
            <div class="border-b border-slate-800 pb-4">
                <h3 class="text-xl font-black text-white flex items-center gap-2.5">
                    <i class="fa-solid fa-book-bookmark text-rose-500"></i>
                    <span>Fire Safety Protocols &amp; Standard Operating Procedures (SOP)</span>
                </h3>
                <p class="text-xs text-slate-400 mt-1 font-mono">Field protocols verified under NFPA 10, NFPA 101 Life Safety Code, and ERG 2024</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                
                <!-- Protocol 1: P.A.S.S. Guide -->
                <div class="p-6 rounded-3xl bg-[#090d16] border border-slate-800 space-y-4 flex flex-col justify-between">
                    <div class="space-y-3">
                        <div class="w-12 h-12 rounded-2xl bg-rose-950/70 border border-rose-800/80 text-rose-400 flex items-center justify-center text-xl">
                            <i class="fa-solid fa-fire-extinguisher"></i>
                        </div>
                        <h4 class="text-base font-extrabold text-white">1. P.A.S.S. Extinguisher Protocol</h4>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Standard operating method for portable ABC dry chemical and CO2 fire extinguishers (NFPA 10 Standard).
                        </p>
                        <ul class="space-y-2 text-xs font-mono text-slate-300 bg-slate-950 p-3.5 rounded-2xl border border-slate-800">
                            <li class="flex items-start gap-2">
                                <strong class="text-rose-400">[P] PULL</strong> the safety pin break seal.
                            </li>
                            <li class="flex items-start gap-2">
                                <strong class="text-amber-400">[A] AIM</strong> nozzle low at the base of the fire.
                            </li>
                            <li class="flex items-start gap-2">
                                <strong class="text-emerald-400">[S] SQUEEZE</strong> handle slowly &amp; evenly.
                            </li>
                            <li class="flex items-start gap-2">
                                <strong class="text-sky-400">[S] SWEEP</strong> from side to side 6-8 ft away.
                            </li>
                        </ul>
                    </div>
                    <span class="text-[10px] text-slate-500 font-mono">Standard Ref: NFPA 10 Edition 2022</span>
                </div>

                <!-- Protocol 2: Toxic Smoke Crawl -->
                <div class="p-6 rounded-3xl bg-[#090d16] border border-slate-800 space-y-4 flex flex-col justify-between">
                    <div class="space-y-3">
                        <div class="w-12 h-12 rounded-2xl bg-amber-950/70 border border-amber-800/80 text-amber-400 flex items-center justify-center text-xl">
                            <i class="fa-solid fa-smog"></i>
                        </div>
                        <h4 class="text-base font-extrabold text-white">2. Smoke &amp; Superheated Gas Crawl</h4>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Techniques for escaping zero-visibility structure fires with Carbon Monoxide and Cyanide smoke layers (NFPA 101).
                        </p>
                        <ul class="space-y-2 text-xs font-mono text-slate-300 bg-slate-950 p-3.5 rounded-2xl border border-slate-800">
                            <li class="flex items-start gap-2">
                                <span class="text-amber-400">&bull;</span> Stay 1 to 2 feet from the floor where breathable oxygen remains.
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-amber-400">&bull;</span> Feel doors with back of hand before opening to detect flashover.
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-amber-400">&bull;</span> Close doors behind you to isolate oxygen feeds.
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-amber-400">&bull;</span> Never use elevators during fire emergencies.
                            </li>
                        </ul>
                    </div>
                    <span class="text-[10px] text-slate-500 font-mono">Standard Ref: Life Safety Code 101</span>
                </div>

                <!-- Protocol 3: LPG & Hazmat Isolation -->
                <div class="p-6 rounded-3xl bg-[#090d16] border border-slate-800 space-y-4 flex flex-col justify-between">
                    <div class="space-y-3">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-950/70 border border-emerald-800/80 text-emerald-400 flex items-center justify-center text-xl">
                            <i class="fa-solid fa-biohazard"></i>
                        </div>
                        <h4 class="text-base font-extrabold text-white">3. LPG &amp; Chemical Gas Isolation</h4>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Immediate mitigation for flammable liquefied petroleum gas and industrial toxic chemical leaks (ERG 2024).
                        </p>
                        <ul class="space-y-2 text-xs font-mono text-slate-300 bg-slate-950 p-3.5 rounded-2xl border border-slate-800">
                            <li class="flex items-start gap-2">
                                <span class="text-emerald-400">&bull;</span> Do NOT operate electrical switches or phones near gas smell.
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-emerald-400">&bull;</span> Isolate main gas manifold or cylinder regulator valve.
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-emerald-400">&bull;</span> Evacuate upwind at least 100 meters immediately.
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-emerald-400">&bull;</span> Call Hazmat unit on 101 / 112 from safe outdoor perimeter.
                            </li>
                        </ul>
                    </div>
                    <span class="text-[10px] text-slate-500 font-mono">Standard Ref: Emergency Response Guide (ERG)</span>
                </div>

            </div>

        </section>

        <!-- ========================================================================= -->
        <!-- TAB 5: EMERGENCY QUICK DIAL LINES -->
        <!-- ========================================================================= -->
        <section id="view_quick_dial" class="cad-tab-view h-full hidden overflow-y-auto p-4 sm:p-6 lg:p-8 space-y-6 bg-[#020617]">
            
            <div class="border-b border-slate-800 pb-4">
                <h3 class="text-xl font-black text-white flex items-center gap-2.5">
                    <i class="fa-solid fa-phone-volume text-rose-500"></i>
                    <span>Direct Emergency Dispatch Lines &amp; Hotline Protocols</span>
                </h3>
                <p class="text-xs text-slate-400 mt-1 font-mono">1-Tap priority dialing lines for immediate municipal dispatch and trauma extraction</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                
                <!-- 101: Fire Control -->
                <a href="tel:101" class="p-6 rounded-3xl bg-[#090d16] border border-rose-800/80 hover:border-rose-600 transition-all flex flex-col justify-between space-y-4 group shadow-lg shadow-rose-950/40">
                    <div class="space-y-3">
                        <div class="w-12 h-12 rounded-2xl bg-rose-950 text-rose-400 border border-rose-800 flex items-center justify-center text-xl group-hover:scale-110 transition-transform">
                            <i class="fa-solid fa-fire-extinguisher"></i>
                        </div>
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-wider text-rose-400 mono">Primary Fire Line</span>
                            <h4 class="text-2xl font-black text-white font-mono mt-0.5">Dial 101</h4>
                        </div>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Municipal Fire Brigade Central Control Room &amp; Water Tender Dispatch.
                        </p>
                    </div>
                    <span class="py-2 rounded-xl bg-rose-600 text-white font-bold text-center text-xs block group-hover:bg-rose-500">
                        <i class="fa-solid fa-phone mr-1"></i> Tap to Call 101
                    </span>
                </a>

                <!-- 112: National ERSS -->
                <a href="tel:112" class="p-6 rounded-3xl bg-[#090d16] border border-slate-800 hover:border-blue-600 transition-all flex flex-col justify-between space-y-4 group shadow-lg shadow-slate-950">
                    <div class="space-y-3">
                        <div class="w-12 h-12 rounded-2xl bg-blue-950 text-blue-400 border border-blue-800 flex items-center justify-center text-xl group-hover:scale-110 transition-transform">
                            <i class="fa-solid fa-tower-broadcast"></i>
                        </div>
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-wider text-blue-400 mono">Unified Response</span>
                            <h4 class="text-2xl font-black text-white font-mono mt-0.5">Dial 112</h4>
                        </div>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            National Emergency Response Support System (ERSS) Universal Triage.
                        </p>
                    </div>
                    <span class="py-2 rounded-xl bg-blue-600 text-white font-bold text-center text-xs block group-hover:bg-blue-500">
                        <i class="fa-solid fa-phone mr-1"></i> Tap to Call 112
                    </span>
                </a>

                <!-- 1078: NDRF / NDMA -->
                <a href="tel:1078" class="p-6 rounded-3xl bg-[#090d16] border border-slate-800 hover:border-amber-600 transition-all flex flex-col justify-between space-y-4 group shadow-lg shadow-slate-950">
                    <div class="space-y-3">
                        <div class="w-12 h-12 rounded-2xl bg-amber-950 text-amber-400 border border-amber-800 flex items-center justify-center text-xl group-hover:scale-110 transition-transform">
                            <i class="fa-solid fa-truck-monster"></i>
                        </div>
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-wider text-amber-400 mono">National Rescue</span>
                            <h4 class="text-2xl font-black text-white font-mono mt-0.5">Dial 1078</h4>
                        </div>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            National Disaster Response Force (NDRF) &amp; NDMA Headquarters.
                        </p>
                    </div>
                    <span class="py-2 rounded-xl bg-amber-600 text-white font-bold text-center text-xs block group-hover:bg-amber-500">
                        <i class="fa-solid fa-phone mr-1"></i> Tap to Call 1078
                    </span>
                </a>

                <!-- 108: Burn Trauma & EMS -->
                <a href="tel:108" class="p-6 rounded-3xl bg-[#090d16] border border-slate-800 hover:border-emerald-600 transition-all flex flex-col justify-between space-y-4 group shadow-lg shadow-slate-950">
                    <div class="space-y-3">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-950 text-emerald-400 border border-emerald-800 flex items-center justify-center text-xl group-hover:scale-110 transition-transform">
                            <i class="fa-solid fa-heart-pulse"></i>
                        </div>
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-wider text-emerald-400 mono">Burn Trauma Care</span>
                            <h4 class="text-2xl font-black text-white font-mono mt-0.5">Dial 108</h4>
                        </div>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            ALS Ambulances, Oxygen Logistics, and Burn ICU Hospital Transfer.
                        </p>
                    </div>
                    <span class="py-2 rounded-xl bg-emerald-600 text-white font-bold text-center text-xs block group-hover:bg-emerald-500">
                        <i class="fa-solid fa-phone mr-1"></i> Tap to Call 108
                    </span>
                </a>

            </div>

        </section>

    </main>

    <!-- ========================================================================= -->
    <!-- MODAL 1: REPORT FIRE EMERGENCY / DISTRESS INTAKE (CITIZEN & DISPATCHER) -->
    <!-- ========================================================================= -->
    <div id="intakeModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-[#090d16] border border-slate-800 rounded-3xl max-w-lg w-full p-6 shadow-2xl relative max-h-[90vh] flex flex-col">
            
            <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                <div class="flex items-center gap-2.5">
                    <span class="w-9 h-9 rounded-2xl bg-rose-950 text-rose-400 border border-rose-800 flex items-center justify-center text-sm">
                        <i class="fa-solid fa-fire"></i>
                    </span>
                    <div>
                        <h3 class="text-base font-extrabold text-white">Report Fire Emergency Alarm</h3>
                        <p class="text-[11px] text-slate-400 font-mono">Immediate 5-Stage CAD Paging</p>
                    </div>
                </div>
                <button type="button" onclick="document.getElementById('intakeModal').classList.add('hidden')" class="text-slate-400 hover:text-white p-2">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form id="fireIntakeForm" class="space-y-4 overflow-y-auto pr-1 flex-1 py-4 text-xs font-mono">
                
                <!-- Category -->
                <div>
                    <label class="block text-slate-400 font-bold uppercase tracking-wider text-[10px] mb-1">Incident Categorization *</label>
                    <select name="fire_type" required class="w-full px-3 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white font-bold focus:outline-none focus:border-rose-500">
                        <option value="Structure Fire">Structure Fire (Residential / Commercial / Industrial)</option>
                        <option value="Wildfire & Brush Fire">Wildfire &amp; Brush Fire</option>
                        <option value="Chemical / Hazmat / Gas Leak">Chemical / Hazmat / Gas Leak</option>
                        <option value="Electrical & Transformer Fire">Electrical &amp; Transformer Fire</option>
                        <option value="Vehicle & Tanker Crash Fire">Vehicle &amp; Tanker Crash Fire</option>
                        <option value="Search & Technical Rescue (USAR)">Search &amp; Technical Rescue (USAR)</option>
                    </select>
                </div>

                <!-- Caller Name & Phone -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-slate-400 font-bold uppercase tracking-wider text-[10px] mb-1">Caller Name *</label>
                        <input type="text" name="caller_name" required placeholder="e.g. John Doe" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-rose-500">
                    </div>
                    <div>
                        <label class="block text-slate-400 font-bold uppercase tracking-wider text-[10px] mb-1">Contact Phone *</label>
                        <input type="text" name="caller_phone" required placeholder="+91 98765 43210" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-rose-500">
                    </div>
                </div>

                <!-- Address & GPS -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-slate-400 font-bold uppercase tracking-wider text-[10px]">Exact Address / Landmark *</label>
                        <button type="button" id="btnGeoDetect" class="text-[10px] text-sky-400 hover:text-sky-300 font-bold">
                            <i class="fa-solid fa-location-crosshairs mr-1"></i> Auto-Detect GPS
                        </button>
                    </div>
                    <input type="text" name="address" id="intake_address" required placeholder="e.g. 4th Floor, Barakhamba Commercial Tower, Connaught Place" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-rose-500">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-slate-400 font-bold uppercase tracking-wider text-[10px] mb-1">Latitude</label>
                        <input type="number" step="any" name="lat" id="intake_lat" value="28.6315" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-slate-300 focus:outline-none focus:border-rose-500 font-mono">
                    </div>
                    <div>
                        <label class="block text-slate-400 font-bold uppercase tracking-wider text-[10px] mb-1">Longitude</label>
                        <input type="number" step="any" name="lng" id="intake_lng" value="77.2167" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-slate-300 focus:outline-none focus:border-rose-500 font-mono">
                    </div>
                </div>

                <!-- Trapped Count & Assigned Apparatus -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-slate-400 font-bold uppercase tracking-wider text-[10px] mb-1">Trapped Victims Count</label>
                        <input type="number" name="trapped_count" min="0" value="0" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white focus:outline-none focus:border-rose-500">
                    </div>
                    <div>
                        <label class="block text-slate-400 font-bold uppercase tracking-wider text-[10px] mb-1">Assign Primary Engine</label>
                        <select name="assigned_vehicle_id" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white font-bold focus:outline-none focus:border-rose-500">
                            <?php foreach ($vehicles as $v): ?>
                                <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['unit_name']) ?> (<?= $v['type'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Hazardous Notes -->
                <div>
                    <label class="block text-slate-400 font-bold uppercase tracking-wider text-[10px] mb-1">Hazardous Notes / Access Hazards</label>
                    <textarea name="notes" rows="2" placeholder="Chemicals stored on site, electrical transformers, narrow alleyway access..." class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-rose-500"></textarea>
                </div>

                <!-- Submit Button -->
                <div class="pt-3 border-t border-slate-800 flex items-center justify-end gap-3">
                    <button type="button" onclick="document.getElementById('intakeModal').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-[#e11d48] hover:bg-[#be123c] text-white font-extrabold shadow-lg shadow-rose-950/60 transition-all flex items-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-tower-broadcast"></i>
                        <span>Dispatch Emergency Alarm</span>
                    </button>
                </div>
            </form>

        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 2: DASHBOARD INTEGRATION & EMBED CODE GENERATOR -->
    <!-- ========================================================================= -->
    <div id="embedModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-[#090d16] border border-slate-800 rounded-3xl max-w-lg w-full p-6 shadow-2xl relative space-y-4 text-xs font-mono">
            
            <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-code text-rose-500"></i>
                    <h3 class="text-sm font-extrabold text-white">Embed Fire CAD into Parent Dashboards</h3>
                </div>
                <button type="button" onclick="document.getElementById('embedModal').classList.add('hidden')" class="text-slate-400 hover:text-white p-1">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <p class="text-slate-400 leading-relaxed font-sans text-xs">
                You can embed this self-contained Fire CAD system into any portal or React/PHP dashboard using the zero-friction snippets below:
            </p>

            <div class="space-y-2">
                <label class="block text-slate-400 font-bold uppercase tracking-wider text-[10px]">1. Iframe Embed Snippet</label>
                <div class="relative">
                    <textarea readonly rows="2" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-300 select-all">&lt;iframe src="http://localhost/DisasterSafe/fire_hub.php?embed=true" width="100%" height="750" frameborder="0" allow="geolocation"&gt;&lt;/iframe&gt;</textarea>
                </div>
            </div>

            <div class="space-y-2">
                <label class="block text-slate-400 font-bold uppercase tracking-wider text-[10px]">2. Cross-Window Listener (Parent Javascript)</label>
                <textarea readonly rows="3" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-300 select-all">window.addEventListener('message', (e) => {
    if (e.data && e.data.type === 'FIRE_INCIDENT_REPORTED') {
        console.log('Live Fire Alarm Paged:', e.data.incident);
    }
});</textarea>
            </div>

            <div class="pt-2 flex justify-end">
                <button type="button" onclick="document.getElementById('embedModal').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold">
                    Close
                </button>
            </div>

        </div>
    </div>

    <!-- Core App JS Engine -->
    <script src="assets/js/sound.js"></script>
    <script src="assets/js/map.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
