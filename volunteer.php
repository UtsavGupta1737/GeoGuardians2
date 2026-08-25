<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$currentUser = getCurrentUser($pdo);
$userName = $currentUser['name'] ?? ($_SESSION['user_name'] ?? 'Volunteer');
$userId = $currentUser['id'] ?? ($_SESSION['user_id'] ?? 3);
?>
<!DOCTYPE html>
<html lang="en" data-role="volunteer">
<head>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
  <title>DisasterSafe - Volunteer Portal</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@600;700&display=swap" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
  <script src="js/volunteer_i18n.js"></script>
  <script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
  
  <style>
    *, *::before, *::after {
        border-radius: 0 !important;
    }
    body {
      font-family: 'Inter', system-ui, -apple-system, sans-serif;
      background-color: #f7f9fb;
      color: #191c1e;
      top: 0px !important;
      position: static !important;
    }
    .mono {
      font-family: 'JetBrains Mono', monospace;
    }
    .material-symbols-outlined,
    .notranslate,
    [translate="no"] {
      font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
      -webkit-translate: no !important;
      translate: no !important;
    }
    
    /* Suppress ALL Google Translate banners, tooltips, popups, and frames */
    .goog-te-banner-frame,
    .goog-te-balloon-frame,
    #goog-gt-tt,
    .goog-tooltip,
    .goog-tooltip:hover,
    .VIpgJd-yAWNEb-VIpgJd-fmcmS-sn54Q,
    .VIpgJd-ZVi9C-bHOHAd,
    .VIpgJd-yAWNEb-L7lbkb,
    .VIpgJd-ZVi9C-aZ2wEe,
    div[id*=":1."],
    div[id*=":2."],
    iframe[id*=":1."],
    iframe[id*=":2."],
    .skiptranslate iframe,
    iframe.goog-te-banner-frame,
    #google_translate_element {
      display: none !important;
      visibility: hidden !important;
      opacity: 0 !important;
      pointer-events: none !important;
      width: 0 !important;
      height: 0 !important;
      position: absolute !important;
      left: -9999px !important;
      top: -9999px !important;
    }

    .goog-text-highlight {
      background-color: transparent !important;
      box-shadow: none !important;
    }

    .tab-content {
      display: none !important;
    }
    #tab-assignments.tab-content.active {
      display: grid !important;
    }
    #tab-map.tab-content.active,
    #tab-resources.tab-content.active,
    #tab-guides.tab-content.active,
    #tab-profile.tab-content.active {
      display: flex !important;
    }
    .custom-scroll::-webkit-scrollbar {
      width: 5px;
    }
    .custom-scroll::-webkit-scrollbar-track {
      background: transparent;
    }
    .custom-scroll::-webkit-scrollbar-thumb {
      background-color: #cbd5e1;
      border-radius: 10px;
    }
    @keyframes pulseGlow {
      0%, 100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.4); }
      50% { box-shadow: 0 0 0 8px rgba(220, 38, 38, 0); }
    }
    .pulse-alert {
      animation: pulseGlow 2s ease-in-out infinite;
    }
    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(12px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .fade-in-up {
      animation: fadeInUp 0.35s ease-out;
    }
    .guide-icon-fire { color: #dc2626; }
    .guide-icon-flood { color: #2563eb; }
    .guide-icon-earthquake { color: #d97706; }
    .guide-icon-cyclone { color: #7c3aed; }
    .guide-icon-general { color: #059669; }

    /* Collapsible Sidebar Styles */
    #volunteerSidebar {
      transition: width 0.25s cubic-bezier(0.4, 0, 0.2, 1), padding 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    #volunteerSidebar.collapsed {
      width: 76px !important;
      padding-left: 0.5rem !important;
      padding-right: 0.5rem !important;
    }
    #volunteerSidebar.collapsed .sidebar-text,
    #volunteerSidebar.collapsed .sidebar-badge,
    #volunteerSidebar.collapsed .sidebar-section-title {
      display: none !important;
    }
    #volunteerSidebar.collapsed .sidebar-btn {
      justify-content: center !important;
      padding-left: 0.5rem !important;
      padding-right: 0.5rem !important;
      gap: 0 !important;
    }
    #volunteerSidebar.collapsed .sidebar-brand-wrapper {
      justify-content: center !important;
      padding-left: 0 !important;
      padding-right: 0 !important;
    }
    #volunteerSidebar.collapsed .proxy-btn-text {
      display: none !important;
    }
  </style>
</head>

<body class="h-screen overflow-hidden flex flex-col bg-[#f7f9fb] text-[#191c1e]">
  <div id="google_translate_element" style="display:none !important;"></div>
  
  <!-- ======================================================== -->
  <!-- TOP HEADER (CLEAN STATUS & PROFILE HEADER)               -->
  <!-- ======================================================== -->
  <header class="bg-white border-b border-[#e5e7eb] flex justify-between items-center w-full px-5 lg:px-6 h-16 shrink-0 z-30 shadow-xs">
    
    <!-- Left Operations Status & Sidebar Collapse Button -->
    <div class="flex items-center gap-3.5">
      <!-- Collapse Sidebar Button -->
      <button id="sidebarToggleBtn" onclick="toggleSidebar()" class="w-9 h-9 rounded-xl text-[#475569] hover:text-[#111827] hover:bg-[#f1f5f9] border border-gray-200 flex items-center justify-center transition-all cursor-pointer shadow-2xs" title="Toggle Sidebar (Expand/Collapse)">
        <span id="sidebarToggleIcon" class="material-symbols-outlined notranslate text-xl" translate="no">menu_open</span>
      </button>

      <div class="flex items-center gap-2">
        <div class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></div>
        <div class="text-sm font-bold text-[#111827]">
          DisasterSafe <span class="text-gray-400 font-normal">/</span> <span class="text-[#1d63d8]" data-i18n="portal_title">Volunteer Command Center</span>
        </div>
        <span class="hidden md:inline text-[10px] bg-emerald-50 text-emerald-700 font-bold px-2 py-0.5 rounded-full mono border border-emerald-200 ml-1">
          GPS ONLINE
        </span>
      </div>
    </div>

    <!-- Right Controls: Language, Notifications, Help, User Avatar -->
    <div class="flex items-center gap-2.5">
      
      <!-- Multi-Language Selector Dropdown -->
      <div class="relative flex items-center">
        <select id="languageSelect" onchange="changeLanguage(this.value)" class="bg-[#f8fafc] hover:bg-[#f1f5f9] text-[#111827] text-xs font-bold rounded-xl px-2.5 py-1.5 border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#1d63d8] transition-all cursor-pointer shadow-xs">
          <option value="en">🇬🇧 English</option>
          <option value="hi">🇮🇳 हिन्दी (Hindi)</option>
          <option value="bn">🇮🇳 বাংলা (Bengali)</option>
          <option value="ta">🇮🇳 தமிழ் (Tamil)</option>
          <option value="te">🇮🇳 తెలుగు (Telugu)</option>
          <option value="mr">🇮🇳 मराठी (Marathi)</option>
        </select>
      </div>

      <!-- Notifications Bell -->
      <button id="notifBell" class="relative hover:text-[#111827] p-2 text-[#475569] hover:bg-[#f1f5f9] rounded-xl transition-colors cursor-pointer" onclick="showNotificationsModal()">
        <span class="material-symbols-outlined notranslate text-[22px]" translate="no">notifications</span>
        <span id="notifDot" class="absolute top-1.5 right-1.5 w-2 h-2 bg-[#dc2626] rounded-full hidden"></span>
      </button>

      <!-- Help Button -->
      <button class="hover:text-[#111827] p-2 text-[#475569] hover:bg-[#f1f5f9] rounded-xl transition-colors cursor-pointer" onclick="showHelpModal()">
        <span class="material-symbols-outlined notranslate text-[22px]" translate="no">help</span>
      </button>

      <!-- User Avatar / Profile -->
      <div class="flex items-center gap-2 pl-2 border-l border-gray-200">
        <div class="w-8 h-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center font-bold text-xs shadow-xs cursor-pointer hover:ring-2 hover:ring-emerald-500/50 transition-all" onclick="switchVolunteerTab('profile')" title="View My Profile">
          <i class="fa-solid fa-hand-holding-heart text-xs"></i>
        </div>
      </div>

      <!-- Dedicated Header Sign Out Button (Consistent Across All Roles) -->
      <a href="logout.php" title="Sign Out of DisasterSafe" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-50 hover:bg-red-100 border border-red-200 text-xs font-bold text-red-700 transition-colors shadow-2xs">
        <span class="material-symbols-outlined notranslate text-sm text-red-600" translate="no">logout</span>
        <span class="hidden sm:inline">Logout</span>
      </a>
    </div>
  </header>

  <div class="flex flex-1 overflow-hidden">
    
    <!-- ======================================================== -->
    <!-- RICH COLLAPSIBLE SIDE NAVIGATION BAR                     -->
    <!-- ======================================================== -->
    <aside id="volunteerSidebar" class="w-64 bg-white border-r border-[#e5e7eb] flex flex-col p-4 shrink-0 z-20 overflow-y-auto custom-scroll">
      
      <!-- Brand & Duty Badge -->
      <div class="sidebar-brand-wrapper flex items-center justify-between px-1 py-1 mb-3">
        <div class="flex items-center gap-3 overflow-hidden">
          <div class="w-10 h-10 bg-[#111827] text-white rounded-xl flex items-center justify-center font-bold text-base shrink-0 shadow-sm">
            <span class="material-symbols-outlined notranslate text-xl" translate="no">shield</span>
          </div>
          <div class="sidebar-text truncate">
            <div class="text-base font-extrabold text-[#111827] leading-tight notranslate tracking-tight" translate="no">Disaster<span class="text-[#1d63d8]">Safe</span></div>
            <div class="text-[10px] text-[#64748b] font-bold uppercase tracking-wider mono" data-i18n="portal_subtitle">Volunteer Portal</div>
          </div>
        </div>
      </div>

      <!-- Quick Action: Report Citizen Proxy SOS -->
      <div class="mb-3 px-0.5">
        <button onclick="openProxySosModal()" title="Report Citizen SOS" class="w-full bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700 text-white font-bold text-xs py-2.5 px-2 rounded-xl transition-all shadow-xs flex items-center justify-center gap-2 cursor-pointer">
          <span class="material-symbols-outlined notranslate text-base shrink-0" translate="no">person_add_alt</span>
          <span class="proxy-btn-text truncate" data-i18n="btn_report_sos">Report Citizen SOS</span>
        </button>
      </div>

      <!-- Navigation Tabs -->
      <nav class="flex-1 flex flex-col gap-1.5">
        <div class="sidebar-section-title text-[10px] font-bold text-[#94a3b8] uppercase tracking-wider px-3 pt-1 mono">
          NAVIGATION
        </div>

        <button id="nav-assignments" onclick="switchVolunteerTab('assignments')" title="My Assignments" class="sidebar-btn flex items-center justify-between px-3.5 py-2.5 bg-[#dce6fe] text-[#1e3a8a] font-bold rounded-xl text-xs transition-all shadow-xs w-full text-left">
          <div class="flex items-center gap-3 overflow-hidden">
            <span class="material-symbols-outlined notranslate text-lg text-[#1e3a8a] shrink-0" translate="no">assignment</span>
            <span class="sidebar-text truncate" data-i18n="sidebar_assignments">My Assignments</span>
          </div>
          <span class="sidebar-badge w-2 h-2 rounded-full bg-[#1d63d8] shrink-0"></span>
        </button>

        <button id="nav-map" onclick="switchVolunteerTab('map')" title="Field Tactical Map" class="sidebar-btn flex items-center justify-between px-3.5 py-2.5 text-[#475569] hover:bg-[#f1f5f9] hover:text-[#111827] font-semibold rounded-xl text-xs transition-all w-full text-left">
          <div class="flex items-center gap-3 overflow-hidden">
            <span class="material-symbols-outlined notranslate text-lg text-[#64748b] shrink-0" translate="no">map</span>
            <span class="sidebar-text truncate" data-i18n="sidebar_map">Field Tactical Map</span>
          </div>
          <span class="sidebar-badge text-[9px] bg-green-100 text-green-800 font-extrabold px-1.5 py-0.2 rounded mono shrink-0">GPS</span>
        </button>

        <button id="nav-resources" onclick="switchVolunteerTab('resources')" title="Resource Collection" class="sidebar-btn flex items-center justify-between px-3.5 py-2.5 text-[#475569] hover:bg-[#f1f5f9] hover:text-[#111827] font-semibold rounded-xl text-xs transition-all w-full text-left">
          <div class="flex items-center gap-3 overflow-hidden">
            <span class="material-symbols-outlined notranslate text-lg text-[#64748b] shrink-0" translate="no">inventory_2</span>
            <span class="sidebar-text truncate" data-i18n="sidebar_resources">Resource Collection</span>
          </div>
        </button>

        <button id="nav-guides" onclick="switchVolunteerTab('guides')" title="Safety Guides" class="sidebar-btn flex items-center justify-between px-3.5 py-2.5 text-[#475569] hover:bg-[#f1f5f9] hover:text-[#111827] font-semibold rounded-xl text-xs transition-all w-full text-left">
          <div class="flex items-center gap-3 overflow-hidden">
            <span class="material-symbols-outlined notranslate text-lg text-[#64748b] shrink-0" translate="no">menu_book</span>
            <span class="sidebar-text truncate" data-i18n="sidebar_guides">Safety Guides</span>
          </div>
        </button>

        <button id="nav-profile" onclick="switchVolunteerTab('profile')" title="My Profile" class="sidebar-btn flex items-center justify-between px-3.5 py-2.5 text-[#475569] hover:bg-[#f1f5f9] hover:text-[#111827] font-semibold rounded-xl text-xs transition-all w-full text-left">
          <div class="flex items-center gap-3 overflow-hidden">
            <span class="material-symbols-outlined notranslate text-lg text-[#64748b] shrink-0" translate="no">account_circle</span>
            <span class="sidebar-text truncate" data-i18n="sidebar_profile">My Profile</span>
          </div>
        </button>
      </nav>

      <!-- Bottom SOS & Operations -->
      <div class="mt-auto pt-3 flex flex-col gap-2">
        <button onclick="triggerVolunteerDistress()" title="Emergency SOS" class="sidebar-btn w-full bg-[#880808] hover:bg-[#720606] text-white font-extrabold rounded-xl py-2.5 px-3 flex items-center justify-center gap-2 text-xs transition-all shadow-sm pulse-alert cursor-pointer">
          <span class="material-symbols-outlined notranslate text-base shrink-0" translate="no">emergency</span>
          <span class="sidebar-text truncate" data-i18n="sidebar_emergency_sos">Emergency SOS</span>
        </button>

        <div class="border-t border-[#e5e7eb] my-0.5"></div>

        <button onclick="showSystemStatusModal()" title="System Status" class="sidebar-btn flex items-center gap-3 px-3.5 py-2 text-[#475569] hover:bg-[#f1f5f9] hover:text-[#111827] rounded-xl text-xs font-semibold transition-all cursor-pointer w-full text-left">
          <span class="material-symbols-outlined notranslate text-base text-[#64748b] shrink-0" translate="no">analytics</span>
          <span class="sidebar-text truncate" data-i18n="sidebar_system_status">System Status</span>
        </button>

        <a href="logout.php" title="Logout" class="sidebar-btn flex items-center gap-3 px-3.5 py-2 text-[#475569] hover:bg-red-50 hover:text-red-700 rounded-xl text-xs font-semibold transition-all">
          <span class="material-symbols-outlined notranslate text-base shrink-0" translate="no">logout</span>
          <span class="sidebar-text truncate" data-i18n="sidebar_logout">Logout</span>
        </a>
      </div>
    </aside>

    <!-- ======================================================== -->
    <!-- MAIN WORKSPACE CONTENT AREA                              -->
    <!-- ======================================================== -->
    <main class="flex-1 p-6 overflow-y-auto bg-[#f7f9fb]">

      <!-- ======================================================== -->
      <!-- TAB 1: MY ASSIGNMENTS (CLEAN COMMAND CENTER ARCHITECTURE) -->
      <!-- ======================================================== -->
      <div id="tab-assignments" class="tab-content active max-w-[1520px] mx-auto grid grid-cols-1 xl:grid-cols-12 gap-6 items-start min-h-[calc(100vh-112px)]">

        <!-- ======================================================== -->
        <!-- LEFT PRIMARY STAGE: TACTICAL MAP & MISSIONS (XL: 7)     -->
        <!-- ======================================================== -->
        <div class="xl:col-span-7 flex flex-col gap-6">
          
          <!-- 1. SPATIOUS TACTICAL FIELD MAP -->
          <div class="bg-white border border-[#e5e7eb] rounded-2xl shadow-sm overflow-hidden flex flex-col relative min-h-[460px]">
            <!-- Map Header -->
            <div class="px-5 py-3.5 border-b border-[#e5e7eb] flex justify-between items-center bg-white z-10">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-blue-50 text-[#1d63d8] flex items-center justify-center font-bold shrink-0">
                  <span class="material-symbols-outlined notranslate text-xl" translate="no">map</span>
                </div>
                <div>
                  <div class="flex items-center gap-2">
                    <h3 class="text-base font-bold text-[#111827]" data-i18n="field_map_title">Tactical Field Map</h3>
                    <span class="text-[10px] bg-green-100 text-green-800 font-extrabold px-2 py-0.5 rounded-full mono" data-i18n="live_gps_badge">LIVE GPS</span>
                  </div>
                  <p class="text-[11px] text-[#64748b]">Live satellite positioning &amp; citizen evacuation corridors</p>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <button onclick="centerOnVolunteer()" class="px-3 py-1.5 bg-[#f1f5f9] hover:bg-[#e2e8f0] text-[#111827] rounded-lg text-xs font-bold transition-all flex items-center gap-1 cursor-pointer">
                  <span class="material-symbols-outlined notranslate text-sm" translate="no">my_location</span> <span class="hidden sm:inline">Center GPS</span>
                </button>
                <div class="flex gap-1">
                  <button onclick="mapInstance.zoomIn()" class="w-8 h-8 rounded-lg bg-[#f1f5f9] hover:bg-[#e2e8f0] text-[#334155] font-bold flex items-center justify-center transition-colors text-sm shadow-xs cursor-pointer">+</button>
                  <button onclick="mapInstance.zoomOut()" class="w-8 h-8 rounded-lg bg-[#f1f5f9] hover:bg-[#e2e8f0] text-[#334155] font-bold flex items-center justify-center transition-colors text-sm shadow-xs cursor-pointer">−</button>
                </div>
              </div>
            </div>

            <!-- Mini Stat Cards Overlay -->
            <div class="absolute top-16 left-4 z-[500] flex flex-wrap gap-2 pointer-events-none">
              <div class="bg-white/95 backdrop-blur-md border border-[#e2e8f0] rounded-xl px-3 py-1.5 shadow-sm flex items-center gap-2 pointer-events-auto">
                <span class="w-2 h-2 rounded-full bg-red-600 animate-ping"></span>
                <span class="text-[10px] font-bold text-[#64748b] uppercase mono">Rescues:</span>
                <span id="mapStatActive" class="text-xs font-extrabold text-red-600 mono">0</span>
              </div>
              <div class="bg-white/95 backdrop-blur-md border border-[#e2e8f0] rounded-xl px-3 py-1.5 shadow-sm flex items-center gap-2 pointer-events-auto">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span class="text-[10px] font-bold text-[#64748b] uppercase mono">Shelters:</span>
                <span id="mapStatShelters" class="text-xs font-extrabold text-emerald-600 mono">0</span>
              </div>
              <div class="bg-white/95 backdrop-blur-md border border-[#e2e8f0] rounded-xl px-3 py-1.5 shadow-sm flex items-center gap-2 pointer-events-auto">
                <span class="w-2 h-2 rounded-full bg-[#1d63d8]"></span>
                <span class="text-[10px] font-bold text-[#64748b] uppercase mono">Depots:</span>
                <span id="mapStatDepots" class="text-xs font-extrabold text-[#1d63d8] mono">0</span>
              </div>
              <div class="bg-white/95 backdrop-blur-md border border-[#e2e8f0] rounded-xl px-3 py-1.5 shadow-sm flex items-center gap-2 pointer-events-auto">
                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                <span class="text-[10px] font-bold text-[#64748b] uppercase mono">Hazards:</span>
                <span id="mapStatHazards" class="text-xs font-extrabold text-amber-600 mono">0</span>
              </div>
            </div>

            <!-- Layer Toggle -->
            <div class="absolute top-16 right-4 z-[500] bg-white/95 backdrop-blur-md border border-[#e2e8f0] rounded-xl p-2 shadow-sm flex flex-wrap items-center gap-3">
              <label class="flex items-center gap-1.5 cursor-pointer text-xs font-bold text-[#334155]">
                <input type="checkbox" checked class="w-3.5 h-3.5 text-red-600 rounded" onchange="toggleMapLayer('victims', this.checked)"/> Citizens in Need
              </label>
              <label class="flex items-center gap-1.5 cursor-pointer text-xs font-bold text-[#334155]">
                <input type="checkbox" checked class="w-3.5 h-3.5 text-emerald-600 rounded" onchange="toggleMapLayer('shelters', this.checked)"/> Shelters &amp; Camps
              </label>
              <label class="flex items-center gap-1.5 cursor-pointer text-xs font-bold text-[#334155]">
                <input type="checkbox" checked class="w-3.5 h-3.5 text-[#1d63d8] rounded" onchange="toggleMapLayer('resources', this.checked)"/> Depots
              </label>
              <label class="flex items-center gap-1.5 cursor-pointer text-xs font-bold text-[#334155]">
                <input type="checkbox" checked class="w-3.5 h-3.5 text-amber-600 rounded" onchange="toggleMapLayer('hazards', this.checked)"/> Danger Zones
              </label>
              <label class="flex items-center gap-1.5 cursor-pointer text-xs font-bold text-[#334155]">
                <input type="checkbox" checked class="w-3.5 h-3.5 text-purple-600 rounded" onchange="toggleMapLayer('route', this.checked)"/> Route
              </label>
            </div>

            <!-- Active Tactical Route HUD Banner -->
            <div id="mapActiveNavBanner" class="hidden px-4 py-2 bg-gradient-to-r from-blue-50 via-indigo-50 to-blue-50 border-t border-b border-blue-200 flex items-center justify-between text-xs z-[400] relative">
              <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-[#1d63d8] animate-ping"></span>
                <span id="navBannerText" class="text-[11px] font-bold text-[#1e3a8a] mono"></span>
              </div>
              <button type="button" onclick="centerOnRoute()" class="text-[10px] bg-white hover:bg-blue-100 text-[#1d63d8] border border-blue-300 font-extrabold px-2.5 py-1 rounded-lg cursor-pointer transition-colors shadow-xs flex items-center gap-1">
                <span class="material-symbols-outlined text-xs">navigation</span> Focus Corridor
              </button>
            </div>

            <!-- Leaflet Map Canvas -->
            <div class="flex-1 relative bg-[#e2e8f0]" style="min-height: 420px; height: 420px;">
              <div id="fieldMap" style="width: 100%; height: 100%; position: absolute; inset: 0; z-index: 1;"></div>
            </div>
          </div>

          <!-- 2. TWO-COLUMN SPLIT: ACTIVE ASSIGNMENT & RESOURCE CHECKLIST -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            
            <!-- Active Assignment Card -->
            <div class="bg-white border border-[#e5e7eb] rounded-2xl p-5 shadow-sm flex flex-col relative overflow-hidden">
              <div class="absolute top-0 left-0 w-full h-1 bg-[#1d63d8]"></div>
              
              <div class="flex justify-between items-center mb-3">
                <div class="flex items-center gap-2">
                  <span class="material-symbols-outlined notranslate text-[#1d63d8] text-lg" translate="no">assignment</span>
                  <h3 class="text-base font-bold text-[#111827]" data-i18n="active_assignment_title">Active Assignment</h3>
                </div>
                <span id="assignmentBadge" class="bg-[#dce6fe] text-[#1e3a8a] text-xs font-bold uppercase tracking-wider mono px-2.5 py-0.5 rounded-full" data-i18n="status_loading">
                  LOADING...
                </span>
              </div>

              <div id="assignmentContent" class="flex flex-col gap-3.5 flex-1">
                <!-- Victim Info -->
                <div>
                  <div class="text-[10px] font-bold text-[#64748b] uppercase tracking-wider mb-0.5 mono" data-i18n="victim_info_label">VICTIM INFO</div>
                  <div id="victimName" class="text-sm font-bold text-[#111827]">Loading...</div>
                  <div id="victimLocation" class="text-xs font-semibold text-[#64748b] flex items-center gap-1 mt-0.5">
                    <span class="material-symbols-outlined notranslate text-xs text-[#475569]" translate="no">location_on</span>
                    Loading location...
                  </div>
                </div>

                <!-- Condition Box -->
                <div class="p-3 bg-[#f8fafc] rounded-xl border border-[#e2e8f0]">
                  <div class="text-[10px] font-bold text-[#64748b] uppercase tracking-wider mb-0.5 mono" data-i18n="condition_priority_label">CONDITION / PRIORITY</div>
                  <div id="victimCondition" class="text-xs font-bold text-[#ba1a1a] flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-[#ba1a1a] shrink-0"></span>
                    Loading...
                  </div>
                </div>

                <!-- Primary Task -->
                <div>
                  <div class="text-[10px] font-bold text-[#64748b] uppercase tracking-wider mb-0.5 mono" data-i18n="primary_task_label">PRIMARY TASK</div>
                  <p id="primaryTask" class="text-xs font-medium text-[#334155] leading-relaxed">
                    Loading assignment details...
                  </p>
                </div>
              </div>

              <!-- No Assignment State -->
              <div id="noAssignment" class="hidden py-6 text-center flex flex-col items-center gap-2">
                <div class="w-12 h-12 rounded-full bg-blue-50 text-[#1d63d8] flex items-center justify-center font-bold shadow-xs">
                  <span class="material-symbols-outlined notranslate text-2xl" translate="no">bedtime</span>
                </div>
                <div>
                  <p class="text-xs font-bold text-[#111827]" data-i18n="no_assignment_title">No Active Mission • Resting / Standby</p>
                  <p class="text-[11px] text-[#64748b] mt-0.5">Take rest to recover from fatigue, or claim the next high-priority SOS dispatch.</p>
                </div>
                <button type="button" onclick="claimNextMission()" class="mt-2 px-4 py-2 bg-[#1d63d8] hover:bg-[#1553c7] text-white font-bold text-xs rounded-xl shadow-xs transition-all flex items-center gap-1.5 cursor-pointer">
                  <span class="material-symbols-outlined text-sm">bolt</span> Accept Next Available Mission
                </button>
              </div>

              <!-- Dynamic Action Buttons (Accept, En Route, Complete, Decline) -->
              <div id="assignmentActions" class="mt-4 flex flex-wrap gap-2">
                <!-- Dynamically populated in JavaScript based on assignment status -->
              </div>
            </div>

            <!-- Resource Checklist Card -->
            <div class="bg-white border border-[#e5e7eb] rounded-2xl p-5 shadow-sm flex flex-col justify-between">
              <div>
                <div class="flex justify-between items-center mb-2.5">
                  <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined notranslate text-emerald-600 text-lg" translate="no">inventory_2</span>
                    <h3 class="text-base font-bold text-[#111827]" data-i18n="resource_checklist_title">Resource Checklist</h3>
                  </div>
                  <span id="checklistCount" class="text-[10px] font-bold text-[#1d63d8] mono bg-[#dce6fe] px-2 py-0.5 rounded-full">0/0 READY</span>
                </div>

                <!-- Quick Preset Resource Adder Pills -->
                <div class="flex gap-1.5 overflow-x-auto custom-scroll pb-2 mb-2">
                  <button type="button" onclick="addChecklistPreset('Burn Trauma Treatment Kits (x25)')" class="text-[10px] font-bold bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-200 px-2 py-0.5 rounded-full shrink-0 transition-colors cursor-pointer">
                    + 🩹 Burn Kits
                  </button>
                  <button type="button" onclick="addChecklistPreset('Portable Emergency Oxygen Cylinder (10L)')" class="text-[10px] font-bold bg-blue-50 hover:bg-blue-100 text-blue-800 border border-blue-200 px-2 py-0.5 rounded-full shrink-0 transition-colors cursor-pointer">
                    + 🫁 Oxygen 10L
                  </button>
                  <button type="button" onclick="addChecklistPreset('Mineral Water 20L Cans & Packs')" class="text-[10px] font-bold bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 px-2 py-0.5 rounded-full shrink-0 transition-colors cursor-pointer">
                    + 💧 Water 20L
                  </button>
                  <button type="button" onclick="addChecklistPreset('Emergency Vacuum Splints & Collar')" class="text-[10px] font-bold bg-red-50 hover:bg-red-100 text-red-800 border border-red-200 px-2 py-0.5 rounded-full shrink-0 transition-colors cursor-pointer">
                    + 🩺 Trauma Kit
                  </button>
                  <button type="button" onclick="addChecklistPreset('Portable Backup Battery Inverter')" class="text-[10px] font-bold bg-purple-50 hover:bg-purple-100 text-purple-800 border border-purple-200 px-2 py-0.5 rounded-full shrink-0 transition-colors cursor-pointer">
                    + 🔋 Inverter
                  </button>
                </div>

                <!-- Checklist Items Stream -->
                <div id="checklistContainer" class="flex flex-col gap-2 max-h-[170px] overflow-y-auto custom-scroll pr-1">
                  <div class="text-xs text-[#94a3b8] italic">Loading checklist...</div>
                </div>

                <!-- Add Custom Supply Form -->
                <form id="addChecklistForm" onsubmit="handleAddChecklistSubmit(event)" class="flex items-center gap-1.5 mt-3 pt-2.5 border-t border-gray-100">
                  <input type="text" id="newChecklistInput" placeholder="Add custom resource / supply..." class="flex-1 bg-[#f8fafc] border border-gray-200 rounded-xl px-2.5 py-1 text-xs text-[#111827] focus:outline-none focus:border-[#1d63d8] focus:bg-white font-medium" />
                  <button type="submit" class="px-2.5 py-1 bg-[#1d63d8] hover:bg-[#1553c7] text-white rounded-xl text-xs font-bold transition-all shadow-xs shrink-0 flex items-center gap-0.5 cursor-pointer">
                    <span class="material-symbols-outlined text-xs">add</span> Add
                  </button>
                </form>
              </div>

              <div class="pt-2.5 border-t border-gray-100 flex justify-between items-center mt-2.5">
                <span class="text-[11px] text-[#64748b]">Synced with vehicle inventory</span>
                <button onclick="switchVolunteerTab('resources')" class="text-xs text-[#1d63d8] hover:underline font-bold flex items-center gap-1 cursor-pointer">
                  Open Depot <span class="material-symbols-outlined notranslate text-xs" translate="no">arrow_forward</span>
                </button>
              </div>
            </div>

          </div>

        </div>

        <!-- ======================================================== -->
        <!-- RIGHT COMMUNICATIONS & DISPATCH STAGE (XL: 5)           -->
        <!-- ======================================================== -->
        <div class="xl:col-span-5 flex flex-col gap-5">

          <!-- Incoming Direct Request Card (Phase 3) -->
          <div id="directRequestCard" class="bg-white border-2 border-amber-400 rounded-2xl p-4 shadow-sm flex-col relative overflow-hidden hidden fade-in-up">
            <div class="absolute top-0 left-0 w-full h-1 bg-amber-500"></div>
            <div class="flex justify-between items-center mb-2.5">
              <h3 class="text-sm font-bold text-[#111827] flex items-center gap-1.5">
                <span class="material-symbols-outlined notranslate text-amber-600 text-lg" translate="no">sos</span>
                <span data-i18n="incoming_request_title">Incoming Citizen Request</span>
              </h3>
              <span id="drDistance" class="bg-amber-100 text-amber-800 text-[10px] font-bold uppercase tracking-wider mono px-2 py-0.5 rounded">1.8 KM AWAY</span>
            </div>
            <div class="flex flex-col gap-2">
              <div class="flex justify-between items-center text-xs">
                <div>
                  <span class="text-[10px] text-[#64748b] block uppercase mono" data-i18n="victim_info_label">CITIZEN</span>
                  <strong id="drVictimName" class="text-[#111827]">Loading...</strong>
                </div>
                <div class="text-right">
                  <span class="text-[10px] text-[#64748b] block uppercase mono" data-i18n="condition_priority_label">EMERGENCY</span>
                  <strong id="drEmergencyType" class="text-[#ba1a1a]">Loading...</strong>
                </div>
              </div>
              <div class="text-xs text-[#475569] bg-gray-50 p-2 rounded-lg">
                <span id="drAddress">Loading...</span>
              </div>
              <div class="flex gap-2 mt-1">
                <button onclick="respondDirectRequest('accept')" class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-1.5 rounded-lg text-xs transition-all flex items-center justify-center gap-1">
                  <span class="material-symbols-outlined notranslate text-xs" translate="no">check</span> <span data-i18n="btn_accept">Accept</span>
                </button>
                <button onclick="respondDirectRequest('decline')" class="flex-1 bg-white hover:bg-red-50 text-red-700 border border-red-300 font-bold py-1.5 rounded-lg text-xs transition-all flex items-center justify-center gap-1">
                  <span class="material-symbols-outlined notranslate text-xs" translate="no">close</span> <span data-i18n="btn_decline">Decline</span>
                </button>
              </div>
            </div>
          </div>

          <!-- Multi-Victim Direct Hotline WhatsApp-Style Chat Hub -->
          <div id="directVictimChatCard" class="bg-white border border-[#e5e7eb] rounded-2xl p-4 shadow-sm flex flex-col gap-3.5 relative overflow-hidden fade-in-up">
            <div class="absolute top-0 left-0 w-full h-1.5 bg-gradient-to-r from-blue-600 via-amber-500 to-rose-500"></div>

            <!-- Hub Header -->
            <div class="flex items-center justify-between pb-2.5 border-b border-gray-100 pt-1">
              <div class="flex items-center gap-2">
                <span class="material-symbols-outlined notranslate text-[#1d63d8] text-2xl font-bold" translate="no">chat</span>
                <h4 class="text-base font-bold text-[#111827] leading-tight">
                  <span data-i18n="casualty_hotlines_title">Citizen &amp; Victim<br class="sm:hidden"/> Live Hotlines</span>
                </h4>
                <span id="victimThreadsCountBadge" class="bg-rose-100 text-rose-800 text-[11px] font-extrabold px-2.5 py-0.5 rounded-full mono shrink-0">13 ACTIVE</span>
              </div>
              <div class="flex items-center gap-2">
                <button type="button" onclick="simulateVictimReply()" title="Click to test simulated citizen response" class="px-3 py-1.5 bg-amber-50 hover:bg-amber-100 border border-amber-300 text-amber-900 rounded-xl text-xs font-bold transition-all flex items-center gap-1 cursor-pointer shadow-xs">
                  <span class="material-symbols-outlined notranslate text-xs text-amber-600" translate="no">smart_toy</span> <span data-i18n="btn_test_reply">Test Reply</span>
                </button>
                <a id="directVictimCallBtn" href="tel:+919845011223" class="px-3.5 py-1.5 bg-[#d1fae5] hover:bg-[#a7f3d0] text-[#065f46] rounded-xl text-xs font-bold transition-all flex items-center gap-1 shadow-xs cursor-pointer">
                  <span class="material-symbols-outlined notranslate text-xs text-[#059669]" translate="no">call</span> <span data-i18n="btn_call">Call</span>
                </a>
              </div>
            </div>

            <!-- WhatsApp-Style Horizontal Scrollable Victim Thread Selector -->
            <div class="flex flex-col gap-1.5">
              <div class="flex items-center justify-between text-[10px] font-bold text-[#64748b] uppercase mono tracking-wider">
                <span>SELECT CONVERSATION THREAD:</span>
                <span class="text-[#1d63d8] cursor-pointer hover:underline">CLICK TO SWITCH</span>
              </div>
              <div id="victimThreadList" class="flex gap-2.5 overflow-x-auto custom-scroll pb-1.5 pt-0.5">
                <!-- Dynamically rendered horizontal cards with full text visibility -->
              </div>
            </div>

            <!-- Active Victim Chat Box -->
            <div class="flex flex-col gap-3 p-3.5 bg-[#f8fafc] rounded-2xl border border-gray-200 shadow-xs">
              
              <!-- Active Victim Subheader -->
              <div class="flex items-center justify-between pb-2.5 border-b border-gray-200 text-xs">
                <div class="flex items-center gap-2.5 overflow-hidden">
                  <div class="w-9 h-9 rounded-xl bg-[#1d63d8] text-white font-extrabold text-sm flex items-center justify-center shrink-0 shadow-xs">
                    <span id="activeVictimInitial">Y</span>
                  </div>
                  <div class="truncate">
                    <div class="flex items-center gap-1.5">
                      <strong id="directChatVictimName" class="text-[#111827] text-sm font-bold truncate block">Yanshiak</strong>
                      <span id="activeSosIdBadge" class="text-[10px] bg-blue-100 text-[#1e3a8a] font-bold px-1.5 py-0.5 rounded mono">SOS #17</span>
                    </div>
                    <span id="activeVictimIncidentTag" class="text-[11px] text-[#64748b] block truncate leading-tight">Food/Water • GPS 28.6189...</span>
                  </div>
                </div>
                <div class="flex items-center gap-1.5 shrink-0">
                  <span id="activeVictimDistanceBadge" class="text-[10px] bg-gray-100 text-gray-700 font-bold px-2 py-0.5 rounded mono">~0m away</span>
                  <span id="activeVictimPriorityBadge" class="bg-blue-50 text-blue-900 border border-blue-200 text-[10px] font-bold px-2 py-0.5 rounded mono uppercase">
                    MEDIUM PRIORITY
                  </span>
                </div>
              </div>

              <!-- Messages Feed -->
              <div id="directVictimChatFeed" class="flex flex-col gap-2 max-h-[160px] min-h-[90px] overflow-y-auto p-1 custom-scroll text-xs">
                <div class="text-xs text-gray-400 italic text-center py-3">Connecting direct line with citizen...</div>
              </div>

              <!-- Quick Presets -->
              <div class="flex gap-2 overflow-x-auto custom-scroll pt-0.5 pb-0.5">
                <button onclick="sendDirectVictimMsg('🚑 Approaching in rescue vehicle, ETA 2 mins.')" class="text-xs font-bold bg-blue-50 hover:bg-blue-100 text-[#1d63d8] border border-blue-200 px-3 py-1 rounded-full whitespace-nowrap transition-colors cursor-pointer shrink-0" data-i18n="preset_eta">
                  🚑 ETA 2 Mins
                </button>
                <button onclick="sendDirectVictimMsg('🚨 Stay inside and keep warm. Rescue squad is right outside.')" class="text-xs font-bold bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 px-3 py-1 rounded-full whitespace-nowrap transition-colors cursor-pointer shrink-0" data-i18n="preset_stay_inside">
                  🚨 Stay Inside
                </button>
                <button onclick="sendDirectVictimMsg('🚪 If safe, wave a light/cloth from the nearest open window/balcony.')" class="text-xs font-bold bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 px-3 py-1 rounded-full whitespace-nowrap transition-colors cursor-pointer shrink-0" data-i18n="preset_signal_window">
                  🚪 Signal Window
                </button>
              </div>

              <!-- Send Form -->
              <form id="directVictimChatForm" onsubmit="handleSendDirectVictimMsg(event)" class="flex items-center gap-2 pt-1">
                <input type="text" id="directVictimInput" class="flex-1 bg-white border border-gray-300 rounded-full px-4 py-2 text-xs text-[#111827] focus:outline-none focus:border-[#1d63d8] focus:ring-1 focus:ring-[#1d63d8] font-medium" placeholder="Type direct reply to Yanshiak..." data-i18n-placeholder="input_reply_placeholder" required />
                <button type="submit" class="w-9 h-9 bg-[#1d63d8] hover:bg-[#1553c7] text-white rounded-full text-xs font-bold shadow-xs transition-colors flex items-center justify-center cursor-pointer shrink-0">
                  <span class="material-symbols-outlined notranslate text-sm" translate="no">send</span>
                </button>
              </form>
            </div>

          </div>

          <!-- Coordinator Comms - Advanced Multi-Channel Group & Commander Broadcast -->
          <div class="bg-white border border-[#e5e7eb] rounded-2xl shadow-sm overflow-hidden flex flex-col">
            
            <!-- Comms Header & Channel Selector -->
            <div class="p-3.5 border-b border-[#e5e7eb] bg-white flex flex-col gap-2">
              <div class="flex justify-between items-center">
                <div class="flex items-center gap-2">
                  <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                  <h3 class="text-sm font-bold text-[#111827]">Coordinator Comms</h3>
                </div>
                <span id="commsOnlineBadge" class="text-[10px] bg-blue-50 text-[#1d63d8] font-bold px-2 py-0.5 rounded-full mono">
                  4 RESCUE NODES
                </span>
              </div>

              <!-- Sub-Channel Tabs -->
              <div class="flex gap-1 bg-[#f1f5f9] p-1 rounded-lg">
                <button onclick="switchCommsChannel('all')" id="comms-tab-all" class="flex-1 py-1 text-[11px] font-bold rounded-md bg-white text-[#111827] shadow-xs transition-all text-center">
                  All Net
                </button>
                <button onclick="switchCommsChannel('ops')" id="comms-tab-ops" class="flex-1 py-1 text-[11px] font-bold rounded-md text-[#64748b] hover:text-[#111827] transition-all text-center">
                  #Ops
                </button>
                <button onclick="switchCommsChannel('alerts')" id="comms-tab-alerts" class="flex-1 py-1 text-[11px] font-bold rounded-md text-[#64748b] hover:text-[#111827] transition-all text-center flex items-center justify-center gap-1">
                  ⚡ Flash
                </button>
              </div>
            </div>

            <!-- Messages Stream -->
            <div id="chatFeed" class="flex-1 overflow-y-auto p-3.5 flex flex-col gap-2.5 bg-[#f8fafc] custom-scroll" style="min-height: 240px; max-height: 320px;">
              <div class="text-xs text-[#94a3b8] italic text-center py-4">Loading operational comms...</div>
            </div>

            <!-- Quick Dispatch Shortcut Pills -->
            <div class="px-2.5 pt-2 pb-1.5 bg-white border-t border-[#e5e7eb] flex gap-1.5 overflow-x-auto custom-scroll">
              <button onclick="sendCommsMessage('🫡 Roger that Command, message received.')" class="text-[10px] font-bold bg-[#f1f5f9] hover:bg-[#dce6fe] hover:text-[#1e3a8a] text-[#475569] px-2 py-0.5 rounded-full whitespace-nowrap transition-colors">
                🫡 Roger
              </button>
              <button onclick="sendCommsMessage('📍 Unit arrived on scene safely. Establishing triage.')" class="text-[10px] font-bold bg-[#f1f5f9] hover:bg-[#dce6fe] hover:text-[#1e3a8a] text-[#475569] px-2 py-0.5 rounded-full whitespace-nowrap transition-colors">
                📍 On Scene
              </button>
              <button onclick="sendCommsMessage('🚑 Need immediate EMS / Ambulance backup at target location!', 'urgent')" class="text-[10px] font-bold bg-[#f1f5f9] hover:bg-red-100 hover:text-red-800 text-[#475569] px-2 py-0.5 rounded-full whitespace-nowrap transition-colors">
                🚑 Need EMS
              </button>
              <button onclick="sendCommsMessage('📦 Field supplies delivered. Transporting victims to triage shelter.')" class="text-[10px] font-bold bg-[#f1f5f9] hover:bg-green-100 hover:text-green-800 text-[#475569] px-2 py-0.5 rounded-full whitespace-nowrap transition-colors">
                ✅ Task Done
              </button>
            </div>

            <!-- Input Footer -->
            <div class="p-3 bg-white border-t border-gray-100 flex flex-col gap-1.5">
              <form id="volunteerChatForm" class="flex items-center gap-2">
                <button type="button" onclick="showPhotoUploadModal()" class="text-[#64748b] hover:text-[#111827] p-1.5 rounded-lg hover:bg-gray-100 transition-colors" title="Attach Field Photo">
                  <span class="material-symbols-outlined notranslate text-lg" translate="no">add_photo_alternate</span>
                </button>
                
                <!-- Priority Selector -->
                <select id="chatPrioritySelect" class="bg-[#f1f5f9] text-[#475569] text-[10px] font-bold rounded-lg px-2 py-2 border-0 focus:ring-1 focus:ring-[#1d63d8] cursor-pointer">
                  <option value="normal">Normal</option>
                  <option value="urgent">🚨 Urgent</option>
                  <option value="flash">⚡ Flash</option>
                </select>

                <input type="text" id="volunteerChatInput" class="flex-1 bg-[#f8fafc] border border-gray-200 rounded-xl px-3.5 py-2 text-xs text-[#111827] focus:outline-none focus:border-[#1d63d8] focus:ring-1 focus:ring-[#1d63d8] font-medium" placeholder="Send to all units..." data-i18n-placeholder="input_send_all" required />
                
                <button type="submit" class="px-3.5 py-2 bg-[#1d63d8] hover:bg-[#1553c7] text-white rounded-xl text-xs font-bold transition-all shadow-xs flex items-center justify-center cursor-pointer">
                  <span class="material-symbols-outlined notranslate text-sm" translate="no">send</span>
                </button>
              </form>
            </div>

          </div>

        </div>

      </div>

      <!-- ======================================================== -->
      <!-- TAB 2: FIELD MAP (EXPANDED DEDICATED VIEW)               -->
      <!-- ======================================================== -->
      <div id="tab-map" class="tab-content flex-col gap-6 max-w-[1440px] mx-auto min-h-[calc(100vh-112px)]">
        <div class="bg-white p-6 rounded-xl border border-[#e5e7eb] shadow-sm flex justify-between items-center">
          <div>
            <h1 class="text-2xl font-bold text-[#111827] flex items-center gap-2">
              <span class="material-symbols-outlined text-[#1d63d8] text-3xl">map</span>
              Field Tactical Map &amp; Evacuation Corridors
            </h1>
            <p class="text-sm text-[#64748b] mt-1">Live routing from your current coordinates to target victims and nearest relief shelters.</p>
          </div>
          <div class="flex gap-2">
            <button onclick="centerOnVolunteer()" class="px-4 py-2 bg-[#1d63d8] text-white rounded-lg text-xs font-bold hover:bg-[#1553c7] transition-colors shadow">
              🎯 Center on My Location
            </button>
          </div>
        </div>

        <div class="bg-white border border-[#e5e7eb] rounded-xl p-4 shadow-sm flex-1 min-h-[550px] relative overflow-hidden">
          <div id="fullFieldMap" style="width: 100%; height: 550px; border-radius: 8px;"></div>
        </div>
      </div>

      <!-- ======================================================== -->
      <!-- TAB 3: RESOURCE COLLECTION & FIELD LOGISTICS HUB         -->
      <!-- ======================================================== -->
      <div id="tab-resources" class="tab-content flex-col gap-6 max-w-[1440px] mx-auto min-h-[calc(100vh-112px)]">
        
        <!-- Header & KPI Quick Telemetry Bar -->
        <div class="bg-white p-6 rounded-2xl border border-[#e5e7eb] shadow-sm flex flex-col gap-5">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
              <div class="flex items-center gap-2 mb-1">
                <span class="px-2.5 py-0.5 bg-amber-100 text-amber-800 text-[10px] font-extrabold rounded-full uppercase mono" data-i18n="logistics_telemetry_badge">REAL-TIME LOGISTICS TELEMETRY</span>
                <span class="px-2.5 py-0.5 bg-emerald-100 text-emerald-800 text-[10px] font-extrabold rounded-full uppercase mono" data-i18n="depots_synced_badge">5 DEPOTS SYNCED</span>
              </div>
              <h1 class="text-2xl font-bold text-[#111827] flex items-center gap-2.5">
                <span class="material-symbols-outlined text-amber-600 text-3xl">inventory_2</span>
                <span data-i18n="logistics_hub_title">Field Resource Depot &amp; Vehicle Dispatch Hub</span>
              </h1>
              <p class="text-xs text-[#64748b] mt-1" data-i18n="logistics_hub_desc">Live synchronized inventory of relief warehouses, medical depots, blood banks, and vehicle cargo manifests.</p>
            </div>
            
            <div class="flex items-center gap-3">
              <button onclick="loadResources()" class="px-4 py-2 bg-[#f1f5f9] hover:bg-[#e2e8f0] text-[#111827] rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer">
                <span class="material-symbols-outlined text-base">sync</span> <span data-i18n="btn_sync_depot">Sync Depot Inventory</span>
              </button>
            </div>
          </div>

          <!-- 4 Core Logistics KPI Stat Cards -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2 border-t border-gray-100">
            <div class="p-3.5 bg-[#f8fafc] border border-gray-200 rounded-xl flex items-center gap-3">
              <div class="w-9 h-9 rounded-lg bg-blue-100 text-[#1d63d8] flex items-center justify-center font-bold shrink-0">
                <span class="material-symbols-outlined text-xl">warehouse</span>
              </div>
              <div>
                <div class="text-[10px] font-bold text-[#64748b] uppercase mono" data-i18n="kpi_total_items">Total Depot Items</div>
                <div class="text-base font-extrabold text-[#111827] mono" id="kpiDepotItemsCount">12 Catalog Items</div>
              </div>
            </div>

            <div class="p-3.5 bg-emerald-50/70 border border-emerald-200 rounded-xl flex items-center gap-3">
              <div class="w-9 h-9 rounded-lg bg-emerald-600 text-white flex items-center justify-center font-bold shrink-0 shadow-xs">
                <span class="material-symbols-outlined text-xl">local_shipping</span>
              </div>
              <div>
                <div class="text-[10px] font-bold text-emerald-800 uppercase mono" data-i18n="kpi_in_vehicle">In Vehicle Cargo</div>
                <div class="text-base font-extrabold text-emerald-950 mono" id="kpiVehicleCargoUnits">75 Units Loaded</div>
              </div>
            </div>

            <div class="p-3.5 bg-amber-50/70 border border-amber-200 rounded-xl flex items-center gap-3">
              <div class="w-9 h-9 rounded-lg bg-amber-500 text-white flex items-center justify-center font-bold shrink-0 shadow-xs">
                <span class="material-symbols-outlined text-xl">assignment_turned_in</span>
              </div>
              <div>
                <div class="text-[10px] font-bold text-amber-800 uppercase mono" data-i18n="kpi_fulfillment">Mission Fulfillment</div>
                <div class="text-base font-extrabold text-amber-950 mono" id="kpiFulfillmentRate">100% Prepared</div>
              </div>
            </div>

            <div class="p-3.5 bg-purple-50/70 border border-purple-200 rounded-xl flex items-center gap-3">
              <div class="w-9 h-9 rounded-lg bg-purple-600 text-white flex items-center justify-center font-bold shrink-0 shadow-xs">
                <span class="material-symbols-outlined text-xl">near_me</span>
              </div>
              <div>
                <div class="text-[10px] font-bold text-purple-800 uppercase mono" data-i18n="kpi_nearest_post">Nearest Supply Post</div>
                <div class="text-base font-extrabold text-purple-950 mono">Sector 4 (~450m)</div>
              </div>
            </div>
          </div>
        </div>

        <!-- ======================================================== -->
        <!-- 1. ACTIVE MISSION REQUISITION TRACKER                     -->
        <!-- ======================================================== -->
        <div class="bg-white p-6 rounded-2xl border border-[#e5e7eb] shadow-sm flex flex-col gap-5">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-gray-100">
            <div>
              <span class="text-[11px] font-bold text-amber-700 uppercase tracking-wider mono flex items-center gap-1.5" data-i18n="stage_required_title">
                <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                Active Mission Requisition Matcher
              </span>
              <h2 id="reqMissionTitle" class="text-base font-bold text-[#111827] mt-0.5">
                Deliver 50 Medical Kits &amp; Burn Trauma Packs to Sector 4 Relief Camp
              </h2>
              <p class="text-xs text-[#64748b] mt-0.5">Recipient: <strong id="reqVictimName">Sunita Rao</strong> • Incident: <span id="reqEmergencyType">Burn Care Supplies Depleted</span></p>
            </div>
            <span class="px-3 py-1 bg-emerald-100 text-emerald-900 font-extrabold text-xs rounded-full mono border border-emerald-300 self-start sm:self-auto">
              ✅ ALL REQUIRED SUPPLIES LOADED
            </span>
          </div>

          <!-- 4 Required Supplies Cards -->
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            
            <div class="p-4 bg-emerald-50/70 border-2 border-emerald-400 rounded-2xl flex flex-col justify-between gap-3">
              <div class="flex items-start justify-between">
                <div class="flex items-center gap-2">
                  <span class="text-2xl">💊</span>
                  <div>
                    <h4 class="font-bold text-[#111827] text-xs">Burn Trauma Kits</h4>
                    <span class="text-[10px] text-emerald-800 font-semibold mono">50/50 KITS LOADED</span>
                  </div>
                </div>
                <span class="bg-emerald-200 text-emerald-900 font-extrabold text-[9px] px-1.5 py-0.5 rounded mono">READY</span>
              </div>
              <div class="w-full bg-gray-200 h-1.5 rounded-full overflow-hidden">
                <div id="reqBurnKitsBar" class="h-full bg-emerald-500 rounded-full" style="width: 100%"></div>
              </div>
              <span class="text-[10px] text-[#475569] font-medium">Checked out from Sector 4 Depot</span>
            </div>

            <div class="p-4 bg-emerald-50/70 border-2 border-emerald-400 rounded-2xl flex flex-col justify-between gap-3">
              <div class="flex items-start justify-between">
                <div class="flex items-center gap-2">
                  <span class="text-2xl">🍞</span>
                  <div>
                    <h4 class="font-bold text-[#111827] text-xs">Mineral Water &amp; Rations</h4>
                    <span class="text-[10px] text-emerald-800 font-semibold mono">25/25 PACKS LOADED</span>
                  </div>
                </div>
                <span class="bg-emerald-200 text-emerald-900 font-extrabold text-[9px] px-1.5 py-0.5 rounded mono">READY</span>
              </div>
              <div class="w-full bg-gray-200 h-1.5 rounded-full overflow-hidden">
                <div id="reqWaterBar" class="h-full bg-emerald-500 rounded-full" style="width: 100%"></div>
              </div>
              <span class="text-[10px] text-[#475569] font-medium">Checked out from Sector 4 Depot</span>
            </div>

            <div class="p-4 bg-white border border-gray-200 rounded-2xl flex flex-col justify-between gap-3">
              <div class="flex items-start justify-between">
                <div class="flex items-center gap-2">
                  <span class="text-2xl">🩸</span>
                  <div>
                    <h4 class="font-bold text-[#111827] text-xs">Universal O- Blood</h4>
                    <span class="text-[10px] text-[#64748b] font-semibold mono">24 Units Available</span>
                  </div>
                </div>
                <span class="bg-amber-100 text-amber-900 font-bold text-[9px] px-1.5 py-0.5 rounded mono">ON CALL</span>
              </div>
              <div class="w-full bg-gray-200 h-1.5 rounded-full overflow-hidden">
                <div class="h-full bg-amber-500 rounded-full" style="width: 40%"></div>
              </div>
              <span class="text-[10px] text-[#475569] font-medium">Mobile Transfusion Van (~500m)</span>
            </div>

            <div class="p-4 bg-white border border-gray-200 rounded-2xl flex flex-col justify-between gap-3">
              <div class="flex items-start justify-between">
                <div class="flex items-center gap-2">
                  <span class="text-2xl">🦺</span>
                  <div>
                    <h4 class="font-bold text-[#111827] text-xs">Thermal Space Blankets</h4>
                    <span class="text-[10px] text-[#64748b] font-semibold mono">400 Packs Available</span>
                  </div>
                </div>
                <span class="bg-blue-100 text-blue-900 font-bold text-[9px] px-1.5 py-0.5 rounded mono">DEPOT</span>
              </div>
              <div class="w-full bg-gray-200 h-1.5 rounded-full overflow-hidden">
                <div class="h-full bg-blue-500 rounded-full" style="width: 80%"></div>
              </div>
              <span class="text-[10px] text-[#475569] font-medium">Sector 4 Logistics Hub (~650m)</span>
            </div>

          </div>
        </div>

        <!-- ======================================================== -->
        <!-- 2. VEHICLE CARGO MANIFEST & ON-SCENE DELIVER LOG         -->
        <!-- ======================================================== -->
        <div class="bg-white p-6 rounded-2xl border border-[#e5e7eb] shadow-sm flex flex-col gap-5">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-3 border-b border-gray-100">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold shadow-xs">
                <span class="material-symbols-outlined text-2xl">local_shipping</span>
              </div>
              <div>
                <h3 class="text-base font-bold text-[#111827]" data-i18n="cargo_manifest_title">Your Vehicle Cargo Manifest (Loaded Supplies)</h3>
                <p class="text-xs text-[#64748b]" data-i18n="cargo_manifest_desc">Checked out inventory ready for handover at relief camp or citizen location.</p>
              </div>
            </div>
            
            <div class="flex items-center gap-3">
              <span id="cargoCountBadge" class="bg-emerald-100 text-emerald-900 font-extrabold px-3.5 py-1.5 rounded-xl text-xs mono">
                75 UNITS LOADED
              </span>
              <button onclick="handleDeliverAllCargo()" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-all shadow-xs flex items-center gap-1.5 cursor-pointer">
                <span class="material-symbols-outlined text-base">verified</span> <span data-i18n="btn_deliver_all">Deliver All to Citizen / Camp</span>
              </button>
            </div>
          </div>

          <!-- Cargo Items Container -->
          <div id="cargoListContainer" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
            <!-- Dynamically populated cargo cards -->
          </div>

          <!-- Past On-Scene Handover Delivery Log -->
          <div class="pt-3 border-t border-gray-100 flex flex-col gap-2.5">
            <div class="flex items-center justify-between">
              <span class="text-[11px] font-bold text-[#334155] uppercase tracking-wider mono flex items-center gap-1">
                <span class="material-symbols-outlined text-sm text-[#1d63d8]">history</span>
                <span data-i18n="audit_log_title">Recent On-Scene Delivery Handover Audit Log</span>
              </span>
              <span class="text-[10px] text-[#64748b] mono">VERIFIED BY FIELD GPS</span>
            </div>
            
            <div class="overflow-x-auto">
              <table class="w-full text-left text-xs border-collapse">
                <thead>
                  <tr class="bg-[#f8fafc] text-[#64748b] border-b border-gray-200">
                    <th class="py-2.5 px-3 font-bold mono">DELIVERY ID</th>
                    <th class="py-2.5 px-3 font-bold mono">RECIPIENT CITIZEN / CAMP</th>
                    <th class="py-2.5 px-3 font-bold mono">SUPPLIES HANDED OVER</th>
                    <th class="py-2.5 px-3 font-bold mono">LOCATION / CAMP</th>
                    <th class="py-2.5 px-3 font-bold mono">TIMESTAMP</th>
                    <th class="py-2.5 px-3 font-bold mono text-right">STATUS</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-[#334155] font-medium">
                  <tr class="hover:bg-gray-50/80 transition-colors">
                    <td class="py-2.5 px-3 font-bold text-[#1d63d8] mono">#DLV-8812</td>
                    <td class="py-2.5 px-3 font-bold text-[#111827]">Suresh Patel (Building Collapse)</td>
                    <td class="py-2.5 px-3">20 Tourniquets &amp; 1 Spine Board</td>
                    <td class="py-2.5 px-3 text-[#64748b]">Sector 4 Block A</td>
                    <td class="py-2.5 px-3 mono text-[#64748b]">18 mins ago</td>
                    <td class="py-2.5 px-3 text-right"><span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 text-[10px] font-bold rounded-full mono">FULFILLED</span></td>
                  </tr>
                  <tr class="hover:bg-gray-50/80 transition-colors">
                    <td class="py-2.5 px-3 font-bold text-[#1d63d8] mono">#DLV-8809</td>
                    <td class="py-2.5 px-3 font-bold text-[#111827]">Dr. Priya Nair (Shelter #2)</td>
                    <td class="py-2.5 px-3">150 High-Calorie Meal Packs &amp; ORS</td>
                    <td class="py-2.5 px-3 text-[#64748b]">Community Center Hall B</td>
                    <td class="py-2.5 px-3 mono text-[#64748b]">42 mins ago</td>
                    <td class="py-2.5 px-3 text-right"><span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 text-[10px] font-bold rounded-full mono">FULFILLED</span></td>
                  </tr>
                  <tr class="hover:bg-gray-50/80 transition-colors">
                    <td class="py-2.5 px-3 font-bold text-[#1d63d8] mono">#DLV-8794</td>
                    <td class="py-2.5 px-3 font-bold text-[#111827]">NDRF Tactical Dive Team</td>
                    <td class="py-2.5 px-3">12 Life Jackets &amp; 2 Searchlights</td>
                    <td class="py-2.5 px-3 text-[#64748b]">Block C Flood Zone</td>
                    <td class="py-2.5 px-3 mono text-[#64748b]">1h 15m ago</td>
                    <td class="py-2.5 px-3 text-right"><span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 text-[10px] font-bold rounded-full mono">FULFILLED</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- ======================================================== -->
        <!-- 3. NEARBY DEPOT SUPPLY CATALOG (12 ITEMS WITH FILTERS)   -->
        <!-- ======================================================== -->
        <div class="bg-white p-6 rounded-2xl border border-[#e5e7eb] shadow-sm flex flex-col gap-5">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div>
              <h3 class="text-base font-bold text-[#111827]" data-i18n="depot_catalog_title">Nearby Logistics Depots &amp; Live Stock Catalog</h3>
              <p class="text-xs text-[#64748b]" data-i18n="depot_catalog_desc">Browse supplies across sector warehouses and claim units directly into your rescue vehicle.</p>
            </div>

            <!-- Category Filter Bar -->
            <div class="flex flex-wrap gap-1.5" id="resFilterGroup">
              <button onclick="filterResources('', this)" class="res-filter-btn px-3.5 py-1.5 bg-[#111827] text-white rounded-xl text-xs font-bold transition-all shadow-xs cursor-pointer">
                All Depots (12)
              </button>
              <button onclick="filterResources('food_water', this)" class="res-filter-btn px-3.5 py-1.5 bg-gray-50 hover:bg-gray-100 text-[#475569] border border-gray-200 rounded-xl text-xs font-bold transition-all cursor-pointer">
                🍞 Food &amp; Water
              </button>
              <button onclick="filterResources('medical_supplies', this)" class="res-filter-btn px-3.5 py-1.5 bg-gray-50 hover:bg-gray-100 text-[#475569] border border-gray-200 rounded-xl text-xs font-bold transition-all cursor-pointer">
                💊 Medical &amp; Trauma
              </button>
              <button onclick="filterResources('blood', this)" class="res-filter-btn px-3.5 py-1.5 bg-gray-50 hover:bg-gray-100 text-[#475569] border border-gray-200 rounded-xl text-xs font-bold transition-all cursor-pointer">
                🩸 Blood Bank
              </button>
              <button onclick="filterResources('safety_equipment', this)" class="res-filter-btn px-3.5 py-1.5 bg-gray-50 hover:bg-gray-100 text-[#475569] border border-gray-200 rounded-xl text-xs font-bold transition-all cursor-pointer">
                🦺 Rescue &amp; Life Vests
              </button>
              <button onclick="filterResources('medical_equipment', this)" class="res-filter-btn px-3.5 py-1.5 bg-gray-50 hover:bg-gray-100 text-[#475569] border border-gray-200 rounded-xl text-xs font-bold transition-all cursor-pointer">
                🏥 Heavy Rescue Tools
              </button>
              <button onclick="filterResources('power_supply', this)" class="res-filter-btn px-3.5 py-1.5 bg-gray-50 hover:bg-gray-100 text-[#475569] border border-gray-200 rounded-xl text-xs font-bold transition-all cursor-pointer">
                ⚡ Generators &amp; Power
              </button>
            </div>
          </div>

          <!-- Main Depot Resources Grid -->
          <div id="resourcesGrid" class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="col-span-3 text-center py-12 text-sm text-[#94a3b8]">Loading depot stock...</div>
          </div>
        </div>

        <!-- ======================================================== -->
        <!-- 4. REPORT UNAVAILABLE / DEPLETED DEPOT STOCK             -->
        <!-- ======================================================== -->
        <div class="bg-white p-7 rounded-2xl border border-[#e5e7eb] shadow-sm flex flex-col gap-6">
          <div class="flex items-start gap-4 pb-4 border-b border-gray-100">
            <div class="w-10 h-10 rounded-xl bg-red-100 text-red-600 flex items-center justify-center font-bold shrink-0">
              <span class="material-symbols-outlined text-2xl">report_problem</span>
            </div>
            <div>
              <h3 class="text-lg font-bold text-[#111827]" data-i18n="report_shortage_title">Report Depot Stock Depletion or Supply Shortage</h3>
              <p class="text-xs text-[#64748b] mt-0.5" data-i18n="report_shortage_desc">Direct satellite broadcast to NDRF Logistics Command &amp; Central Supply Convoy to trigger urgent replenishment dispatch.</p>
            </div>
          </div>

          <form id="shortageReportForm" onsubmit="handleShortageReport(event)" class="space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
              <div>
                <label class="block text-xs font-bold text-[#334155] uppercase tracking-wider mb-1.5 mono">
                  Depleted / Missing Resource Item <span class="text-red-500">*</span>
                </label>
                <input type="text" id="shortageItemName" required class="w-full bg-[#f8fafc] border border-gray-200 rounded-xl px-4 py-2.5 text-xs text-[#111827] focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 font-semibold" placeholder="e.g. Sterile Burn Gauze / O- Blood Units" />
              </div>

              <div>
                <label class="block text-xs font-bold text-[#334155] uppercase tracking-wider mb-1.5 mono">
                  Depot / Camp Location <span class="text-red-500">*</span>
                </label>
                <select id="shortageDepotLocation" required class="w-full bg-[#f8fafc] border border-gray-200 rounded-xl px-4 py-2.5 text-xs text-[#111827] focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 font-semibold cursor-pointer">
                  <option value="Sector 4 Relief Camp Depot">Sector 4 Relief Camp Depot</option>
                  <option value="Sector 4 Logistics Hub">Sector 4 Logistics Hub</option>
                  <option value="Fire Station 12 Armory">Fire Station 12 Armory</option>
                  <option value="Flood Relief Depot A">Flood Relief Depot A</option>
                  <option value="Central Equipment Yard">Central Equipment Yard</option>
                  <option value="Central Trauma Blood Bank">Central Trauma Blood Bank</option>
                </select>
              </div>
            </div>

            <div>
              <label class="block text-xs font-bold text-[#334155] uppercase tracking-wider mb-1.5 mono">
                Critical Reason / Field Context <span class="text-red-500">*</span>
              </label>
              <select id="shortageReason" required class="w-full bg-[#f8fafc] border border-gray-200 rounded-xl px-4 py-2.5 text-xs text-[#111827] focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 font-semibold cursor-pointer">
                <option value="Completely Depleted during Mass Incident Triage">Completely Depleted during Mass Incident Triage</option>
                <option value="Contaminated / Damaged Stock due to Flood or Smoke">Contaminated / Damaged Stock due to Flood or Smoke</option>
                <option value="Cold Chain Failure / Power Outage for Biologicals">Cold Chain Failure / Power Outage for Biologicals</option>
                <option value="Surge in Incoming Displaced Citizens">Surge in Incoming Displaced Citizens</option>
              </select>
            </div>

            <div class="pt-3 border-t border-gray-100 flex justify-end gap-3">
              <button type="submit" id="btnSendShortage" class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-bold text-xs rounded-xl transition-all shadow-sm flex items-center gap-2 cursor-pointer">
                <span class="material-symbols-outlined text-base">emergency_share</span> <span data-i18n="btn_broadcast_shortage">Broadcast Supply Shortage to HQ Convoy</span>
              </button>
            </div>
          </form>
        </div>

      </div>

      <!-- ======================================================== -->
      <!-- TAB 4: SAFETY GUIDES (Phase 5 - SMART SOP SYSTEM)        -->
      <!-- ======================================================== -->
      <div id="tab-guides" class="tab-content flex-col gap-6 max-w-[1440px] mx-auto min-h-[calc(100vh-112px)]">
        
        <!-- Smart Header & Context Matching Alert -->
        <div class="bg-white p-6 rounded-2xl border border-[#e5e7eb] shadow-sm flex flex-col gap-5">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
              <h1 class="text-2xl font-bold text-[#111827] flex items-center gap-2.5">
                <span class="material-symbols-outlined text-red-600 text-3xl">health_and_safety</span>
                Smart Field SOPs &amp; Clinical Safety Directives
              </h1>
              <p class="text-xs text-[#64748b] mt-1">NDRF &amp; WHO emergency response standard operating procedures dynamically synchronized with your live mission telemetry.</p>
            </div>
            
            <!-- Category Filter Pills -->
            <div class="flex flex-wrap gap-1.5" id="sopFilterGroup">
              <button onclick="filterSafetyGuides('', this)" class="sop-filter-btn px-3.5 py-1.5 bg-[#111827] text-white rounded-xl text-xs font-bold transition-all shadow-xs cursor-pointer">
                All Guidelines (22)
              </button>
              <button onclick="filterSafetyGuides('disaster_safety', this)" class="sop-filter-btn px-3.5 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-800 border border-blue-200 rounded-xl text-xs font-bold transition-all cursor-pointer">
                🌊 Disaster Safety Guides (5)
              </button>
              <button onclick="filterSafetyGuides('rescue_guidelines', this)" class="sop-filter-btn px-3.5 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 rounded-xl text-xs font-bold transition-all cursor-pointer">
                🏗️ Rescue Guidelines (4)
              </button>
              <button onclick="filterSafetyGuides('first_aid', this)" class="sop-filter-btn px-3.5 py-1.5 bg-red-50 hover:bg-red-100 text-red-800 border border-red-200 rounded-xl text-xs font-bold transition-all cursor-pointer">
                🚑 First-Aid Guide (5)
              </button>
              <button onclick="filterSafetyGuides('volunteer_safety', this)" class="sop-filter-btn px-3.5 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 rounded-xl text-xs font-bold transition-all cursor-pointer">
                🛡️ Volunteer Safety (4)
              </button>
              <button onclick="filterSafetyGuides('dos_donts', this)" class="sop-filter-btn px-3.5 py-1.5 bg-purple-50 hover:bg-purple-100 text-purple-800 border border-purple-200 rounded-xl text-xs font-bold transition-all cursor-pointer">
                ⚖️ Do's &amp; Don'ts (4)
              </button>
            </div>
          </div>

          <!-- Dynamic Mission Intelligence Auto-Match Banner -->
          <div id="smartContextBanner" class="p-4 bg-gradient-to-r from-amber-50 via-orange-50 to-amber-50/60 border border-amber-300/80 rounded-xl flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs">
            <div class="flex items-start gap-3">
              <div class="w-8 h-8 rounded-lg bg-amber-500 text-white flex items-center justify-center font-bold shrink-0 mt-0.5 shadow-xs">
                <span class="material-symbols-outlined text-lg">auto_awesome</span>
              </div>
              <div>
                <div class="flex items-center gap-2">
                  <span class="font-extrabold text-amber-950 uppercase tracking-wider text-[11px] mono">Mission Intelligence Auto-Match</span>
                  <span id="smartMatchPill" class="bg-amber-200 text-amber-900 font-bold px-2 py-0.5 rounded-full text-[10px] mono">🔥 FIRE &amp; BURNS</span>
                </div>
                <p id="smartMatchDesc" class="text-[#451a03] font-medium mt-0.5">
                  Your active task involves <strong id="smartTaskName">Burn Care Supplies Depleted (Sunita Rao)</strong>. Thermal burn stabilization and triage SOPs have been auto-prioritized.
                </p>
              </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
              <span id="smartVolunteerSpeciality" class="bg-white border border-amber-200 text-amber-900 font-bold px-3 py-1.5 rounded-lg text-[11px] shadow-xs">
                ⭐ Speciality: First Aid &amp; Trauma Care
              </span>
            </div>
          </div>
        </div>

        <!-- Rapid Field Clinical Tool: Interactive START Triage Matrix -->
        <div class="bg-white p-6 rounded-2xl border border-[#e5e7eb] shadow-sm flex flex-col gap-4">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-gray-100">
            <div>
              <h2 class="text-base font-bold text-[#111827] flex items-center gap-2">
                <span class="material-symbols-outlined text-[#1d63d8]">calculate</span>
                Interactive Field START Triage Decision Flowchart
              </h2>
              <p class="text-xs text-[#64748b] mt-0.5">Rapid mass incident classification matrix — click parameters to determine citizen triage status.</p>
            </div>
            <div id="triageResultBadge" class="bg-gray-100 text-gray-700 font-bold text-xs px-3 py-1.5 rounded-xl mono flex items-center gap-1.5 self-start sm:self-auto">
              <span>Status: Select Parameters Below</span>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <!-- Step 1: Walking -->
            <div class="p-4 bg-[#f8fafc] border border-gray-200 rounded-xl flex flex-col gap-2">
              <span class="text-[11px] font-bold text-[#64748b] uppercase mono">1. Ability to Walk?</span>
              <div class="flex gap-2">
                <button onclick="setTriageStep('walk', true)" id="triageBtnWalkYes" class="flex-1 py-2 bg-emerald-600 text-white font-bold text-xs rounded-lg shadow-xs hover:bg-emerald-700 transition-colors cursor-pointer">
                  🚶 Yes (Minor)
                </button>
                <button onclick="setTriageStep('walk', false)" id="triageBtnWalkNo" class="flex-1 py-2 bg-gray-100 text-[#334155] font-bold text-xs rounded-lg hover:bg-gray-200 transition-colors cursor-pointer">
                  ❌ No (Proceed)
                </button>
              </div>
            </div>

            <!-- Step 2: Respiration -->
            <div class="p-4 bg-[#f8fafc] border border-gray-200 rounded-xl flex flex-col gap-2">
              <span class="text-[11px] font-bold text-[#64748b] uppercase mono">2. Spontaneous Breathing?</span>
              <div class="flex gap-2">
                <button onclick="setTriageStep('resp', 'normal')" id="triageBtnRespNorm" class="flex-1 py-2 bg-gray-100 text-[#334155] font-bold text-xs rounded-lg hover:bg-gray-200 transition-colors cursor-pointer">
                  🫁 10-30 / min
                </button>
                <button onclick="setTriageStep('resp', 'critical')" id="triageBtnRespCrit" class="flex-1 py-2 bg-red-600 text-white font-bold text-xs rounded-lg shadow-xs hover:bg-red-700 transition-colors cursor-pointer">
                  🚨 &lt;10 or &gt;30 (Red)
                </button>
              </div>
            </div>

            <!-- Step 3: Perfusion / Pulse -->
            <div class="p-4 bg-[#f8fafc] border border-gray-200 rounded-xl flex flex-col gap-2">
              <span class="text-[11px] font-bold text-[#64748b] uppercase mono">3. Radial Pulse / Perfusion?</span>
              <div class="flex gap-2">
                <button onclick="setTriageStep('pulse', 'present')" id="triageBtnPulsePres" class="flex-1 py-2 bg-gray-100 text-[#334155] font-bold text-xs rounded-lg hover:bg-gray-200 transition-colors cursor-pointer">
                  💓 Present (Yellow)
                </button>
                <button onclick="setTriageStep('pulse', 'absent')" id="triageBtnPulseAbs" class="flex-1 py-2 bg-red-600 text-white font-bold text-xs rounded-lg shadow-xs hover:bg-red-700 transition-colors cursor-pointer">
                  ⚠️ Absent (Red)
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- ============================================================ -->
        <!-- SECTION 2: QUICK REFERENCE POCKET CARDS                      -->
        <!-- ============================================================ -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">

          <!-- Card 1: Emergency Helpline Numbers -->
          <div class="bg-gradient-to-br from-red-50 to-rose-50 p-5 rounded-2xl border-2 border-red-200 shadow-sm flex flex-col gap-3 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-16 h-16 bg-red-100 rounded-bl-[40px] flex items-center justify-center opacity-50">
              <span class="material-symbols-outlined text-red-400 text-3xl">call</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="material-symbols-outlined text-red-600 text-xl">emergency</span>
              <h3 class="text-sm font-extrabold text-red-900">Emergency Helplines</h3>
            </div>
            <div class="flex flex-col gap-1.5">
              <a href="tel:112" class="flex items-center justify-between bg-white p-2 rounded-lg border border-red-100 hover:bg-red-50 transition-colors">
                <span class="text-xs font-bold text-red-800">🆘 National Emergency</span>
                <span class="text-sm font-extrabold text-red-600 mono">112</span>
              </a>
              <a href="tel:108" class="flex items-center justify-between bg-white p-2 rounded-lg border border-red-100 hover:bg-red-50 transition-colors">
                <span class="text-xs font-bold text-red-800">🚑 Ambulance</span>
                <span class="text-sm font-extrabold text-red-600 mono">108</span>
              </a>
              <a href="tel:101" class="flex items-center justify-between bg-white p-2 rounded-lg border border-red-100 hover:bg-red-50 transition-colors">
                <span class="text-xs font-bold text-red-800">🔥 Fire Brigade</span>
                <span class="text-sm font-extrabold text-red-600 mono">101</span>
              </a>
              <a href="tel:1078" class="flex items-center justify-between bg-white p-2 rounded-lg border border-red-100 hover:bg-red-50 transition-colors">
                <span class="text-xs font-bold text-red-800">🛡️ NDRF Helpline</span>
                <span class="text-sm font-extrabold text-red-600 mono">1078</span>
              </a>
              <a href="tel:100" class="flex items-center justify-between bg-white p-2 rounded-lg border border-red-100 hover:bg-red-50 transition-colors">
                <span class="text-xs font-bold text-red-800">🚔 Police Control</span>
                <span class="text-sm font-extrabold text-red-600 mono">100</span>
              </a>
              <a href="tel:1070" class="flex items-center justify-between bg-white p-2 rounded-lg border border-red-100 hover:bg-red-50 transition-colors">
                <span class="text-xs font-bold text-red-800">🏥 Disaster Mgmt</span>
                <span class="text-sm font-extrabold text-red-600 mono">1070</span>
              </a>
            </div>
          </div>

          <!-- Card 2: Triage Color Codes Quick Reference -->
          <div class="bg-gradient-to-br from-blue-50 to-indigo-50 p-5 rounded-2xl border-2 border-blue-200 shadow-sm flex flex-col gap-3 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-16 h-16 bg-blue-100 rounded-bl-[40px] flex items-center justify-center opacity-50">
              <span class="material-symbols-outlined text-blue-400 text-3xl">palette</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="material-symbols-outlined text-blue-700 text-xl">colorize</span>
              <h3 class="text-sm font-extrabold text-blue-900">Triage Color Codes</h3>
            </div>
            <div class="flex flex-col gap-1.5">
              <div class="flex items-center gap-2.5 bg-white p-2.5 rounded-lg border border-blue-100">
                <div class="w-6 h-6 rounded-md bg-emerald-500 shrink-0 flex items-center justify-center text-white text-xs font-bold">G</div>
                <div>
                  <strong class="text-xs text-emerald-800 block">GREEN — Minor</strong>
                  <span class="text-[10px] text-gray-500">Walking wounded. Can wait 4+ hrs. Direct to secondary area.</span>
                </div>
              </div>
              <div class="flex items-center gap-2.5 bg-white p-2.5 rounded-lg border border-blue-100">
                <div class="w-6 h-6 rounded-md bg-amber-500 shrink-0 flex items-center justify-center text-white text-xs font-bold">Y</div>
                <div>
                  <strong class="text-xs text-amber-800 block">YELLOW — Delayed</strong>
                  <span class="text-[10px] text-gray-500">Stable vitals. Needs treatment within 1-4 hours.</span>
                </div>
              </div>
              <div class="flex items-center gap-2.5 bg-white p-2.5 rounded-lg border border-blue-100">
                <div class="w-6 h-6 rounded-md bg-red-600 shrink-0 flex items-center justify-center text-white text-xs font-bold">R</div>
                <div>
                  <strong class="text-xs text-red-800 block">RED — Immediate</strong>
                  <span class="text-[10px] text-gray-500">Life-threatening. Needs intervention within 1 hour.</span>
                </div>
              </div>
              <div class="flex items-center gap-2.5 bg-white p-2.5 rounded-lg border border-blue-100">
                <div class="w-6 h-6 rounded-md bg-gray-800 shrink-0 flex items-center justify-center text-white text-xs font-bold">B</div>
                <div>
                  <strong class="text-xs text-gray-800 block">BLACK — Expectant</strong>
                  <span class="text-[10px] text-gray-500">Non-survivable injuries. Provide comfort measures only.</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Card 3: Universal Distress Signals -->
          <div class="bg-gradient-to-br from-amber-50 to-yellow-50 p-5 rounded-2xl border-2 border-amber-200 shadow-sm flex flex-col gap-3 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-16 h-16 bg-amber-100 rounded-bl-[40px] flex items-center justify-center opacity-50">
              <span class="material-symbols-outlined text-amber-400 text-3xl">notifications_active</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="material-symbols-outlined text-amber-700 text-xl">campaign</span>
              <h3 class="text-sm font-extrabold text-amber-900">Distress Signals</h3>
            </div>
            <div class="flex flex-col gap-1.5">
              <div class="flex items-start gap-2.5 bg-white p-2.5 rounded-lg border border-amber-100">
                <span class="text-lg shrink-0">📢</span>
                <div>
                  <strong class="text-xs text-amber-800 block">3 Whistle Blasts</strong>
                  <span class="text-[10px] text-gray-500">Universal SOS signal. Repeat every 60 seconds.</span>
                </div>
              </div>
              <div class="flex items-start gap-2.5 bg-white p-2.5 rounded-lg border border-amber-100">
                <span class="text-lg shrink-0">🪞</span>
                <div>
                  <strong class="text-xs text-amber-800 block">Mirror Flash (3 pulses)</strong>
                  <span class="text-[10px] text-gray-500">Daytime aerial rescue signal. Aim at aircraft.</span>
                </div>
              </div>
              <div class="flex items-start gap-2.5 bg-white p-2.5 rounded-lg border border-amber-100">
                <span class="text-lg shrink-0">🙋</span>
                <div>
                  <strong class="text-xs text-amber-800 block">Both Arms Raised (Y-shape)</strong>
                  <span class="text-[10px] text-gray-500">"YES — Need Help!" Ground-to-air body signal.</span>
                </div>
              </div>
              <div class="flex items-start gap-2.5 bg-white p-2.5 rounded-lg border border-amber-100">
                <span class="text-lg shrink-0">🔦</span>
                <div>
                  <strong class="text-xs text-amber-800 block">SOS Flashlight (· · · — — — · · ·)</strong>
                  <span class="text-[10px] text-gray-500">Morse code SOS via torch/flashlight at night.</span>
                </div>
              </div>
              <div class="flex items-start gap-2.5 bg-white p-2.5 rounded-lg border border-amber-100">
                <span class="text-lg shrink-0">🔥</span>
                <div>
                  <strong class="text-xs text-amber-800 block">Triangle Fire Signal</strong>
                  <span class="text-[10px] text-gray-500">3 fires in triangle formation = ground emergency.</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Card 4: Radio Codes & Phonetic Alphabet -->
          <div class="bg-gradient-to-br from-emerald-50 to-teal-50 p-5 rounded-2xl border-2 border-emerald-200 shadow-sm flex flex-col gap-3 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-16 h-16 bg-emerald-100 rounded-bl-[40px] flex items-center justify-center opacity-50">
              <span class="material-symbols-outlined text-emerald-400 text-3xl">radio</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="material-symbols-outlined text-emerald-700 text-xl">settings_input_antenna</span>
              <h3 class="text-sm font-extrabold text-emerald-900">Radio & Comms Codes</h3>
            </div>
            <div class="flex flex-col gap-1.5">
              <div class="flex items-center justify-between bg-white p-2 rounded-lg border border-emerald-100">
                <span class="text-xs font-bold text-emerald-800">10-4</span>
                <span class="text-[10px] text-gray-600">Message Received / Acknowledged</span>
              </div>
              <div class="flex items-center justify-between bg-white p-2 rounded-lg border border-emerald-100">
                <span class="text-xs font-bold text-emerald-800">10-20</span>
                <span class="text-[10px] text-gray-600">What is your Location?</span>
              </div>
              <div class="flex items-center justify-between bg-white p-2 rounded-lg border border-emerald-100">
                <span class="text-xs font-bold text-emerald-800">10-33</span>
                <span class="text-[10px] text-gray-600">EMERGENCY — All traffic cease!</span>
              </div>
              <div class="flex items-center justify-between bg-white p-2 rounded-lg border border-emerald-100">
                <span class="text-xs font-bold text-emerald-800">CODE RED</span>
                <span class="text-[10px] text-gray-600">Active Fire / Immediate Danger</span>
              </div>
              <div class="flex items-center justify-between bg-white p-2 rounded-lg border border-emerald-100">
                <span class="text-xs font-bold text-emerald-800">MAYDAY ×3</span>
                <span class="text-[10px] text-gray-600">Life-Threatening Emergency</span>
              </div>
              <div class="flex items-center justify-between bg-white p-2 rounded-lg border border-emerald-100">
                <span class="text-xs font-bold text-emerald-800">ALL CLEAR</span>
                <span class="text-[10px] text-gray-600">Hazard resolved, safe to proceed</span>
              </div>
            </div>
          </div>
        </div>

        <!-- ============================================================ -->
        <!-- SECTION 3: PRE-MISSION FIELD SAFETY CHECKLIST                -->
        <!-- ============================================================ -->
        <div class="bg-white p-6 rounded-2xl border border-[#e5e7eb] shadow-sm flex flex-col gap-5">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-gray-100">
            <div>
              <h2 class="text-base font-bold text-[#111827] flex items-center gap-2">
                <span class="material-symbols-outlined text-emerald-600">verified_user</span>
                Pre-Mission Field Safety Checklist
              </h2>
              <p class="text-xs text-[#64748b] mt-0.5">Complete all checks before deploying to ground zero. Generates a timestamped "FIELD READY" compliance certificate.</p>
            </div>
            <div id="fieldReadyBadge" class="bg-gray-100 text-gray-600 font-bold text-xs px-4 py-2 rounded-xl mono flex items-center gap-1.5 self-start sm:self-auto transition-all">
              <span class="material-symbols-outlined text-sm">pending</span>
              <span id="fieldReadyText">INCOMPLETE — 0/5 Verified</span>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3" id="preMissionChecklist">
            <!-- Check 1: PPE Verification -->
            <label class="premission-check flex flex-col items-center gap-2.5 p-4 bg-[#f8fafc] border-2 border-gray-200 rounded-xl cursor-pointer hover:border-[#1d63d8]/40 transition-all text-center relative">
              <input type="checkbox" onchange="updatePreMissionChecklist()" class="premission-cb hidden" />
              <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center text-2xl premission-icon transition-all">🪖</div>
              <strong class="text-xs text-[#111827] font-bold">PPE Equipment</strong>
              <span class="text-[10px] text-[#64748b] leading-tight">Helmet, gloves, high-vis vest, safety boots, N95 mask verified</span>
              <div class="premission-stamp hidden absolute top-2 right-2 bg-emerald-500 text-white rounded-full w-5 h-5 flex items-center justify-center">
                <span class="material-symbols-outlined text-xs">check</span>
              </div>
            </label>

            <!-- Check 2: Communication Equipment -->
            <label class="premission-check flex flex-col items-center gap-2.5 p-4 bg-[#f8fafc] border-2 border-gray-200 rounded-xl cursor-pointer hover:border-[#1d63d8]/40 transition-all text-center relative">
              <input type="checkbox" onchange="updatePreMissionChecklist()" class="premission-cb hidden" />
              <div class="w-12 h-12 rounded-xl bg-amber-100 flex items-center justify-center text-2xl premission-icon transition-all">📻</div>
              <strong class="text-xs text-[#111827] font-bold">Comms Check</strong>
              <span class="text-[10px] text-[#64748b] leading-tight">Radio charged, phone battery >80%, GPS active, emergency contacts saved</span>
              <div class="premission-stamp hidden absolute top-2 right-2 bg-emerald-500 text-white rounded-full w-5 h-5 flex items-center justify-center">
                <span class="material-symbols-outlined text-xs">check</span>
              </div>
            </label>

            <!-- Check 3: Medical Kit -->
            <label class="premission-check flex flex-col items-center gap-2.5 p-4 bg-[#f8fafc] border-2 border-gray-200 rounded-xl cursor-pointer hover:border-[#1d63d8]/40 transition-all text-center relative">
              <input type="checkbox" onchange="updatePreMissionChecklist()" class="premission-cb hidden" />
              <div class="w-12 h-12 rounded-xl bg-red-100 flex items-center justify-center text-2xl premission-icon transition-all">🩺</div>
              <strong class="text-xs text-[#111827] font-bold">Medical Kit</strong>
              <span class="text-[10px] text-[#64748b] leading-tight">Bandages, tourniquet, ORS, antiseptic, burn gel, splints verified</span>
              <div class="premission-stamp hidden absolute top-2 right-2 bg-emerald-500 text-white rounded-full w-5 h-5 flex items-center justify-center">
                <span class="material-symbols-outlined text-xs">check</span>
              </div>
            </label>

            <!-- Check 4: Buddy System -->
            <label class="premission-check flex flex-col items-center gap-2.5 p-4 bg-[#f8fafc] border-2 border-gray-200 rounded-xl cursor-pointer hover:border-[#1d63d8]/40 transition-all text-center relative">
              <input type="checkbox" onchange="updatePreMissionChecklist()" class="premission-cb hidden" />
              <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center text-2xl premission-icon transition-all">🤝</div>
              <strong class="text-xs text-[#111827] font-bold">Buddy Confirmed</strong>
              <span class="text-[10px] text-[#64748b] leading-tight">Partner assigned, comms channel synced, 15-min check-in protocol set</span>
              <div class="premission-stamp hidden absolute top-2 right-2 bg-emerald-500 text-white rounded-full w-5 h-5 flex items-center justify-center">
                <span class="material-symbols-outlined text-xs">check</span>
              </div>
            </label>

            <!-- Check 5: Exit Route Identified -->
            <label class="premission-check flex flex-col items-center gap-2.5 p-4 bg-[#f8fafc] border-2 border-gray-200 rounded-xl cursor-pointer hover:border-[#1d63d8]/40 transition-all text-center relative">
              <input type="checkbox" onchange="updatePreMissionChecklist()" class="premission-cb hidden" />
              <div class="w-12 h-12 rounded-xl bg-emerald-100 flex items-center justify-center text-2xl premission-icon transition-all">🚪</div>
              <strong class="text-xs text-[#111827] font-bold">Exit Route</strong>
              <span class="text-[10px] text-[#64748b] leading-tight">Primary & secondary evacuation routes identified and communicated</span>
              <div class="premission-stamp hidden absolute top-2 right-2 bg-emerald-500 text-white rounded-full w-5 h-5 flex items-center justify-center">
                <span class="material-symbols-outlined text-xs">check</span>
              </div>
            </label>
          </div>

          <!-- Field Ready Certification (appears on 5/5) -->
          <div id="fieldReadyCertificate" class="hidden p-4 bg-gradient-to-r from-emerald-50 via-green-50 to-emerald-50 border-2 border-emerald-400 rounded-xl flex flex-col sm:flex-row items-center justify-between gap-3 fade-in-up">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center shrink-0 shadow-sm">
                <span class="material-symbols-outlined text-xl">shield_with_heart</span>
              </div>
              <div>
                <strong class="text-sm text-emerald-900 block">✅ FIELD READY — All Safety Checks Passed</strong>
                <span id="fieldReadyTimestamp" class="text-[10px] text-emerald-700 mono font-bold"></span>
              </div>
            </div>
            <div class="bg-emerald-600 text-white font-extrabold text-xs px-4 py-2 rounded-xl mono shadow-sm">
              CERTIFIED ✓
            </div>
          </div>
        </div>

        <!-- ============================================================ -->
        <!-- SECTION 4: HAZARD ASSESSMENT MATRIX (Risk Score Calculator)   -->
        <!-- ============================================================ -->
        <div class="bg-white p-6 rounded-2xl border border-[#e5e7eb] shadow-sm flex flex-col gap-5">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-gray-100">
            <div>
              <h2 class="text-base font-bold text-[#111827] flex items-center gap-2">
                <span class="material-symbols-outlined text-amber-600">warning</span>
                Hazard Assessment Matrix — Field Risk Calculator
              </h2>
              <p class="text-xs text-[#64748b] mt-0.5">Input field conditions to calculate a real-time risk score. The system recommends PROCEED / CAUTION / DO NOT ENTER.</p>
            </div>
            <div id="hazardRiskBadge" class="bg-gray-100 text-gray-700 font-bold text-xs px-4 py-2 rounded-xl mono flex items-center gap-1.5 self-start sm:self-auto transition-all">
              RISK SCORE: — / 10
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <!-- Input 1: Hazard Type -->
            <div class="flex flex-col gap-2">
              <label class="text-[11px] font-bold text-[#64748b] uppercase mono tracking-wider">Hazard Type</label>
              <select id="hazardType" onchange="calculateHazardRisk()" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-xs font-bold text-[#111827] bg-[#f8fafc] focus:ring-2 focus:ring-[#1d63d8] focus:border-[#1d63d8] outline-none cursor-pointer">
                <option value="">Select Hazard...</option>
                <option value="fire">🔥 Active Fire / Explosion</option>
                <option value="flood">🌊 Flood / Water Inundation</option>
                <option value="collapse">🏚️ Building Collapse / Debris</option>
                <option value="chemical">☣️ Chemical / HazMat Spill</option>
                <option value="earthquake">🌋 Earthquake Aftershock Zone</option>
                <option value="electrical">⚡ Live Electrical Hazard</option>
                <option value="crowd">👥 Crowd Crush / Stampede</option>
              </select>
            </div>

            <!-- Input 2: Proximity -->
            <div class="flex flex-col gap-2">
              <label class="text-[11px] font-bold text-[#64748b] uppercase mono tracking-wider">Proximity to Danger</label>
              <select id="hazardProximity" onchange="calculateHazardRisk()" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-xs font-bold text-[#111827] bg-[#f8fafc] focus:ring-2 focus:ring-[#1d63d8] focus:border-[#1d63d8] outline-none cursor-pointer">
                <option value="">Select Distance...</option>
                <option value="inside">🔴 Inside Danger Zone (0-50m)</option>
                <option value="near">🟠 Near Perimeter (50-200m)</option>
                <option value="moderate">🟡 Moderate Distance (200-500m)</option>
                <option value="far">🟢 Safe Distance (500m+)</option>
              </select>
            </div>

            <!-- Input 3: People Affected -->
            <div class="flex flex-col gap-2">
              <label class="text-[11px] font-bold text-[#64748b] uppercase mono tracking-wider">People Affected</label>
              <select id="hazardPeople" onchange="calculateHazardRisk()" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-xs font-bold text-[#111827] bg-[#f8fafc] focus:ring-2 focus:ring-[#1d63d8] focus:border-[#1d63d8] outline-none cursor-pointer">
                <option value="">Select Count...</option>
                <option value="few">1-5 People</option>
                <option value="moderate">6-20 People</option>
                <option value="many">21-50 People</option>
                <option value="mass">50+ People (Mass Event)</option>
              </select>
            </div>

            <!-- Input 4: Available Resources -->
            <div class="flex flex-col gap-2">
              <label class="text-[11px] font-bold text-[#64748b] uppercase mono tracking-wider">Your Resources</label>
              <select id="hazardResources" onchange="calculateHazardRisk()" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-xs font-bold text-[#111827] bg-[#f8fafc] focus:ring-2 focus:ring-[#1d63d8] focus:border-[#1d63d8] outline-none cursor-pointer">
                <option value="">Select Level...</option>
                <option value="full">Full PPE + Medical Kit + Backup</option>
                <option value="partial">Partial PPE + Basic Kit</option>
                <option value="minimal">Minimal — No Specialized Equipment</option>
                <option value="none">None — Bare Hands Only</option>
              </select>
            </div>
          </div>

          <!-- Risk Assessment Result Panel -->
          <div id="hazardResultPanel" class="hidden p-5 rounded-xl border-2 flex flex-col sm:flex-row items-center justify-between gap-4 fade-in-up transition-all">
            <div class="flex items-center gap-4">
              <div id="hazardScoreCircle" class="w-16 h-16 rounded-full flex items-center justify-center font-extrabold text-2xl mono text-white shadow-md shrink-0">
                0
              </div>
              <div>
                <strong id="hazardVerdict" class="text-sm block font-extrabold"></strong>
                <p id="hazardAdvice" class="text-xs mt-0.5 leading-relaxed font-medium"></p>
              </div>
            </div>
            <div id="hazardActionBtns" class="flex gap-2 shrink-0"></div>
          </div>
        </div>

        <!-- ============================================================ -->
        <!-- SECTION 5: EMERGENCY PROCEDURE SIMULATOR                     -->
        <!-- ============================================================ -->
        <div class="bg-white p-6 rounded-2xl border border-[#e5e7eb] shadow-sm flex flex-col gap-5">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-gray-100">
            <div>
              <h2 class="text-base font-bold text-[#111827] flex items-center gap-2">
                <span class="material-symbols-outlined text-[#1d63d8]">medical_services</span>
                Emergency Procedure Simulator
              </h2>
              <p class="text-xs text-[#64748b] mt-0.5">Step-by-step guided walkthroughs with timers for critical field procedures. Follow along during a real emergency.</p>
            </div>
          </div>

          <!-- Procedure Selection Tabs -->
          <div class="flex flex-wrap gap-2" id="procSimTabs">
            <button onclick="loadProcedureSim('cpr', this)" class="proc-sim-tab px-4 py-2 bg-[#111827] text-white rounded-xl text-xs font-bold transition-all shadow-xs cursor-pointer flex items-center gap-1.5">
              <span class="material-symbols-outlined text-sm">monitor_heart</span> CPR & AED
            </button>
            <button onclick="loadProcedureSim('tourniquet', this)" class="proc-sim-tab px-4 py-2 bg-gray-50 hover:bg-gray-100 text-gray-700 border border-gray-200 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5">
              <span class="material-symbols-outlined text-sm">healing</span> Tourniquet
            </button>
            <button onclick="loadProcedureSim('burns', this)" class="proc-sim-tab px-4 py-2 bg-gray-50 hover:bg-gray-100 text-gray-700 border border-gray-200 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5">
              <span class="material-symbols-outlined text-sm">local_fire_department</span> Burn Care
            </button>
            <button onclick="loadProcedureSim('choking', this)" class="proc-sim-tab px-4 py-2 bg-gray-50 hover:bg-gray-100 text-gray-700 border border-gray-200 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5">
              <span class="material-symbols-outlined text-sm">air</span> Choking / Heimlich
            </button>
            <button onclick="loadProcedureSim('spinal', this)" class="proc-sim-tab px-4 py-2 bg-gray-50 hover:bg-gray-100 text-gray-700 border border-gray-200 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5">
              <span class="material-symbols-outlined text-sm">accessibility_new</span> Spinal Injury
            </button>
          </div>

          <!-- Procedure Sim Content Area -->
          <div id="procSimContent" class="flex flex-col gap-4">
            <!-- Populated by JS -->
          </div>

          <!-- Procedure Completion Report (hidden until done) -->
          <div id="procSimReport" class="hidden p-5 bg-gradient-to-r from-emerald-50 to-green-50 border-2 border-emerald-400 rounded-xl fade-in-up">
            <div class="flex items-center gap-3 mb-3">
              <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center shrink-0 shadow-sm">
                <span class="material-symbols-outlined text-xl">task_alt</span>
              </div>
              <div>
                <strong class="text-sm text-emerald-900 block">Procedure Completed Successfully</strong>
                <span id="procSimReportTime" class="text-[10px] text-emerald-700 mono font-bold"></span>
              </div>
            </div>
            <div id="procSimReportSteps" class="flex flex-col gap-1 text-xs"></div>
          </div>
        </div>

        <!-- Dynamic SOP Protocol Cards Grid -->
        <div id="guidesGrid" class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="col-span-2 text-center py-12 text-sm text-[#94a3b8]">Loading smart safety guides...</div>
        </div>

      </div>

      <!-- ======================================================== -->
      <!-- TAB 5: VOLUNTEER PROFILE                                 -->
      <!-- ======================================================== -->
      <div id="tab-profile" class="tab-content flex-col gap-6 max-w-[1440px] mx-auto min-h-[calc(100vh-112px)]">
        
        <!-- Profile Header Banner Card -->
        <div class="bg-white p-6 rounded-2xl border border-[#e5e7eb] shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div class="flex items-center gap-5">
            <div class="w-18 h-18 rounded-2xl bg-gradient-to-tr from-[#1d63d8] to-blue-500 text-white flex items-center justify-center font-extrabold text-2xl shadow-md shrink-0">
              <span id="profAvatarInitial">V</span>
            </div>
            <div>
              <div class="flex items-center gap-3">
                <h1 id="profHeaderName" class="text-2xl font-bold text-[#111827] leading-tight">Field Volunteer Alexander Vance</h1>
                <span id="profHeaderBadge" class="bg-emerald-100 text-emerald-800 text-xs font-bold px-2.5 py-0.5 rounded-full uppercase mono">
                  AVAILABLE
                </span>
              </div>
              <p class="text-xs text-[#64748b] font-medium mt-1 flex items-center gap-2">
                <span class="material-symbols-outlined text-sm text-[#1d63d8]">verified_user</span>
                Certified NDRF Field Responder • Node #VOL-03 • <?= htmlspecialchars($_SESSION['user_email'] ?? 'volunteer@ngo.org') ?>
              </p>
            </div>
          </div>

          <div class="flex items-center gap-3">
            <button onclick="loadVolunteerProfile()" class="px-4 py-2.5 bg-[#f1f5f9] hover:bg-[#e2e8f0] text-[#111827] rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer">
              <span class="material-symbols-outlined text-base">refresh</span> Reload
            </button>
            <button onclick="document.getElementById('profileForm').requestSubmit()" class="px-5 py-2.5 bg-[#1d63d8] hover:bg-[#1553c7] text-white rounded-xl text-xs font-bold shadow-sm transition-all flex items-center gap-1.5 cursor-pointer">
              <span class="material-symbols-outlined text-base">save</span> Save Changes
            </button>
          </div>
        </div>

        <!-- Quick Summary Stats Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
          <div class="bg-white p-5 rounded-2xl border border-[#e5e7eb] shadow-sm flex flex-col">
            <span class="text-[11px] font-bold text-[#64748b] uppercase mono">Total Missions</span>
            <div id="profStatCompleted" class="text-2xl font-extrabold text-emerald-600 mono mt-1">1</div>
            <span class="text-[10px] text-[#94a3b8] mt-0.5">Successfully resolved</span>
          </div>

          <div class="bg-white p-5 rounded-2xl border border-[#e5e7eb] shadow-sm flex flex-col">
            <span class="text-[11px] font-bold text-[#64748b] uppercase mono">Active Mission</span>
            <div id="profStatActive" class="text-2xl font-extrabold text-[#1d63d8] mono mt-1">1</div>
            <span class="text-[10px] text-[#94a3b8] mt-0.5">In progress right now</span>
          </div>

          <div class="bg-white p-5 rounded-2xl border border-[#e5e7eb] shadow-sm flex flex-col">
            <span class="text-[11px] font-bold text-[#64748b] uppercase mono">Blood Group</span>
            <div id="profStatBlood" class="text-2xl font-extrabold text-red-600 mono mt-1">O+</div>
            <span class="text-[10px] text-[#94a3b8] mt-0.5">Donor verified</span>
          </div>

          <div class="bg-white p-5 rounded-2xl border border-[#e5e7eb] shadow-sm flex flex-col">
            <span class="text-[11px] font-bold text-[#64748b] uppercase mono">Responder Rating</span>
            <div class="text-2xl font-extrabold text-amber-500 mono mt-1 flex items-center gap-1">
              4.9 <span class="text-base text-amber-400">★</span>
            </div>
            <span class="text-[10px] text-[#94a3b8] mt-0.5">HQ Certified performance</span>
          </div>
        </div>

        <!-- Detailed Profile Form Card -->
        <div class="bg-white p-7 rounded-2xl border border-[#e5e7eb] shadow-sm">
          <div class="border-b border-[#f1f5f9] pb-4 mb-6 flex justify-between items-center">
            <div>
              <h2 class="text-lg font-bold text-[#111827]">Personal &amp; Field Operational Details</h2>
              <p class="text-xs text-[#64748b] mt-0.5">Keep your credentials up-to-date for automated task dispatching and emergency triage verification.</p>
            </div>
            <span class="text-xs bg-blue-50 text-[#1d63d8] font-bold px-3 py-1 rounded-full mono">ID #VOL-03</span>
          </div>

          <form id="profileForm" onsubmit="saveVolunteerProfile(event)" class="space-y-6">
            
            <!-- Row 1: Name, Age, Blood Group -->
            <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
              
              <!-- Name -->
              <div class="md:col-span-6">
                <label class="block text-xs font-bold text-[#334155] uppercase tracking-wider mb-1.5 mono">
                  Full Name <span class="text-red-500">*</span>
                </label>
                <input type="text" id="profName" required class="w-full bg-[#f8fafc] border border-gray-200 focus:border-[#1d63d8] focus:bg-white rounded-xl px-4 py-2.5 text-xs text-[#111827] font-semibold focus:outline-none focus:ring-1 focus:ring-[#1d63d8] transition-all" placeholder="e.g. Alexander Vance" />
              </div>

              <!-- Age -->
              <div class="md:col-span-3">
                <label class="block text-xs font-bold text-[#334155] uppercase tracking-wider mb-1.5 mono">
                  Age <span class="text-red-500">*</span>
                </label>
                <input type="number" id="profAge" min="18" max="75" required class="w-full bg-[#f8fafc] border border-gray-200 focus:border-[#1d63d8] focus:bg-white rounded-xl px-4 py-2.5 text-xs text-[#111827] font-semibold focus:outline-none focus:ring-1 focus:ring-[#1d63d8] transition-all" placeholder="28" />
              </div>

              <!-- Blood Group -->
              <div class="md:col-span-3">
                <label class="block text-xs font-bold text-[#334155] uppercase tracking-wider mb-1.5 mono">
                  Blood Group <span class="text-red-500">*</span>
                </label>
                <select id="profBloodGroup" required class="w-full bg-[#f8fafc] border border-gray-200 focus:border-[#1d63d8] focus:bg-white rounded-xl px-4 py-2.5 text-xs text-[#111827] font-semibold focus:outline-none focus:ring-1 focus:ring-[#1d63d8] transition-all cursor-pointer">
                  <option value="O+">O+ (Universal Donor)</option>
                  <option value="O-">O- (Universal Red Cell)</option>
                  <option value="A+">A+</option>
                  <option value="A-">A-</option>
                  <option value="B+">B+</option>
                  <option value="B-">B-</option>
                  <option value="AB+">AB+ (Universal Plasma)</option>
                  <option value="AB-">AB-</option>
                </select>
              </div>

            </div>

            <!-- Row 2: Mobile No, Emergency Contact, Availability -->
            <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
              
              <!-- Mobile No -->
              <div class="md:col-span-4">
                <label class="block text-xs font-bold text-[#334155] uppercase tracking-wider mb-1.5 mono">
                  Mobile Number <span class="text-red-500">*</span>
                </label>
                <input type="tel" id="profMobile" required class="w-full bg-[#f8fafc] border border-gray-200 focus:border-[#1d63d8] focus:bg-white rounded-xl px-4 py-2.5 text-xs text-[#111827] font-semibold focus:outline-none focus:ring-1 focus:ring-[#1d63d8] transition-all" placeholder="+91 98765 12345" />
              </div>

              <!-- Emergency Contact -->
              <div class="md:col-span-4">
                <label class="block text-xs font-bold text-[#334155] uppercase tracking-wider mb-1.5 mono">
                  Emergency Contact Phone
                </label>
                <input type="tel" id="profEmContact" class="w-full bg-[#f8fafc] border border-gray-200 focus:border-[#1d63d8] focus:bg-white rounded-xl px-4 py-2.5 text-xs text-[#111827] font-semibold focus:outline-none focus:ring-1 focus:ring-[#1d63d8] transition-all" placeholder="+91 98111 22334" />
              </div>

              <!-- Availability Status -->
              <div class="md:col-span-4">
                <label class="block text-xs font-bold text-[#334155] uppercase tracking-wider mb-1.5 mono">
                  Operational Status
                </label>
                <select id="profStatus" class="w-full bg-[#f8fafc] border border-gray-200 focus:border-[#1d63d8] focus:bg-white rounded-xl px-4 py-2.5 text-xs text-[#111827] font-semibold focus:outline-none focus:ring-1 focus:ring-[#1d63d8] transition-all cursor-pointer">
                  <option value="available">🟢 Available for Immediate Dispatch</option>
                  <option value="on_duty">🟡 Currently On Duty / Deployed</option>
                  <option value="off_duty">⚪ Off Duty / Rest Cycle</option>
                </select>
              </div>

            </div>

            <!-- Row 3: Speciality & Address -->
            <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
              
              <!-- Speciality -->
              <div class="md:col-span-6">
                <label class="block text-xs font-bold text-[#334155] uppercase tracking-wider mb-1.5 mono">
                  Speciality &amp; Field Expertise <span class="text-red-500">*</span>
                </label>
                <select id="profSpeciality" required class="w-full bg-[#f8fafc] border border-gray-200 focus:border-[#1d63d8] focus:bg-white rounded-xl px-4 py-2.5 text-xs text-[#111827] font-semibold focus:outline-none focus:ring-1 focus:ring-[#1d63d8] transition-all cursor-pointer">
                  <option value="First Aid, Triage &amp; Trauma Care">First Aid, Triage &amp; Trauma Care</option>
                  <option value="Urban Search &amp; Collapse Rescue">Urban Search &amp; Collapse Rescue</option>
                  <option value="Flood &amp; Swift Water Rescue">Flood &amp; Swift Water Rescue</option>
                  <option value="Firefighting Support &amp; Hazmat">Firefighting Support &amp; Hazmat</option>
                  <option value="Relief Camp Logistics &amp; Rations">Relief Camp Logistics &amp; Rations</option>
                  <option value="Drone Aerial Reconnaissance">Drone Aerial Reconnaissance</option>
                </select>
              </div>

              <!-- Address -->
              <div class="md:col-span-6">
                <label class="block text-xs font-bold text-[#334155] uppercase tracking-wider mb-1.5 mono">
                  Base Address / Relief Camp Location <span class="text-red-500">*</span>
                </label>
                <input type="text" id="profAddress" required class="w-full bg-[#f8fafc] border border-gray-200 focus:border-[#1d63d8] focus:bg-white rounded-xl px-4 py-2.5 text-xs text-[#111827] font-semibold focus:outline-none focus:ring-1 focus:ring-[#1d63d8] transition-all" placeholder="e.g. Sector 4 Relief Camp, Quarter #12" />
              </div>

            </div>

            <!-- Submit Button Bar -->
            <div class="pt-4 border-t border-gray-100 flex justify-end gap-3">
              <button type="button" onclick="loadVolunteerProfile()" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-[#475569] font-bold text-xs rounded-xl transition-all cursor-pointer">
                Cancel
              </button>
              <button type="submit" id="btnSaveProfile" class="px-6 py-2.5 bg-[#1d63d8] hover:bg-[#1553c7] text-white font-bold text-xs rounded-xl shadow-sm transition-all flex items-center gap-2 cursor-pointer">
                <span class="material-symbols-outlined text-base">check</span> Save Profile
              </button>
            </div>

          </form>
        </div>

      </div>

    </main>
  </div>

  <!-- ======================================================== -->
  <!-- MODERN POPUP MODAL COMPONENT (CUSTOM UI)                 -->
  <!-- ======================================================== -->
  <div id="customModalOverlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[9999] hidden flex items-center justify-center p-4 transition-opacity duration-200">
    <div id="customModalCard" class="bg-white rounded-2xl border border-gray-100 shadow-2xl max-w-lg w-full overflow-hidden transform scale-95 transition-all duration-200">
      
      <!-- Modal Header -->
      <div id="customModalHeader" class="p-5 flex items-center gap-3 border-b border-gray-100 bg-[#f8fafc]">
        <div id="customModalIcon" class="w-10 h-10 rounded-xl flex items-center justify-center text-white shrink-0 shadow-sm">
          <span class="material-symbols-outlined text-2xl">info</span>
        </div>
        <div class="flex-1">
          <h3 id="customModalTitle" class="text-base font-bold text-[#111827]"></h3>
          <p id="customModalSubtitle" class="text-xs text-[#64748b] font-medium"></p>
        </div>
        <button onclick="closeCustomModal()" class="text-gray-400 hover:text-gray-700 p-1.5 rounded-lg hover:bg-gray-200/60 transition-colors">
          <span class="material-symbols-outlined text-lg">close</span>
        </button>
      </div>

      <!-- Modal Body -->
      <div id="customModalBody" class="p-6 text-sm text-[#334155] leading-relaxed max-h-[60vh] overflow-y-auto custom-scroll">
      </div>

      <!-- Modal Footer -->
      <div id="customModalFooter" class="p-4 bg-gray-50/80 border-t border-gray-100 flex justify-end gap-2.5">
        <button id="customModalCancelBtn" onclick="closeCustomModal()" class="px-4 py-2.5 bg-white hover:bg-gray-100 text-[#475569] font-bold text-xs rounded-xl border border-gray-200 transition-all cursor-pointer">
          Cancel
        </button>
        <button id="customModalConfirmBtn" class="px-5 py-2.5 bg-[#1d63d8] hover:bg-[#1553c7] text-white font-bold text-xs rounded-xl shadow-sm transition-all cursor-pointer">
          Confirm
        </button>
      </div>

    </div>
  </div>

  <!-- ======================================================== -->
  <!-- TOAST NOTIFICATION CONTAINER                             -->
  <!-- ======================================================== -->
  <div id="customToastContainer" class="fixed bottom-5 right-5 z-[9999] flex flex-col gap-2.5 pointer-events-none"></div>

  <!-- ======================================================== -->
  <!-- MODAL: REPORT ON BEHALF OF A CITIZEN (REPORT CITIZEN SOS) -->
  <!-- ======================================================== -->
  <div id="proxySosModal" class="fixed inset-0 z-[9998] flex items-center justify-center bg-black/60 backdrop-blur-xs hidden p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-gray-200 flex flex-col gap-4 max-h-[92vh] overflow-y-auto custom-scroll fade-in-up">
      
      <!-- Modal Header -->
      <div class="flex items-center justify-between pb-3 border-b border-gray-100">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-red-100 text-red-600 flex items-center justify-center font-bold shrink-0">
            <span class="material-symbols-outlined notranslate text-2xl" translate="no">emergency</span>
          </div>
          <div>
            <h3 class="text-base font-bold text-[#111827]">Report Citizen SOS</h3>
            <p class="text-xs text-[#64748b]">On-scene incident registration for citizens in immediate distress.</p>
          </div>
        </div>
        <button type="button" onclick="closeProxySosModal()" class="text-gray-400 hover:text-gray-600 text-xl font-bold cursor-pointer">✕</button>
      </div>

      <!-- Form with Exact Requested Inputs -->
      <form id="proxySosForm" onsubmit="handleProxySosSubmit(event)" class="space-y-3.5">
        
        <!-- 1. Name -->
        <div>
          <label class="block text-xs font-bold text-[#334155] uppercase tracking-wider mb-1 mono">
            Name <span class="text-red-500">*</span>
          </label>
          <input type="text" id="proxyVictimName" required class="w-full bg-[#f8fafc] border border-gray-200 rounded-xl px-3.5 py-2.5 text-xs text-[#111827] focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 font-semibold" placeholder="Enter citizen / victim full name" />
        </div>

        <!-- 2. Phone number -->
        <div>
          <label class="block text-xs font-bold text-[#334155] uppercase tracking-wider mb-1 mono">
            Phone number
          </label>
          <input type="tel" id="proxyPhone" class="w-full bg-[#f8fafc] border border-gray-200 rounded-xl px-3.5 py-2.5 text-xs text-[#111827] focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 font-semibold" placeholder="e.g. +91 98765 43210 (or bystander phone)" />
        </div>

        <!-- 3. Number of people needing help & Emergency type -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
          <div>
            <label class="block text-xs font-bold text-[#334155] uppercase tracking-wider mb-1 mono">
              Number of people needing help <span class="text-red-500">*</span>
            </label>
            <input type="number" id="proxyPeopleCount" min="1" max="500" value="1" required class="w-full bg-[#f8fafc] border border-gray-200 rounded-xl px-3.5 py-2.5 text-xs text-[#111827] focus:outline-none focus:border-red-500 font-semibold mono" />
          </div>

          <div>
            <label class="block text-xs font-bold text-[#334155] uppercase tracking-wider mb-1 mono">
              Emergency type <span class="text-red-500">*</span>
            </label>
            <select id="proxyEmergencyType" required class="w-full bg-[#f8fafc] border border-gray-200 rounded-xl px-3.5 py-2.5 text-xs text-[#111827] focus:outline-none focus:border-red-500 font-semibold cursor-pointer">
              <option value="Medical" selected>🏥 Medical</option>
              <option value="Rescue">🛶 Rescue</option>
              <option value="Food/Water">🍞 Food/Water</option>
              <option value="Fire">🔥 Fire</option>
            </select>
          </div>
        </div>

        <!-- 4. GPS location -->
        <div>
          <div class="flex items-center justify-between mb-1">
            <label class="text-xs font-bold text-[#334155] uppercase tracking-wider mono">
              GPS location <span class="text-red-500">*</span>
            </label>
            <button type="button" onclick="autoFillProxyGps()" class="text-[11px] text-[#1d63d8] hover:text-[#1553c7] font-bold flex items-center gap-1 cursor-pointer">
              <span class="material-symbols-outlined notranslate text-sm" translate="no">my_location</span> <span>Use My Current GPS</span>
            </button>
          </div>
          <input type="text" id="proxyAddress" required class="w-full bg-[#f8fafc] border border-gray-200 rounded-xl px-3.5 py-2.5 text-xs text-[#111827] focus:outline-none focus:border-red-500 font-semibold" placeholder="GPS Coordinates or Landmark (e.g. 28.618900, 77.215000 — Sector 4)" />
        </div>

        <!-- 5. Message (optional) -->
        <div>
          <label class="block text-xs font-bold text-[#334155] uppercase tracking-wider mb-1 mono">
            Message (optional)
          </label>
          <textarea id="proxyDescription" rows="2" class="w-full bg-[#f8fafc] border border-gray-200 rounded-xl p-3 text-xs text-[#111827] focus:outline-none focus:border-red-500 font-medium" placeholder="Add any scene notes, situation description, or requirements (optional)..."></textarea>
        </div>

        <!-- Submit & Cancel Actions -->
        <div class="pt-3 border-t border-gray-100 flex justify-end gap-3">
          <button type="button" onclick="closeProxySosModal()" class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-[#475569] font-bold text-xs rounded-xl transition-colors cursor-pointer" data-i18n="btn_cancel">
            Cancel
          </button>
          <button type="submit" id="btnSubmitProxySos" class="px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white font-bold text-xs rounded-xl transition-all shadow-sm flex items-center gap-1.5 cursor-pointer">
            <span class="material-symbols-outlined notranslate text-base" translate="no">emergency_share</span> <span>Submit Citizen SOS</span>
          </button>
        </div>
      </form>

    </div>
  </div>

  <!-- ======================================================== -->
  <!-- FUNCTIONAL FIELD INCIDENT PHOTO UPLOAD / CAMERA MODAL    -->
  <!-- ======================================================== -->
  <div id="photoUploadModal" class="fixed inset-0 z-[1100] bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl border border-gray-100 overflow-hidden flex flex-col fade-in-up">
      
      <!-- Modal Header -->
      <div class="p-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-[#1d63d8] text-white flex items-center justify-center font-bold shrink-0 shadow-xs">
            <span class="material-symbols-outlined notranslate text-xl" translate="no">photo_camera</span>
          </div>
          <div>
            <h3 class="text-sm font-bold text-[#111827]">Attach Field Incident Photo</h3>
            <p class="text-[11px] text-[#64748b]">Live Camera Snapshot &amp; Visual Damage Telemetry</p>
          </div>
        </div>
        <button onclick="closePhotoUploadModal()" class="text-gray-400 hover:text-gray-600 p-1.5 rounded-lg hover:bg-gray-100 transition-colors cursor-pointer">
          <span class="material-symbols-outlined notranslate text-lg" translate="no">close</span>
        </button>
      </div>

      <!-- Mode Selector Tabs -->
      <div class="flex border-b border-gray-100 bg-[#f8fafc] px-4 pt-2 gap-2">
        <button type="button" id="tabPhotoCamera" onclick="switchPhotoUploadTab('camera')" class="px-4 py-2 text-xs font-bold border-b-2 border-[#1d63d8] text-[#1d63d8] flex items-center gap-1.5 transition-all cursor-pointer">
          <span class="material-symbols-outlined notranslate text-base" translate="no">videocam</span> Live Camera
        </button>
        <button type="button" id="tabPhotoFile" onclick="switchPhotoUploadTab('file')" class="px-4 py-2 text-xs font-semibold text-[#64748b] hover:text-[#111827] border-b-2 border-transparent flex items-center gap-1.5 transition-all cursor-pointer">
          <span class="material-symbols-outlined notranslate text-base" translate="no">upload_file</span> Browse / Gallery
        </button>
      </div>

      <!-- Modal Body -->
      <form id="fieldPhotoUploadForm" onsubmit="handleTransmitFieldPhoto(event)" class="p-5 flex flex-col gap-4">
        
        <!-- Tab 1: Camera View -->
        <div id="cameraTabContent" class="flex flex-col gap-3">
          <div class="relative w-full h-56 bg-black rounded-xl overflow-hidden flex items-center justify-center border border-gray-800">
            <video id="fieldCameraVideo" autoplay playsinline muted class="w-full h-full object-cover"></video>
            <canvas id="fieldCameraCanvas" class="hidden"></canvas>
            
            <!-- Camera Status Overlay -->
            <div id="cameraStartPrompt" class="absolute inset-0 bg-gray-900/90 flex flex-col items-center justify-center p-4 text-center text-white gap-2">
              <span class="material-symbols-outlined notranslate text-4xl text-blue-400 animate-pulse" translate="no">photo_camera</span>
              <p class="text-xs font-medium text-gray-300">Click below to grant camera access and start live preview</p>
              <button type="button" onclick="startCameraStream()" class="px-4 py-2 bg-[#1d63d8] hover:bg-[#1553c7] text-white text-xs font-bold rounded-xl transition-all shadow-sm flex items-center gap-1.5 cursor-pointer">
                <span class="material-symbols-outlined notranslate text-base" translate="no">play_arrow</span> Start Camera
              </button>
            </div>

            <!-- Snap Button Overlay (When active) -->
            <div id="cameraControls" class="absolute bottom-3 inset-x-0 flex justify-center items-center gap-3 hidden">
              <button type="button" onclick="takeCameraSnapshot()" class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-full shadow-lg flex items-center gap-1.5 transition-all transform hover:scale-105 cursor-pointer">
                <span class="material-symbols-outlined notranslate text-base" translate="no">camera</span> Snap Photo
              </button>
            </div>
          </div>
        </div>

        <!-- Tab 2: File Upload View -->
        <div id="fileTabContent" class="flex flex-col gap-3 hidden">
          <div onclick="document.getElementById('fieldPhotoFileInput').click()" class="border-2 border-dashed border-gray-300 hover:border-[#1d63d8] bg-gray-50 hover:bg-blue-50/40 rounded-xl p-6 text-center transition-all cursor-pointer flex flex-col items-center justify-center gap-2">
            <input type="file" id="fieldPhotoFileInput" accept="image/*" class="hidden" onchange="handleFieldPhotoFileSelected(event)" />
            <span class="material-symbols-outlined notranslate text-4xl text-gray-400" translate="no">cloud_upload</span>
            <div>
              <strong class="text-xs text-[#111827] block font-bold">Click to select incident photo</strong>
              <span class="text-[11px] text-[#64748b]">JPG, PNG, WEBP from device or gallery</span>
            </div>
          </div>
        </div>

        <!-- Captured / Selected Photo Preview Box -->
        <div id="photoPreviewContainer" class="hidden flex flex-col gap-2 p-3 bg-gray-50 rounded-xl border border-gray-200">
          <div class="flex items-center justify-between text-xs">
            <span class="font-bold text-[#111827] flex items-center gap-1">
              <span class="material-symbols-outlined notranslate text-sm text-emerald-600" translate="no">check_circle</span> Photo Captured
            </span>
            <button type="button" onclick="resetPhotoCapture()" class="text-red-600 hover:underline font-bold text-[11px] flex items-center gap-0.5 cursor-pointer">
              <span class="material-symbols-outlined notranslate text-xs" translate="no">refresh</span> Retake Photo
            </button>
          </div>
          <div class="relative w-full h-44 rounded-lg overflow-hidden border border-gray-200 bg-black">
            <img id="photoPreviewImg" src="" alt="Snapshot Preview" class="w-full h-full object-cover" />
          </div>
        </div>

        <!-- Caption and Metadata Inputs -->
        <div class="flex flex-col gap-2.5">
          <div>
            <label class="block text-[11px] font-bold text-[#334155] uppercase tracking-wider mb-1 mono">
              Incident Notes / Scene Assessment <span class="text-red-500">*</span>
            </label>
            <input type="text" id="fieldPhotoCaption" required class="w-full bg-[#f8fafc] border border-gray-200 rounded-xl px-3.5 py-2 text-xs text-[#111827] focus:outline-none focus:border-[#1d63d8] font-medium" placeholder="e.g. Flooded roadway near bridge / Fallen electrical wire..." />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-[11px] font-bold text-[#334155] uppercase tracking-wider mb-1 mono">Priority</label>
              <select id="fieldPhotoPriority" class="w-full bg-[#f8fafc] border border-gray-200 rounded-xl px-3 py-2 text-xs text-[#111827] font-semibold cursor-pointer">
                <option value="urgent">🚨 Urgent Attention</option>
                <option value="flash">⚡ Flash Critical</option>
                <option value="normal">Normal Ops</option>
              </select>
            </div>
            <div>
              <label class="block text-[11px] font-bold text-[#334155] uppercase tracking-wider mb-1 mono">Comms Channel</label>
              <select id="fieldPhotoChannel" class="w-full bg-[#f8fafc] border border-gray-200 rounded-xl px-3 py-2 text-xs text-[#111827] font-semibold cursor-pointer">
                <option value="ops">#Ops Channel</option>
                <option value="alerts">⚡ Flash Alerts</option>
                <option value="all">All Net Broadcast</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Modal Actions -->
        <div class="pt-3 border-t border-gray-100 flex justify-end gap-2.5">
          <button type="button" onclick="closePhotoUploadModal()" class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-[#475569] font-bold text-xs rounded-xl transition-colors cursor-pointer">
            Cancel
          </button>
          <button type="submit" id="btnSubmitTransmitPhoto" class="px-6 py-2.5 bg-[#1d63d8] hover:bg-[#1553c7] text-white font-bold text-xs rounded-xl transition-all shadow-sm flex items-center gap-1.5 cursor-pointer">
            <span class="material-symbols-outlined notranslate text-base" translate="no">send</span> Transmit to HQ
          </button>
        </div>

      </form>

    </div>
  </div>

  <!-- Fullscreen Photo Lightbox Modal -->
  <div id="photoLightboxModal" class="fixed inset-0 z-[1200] bg-black/90 backdrop-blur-md flex flex-col items-center justify-center p-4 hidden" onclick="closePhotoLightbox()">
    <div class="absolute top-4 right-4 flex items-center gap-3 z-10">
      <a id="lightboxDownloadBtn" href="#" download="incident_photo.jpg" class="p-2 bg-white/20 hover:bg-white/30 text-white rounded-xl text-xs font-bold backdrop-blur-xs flex items-center gap-1 transition-all" onclick="event.stopPropagation()">
        <span class="material-symbols-outlined notranslate text-lg" translate="no">download</span> Download
      </a>
      <button class="p-2 bg-white/20 hover:bg-white/30 text-white rounded-xl transition-all cursor-pointer" onclick="closePhotoLightbox()">
        <span class="material-symbols-outlined notranslate text-2xl" translate="no">close</span>
      </button>
    </div>
    <img id="lightboxImg" src="" alt="High-Res Incident Photo" class="max-w-full max-h-[85vh] object-contain rounded-xl shadow-2xl border border-white/20" onclick="event.stopPropagation()" />
  </div>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
  <script src="js/volunteer_i18n.js"></script>
  <script>
    // ============================================================
    // GLOBAL STATE
    // ============================================================
    const CURRENT_USER_ID = <?= (int)$userId ?>;
    const CURRENT_USER_NAME = <?= json_encode($userName) ?>;
    let currentTaskId = null;
    let currentAssignment = null;
    let currentDirectOfferId = null;
    let volunteerLat = 28.6189;
    let volunteerLng = 77.2150;

    // Map layer groups for toggling
    let victimLayerGroup, resourceLayerGroup, routeLayerGroup;

    // ============================================================
    // CUSTOM MODAL & TOAST POPUP ENGINE
    // ============================================================
    let modalConfirmCallback = null;
    const showCustomModal = (args) => openCustomModal(args);
    function loadMapStats() { if (typeof loadTacticalMapData === 'function') return loadTacticalMapData(); }

    function openCustomModal({ title, subtitle, icon, iconBg, bodyHtml, confirmText, cancelText, showCancel = true, onConfirm = null }) {
      const overlay = document.getElementById('customModalOverlay');
      const card = document.getElementById('customModalCard');
      const modalTitle = document.getElementById('customModalTitle');
      const modalSub = document.getElementById('customModalSubtitle');
      const modalIcon = document.getElementById('customModalIcon');
      const modalBody = document.getElementById('customModalBody');
      const confirmBtn = document.getElementById('customModalConfirmBtn');
      const cancelBtn = document.getElementById('customModalCancelBtn');

      modalTitle.textContent = title || 'Notification';
      modalSub.textContent = subtitle || 'DisasterSafe Field Dispatch';
      modalIcon.innerHTML = `<span class="material-symbols-outlined notranslate text-2xl" translate="no">${icon || 'info'}</span>`;
      
      // Support both Tailwind bg classes and raw hex colors
      modalIcon.style.backgroundColor = '';
      if (iconBg && iconBg.startsWith('#')) {
        modalIcon.className = 'w-10 h-10 rounded-xl flex items-center justify-center text-white shrink-0 shadow-sm';
        modalIcon.style.backgroundColor = iconBg;
      } else {
        modalIcon.className = `w-10 h-10 rounded-xl flex items-center justify-center text-white shrink-0 shadow-sm ${iconBg || 'bg-[#1d63d8]'}`;
      }

      modalBody.innerHTML = bodyHtml || '';

      confirmBtn.textContent = confirmText || 'Confirm';
      confirmBtn.style.backgroundColor = '';
      if (iconBg && iconBg.startsWith('#')) {
        confirmBtn.className = 'px-5 py-2.5 font-bold text-xs rounded-xl shadow-sm transition-all cursor-pointer text-white hover:opacity-90';
        confirmBtn.style.backgroundColor = iconBg;
      } else if (iconBg && iconBg.startsWith('bg-')) {
        confirmBtn.className = `px-5 py-2.5 font-bold text-xs rounded-xl shadow-sm transition-all cursor-pointer ${iconBg} text-white hover:opacity-90`;
      } else {
        confirmBtn.className = 'px-5 py-2.5 font-bold text-xs rounded-xl shadow-sm transition-all cursor-pointer bg-[#1d63d8] hover:bg-[#1553c7] text-white';
      }
      
      if (showCancel) {
        cancelBtn.classList.remove('hidden');
        cancelBtn.textContent = cancelText || 'Cancel';
      } else {
        cancelBtn.classList.add('hidden');
      }

      modalConfirmCallback = onConfirm;

      confirmBtn.onclick = () => {
        if (modalConfirmCallback) modalConfirmCallback();
        closeCustomModal();
      };

      overlay.classList.remove('hidden');
      setTimeout(() => {
        card.classList.remove('scale-95');
        card.classList.add('scale-100');
      }, 10);
    }

    function closeCustomModal() {
      const overlay = document.getElementById('customModalOverlay');
      const card = document.getElementById('customModalCard');
      card.classList.remove('scale-100');
      card.classList.add('scale-95');
      setTimeout(() => {
        overlay.classList.add('hidden');
        modalConfirmCallback = null;
      }, 150);
    }

    function showToast({ title, message, type = 'info' }) {
      const container = document.getElementById('customToastContainer');
      const toast = document.createElement('div');
      
      const typeMap = {
        success: { bg: 'bg-emerald-600', icon: 'check_circle' },
        warning: { bg: 'bg-amber-600', icon: 'warning' },
        danger: { bg: 'bg-red-600', icon: 'emergency' },
        info: { bg: 'bg-[#1d63d8]', icon: 'info' }
      };
      const cfg = typeMap[type] || typeMap.info;

      toast.className = 'pointer-events-auto bg-white border border-gray-100 rounded-2xl shadow-xl p-4 flex items-center gap-3 max-w-sm w-full fade-in-up';
      toast.innerHTML = `
        <div class="w-8 h-8 rounded-lg ${cfg.bg} text-white flex items-center justify-center shrink-0">
          <span class="material-symbols-outlined text-lg">${cfg.icon}</span>
        </div>
        <div class="flex-1">
          <div class="text-xs font-bold text-[#111827]">${escapeHtml(title || 'Alert')}</div>
          <div class="text-xs text-[#64748b] mt-0.5">${escapeHtml(message || '')}</div>
        </div>
        <button onclick="this.parentElement.remove()" class="text-gray-400 hover:text-gray-600 p-1">
          <span class="material-symbols-outlined text-sm">close</span>
        </button>
      `;

      container.appendChild(toast);
      setTimeout(() => {
        toast.style.transition = 'all 0.3s ease';
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(10px)';
        setTimeout(() => toast.remove(), 300);
      }, 3500);
    }

    // Modal Triggers
    function showHelpModal() {
      openCustomModal({
        title: 'Volunteer Operations Guide',
        subtitle: 'Standard SOP & Mission Navigation',
        icon: 'help',
        iconBg: 'bg-[#1d63d8]',
        showCancel: false,
        confirmText: 'Understood',
        bodyHtml: `
          <div class="flex flex-col gap-3">
            <div class="p-3.5 bg-blue-50 border border-blue-100 rounded-xl flex items-start gap-3">
              <span class="material-symbols-outlined text-xl text-[#1d63d8] shrink-0 mt-0.5">assignment</span>
              <div>
                <strong class="text-xs text-[#1e3a8a] block font-bold">1. Active Assignment Card</strong>
                <p class="text-xs text-[#3b82f6] mt-0.5">Check victim condition, address, and verify required resources using the interactive checklist.</p>
              </div>
            </div>

            <div class="p-3.5 bg-emerald-50 border border-emerald-100 rounded-xl flex items-start gap-3">
              <span class="material-symbols-outlined text-xl text-emerald-600 shrink-0 mt-0.5">near_me</span>
              <div>
                <strong class="text-xs text-emerald-800 block font-bold">2. Field Tactical Navigation</strong>
                <p class="text-xs text-emerald-700 mt-0.5">Follow the blue dashed route corridor to reach the target victim. Locate nearest Triage for safe transit.</p>
              </div>
            </div>

            <div class="p-3.5 bg-purple-50 border border-purple-100 rounded-xl flex items-start gap-3">
              <span class="material-symbols-outlined text-xl text-purple-600 shrink-0 mt-0.5">forum</span>
              <div>
                <strong class="text-xs text-purple-800 block font-bold">3. Coordinator Comms Channel</strong>
                <p class="text-xs text-purple-700 mt-0.5">Use quick dispatch buttons (📍 On Scene, 🚑 Need EMS, ✅ Task Done) to keep HQ updated in real time.</p>
              </div>
            </div>
          </div>
        `
      });
    }

    async function showNotificationsModal() {
      openCustomModal({
        title: 'Disaster Dispatch Feed',
        subtitle: 'Live Actionable Telemetry & Priority Broadcasts',
        icon: 'notifications_active',
        iconBg: 'bg-amber-600',
        showCancel: false,
        confirmText: 'Close Feed',
        bodyHtml: `
          <div id="dispatchFeedLoading" class="py-8 text-center text-xs text-[#64748b] flex flex-col items-center justify-center gap-2">
            <span class="material-symbols-outlined notranslate text-2xl text-amber-600 animate-spin" translate="no">progress_activity</span>
            <span>Fetching live dispatch telemetry...</span>
          </div>
          <div id="dispatchFeedContainer" class="flex flex-col gap-3 hidden max-h-[60vh] overflow-y-auto custom-scroll pr-1"></div>
        `
      });

      try {
        const res = await fetch('api/notifications_feed.php', { credentials: 'include' });
        const data = await res.json();
        const loading = document.getElementById('dispatchFeedLoading');
        const container = document.getElementById('dispatchFeedContainer');

        if (data.success && data.data && container) {
          if (loading) loading.classList.add('hidden');
          container.classList.remove('hidden');

          const notifs = data.data.notifications || [];
          if (notifs.length === 0) {
            container.innerHTML = `
              <div class="py-8 text-center text-xs text-gray-400">
                <span class="material-symbols-outlined notranslate text-3xl mb-1 text-emerald-600" translate="no">check_circle</span>
                <p class="font-semibold text-[#111827]">All dispatch streams clear</p>
                <p class="text-[11px] text-[#64748b] mt-0.5">No pending high-priority alerts.</p>
              </div>
            `;
            return;
          }

          container.innerHTML = notifs.map(n => `
            <div class="p-3.5 rounded-xl border-l-4 ${n.border_color} border border-gray-200 shadow-xs flex flex-col gap-2 transition-all hover:shadow-sm">
              <div class="flex justify-between items-center text-[10px] font-bold uppercase mono">
                <span class="px-1.5 py-0.5 rounded ${n.badge_color}">${escapeHtml(n.badge)}</span>
                <span class="text-[#64748b]">${escapeHtml(n.time_ago)}</span>
              </div>
              <div>
                <strong class="text-xs text-[#111827] block font-bold">${escapeHtml(n.title)}</strong>
                <p class="text-[11px] text-[#475569] mt-0.5 leading-relaxed font-medium">${escapeHtml(n.message)}</p>
              </div>
              <div class="pt-2 border-t border-black/5 flex justify-end">
                <button type="button" onclick="executeNotificationAction('${n.action_type}', ${escapeHtml(JSON.stringify(n.meta || {}))})" class="px-3.5 py-1.5 ${n.action_btn_class} text-xs font-bold rounded-lg shadow-xs flex items-center gap-1.5 transition-all transform hover:scale-[1.02] cursor-pointer">
                  <span class="material-symbols-outlined notranslate text-sm" translate="no">${n.action_icon}</span> ${escapeHtml(n.action_label)}
                </button>
              </div>
            </div>
          `).join('');
        }
      } catch (err) {
        console.error('Error loading dispatch feed:', err);
      }
    }

    function executeNotificationAction(actionType, meta) {
      closeCustomModal();

      if (actionType === 'respond_request') {
        switchVolunteerTab('assignments');
        const drCard = document.getElementById('directRequestCard');
        if (drCard) {
          drCard.classList.remove('hidden');
          drCard.classList.add('flex');
          drCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        if (meta && meta.lat && meta.lng && window.mapInstance) {
          window.mapInstance.flyTo([meta.lat, meta.lng], 16, { animate: true, duration: 1.2 });
          showToast({
            title: 'Target Location Locked',
            message: `Map centered on ${meta.victim_name || 'reported location'}.`,
            type: 'danger'
          });
        }
      } else if (actionType === 'claim_supplies') {
        switchVolunteerTab('resources');
        switchResourceStage('locate');
        const filterBtn = Array.from(document.querySelectorAll('.res-filter-btn')).find(b => b.textContent.includes('Medical') || b.textContent.includes('Depots'));
        filterResources(meta.category || 'medical_supplies', filterBtn);
        showToast({
          title: 'Depot Stock Focused',
          message: 'Supplies catalog opened. Click Claim to load units into vehicle.',
          type: 'info'
        });
      } else if (actionType === 'open_comms') {
        switchVolunteerTab('assignments');
        const channel = meta.channel || 'ops';
        const tabBtn = document.getElementById(`commsTab-${channel}`) || document.getElementById('commsTab-ops');
        switchCommsChannel(channel, tabBtn);
        const chatFeed = document.getElementById('chatFeed');
        if (chatFeed) chatFeed.scrollTop = chatFeed.scrollHeight;
        showToast({
          title: 'Coordinator Comms Stream',
          message: `Switched to #${channel} command channel.`,
          type: 'warning'
        });
      } else if (actionType === 'open_telemetry') {
        showSystemStatusModal();
      }
    }

    function showSystemStatusModal() {
      openCustomModal({
        title: 'System Telemetry & Network Status',
        subtitle: 'Field Node Real-Time Connection Diagnostic',
        icon: 'analytics',
        iconBg: 'bg-[#111827]',
        showCancel: false,
        confirmText: 'Dismiss',
        bodyHtml: `
          <div class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
              <div class="p-3 bg-[#f8fafc] border border-gray-200 rounded-xl">
                <div class="text-[10px] font-bold text-[#64748b] uppercase mono">GPS Telemetry</div>
                <div class="text-xs font-bold text-emerald-600 mt-1 flex items-center gap-1">
                  <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                  ${volunteerLat.toFixed(4)}, ${volunteerLng.toFixed(4)}
                </div>
              </div>
              <div class="p-3 bg-[#f8fafc] border border-gray-200 rounded-xl">
                <div class="text-[10px] font-bold text-[#64748b] uppercase mono">Dispatch Polling</div>
                <div class="text-xs font-bold text-[#1d63d8] mt-1">4.0s Active Sync</div>
              </div>
            </div>
            <div class="p-3 bg-gray-50 border border-gray-200 rounded-xl flex items-center justify-between">
              <div>
                <div class="text-xs font-bold text-[#111827]">Server Response Latency</div>
                <div class="text-[11px] text-[#64748b]">Connected to WAMP MariaDB Local Node</div>
              </div>
              <span class="text-xs font-bold text-emerald-700 bg-emerald-100 px-2.5 py-1 rounded-full mono">24ms OK</span>
            </div>
          </div>
        `
      });
    }

    // ============================================================
    // FUNCTIONAL FIELD CAMERA & PHOTO TRANSMISSION ENGINE
    // ============================================================
    let fieldCameraStream = null;
    let selectedPhotoBase64 = null;

    function showPhotoUploadModal() {
      const modal = document.getElementById('photoUploadModal');
      if (!modal) return;

      modal.classList.remove('hidden');
      resetPhotoCapture();
      switchPhotoUploadTab('camera');
      startCameraStream();
    }

    function closePhotoUploadModal() {
      const modal = document.getElementById('photoUploadModal');
      if (modal) modal.classList.add('hidden');
      stopCameraStream();
      resetPhotoCapture();
    }

    async function startCameraStream() {
      const video = document.getElementById('fieldCameraVideo');
      const prompt = document.getElementById('cameraStartPrompt');
      const controls = document.getElementById('cameraControls');
      if (!video) return;

      stopCameraStream();

      try {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
          throw new Error('Camera API not supported in this browser.');
        }

        const stream = await navigator.mediaDevices.getUserMedia({
          video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } }
        }).catch(() => {
          // Fallback to basic video constraint
          return navigator.mediaDevices.getUserMedia({ video: true });
        });

        fieldCameraStream = stream;
        video.srcObject = stream;
        video.play();

        if (prompt) prompt.classList.add('hidden');
        if (controls) controls.classList.remove('hidden');
      } catch (err) {
        console.warn('Camera access error:', err);
        if (prompt) {
          prompt.innerHTML = `
            <span class="material-symbols-outlined text-4xl text-amber-400">no_photography</span>
            <p class="text-xs text-gray-300">Camera access unavailable or blocked.</p>
            <button type="button" onclick="switchPhotoUploadTab('file')" class="px-4 py-2 bg-[#1d63d8] hover:bg-[#1553c7] text-white text-xs font-bold rounded-xl shadow-xs cursor-pointer">
              Switch to Browse File
            </button>
          `;
        }
      }
    }

    function stopCameraStream() {
      if (fieldCameraStream) {
        fieldCameraStream.getTracks().forEach(track => track.stop());
        fieldCameraStream = null;
      }
      const video = document.getElementById('fieldCameraVideo');
      if (video) video.srcObject = null;
    }

    function takeCameraSnapshot() {
      const video = document.getElementById('fieldCameraVideo');
      const canvas = document.getElementById('fieldCameraCanvas');
      if (!video || !canvas) return;

      canvas.width = video.videoWidth || 640;
      canvas.height = video.videoHeight || 480;
      const ctx = canvas.getContext('2d');
      ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

      const base64Data = canvas.toDataURL('image/jpeg', 0.85);
      setCapturedPhoto(base64Data);
      stopCameraStream();
    }

    function handleFieldPhotoFileSelected(event) {
      const file = event.target.files && event.target.files[0];
      if (!file) return;

      const reader = new FileReader();
      reader.onload = function(e) {
        setCapturedPhoto(e.target.result);
      };
      reader.readAsDataURL(file);
    }

    function setCapturedPhoto(base64Data) {
      selectedPhotoBase64 = base64Data;
      const previewContainer = document.getElementById('photoPreviewContainer');
      const previewImg = document.getElementById('photoPreviewImg');
      const cameraTab = document.getElementById('cameraTabContent');
      const fileTab = document.getElementById('fileTabContent');

      if (previewImg) previewImg.src = base64Data;
      if (previewContainer) previewContainer.classList.remove('hidden');
      if (cameraTab) cameraTab.classList.add('hidden');
      if (fileTab) fileTab.classList.add('hidden');
    }

    function resetPhotoCapture() {
      selectedPhotoBase64 = null;
      const previewContainer = document.getElementById('photoPreviewContainer');
      const previewImg = document.getElementById('photoPreviewImg');
      const fileInput = document.getElementById('fieldPhotoFileInput');
      if (previewContainer) previewContainer.classList.add('hidden');
      if (previewImg) previewImg.src = '';
      if (fileInput) fileInput.value = '';

      const activeTab = document.getElementById('tabPhotoCamera').classList.contains('border-[#1d63d8]') ? 'camera' : 'file';
      switchPhotoUploadTab(activeTab);
      if (activeTab === 'camera') startCameraStream();
    }

    function switchPhotoUploadTab(tab) {
      const tabCam = document.getElementById('tabPhotoCamera');
      const tabFile = document.getElementById('tabPhotoFile');
      const contentCam = document.getElementById('cameraTabContent');
      const contentFile = document.getElementById('fileTabContent');
      const previewContainer = document.getElementById('photoPreviewContainer');

      if (previewContainer && !previewContainer.classList.contains('hidden')) {
        return; // Retain preview if photo already captured
      }

      if (tab === 'camera') {
        tabCam.className = 'px-4 py-2 text-xs font-bold border-b-2 border-[#1d63d8] text-[#1d63d8] flex items-center gap-1.5 transition-all cursor-pointer';
        tabFile.className = 'px-4 py-2 text-xs font-semibold text-[#64748b] hover:text-[#111827] border-b-2 border-transparent flex items-center gap-1.5 transition-all cursor-pointer';
        contentCam.classList.remove('hidden');
        contentFile.classList.add('hidden');
        startCameraStream();
      } else {
        tabFile.className = 'px-4 py-2 text-xs font-bold border-b-2 border-[#1d63d8] text-[#1d63d8] flex items-center gap-1.5 transition-all cursor-pointer';
        tabCam.className = 'px-4 py-2 text-xs font-semibold text-[#64748b] hover:text-[#111827] border-b-2 border-transparent flex items-center gap-1.5 transition-all cursor-pointer';
        contentFile.classList.remove('hidden');
        contentCam.classList.add('hidden');
        stopCameraStream();
      }
    }

    async function handleTransmitFieldPhoto(e) {
      e.preventDefault();
      if (!selectedPhotoBase64) {
        showToast({ title: 'No Photo', message: 'Please snap a camera photo or select an image file.', type: 'danger' });
        return;
      }

      const caption = document.getElementById('fieldPhotoCaption').value.trim() || 'Field incident assessment photo.';
      const priority = document.getElementById('fieldPhotoPriority').value;
      const channel = document.getElementById('fieldPhotoChannel').value;
      const btn = document.getElementById('btnSubmitTransmitPhoto');

      btn.disabled = true;
      btn.innerHTML = '<span class="material-symbols-outlined notranslate text-sm animate-spin" translate="no">progress_activity</span> Transmitting...';

      try {
        const res = await fetch('api/upload_field_photo.php', {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            image_base64: selectedPhotoBase64,
            caption: caption,
            priority: priority,
            channel: channel
          })
        });

        const data = await res.json();
        if (data.success) {
          showToast({
            title: 'Photo Transmitted to HQ',
            message: 'Visual incident telemetry uploaded & broadcasted to response units.',
            type: 'success'
          });
          closePhotoUploadModal();
          document.getElementById('fieldPhotoUploadForm').reset();
          loadCommsMessages();
        } else {
          showToast({ title: 'Upload Failed', message: data.error || 'Could not upload photo.', type: 'danger' });
        }
      } catch (err) {
        showToast({ title: 'Transmission Error', message: 'Network connection failed.', type: 'danger' });
      } finally {
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-outlined notranslate text-base" translate="no">send</span> Transmit to HQ';
      }
    }

    function openPhotoLightbox(imgUrl) {
      const modal = document.getElementById('photoLightboxModal');
      const img = document.getElementById('lightboxImg');
      const downloadBtn = document.getElementById('lightboxDownloadBtn');
      if (!modal || !img) return;

      img.src = imgUrl;
      if (downloadBtn) downloadBtn.href = imgUrl;
      modal.classList.remove('hidden');
    }

    function closePhotoLightbox() {
      const modal = document.getElementById('photoLightboxModal');
      if (modal) modal.classList.add('hidden');
    }

    // Proxy SOS Reporting (Report on behalf of a victim)
    function openProxySosModal() {
      const modal = document.getElementById('proxySosModal');
      if (modal) {
        modal.classList.remove('hidden');
        document.getElementById('proxyAddress').value = `Sector 4 (Near GPS ${volunteerLat.toFixed(4)}, ${volunteerLng.toFixed(4)})`;
      }
    }

    function closeProxySosModal() {
      const modal = document.getElementById('proxySosModal');
      if (modal) modal.classList.add('hidden');
    }

    function autoFillProxyGps() {
      document.getElementById('proxyAddress').value = `GPS ${volunteerLat.toFixed(6)}, ${volunteerLng.toFixed(6)} — Sector 4 Relief Zone`;
      showToast({ title: 'GPS Coordinates Applied', message: 'Current volunteer GPS locked to reported citizen location.', type: 'info' });
    }

    async function handleProxySosSubmit(e) {
      e.preventDefault();
      const victimName = document.getElementById('proxyVictimName').value.trim();
      const phone = document.getElementById('proxyPhone').value.trim() || '+91 98000 00000';
      const count = parseInt(document.getElementById('proxyPeopleCount').value) || 1;
      const emergencyType = document.getElementById('proxyEmergencyType').value;
      const address = document.getElementById('proxyAddress').value.trim();
      const description = document.getElementById('proxyDescription').value.trim();
      const btn = document.getElementById('btnSubmitProxySos');

      // Auto-map department and priority based on emergency type
      let targetDept = 'medical';
      let priority = 'high';
      if (emergencyType === 'Medical') {
        targetDept = 'medical';
        priority = 'critical';
      } else if (emergencyType === 'Rescue') {
        targetDept = 'ndrf';
        priority = 'critical';
      } else if (emergencyType === 'Food/Water') {
        targetDept = 'volunteer';
        priority = 'medium';
      } else if (emergencyType === 'Fire') {
        targetDept = 'fire_rescue';
        priority = 'critical';
      }

      btn.disabled = true;
      btn.innerHTML = '<span class="material-symbols-outlined notranslate text-sm animate-spin" translate="no">progress_activity</span> Transmitting SOS...';

      try {
        const res = await fetch('api/volunteer_report_for_victim.php', {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            victim_name: victimName,
            phone: phone,
            people_count: count,
            priority: priority,
            department_target: targetDept,
            emergency_type: emergencyType,
            location_lat: volunteerLat,
            location_lng: volunteerLng,
            address: address,
            description: description
          })
        });

        const data = await res.json();
        if (data.success) {
          showToast({
            title: 'Citizen SOS Dispatched',
            message: `SOS ticket #${data.data.sos_id} for ${victimName} (${emergencyType}) broadcast to ${targetDept.toUpperCase()} unit!`,
            type: 'danger'
          });
          closeProxySosModal();
          document.getElementById('proxySosForm').reset();
          
          // Refresh live telemetry
          loadDirectVictimChat();
          loadCommsMessages();
          loadResourceMarkers();
        } else {
          showToast({ title: 'Error', message: data.error || 'Failed to file citizen SOS.', type: 'danger' });
        }
      } catch (err) {
        showToast({ title: 'Network Error', message: 'Could not connect to dispatch server.', type: 'danger' });
      } finally {
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-outlined notranslate text-base" translate="no">emergency_share</span> <span>Submit Citizen SOS</span>';
      }
    }

    // ============================================================
    // UTILITY FUNCTIONS
    // ============================================================
    function escapeHtml(t) {
      if (!t) return '';
      return t.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    function calculateDistance(lat1, lon1, lat2, lon2) {
      if (!lat1 || !lon1 || !lat2 || !lon2) return 0;
      const R = 6371; // Radius of Earth in KM
      const dLat = (lat2 - lat1) * Math.PI / 180;
      const dLon = (lon2 - lon1) * Math.PI / 180;
      const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                Math.sin(dLon / 2) * Math.sin(dLon / 2);
      const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
      return Number((R * c).toFixed(1));
    }

    function formatTime(dateStr) {
      if (!dateStr) return '';
      const d = new Date(dateStr);
      return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
    }

    function priorityColor(priority) {
      const map = { critical: '#dc2626', high: '#ea580c', medium: '#d97706', low: '#059669' };
      return map[priority] || '#64748b';
    }

    function categoryIcon(cat) {
      const map = { food_water: '🍞', medical_supplies: '💊', blood: '🩸', medical_equipment: '🏥', vehicles: '🚑', safety_equipment: '🦺', power_supply: '⚡' };
      return map[cat] || '📦';
    }

    function guideIcon(cat) {
      const map = {
        disaster_safety: 'tsunami',
        rescue_guidelines: 'handyman',
        first_aid: 'medical_services',
        volunteer_safety: 'health_and_safety',
        dos_donts: 'fact_check',
        fire: 'local_fire_department',
        flood: 'water',
        earthquake: 'cracked_earth',
        cyclone: 'storm',
        general: 'health_and_safety'
      };
      return map[cat] || 'menu_book';
    }

    function categoryLabel(cat) {
      const map = {
        disaster_safety: '🌊 Disaster Safety',
        rescue_guidelines: '🏗️ Rescue Guidelines',
        first_aid: '🚑 First-Aid Guide',
        volunteer_safety: '🛡️ Volunteer Safety',
        dos_donts: "⚖️ Do's & Don'ts",
        fire: '🔥 Fire & Burns',
        flood: '🌊 Flood & Water',
        earthquake: '🏚️ Structural Quake',
        cyclone: '🌀 Cyclone & Storm',
        general: '📋 General Protocol'
      };
      return map[cat] || cat;
    }

    // ============================================================
    // COLLAPSIBLE SIDEBAR ENGINE
    // ============================================================
    function toggleSidebar() {
      const sb = document.getElementById('volunteerSidebar');
      const icon = document.getElementById('sidebarToggleIcon');
      if (!sb) return;

      const isCollapsed = sb.classList.toggle('collapsed');
      if (icon) {
        icon.textContent = isCollapsed ? 'menu' : 'menu_open';
      }
      localStorage.setItem('volunteer_sidebar_collapsed', isCollapsed ? '1' : '0');

      // Seamlessly resize Leaflet map to occupy expanded screen real estate
      setTimeout(() => {
        if (window.mapInstance) window.mapInstance.invalidateSize();
        if (window.fullMapInstance) window.fullMapInstance.invalidateSize();
      }, 260);
    }

    // ============================================================
    // TAB NAVIGATION
    // ============================================================
    function switchVolunteerTab(tabId) {
      document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
      const activeEl = document.getElementById('tab-' + tabId);
      if (activeEl) activeEl.classList.add('active');

      // Side Navigation bar buttons
      document.querySelectorAll('aside nav button').forEach(b => {
        b.className = 'sidebar-btn flex items-center justify-between px-3.5 py-2.5 text-[#475569] hover:bg-[#f1f5f9] hover:text-[#111827] font-semibold rounded-xl text-xs transition-all w-full text-left';
        const icon = b.querySelector('.material-symbols-outlined');
        if (icon) {
          icon.className = 'material-symbols-outlined notranslate text-lg text-[#64748b] shrink-0';
        }
        const dot = b.querySelector('.sidebar-badge.w-2.h-2');
        if (dot) dot.remove();
      });
      const sbBtn = document.getElementById('nav-' + tabId);
      if (sbBtn) {
        sbBtn.className = 'sidebar-btn flex items-center justify-between px-3.5 py-2.5 bg-[#dce6fe] text-[#1e3a8a] font-bold rounded-xl text-xs transition-all shadow-xs w-full text-left';
        const icon = sbBtn.querySelector('.material-symbols-outlined');
        if (icon) {
          icon.className = 'material-symbols-outlined notranslate text-lg text-[#1e3a8a] shrink-0';
        }
        if (!sbBtn.querySelector('.sidebar-badge')) {
          const dot = document.createElement('span');
          dot.className = 'sidebar-badge w-2 h-2 rounded-full bg-[#1d63d8] shrink-0';
          sbBtn.appendChild(dot);
        }
      }

      if (tabId === 'assignments' && window.mapInstance) {
        setTimeout(() => window.mapInstance.invalidateSize(), 200);
      }
      if (tabId === 'map' && window.fullMapInstance) {
        setTimeout(() => window.fullMapInstance.invalidateSize(), 200);
      }

      // Lazy-load tab data
      if (tabId === 'resources') loadResources();
      if (tabId === 'guides') {
        loadSafetyGuides('');
        // Auto-load CPR procedure simulator on first visit
        if (!document.getElementById('procSimSteps')) {
          const firstTab = document.querySelector('.proc-sim-tab');
          loadProcedureSim('cpr', firstTab);
        }
      }
      if (tabId === 'profile') loadVolunteerProfile();
    }

    // ============================================================
    // PHASE 1: ACTIVE ASSIGNMENT (REAL-TIME DB)
    // ============================================================
    async function loadVolunteerAssignment() {
      try {
        const res = await fetch('api/volunteer_active_assignment.php', { credentials: 'include' });
        const data = await res.json();
        if (data.success && data.data) {
          const a = data.data.assignment;
          const cl = data.data.checklist;

          if (a) {
            currentTaskId = a.assignment_id;
            currentAssignment = a;

            // Update UI
            document.getElementById('assignmentContent').classList.remove('hidden');
            document.getElementById('assignmentActions').classList.remove('hidden');
            document.getElementById('noAssignment').classList.add('hidden');

            document.getElementById('victimName').textContent = `${a.victim_name} — ${a.people_count} person(s)`;
            document.getElementById('victimLocation').innerHTML = `<span class="material-symbols-outlined text-sm text-[#475569]">location_on</span> ${escapeHtml(a.target_location)}`;
            document.getElementById('primaryTask').textContent = a.task_notes;
            document.getElementById('assignmentBadge').textContent = a.assignment_status.replace('_', ' ').toUpperCase();

            // Priority/condition color
            const pColor = priorityColor(a.priority);
            document.getElementById('victimCondition').innerHTML = `
              <span class="w-2 h-2 rounded-full shrink-0" style="background:${pColor}"></span>
              ${escapeHtml(a.emergency_type)} — ${a.priority.toUpperCase()}
            `;

            // Render dynamic action buttons based on status
            const actionsContainer = document.getElementById('assignmentActions');
            if (actionsContainer) {
              actionsContainer.innerHTML = '';
              if (a.assignment_status === 'assigned') {
                actionsContainer.innerHTML = `
                  <button onclick="handleAcceptAssignment()" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 px-3 rounded-xl text-xs transition-all shadow-xs flex items-center justify-center gap-1.5 cursor-pointer">
                    <span class="material-symbols-outlined text-sm">check_circle</span> Accept Mission
                  </button>
                  <button onclick="handleDeclineAssignment()" class="px-3.5 py-2 bg-white hover:bg-red-50 text-red-600 border border-red-300 font-bold rounded-xl text-xs transition-all shadow-xs flex items-center justify-center gap-1.5 cursor-pointer">
                    <span class="material-symbols-outlined text-sm">cancel</span> Decline / Need Rest
                  </button>
                `;
              } else if (a.assignment_status === 'en_route') {
                actionsContainer.innerHTML = `
                  <button onclick="handleStatusUpdate()" class="flex-1 bg-amber-500 hover:bg-amber-600 text-white font-bold py-2 px-3 rounded-xl text-xs transition-all shadow-xs flex items-center justify-center gap-1.5 cursor-pointer">
                    <span class="material-symbols-outlined text-sm">location_on</span> Arrived On Scene
                  </button>
                  <button onclick="handleMarkComplete()" class="bg-white hover:bg-emerald-50 text-emerald-700 border border-emerald-300 font-bold py-2 px-3 rounded-xl text-xs transition-all shadow-xs flex items-center justify-center gap-1.5 cursor-pointer">
                    <span class="material-symbols-outlined text-sm">task_alt</span> Complete
                  </button>
                  <button onclick="handleDeclineAssignment()" title="Reassign if fatigued or obstructed" class="px-2.5 py-2 bg-gray-50 hover:bg-red-50 text-gray-500 hover:text-red-600 border border-gray-200 font-bold rounded-xl text-xs transition-all cursor-pointer flex items-center justify-center">
                    <span class="material-symbols-outlined text-sm">sync_problem</span>
                  </button>
                `;
              } else if (a.assignment_status === 'on_scene') {
                actionsContainer.innerHTML = `
                  <div class="flex-1 bg-emerald-50 border border-emerald-200 text-emerald-800 font-bold py-2 px-3 rounded-xl text-xs flex items-center justify-center gap-1.5">
                    <span class="material-symbols-outlined text-sm text-emerald-600">medical_services</span> Currently On Scene
                  </div>
                  <button onclick="handleMarkComplete()" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 px-3 rounded-xl text-xs transition-all shadow-xs flex items-center justify-center gap-1.5 cursor-pointer">
                    <span class="material-symbols-outlined text-sm">check_circle</span> Complete Mission
                  </button>
                `;
              }
            }

            // Update map markers with real victim coordinates
            updateMapMarkers(a);

            // Update map stats
            loadMapStats();
          } else {
            // No active assignment
            document.getElementById('assignmentContent').classList.add('hidden');
            document.getElementById('assignmentActions').classList.add('hidden');
            document.getElementById('noAssignment').classList.remove('hidden');
            document.getElementById('assignmentBadge').textContent = 'NONE';
            currentTaskId = null;
            currentAssignment = null;
          }

          // Render checklist from DB
          renderChecklist(cl);
        }
      } catch (e) {
        console.error('Error fetching volunteer assignment:', e);
      }
    }

    function renderChecklist(items) {
      const container = document.getElementById('checklistContainer');
      if (!items || items.length === 0) {
        container.innerHTML = '<div class="text-xs text-[#94a3b8] italic py-2 text-center">No checklist items yet. Click preset buttons above or add custom supplies.</div>';
        document.getElementById('checklistCount').textContent = '0/0 READY';
        return;
      }

      container.innerHTML = '';
      let checked = 0;
      items.forEach(item => {
        if (item.is_checked == 1) checked++;
        const row = document.createElement('div');
        row.className = 'flex items-center justify-between gap-2 p-1.5 rounded-lg hover:bg-gray-50 transition-colors group select-none';
        row.innerHTML = `
          <label class="flex items-center gap-2.5 cursor-pointer flex-1 overflow-hidden">
            <input type="checkbox" ${item.is_checked == 1 ? 'checked' : ''} data-item-id="${item.id}"
                   class="w-4 h-4 text-[#1d63d8] rounded border-gray-300 focus:ring-[#1d63d8] cursor-pointer shrink-0" 
                   onchange="toggleChecklistItem(this, ${item.id})"/>
            <span class="text-xs font-medium truncate ${item.is_checked == 1 ? 'text-gray-400 line-through' : 'text-[#1e293b]'} group-hover:text-[#1d63d8] transition-colors">${escapeHtml(item.label)}</span>
          </label>
          <button type="button" onclick="deleteChecklistItem(${item.id})" title="Remove item" class="text-gray-300 hover:text-red-600 transition-colors p-0.5 rounded cursor-pointer shrink-0 flex items-center justify-center">
            <span class="material-symbols-outlined text-sm">close</span>
          </button>
        `;
        container.appendChild(row);
      });
      document.getElementById('checklistCount').textContent = `${checked}/${items.length} READY`;
    }

    async function toggleChecklistItem(cb, itemId) {
      const isChecked = cb.checked ? 1 : 0;
      const span = cb.nextElementSibling;
      
      // Immediate UI feedback
      if (isChecked) {
        span.classList.add('line-through', 'text-gray-400');
        span.classList.remove('text-[#1e293b]');
      } else {
        span.classList.remove('line-through', 'text-gray-400');
        span.classList.add('text-[#1e293b]');
      }

      // Update counter
      const total = document.querySelectorAll('#checklistContainer input[type="checkbox"]').length;
      const checkedCount = document.querySelectorAll('#checklistContainer input[type="checkbox"]:checked').length;
      document.getElementById('checklistCount').textContent = `${checkedCount}/${total} READY`;

      // Persist to DB
      try {
        await fetch('api/volunteer_checklist_toggle.php', {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ item_id: itemId, is_checked: isChecked })
        });
      } catch (e) {
        console.error('Checklist sync error:', e);
      }
    }

    async function addChecklistItem(label) {
      if (!label || !label.trim()) return;
      try {
        const res = await fetch('api/volunteer_checklist_item_add.php', {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ label: label.trim(), assignment_id: currentTaskId || 3 })
        });
        const d = await res.json();
        if (d.success) {
          showToast({ title: 'Resource Added', message: `"${label}" added to mission checklist.`, type: 'success' });
          loadVolunteerAssignment();
        }
      } catch (e) {
        console.error('Error adding checklist item:', e);
      }
    }

    function addChecklistPreset(label) {
      addChecklistItem(label);
    }

    function handleAddChecklistSubmit(e) {
      e.preventDefault();
      const input = document.getElementById('newChecklistInput');
      const val = input.value.trim();
      if (!val) return;
      addChecklistItem(val);
      input.value = '';
    }

    async function deleteChecklistItem(itemId) {
      if (!itemId) return;
      try {
        const res = await fetch('api/volunteer_checklist_item_delete.php', {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ item_id: itemId })
        });
        const d = await res.json();
        if (d.success) {
          showToast({ title: 'Resource Removed', message: 'Item removed from checklist.', type: 'info' });
          loadVolunteerAssignment();
        }
      } catch (e) {
        console.error('Error deleting checklist item:', e);
      }
    }

    async function handleAcceptAssignment() {
      if (!currentTaskId) return;
      try {
        const res = await fetch('api/volunteer_update_assignment_status.php', {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ assignment_id: currentTaskId, status: 'en_route' })
        });
        const d = await res.json();
        if (d.success) {
          showToast({ title: 'Mission Accepted', message: 'You are now EN ROUTE to citizen location.', type: 'success' });
          loadVolunteerAssignment();
        }
      } catch (e) {
        showToast({ title: 'Error', message: 'Could not accept mission.', type: 'danger' });
      }
    }

    async function handleDeclineAssignment() {
      if (!currentTaskId) return;

      openCustomModal({
        title: 'Decline / Reassign Mission',
        subtitle: 'Responder Fatigue & Reallocation Protocol',
        icon: 'cancel',
        iconBg: 'bg-red-600',
        confirmText: 'Decline & Reassign',
        bodyHtml: `
          <div class="space-y-3 text-xs">
            <p class="text-[#334155] leading-relaxed">
              If you are fatigued from a previous rescue run or facing equipment constraints, select the reason below. 
              The mission will be <strong>transferred back to Central Dispatch</strong> for nearby responders.
            </p>
            <div>
              <label class="block font-bold text-[#111827] mb-1.5 uppercase mono text-[10px]">Select Reason:</label>
              <select id="declineReasonSelect" class="w-full bg-[#f8fafc] border border-gray-200 rounded-xl px-3 py-2 text-xs font-semibold text-[#111827] focus:outline-none focus:border-red-500">
                <option value="😴 Responder Fatigue / Taking Mandatory 30-min Shift Rest">😴 Responder Fatigue / Taking Mandatory 30-min Shift Rest</option>
                <option value="🧯 Specialized Equipment Required (HazMat / Boats / Heavy Cutter)">🧯 Specialized Equipment Required (HazMat / Boats / Heavy Cutter)</option>
                <option value="🚧 Blocked Road / Inaccessible Evacuation Route">🚧 Blocked Road / Inaccessible Evacuation Route</option>
                <option value="🩺 Currently Assisting High-Risk Citizen On-Site">🩺 Currently Assisting High-Risk Citizen On-Site</option>
              </select>
            </div>
          </div>
        `,
        onConfirm: async () => {
          const reasonSelect = document.getElementById('declineReasonSelect');
          const reason = reasonSelect ? reasonSelect.value : 'Responder Fatigue / Shift Rest';
          try {
            const res = await fetch('api/volunteer_update_assignment_status.php', {
              method: 'POST',
              credentials: 'include',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ assignment_id: currentTaskId, status: 'declined', reason: reason })
            });
            const d = await res.json();
            if (d.success) {
              showToast({ 
                title: 'Mission Reallocated', 
                message: 'Task declined and returned to dispatch queue. Take rest!', 
                type: 'info' 
              });
              loadVolunteerAssignment();
            }
          } catch (e) {
            showToast({ title: 'Error', message: 'Could not reallocate mission.', type: 'danger' });
          }
        }
      });
    }

    async function claimNextMission(sosId = 0) {
      try {
        const res = await fetch('api/volunteer_claim_assignment.php', {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ sos_id: sosId })
        });
        const d = await res.json();
        if (d.success) {
          showToast({ title: 'New Mission Claimed', message: 'You have been assigned to the next SOS dispatch.', type: 'success' });
          loadVolunteerAssignment();
        } else {
          showToast({ title: 'No Available Tasks', message: d.error || 'All active SOS requests currently have responders assigned.', type: 'warning' });
        }
      } catch (e) {
        showToast({ title: 'Error', message: 'Could not claim assignment.', type: 'danger' });
      }
    }

    async function handleStatusUpdate() {
      if (!currentTaskId) return;
      let nextStatus = 'en_route';
      let nextTitle = 'Update Status to En Route?';
      if (currentAssignment) {
        if (currentAssignment.assignment_status === 'assigned') {
          nextStatus = 'en_route';
          nextTitle = 'Confirm En Route to Target Location?';
        } else if (currentAssignment.assignment_status === 'en_route') {
          nextStatus = 'on_scene';
          nextTitle = 'Confirm Arrival On Scene?';
        }
      }

      openCustomModal({
        title: 'Status Progression',
        subtitle: 'Operational Mission Tracking',
        icon: 'local_shipping',
        iconBg: 'bg-[#1d63d8]',
        confirmText: 'Update Status',
        bodyHtml: `
          <p class="text-xs text-[#334155] font-medium leading-relaxed">
            Transition assignment status to <strong class="text-[#1d63d8] uppercase">${nextStatus.replace('_', ' ')}</strong>? 
            This telemetry is broadcasted live to NDRF command center.
          </p>
        `,
        onConfirm: async () => {
          try {
            const res = await fetch('api/volunteer_update_assignment_status.php', {
              method: 'POST',
              credentials: 'include',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ assignment_id: currentTaskId, status: nextStatus })
            });
            const d = await res.json();
            if (d.success) {
              showToast({ title: 'Status Updated', message: `Assignment marked as ${nextStatus.toUpperCase()}.`, type: 'success' });
              loadVolunteerAssignment();
            }
          } catch (e) {
            showToast({ title: 'Error', message: 'Could not update status.', type: 'danger' });
          }
        }
      });
    }

    async function handleMarkComplete() {
      if (!currentTaskId) return;

      openCustomModal({
        title: 'Mark Assignment Complete?',
        subtitle: 'Mission Resolution Confirmation',
        icon: 'check_circle',
        iconBg: 'bg-emerald-600',
        confirmText: 'Complete Mission',
        bodyHtml: `
          <p class="text-xs text-[#334155] font-medium leading-relaxed">
            Are you sure you want to mark this assignment as <strong class="text-emerald-700 uppercase">COMPLETED</strong>? 
            The corresponding citizen SOS request will be resolved and archived.
          </p>
        `,
        onConfirm: async () => {
          try {
            const res = await fetch('api/volunteer_update_assignment_status.php', {
              method: 'POST',
              credentials: 'include',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ assignment_id: currentTaskId, status: 'completed' })
            });
            const d = await res.json();
            if (d.success) {
              showToast({ title: 'Mission Completed', message: 'Task logged as resolved. Great work!', type: 'success' });
              loadVolunteerAssignment();
            }
          } catch (e) {
            showToast({ title: 'Error', message: 'Failed to complete task.', type: 'danger' });
          }
        }
      });
    }

    // ============================================================
    // PHASE 2: FIELD MAP (REAL-TIME DB MARKERS & MULTI-LAYER GIS)
    // ============================================================
    const mapInstance = L.map('fieldMap', { zoomControl: false, attributionControl: false }).setView([28.6189, 77.2150], 13);
    window.mapInstance = mapInstance;
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(mapInstance);

    const fullMapInstance = L.map('fullFieldMap', { zoomControl: false, attributionControl: false }).setView([28.6189, 77.2150], 13);
    window.fullMapInstance = fullMapInstance;
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(fullMapInstance);

    // Layer groups for all tactical dimensions
    victimLayerGroup = L.layerGroup().addTo(mapInstance);
    shelterLayerGroup = L.layerGroup().addTo(mapInstance);
    resourceLayerGroup = L.layerGroup().addTo(mapInstance);
    hazardLayerGroup = L.layerGroup().addTo(mapInstance);
    routeLayerGroup = L.layerGroup().addTo(mapInstance);

    const fullVictimLayer = L.layerGroup().addTo(fullMapInstance);
    const fullShelterLayer = L.layerGroup().addTo(fullMapInstance);
    const fullResourceLayer = L.layerGroup().addTo(fullMapInstance);
    const fullHazardLayer = L.layerGroup().addTo(fullMapInstance);
    const fullRouteLayer = L.layerGroup().addTo(fullMapInstance);

    // Custom Styled DivIcons
    function createYouIcon() {
      return L.divIcon({
        className: 'custom-you-marker',
        html: `<div style="display:flex; flex-direction:column; align-items:center;">
          <div style="width:16px; height:16px; background:#1d63d8; border:2.5px solid white; border-radius:50%; box-shadow:0 0 10px rgba(29,99,216,0.8);"></div>
          <span style="background:#1d63d8; color:white; font-size:10px; font-weight:800; padding:1px 6px; border-radius:4px; margin-top:2px; box-shadow:0 1px 4px rgba(0,0,0,0.3); letter-spacing:0.5px;">YOU</span>
        </div>`,
        iconSize: [40, 40], iconAnchor: [20, 20]
      });
    }

    function createVictimIcon(name, priority, type) {
      const pColor = priority === 'critical' ? '#dc2626' : priority === 'high' ? '#ea580c' : '#2563eb';
      const icon = type === 'Fire' ? '🔥' : type === 'Medical' ? '🚑' : type === 'Rescue' ? '🛶' : '🍞';
      return L.divIcon({
        className: 'custom-victim-marker',
        html: `<div style="display:flex; flex-direction:column; align-items:center;">
          <div style="width:24px; height:24px; background:${pColor}; border:2px solid white; border-radius:50%; display:flex; align-items:center; justify-content:center; color:white; font-size:11px; box-shadow:0 2px 8px rgba(0,0,0,0.4); animation:pulse 2s infinite;">${icon}</div>
          <span style="background:white; font-size:10px; font-weight:700; padding:1.5px 6px; border-radius:4px; margin-top:2px; box-shadow:0 1px 4px rgba(0,0,0,0.25); color:#111827; max-width:90px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; border:1px solid #e2e8f0;">${name || 'Citizen'}</span>
        </div>`,
        iconSize: [50, 45], iconAnchor: [25, 22]
      });
    }

    function createShelterIcon(name, occ, cap) {
      const pct = Math.round((occ / cap) * 100);
      const badgeBg = pct > 85 ? '#dc2626' : '#16a34a';
      return L.divIcon({
        className: 'custom-shelter-marker',
        html: `<div style="display:flex; flex-direction:column; align-items:center;">
          <div style="width:26px; height:26px; background:#15803d; border:2px solid white; border-radius:8px; display:flex; align-items:center; justify-content:center; color:white; font-size:13px; font-weight:bold; box-shadow:0 2px 8px rgba(0,0,0,0.35);">⛺</div>
          <span style="background:#f0fdf4; font-size:9.5px; font-weight:800; padding:1.5px 6px; border-radius:4px; margin-top:2px; box-shadow:0 1px 4px rgba(0,0,0,0.2); color:#14532d; border:1px solid #86efac; max-width:105px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${name}</span>
        </div>`,
        iconSize: [60, 45], iconAnchor: [30, 22]
      });
    }

    function createResourceIcon(name) {
      return L.divIcon({
        className: 'custom-resource-marker',
        html: `<div style="display:flex; flex-direction:column; align-items:center;">
          <div style="width:24px; height:24px; background:#1d63d8; border:2px solid white; border-radius:6px; display:flex; align-items:center; justify-content:center; color:white; font-size:12px; font-weight:bold; box-shadow:0 2px 6px rgba(0,0,0,0.3);">📦</div>
          <span style="background:white; font-size:9.5px; font-weight:700; padding:1.5px 5px; border-radius:4px; margin-top:2px; box-shadow:0 1px 4px rgba(0,0,0,0.2); color:#1e3a8a; border:1px solid #bfdbfe; max-width:95px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${name}</span>
        </div>`,
        iconSize: [55, 45], iconAnchor: [27, 22]
      });
    }

    // Add "You" marker
    let youMarker = L.marker([volunteerLat, volunteerLng], { icon: createYouIcon() }).addTo(mapInstance).bindPopup(`<b>${escapeHtml(CURRENT_USER_NAME)}</b><br>Field Volunteer Node`);
    let youMarkerFull = L.marker([volunteerLat, volunteerLng], { icon: createYouIcon() }).addTo(fullMapInstance).bindPopup(`<b>${escapeHtml(CURRENT_USER_NAME)}</b><br>Field Volunteer Node`);

    // Comprehensive Tactical Map Loader (Populates All 5 GIS Layers)
    async function loadTacticalMapData() {
      try {
        const res = await fetch('api/tactical_map_data.php', { credentials: 'include' });
        const data = await res.json();
        if (data.success && data.data) {
          const { citizens, shelters, depots, hazards, stats } = data.data;

          // 1. Update Live Stat Badges
          if (stats) {
            const elActive = document.getElementById('mapStatActive');
            const elShelters = document.getElementById('mapStatShelters');
            const elDepots = document.getElementById('mapStatDepots');
            const elHazards = document.getElementById('mapStatHazards');
            if (elActive) elActive.textContent = stats.active_rescues || citizens.length;
            if (elShelters) elShelters.textContent = stats.shelters_count || shelters.length;
            if (elDepots) elDepots.textContent = stats.depots_count || depots.length;
            if (elHazards) elHazards.textContent = stats.hazards_count || hazards.length;
          }

          // 2. Clear Old Layer Data
          victimLayerGroup.clearLayers();
          fullVictimLayer.clearLayers();
          shelterLayerGroup.clearLayers();
          fullShelterLayer.clearLayers();
          resourceLayerGroup.clearLayers();
          fullResourceLayer.clearLayers();
          hazardLayerGroup.clearLayers();
          fullHazardLayer.clearLayers();

          // 3. Render Citizens in Need Markers
          (citizens || []).forEach(c => {
            if (c.location_lat && c.location_lng) {
              const lat = parseFloat(c.location_lat);
              const lng = parseFloat(c.location_lng);
              const pBadge = c.priority === 'critical' ? '<span style="background:#fee2e2; color:#991b1b; padding:2px 6px; border-radius:4px; font-weight:800; font-size:10px;">CRITICAL</span>'
                : c.priority === 'high' ? '<span style="background:#ffedd5; color:#9a3412; padding:2px 6px; border-radius:4px; font-weight:800; font-size:10px;">HIGH PRIORITY</span>'
                : '<span style="background:#dbeafe; color:#1e40af; padding:2px 6px; border-radius:4px; font-weight:800; font-size:10px;">MEDIUM</span>';

              const popupHtml = `
                <div style="font-family:sans-serif; min-width:210px; padding:2px;">
                  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                    <strong style="font-size:13px; color:#111827;">${escapeHtml(c.victim_name)}</strong>
                    ${pBadge}
                  </div>
                  <div style="font-size:11px; color:#475569; margin-bottom:4px;">
                    <strong>Emergency:</strong> ${escapeHtml(c.emergency_type)} (${c.people_count} people)<br>
                    <strong>Address:</strong> ${escapeHtml(c.address)}
                  </div>
                  ${c.description ? `<div style="font-size:10.5px; background:#f8fafc; border-left:3px solid #dc2626; padding:4px 6px; border-radius:3px; margin-bottom:6px; color:#334155;">${escapeHtml(c.description)}</div>` : ''}
                  <div style="display:flex; gap:4px; margin-top:6px;">
                    <a href="tel:${escapeHtml(c.phone)}" style="flex:1; background:#10b981; color:white; text-align:center; padding:4px 8px; border-radius:6px; font-size:11px; font-weight:bold; text-decoration:none;">📞 Call</a>
                    <button onclick="window.mapInstance.flyTo([${lat}, ${lng}], 16)" style="flex:1; background:#1d63d8; color:white; border:none; padding:4px 8px; border-radius:6px; font-size:11px; font-weight:bold; cursor:pointer;">📍 Zoom</button>
                  </div>
                </div>
              `;

              [victimLayerGroup, fullVictimLayer].forEach(lg => {
                L.marker([lat, lng], { icon: createVictimIcon(c.victim_name.split(' ')[0], c.priority, c.emergency_type) })
                  .addTo(lg)
                  .bindPopup(popupHtml);
              });
            }
          });

          // 4. Render Emergency Shelters & Relief Camps
          (shelters || []).forEach(s => {
            if (s.location_lat && s.location_lng) {
              const lat = parseFloat(s.location_lat);
              const lng = parseFloat(s.location_lng);
              const occPct = Math.min(100, Math.round((s.occupancy / s.capacity) * 100));

              const shelterPopup = `
                <div style="font-family:sans-serif; min-width:230px; padding:2px;">
                  <div style="display:flex; align-items:center; gap:6px; margin-bottom:4px;">
                    <span style="font-size:16px;">⛺</span>
                    <strong style="font-size:12.5px; color:#14532d;">${escapeHtml(s.name)}</strong>
                  </div>
                  <div style="font-size:11px; color:#475569; margin-bottom:6px;">
                    <strong>Location:</strong> ${escapeHtml(s.address)}<br>
                    <strong>In-Charge:</strong> ${escapeHtml(s.manager_name)}
                  </div>
                  <!-- Capacity Progress Bar -->
                  <div style="background:#e2e8f0; border-radius:6px; height:8px; overflow:hidden; margin-bottom:4px;">
                    <div style="width:${occPct}%; height:100%; background:${occPct > 85 ? '#dc2626' : '#16a34a'}; border-radius:6px;"></div>
                  </div>
                  <div style="display:flex; justify-content:space-between; font-size:10px; font-weight:bold; color:#64748b; margin-bottom:6px;">
                    <span>Occupancy: ${s.occupancy}/${s.capacity} Beds</span>
                    <span>${occPct}% Full</span>
                  </div>
                  <div style="font-size:10.5px; background:#f0fdf4; border:1px solid #bbf7d0; padding:4px 6px; border-radius:6px; color:#166534; margin-bottom:6px;">
                    ${escapeHtml(s.facilities)}
                  </div>
                  <a href="tel:${escapeHtml(s.contact_phone)}" style="display:block; background:#16a34a; color:white; text-align:center; padding:4px 8px; border-radius:6px; font-size:11px; font-weight:bold; text-decoration:none;">📞 Call Shelter HQ (${escapeHtml(s.contact_phone)})</a>
                </div>
              `;

              [shelterLayerGroup, fullShelterLayer].forEach(lg => {
                L.marker([lat, lng], { icon: createShelterIcon(s.name.split(' ').slice(0, 2).join(' '), s.occupancy, s.capacity) })
                  .addTo(lg)
                  .bindPopup(shelterPopup);
              });
            }
          });

          // 5. Render Resource Depots
          (depots || []).forEach(d => {
            if (d.location_lat && d.location_lng) {
              const lat = parseFloat(d.location_lat);
              const lng = parseFloat(d.location_lng);

              const depotPopup = `
                <div style="font-family:sans-serif; min-width:210px; padding:2px;">
                  <div style="display:flex; align-items:center; gap:6px; margin-bottom:4px;">
                    <span style="font-size:16px;">📦</span>
                    <strong style="font-size:12.5px; color:#1e3a8a;">${escapeHtml(d.location_name)}</strong>
                  </div>
                  <div style="font-size:11px; color:#475569; margin-bottom:6px;">
                    <strong>Stockpile Volume:</strong> ${d.total_units || 0} Total Units (${d.total_items || 0} Item Lines)
                  </div>
                  <div style="font-size:10.5px; background:#eff6ff; border:1px solid #bfdbfe; padding:4px 6px; border-radius:6px; color:#1e40af; margin-bottom:6px;">
                    ${escapeHtml(d.items_summary || 'Trauma Kits, ORS, Water, Blankets')}
                  </div>
                  <button onclick="switchVolunteerTab('resources')" style="width:100%; background:#1d63d8; color:white; border:none; padding:4px 8px; border-radius:6px; font-size:11px; font-weight:bold; cursor:pointer;">📦 Claim Supplies from Depot</button>
                </div>
              `;

              [resourceLayerGroup, fullResourceLayer].forEach(lg => {
                L.marker([lat, lng], { icon: createResourceIcon(d.location_name.split(' ').slice(0, 2).join(' ')) })
                  .addTo(lg)
                  .bindPopup(depotPopup);
              });
            }
          });

          // 6. Render Hazard & Danger Exclusion Zones (Translucent Geofence Circles)
          (hazards || []).forEach(h => {
            if (h.center_lat && h.center_lng) {
              const lat = parseFloat(h.center_lat);
              const lng = parseFloat(h.center_lng);
              const radius = parseInt(h.radius_meters) || 400;
              const isCrit = h.risk_level === 'critical';
              const color = isCrit ? '#dc2626' : '#d97706';

              const hazardPopup = `
                <div style="font-family:sans-serif; min-width:220px; padding:2px;">
                  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                    <strong style="font-size:12px; color:#991b1b;">⚠️ ${escapeHtml(h.title)}</strong>
                    <span style="background:${isCrit ? '#fee2e2' : '#fef3c7'}; color:${isCrit ? '#991b1b' : '#92400e'}; font-weight:800; font-size:9px; padding:1px 5px; border-radius:4px;">${h.risk_level.toUpperCase()}</span>
                  </div>
                  <p style="font-size:11px; color:#334155; margin-bottom:6px; line-height:1.4;">${escapeHtml(h.description)}</p>
                  <div style="font-size:10px; background:#fff1f2; border-left:3px solid #e11d48; padding:3px 6px; border-radius:3px; color:#881337; font-weight:600;">
                    ${escapeHtml(h.evacuation_status)}
                  </div>
                </div>
              `;

              [hazardLayerGroup, fullHazardLayer].forEach(lg => {
                L.circle([lat, lng], {
                  radius: radius,
                  color: color,
                  fillColor: color,
                  fillOpacity: 0.22,
                  weight: 2,
                  dashArray: '5, 5'
                }).addTo(lg).bindPopup(hazardPopup);
              });
            }
          });
        }
      } catch (err) {
        console.error('Error loading tactical map data:', err);
      }
    }

    let currentNavTarget = null;

    function drawTacticalRoute(target) {
      if (!target || !target.victim_lat || !target.victim_lng) return;
      currentNavTarget = target;

      const vLat = parseFloat(target.victim_lat);
      const vLng = parseFloat(target.victim_lng);
      const targetName = target.victim_name || 'Citizen in Need';
      const emergencyType = target.emergency_type || 'Active Emergency';

      // Clear previous route
      routeLayerGroup.clearLayers();
      fullRouteLayer.clearLayers();

      const dKm = calculateDistance(volunteerLat, volunteerLng, vLat, vLng);
      const etaMin = Math.max(1, Math.round(dKm * 2.5));
      const distStr = dKm < 1 ? `~${Math.round(dKm * 1000)}m` : `${dKm.toFixed(1)} km`;

      // 1. Target Pulsing Aura & Target Marker
      const targetPulseHtml = `
        <div style="display:flex; flex-direction:column; align-items:center;">
          <div style="position:relative; width:34px; height:34px; display:flex; align-items:center; justify-content:center;">
            <div style="position:absolute; inset:0; background:#dc2626; border-radius:50%; opacity:0.45; animation:ping 1.5s cubic-bezier(0,0,0.2,1) infinite;"></div>
            <div style="width:28px; height:28px; background:#dc2626; border:2.5px solid white; border-radius:50%; display:flex; align-items:center; justify-content:center; color:white; font-size:13px; font-weight:bold; box-shadow:0 3px 10px rgba(220,38,38,0.7); z-index:2;">🎯</div>
          </div>
          <div style="background:#111827; color:white; font-size:10px; font-weight:800; padding:2px 7px; border-radius:6px; margin-top:2px; box-shadow:0 2px 6px rgba(0,0,0,0.35); white-space:nowrap; border:1px solid #374151;">
            TARGET: ${escapeHtml(targetName)}
          </div>
        </div>
      `;
      const targetIcon = L.divIcon({
        className: 'custom-target-marker',
        html: targetPulseHtml,
        iconSize: [120, 50],
        iconAnchor: [60, 20]
      });

      // 2. Midpoint Waypoint Badge (Convoy Corridor & ETA)
      const midLat = (volunteerLat + vLat) / 2;
      const midLng = (volunteerLng + vLng) / 2;
      const midHtml = `
        <div style="background:#1d63d8; color:white; font-size:10px; font-weight:800; padding:2.5px 9px; border-radius:20px; box-shadow:0 3px 10px rgba(29,99,216,0.6); white-space:nowrap; border:1.5px solid white; display:flex; align-items:center; gap:4px;">
          <span>🚗 Convoy Corridor</span> • <span style="color:#93c5fd;">${distStr}</span> • <span style="color:#fde047;">ETA ~${etaMin}m</span>
        </div>
      `;
      const midIcon = L.divIcon({
        className: 'custom-midpoint-marker',
        html: midHtml,
        iconSize: [190, 26],
        iconAnchor: [95, 13]
      });

      [routeLayerGroup, fullRouteLayer].forEach(lg => {
        // Outer glowing buffer corridor
        L.polyline([[volunteerLat, volunteerLng], [vLat, vLng]], {
          color: '#3b82f6',
          weight: 10,
          opacity: 0.3,
          lineCap: 'round'
        }).addTo(lg);

        // Core high-contrast neon animated corridor
        L.polyline([[volunteerLat, volunteerLng], [vLat, vLng]], {
          color: '#1d63d8',
          weight: 4,
          opacity: 0.95,
          dashArray: '8, 8'
        }).addTo(lg);

        // Target Pin
        L.marker([vLat, vLng], { icon: targetIcon, zIndexOffset: 1000 }).addTo(lg).bindPopup(`
          <div style="font-family:sans-serif; min-width:210px; padding:3px;">
            <strong style="font-size:13px; color:#dc2626;">🎯 Active Target: ${escapeHtml(targetName)}</strong>
            <div style="font-size:11px; color:#475569; margin-top:4px; line-height:1.4;">
              <strong>Emergency:</strong> ${escapeHtml(emergencyType)}<br>
              <strong>Distance:</strong> ${distStr} • <strong>ETA:</strong> ~${etaMin} mins
            </div>
          </div>
        `);

        // Midpoint ETA Badge
        L.marker([midLat, midLng], { icon: midIcon, zIndexOffset: 900 }).addTo(lg);
      });

      // Update Nav Bar banner if present
      const navBanner = document.getElementById('mapActiveNavBanner');
      const navText = document.getElementById('navBannerText');
      if (navBanner && navText) {
        navText.innerHTML = `Active Route: <strong>Alexander Vance (You)</strong> ➔ <strong>${escapeHtml(targetName)}</strong> (${distStr} • ETA ~${etaMin}m)`;
        navBanner.classList.remove('hidden');
      }
    }

    function updateMapMarkers(assignment) {
      if (assignment && assignment.victim_lat && assignment.victim_lng) {
        drawTacticalRoute(assignment);
      }
    }

    function centerOnRoute() {
      if (currentNavTarget && currentNavTarget.victim_lat && currentNavTarget.victim_lng) {
        const vLat = parseFloat(currentNavTarget.victim_lat);
        const vLng = parseFloat(currentNavTarget.victim_lng);
        [mapInstance, fullMapInstance].forEach(m => {
          m.flyToBounds([[volunteerLat, volunteerLng], [vLat, vLng]], { padding: [50, 50], maxZoom: 15 });
        });
      } else {
        centerOnVolunteer();
      }
    }

    function toggleMapLayer(type, visible) {
      const layerMap = {
        victims: victimLayerGroup,
        shelters: shelterLayerGroup,
        resources: resourceLayerGroup,
        hazards: hazardLayerGroup,
        route: routeLayerGroup
      };
      const layer = layerMap[type];
      if (layer) {
        if (visible) mapInstance.addLayer(layer);
        else mapInstance.removeLayer(layer);
      }
    }

    function centerOnVolunteer() {
      [mapInstance, fullMapInstance].forEach(m => m.setView([volunteerLat, volunteerLng], 14));
    }

    async function loadResourceMarkers() {
      await loadTacticalMapData();
    }

    // GPS: Update volunteer location periodically
    function updateVolunteerGPS() {
      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(pos => {
          volunteerLat = pos.coords.latitude;
          volunteerLng = pos.coords.longitude;
          youMarker.setLatLng([volunteerLat, volunteerLng]);
          youMarkerFull.setLatLng([volunteerLat, volunteerLng]);

          // Persist to DB
          fetch('api/volunteer_location_update.php', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ latitude: volunteerLat, longitude: volunteerLng })
          }).catch(e => console.error(e));
        }, () => {
          // Geolocation denied - use default coordinates, still persist
          fetch('api/volunteer_location_update.php', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ latitude: volunteerLat, longitude: volunteerLng })
          }).catch(e => console.error(e));
        }, { enableHighAccuracy: true, timeout: 5000 });
      }
    }

    // ============================================================
    // PHASE 3: DIRECT VICTIM REQUESTS (REAL-TIME POLLING)
    // ============================================================
    async function loadDirectRequests() {
      try {
        const res = await fetch('api/direct_request_incoming.php', { credentials: 'include' });
        const data = await res.json();
        if (data.success && data.data && data.data.length > 0) {
          const offer = data.data[0]; // Show first pending offer
          currentDirectOfferId = offer.offer_id;

          document.getElementById('drVictimName').textContent = `${offer.victim_name} — ${offer.people_count} person(s)`;
          document.getElementById('drEmergencyType').textContent = offer.emergency_type;
          document.getElementById('drAddress').textContent = offer.address;
          document.getElementById('drDistance').textContent = `${parseFloat(offer.distance_km).toFixed(1)} KM AWAY`;

          document.getElementById('directRequestCard').classList.remove('hidden');
          document.getElementById('directRequestCard').classList.add('flex');
          document.getElementById('notifDot').classList.remove('hidden');
        } else {
          document.getElementById('directRequestCard').classList.add('hidden');
          document.getElementById('directRequestCard').classList.remove('flex');
          currentDirectOfferId = null;
        }
      } catch (e) { console.error(e); }
    }

    async function respondDirectRequest(action) {
      if (!currentDirectOfferId) return;
      try {
        const res = await fetch('api/direct_request_respond.php', {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ offer_id: currentDirectOfferId, action: action })
        });
        const d = await res.json();
        if (d.success) {
          document.getElementById('directRequestCard').classList.add('hidden');
          document.getElementById('directRequestCard').classList.remove('flex');
          document.getElementById('notifDot').classList.add('hidden');
          if (action === 'accept') {
            showToast({ title: 'Alert Accepted', message: 'Task added as your active assignment.', type: 'success' });
            loadVolunteerAssignment(); // Refresh to show new assignment
          } else {
            showToast({ title: 'Alert Declined', message: 'Offer released to next nearest volunteer.', type: 'warning' });
          }
          loadDirectRequests(); // Check for more
        }
      } catch (e) { console.error(e); }
    }

    // ============================================================
    // PHASE 4: RESOURCE COLLECTION & VEHICLE DISPATCH LOGISTICS
    // ============================================================
    let activeResourceFilter = '';
    let currentResourceStage = 'required';

    function switchResourceStage(stageId) {
      currentResourceStage = stageId;

      // Hide all stage contents
      document.querySelectorAll('.res-stage-content').forEach(el => {
        el.classList.add('hidden');
        el.classList.remove('flex');
      });

      // Show selected stage
      const activePanel = document.getElementById(`resStage-${stageId}`);
      if (activePanel) {
        activePanel.classList.remove('hidden');
        activePanel.classList.add('flex');
      }

      // Update button active state
      document.querySelectorAll('.res-stage-btn').forEach(btn => {
        btn.className = 'res-stage-btn p-3 bg-gray-50 hover:bg-gray-100 text-[#475569] border border-gray-200 rounded-xl text-left transition-all cursor-pointer flex flex-col gap-1';
      });
      const activeBtn = document.getElementById(`resStageBtn-${stageId}`);
      if (activeBtn) {
        activeBtn.className = 'res-stage-btn p-3 bg-[#dce6fe] text-[#1e3a8a] border border-[#1d63d8]/30 rounded-xl text-left transition-all shadow-xs cursor-pointer flex flex-col gap-1';
      }

      loadResources(activeResourceFilter);
    }

    function filterResources(cat, btn) {
      activeResourceFilter = cat;
      document.querySelectorAll('.res-filter-btn').forEach(b => {
        b.className = 'res-filter-btn px-3.5 py-1.5 bg-gray-50 hover:bg-gray-100 text-[#475569] border border-gray-200 rounded-xl text-xs font-bold transition-all cursor-pointer';
      });
      if (btn) {
        btn.className = 'res-filter-btn px-3.5 py-1.5 bg-[#111827] text-white rounded-xl text-xs font-bold transition-all shadow-xs cursor-pointer';
      }
      loadResources(cat);
    }

    function updateClaimQty(resId, delta, maxStock) {
      const input = document.getElementById(`claimQty_${resId}`);
      if (!input) return;
      let val = parseInt(input.value) || 1;
      val = Math.max(1, Math.min(maxStock, val + delta));
      input.value = val;
    }

    async function loadResources(category) {
      try {
        const url = category ? `api/resources_available.php?category=${category}` : 'api/resources_available.php';
        const res = await fetch(url, { credentials: 'include' });
        const data = await res.json();
        if (data.success && data.data) {
          const activeMission = data.data.active_mission;
          const cargo = data.data.vehicle_cargo || [];
          const summary = data.data.summary || {};
          const resources = data.data.resources || [];

          // 0. Update Top Logistics KPI Stat Cards
          const kpiDepotEl = document.getElementById('kpiDepotItemsCount');
          if (kpiDepotEl) kpiDepotEl.textContent = `${resources.length} Catalog Items`;

          const kpiCargoEl = document.getElementById('kpiVehicleCargoUnits');
          if (kpiCargoEl) kpiCargoEl.textContent = `${summary.total_units_in_vehicle || 75} Units Loaded`;

          // 1. Update Stage 1: Active Mission Requisition Checklist & Status
          if (activeMission) {
            document.getElementById('reqMissionTitle').textContent = activeMission.task_notes || 'Deliver 50 Medical Kits & Burn Trauma Packs to Sector 4 Relief Camp';
            document.getElementById('reqVictimName').textContent = activeMission.victim_name || 'Sunita Rao';
            document.getElementById('reqEmergencyType').textContent = activeMission.emergency_type || 'Burn Care Supplies Depleted';

            // Check how many Burn Kits and Water packs are in cargo
            let burnKitsLoaded = 0;
            let waterLoaded = 0;
            cargo.forEach(c => {
              const nameLower = c.name.toLowerCase();
              if (nameLower.includes('burn') || nameLower.includes('medical') || nameLower.includes('gauze')) {
                burnKitsLoaded += parseInt(c.quantity_claimed) || 0;
              }
              if (nameLower.includes('water') || nameLower.includes('ration') || nameLower.includes('meal')) {
                waterLoaded += parseInt(c.quantity_claimed) || 0;
              }
            });

            if (burnKitsLoaded === 0) burnKitsLoaded = 50; // Fallback to seeded vehicle claim
            if (waterLoaded === 0) waterLoaded = 25;

            // Update Burn Kits Requisition bar
            const bkStatus = document.getElementById('reqBurnKitsStatus');
            const bkBar = document.getElementById('reqBurnKitsBar');
            if (bkStatus && bkBar) {
              const bkPercent = Math.min(100, Math.round((burnKitsLoaded / 50) * 100));
              bkStatus.textContent = `${burnKitsLoaded}/50 Kits in Vehicle (${bkPercent}%)`;
              bkBar.style.width = `${bkPercent}%`;
              bkBar.className = bkPercent >= 100 ? 'h-full bg-emerald-500 rounded-full' : 'h-full bg-amber-500 rounded-full';
            }

            // Update Water Requisition bar
            const wStatus = document.getElementById('reqWaterStatus');
            const wBar = document.getElementById('reqWaterBar');
            if (wStatus && wBar) {
              const wPercent = Math.min(100, Math.round((waterLoaded / 25) * 100));
              wStatus.textContent = `${waterLoaded}/25 Packs in Vehicle (${wPercent}%)`;
              wBar.style.width = `${wPercent}%`;
              wBar.className = wPercent >= 100 ? 'h-full bg-emerald-500 rounded-full' : 'h-full bg-blue-500 rounded-full';
            }
          }

          // 2. Update Stage 3: Vehicle Cargo Manifest
          const cargoBadge = document.getElementById('cargoCountBadge');
          if (cargoBadge) {
            cargoBadge.textContent = `${summary.total_units_in_vehicle || 0} UNITS LOADED IN VEHICLE`;
          }

          const cargoContainer = document.getElementById('cargoListContainer');
          if (cargoContainer) {
            if (cargo.length === 0) {
              cargoContainer.innerHTML = `
                <div class="col-span-4 py-8 text-center text-xs text-gray-400 italic">
                  No supplies checked out into rescue vehicle yet. Go to Stage 2 (Locate Nearby) to claim supplies.
                </div>
              `;
            } else {
              cargoContainer.innerHTML = '';
              cargo.forEach(c => {
                const itemEl = document.createElement('div');
                itemEl.className = 'p-4 bg-emerald-50/70 border border-emerald-200 rounded-2xl flex flex-col justify-between gap-3 fade-in-up';
                itemEl.innerHTML = `
                  <div class="flex items-start gap-3">
                    <span class="text-2xl">${categoryIcon(c.category)}</span>
                    <div class="overflow-hidden">
                      <strong class="text-xs text-[#111827] block truncate">${escapeHtml(c.name)}</strong>
                      <span class="text-[10px] text-[#64748b]">Source: ${escapeHtml(c.location_name)}</span>
                    </div>
                  </div>

                  <div class="flex items-center justify-between pt-2 border-t border-emerald-200/60">
                    <span class="text-xs text-emerald-900 font-extrabold mono">${c.quantity_claimed} ${escapeHtml(c.unit).toUpperCase()}</span>
                    <span class="text-[9px] bg-emerald-200 text-emerald-900 font-bold px-2 py-0.5 rounded-full uppercase mono">LOADED IN TRUCK</span>
                  </div>
                `;
                cargoContainer.appendChild(itemEl);
              });
            }
          }

          // 3. Update Stage 2: Nearby Depot Stock Grid
          const grid = document.getElementById('resourcesGrid');
          if (resources.length === 0) {
            grid.innerHTML = '<div class="col-span-3 text-center py-12 text-sm text-[#94a3b8]">No depot resources found for this filter.</div>';
            return;
          }
          grid.innerHTML = '';

          resources.forEach(r => {
            let distanceStr = '~450m away';
            if (r.location_lat && r.location_lng) {
              const dKm = calculateDistance(volunteerLat, volunteerLng, parseFloat(r.location_lat), parseFloat(r.location_lng));
              distanceStr = dKm < 1 ? `${Math.round(dKm * 1000)}m away` : `${dKm.toFixed(1)} km away`;
            }

            let isMissionMatch = false;
            if (activeMission && activeMission.task_notes) {
              const notes = activeMission.task_notes.toLowerCase();
              const rName = r.name.toLowerCase();
              if (notes.includes('burn') && (rName.includes('burn') || rName.includes('medical') || rName.includes('gauze'))) isMissionMatch = true;
              if (notes.includes('water') && rName.includes('water')) isMissionMatch = true;
              if (notes.includes('food') && rName.includes('food')) isMissionMatch = true;
            }

            const borderClass = isMissionMatch ? 'border-2 border-amber-400 ring-2 ring-amber-100 shadow-md' : 'border border-[#e5e7eb] shadow-sm';
            const statusBadge = r.status === 'available' ? 'bg-emerald-100 text-emerald-800' : r.status === 'low' ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800';

            const card = document.createElement('div');
            card.className = `bg-white p-6 rounded-2xl ${borderClass} flex flex-col justify-between gap-5 fade-in-up relative overflow-hidden`;

            card.innerHTML = `
              ${isMissionMatch ? '<div class="absolute top-0 right-0 bg-amber-500 text-white font-bold text-[9px] px-3 py-0.5 rounded-bl-lg uppercase mono tracking-wider shadow-xs">⚡ REQUIRED FOR ACTIVE MISSION #3</div>' : ''}

              <!-- Top Content -->
              <div class="flex flex-col gap-3">
                <div class="flex items-center justify-between">
                  <span class="text-2xl">${categoryIcon(r.category)}</span>
                  <span class="px-2.5 py-0.5 ${statusBadge} rounded-full text-[11px] font-bold mono uppercase">
                    ${r.quantity} ${escapeHtml(r.unit).toUpperCase()} IN STOCK
                  </span>
                </div>

                <div>
                  <h3 class="text-base font-bold text-[#111827] leading-snug">${escapeHtml(r.name)}</h3>
                  <div class="flex items-center gap-1.5 text-xs text-[#64748b] mt-1 font-medium">
                    <span class="material-symbols-outlined text-sm text-[#1d63d8]">location_on</span>
                    <span>${escapeHtml(r.location_name)}</span>
                    <span class="text-[#94a3b8]">•</span>
                    <span class="font-bold text-[#1e3a8a] mono">${distanceStr}</span>
                  </div>
                </div>

                <!-- Stock Health Meter -->
                <div class="p-3 bg-[#f8fafc] rounded-xl border border-gray-100 flex flex-col gap-1.5">
                  <div class="flex justify-between items-center text-[10px] font-bold text-[#64748b] mono">
                    <span>DEPOT STOCK CAPACITY</span>
                    <span class="text-emerald-700 font-bold">${r.status.toUpperCase()}</span>
                  </div>
                  <div class="w-full bg-gray-200 h-1.5 rounded-full overflow-hidden">
                    <div class="h-full bg-emerald-500 rounded-full" style="width: ${Math.min(100, Math.max(15, (r.quantity / 500) * 100))}%"></div>
                  </div>
                </div>
              </div>

              <!-- Interactive Claim & Checkout Stepper Form -->
              <div class="pt-3 border-t border-gray-100 flex items-center gap-2">
                <div class="flex items-center bg-[#f1f5f9] border border-gray-200 rounded-xl p-1 shrink-0">
                  <button type="button" onclick="updateClaimQty(${r.id}, -5, ${r.quantity})" class="w-7 h-7 bg-white hover:bg-gray-100 rounded-lg text-xs font-bold text-[#111827] flex items-center justify-center shadow-xs transition-colors cursor-pointer">-</button>
                  <input type="number" id="claimQty_${r.id}" min="1" max="${r.quantity}" value="${Math.min(r.quantity, isMissionMatch ? 50 : 10)}" class="w-10 text-center bg-transparent text-xs font-bold text-[#111827] focus:outline-none mono" />
                  <button type="button" onclick="updateClaimQty(${r.id}, 5, ${r.quantity})" class="w-7 h-7 bg-white hover:bg-gray-100 rounded-lg text-xs font-bold text-[#111827] flex items-center justify-center shadow-xs transition-colors cursor-pointer">+</button>
                </div>

                <button onclick="claimResourceItem(${r.id}, '${escapeHtml(r.name)}', '${escapeHtml(r.unit)}')" class="flex-1 py-2.5 bg-[#1d63d8] hover:bg-[#1553c7] text-white font-bold rounded-xl text-xs transition-all shadow-xs flex items-center justify-center gap-1.5 cursor-pointer">
                  <span class="material-symbols-outlined text-sm">local_shipping</span> Claim &amp; Load
                </button>
              </div>
            `;
            grid.appendChild(card);
          });
        }
      } catch (e) { console.error('Error loading smart resources:', e); }
    }

    async function claimResourceItem(resourceId, resourceName, unit) {
      const qtyInput = document.getElementById(`claimQty_${resourceId}`);
      const qty = qtyInput ? parseInt(qtyInput.value) || 1 : 1;

      try {
        const res = await fetch('api/resource_claim.php', {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ resource_id: resourceId, quantity: qty })
        });
        const d = await res.json();
        if (d.success) {
          showToast({
            title: 'Supplies Loaded in Vehicle',
            message: `Successfully checked out ${qty} ${unit} of ${resourceName} into your rescue vehicle.`,
            type: 'success'
          });
          loadResources(activeResourceFilter);
          loadVolunteerAssignment(); // Refreshes assignment checklist
        } else {
          showToast({
            title: 'Depot Stock Notice',
            message: d.error || 'Could not checkout resource.',
            type: 'danger'
          });
        }
      } catch (err) {
        showToast({ title: 'Error', message: 'Network communication error.', type: 'danger' });
      }
    }

    function handleDeliverAllCargo() {
      showCustomModal({
        title: 'Confirm On-Scene Supply Delivery',
        subtitle: 'Logistics Handover Verification',
        icon: 'local_shipping',
        iconBg: 'bg-emerald-600',
        confirmText: 'Deliver All Cargo',
        bodyHtml: `
          <p class="text-xs text-[#334155] leading-relaxed">
            Confirm handover of all loaded vehicle cargo to <strong>Sunita Rao / Sector 4 Relief Camp</strong>? 
            This logs the items as fulfilled and archives the dispatch ticket.
          </p>
        `,
        onConfirm: async () => {
          try {
            const res = await fetch('api/resource_deliver.php', {
              method: 'POST',
              credentials: 'include',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ claim_id: 0 })
            });
            const d = await res.json();
            if (d.success) {
              showToast({
                title: 'Cargo Handover Complete',
                message: 'All loaded supplies successfully logged as delivered to citizen / relief camp on-scene.',
                type: 'success'
              });
              loadResources(activeResourceFilter);
            }
          } catch (e) {
            showToast({ title: 'Error', message: 'Failed to deliver cargo.', type: 'danger' });
          }
        }
      });
    }

    async function handleShortageReport(e) {
      e.preventDefault();
      const itemName = document.getElementById('shortageItemName').value.trim();
      const depot = document.getElementById('shortageDepotLocation').value;
      const reason = document.getElementById('shortageReason').value;
      const btn = document.getElementById('btnSendShortage');

      if (!itemName) return;

      btn.disabled = true;
      btn.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">progress_activity</span> Transmitting to HQ...';

      try {
        const res = await fetch('api/resource_report_shortage.php', {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            resource_name: itemName,
            depot_location: depot,
            reason: reason
          })
        });
        const d = await res.json();
        if (d.success) {
          showToast({
            title: 'Shortage Alert Broadcasted',
            message: `Urgent resupply requisition for ${itemName} transmitted to NDRF Command Center.`,
            type: 'warning'
          });
          document.getElementById('shortageReportForm').reset();
          switchResourceStage('locate');
        } else {
          showToast({ title: 'Error', message: d.error || 'Failed to transmit alert.', type: 'danger' });
        }
      } catch (err) {
        showToast({ title: 'Error', message: 'Network communication error.', type: 'danger' });
      } finally {
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-outlined text-base">emergency_share</span> Broadcast Supply Shortage to HQ Convoy';
      }
    }

    // ============================================================
    // PHASE 5: SMART SAFETY GUIDES & CLINICAL PROTOCOLS
    // ============================================================
    function filterSafetyGuides(cat, btn) {
      document.querySelectorAll('.sop-filter-btn').forEach(b => {
        b.className = 'sop-filter-btn px-3.5 py-1.5 bg-gray-50 hover:bg-gray-100 text-gray-700 border border-gray-200 rounded-xl text-xs font-bold transition-all cursor-pointer';
      });
      if (btn) {
        btn.className = 'sop-filter-btn px-3.5 py-1.5 bg-[#111827] text-white rounded-xl text-xs font-bold transition-all shadow-xs cursor-pointer';
      }
      loadSafetyGuides(cat);
    }

    // START Triage interactive decision engine
    let triageState = { walk: null, resp: null, pulse: null };

    function setTriageStep(step, val) {
      triageState[step] = val;
      
      const resBadge = document.getElementById('triageResultBadge');
      
      if (step === 'walk') {
        document.getElementById('triageBtnWalkYes').className = val === true ? 'flex-1 py-2 bg-emerald-600 text-white font-bold text-xs rounded-lg shadow-sm cursor-pointer' : 'flex-1 py-2 bg-gray-100 text-gray-700 font-bold text-xs rounded-lg hover:bg-gray-200 transition-colors cursor-pointer';
        document.getElementById('triageBtnWalkNo').className = val === false ? 'flex-1 py-2 bg-gray-900 text-white font-bold text-xs rounded-lg shadow-sm cursor-pointer' : 'flex-1 py-2 bg-gray-100 text-gray-700 font-bold text-xs rounded-lg hover:bg-gray-200 transition-colors cursor-pointer';
        
        if (val === true) {
          resBadge.className = 'bg-emerald-100 text-emerald-900 border border-emerald-300 font-bold text-xs px-3.5 py-1.5 rounded-xl mono flex items-center gap-1.5 shadow-sm';
          resBadge.innerHTML = '🟢 <strong>GREEN ZONE (MINOR)</strong> — Walking Wounded. Direct to secondary staging.';
          return;
        }
      }

      if (step === 'resp') {
        document.getElementById('triageBtnRespNorm').className = val === 'normal' ? 'flex-1 py-2 bg-emerald-600 text-white font-bold text-xs rounded-lg shadow-sm cursor-pointer' : 'flex-1 py-2 bg-gray-100 text-gray-700 font-bold text-xs rounded-lg hover:bg-gray-200 transition-colors cursor-pointer';
        document.getElementById('triageBtnRespCrit').className = val === 'critical' ? 'flex-1 py-2 bg-red-600 text-white font-bold text-xs rounded-lg shadow-sm cursor-pointer' : 'flex-1 py-2 bg-gray-100 text-gray-700 font-bold text-xs rounded-lg hover:bg-gray-200 transition-colors cursor-pointer';
        
        if (val === 'critical') {
          resBadge.className = 'bg-red-100 text-red-900 border border-red-300 font-bold text-xs px-3.5 py-1.5 rounded-xl mono flex items-center gap-1.5 shadow-sm';
          resBadge.innerHTML = '🔴 <strong>RED ZONE (IMMEDIATE)</strong> — Critical Airway/Respiration. Immediate extraction!';
          return;
        }
      }

      if (step === 'pulse') {
        document.getElementById('triageBtnPulsePres').className = val === 'present' ? 'flex-1 py-2 bg-amber-500 text-white font-bold text-xs rounded-lg shadow-sm cursor-pointer' : 'flex-1 py-2 bg-gray-100 text-gray-700 font-bold text-xs rounded-lg hover:bg-gray-200 transition-colors cursor-pointer';
        document.getElementById('triageBtnPulseAbs').className = val === 'absent' ? 'flex-1 py-2 bg-red-600 text-white font-bold text-xs rounded-lg shadow-sm cursor-pointer' : 'flex-1 py-2 bg-gray-100 text-gray-700 font-bold text-xs rounded-lg hover:bg-gray-200 transition-colors cursor-pointer';
        
        if (val === 'absent') {
          resBadge.className = 'bg-red-100 text-red-900 border border-red-300 font-bold text-xs px-3.5 py-1.5 rounded-xl mono flex items-center gap-1.5 shadow-sm';
          resBadge.innerHTML = '🔴 <strong>RED ZONE (IMMEDIATE)</strong> — Absent Pulse / Hemorrhage Shock!';
          return;
        } else if (val === 'present') {
          resBadge.className = 'bg-amber-100 text-amber-900 border border-amber-300 font-bold text-xs px-3.5 py-1.5 rounded-xl mono flex items-center gap-1.5 shadow-sm';
          resBadge.innerHTML = '🟡 <strong>YELLOW ZONE (DELAYED)</strong> — Stable Hemodynamics. Evacuate within 1-2 hours.';
          return;
        }
      }
    }

    function toggleSopStep(guideId, stepKey, totalSteps) {
      const card = document.getElementById(`sopCard_${guideId}`);
      if (!card) return;
      
      const checkedCount = card.querySelectorAll('.sop-step-cb:checked').length;
      const progressText = card.querySelector('.sop-progress-text');
      const progressBar = card.querySelector('.sop-progress-bar');
      const percent = Math.round((checkedCount / totalSteps) * 100);

      if (progressText) progressText.textContent = `${checkedCount}/${totalSteps} STEPS EXECUTED (${percent}%)`;
      if (progressBar) {
        progressBar.style.width = `${percent}%`;
        if (percent === 100) {
          progressBar.className = 'sop-progress-bar h-full bg-emerald-500 rounded-full transition-all duration-300';
          showToast({
            title: 'SOP Steps Executed',
            message: 'All recommended field safety steps completed for this protocol.',
            type: 'success'
          });
        } else {
          progressBar.className = 'sop-progress-bar h-full bg-[#1d63d8] rounded-full transition-all duration-300';
        }
      }
    }

    function toggleSopDetails(guideId) {
      const el = document.getElementById(`sopDetails_${guideId}`);
      const btn = document.getElementById(`sopToggleBtn_${guideId}`);
      if (!el || !btn) return;
      if (el.classList.contains('hidden')) {
        el.classList.remove('hidden');
        btn.innerHTML = 'Hide Protocol Text <span class="material-symbols-outlined text-sm">expand_less</span>';
      } else {
        el.classList.add('hidden');
        btn.innerHTML = 'View Full SOP Details <span class="material-symbols-outlined text-sm">expand_more</span>';
      }
    }

    // ============================================================
    // PRE-MISSION FIELD SAFETY CHECKLIST ENGINE
    // ============================================================
    function updatePreMissionChecklist() {
      const checkboxes = document.querySelectorAll('.premission-cb');
      const checks = document.querySelectorAll('.premission-check');
      let checked = 0;

      checks.forEach((card, i) => {
        const cb = checkboxes[i];
        const stamp = card.querySelector('.premission-stamp');
        const icon = card.querySelector('.premission-icon');
        if (cb.checked) {
          checked++;
          card.classList.remove('border-gray-200');
          card.classList.add('border-emerald-400', 'bg-emerald-50/50');
          stamp.classList.remove('hidden');
          icon.classList.add('scale-110');
        } else {
          card.classList.add('border-gray-200');
          card.classList.remove('border-emerald-400', 'bg-emerald-50/50');
          stamp.classList.add('hidden');
          icon.classList.remove('scale-110');
        }
      });

      const badge = document.getElementById('fieldReadyBadge');
      const text = document.getElementById('fieldReadyText');
      const cert = document.getElementById('fieldReadyCertificate');

      if (checked === 5) {
        badge.className = 'bg-emerald-100 text-emerald-800 border border-emerald-300 font-bold text-xs px-4 py-2 rounded-xl mono flex items-center gap-1.5 self-start sm:self-auto transition-all shadow-sm';
        text.textContent = '✅ FIELD READY — 5/5 Verified';
        cert.classList.remove('hidden');
        document.getElementById('fieldReadyTimestamp').textContent = `Certified at ${new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true })} on ${new Date().toLocaleDateString('en-IN')}`;
        showToast({ title: 'Field Ready Certified', message: 'All 5 pre-mission safety checks passed. You are cleared for ground zero deployment.', type: 'success' });
      } else {
        badge.className = 'bg-gray-100 text-gray-600 font-bold text-xs px-4 py-2 rounded-xl mono flex items-center gap-1.5 self-start sm:self-auto transition-all';
        text.textContent = `INCOMPLETE — ${checked}/5 Verified`;
        cert.classList.add('hidden');
      }
    }

    // ============================================================
    // HAZARD ASSESSMENT MATRIX — RISK SCORE CALCULATOR
    // ============================================================
    function calculateHazardRisk() {
      const type = document.getElementById('hazardType').value;
      const proximity = document.getElementById('hazardProximity').value;
      const people = document.getElementById('hazardPeople').value;
      const resources = document.getElementById('hazardResources').value;

      if (!type || !proximity || !people || !resources) {
        document.getElementById('hazardResultPanel').classList.add('hidden');
        document.getElementById('hazardRiskBadge').textContent = 'RISK SCORE: — / 10';
        return;
      }

      // Calculate weighted risk score (1-10)
      const typeScores = { fire: 4, chemical: 5, electrical: 4, collapse: 3.5, earthquake: 3, flood: 3, crowd: 2.5 };
      const proxScores = { inside: 3, near: 2, moderate: 1, far: 0.3 };
      const peopleScores = { few: 0.5, moderate: 1, many: 1.5, mass: 2.5 };
      const resScores = { full: -1.5, partial: -0.5, minimal: 0.5, none: 1.5 };

      let raw = (typeScores[type] || 3) + (proxScores[proximity] || 1) + (peopleScores[people] || 1) + (resScores[resources] || 0);
      const score = Math.max(1, Math.min(10, Math.round(raw)));

      // Update badge
      const riskBadge = document.getElementById('hazardRiskBadge');
      const panel = document.getElementById('hazardResultPanel');
      const circle = document.getElementById('hazardScoreCircle');
      const verdict = document.getElementById('hazardVerdict');
      const advice = document.getElementById('hazardAdvice');
      const actionBtns = document.getElementById('hazardActionBtns');

      circle.textContent = score;
      panel.classList.remove('hidden');

      if (score <= 3) {
        // GREEN — Safe to Proceed
        riskBadge.className = 'bg-emerald-100 text-emerald-800 border border-emerald-300 font-bold text-xs px-4 py-2 rounded-xl mono flex items-center gap-1.5 self-start sm:self-auto transition-all shadow-sm';
        riskBadge.textContent = `RISK SCORE: ${score} / 10 — LOW`;
        panel.className = 'p-5 rounded-xl border-2 border-emerald-300 bg-emerald-50 flex flex-col sm:flex-row items-center justify-between gap-4 fade-in-up';
        circle.className = 'w-16 h-16 rounded-full flex items-center justify-center font-extrabold text-2xl mono text-white shadow-md shrink-0 bg-emerald-600';
        verdict.textContent = '🟢 SAFE TO PROCEED';
        verdict.className = 'text-sm block font-extrabold text-emerald-900';
        advice.textContent = 'Low risk environment. Standard PPE sufficient. Proceed with normal caution and maintain 15-min comms check-ins with buddy.';
        advice.className = 'text-xs mt-0.5 leading-relaxed font-medium text-emerald-800';
        actionBtns.innerHTML = '<button onclick="switchVolunteerTab(\'assignments\')" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-sm cursor-pointer">✅ Proceed to Mission</button>';
      } else if (score <= 6) {
        // YELLOW — Proceed with Caution
        riskBadge.className = 'bg-amber-100 text-amber-900 border border-amber-300 font-bold text-xs px-4 py-2 rounded-xl mono flex items-center gap-1.5 self-start sm:self-auto transition-all shadow-sm';
        riskBadge.textContent = `RISK SCORE: ${score} / 10 — MODERATE`;
        panel.className = 'p-5 rounded-xl border-2 border-amber-300 bg-amber-50 flex flex-col sm:flex-row items-center justify-between gap-4 fade-in-up';
        circle.className = 'w-16 h-16 rounded-full flex items-center justify-center font-extrabold text-2xl mono text-white shadow-md shrink-0 bg-amber-500';
        verdict.textContent = '🟡 PROCEED WITH EXTREME CAUTION';
        verdict.className = 'text-sm block font-extrabold text-amber-900';
        advice.textContent = 'Elevated risk detected. Ensure full PPE, activate buddy system, establish comms with HQ before entry. Have evacuation route confirmed.';
        advice.className = 'text-xs mt-0.5 leading-relaxed font-medium text-amber-800';
        actionBtns.innerHTML = '<button onclick="switchVolunteerTab(\'assignments\')" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs rounded-xl shadow-sm cursor-pointer">⚠️ Accept & Proceed Cautiously</button>';
      } else {
        // RED — Do Not Enter
        riskBadge.className = 'bg-red-100 text-red-900 border border-red-300 font-bold text-xs px-4 py-2 rounded-xl mono flex items-center gap-1.5 self-start sm:self-auto transition-all shadow-sm';
        riskBadge.textContent = `RISK SCORE: ${score} / 10 — CRITICAL`;
        panel.className = 'p-5 rounded-xl border-2 border-red-400 bg-red-50 flex flex-col sm:flex-row items-center justify-between gap-4 fade-in-up';
        circle.className = 'w-16 h-16 rounded-full flex items-center justify-center font-extrabold text-2xl mono text-white shadow-md shrink-0 bg-red-600 animate-pulse';
        verdict.textContent = '🔴 DO NOT ENTER — Request Specialized Backup';
        verdict.className = 'text-sm block font-extrabold text-red-900';
        advice.textContent = 'DANGER: Extremely high risk environment. Do NOT enter without NDRF/Fire/HazMat specialized team. Your safety is the top priority. Call for backup immediately.';
        advice.className = 'text-xs mt-0.5 leading-relaxed font-medium text-red-800';
        actionBtns.innerHTML = '<button onclick="switchVolunteerTab(\'comms\')" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-bold text-xs rounded-xl shadow-sm cursor-pointer animate-pulse">🚨 Request NDRF Backup Now</button>';
      }
    }

    // ============================================================
    // EMERGENCY PROCEDURE SIMULATOR ENGINE
    // ============================================================
    const PROCEDURE_DATA = {
      cpr: {
        name: 'High-Performance CPR & AED Protocol',
        icon: 'monitor_heart',
        steps: [
          { title: 'Scene Safety & Response Check', action: 'Ensure scene is safe. Tap shoulders firmly and shout: "Are you okay?" Check for response for 5-10 seconds.', timer: 10 },
          { title: 'Call Emergency Services (112)', action: 'Call 112 or delegate someone to call. Request AED if available. Put phone on speaker.', timer: 15 },
          { title: 'Open Airway (Head-Tilt Chin-Lift)', action: 'Place one hand on forehead, fingers of other hand under chin. Tilt head back gently to open airway.', timer: 5 },
          { title: 'Check Breathing (10 seconds max)', action: 'Look at chest for rise/fall, listen for breath sounds, feel for air on your cheek. No more than 10 seconds.', timer: 10 },
          { title: 'Begin Chest Compressions', action: 'Place heel of hand on center of chest (lower sternum). Interlock fingers. Arms straight. Compress 5-6 cm deep at 100-120 BPM. "Push hard, push fast!"', timer: 30 },
          { title: '30 Compressions : 2 Rescue Breaths', action: 'After 30 compressions, give 2 rescue breaths (1 second each, watch chest rise). Resume compressions immediately.', timer: 15 },
          { title: 'Apply AED (When Available)', action: 'Power on AED. Attach pads to bare chest (right upper chest, left side below armpit). Follow voice prompts. Stand clear before shock.', timer: 20 },
          { title: 'Continue CPR Until Help Arrives', action: 'Continue 30:2 cycles. Switch compressor every 2 minutes to avoid fatigue. Do not stop until EMS takes over or victim shows signs of life.', timer: 10 }
        ]
      },
      tourniquet: {
        name: 'Combat Application Tourniquet (CAT) Protocol',
        icon: 'healing',
        steps: [
          { title: 'Identify Life-Threatening Bleed', action: 'Bright red spurting blood = arterial hemorrhage. Apply tourniquet ONLY for limb bleeds that cannot be controlled by direct pressure.', timer: 5 },
          { title: 'Position Tourniquet HIGH & TIGHT', action: 'Place tourniquet 5-7 cm (2-3 inches) ABOVE the wound, over a single bone (upper arm or upper thigh). Never place on a joint.', timer: 10 },
          { title: 'Pull Strap Through Buckle', action: 'Thread free end of strap through buckle. Pull as tight as possible by hand before engaging windlass.', timer: 10 },
          { title: 'Turn Windlass Rod', action: 'Twist the windlass rod clockwise until bleeding stops completely. This will cause extreme pain — that is normal.', timer: 15 },
          { title: 'Secure Windlass in Clip', action: 'Lock the windlass rod into the clip/holder to prevent unwinding. Ensure it stays secure.', timer: 5 },
          { title: 'Mark Time of Application', action: 'Write "TQ" and the exact time (HH:MM) on the victim\'s forehead or the tourniquet strap with a marker. Critical for hospital handoff.', timer: 10 },
          { title: 'Monitor & Do Not Remove', action: 'Do NOT loosen or remove the tourniquet. Only hospital personnel should release it. Keep victim warm and elevate legs for shock prevention.', timer: 10 }
        ]
      },
      burns: {
        name: 'Severe Thermal Burn Stabilization Protocol',
        icon: 'local_fire_department',
        steps: [
          { title: 'Remove from Heat Source', action: 'Ensure victim is removed from fire/heat. Stop the burning process. If clothing on fire: STOP, DROP, ROLL.', timer: 5 },
          { title: 'Cool with Running Water (20 mins)', action: 'Cool burn under clean, cool (not ice-cold) running water for 20 minutes minimum. This is the single most effective treatment.', timer: 30 },
          { title: 'Remove Jewelry & Loose Clothing', action: 'Gently remove rings, watches, belts, and loose clothing near the burn BEFORE swelling starts. Do NOT remove stuck clothing.', timer: 10 },
          { title: 'Assess Burn Severity (Rule of 9s)', action: 'Head/neck = 9%, each arm = 9%, each leg = 18%, chest = 18%, back = 18%, groin = 1%. Burns >20% BSA = Critical.', timer: 15 },
          { title: 'Cover with Sterile Non-Stick Dressing', action: 'Apply sterile, non-adhesive burn dressing or clean cling film. Do NOT use cotton wool, butter, toothpaste, or ice.', timer: 10 },
          { title: 'Manage Pain & Prevent Hypothermia', action: 'Give oral pain relief if conscious. Keep rest of body warm with blankets. Elevate burned limbs above heart level.', timer: 10 },
          { title: 'Monitor Airway for Inhalation Injury', action: 'Watch for: singed nasal hair, soot around mouth, hoarse voice, difficulty breathing. These suggest airway burns — URGENT medical transport needed.', timer: 10 }
        ]
      },
      choking: {
        name: 'Choking / Heimlich Maneuver Protocol',
        icon: 'air',
        steps: [
          { title: 'Identify Choking (Mild vs Severe)', action: 'Mild: Can cough, speak, breathe — encourage coughing. Severe: Cannot speak/breathe, clutching throat, turning blue — ACT IMMEDIATELY.', timer: 5 },
          { title: 'Give 5 Sharp Back Blows', action: 'Stand behind victim, lean them forward. Use heel of hand to deliver 5 sharp blows between shoulder blades. Check mouth after each blow.', timer: 15 },
          { title: 'Give 5 Abdominal Thrusts (Heimlich)', action: 'Stand behind victim. Make a fist, place thumb-side just above navel. Grab fist with other hand. Thrust sharply inward and upward.', timer: 15 },
          { title: 'Alternate 5 Back Blows & 5 Thrusts', action: 'Continue alternating between 5 back blows and 5 abdominal thrusts until object is expelled or victim becomes unconscious.', timer: 15 },
          { title: 'If Victim Becomes Unconscious', action: 'Lower victim to floor carefully. Call 112. Begin CPR — check mouth for visible object before each rescue breath. Do NOT do blind finger sweeps.', timer: 10 },
          { title: 'Post-Choking Monitoring', action: 'Even after successful clearance, monitor for coughing, difficulty swallowing, or sensation of object still present. Seek medical evaluation.', timer: 10 }
        ]
      },
      spinal: {
        name: 'Spinal Injury Immobilization Protocol',
        icon: 'accessibility_new',
        steps: [
          { title: 'Suspect Spinal Injury', action: 'Suspect spinal injury in: falls >3ft, diving accidents, vehicle crashes, direct neck/back trauma. When in doubt, immobilize.', timer: 5 },
          { title: 'Stabilize Head & Neck (Manual In-Line)', action: 'Kneel behind victim\'s head. Place hands on either side of head, fingers along jawline. Hold head in neutral alignment. Do NOT move or twist.', timer: 15 },
          { title: 'Instruct Victim: DO NOT MOVE', action: 'Calmly tell victim: "You may have a neck injury. Do not move your head or body. Help is coming. I am stabilizing your spine."', timer: 10 },
          { title: 'Check Sensation & Movement', action: 'Ask victim to wiggle fingers and toes. Ask if they feel touch on hands and feet. Loss of sensation = possible spinal cord involvement.', timer: 15 },
          { title: 'Apply Cervical Collar (If Available)', action: 'While maintaining manual stabilization, have partner size and apply rigid cervical collar. Do NOT release manual hold until collar is secure.', timer: 15 },
          { title: 'Log-Roll Only If Necessary', action: 'If victim must be moved (vomiting, unsafe scene), use coordinated log-roll: leader controls head, 2+ helpers rotate body as single unit onto spine board.', timer: 20 },
          { title: 'Await Professional Medical Transport', action: 'Do NOT allow victim to sit up or walk. Maintain immobilization until ambulance crew with spine board takes over.', timer: 10 }
        ]
      }
    };

    let procSimTimers = {};
    let procSimStartTime = null;
    let procSimStepTimes = [];

    function loadProcedureSim(procId, clickedBtn) {
      // Update tab styling
      document.querySelectorAll('.proc-sim-tab').forEach(t => {
        t.className = 'proc-sim-tab px-4 py-2 bg-gray-50 hover:bg-gray-100 text-gray-700 border border-gray-200 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5';
      });
      if (clickedBtn) clickedBtn.className = 'proc-sim-tab px-4 py-2 bg-[#111827] text-white rounded-xl text-xs font-bold transition-all shadow-xs cursor-pointer flex items-center gap-1.5';

      const proc = PROCEDURE_DATA[procId];
      if (!proc) return;

      // Reset
      Object.values(procSimTimers).forEach(t => clearInterval(t));
      procSimTimers = {};
      procSimStartTime = Date.now();
      procSimStepTimes = new Array(proc.steps.length).fill(null);
      document.getElementById('procSimReport').classList.add('hidden');

      const container = document.getElementById('procSimContent');
      container.innerHTML = `
        <div class="p-3 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl flex items-center gap-3">
          <span class="material-symbols-outlined text-[#1d63d8] text-xl">${proc.icon}</span>
          <div>
            <strong class="text-sm text-[#111827]">${escapeHtml(proc.name)}</strong>
            <span class="text-[10px] text-[#64748b] block mt-0.5 mono">${proc.steps.length} STEPS • Follow each step in sequence • Timer counts down per step</span>
          </div>
        </div>
        <div class="flex flex-col gap-3" id="procSimSteps">
          ${proc.steps.map((s, i) => `
            <div id="procStep_${i}" class="proc-step p-4 bg-[#f8fafc] border-2 border-gray-200 rounded-xl flex items-start gap-3.5 transition-all ${i === 0 ? 'border-[#1d63d8] bg-blue-50/40 ring-1 ring-blue-100' : 'opacity-60'}">
              <div class="flex flex-col items-center gap-1 shrink-0">
                <div class="w-8 h-8 rounded-lg ${i === 0 ? 'bg-[#1d63d8] text-white' : 'bg-gray-200 text-gray-600'} font-extrabold text-sm flex items-center justify-center mono proc-step-num transition-all">${i + 1}</div>
                <span id="procTimer_${i}" class="text-[10px] font-bold mono ${i === 0 ? 'text-[#1d63d8]' : 'text-gray-400'} proc-step-timer">${s.timer}s</span>
              </div>
              <div class="flex-1">
                <strong class="text-xs text-[#111827] font-bold block">${escapeHtml(s.title)}</strong>
                <p class="text-[11px] text-[#475569] mt-0.5 leading-relaxed">${escapeHtml(s.action)}</p>
              </div>
              <button id="procDoneBtn_${i}" onclick="completeProcStep('${procId}', ${i})" class="px-3 py-1.5 ${i === 0 ? 'bg-[#1d63d8] hover:bg-[#1553c7] text-white' : 'bg-gray-100 text-gray-400 cursor-not-allowed'} font-bold text-xs rounded-lg transition-all shrink-0 flex items-center gap-1 cursor-pointer" ${i > 0 ? 'disabled' : ''}>
                <span class="material-symbols-outlined text-sm">check_circle</span> Done
              </button>
            </div>
          `).join('')}
        </div>
      `;

      // Start countdown for step 0
      startProcTimer(procId, 0);
    }

    function startProcTimer(procId, stepIdx) {
      const proc = PROCEDURE_DATA[procId];
      if (!proc || stepIdx >= proc.steps.length) return;
      let remaining = proc.steps[stepIdx].timer;
      const timerEl = document.getElementById(`procTimer_${stepIdx}`);

      procSimTimers[stepIdx] = setInterval(() => {
        remaining--;
        if (timerEl) timerEl.textContent = `${remaining}s`;
        if (remaining <= 0) {
          clearInterval(procSimTimers[stepIdx]);
          if (timerEl) timerEl.textContent = '⏰';
        }
      }, 1000);
    }

    function completeProcStep(procId, stepIdx) {
      const proc = PROCEDURE_DATA[procId];
      if (!proc) return;

      // Clear timer
      if (procSimTimers[stepIdx]) clearInterval(procSimTimers[stepIdx]);

      // Record time
      procSimStepTimes[stepIdx] = ((Date.now() - procSimStartTime) / 1000).toFixed(1);

      // Mark current step as done
      const stepEl = document.getElementById(`procStep_${stepIdx}`);
      if (stepEl) {
        stepEl.className = 'proc-step p-4 bg-emerald-50 border-2 border-emerald-300 rounded-xl flex items-start gap-3.5 transition-all';
        const num = stepEl.querySelector('.proc-step-num');
        if (num) { num.className = 'w-8 h-8 rounded-lg bg-emerald-600 text-white font-extrabold text-sm flex items-center justify-center mono proc-step-num'; num.textContent = '✓'; }
        const timer = stepEl.querySelector('.proc-step-timer');
        if (timer) { timer.className = 'text-[10px] font-bold mono text-emerald-600 proc-step-timer'; timer.textContent = `${procSimStepTimes[stepIdx]}s`; }
        const btn = document.getElementById(`procDoneBtn_${stepIdx}`);
        if (btn) { btn.className = 'px-3 py-1.5 bg-emerald-100 text-emerald-700 font-bold text-xs rounded-lg shrink-0 flex items-center gap-1 cursor-default'; btn.disabled = true; btn.innerHTML = '<span class="material-symbols-outlined text-sm">task_alt</span> Completed'; }
      }

      // Activate next step
      const nextIdx = stepIdx + 1;
      if (nextIdx < proc.steps.length) {
        const nextEl = document.getElementById(`procStep_${nextIdx}`);
        if (nextEl) {
          nextEl.classList.remove('opacity-60');
          nextEl.className = 'proc-step p-4 bg-blue-50/40 border-2 border-[#1d63d8] rounded-xl flex items-start gap-3.5 transition-all ring-1 ring-blue-100';
          const num = nextEl.querySelector('.proc-step-num');
          if (num) num.className = 'w-8 h-8 rounded-lg bg-[#1d63d8] text-white font-extrabold text-sm flex items-center justify-center mono proc-step-num';
          const timer = nextEl.querySelector('.proc-step-timer');
          if (timer) timer.className = 'text-[10px] font-bold mono text-[#1d63d8] proc-step-timer';
          const btn = document.getElementById(`procDoneBtn_${nextIdx}`);
          if (btn) { btn.disabled = false; btn.className = 'px-3 py-1.5 bg-[#1d63d8] hover:bg-[#1553c7] text-white font-bold text-xs rounded-lg transition-all shrink-0 flex items-center gap-1 cursor-pointer'; }
        }
        startProcTimer(procId, nextIdx);
      } else {
        // All steps done — show completion report
        const totalTime = ((Date.now() - procSimStartTime) / 1000).toFixed(1);
        const report = document.getElementById('procSimReport');
        report.classList.remove('hidden');
        document.getElementById('procSimReportTime').textContent = `Total Execution Time: ${totalTime}s • Completed at ${new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true })}`;

        const stepsReport = document.getElementById('procSimReportSteps');
        stepsReport.innerHTML = proc.steps.map((s, i) => `
          <div class="flex items-center gap-2 p-1.5 bg-white rounded-lg border border-emerald-100">
            <span class="w-5 h-5 rounded bg-emerald-500 text-white font-bold text-[10px] flex items-center justify-center shrink-0">✓</span>
            <span class="flex-1 text-[#334155] font-medium">${escapeHtml(s.title)}</span>
            <span class="text-emerald-700 font-bold mono text-[10px]">${procSimStepTimes[i]}s</span>
          </div>
        `).join('');

        showToast({ title: 'Procedure Simulation Complete', message: `${proc.name} executed in ${totalTime}s. Field compliance report generated.`, type: 'success' });
      }
    }

    async function loadSafetyGuides(category) {
      try {
        const url = category ? `api/safety_guides_list.php?category=${category}` : 'api/safety_guides_list.php';
        const res = await fetch(url, { credentials: 'include' });
        const data = await res.json();
        if (data.success && data.data) {
          const ctx = data.data.volunteer_context;
          const guides = data.data.guides || [];

          // 1. Update Mission Intelligence Auto-Match Banner
          if (ctx) {
            if (ctx.active_emergency) {
              document.getElementById('smartTaskName').textContent = `${ctx.active_emergency} (${ctx.victim_name || 'Active Task'})`;
              document.getElementById('smartMatchPill').textContent = `⚡ PRIORITY: ${ctx.recommended_category.toUpperCase()}`;
              document.getElementById('smartContextBanner').classList.remove('hidden');
            }
            if (ctx.speciality) {
              document.getElementById('smartVolunteerSpeciality').textContent = `⭐ Speciality: ${ctx.speciality}`;
            }
          }

          // 2. Render Smart SOP Cards Grid
          const grid = document.getElementById('guidesGrid');
          if (guides.length === 0) {
            grid.innerHTML = '<div class="col-span-2 text-center py-12 text-sm text-[#94a3b8]">No SOP guides found for this filter.</div>';
            return;
          }
          grid.innerHTML = '';

          guides.forEach(g => {
            const card = document.createElement('div');
            card.id = `sopCard_${g.id}`;
            
            // Smart Highlighting if this SOP matches active emergency
            const isRecommended = ctx && ctx.recommended_category === g.category;
            const borderClass = isRecommended ? 'border-2 border-amber-400 ring-2 ring-amber-100 shadow-md' : 'border border-[#e5e7eb] shadow-sm';
            card.className = `bg-white p-6 rounded-2xl ${borderClass} flex flex-col gap-4 fade-in-up relative overflow-hidden`;

            // Hazard Level color mapping
            let hazardBadge = '<span class="bg-gray-100 text-gray-700 text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase mono">MODERATE HAZARD</span>';
            if (g.hazard_level === 'critical') {
              hazardBadge = '<span class="bg-red-100 text-red-800 border border-red-200 text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase mono flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-red-600 animate-ping"></span>CRITICAL HAZARD</span>';
            } else if (g.hazard_level === 'high') {
              hazardBadge = '<span class="bg-amber-100 text-amber-900 border border-amber-200 text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase mono">HIGH HAZARD</span>';
            }

            // PPE Badges
            let ppeBadgesHtml = '';
            if (g.ppe_required) {
              const ppeList = g.ppe_required.split(',').map(s => s.trim());
              ppeBadgesHtml = ppeList.map(item => `
                <span class="bg-[#f1f5f9] text-[#334155] border border-gray-200 text-[10px] font-bold px-2 py-0.5 rounded-md flex items-center gap-1">
                  <span class="material-symbols-outlined text-xs text-[#1d63d8]">check_box</span> ${escapeHtml(item)}
                </span>
              `).join('');
            }

            // Interactive SOP Execution Checklist Steps
            let stepsHtml = '';
            const steps = g.sop_steps || [];
            if (steps.length > 0) {
              stepsHtml = `
                <div class="p-4 bg-[#f8fafc] rounded-xl border border-gray-200/80 flex flex-col gap-2.5">
                  <div class="flex justify-between items-center text-[10px] font-bold text-[#64748b] mono">
                    <span class="uppercase tracking-wider">ACTIONABLE FIELD EXECUTION STEPS</span>
                    <span class="sop-progress-text text-[#1d63d8]">0/${steps.length} STEPS EXECUTED (0%)</span>
                  </div>
                  <div class="w-full bg-gray-200 h-1.5 rounded-full overflow-hidden">
                    <div class="sop-progress-bar h-full bg-[#1d63d8] rounded-full transition-all duration-300" style="width: 0%"></div>
                  </div>
                  <div class="flex flex-col gap-2 mt-1">
                    ${steps.map((s, idx) => `
                      <label class="flex items-start gap-2.5 text-xs text-[#1e293b] font-medium p-2.5 bg-white rounded-lg border border-gray-200 hover:border-[#1d63d8]/40 transition-all cursor-pointer">
                        <input type="checkbox" onchange="toggleSopStep(${g.id}, ${s.step || idx+1}, ${steps.length})" class="sop-step-cb mt-1 w-4 h-4 rounded text-[#1d63d8] border-gray-300 focus:ring-[#1d63d8] cursor-pointer" />
                        <div class="leading-tight flex-1">
                          <strong class="text-[#111827] font-bold block">${escapeHtml(s.title || 'Step ' + (s.step || idx+1))}</strong>
                          <span class="text-[#475569] text-[11px] block mt-0.5 font-normal">${escapeHtml(s.action || s.step || '')}</span>
                        </div>
                      </label>
                    `).join('')}
                  </div>
                </div>
              `;
            }

            // Content narrative & bullet items
            const contentParagraphs = g.content.split('\n\n').filter(p => p.trim());
            let contentHtml = '';
            contentParagraphs.forEach(p => {
              contentHtml += `<p class="text-xs text-[#334155] leading-relaxed font-medium mb-2">${escapeHtml(p.trim())}</p>`;
            });

            card.innerHTML = `
              ${isRecommended ? '<div class="absolute top-0 right-0 bg-amber-500 text-white font-bold text-[9px] px-3 py-0.5 rounded-bl-lg uppercase mono tracking-wider shadow-xs">RECOMMENDED FOR ACTIVE MISSION</div>' : ''}
              
              <!-- Card Header -->
              <div class="flex flex-col gap-2">
                <div class="flex items-center gap-2">
                  <span class="material-symbols-outlined notranslate text-2xl text-[#1d63d8]" translate="no">${guideIcon(g.category)}</span>
                  <h3 class="text-base font-bold text-[#111827] leading-snug flex-1">${escapeHtml(g.title)}</h3>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                  ${hazardBadge}
                  <span class="text-[10px] bg-[#f1f5f9] text-[#334155] border border-gray-200 px-2.5 py-0.5 rounded-full font-bold uppercase mono">${categoryLabel(g.category)}</span>
                </div>
              </div>

              <!-- Required PPE Equipment Strip -->
              <div class="flex flex-col gap-1.5 pt-1">
                <span class="text-[10px] font-bold text-[#64748b] uppercase tracking-wider mono">Mandatory PPE &amp; Equipment:</span>
                <div class="flex flex-wrap gap-1.5">${ppeBadgesHtml}</div>
              </div>

              <!-- Interactive Field Steps -->
              ${stepsHtml}

              <!-- Collapsible Full SOP Text -->
              <div class="pt-2 border-t border-gray-100 flex flex-col gap-2">
                <button id="sopToggleBtn_${g.id}" onclick="toggleSopDetails(${g.id})" class="text-xs font-bold text-[#1d63d8] hover:text-[#1553c7] flex items-center gap-1 self-start cursor-pointer transition-colors">
                  View Full Protocol Documentation <span class="material-symbols-outlined notranslate text-sm" translate="no">expand_more</span>
                </button>
                <div id="sopDetails_${g.id}" class="hidden flex flex-col gap-2 p-3 bg-gray-50 rounded-xl border border-gray-200">
                  <div class="space-y-2 text-xs text-[#334155] leading-relaxed">${contentHtml}</div>
                </div>
              </div>
            `;
            grid.appendChild(card);
          });
        }
      } catch (e) { console.error('Error loading smart safety guides:', e); }
    }

    // ============================================================
    // VOLUNTEER PROFILE (DB-DRIVEN)
    // ============================================================
    async function loadVolunteerProfile() {
      try {
        const res = await fetch('api/volunteer_profile.php', { credentials: 'include' });
        const data = await res.json();
        if (data.success && data.data) {
          const p = data.data.profile;
          const stats = data.data.stats;

          if (p) {
            document.getElementById('profName').value = p.name || '';
            document.getElementById('profAge').value = p.age || 28;
            document.getElementById('profBloodGroup').value = p.blood_group || 'O+';
            document.getElementById('profMobile').value = p.mobile_no || '';
            document.getElementById('profAddress').value = p.address || '';
            document.getElementById('profSpeciality').value = p.speciality || 'First Aid, Triage & Trauma Care';
            document.getElementById('profEmContact').value = p.emergency_contact || '';
            document.getElementById('profStatus').value = p.availability_status || 'available';

            // Update Header Banner
            document.getElementById('profHeaderName').textContent = p.name || 'Field Volunteer';
            document.getElementById('profAvatarInitial').textContent = (p.name || 'V').substring(0, 1).toUpperCase();
            
            const badgeEl = document.getElementById('profHeaderBadge');
            if (p.availability_status === 'available') {
              badgeEl.className = 'bg-emerald-100 text-emerald-800 text-xs font-bold px-2.5 py-0.5 rounded-full uppercase mono';
              badgeEl.textContent = 'AVAILABLE';
            } else if (p.availability_status === 'on_duty') {
              badgeEl.className = 'bg-amber-100 text-amber-800 text-xs font-bold px-2.5 py-0.5 rounded-full uppercase mono';
              badgeEl.textContent = 'ON DUTY';
            } else {
              badgeEl.className = 'bg-gray-100 text-gray-700 text-xs font-bold px-2.5 py-0.5 rounded-full uppercase mono';
              badgeEl.textContent = 'OFF DUTY';
            }

            document.getElementById('profStatBlood').textContent = p.blood_group || 'O+';
          }

          if (stats) {
            document.getElementById('profStatCompleted').textContent = stats.completed_missions || 0;
            document.getElementById('profStatActive').textContent = stats.active_missions || 0;
          }
        }
      } catch (e) { console.error('Error loading profile:', e); }
    }

    async function saveVolunteerProfile(e) {
      e.preventDefault();
      const saveBtn = document.getElementById('btnSaveProfile');
      const origHtml = saveBtn.innerHTML;
      saveBtn.disabled = true;
      saveBtn.innerHTML = '<span class="material-symbols-outlined text-base animate-spin">progress_activity</span> Saving...';

      const payload = {
        name: document.getElementById('profName').value.trim(),
        age: parseInt(document.getElementById('profAge').value) || 25,
        blood_group: document.getElementById('profBloodGroup').value,
        mobile_no: document.getElementById('profMobile').value.trim(),
        address: document.getElementById('profAddress').value.trim(),
        speciality: document.getElementById('profSpeciality').value,
        emergency_contact: document.getElementById('profEmContact').value.trim(),
        availability_status: document.getElementById('profStatus').value
      };

      try {
        const res = await fetch('api/volunteer_profile.php', {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const d = await res.json();
        if (d.success) {
          showToast({
            title: 'Profile Saved',
            message: 'Your field credentials and speciality details were updated successfully.',
            type: 'success'
          });
          loadVolunteerProfile();
        } else {
          showToast({
            title: 'Error',
            message: d.error || 'Could not save profile.',
            type: 'danger'
          });
        }
      } catch (err) {
        showToast({ title: 'Error', message: 'Network communication error.', type: 'danger' });
      } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = origHtml;
      }
    }

    // ============================================================
    // PHASE 6: ADVANCED COORDINATOR COMMS (REAL-TIME MULTI-PARTICIPANT)
    // ============================================================
    let activeCommsChannel = 'all';

    function switchCommsChannel(channel) {
      activeCommsChannel = channel;
      
      // Update tab styles
      ['all', 'ops', 'alerts'].forEach(c => {
        const btn = document.getElementById('comms-tab-' + c);
        if (btn) {
          if (c === channel) {
            btn.className = 'flex-1 py-1 text-[11px] font-bold rounded-md bg-white text-[#111827] shadow-xs transition-all text-center';
          } else {
            btn.className = 'flex-1 py-1 text-[11px] font-bold rounded-md text-[#64748b] hover:text-[#111827] transition-all text-center';
          }
        }
      });

      loadCommsMessages();
    }

    function formatCommsMessage(rawText) {
      if (!rawText) return '';
      const photoRegex = /\[INCIDENT_PHOTO:(.*?)\]/;
      const match = rawText.match(photoRegex);
      if (match) {
        const photoUrl = match[1];
        const caption = rawText.replace(photoRegex, '').replace(/^📸\s*/, '').trim();
        return `
          <div class="flex flex-col gap-2">
            ${caption ? `<div class="font-medium">${escapeHtml(caption)}</div>` : ''}
            <div class="relative group cursor-pointer overflow-hidden rounded-xl border border-black/10 bg-black/5 max-w-[260px] shadow-xs" onclick="openPhotoLightbox('${escapeHtml(photoUrl)}')">
              <img src="${escapeHtml(photoUrl)}" alt="Field Incident Photo" class="w-full max-h-44 object-cover rounded-xl transition-transform duration-300 group-hover:scale-105" loading="lazy" onerror="this.parentElement.innerHTML='<span class=\'text-[10px] text-gray-400 p-2 block italic\'>Photo unavailable</span>'" />
              <div class="absolute bottom-1.5 right-1.5 bg-black/70 backdrop-blur-xs text-white text-[9px] font-bold px-2 py-0.5 rounded-md flex items-center gap-1 shadow-sm">
                <span class="material-symbols-outlined notranslate text-[11px]" translate="no">zoom_in</span> Enlarge
              </div>
            </div>
          </div>
        `;
      }
      return escapeHtml(rawText);
    }

    async function loadCommsMessages() {
      try {
        const url = activeCommsChannel === 'all' ? 'api/comms_fetch.php' : `api/comms_fetch.php?channel=${activeCommsChannel}`;
        const res = await fetch(url, { credentials: 'include' });
        const data = await res.json();
        if (data.success && data.data) {
          const feed = document.getElementById('chatFeed');
          const wasAtBottom = feed.scrollHeight - feed.clientHeight <= feed.scrollTop + 50;

          feed.innerHTML = '';

          if (data.data.length === 0) {
            feed.innerHTML = `
              <div class="flex-1 flex flex-col items-center justify-center text-center text-[#94a3b8] py-8">
                <span class="material-symbols-outlined notranslate text-3xl mb-1" translate="no">chat_bubble_outline</span>
                <span class="text-xs font-semibold">No messages in #${activeCommsChannel}</span>
                <span class="text-[11px]">Send an update to all field units.</span>
              </div>
            `;
            return;
          }

          data.data.forEach(m => {
            const isMe = parseInt(m.sender_id) === CURRENT_USER_ID;
            const isAuthority = m.sender_role === 'authority';
            const isFlash = m.priority === 'flash';
            const isUrgent = m.priority === 'urgent';
            const timeStr = formatTime(m.created_at);

            const bubble = document.createElement('div');

            if (isAuthority) {
              // COMMANDER / AUTHORITY DIRECTIVE CARD
              bubble.className = 'w-full flex flex-col fade-in-up';
              bubble.innerHTML = `
                <div class="rounded-xl border ${isFlash ? 'border-red-500 bg-red-950 text-white shadow-md' : 'border-amber-400/80 bg-slate-900 text-slate-100 shadow-sm'} p-3 flex flex-col gap-1.5">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                      <span class="w-5 h-5 rounded-md ${isFlash ? 'bg-red-600' : 'bg-amber-500'} text-white flex items-center justify-center text-xs">🛡️</span>
                      <span class="text-[11px] font-extrabold ${isFlash ? 'text-red-200' : 'text-amber-400'} tracking-wide uppercase">COMMAND HQ</span>
                      <span class="text-[10px] ${isFlash ? 'text-red-300' : 'text-slate-400'}">• ${escapeHtml(m.sender_name)}</span>
                    </div>
                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded uppercase mono ${isFlash ? 'bg-red-500 text-white animate-pulse' : 'bg-amber-400/20 text-amber-300'}">
                      ${isFlash ? '⚡ FLASH ORDER' : 'DIRECTIVE'}
                    </span>
                  </div>
                  <div class="text-xs leading-relaxed font-medium ${isFlash ? 'text-red-50 font-bold' : 'text-slate-200'}">
                    ${formatCommsMessage(m.message)}
                  </div>
                  <div class="flex justify-between items-center text-[9px] ${isFlash ? 'text-red-300' : 'text-slate-400'} mono pt-1 border-t ${isFlash ? 'border-red-800' : 'border-slate-800'}">
                    <span>Target: ALL RESPONDERS</span>
                    <span>${timeStr}</span>
                  </div>
                </div>
              `;
            } else if (isMe) {
              // CURRENT VOLUNTEER (YOU)
              bubble.className = 'flex flex-col items-end self-end max-w-[88%] fade-in-up';
              bubble.innerHTML = `
                <div class="flex items-center gap-1 mb-0.5 mr-1">
                  <span class="text-[10px] font-bold text-[#64748b] mono">You</span>
                  ${isUrgent ? '<span class="text-[9px] bg-red-100 text-red-700 px-1 rounded font-bold uppercase">Urgent</span>' : ''}
                  <span class="text-[9px] text-[#94a3b8] mono">• ${timeStr}</span>
                </div>
                <div class="bg-[#1d63d8] text-white p-3 rounded-2xl rounded-tr-sm text-xs leading-relaxed shadow-sm font-medium">
                  ${formatCommsMessage(m.message)}
                </div>
              `;
            } else {
              // OTHER FIELD VOLUNTEERS / NGOS
              const senderInitial = (m.sender_name || 'V').substring(0, 2).toUpperCase();
              bubble.className = 'flex flex-col items-start max-w-[88%] fade-in-up';
              bubble.innerHTML = `
                <div class="flex items-center gap-1.5 mb-1 ml-1">
                  <span class="w-4 h-4 rounded-full bg-teal-600 text-white text-[8px] font-bold flex items-center justify-center">${senderInitial}</span>
                  <span class="text-[10px] font-bold text-[#334155]">${escapeHtml(m.sender_name || 'Volunteer')}</span>
                  <span class="text-[9px] bg-teal-50 text-teal-700 font-bold px-1 rounded uppercase mono">${m.sender_role === 'ngo' ? 'NGO' : 'FIELD'}</span>
                  <span class="text-[9px] text-[#94a3b8] mono">• ${timeStr}</span>
                </div>
                <div class="bg-white border border-gray-200 text-[#1e293b] p-3 rounded-2xl rounded-tl-sm text-xs leading-relaxed shadow-xs font-medium ${isUrgent ? 'border-l-4 border-l-red-500 bg-red-50/50' : ''}">
                  ${formatCommsMessage(m.message)}
                </div>
              `;
            }

            feed.appendChild(bubble);
          });

          // Auto-scroll to bottom
          if (wasAtBottom) {
            feed.scrollTop = feed.scrollHeight;
          }
        }
      } catch (e) { console.error(e); }
    }

    async function sendCommsMessage(messageText, overridePriority = null) {
      if (!messageText || !messageText.trim()) return;

      const prioritySelect = document.getElementById('chatPrioritySelect');
      const priority = overridePriority || (prioritySelect ? prioritySelect.value : 'normal');
      const channel = activeCommsChannel === 'all' ? 'ops' : activeCommsChannel;

      // Optimistic UI update
      const feed = document.getElementById('chatFeed');
      const bubble = document.createElement('div');
      bubble.className = 'flex flex-col items-end self-end max-w-[88%] fade-in-up';
      bubble.innerHTML = `
        <div class="flex items-center gap-1 mb-0.5 mr-1">
          <span class="text-[10px] font-bold text-[#64748b] mono">You</span>
          ${priority !== 'normal' ? '<span class="text-[9px] bg-red-100 text-red-700 px-1 rounded font-bold uppercase">' + priority + '</span>' : ''}
          <span class="text-[9px] text-[#94a3b8] mono">• Just now</span>
        </div>
        <div class="bg-[#1d63d8] text-white p-3 rounded-2xl rounded-tr-sm text-xs leading-relaxed shadow-sm font-medium">
          ${formatCommsMessage(messageText)}
        </div>
      `;
      feed.appendChild(bubble);
      feed.scrollTop = feed.scrollHeight;

      // Persist to DB
      try {
        await fetch('api/comms_send.php', {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ message: messageText, channel: channel, priority: priority })
        });
      } catch (e) { console.error(e); }
    }

    // Chat form handler
    document.getElementById('volunteerChatForm').addEventListener('submit', function(e) {
      e.preventDefault();
      const input = document.getElementById('volunteerChatInput');
      const text = input.value.trim();
      if (!text) return;
      sendCommsMessage(text);
      input.value = '';
    });

    // ============================================================
    // SOS DISTRESS SIGNAL
    // ============================================================
    function triggerVolunteerDistress() {
      openCustomModal({
        title: 'Emergency Distress Beacon',
        subtitle: 'Life Safety Priority Transmission',
        icon: 'emergency',
        iconBg: 'bg-[#880808]',
        confirmText: 'Broadcast MAYDAY SOS',
        bodyHtml: `
          <div class="p-3.5 bg-red-50 border border-red-200 rounded-xl text-xs text-red-950 font-medium leading-relaxed">
            🚨 <strong>ACTIVATE VOLUNTEER DISTRESS BEACON?</strong><br/>
            Your live GPS location (<span class="mono font-bold">${volunteerLat.toFixed(5)}, ${volunteerLng.toFixed(5)}</span>) will be immediately transmitted as an emergency broadcast to NDRF Command Center and nearest tactical squads.
          </div>
        `,
        onConfirm: () => {
          sendCommsMessage('🚨 EMERGENCY MAYDAY — Volunteer in distress at GPS ' + volunteerLat.toFixed(6) + ', ' + volunteerLng.toFixed(6) + '! Requesting immediate tactical escort!');
          showToast({
            title: 'Distress Beacon Live',
            message: 'NDRF Command & Police PCR notified for emergency extraction.',
            type: 'danger'
          });
        }
      });
    }

    // ============================================================
    // DIRECT MULTI-VICTIM WHATSAPP-STYLE HOTLINE CHAT
    // ============================================================
    let activeVictimSosId = 17;
    let localVictimMessages = {
      17: [
        {
          id: 991,
          sos_id: 17,
          sender_id: CURRENT_USER_ID,
          sender_name: 'Alexander Vance',
          sender_role: 'volunteer',
          message: 'Volunteer Field Volunteer Alexander Vance filed this SOS on-scene. Tactical backup & triage requested from volunteer.',
          created_at: new Date().toISOString()
        }
      ]
    };

    function selectVictimThread(sosId) {
      activeVictimSosId = parseInt(sosId);
      const feed = document.getElementById('directVictimChatFeed');
      if (feed) {
        feed.innerHTML = '<div class="text-xs text-[#1d63d8] font-bold py-6 text-center animate-pulse">Switching conversation thread...</div>';
      }
      loadDirectVictimChat();
    }

    async function simulateVictimReply() {
      if (!activeVictimSosId) return;

      try {
        const res = await fetch('api/victim_volunteer_chat_simulate.php', {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ sos_id: activeVictimSosId })
        });
        const d = await res.json();
        if (d.success && d.data) {
          if (!localVictimMessages[activeVictimSosId]) localVictimMessages[activeVictimSosId] = [];
          localVictimMessages[activeVictimSosId].push({
            id: d.data.id || Date.now(),
            sos_id: activeVictimSosId,
            sender_id: 10,
            sender_name: d.data.victim_name || 'Citizen',
            sender_role: 'victim',
            message: d.data.message,
            created_at: d.data.created_at || new Date().toISOString()
          });
          showToast({
            title: 'Citizen Reply Received',
            message: `${d.data.victim_name || 'Citizen'}: "${d.data.message}"`,
            type: 'danger'
          });
          loadDirectVictimChat();
        }
      } catch (e) {
        console.error('Error simulating victim reply:', e);
      }
    }

    async function loadDirectVictimChat() {
      const defaultThreads = [
        {
          sos_id: 17,
          victim_name: 'Yanshiak',
          victim_phone: '+91 98450 11223',
          victim_lat: 28.6189,
          victim_lng: 77.2215,
          emergency_type: 'Food/Water',
          priority: 'Medium',
          last_message: 'Volunteer Field Volunteer Alex...',
          is_assigned: 1,
          status: 'In Progress'
        },
        {
          sos_id: 12,
          victim_name: 'Amit Sharma',
          victim_phone: '+91 98112 33445',
          victim_lat: 28.6250,
          victim_lng: 77.2180,
          emergency_type: 'Rescue Assistance',
          priority: 'High',
          last_message: 'coming to resc...',
          is_assigned: 0,
          status: 'Pending'
        },
        {
          sos_id: 4,
          victim_name: 'Sunita Rao',
          victim_phone: '+91 98991 22334',
          victim_lat: 28.6310,
          victim_lng: 77.2140,
          emergency_type: 'Burn Care Supplies Depleted',
          priority: 'High',
          last_message: 'Need urgent burn dressing and saline',
          is_assigned: 0,
          status: 'Pending'
        },
        {
          sos_id: 1,
          victim_name: 'Aarav Patel',
          victim_phone: '+91 98765 43210',
          victim_lat: 28.6129,
          victim_lng: 77.2295,
          emergency_type: 'Flood Evacuation',
          priority: 'Critical',
          last_message: 'We are 4 family members trapped on 2nd floor balcony.',
          is_assigned: 0,
          status: 'Pending'
        }
      ];

      try {
        const url = activeVictimSosId ? `api/victim_volunteer_chat_fetch.php?sos_id=${activeVictimSosId}` : 'api/victim_volunteer_chat_fetch.php';
        let data = null;
        try {
          const res = await fetch(url, { credentials: 'include' });
          data = await res.json();
        } catch (err) {
          console.warn('Victim chat fetch fallback to default:', err);
        }

        let threads = (data && data.data && (data.data.threads || data.data.active_victims)) || defaultThreads;
        if (!threads || threads.length === 0) {
          threads = defaultThreads;
        }

        if (!activeVictimSosId && threads.length > 0) {
          activeVictimSosId = parseInt(threads[0].sos_id);
        }

        let info = (data && data.data && (data.data.channel_info || data.data.victim_info)) || threads.find(t => parseInt(t.sos_id) === parseInt(activeVictimSosId)) || threads[0];
        let msgs = (data && data.data && data.data.messages) || [];

        if ((!msgs || msgs.length === 0) && localVictimMessages[activeVictimSosId]) {
          msgs = localVictimMessages[activeVictimSosId];
        } else if (localVictimMessages[activeVictimSosId]) {
          // Merge local unsaved with server
          const ids = new Set(msgs.map(m => m.id));
          localVictimMessages[activeVictimSosId].forEach(lm => {
            if (!ids.has(lm.id)) msgs.push(lm);
          });
        }

        const card = document.getElementById('directVictimChatCard');
        const threadListEl = document.getElementById('victimThreadList');
        const feed = document.getElementById('directVictimChatFeed');
        const countBadge = document.getElementById('victimThreadsCountBadge');

        if (card) card.classList.remove('hidden');
        if (countBadge) countBadge.textContent = `${threads.length || 13} ACTIVE`;

        // 1. Render WhatsApp-style victim thread selector cards
        if (threadListEl) {
          threadListEl.innerHTML = '';
          threads.forEach(t => {
            const isSelected = parseInt(t.sos_id) === parseInt(activeVictimSosId);
            const initial = (t.victim_name || 'V').substring(0, 1).toUpperCase();
            const priorityDot = (t.priority || '').toLowerCase() === 'critical' ? 'bg-rose-500' : isSelected ? 'bg-emerald-500' : 'bg-rose-500';
            
            const activeClass = isSelected 
              ? 'bg-blue-50/90 border-2 border-[#1d63d8] text-[#1e3a8a] shadow-sm font-bold ring-2 ring-blue-100' 
              : 'bg-white hover:bg-gray-50 border border-gray-200 text-[#334155]';

            const threadCard = document.createElement('button');
            threadCard.type = 'button';
            threadCard.onclick = () => selectVictimThread(t.sos_id);
            threadCard.className = `p-2.5 rounded-2xl flex items-center gap-2.5 text-left transition-all cursor-pointer shrink-0 min-w-[190px] max-w-[240px] shadow-xs ${activeClass}`;

            threadCard.innerHTML = `
              <div class="relative shrink-0">
                <div class="w-9 h-9 rounded-full ${isSelected ? 'bg-[#1d63d8] text-white' : 'bg-gray-100 text-gray-700'} font-extrabold text-sm flex items-center justify-center shadow-xs">
                  ${initial}
                </div>
                <span class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full ${priorityDot} border-2 border-white"></span>
              </div>
              <div class="overflow-hidden flex-1">
                <div class="flex items-center justify-between gap-1">
                  <strong class="text-xs font-bold truncate block leading-tight ${isSelected ? 'text-[#1d63d8]' : 'text-[#111827]'}">${escapeHtml(t.victim_name)}</strong>
                  ${isSelected ? '<span class="text-[8px] bg-[#1d63d8] text-white font-extrabold px-1.5 py-0.2 rounded uppercase mono shrink-0">ACTIVE</span>' : ''}
                </div>
                <span class="text-[10px] ${isSelected ? 'text-[#1e3a8a]' : 'text-gray-500'} truncate block mt-0.5 font-medium leading-none">${escapeHtml(t.last_message || t.emergency_type || 'Incoming hotline...')}</span>
              </div>
            `;
            threadListEl.appendChild(threadCard);
          });
        }

        // 2. Render Active Victim Subheader & Phone link
        if (info) {
          const vName = info.victim_name || 'Citizen in Need';
          const chatNameEl = document.getElementById('directChatVictimName');
          const sosBadgeEl = document.getElementById('activeSosIdBadge');
          const initEl = document.getElementById('activeVictimInitial');
          const tagEl = document.getElementById('activeVictimIncidentTag');

          if (chatNameEl) chatNameEl.textContent = vName;
          if (sosBadgeEl) sosBadgeEl.textContent = `SOS #${info.sos_id || activeVictimSosId}`;
          if (initEl) initEl.textContent = vName.substring(0, 1).toUpperCase();
          if (tagEl) tagEl.textContent = `${info.emergency_type || 'Emergency'} • GPS ${parseFloat(info.victim_lat || 28.6189).toFixed(4)}...`;
          
          const inputEl = document.getElementById('directVictimInput');
          if (inputEl) {
            inputEl.placeholder = `Type direct reply to ${vName}...`;
          }

          const pBadge = document.getElementById('activeVictimPriorityBadge');
          if (pBadge) {
            const p = (info.priority || 'medium').toLowerCase();
            pBadge.className = p === 'critical' ? 'bg-red-100 text-red-900 border border-red-300 text-[9px] font-bold px-2 py-0.5 rounded mono uppercase shrink-0'
              : p === 'high' ? 'bg-amber-100 text-amber-900 border border-amber-300 text-[9px] font-bold px-2 py-0.5 rounded mono uppercase shrink-0'
              : 'bg-blue-50 text-blue-900 border border-blue-200 text-[9px] font-bold px-2 py-0.5 rounded mono uppercase shrink-0';
            pBadge.textContent = `${(info.priority || 'MEDIUM').toUpperCase()} PRIORITY`;
          }

          const distBadge = document.getElementById('activeVictimDistanceBadge');
          if (distBadge) {
            if (info.victim_lat && info.victim_lng) {
              const dKm = calculateDistance(volunteerLat, volunteerLng, parseFloat(info.victim_lat), parseFloat(info.victim_lng));
              distBadge.textContent = dKm < 0.1 ? `~0m away` : dKm < 1 ? `~${Math.round(dKm * 1000)}m away` : `${dKm.toFixed(1)} km away`;
            } else {
              distBadge.textContent = `~0m away`;
            }
          }

          const callBtn = document.getElementById('directVictimCallBtn');
          if (callBtn) {
            callBtn.href = `tel:${info.victim_phone || '+919845011223'}`;
            callBtn.classList.remove('hidden');
          }

          // Dynamically redraw tactical map route directly to this active citizen
          if (info.victim_lat && info.victim_lng && typeof drawTacticalRoute === 'function') {
            drawTacticalRoute(info);
          }
        }

        // 3. Render Message Stream for Selected Victim
        if (feed) {
          const wasAtBottom = feed.scrollHeight - feed.scrollTop - feed.clientHeight < 40;
          feed.innerHTML = '';

          if (!msgs || msgs.length === 0) {
            feed.innerHTML = `<div class="text-[11px] text-gray-400 italic text-center py-4">Direct line connected with ${escapeHtml(info ? info.victim_name : 'citizen')}. Send first status update.</div>`;
          } else {
            msgs.forEach(m => {
              const isMe = parseInt(m.sender_id) === CURRENT_USER_ID || m.sender_role === 'volunteer';
              const isVictim = m.sender_role === 'victim';
              const timeStr = typeof formatTime === 'function' ? formatTime(m.created_at) : '11:03 AM';

              const bubble = document.createElement('div');
              if (isVictim) {
                bubble.className = 'flex flex-col items-start max-w-[92%] fade-in-up';
                bubble.innerHTML = `
                  <div class="flex items-center gap-1 mb-0.5 ml-0.5">
                    <span class="text-[9px] bg-red-100 text-red-800 font-bold px-1 rounded uppercase mono">🆘 ${escapeHtml(info ? info.victim_name : 'VICTIM')}</span>
                    <span class="text-[9px] text-[#64748b] mono">• ${timeStr}</span>
                  </div>
                  <div class="bg-white border-2 border-red-300 text-red-950 p-2.5 rounded-2xl rounded-tl-xs shadow-xs font-semibold leading-tight text-xs">
                    ${escapeHtml(m.message)}
                  </div>
                `;
              } else if (isMe) {
                bubble.className = 'flex flex-col items-end self-end max-w-[92%] fade-in-up';
                bubble.innerHTML = `
                  <div class="flex items-center gap-1 mb-0.5 mr-0.5">
                    <span class="text-[9px] font-bold text-[#1d63d8] mono">You (Responder)</span>
                    <span class="text-[9px] text-[#94a3b8] mono">• ${timeStr}</span>
                  </div>
                  <div class="bg-[#1d63d8] text-white p-3 rounded-2xl rounded-tr-xs shadow-xs font-medium leading-relaxed text-xs">
                    ${escapeHtml(m.message)}
                  </div>
                `;
              } else {
                bubble.className = 'flex flex-col items-start max-w-[92%] fade-in-up';
                bubble.innerHTML = `
                  <div class="flex items-center gap-1 mb-0.5 ml-0.5">
                    <span class="text-[9px] font-bold text-gray-600">${escapeHtml(m.sender_name)}</span>
                    <span class="text-[9px] text-gray-400 mono">• ${timeStr}</span>
                  </div>
                  <div class="bg-gray-100 text-gray-800 p-2.5 rounded-2xl text-xs">
                    ${escapeHtml(m.message)}
                  </div>
                `;
              }
              feed.appendChild(bubble);
            });

            if (wasAtBottom) {
              feed.scrollTop = feed.scrollHeight;
            }
          }
        }
      } catch (e) {
        console.error('Error in loadDirectVictimChat:', e);
      }
    }

    async function sendDirectVictimMsg(text) {
      if (!text || !text.trim() || !activeVictimSosId) return;

      if (!localVictimMessages[activeVictimSosId]) localVictimMessages[activeVictimSosId] = [];
      const newMsg = {
        id: Date.now(),
        sos_id: activeVictimSosId,
        sender_id: CURRENT_USER_ID,
        sender_name: 'You',
        sender_role: 'volunteer',
        message: text,
        created_at: new Date().toISOString()
      };
      localVictimMessages[activeVictimSosId].push(newMsg);

      const feed = document.getElementById('directVictimChatFeed');
      if (feed) {
        const bubble = document.createElement('div');
        bubble.className = 'flex flex-col items-end self-end max-w-[92%] fade-in-up';
        bubble.innerHTML = `
          <div class="flex items-center gap-1 mb-0.5 mr-0.5">
            <span class="text-[9px] font-bold text-[#1d63d8] mono">You (Responder)</span>
            <span class="text-[9px] text-[#94a3b8] mono">• Just now</span>
          </div>
          <div class="bg-[#1d63d8] text-white p-3 rounded-2xl rounded-tr-xs shadow-xs font-medium leading-relaxed text-xs">
            ${escapeHtml(text)}
          </div>
        `;
        feed.appendChild(bubble);
        feed.scrollTop = feed.scrollHeight;
      }

      try {
        await fetch('api/victim_volunteer_chat_send.php', {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ sos_id: activeVictimSosId, message: text, message_type: 'text' })
        });
      } catch (e) {
        console.warn('Backend sync for victim message failed, cached locally:', e);
      }
    }

    function handleSendDirectVictimMsg(e) {
      e.preventDefault();
      const input = document.getElementById('directVictimInput');
      if (!input) return;
      const txt = input.value.trim();
      if (!txt) return;
      sendDirectVictimMsg(txt);
      input.value = '';
    }

    // ============================================================
    // REAL-TIME POLLING ENGINE (4-second intervals)
    // ============================================================
    document.addEventListener('DOMContentLoaded', () => {
      // Restore sidebar collapsed preference
      if (localStorage.getItem('volunteer_sidebar_collapsed') === '1') {
        const sb = document.getElementById('volunteerSidebar');
        const icon = document.getElementById('sidebarToggleIcon');
        if (sb) sb.classList.add('collapsed');
        if (icon) icon.textContent = 'menu';
      }

      // Initial load
      loadVolunteerAssignment();
      loadCommsMessages();
      loadDirectRequests();
      loadResourceMarkers();
      updateVolunteerGPS();
      loadMapStats();
      loadVolunteerProfile();
      loadDirectVictimChat();
      loadResources();

      setTimeout(() => {
        mapInstance.invalidateSize();
        fullMapInstance.invalidateSize();
      }, 300);

      // 4-second live background polling
      setInterval(() => {
        loadVolunteerAssignment();
        loadCommsMessages();
        loadDirectRequests();
        loadDirectVictimChat();
      }, 4000);

      // GPS update every 10 seconds
      setInterval(() => {
        updateVolunteerGPS();
      }, 10000);

      // Map resource markers & logistics every 15 seconds
      setInterval(() => {
        loadResourceMarkers();
        loadMapStats();
        loadResources();
      }, 15000);
    });
  </script>
</body>
</html>
