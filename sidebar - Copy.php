<?php
// sidebar.php - Dynamic DisasterSafe Command Navigation Sidebar
if (!isset($currentUser)) {
    $currentUser = getCurrentUser($pdo);
}

$isSuperAdmin = isSuperAdmin($currentUser);
$hasPolice = hasPermission($currentUser, 'access_police');
$hasFire = hasPermission($currentUser, 'access_fire');
$hasMedical = hasPermission($currentUser, 'access_medical');
$hasVolunteer = hasPermission($currentUser, 'access_volunteer');
$hasMissing = hasPermission($currentUser, 'access_missing_persons');
$hasDisasters = hasPermission($currentUser, 'access_disasters');
$hasUsers = hasPermission($currentUser, 'manage_users');
$hasRoles = hasPermission($currentUser, 'manage_roles');
$hasLogs = hasPermission($currentUser, 'view_activity_logs');

$currentScript = basename($_SERVER['PHP_SELF']);
?>

<!-- Mobile Sidebar Backdrop -->
<div id="mobile-sidebar-backdrop" class="fixed inset-0 bg-[#060a14]/80 backdrop-blur-sm z-40 hidden lg:hidden" onclick="toggleMainSidebar()"></div>

<!-- Sidebar Component (Sliding Drawer) -->
<aside id="main-sidebar" class="h-screen flex-shrink-0 w-72 bg-[#0c1326] border-r border-[#243049] flex flex-col z-50 fixed inset-y-0 left-0 lg:static lg:inset-auto -translate-x-full lg:translate-x-0">
    
    <!-- Brand Header & Collapse Drawer Button -->
    <div class="h-16 flex items-center justify-between px-5 border-b border-[#243049] shrink-0 bg-[#080d1a]">
        <a href="dashboard.php" class="flex items-center gap-3 group">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-red-600 to-indigo-600 flex items-center justify-center font-black text-white text-base shadow-md shadow-indigo-600/30 group-hover:scale-105 transition-transform shrink-0">
                <i class="fa-solid fa-shield-halved text-sm"></i>
            </div>
            <div class="min-w-0">
                <span class="text-base font-extrabold text-white tracking-tight block truncate">DisasterSafe</span>
                <span class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider truncate">Command Center</span>
            </div>
        </a>
        <button type="button" onclick="toggleMainSidebar()" class="text-slate-400 hover:text-white p-1.5 rounded-lg hover:bg-slate-800 transition-colors focus:outline-none" title="Hide Sidebar Drawer">
            <i class="fa-solid fa-chevron-left text-xs hidden lg:inline"></i>
            <i class="fa-solid fa-xmark text-base lg:hidden"></i>
        </button>
    </div>

    <!-- Navigation Links (Scrollable Middle Section) -->
    <div class="flex-1 overflow-y-auto px-3.5 py-4 space-y-4 text-xs font-semibold">
        
        <!-- Command & Overview -->
        <div>
            <div class="px-3 mb-1.5 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                Command & Overview
            </div>
            <nav class="space-y-1">
                <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all <?= ($currentScript === 'dashboard.php') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30 font-bold' : 'text-slate-400 hover:text-slate-100 hover:bg-[#131e36]' ?>">
                    <i class="fa-solid fa-chart-pie text-sm w-4 text-center"></i>
                    <span class="flex-1">Command Hub</span>
                </a>

                <a href="map.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all <?= ($currentScript === 'map.php') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30 font-bold' : 'text-slate-400 hover:text-slate-100 hover:bg-[#131e36]' ?>">
                    <i class="fa-solid fa-map-location-dot text-sm w-4 text-center text-teal-400"></i>
                    <span class="flex-1">Map</span>
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                </a>

                <?php 
                    $hasSosAccess = isSuperAdmin($currentUser) || hasPermission($currentUser, 'access_sos_database');
                    if ($hasSosAccess): 
                        $pendingSosCount = (int) $pdo->query("SELECT COUNT(*) FROM emergency_sos WHERE status = 'Pending'")->fetchColumn();
                ?>
                    <a href="sos.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all <?= ($currentScript === 'sos.php') ? 'bg-rose-600 text-white shadow-lg shadow-rose-600/30 font-bold' : 'text-slate-400 hover:text-slate-100 hover:bg-[#131e36]' ?>">
                        <i class="fa-solid fa-tower-broadcast text-sm w-4 text-center text-rose-400"></i>
                        <span class="flex-1">SOS Alerts Hub</span>
                        <?php if ($pendingSosCount > 0): ?>
                            <span class="px-1.5 py-0.5 text-[9px] font-extrabold rounded bg-rose-500/20 text-rose-300 border border-rose-500/30 animate-pulse">
                                <?= $pendingSosCount ?> CRITICAL
                            </span>
                        <?php else: ?>
                            <span class="px-1.5 py-0.5 text-[9px] font-bold rounded bg-slate-800 text-slate-400 border border-slate-700">
                                <?= $pdo->query("SELECT COUNT(*) FROM emergency_sos")->fetchColumn(); ?>
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>

                <a href="resources.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all <?= ($currentScript === 'resources.php') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30 font-bold' : 'text-slate-400 hover:text-slate-100 hover:bg-[#131e36]' ?>">
                    <i class="fa-solid fa-boxes-stacked text-sm w-4 text-center text-amber-400"></i>
                    <span class="flex-1">Resources</span>
                    <span class="px-1.5 py-0.5 text-[9px] font-bold rounded bg-indigo-950 text-indigo-300 border border-indigo-800">
                        <?= $pdo->query("SELECT COUNT(*) FROM master_resources")->fetchColumn(); ?>
                    </span>
                </a>
            </nav>
        </div>

        <!-- Emergency Agency Command (Dedicated 3 Departments) -->
        <?php if ($isSuperAdmin || $hasPolice || $hasFire || $hasMedical): ?>
            <div>
                <div class="px-3 mb-1.5 text-[10px] font-extrabold uppercase tracking-wider text-amber-400 flex items-center justify-between">
                    <span>Emergency Agencies</span>
                    <i class="fa-solid fa-building-shield text-[10px]"></i>
                </div>
                <nav class="space-y-1">
                    <!-- 1. Police Department -->
                    <?php if ($isSuperAdmin || $hasPolice): ?>
                        <a href="police_hub.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all <?= ($currentScript === 'police_hub.php') ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/30 font-bold' : 'text-slate-400 hover:text-slate-100 hover:bg-[#131e36]' ?>">
                            <i class="fa-solid fa-shield-halved text-sm w-4 text-center text-blue-400"></i>
                            <span class="flex-1">Police Department</span>
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-blue-950 border border-blue-800 text-blue-300">
                                <?= $pdo->query("SELECT COUNT(*) FROM agency_teams WHERE agency_type = 'Police' AND status != 'Available'")->fetchColumn(); ?> Active
                            </span>
                        </a>
                    <?php endif; ?>

                    <!-- 2. Fire & Rescue Department -->
                    <?php if ($isSuperAdmin || $hasFire): ?>
                        <a href="fire_hub.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all <?= ($currentScript === 'fire_hub.php') ? 'bg-red-600 text-white shadow-lg shadow-red-600/30 font-bold' : 'text-slate-400 hover:text-slate-100 hover:bg-[#131e36]' ?>">
                            <i class="fa-solid fa-fire-extinguisher text-sm w-4 text-center text-red-400"></i>
                            <span class="flex-1">Fire & Rescue Dept</span>
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-red-950 border border-red-800 text-red-300">
                                <?= $pdo->query("SELECT COUNT(*) FROM agency_teams WHERE agency_type = 'Fire' AND status != 'Available'")->fetchColumn(); ?> Units
                            </span>
                        </a>
                    <?php endif; ?>

                    <!-- 3. Medical Department -->
                    <?php if ($isSuperAdmin || $hasMedical): ?>
                        <a href="medical_hub.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all <?= ($currentScript === 'medical_hub.php') ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-600/30 font-bold' : 'text-slate-400 hover:text-slate-100 hover:bg-[#131e36]' ?>">
                            <i class="fa-solid fa-truck-medical text-sm w-4 text-center text-emerald-400"></i>
                            <span class="flex-1">Medical Department</span>
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-emerald-950 border border-emerald-800 text-emerald-300">
                                <?= $pdo->query("SELECT available_quantity FROM agency_resources WHERE agency_type = 'Medical' AND item_name LIKE '%ICU%' LIMIT 1")->fetchColumn() ?: '98'; ?> Beds
                            </span>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>

        <!-- Police Registry & Missing Persons -->
        <?php if ($hasPolice || $hasMissing || $isSuperAdmin): ?>
            <div>
                <div class="px-3 mb-1.5 text-[10px] font-extrabold uppercase tracking-wider text-purple-400 flex items-center justify-between">
                    <span>Registry & Search</span>
                    <i class="fa-solid fa-person-circle-question text-[10px]"></i>
                </div>
                <nav class="space-y-1">
                    <a href="missing_persons.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all <?= ($currentScript === 'missing_persons.php') ? 'bg-purple-600 text-white shadow-lg shadow-purple-600/30 font-bold' : 'text-slate-400 hover:text-slate-100 hover:bg-[#131e36]' ?>">
                        <i class="fa-solid fa-person-circle-question text-sm w-4 text-center text-purple-400"></i>
                        <span class="flex-1">Missing Persons</span>
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-rose-950 border border-rose-800 text-rose-300">
                            <?= $pdo->query("SELECT COUNT(*) FROM missing_persons WHERE status = 'Missing'")->fetchColumn(); ?>
                        </span>
                    </a>
                </nav>
            </div>
        <?php endif; ?>

        <!-- Volunteer Relief Corps & NGO Management -->
        <?php if ($hasVolunteer || $isSuperAdmin): ?>
            <div>
                <div class="px-3 mb-1.5 text-[10px] font-extrabold uppercase tracking-wider text-emerald-400 flex items-center justify-between">
                    <span>NGO & Volunteer Corps</span>
                    <i class="fa-solid fa-hand-holding-heart text-[10px]"></i>
                </div>
                <nav class="space-y-1">
                    <?php 
                        $pendingVolCount = (int) $pdo->query("SELECT COUNT(*) FROM volunteers WHERE application_status = 'Pending Approval'")->fetchColumn();
                        $activeVolCount = (int) $pdo->query("SELECT COUNT(*) FROM volunteers WHERE application_status = 'Approved'")->fetchColumn();
                    ?>
                    <a href="volunteers.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all <?= ($currentScript === 'volunteers.php') ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-600/30 font-bold' : 'text-slate-400 hover:text-slate-100 hover:bg-[#131e36]' ?>">
                        <i class="fa-solid fa-users-gear text-sm w-4 text-center text-emerald-400"></i>
                        <span class="flex-1">Volunteer Management</span>
                        <span class="px-1.5 py-0.5 text-[9px] font-bold rounded bg-emerald-950 text-emerald-300 border border-emerald-800">
                            <?= $activeVolCount ?>
                        </span>
                    </a>

                    <a href="tasks.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all <?= ($currentScript === 'tasks.php') ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-600/30 font-bold' : 'text-slate-400 hover:text-slate-100 hover:bg-[#131e36]' ?>">
                        <i class="fa-solid fa-list-check text-sm w-4 text-center text-emerald-400"></i>
                        <span class="flex-1">Missions Board</span>
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-emerald-950 border border-emerald-800 text-emerald-300">
                            <?= $pdo->query("SELECT COUNT(*) FROM volunteer_tasks WHERE status != 'Completed'")->fetchColumn(); ?> Open
                        </span>
                    </a>

                    <a href="relief.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all <?= ($currentScript === 'relief.php') ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-600/30 font-bold' : 'text-slate-400 hover:text-slate-100 hover:bg-[#131e36]' ?>">
                        <i class="fa-solid fa-boxes-stacked text-sm w-4 text-center text-teal-400"></i>
                        <span>Relief Supply Ledger</span>
                    </a>
                </nav>
            </div>
        <?php endif; ?>

        <!-- System Governance (Admin & Superadmin) -->
        <?php if ($isSuperAdmin || $hasUsers || $hasRoles || $hasLogs): ?>
            <div>
                <div class="px-3 mb-1.5 text-[10px] font-extrabold uppercase tracking-wider text-indigo-400">
                    Governance
                </div>
                <nav class="space-y-1">
                    <?php if ($hasUsers || $isSuperAdmin): ?>
                        <a href="users.php" class="flex items-center gap-3 px-3 py-2 rounded-xl transition-all <?= ($currentScript === 'users.php') ? 'bg-indigo-600 text-white font-bold' : 'text-slate-400 hover:text-slate-100 hover:bg-[#131e36]' ?>">
                            <i class="fa-solid fa-users-gear text-sm w-4 text-center"></i>
                            <span>Users & Permissions</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($hasRoles || $isSuperAdmin): ?>
                        <a href="roles.php" class="flex items-center gap-3 px-3 py-2 rounded-xl transition-all <?= ($currentScript === 'roles.php') ? 'bg-indigo-600 text-white font-bold' : 'text-slate-400 hover:text-slate-100 hover:bg-[#131e36]' ?>">
                            <i class="fa-solid fa-user-shield text-sm w-4 text-center"></i>
                            <span>Role Matrix</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($hasLogs || $isSuperAdmin): ?>
                        <a href="activity_logs.php" class="flex items-center gap-3 px-3 py-2 rounded-xl transition-all <?= ($currentScript === 'activity_logs.php') ? 'bg-indigo-600 text-white font-bold' : 'text-slate-400 hover:text-slate-100 hover:bg-[#131e36]' ?>">
                            <i class="fa-solid fa-clock-rotate-left text-sm w-4 text-center"></i>
                            <span>Audit & Security Logs</span>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>

    </div>

    <!-- DOCKED BOTTOM-LEFT CORNER USER & LOGOUT FOOTER -->
    <div class="p-3.5 border-t border-[#243049] bg-[#080d1a] space-y-2.5 shrink-0">
        
        <!-- User Profile Pill -->
        <a href="profile.php" class="flex items-center gap-2.5 p-1.5 rounded-xl hover:bg-slate-800/60 transition-colors">
            <div class="w-8 h-8 rounded-lg bg-indigo-600/20 border border-indigo-500/30 flex items-center justify-center text-indigo-400 font-bold shrink-0">
                <?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)) ?>
            </div>
            <div class="min-w-0 flex-1">
                <p class="font-bold text-slate-200 text-xs truncate leading-tight"><?= htmlspecialchars($currentUser['name'] ?? 'User') ?></p>
                <p class="text-[10px] text-slate-500 truncate"><?= htmlspecialchars($currentUser['role_name'] ?? 'Official') ?></p>
            </div>
            <i class="fa-solid fa-gear text-xs text-slate-500 hover:text-slate-300"></i>
        </a>

        <!-- Sign Out Button (Clean, prominent, docked at bottom-left) -->
        <a href="logout.php" class="w-full py-2 px-3 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/30 text-rose-400 hover:text-rose-300 font-bold text-xs flex items-center justify-center gap-2 transition-all">
            <i class="fa-solid fa-power-off text-xs"></i>
            <span>Sign Out</span>
        </a>

    </div>
</aside>
