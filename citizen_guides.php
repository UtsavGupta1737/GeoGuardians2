<?php
// citizen_guides.php - DisasterSafe Interactive Survival Guides (Converted from React to PHP)
define('PAGE_TITLE', 'Disaster Safety Guides');
require_once __DIR__ . '/auth.php';

$currentUser = getCurrentUser($pdo);
$userName = $currentUser['name'] ?? 'Citizen';
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-[#fbf9f5] text-[#1c1917]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disaster Safety & Survival Protocols | DisasterSafe</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        cream: {
                            50: '#fbf9f5',
                            100: '#f4f0ea',
                            200: '#eee7db',
                            300: '#d8d0c5',
                            400: '#b8ad9e'
                        },
                        navy: {
                            950: '#000a1e',
                            900: '#0a0f1d',
                            800: '#11192e',
                            700: '#1c2b4e'
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace']
                    }
                }
            }
        }
    </script>
    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f4f0ea;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #d8d0c5;
            border-radius: 4px;
        }
        .guide-drawer-open {
            transform: translateX(0) !important;
        }
    </style>
</head>
<body class="min-h-screen bg-[#fbf9f5] text-[#1c1917] font-sans antialiased flex flex-col">

    <!-- Top Navigation Header -->
    <header class="sticky top-0 z-50 bg-[#000a1e] border-b border-[#1c2b4e] text-white shadow-lg backdrop-blur-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-red-600 to-amber-500 flex items-center justify-center font-black text-white text-xl shadow-md shadow-red-900/50">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-lg font-extrabold text-white tracking-tight">DisasterSafe</span>
                            <span class="px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-400 border border-amber-500/30 text-[10px] font-bold uppercase tracking-wider">Safety Guides</span>
                        </div>
                        <p class="text-[11px] text-slate-400 font-medium hidden sm:block">Step-by-Step Offline Preparedness Protocols</p>
                    </div>
                </div>

                <nav class="hidden md:flex items-center gap-1 bg-[#11192e] p-1 rounded-xl border border-[#243049]">
                    <a href="citizen.php" class="px-3.5 py-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 font-semibold text-xs flex items-center gap-2 transition-all">
                        <i class="fa-solid fa-tower-broadcast text-xs text-red-400"></i>
                        <span>Emergency SOS</span>
                    </a>
                    <a href="citizen_guides.php" class="px-3.5 py-1.5 rounded-lg bg-amber-600 text-white font-bold text-xs flex items-center gap-2 shadow-sm transition-all">
                        <i class="fa-solid fa-book-medical text-xs"></i>
                        <span>Safety Guides</span>
                    </a>
                    <a href="citizen_contacts.php" class="px-3.5 py-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 font-semibold text-xs flex items-center gap-2 transition-all">
                        <i class="fa-solid fa-phone-volume text-xs text-teal-400"></i>
                        <span>Emergency Directory</span>
                    </a>
                </nav>

                <div class="flex items-center gap-3">
                    <a href="citizen.php" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-red-600 hover:bg-red-500 text-white text-xs font-bold transition-all shadow-md shadow-red-950/40">
                        <i class="fa-solid fa-tower-broadcast text-xs animate-pulse"></i>
                        <span>Transmit SOS</span>
                    </a>
                    <div class="flex items-center gap-2.5 pl-2 border-l border-[#243049]">
                        <span class="hidden sm:block text-xs font-bold text-slate-200"><?= htmlspecialchars($userName) ?></span>
                        <a href="logout.php" title="Sign Out" class="p-2 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 transition-colors">
                            <i class="fa-solid fa-right-from-bracket text-xs"></i>
                        </a>
                    </div>
                </div>

            </div>
        </div>

        <div class="md:hidden flex items-center justify-around border-t border-[#1c2b4e] bg-[#0a0f1d] px-2 py-2 text-xs">
            <a href="citizen.php" class="px-3 py-1 rounded-lg text-slate-400 font-semibold flex items-center gap-1.5">
                <i class="fa-solid fa-tower-broadcast text-red-400"></i> SOS Beacon
            </a>
            <a href="citizen_guides.php" class="px-3 py-1 rounded-lg bg-amber-600/20 text-amber-400 font-bold flex items-center gap-1.5">
                <i class="fa-solid fa-book-medical"></i> Guides
            </a>
            <a href="citizen_contacts.php" class="px-3 py-1 rounded-lg text-slate-400 font-semibold flex items-center gap-1.5">
                <i class="fa-solid fa-phone-volume text-teal-400"></i> Helplines
            </a>
        </div>
    </header>

    <!-- Main Body -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 lg:p-8 space-y-6">
        
        <!-- Hero Title Banner -->
        <section class="bg-[#f4f0ea] border border-[#d8d0c5] rounded-3xl p-6 sm:p-8 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div class="space-y-1.5 max-w-2xl">
                <span class="text-xs font-extrabold uppercase tracking-widest text-amber-700">Official Survival Checklists</span>
                <h1 class="text-2xl sm:text-3xl font-black text-[#000a1e] tracking-tight">Disaster Response & First-Aid Guides</h1>
                <p class="text-sm text-[#586377] leading-relaxed">
                    Interactive Before, During, and After action checklists. All progress is saved automatically on your device so you can prepare offline.
                </p>
            </div>
            
            <!-- Search Filter Input -->
            <div class="w-full md:w-72">
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input type="text" id="guideSearchInput" oninput="filterGuides()" placeholder="Search protocols, injuries…"
                           class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-white border border-[#d8d0c5] text-sm text-[#1c1917] focus:outline-none focus:border-amber-600 transition-colors">
                </div>
            </div>
        </section>

        <!-- 6 Main Guides Cards Grid -->
        <section class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5" id="guidesGrid">
            
            <!-- 1. Flash Flood -->
            <div class="guide-card bg-white rounded-3xl border border-[#d8d0c5] p-6 shadow-xs hover:border-blue-500 hover:shadow-md transition-all space-y-4 flex flex-col justify-between" data-category="flood" data-title="Flash Flood & Water Inflow">
                <div>
                    <div class="w-12 h-12 rounded-2xl bg-blue-100 border border-blue-200 text-blue-600 flex items-center justify-center text-2xl mb-4">
                        <i class="fa-solid fa-water"></i>
                    </div>
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <h3 class="text-lg font-bold text-[#000a1e]">Flash Flood Response</h3>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-50 text-blue-800 border border-blue-200">NDRF 1070</span>
                    </div>
                    <p class="text-xs text-[#586377] leading-relaxed mb-3">
                        Turn around, don't drown. Move to rooftop or high ground, disconnect electrical mains, and signal rescuers.
                    </p>
                </div>
                <div>
                    <div class="flex items-center justify-between text-xs font-bold text-[#78716c] mb-1.5">
                        <span>Checklist Steps</span>
                        <span id="stat_flood" class="text-blue-600 font-mono">0/12 Done</span>
                    </div>
                    <div class="w-full h-2 rounded-full bg-[#eee7db] overflow-hidden mb-3">
                        <div id="bar_flood" class="h-full bg-blue-600 rounded-full transition-all duration-300" style="width: 0%;"></div>
                    </div>
                    <button type="button" onclick="openGuideModal('flood')" class="w-full py-2.5 rounded-xl bg-[#000a1e] hover:bg-[#11192e] text-white text-xs font-bold transition-all flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-list-check text-blue-400"></i>
                        <span>Open Flood Checklist</span>
                    </button>
                </div>
            </div>

            <!-- 2. Earthquake -->
            <div class="guide-card bg-white rounded-3xl border border-[#d8d0c5] p-6 shadow-xs hover:border-amber-500 hover:shadow-md transition-all space-y-4 flex flex-col justify-between" data-category="earthquake" data-title="Earthquake & Tremors">
                <div>
                    <div class="w-12 h-12 rounded-2xl bg-amber-100 border border-amber-200 text-amber-600 flex items-center justify-center text-2xl mb-4">
                        <i class="fa-solid fa-house-crack"></i>
                    </div>
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <h3 class="text-lg font-bold text-[#000a1e]">Earthquake Safety</h3>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-50 text-amber-800 border border-amber-200">NDMA 1078</span>
                    </div>
                    <p class="text-xs text-[#586377] leading-relaxed mb-3">
                        Drop, Cover, and Hold On. Protect your head and neck under sturdy furniture. Avoid windows, elevators, and masonry walls.
                    </p>
                </div>
                <div>
                    <div class="flex items-center justify-between text-xs font-bold text-[#78716c] mb-1.5">
                        <span>Checklist Steps</span>
                        <span id="stat_earthquake" class="text-amber-600 font-mono">0/12 Done</span>
                    </div>
                    <div class="w-full h-2 rounded-full bg-[#eee7db] overflow-hidden mb-3">
                        <div id="bar_earthquake" class="h-full bg-amber-600 rounded-full transition-all duration-300" style="width: 0%;"></div>
                    </div>
                    <button type="button" onclick="openGuideModal('earthquake')" class="w-full py-2.5 rounded-xl bg-[#000a1e] hover:bg-[#11192e] text-white text-xs font-bold transition-all flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-list-check text-amber-400"></i>
                        <span>Open Earthquake Checklist</span>
                    </button>
                </div>
            </div>

            <!-- 3. Fire & Hazmat -->
            <div class="guide-card bg-white rounded-3xl border border-[#d8d0c5] p-6 shadow-xs hover:border-red-500 hover:shadow-md transition-all space-y-4 flex flex-col justify-between" data-category="fire" data-title="Fire & Smoke Hazard">
                <div>
                    <div class="w-12 h-12 rounded-2xl bg-red-100 border border-red-200 text-red-600 flex items-center justify-center text-2xl mb-4">
                        <i class="fa-solid fa-fire"></i>
                    </div>
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <h3 class="text-lg font-bold text-[#000a1e]">Fire & Hazmat Rescue</h3>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-red-50 text-red-800 border border-red-200">Fire 101</span>
                    </div>
                    <p class="text-xs text-[#586377] leading-relaxed mb-3">
                        Crawl low under smoke where air is cleaner. Feel doors with back of hand before opening. Stop, drop, and roll if clothes ignite.
                    </p>
                </div>
                <div>
                    <div class="flex items-center justify-between text-xs font-bold text-[#78716c] mb-1.5">
                        <span>Checklist Steps</span>
                        <span id="stat_fire" class="text-red-600 font-mono">0/12 Done</span>
                    </div>
                    <div class="w-full h-2 rounded-full bg-[#eee7db] overflow-hidden mb-3">
                        <div id="bar_fire" class="h-full bg-red-600 rounded-full transition-all duration-300" style="width: 0%;"></div>
                    </div>
                    <button type="button" onclick="openGuideModal('fire')" class="w-full py-2.5 rounded-xl bg-[#000a1e] hover:bg-[#11192e] text-white text-xs font-bold transition-all flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-list-check text-red-400"></i>
                        <span>Open Fire Checklist</span>
                    </button>
                </div>
            </div>

            <!-- 4. Cyclone & Gale Storm -->
            <div class="guide-card bg-white rounded-3xl border border-[#d8d0c5] p-6 shadow-xs hover:border-purple-500 hover:shadow-md transition-all space-y-4 flex flex-col justify-between" data-category="cyclone" data-title="Cyclone & Severe Gale">
                <div>
                    <div class="w-12 h-12 rounded-2xl bg-purple-100 border border-purple-200 text-purple-600 flex items-center justify-center text-2xl mb-4">
                        <i class="fa-solid fa-tornado"></i>
                    </div>
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <h3 class="text-lg font-bold text-[#000a1e]">Cyclone & Severe Storm</h3>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-purple-50 text-purple-800 border border-purple-200">NDRF 1070</span>
                    </div>
                    <p class="text-xs text-[#586377] leading-relaxed mb-3">
                        Board glass windows, secure loose objects, store 72 hours of drinking water, and stay inside interior reinforced rooms.
                    </p>
                </div>
                <div>
                    <div class="flex items-center justify-between text-xs font-bold text-[#78716c] mb-1.5">
                        <span>Checklist Steps</span>
                        <span id="stat_cyclone" class="text-purple-600 font-mono">0/12 Done</span>
                    </div>
                    <div class="w-full h-2 rounded-full bg-[#eee7db] overflow-hidden mb-3">
                        <div id="bar_cyclone" class="h-full bg-purple-600 rounded-full transition-all duration-300" style="width: 0%;"></div>
                    </div>
                    <button type="button" onclick="openGuideModal('cyclone')" class="w-full py-2.5 rounded-xl bg-[#000a1e] hover:bg-[#11192e] text-white text-xs font-bold transition-all flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-list-check text-purple-400"></i>
                        <span>Open Cyclone Checklist</span>
                    </button>
                </div>
            </div>

            <!-- 5. First Aid & Trauma Care -->
            <div class="guide-card bg-white rounded-3xl border border-[#d8d0c5] p-6 shadow-xs hover:border-emerald-500 hover:shadow-md transition-all space-y-4 flex flex-col justify-between" data-category="first_aid" data-title="First Aid & Trauma Management">
                <div>
                    <div class="w-12 h-12 rounded-2xl bg-emerald-100 border border-emerald-200 text-emerald-600 flex items-center justify-center text-2xl mb-4">
                        <i class="fa-solid fa-kit-medical"></i>
                    </div>
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <h3 class="text-lg font-bold text-[#000a1e]">First-Aid & Trauma Care</h3>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200">EMS 108</span>
                    </div>
                    <p class="text-xs text-[#586377] leading-relaxed mb-3">
                        Control severe arterial bleeding with direct pressure, immobilize spine fractures, keep victims warm, and manage airway recovery.
                    </p>
                </div>
                <div>
                    <div class="flex items-center justify-between text-xs font-bold text-[#78716c] mb-1.5">
                        <span>Checklist Steps</span>
                        <span id="stat_first_aid" class="text-emerald-600 font-mono">0/12 Done</span>
                    </div>
                    <div class="w-full h-2 rounded-full bg-[#eee7db] overflow-hidden mb-3">
                        <div id="bar_first_aid" class="h-full bg-emerald-600 rounded-full transition-all duration-300" style="width: 0%;"></div>
                    </div>
                    <button type="button" onclick="openGuideModal('first_aid')" class="w-full py-2.5 rounded-xl bg-[#000a1e] hover:bg-[#11192e] text-white text-xs font-bold transition-all flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-list-check text-emerald-400"></i>
                        <span>Open First-Aid Checklist</span>
                    </button>
                </div>
            </div>

            <!-- 6. Landslide & Debris Flow -->
            <div class="guide-card bg-white rounded-3xl border border-[#d8d0c5] p-6 shadow-xs hover:border-amber-700 hover:shadow-md transition-all space-y-4 flex flex-col justify-between" data-category="landslide" data-title="Landslide & Rockfall">
                <div>
                    <div class="w-12 h-12 rounded-2xl bg-orange-100 border border-orange-200 text-orange-700 flex items-center justify-center text-2xl mb-4">
                        <i class="fa-solid fa-mountain"></i>
                    </div>
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <h3 class="text-lg font-bold text-[#000a1e]">Landslide & Slope Hazard</h3>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-orange-50 text-orange-800 border border-orange-200">NDRF 1070</span>
                    </div>
                    <p class="text-xs text-[#586377] leading-relaxed mb-3">
                        Listen for rumbling sounds and cracking trees. Evacuate perpendicular to path of flow. Avoid low-lying river valleys.
                    </p>
                </div>
                <div>
                    <div class="flex items-center justify-between text-xs font-bold text-[#78716c] mb-1.5">
                        <span>Checklist Steps</span>
                        <span id="stat_landslide" class="text-orange-700 font-mono">0/12 Done</span>
                    </div>
                    <div class="w-full h-2 rounded-full bg-[#eee7db] overflow-hidden mb-3">
                        <div id="bar_landslide" class="h-full bg-orange-700 rounded-full transition-all duration-300" style="width: 0%;"></div>
                    </div>
                    <button type="button" onclick="openGuideModal('landslide')" class="w-full py-2.5 rounded-xl bg-[#000a1e] hover:bg-[#11192e] text-white text-xs font-bold transition-all flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-list-check text-orange-400"></i>
                        <span>Open Landslide Checklist</span>
                    </button>
                </div>
            </div>

        </section>

    </main>

    <!-- Side Drawer Modal for Checklist View (Yukta's Interactive Drawer) -->
    <div id="guideDrawerBackdrop" onclick="closeGuideModal()" class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs z-50 hidden transition-opacity"></div>
    
    <aside id="guideDrawer" class="fixed top-0 right-0 h-full w-full max-w-lg bg-white border-l border-[#d8d0c5] shadow-2xl z-50 transform translate-x-full transition-transform duration-300 flex flex-col">
        
        <!-- Drawer Header -->
        <div class="p-5 sm:p-6 bg-[#f4f0ea] border-b border-[#d8d0c5] flex items-start justify-between gap-3">
            <div class="flex items-center gap-3">
                <div id="modalIconWrap" class="w-12 h-12 rounded-2xl bg-red-100 text-red-600 flex items-center justify-center text-2xl shrink-0">
                    <i id="modalIcon" class="fa-solid fa-water"></i>
                </div>
                <div>
                    <h3 id="modalTitle" class="text-lg font-black text-[#000a1e] leading-tight">Disaster Guide</h3>
                    <p id="modalSubtitle" class="text-xs text-[#78716c] font-medium">Standard Operating Procedure</p>
                </div>
            </div>
            <button type="button" onclick="closeGuideModal()" class="w-8 h-8 rounded-full bg-[#d8d0c5]/50 hover:bg-[#d8d0c5] flex items-center justify-center text-[#000a1e] transition-colors cursor-pointer">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <!-- 3-Phase Tab Selector (Before, During, After) -->
        <div class="flex border-b border-[#d8d0c5] bg-[#fbf9f5] px-4">
            <button type="button" onclick="switchPhase('before')" id="phaseTab_before" class="flex-1 py-3 text-xs font-bold border-b-2 border-red-600 text-red-600 transition-colors">
                1. BEFORE CRISIS
            </button>
            <button type="button" onclick="switchPhase('during')" id="phaseTab_during" class="flex-1 py-3 text-xs font-bold border-b-2 border-transparent text-[#78716c] hover:text-[#000a1e] transition-colors">
                2. DURING EVENT
            </button>
            <button type="button" onclick="switchPhase('after')" id="phaseTab_after" class="flex-1 py-3 text-xs font-bold border-b-2 border-transparent text-[#78716c] hover:text-[#000a1e] transition-colors">
                3. AFTERMATH
            </button>
        </div>

        <!-- Checklist Content Body -->
        <div class="flex-1 overflow-y-auto p-5 sm:p-6 space-y-3.5 bg-white custom-scrollbar">
            <div class="flex items-center justify-between text-xs text-[#78716c] pb-2 border-b border-[#eee7db]">
                <span id="phaseStepCount" class="font-bold">0 / 4 Steps Completed</span>
                <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                    <i class="fa-solid fa-check mr-1"></i> Auto-Saved Offline
                </span>
            </div>

            <div id="checklistItemsContainer" class="space-y-2.5">
                <!-- Checkboxes dynamically rendered via JavaScript -->
            </div>
        </div>

        <!-- Drawer Footer -->
        <div class="p-4 bg-[#f4f0ea] border-t border-[#d8d0c5] flex items-center justify-between gap-3">
            <a href="citizen.php" class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-500 text-white text-xs font-bold transition-all shadow-sm">
                <i class="fa-solid fa-tower-broadcast mr-1"></i> Send Emergency SOS
            </a>
            <button type="button" onclick="closeGuideModal()" class="px-4 py-2 rounded-xl bg-white border border-[#d8d0c5] hover:bg-[#eee7db] text-xs font-bold text-[#000a1e] transition-colors">
                Close Guide
            </button>
        </div>

    </aside>

    <!-- Footer -->
    <footer class="mt-auto bg-[#000a1e] border-t border-[#1c2b4e] py-4 text-center text-xs text-slate-400">
        <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-2">
            <span>&copy; 2026 DisasterSafe Crisis Management Suite • GeoGuardians</span>
            <div class="flex items-center gap-4 text-slate-300">
                <a href="citizen.php" class="hover:text-white">Emergency SOS</a>
                <span>•</span>
                <a href="citizen_contacts.php" class="hover:text-white">Helpline Directory</a>
            </div>
        </div>
    </footer>

    <!-- Interactive Checklist Data & Engine -->
    <script>
        const GUIDES_DATA = {
            flood: {
                title: "Flash Flood Survival Protocol",
                subtitle: "Rising Water, Submerged Roads & Rooftop Rescue",
                icon: "fa-water",
                colorClass: "bg-blue-100 text-blue-600",
                phases: {
                    before: [
                        "Pack waterproof emergency kit with essential medicines, water purification tablets, and documents.",
                        "Locate the highest reachable high-ground point or certified relief shelter in your sector.",
                        "Switch off main electrical circuit breaker and LPG gas cylinder valves immediately.",
                        "Charge all mobile devices and backup power banks to 100%."
                    ],
                    during: [
                        "Never walk, swim, or drive through moving floodwaters — 6 inches can knock you down.",
                        "If water enters house, evacuate to the highest floor or roof with signaling items (white cloth/torch).",
                        "Avoid touching any submerged electrical wiring, poles, or fallen utility cables.",
                        "Do not drink unfiltered tap/flood water; consume only boiled or bottled water."
                    ],
                    after: [
                        "Return home only after local disaster management authorities issue a formal all-clear.",
                        "Watch out for snakes, insects, and sharp debris carried into premises by floodwaters.",
                        "Photograph property damage for insurance and government disaster relief claims.",
                        "Disinfect all flooded rooms, utensils, and surfaces before reuse."
                    ]
                }
            },
            earthquake: {
                title: "Earthquake & Tremor Safety Protocol",
                subtitle: "Drop, Cover, and Hold On Drill",
                icon: "fa-house-crack",
                colorClass: "bg-amber-100 text-amber-600",
                phases: {
                    before: [
                        "Fasten heavy wardrobes, bookshelves, and water heaters securely to structural wall studs.",
                        "Identify safe interior drop-zones (under sturdy study tables, away from exterior glass windows).",
                        "Prepare a grab-and-go trauma kit with flashlight, whistle, and spare water bottles.",
                        "Conduct family drop-cover-hold drills twice a year."
                    ],
                    during: [
                        "DROP to your hands and knees immediately to prevent being violently thrown.",
                        "COVER your head and neck under a sturdy desk or interior table.",
                        "HOLD ON until the main seismic shaking stops completely.",
                        "If in high-rise: Do NOT use elevators under any circumstances. Take stairwells once shaking ceases."
                    ],
                    after: [
                        "Expect and prepare for strong aftershocks — stay away from damaged brick walls.",
                        "Check for gas leaks by smell. If smelled, open windows and evacuate immediately.",
                        "Wear sturdy shoes to protect feet from shattered glass and concrete shards.",
                        "Use whistle to signal search and rescue teams if trapped under rubble."
                    ]
                }
            },
            fire: {
                title: "Fire & Smoke Escape Protocol",
                subtitle: "Smoke Ventilation & Burn Mitigation",
                icon: "fa-fire",
                colorClass: "bg-red-100 text-red-600",
                phases: {
                    before: [
                        "Test smoke detectors and ensure fire extinguishers (ABC type) are serviced.",
                        "Plan two clear escape routes from every room in your residence.",
                        "Keep emergency stairways and fire exits completely clear of storage boxes.",
                        "Memorize local Fire Department emergency direct line: 101."
                    ],
                    during: [
                        "CRAWL LOW under smoke — clean, breathable oxygen remains closest to the floor.",
                        "Feel closed doors with the back of hand before turning handle; if hot, do NOT open.",
                        "Cover mouth and nose with a damp towel or cloth to filter toxic smoke particles.",
                        "If clothes catch fire: STOP, DROP to the ground, and ROLL repeatedly."
                    ],
                    after: [
                        "Once evacuated, stay out! Never re-enter a burning structure for personal possessions.",
                        "Cool minor thermal burns under clean running cold water for 15-20 minutes.",
                        "Report to EMS paramedics for smoke inhalation checkup even if feeling normal.",
                        "Wait for Fire Chief clearance before entering structure."
                    ]
                }
            },
            cyclone: {
                title: "Cyclone & Gale Storm Protocol",
                subtitle: "High-Velocity Winds & Flying Debris Protection",
                icon: "fa-tornado",
                colorClass: "bg-purple-100 text-purple-600",
                phases: {
                    before: [
                        "Trim dead tree branches hanging near power cables and rooftop solar panels.",
                        "Board up or tape large glass windows in criss-cross pattern to prevent fragmentation.",
                        "Stock 3 days of non-perishable canned food, drinking water, and battery radios.",
                        "Secure all loose outdoor furniture, tin sheds, and water tanks."
                    ],
                    during: [
                        "Stay inside the safest interior room away from exterior windows and glass doors.",
                        "Disconnect all television antennas, air conditioner cords, and electrical appliances.",
                        "Beware the 'eye of the storm' — a temporary lull followed by fierce winds from opposite direction.",
                        "Keep radio tuned to IMD/DisasterSafe official bulletins."
                    ],
                    after: [
                        "Stay clear of fallen electrical wires and water puddles near electric transformers.",
                        "Do not go sight-seeing in affected zones; keep roads open for emergency convoys.",
                        "Boil all drinking water thoroughly before consuming.",
                        "Report downed power lines to regional electricity board."
                    ]
                }
            },
            first_aid: {
                title: "First-Aid & Trauma Emergency Protocol",
                subtitle: "Hemorrhage Control & Basic Life Support",
                icon: "fa-kit-medical",
                colorClass: "bg-emerald-100 text-emerald-600",
                phases: {
                    before: [
                        "Stock comprehensive trauma first aid box (sterile gauze, tourniquet, antiseptic, splints).",
                        "Learn compression-only CPR (100-120 chest compressions per minute).",
                        "Keep list of blood groups and emergency contacts taped on refrigerator.",
                        "Check expiry dates of burn ointments, painkillers, and antiseptic lotions."
                    ],
                    during: [
                        "Severe Bleeding: Apply firm, continuous direct pressure with sterile dressing directly on wound.",
                        "Suspected Bone Fracture: Immobilize joint above and below fracture with rigid splint; do not force bone back.",
                        "Choking Victim: Perform Heimlich abdominal thrusts upwards until airway clears.",
                        "Unresponsive & Not Breathing: Call 108 immediately and begin continuous CPR chest compressions."
                    ],
                    after: [
                        "Keep injured victims calm, warm, and lying down to prevent shock.",
                        "Do not administer fluids or oral medication to an unconscious or drowsy patient.",
                        "Hand over patient to EMS paramedics with written record of time incident occurred.",
                        "Restock used first-aid supplies immediately."
                    ]
                }
            },
            landslide: {
                title: "Landslide & Slope Hazard Protocol",
                subtitle: "Debris Flow & Mountain Road Evacuation",
                icon: "fa-mountain",
                colorClass: "bg-orange-100 text-orange-700",
                phases: {
                    before: [
                        "Monitor local weather alerts for intense prolonged mountain rainfall.",
                        "Inspect retaining walls and hill slopes for new cracks or soil bulges.",
                        "Know the designated uphill evacuation routes in your hilly settlement.",
                        "Avoid constructing structures near steep drainage gullies."
                    ],
                    during: [
                        "If you hear a deep rumbling or cracking trees, evacuate uphill perpendicular to flow path.",
                        "Avoid low-lying stream beds and river valleys which channel fast debris flows.",
                        "If trapped inside building, curl into tight ball under heavy table and protect head.",
                        "Never cross a road where debris or mud is currently moving."
                    ],
                    after: [
                        "Stay away from slide zone — secondary slope failures frequently follow initial slides.",
                        "Check for trapped and injured persons near slope edges without entering danger zone.",
                        "Report broken utility gas lines and water mains to authorities immediately.",
                        "Assist neighbors requiring special evacuation assistance."
                    ]
                }
            }
        };

        let currentActiveGuideKey = 'flood';
        let currentActivePhase = 'before';

        function openGuideModal(guideKey) {
            currentActiveGuideKey = guideKey;
            currentActivePhase = 'before';
            const guide = GUIDES_DATA[guideKey];
            if (!guide) return;

            document.getElementById('modalTitle').textContent = guide.title;
            document.getElementById('modalSubtitle').textContent = guide.subtitle;
            document.getElementById('modalIcon').className = `fa-solid ${guide.icon}`;
            document.getElementById('modalIconWrap').className = `w-12 h-12 rounded-2xl ${guide.colorClass} flex items-center justify-center text-2xl shrink-0`;

            switchPhase('before');

            document.getElementById('guideDrawerBackdrop').classList.remove('hidden');
            document.getElementById('guideDrawer').classList.add('guide-drawer-open');
        }

        function closeGuideModal() {
            document.getElementById('guideDrawerBackdrop').classList.add('hidden');
            document.getElementById('guideDrawer').classList.remove('guide-drawer-open');
        }

        function switchPhase(phaseKey) {
            currentActivePhase = phaseKey;

            // Highlight active tab
            ['before', 'during', 'after'].forEach(p => {
                const tab = document.getElementById(`phaseTab_${p}`);
                if (p === phaseKey) {
                    tab.className = "flex-1 py-3 text-xs font-bold border-b-2 border-red-600 text-red-600 transition-colors";
                } else {
                    tab.className = "flex-1 py-3 text-xs font-bold border-b-2 border-transparent text-[#78716c] hover:text-[#000a1e] transition-colors";
                }
            });

            renderChecklistItems();
        }

        function renderChecklistItems() {
            const guide = GUIDES_DATA[currentActiveGuideKey];
            const items = guide.phases[currentActivePhase] || [];
            const container = document.getElementById('checklistItemsContainer');
            container.innerHTML = '';

            let checkedCount = 0;

            items.forEach((itemText, idx) => {
                const storageKey = `ds_guide_${currentActiveGuideKey}_${currentActivePhase}_${idx}`;
                const isChecked = localStorage.getItem(storageKey) === 'true';
                if (isChecked) checkedCount++;

                const itemCard = document.createElement('label');
                itemCard.className = `flex items-start gap-3 p-3.5 rounded-2xl border cursor-pointer select-none transition-all ${
                    isChecked ? 'bg-emerald-50/80 border-emerald-300' : 'bg-[#fbf9f5] border-[#d8d0c5] hover:border-slate-400'
                }`;

                itemCard.innerHTML = `
                    <input type="checkbox" ${isChecked ? 'checked' : ''} onchange="toggleCheckItem('${storageKey}', this)" class="mt-0.5 w-4 h-4 accent-emerald-600 rounded cursor-pointer shrink-0">
                    <div class="text-xs text-[#1c1917] leading-relaxed">
                        <strong class="${isChecked ? 'text-emerald-800' : 'text-[#000a1e]'} mr-1">Step ${idx + 1}:</strong>
                        <span>${escapeHtml(itemText)}</span>
                    </div>
                `;

                container.appendChild(itemCard);
            });

            document.getElementById('phaseStepCount').textContent = `${checkedCount} / ${items.length} Steps Completed`;
            updateAllOverviewStats();
        }

        function toggleCheckItem(storageKey, checkbox) {
            localStorage.setItem(storageKey, checkbox.checked ? 'true' : 'false');
            renderChecklistItems();
        }

        function updateAllOverviewStats() {
            Object.keys(GUIDES_DATA).forEach(guideKey => {
                const guide = GUIDES_DATA[guideKey];
                let totalSteps = 0;
                let doneSteps = 0;

                ['before', 'during', 'after'].forEach(phase => {
                    const items = guide.phases[phase] || [];
                    totalSteps += items.length;
                    items.forEach((_, idx) => {
                        if (localStorage.getItem(`ds_guide_${guideKey}_${phase}_${idx}`) === 'true') {
                            doneSteps++;
                        }
                    });
                });

                const statEl = document.getElementById(`stat_${guideKey}`);
                const barEl = document.getElementById(`bar_${guideKey}`);

                if (statEl) statEl.textContent = `${doneSteps}/${totalSteps} Done`;
                if (barEl) {
                    const pct = totalSteps > 0 ? Math.round((doneSteps / totalSteps) * 100) : 0;
                    barEl.style.width = `${pct}%`;
                }
            });
        }

        function filterGuides() {
            const query = document.getElementById('guideSearchInput').value.toLowerCase().trim();
            const cards = document.querySelectorAll('.guide-card');

            cards.forEach(card => {
                const title = card.getAttribute('data-title').toLowerCase();
                const cat = card.getAttribute('data-category').toLowerCase();
                const text = card.textContent.toLowerCase();

                if (!query || title.includes(query) || cat.includes(query) || text.includes(query)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function escapeHtml(text) {
            if (!text) return '';
            return text.replace(/[&<>"']/g, function(m) {
                return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[m];
            });
        }

        // Auto open modal if requested via URL param (e.g. ?guide=flood)
        document.addEventListener('DOMContentLoaded', () => {
            updateAllOverviewStats();
            const urlParams = new URLSearchParams(window.location.search);
            const guideParam = urlParams.get('guide');
            if (guideParam && GUIDES_DATA[guideParam]) {
                openGuideModal(guideParam);
            }
        });
    </script>
</body>
</html>
