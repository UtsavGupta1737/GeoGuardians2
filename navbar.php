<?php
// navbar.php - DisasterSafe Tactical Top Navigation Bar
if (!isset($currentUser)) {
    $currentUser = getCurrentUser($pdo);
}
$flash = getFlash();
$currentScript = basename($_SERVER['PHP_SELF']);
?>
<!-- Top Navigation Header -->
<header class="h-16 bg-[#0c1326]/90 backdrop-blur-xl border-b border-[#243049] px-4 sm:px-8 flex items-center justify-between sticky top-0 z-30 shrink-0">
    
    <!-- Left Section: Drawer Toggle Button & Active Command Context -->
    <div class="flex items-center gap-4 sm:gap-6">
        
        <!-- Sidebar Drawer Toggle Button (Desktop & Mobile) -->
        <button type="button" onclick="toggleMainSidebar()" 
                class="text-slate-300 hover:text-white p-2 sm:px-2.5 sm:py-2 rounded-xl bg-slate-800/60 hover:bg-slate-700/80 border border-[#243049] transition-all flex items-center gap-2 focus:outline-none shadow-sm"
                title="Toggle Sidebar Navigation Drawer">
            <i class="fa-solid fa-bars text-sm"></i>
            <span class="text-xs font-bold text-slate-300 hidden md:inline">Menu</span>
        </button>

        <!-- Command Center Context Title (Clean, non-duplicated) -->
        <div class="flex items-center gap-2">
            <div class="h-4 w-1 rounded-full bg-indigo-500 hidden sm:block"></div>
            <h1 class="text-sm sm:text-base font-extrabold text-white tracking-tight">
                <?= htmlspecialchars(PAGE_TITLE) ?>
            </h1>
            <span class="text-[10px] font-semibold text-indigo-400 uppercase tracking-wider hidden md:inline ml-1 px-2 py-0.5 rounded bg-indigo-500/10 border border-indigo-500/20">
                Live Ops
            </span>
        </div>

        <!-- Topbar Horizontal Quick Navigation Links -->
        <nav class="hidden 2xl:flex items-center gap-1 pl-4 border-l border-[#243049] text-xs font-semibold text-slate-400">
            <a href="dashboard.php" class="px-3 py-1.5 rounded-lg transition-colors <?= $currentScript === 'dashboard.php' ? 'text-white bg-indigo-600/20 border border-indigo-500/30 font-bold' : 'hover:text-slate-200 hover:bg-slate-800/40' ?>">
                <i class="fa-solid fa-gauge-high mr-1.5 text-indigo-400"></i> Command Hub
            </a>
            <a href="map.php" class="px-3 py-1.5 rounded-lg transition-colors <?= $currentScript === 'map.php' ? 'text-white bg-indigo-600/20 border border-indigo-500/30 font-bold text-teal-300' : 'hover:text-slate-200 hover:bg-slate-800/40' ?>">
                <i class="fa-solid fa-map-location-dot mr-1.5 text-teal-400"></i> Map
            </a>
            <a href="sos.php" class="px-3 py-1.5 rounded-lg transition-colors <?= $currentScript === 'sos.php' ? 'text-white bg-rose-600/20 border border-rose-500/30 font-bold text-rose-300' : 'hover:text-slate-200 hover:bg-slate-800/40' ?>">
                <i class="fa-solid fa-tower-broadcast mr-1.5 text-rose-400"></i> SOS Hub
            </a>
            <a href="resources.php" class="px-3 py-1.5 rounded-lg transition-colors <?= $currentScript === 'resources.php' ? 'text-white bg-indigo-600/20 border border-indigo-500/30 font-bold text-amber-300' : 'hover:text-slate-200 hover:bg-slate-800/40' ?>">
                <i class="fa-solid fa-boxes-stacked mr-1.5 text-amber-400"></i> Resources
            </a>
            <a href="police_hub.php" class="px-3 py-1.5 rounded-lg transition-colors <?= $currentScript === 'police_hub.php' ? 'text-white bg-blue-500/20 border border-blue-500/30 font-bold text-blue-300' : 'hover:text-slate-200 hover:bg-slate-800/40' ?>">
                <i class="fa-solid fa-shield-halved mr-1.5 text-blue-400"></i> Police Dept
            </a>
            <a href="fire_hub.php" class="px-3 py-1.5 rounded-lg transition-colors <?= $currentScript === 'fire_hub.php' ? 'text-white bg-red-500/20 border border-red-500/30 font-bold text-red-300' : 'hover:text-slate-200 hover:bg-slate-800/40' ?>">
                <i class="fa-solid fa-fire-extinguisher mr-1.5 text-red-400"></i> Fire & Rescue
            </a>
            <a href="medical_hub.php" class="px-3 py-1.5 rounded-lg transition-colors <?= $currentScript === 'medical_hub.php' ? 'text-white bg-emerald-500/20 border border-emerald-500/30 font-bold text-emerald-300' : 'hover:text-slate-200 hover:bg-slate-800/40' ?>">
                <i class="fa-solid fa-truck-medical mr-1.5 text-emerald-400"></i> Medical Dept
            </a>
            <a href="volunteers.php" class="px-3 py-1.5 rounded-lg transition-colors <?= $currentScript === 'volunteers.php' ? 'text-white bg-emerald-600/20 border border-emerald-500/30 font-bold text-emerald-300' : 'hover:text-slate-200 hover:bg-slate-800/40' ?>">
                <i class="fa-solid fa-users-gear mr-1.5 text-emerald-400"></i> Volunteer Mgmt
            </a>
            <a href="tasks.php" class="px-3 py-1.5 rounded-lg transition-colors <?= $currentScript === 'tasks.php' ? 'text-white bg-emerald-500/20 border border-emerald-500/30 font-bold' : 'hover:text-slate-200 hover:bg-slate-800/40' ?>">
                <i class="fa-solid fa-list-check mr-1.5 text-emerald-400"></i> Missions
            </a>
        </nav>
    </div>

    <!-- Right Section: Actions, Demo Switcher & Badges -->
    <div class="flex items-center gap-3 sm:gap-4">
        
        <!-- Live Alert Dot Badge -->
        <div class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-[#11192e] border border-[#243049] text-xs font-semibold text-slate-300 shadow-sm">
            <span class="live-dot"></span>
            <span class="hidden sm:inline">LIVE INCIDENTS</span>
        </div>

        <!-- Role Badge -->
        <?php
            $roleSlug = $currentUser['role_slug'] ?? 'user';
            $badgeColor = match($roleSlug) {
                'superadmin' => 'bg-purple-500/10 text-purple-400 border-purple-500/30',
                'ndrf' => 'bg-orange-500/10 text-orange-400 border-orange-500/30',
                'police' => 'bg-blue-500/10 text-blue-400 border-blue-500/30',
                'fire' => 'bg-red-500/10 text-red-400 border-red-500/30',
                'medical' => 'bg-teal-500/10 text-teal-400 border-teal-500/30',
                'volunteer' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
                default => 'bg-slate-500/10 text-slate-300 border-slate-500/30'
            };
        ?>
        <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-full border text-xs font-semibold <?= $badgeColor ?>">
            <i class="fa-solid <?= $roleSlug === 'superadmin' ? 'fa-crown' : ($roleSlug === 'ndrf' ? 'fa-truck-monster' : ($roleSlug === 'police' ? 'fa-shield-halved' : ($roleSlug === 'fire' ? 'fa-fire-extinguisher' : ($roleSlug === 'medical' ? 'fa-heart-pulse' : ($roleSlug === 'volunteer' ? 'fa-hand-holding-heart' : 'fa-user'))))) ?>"></i>
            <span><?= htmlspecialchars($currentUser['role_name'] ?? 'User') ?></span>
        </div>

        <!-- Quick Switch Demo Role Dropdown -->
        <div class="relative" id="demoAccountsWrapper">
            <button type="button" onclick="document.getElementById('demoSwitchMenu').classList.toggle('hidden')" 
                    class="px-3 py-1.5 text-xs font-semibold bg-[#11192e] hover:bg-slate-800 border border-[#243049] rounded-xl text-slate-300 flex items-center gap-2 transition-all shadow-sm">
                <i class="fa-solid fa-users-viewfinder text-indigo-400"></i>
                <span class="hidden md:inline">Demo Role Switcher</span>
                <i class="fa-solid fa-chevron-down text-[10px] text-slate-400"></i>
            </button>
            <div id="demoSwitchMenu" class="hidden absolute right-0 mt-2 w-64 rounded-2xl bg-[#0f172a] border border-[#243049] shadow-2xl p-2 z-50">
                <div class="px-3 py-2 text-[11px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-800">
                    1-Click Demo Accounts (7 Roles)
                </div>
                <div class="space-y-1 mt-1 max-h-80 overflow-y-auto">
                    <a href="login.php?quick_login=superadmin@system.local" class="flex items-center gap-2.5 px-3 py-2 text-xs text-purple-300 hover:bg-purple-950/40 rounded-xl transition-colors">
                        <i class="fa-solid fa-crown text-purple-400 w-4 text-center"></i>
                        <div>
                            <p class="font-bold">Super Administrator</p>
                            <p class="text-[10px] text-slate-400">Supreme Commander</p>
                        </div>
                    </a>
                    <a href="login.php?quick_login=ndrf.commander@disaster.local" class="flex items-center gap-2.5 px-3 py-2 text-xs text-orange-300 hover:bg-orange-950/40 rounded-xl transition-colors">
                        <i class="fa-solid fa-truck-monster text-orange-400 w-4 text-center"></i>
                        <div>
                            <p class="font-bold">NDRF Force Commander</p>
                            <p class="text-[10px] text-slate-400">Tactical Crisis Response</p>
                        </div>
                    </a>
                    <a href="login.php?quick_login=police.command@disaster.local" class="flex items-center gap-2.5 px-3 py-2 text-xs text-blue-300 hover:bg-blue-950/40 rounded-xl transition-colors">
                        <i class="fa-solid fa-person-military-pointing text-blue-400 w-4 text-center"></i>
                        <div>
                            <p class="font-bold">Police Commander</p>
                            <p class="text-[10px] text-slate-400">Perimeter & Deployments</p>
                        </div>
                    </a>
                    <a href="login.php?quick_login=fire.chief@disaster.local" class="flex items-center gap-2.5 px-3 py-2 text-xs text-red-300 hover:bg-red-950/40 rounded-xl transition-colors">
                        <i class="fa-solid fa-fire-extinguisher text-red-400 w-4 text-center"></i>
                        <div>
                            <p class="font-bold">Fire & Rescue Chief</p>
                            <p class="text-[10px] text-slate-400">Fire Suppression & Hazmat</p>
                        </div>
                    </a>
                    <a href="login.php?quick_login=medical.ems@disaster.local" class="flex items-center gap-2.5 px-3 py-2 text-xs text-teal-300 hover:bg-teal-950/40 rounded-xl transition-colors">
                        <i class="fa-solid fa-heart-pulse text-teal-400 w-4 text-center"></i>
                        <div>
                            <p class="font-bold">Medical & EMS Chief</p>
                            <p class="text-[10px] text-slate-400">ICU Beds & Ambulances</p>
                        </div>
                    </a>
                    <a href="login.php?quick_login=volunteer@disaster.local" class="flex items-center gap-2.5 px-3 py-2 text-xs text-emerald-300 hover:bg-emerald-950/40 rounded-xl transition-colors">
                        <i class="fa-solid fa-hand-holding-heart text-emerald-400 w-4 text-center"></i>
                        <div>
                            <p class="font-bold">Disaster Volunteer</p>
                            <p class="text-[10px] text-slate-400">Ground Missions & Relief</p>
                        </div>
                    </a>
                    <a href="login.php?quick_login=citizen@example.com" class="flex items-center gap-2.5 px-3 py-2 text-xs text-slate-300 hover:bg-slate-800 rounded-xl transition-colors">
                        <i class="fa-solid fa-user text-slate-400 w-4 text-center"></i>
                        <div>
                            <p class="font-bold">Public Citizen</p>
                            <p class="text-[10px] text-slate-400">Safe Shelters & SOS</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- User Profile Avatar & Dropdown -->
        <div class="relative">
            <button type="button" onclick="document.getElementById('userProfileMenu').classList.toggle('hidden')" 
                    class="flex items-center gap-2 p-1 rounded-xl hover:bg-slate-800/60 transition-colors focus:outline-none">
                <img src="<?= htmlspecialchars($currentUser['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($currentUser['name'] ?? 'User') . '&background=6366f1&color=fff') ?>" 
                     alt="Avatar" class="w-8 h-8 rounded-lg object-cover ring-2 ring-indigo-500/40">
                <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 hidden sm:block"></i>
            </button>
            <div id="userProfileMenu" class="hidden absolute right-0 mt-2 w-56 rounded-2xl bg-[#0f172a] border border-[#243049] shadow-2xl p-2 z-50">
                <div class="px-3 py-2 border-b border-slate-800">
                    <p class="text-xs font-bold text-white truncate"><?= htmlspecialchars($currentUser['name'] ?? 'Guest') ?></p>
                    <p class="text-[11px] text-slate-400 truncate"><?= htmlspecialchars($currentUser['email'] ?? '') ?></p>
                </div>
                <div class="space-y-1 mt-1">
                    <a href="profile.php" class="flex items-center gap-2 px-3 py-2 text-xs text-slate-300 hover:bg-slate-800 rounded-xl transition-colors">
                        <i class="fa-solid fa-user-pen text-indigo-400 w-4 text-center"></i>
                        <span>Edit Profile</span>
                    </a>
                    <hr class="border-slate-800 my-1">
                    <a href="logout.php" class="flex items-center gap-2 px-3 py-2 text-xs text-rose-400 hover:bg-rose-500/10 rounded-xl transition-colors font-semibold">
                        <i class="fa-solid fa-power-off text-rose-400 w-4 text-center"></i>
                        <span>Sign Out</span>
                    </a>
                </div>
            </div>
        </div>

    </div>
</header>

<script>
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#demoAccountsWrapper')) {
            const menu = document.getElementById('demoSwitchMenu');
            if (menu && !menu.classList.contains('hidden')) {
                menu.classList.add('hidden');
            }
        }
        if (!e.target.closest('#userProfileMenu') && !e.target.closest('button[onclick*="userProfileMenu"]')) {
            const profileMenu = document.getElementById('userProfileMenu');
            if (profileMenu && !profileMenu.classList.contains('hidden')) {
                profileMenu.classList.add('hidden');
            }
        }
    });
</script>
