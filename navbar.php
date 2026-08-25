<?php
// navbar.php - DisasterSafe Government Crisis Portal Navigation Bar (Enhanced Accent Contrast)
if (!isset($currentUser)) {
    $currentUser = getCurrentUser($pdo);
}
$flash = getFlash();
$currentScript = basename($_SERVER['PHP_SELF']);
?>
<!-- Top Navigation Header -->
<header class="bg-white border-b border-slate-200 sticky top-0 z-30 shrink-0 shadow-xs">
    
    <!-- Top Sovereign Gradient Accent Ribbon -->
    <div class="top-accent-line"></div>

    <div class="h-16 px-4 sm:px-6 lg:px-8 flex items-center justify-between">
        <!-- Left Section: Drawer Toggle Button & Active Command Context -->
        <div class="flex items-center gap-3.5 sm:gap-5">
            
            <!-- Sidebar Drawer Toggle Button (Desktop & Mobile) -->
            <button type="button" onclick="toggleMainSidebar()" 
                    class="text-slate-600 hover:text-slate-900 p-2 sm:px-2.5 sm:py-2 rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-200 transition-all flex items-center gap-2 focus:outline-none shadow-2xs cursor-pointer"
                    title="Toggle Sidebar Navigation Drawer">
                <i class="fa-solid fa-bars text-sm text-[#1d63d8]"></i>
                <span class="text-xs font-bold text-slate-700 hidden md:inline">Menu</span>
            </button>

            <!-- Command Center Context Title -->
            <div class="flex items-center gap-2.5">
                <div class="h-5 w-1.5 bg-[var(--role-primary)] hidden sm:block"></div>
                <h1 class="text-sm sm:text-base font-black text-slate-900 tracking-tight">
                    <?= htmlspecialchars(PAGE_TITLE) ?>
                </h1>
                <span class="text-[10px] font-extrabold text-[var(--role-primary)] uppercase tracking-wider hidden md:inline ml-1 px-2.5 py-0.5 bg-[var(--role-accent-bg)] border border-[var(--role-accent-border)] mono">
                    Live Ops
                </span>
            </div>

            <!-- Topbar Horizontal Quick Navigation Links -->
            <nav class="hidden 2xl:flex items-center gap-1 pl-4 border-l border-slate-200 text-xs font-bold text-slate-600">
                <a href="dashboard.php" class="px-3 py-1.5 rounded-xl transition-all <?= $currentScript === 'dashboard.php' ? 'text-blue-700 bg-blue-50 border border-blue-200 shadow-xs' : 'hover:text-slate-900 hover:bg-slate-50' ?>">
                    <i class="fa-solid fa-gauge-high mr-1.5 text-[#1d63d8]"></i> Command Hub
                </a>
                <a href="map.php" class="px-3 py-1.5 rounded-xl transition-all <?= $currentScript === 'map.php' ? 'text-teal-700 bg-teal-50 border border-teal-200 shadow-xs' : 'hover:text-slate-900 hover:bg-slate-50' ?>">
                    <i class="fa-solid fa-map-location-dot mr-1.5 text-teal-600"></i> Map
                </a>
                <a href="sos.php" class="px-3 py-1.5 rounded-xl transition-all <?= $currentScript === 'sos.php' ? 'text-red-700 bg-red-50 border border-red-200 shadow-xs' : 'hover:text-slate-900 hover:bg-slate-50' ?>">
                    <i class="fa-solid fa-tower-broadcast mr-1.5 text-red-600"></i> SOS Hub
                </a>
                <a href="resources.php" class="px-3 py-1.5 rounded-xl transition-all <?= $currentScript === 'resources.php' ? 'text-amber-800 bg-amber-50 border border-amber-200 shadow-xs' : 'hover:text-slate-900 hover:bg-slate-50' ?>">
                    <i class="fa-solid fa-boxes-stacked mr-1.5 text-amber-600"></i> Resources
                </a>
                <a href="police_hub.php" class="px-3 py-1.5 rounded-xl transition-all <?= $currentScript === 'police_hub.php' ? 'text-blue-800 bg-blue-50 border border-blue-200 shadow-xs' : 'hover:text-slate-900 hover:bg-slate-50' ?>">
                    <i class="fa-solid fa-shield-halved mr-1.5 text-blue-600"></i> Police Dept
                </a>
                <a href="fire_hub.php" class="px-3 py-1.5 rounded-xl transition-all <?= $currentScript === 'fire_hub.php' ? 'text-red-800 bg-red-50 border border-red-200 shadow-xs' : 'hover:text-slate-900 hover:bg-slate-50' ?>">
                    <i class="fa-solid fa-fire-extinguisher mr-1.5 text-red-600"></i> Fire & Rescue
                </a>
                <a href="medical_hub.php" class="px-3 py-1.5 rounded-xl transition-all <?= $currentScript === 'medical_hub.php' ? 'text-teal-800 bg-teal-50 border border-teal-200 shadow-xs' : 'hover:text-slate-900 hover:bg-slate-50' ?>">
                    <i class="fa-solid fa-truck-medical mr-1.5 text-teal-600"></i> Medical Dept
                </a>
                <a href="volunteers.php" class="px-3 py-1.5 rounded-xl transition-all <?= $currentScript === 'volunteers.php' ? 'text-emerald-800 bg-emerald-50 border border-emerald-200 shadow-xs' : 'hover:text-slate-900 hover:bg-slate-50' ?>">
                    <i class="fa-solid fa-users-gear mr-1.5 text-emerald-600"></i> Volunteers
                </a>
                <a href="tasks.php" class="px-3 py-1.5 rounded-xl transition-all <?= $currentScript === 'tasks.php' ? 'text-blue-800 bg-blue-50 border border-blue-200 shadow-xs' : 'hover:text-slate-900 hover:bg-slate-50' ?>">
                    <i class="fa-solid fa-list-check mr-1.5 text-[#1d63d8]"></i> Missions
                </a>
                <a href="settings.php" class="px-3 py-1.5 rounded-xl transition-all <?= $currentScript === 'settings.php' ? 'text-indigo-800 bg-indigo-50 border border-indigo-200 shadow-xs font-bold' : 'hover:text-slate-900 hover:bg-slate-50' ?>">
                    <i class="fa-solid fa-microchip mr-1.5 text-indigo-600"></i> Settings
                </a>
            </nav>
        </div>

        <!-- Right Section: Actions, Demo Switcher & Badges -->
        <div class="flex items-center gap-2 sm:gap-3">
            
            <!-- Global ESP32 Hardware Status / Connect Pill -->
            <button type="button" id="globalEsp32NavBtn" onclick="toggleGlobalSerial()" class="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-slate-100 hover:bg-slate-200 border border-slate-300 text-xs font-bold text-slate-700 transition-all shadow-xs cursor-pointer" title="Click to Connect/Disconnect ESP32 USB Serial">
                <span id="globalEsp32Dot" class="w-2 h-2 rounded-full bg-slate-400"></span>
                <span id="globalEsp32Text" class="mono text-[11px]">🔌 ESP32: Connect</span>
            </button>

            <!-- Live Alert Dot Badge with Accent Glow -->
            <div class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-red-50 border border-red-200 text-xs font-bold text-red-700 shadow-2xs">
                <span class="live-dot"></span>
                <span class="hidden sm:inline mono">LIVE INCIDENTS</span>
            </div>

            <!-- Role Badge with Distinct Agency Color Accent -->
            <?php
                $roleSlug = $currentUser['role_slug'] ?? 'user';
                $badgeColor = match($roleSlug) {
                    'superadmin' => 'bg-purple-50 text-purple-700 border-purple-200',
                    'ndrf' => 'bg-orange-50 text-orange-700 border-orange-200',
                    'police' => 'bg-blue-50 text-blue-700 border-blue-200',
                    'fire' => 'bg-red-50 text-red-700 border-red-200',
                    'medical' => 'bg-teal-50 text-teal-700 border-teal-200',
                    'volunteer' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                    default => 'bg-slate-100 text-slate-700 border-slate-200'
                };
            ?>
            <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-full border text-xs font-extrabold <?= $badgeColor ?> mono">
                <i class="fa-solid <?= $roleSlug === 'superadmin' ? 'fa-crown text-purple-600' : ($roleSlug === 'ndrf' ? 'fa-truck-monster text-orange-600' : ($roleSlug === 'police' ? 'fa-shield-halved text-blue-600' : ($roleSlug === 'fire' ? 'fa-fire-extinguisher text-red-600' : ($roleSlug === 'medical' ? 'fa-heart-pulse text-teal-600' : ($roleSlug === 'volunteer' ? 'fa-hand-holding-heart text-emerald-600' : 'fa-user text-slate-600'))))) ?>"></i>
                <span><?= htmlspecialchars($currentUser['role_name'] ?? 'User') ?></span>
            </div>

            <!-- Quick Switch Demo Role Dropdown -->
            <div class="relative" id="demoAccountsWrapper">
                <button type="button" onclick="document.getElementById('demoSwitchMenu').classList.toggle('hidden')" 
                        class="px-3 py-1.5 text-xs font-bold bg-slate-50 hover:bg-slate-100 border border-slate-200 hover:border-[var(--role-accent-border)] text-slate-800 flex items-center gap-2 transition-all cursor-pointer">
                    <i class="fa-solid fa-users-viewfinder text-[var(--role-primary)]"></i>
                    <span class="hidden md:inline">Role Switcher</span>
                    <i class="fa-solid fa-chevron-down text-[10px] text-slate-400"></i>
                </button>
                <div id="demoSwitchMenu" class="hidden absolute right-0 mt-2 w-64 bg-white border border-slate-200 shadow-xl p-2 z-50">
                    <div class="px-3 py-2 text-[11px] font-extrabold text-blue-800 uppercase tracking-wider border-b border-slate-100 mono flex items-center justify-between">
                        <span>Demo Accounts (7 Roles)</span>
                        <i class="fa-solid fa-bolt text-amber-500"></i>
                    </div>
                    <div class="space-y-1 mt-1 max-h-80 overflow-y-auto">
                        <a href="login.php?quick_login=superadmin@system.local" class="flex items-center gap-2.5 px-3 py-2 text-xs text-slate-700 hover:bg-purple-50 hover:text-purple-900 transition-colors">
                            <div class="w-7 h-7 bg-purple-50 text-purple-600 border border-purple-200 flex items-center justify-center font-bold text-xs shrink-0">
                                <i class="fa-solid fa-crown"></i>
                            </div>
                            <div>
                                <p class="font-bold text-slate-900">Super Administrator</p>
                                <p class="text-[10px] text-slate-500">Supreme Commander</p>
                            </div>
                        </a>
                        <a href="login.php?quick_login=ndrf.commander@disaster.local" class="flex items-center gap-2.5 px-3 py-2 text-xs text-slate-700 hover:bg-orange-50 hover:text-orange-900 transition-colors">
                            <div class="w-7 h-7 bg-orange-50 text-orange-600 border border-orange-200 flex items-center justify-center font-bold text-xs shrink-0">
                                <i class="fa-solid fa-truck-monster"></i>
                            </div>
                            <div>
                                <p class="font-bold text-slate-900">NDRF Force Commander</p>
                                <p class="text-[10px] text-slate-500">Tactical Crisis Response</p>
                            </div>
                        </a>
                        <a href="login.php?quick_login=police.command@disaster.local" class="flex items-center gap-2.5 px-3 py-2 text-xs text-slate-700 hover:bg-blue-50 hover:text-blue-900 transition-colors">
                            <div class="w-7 h-7 bg-blue-50 text-blue-600 border border-blue-200 flex items-center justify-center font-bold text-xs shrink-0">
                                <i class="fa-solid fa-person-military-pointing"></i>
                            </div>
                            <div>
                                <p class="font-bold text-slate-900">Police Commander</p>
                                <p class="text-[10px] text-slate-500">Perimeter & Deployments</p>
                            </div>
                        </a>
                        <a href="login.php?quick_login=fire.chief@disaster.local" class="flex items-center gap-2.5 px-3 py-2 text-xs text-slate-700 hover:bg-red-50 hover:text-red-900 transition-colors">
                            <div class="w-7 h-7 bg-red-50 text-red-600 border border-red-200 flex items-center justify-center font-bold text-xs shrink-0">
                                <i class="fa-solid fa-fire-extinguisher"></i>
                            </div>
                            <div>
                                <p class="font-bold text-slate-900">Fire & Rescue Chief</p>
                                <p class="text-[10px] text-slate-500">Fire Suppression & Hazmat</p>
                            </div>
                        </a>
                        <a href="login.php?quick_login=medical.dir@disaster.local" class="flex items-center gap-2.5 px-3 py-2 text-xs text-slate-700 hover:bg-teal-50 hover:text-teal-900 transition-colors">
                            <div class="w-7 h-7 bg-teal-50 text-teal-600 border border-teal-200 flex items-center justify-center font-bold text-xs shrink-0">
                                <i class="fa-solid fa-truck-medical"></i>
                            </div>
                            <div>
                                <p class="font-bold text-slate-900">Medical EMS Director</p>
                                <p class="text-[10px] text-slate-500">Hospital ICU & Ambulances</p>
                            </div>
                        </a>
                        <a href="login.php?quick_login=volunteer@disaster.local" class="flex items-center gap-2.5 px-3 py-2 text-xs text-slate-700 hover:bg-emerald-50 hover:text-emerald-900 transition-colors">
                            <div class="w-7 h-7 bg-emerald-50 text-emerald-600 border border-emerald-200 flex items-center justify-center font-bold text-xs shrink-0">
                                <i class="fa-solid fa-hand-holding-heart"></i>
                            </div>
                            <div>
                                <p class="font-bold text-slate-900">Volunteer (Vaibhav Hub)</p>
                                <p class="text-[10px] text-slate-500">Field Missions & Aid</p>
                            </div>
                        </a>
                        <a href="login.php?quick_login=citizen@example.com" class="flex items-center gap-2.5 px-3 py-2 text-xs text-slate-700 hover:bg-amber-50 hover:text-amber-900 transition-colors">
                            <div class="w-7 h-7 bg-amber-50 text-amber-600 border border-amber-200 flex items-center justify-center font-bold text-xs shrink-0">
                                <i class="fa-solid fa-person-shelter"></i>
                            </div>
                            <div>
                                <p class="font-bold text-slate-900">Citizen (Yukta Portal)</p>
                                <p class="text-[10px] text-slate-500">SOS Distress & Radar</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>            <!-- User Profile Badge & Menu Dropdown -->
            <div class="relative">
                <?php
                    $navRoleSlug = $currentUser['role_slug'] ?? 'user';
                    $navRoleIcon = match($navRoleSlug) {
                        'superadmin' => 'fa-crown',
                        'ndrf' => 'fa-truck-monster',
                        'police' => 'fa-shield-halved',
                        'fire' => 'fa-fire-extinguisher',
                        'medical' => 'fa-heart-pulse',
                        'volunteer' => 'fa-hand-holding-heart',
                        default => 'fa-user'
                    };
                    $navRoleBg = match($navRoleSlug) {
                        'superadmin' => 'bg-purple-600 text-white',
                        'ndrf' => 'bg-orange-600 text-white',
                        'police' => 'bg-blue-600 text-white',
                        'fire' => 'bg-red-600 text-white',
                        'medical' => 'bg-teal-600 text-white',
                        'volunteer' => 'bg-emerald-600 text-white',
                        default => 'bg-slate-700 text-white'
                    };
                ?>
                <button type="button" onclick="document.getElementById('userMenu').classList.toggle('hidden')" 
                        class="flex items-center gap-2 p-1.5 bg-slate-50 hover:bg-slate-100 border border-slate-200 transition-colors focus:outline-none cursor-pointer rounded-lg">
                    <div class="w-7 h-7 rounded <?= $navRoleBg ?> flex items-center justify-center font-bold text-xs shadow-2xs shrink-0">
                        <i class="fa-solid <?= $navRoleIcon ?> text-[11px]"></i>
                    </div>
                    <span class="text-xs font-extrabold text-slate-900 hidden lg:inline">
                        <?= htmlspecialchars(explode(' ', $currentUser['name'] ?? 'User')[0]) ?>
                    </span>
                    <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 mr-1 hidden lg:inline"></i>
                </button>

                <div id="userMenu" class="hidden absolute right-0 mt-2 w-56 bg-white border border-slate-200 shadow-xl p-2 z-50 space-y-1">
                    <div class="px-3 py-2 border-b border-slate-100">
                        <p class="text-xs font-extrabold text-slate-900 truncate"><?= htmlspecialchars($currentUser['name'] ?? 'User') ?></p>
                        <p class="text-[10px] text-slate-500 font-mono truncate"><?= htmlspecialchars($currentUser['email'] ?? '') ?></p>
                    </div>
                    <a href="profile.php" class="flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-blue-50 hover:text-[var(--role-primary)] transition-colors">
                        <i class="fa-solid fa-user-gear text-slate-400"></i>
                        <span>Account Profile</span>
                    </a>
                    <a href="logout.php" class="flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-red-600 hover:bg-red-50 transition-colors">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                        <span>Sign Out</span>
                    </a>
                </div>
            </div>

            <!-- Dedicated Header Sign Out Button (Consistent Across All Role Pages) -->
            <a href="logout.php" title="Sign Out of DisasterSafe" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-50 hover:bg-red-100 border border-red-200 text-xs font-bold text-red-700 transition-colors shadow-2xs">
                <i class="fa-solid fa-arrow-right-from-bracket text-red-600"></i>
                <span class="hidden sm:inline">Logout</span>
            </a>iv>

        </div>
    </div>
</header>

<!-- Alert Toast Notification / Flash Messages -->
<?php if ($flash): ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 4500,
                timerProgressBar: true
            });
            Toast.fire({
                icon: '<?= $flash['type'] === 'error' ? 'error' : ($flash['type'] === 'warning' ? 'warning' : 'success') ?>',
                title: '<?= addslashes(htmlspecialchars($flash['message'])) ?>'
            });
        });
    </script>
<?php endif; ?>
