<?php
// sidebar.php - Dynamic DisasterSafe Command Navigation Sidebar (Enhanced Accent Contrast)
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
<div id="mobile-sidebar-backdrop" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-40 hidden lg:hidden" onclick="toggleMainSidebar()"></div>

<!-- Sidebar Component (Sliding Drawer) -->
<aside id="main-sidebar" class="h-screen flex-shrink-0 w-72 bg-white border-r border-slate-200 flex flex-col z-50 fixed inset-y-0 left-0 lg:static lg:inset-auto -translate-x-full lg:translate-x-0 shadow-xs">
    
    <!-- Brand Header & Collapse Drawer Button -->
    <div class="h-16 flex items-center justify-between px-5 border-b border-slate-200 shrink-0 bg-white">
        <a href="dashboard.php" class="flex items-center gap-3 group">
            <div class="w-9 h-9 bg-gradient-to-br from-[var(--role-gradient-from)] to-[var(--role-gradient-to)] text-white flex items-center justify-center font-black text-sm shrink-0 border border-[var(--role-accent-border)]">
                <i class="fa-solid fa-shield-halved text-sm"></i>
            </div>
            <div class="min-w-0">
                <span class="text-base font-black text-slate-900 tracking-tight block truncate">Disaster<span class="text-[var(--role-primary)]">Safe</span></span>
                <span class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider truncate mono">Command Center</span>
            </div>
        </a>
        <button type="button" onclick="toggleMainSidebar()" class="text-slate-500 hover:text-slate-900 p-1.5 rounded-lg hover:bg-slate-100 transition-colors focus:outline-none cursor-pointer" title="Hide Sidebar Drawer">
            <i class="fa-solid fa-chevron-left text-xs hidden lg:inline"></i>
            <i class="fa-solid fa-xmark text-base lg:hidden"></i>
        </button>
    </div>

    <!-- Navigation Links (Scrollable Middle Section) -->
    <div class="flex-1 overflow-y-auto px-3.5 py-4 space-y-4 text-xs font-bold">
        
        <!-- Command & Overview -->
        <div>
            <div class="px-3 mb-1.5 text-[10px] font-black uppercase tracking-wider text-blue-900/70 mono flex items-center justify-between">
                <span>Command &amp; Overview</span>
                <i class="fa-solid fa-satellite-dish text-[10px] text-blue-600"></i>
            </div>
            <nav class="space-y-1">
                <a href="dashboard.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all <?= ($currentScript === 'dashboard.php') ? 'bg-blue-50 text-[#1d4ed8] border-l-4 border-[#1d63d8] shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                    <i class="fa-solid fa-chart-pie text-sm w-4 text-center text-[#1d63d8]"></i>
                    <span class="flex-1 font-extrabold">Command Hub</span>
                </a>

                <a href="map.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all <?= ($currentScript === 'map.php') ? 'bg-teal-50 text-teal-800 border-l-4 border-teal-600 shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                    <i class="fa-solid fa-map-location-dot text-sm w-4 text-center text-teal-600"></i>
                    <span class="flex-1 font-extrabold">Tactical GIS Map</span>
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                </a>

                <?php 
                    $hasSosAccess = isSuperAdmin($currentUser) || hasPermission($currentUser, 'access_sos_database');
                    if ($hasSosAccess): 
                        $pendingSosCount = (int) $pdo->query("SELECT COUNT(*) FROM emergency_sos WHERE status = 'Pending'")->fetchColumn();
                ?>
                    <a href="sos.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all <?= ($currentScript === 'sos.php') ? 'bg-red-50 text-red-800 border-l-4 border-red-600 shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                        <i class="fa-solid fa-tower-broadcast text-sm w-4 text-center text-red-600"></i>
                        <span class="flex-1 font-extrabold">SOS Alerts Hub</span>
                        <?php if ($pendingSosCount > 0): ?>
                            <span class="px-1.5 py-0.5 text-[9px] font-extrabold rounded-full bg-red-100 text-red-800 border border-red-300 animate-pulse mono">
                                <?= $pendingSosCount ?> CRITICAL
                            </span>
                        <?php else: ?>
                            <span class="px-1.5 py-0.5 text-[9px] font-bold rounded-full bg-slate-100 text-slate-600 border border-slate-200 mono">
                                <?= $pdo->query("SELECT COUNT(*) FROM emergency_sos")->fetchColumn(); ?>
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>

                <a href="resources.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all <?= ($currentScript === 'resources.php') ? 'bg-amber-50 text-amber-900 border-l-4 border-amber-600 shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                    <i class="fa-solid fa-boxes-stacked text-sm w-4 text-center text-amber-600"></i>
                    <span class="flex-1 font-extrabold">Logistics &amp; Resources</span>
                    <span class="px-1.5 py-0.5 text-[9px] font-bold rounded-full bg-amber-100 text-amber-800 border border-amber-200 mono">
                        <?= $pdo->query("SELECT COUNT(*) FROM master_resources")->fetchColumn(); ?>
                    </span>
                </a>
            </nav>
        </div>

        <!-- Emergency Agency Command (Dedicated 3 Departments) -->
        <?php if ($isSuperAdmin || $hasPolice || $hasFire || $hasMedical): ?>
            <div>
                <div class="px-3 mb-1.5 text-[10px] font-black uppercase tracking-wider text-slate-500 flex items-center justify-between mono">
                    <span>Emergency Agencies</span>
                    <i class="fa-solid fa-building-shield text-[10px] text-slate-500"></i>
                </div>
                <nav class="space-y-1">
                    <!-- 1. Police Department -->
                    <?php if ($isSuperAdmin || $hasPolice): ?>
                        <a href="police_hub.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all <?= ($currentScript === 'police_hub.php') ? 'bg-blue-50 text-blue-900 border-l-4 border-blue-600 shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                            <i class="fa-solid fa-shield-halved text-sm w-4 text-center text-blue-600"></i>
                            <span class="flex-1 font-extrabold">Police Command Hub</span>
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-blue-100 border border-blue-200 text-blue-800 mono">
                                <?= $pdo->query("SELECT COUNT(*) FROM agency_teams WHERE agency_type = 'Police' AND status != 'Available'")->fetchColumn(); ?> Active
                            </span>
                        </a>
                    <?php endif; ?>

                    <!-- 2. Fire & Rescue Department -->
                    <?php if ($isSuperAdmin || $hasFire): ?>
                        <a href="fire_hub.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all <?= ($currentScript === 'fire_hub.php') ? 'bg-red-50 text-red-900 border-l-4 border-red-600 shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                            <i class="fa-solid fa-fire-extinguisher text-sm w-4 text-center text-red-600"></i>
                            <span class="flex-1 font-extrabold">Fire &amp; Rescue Hub</span>
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-red-100 border border-red-200 text-red-800 mono">
                                <?= $pdo->query("SELECT COUNT(*) FROM agency_teams WHERE agency_type = 'Fire' AND status != 'Available'")->fetchColumn(); ?> Units
                            </span>
                        </a>
                    <?php endif; ?>

                    <!-- 3. Medical Department -->
                    <?php if ($isSuperAdmin || $hasMedical): ?>
                        <a href="medical_hub.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all <?= ($currentScript === 'medical_hub.php') ? 'bg-teal-50 text-teal-900 border-l-4 border-teal-600 shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                            <i class="fa-solid fa-truck-medical text-sm w-4 text-center text-teal-600"></i>
                            <span class="flex-1 font-extrabold">EMS &amp; Medical Hub</span>
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-teal-100 border border-teal-200 text-teal-800 mono">
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
                <div class="px-3 mb-1.5 text-[10px] font-black uppercase tracking-wider text-slate-500 flex items-center justify-between mono">
                    <span>Field Tactical Operations</span>
                    <i class="fa-solid fa-user-shield text-[10px] text-slate-500"></i>
                </div>
                <nav class="space-y-1">
                    <?php if ($hasPolice || $isSuperAdmin): ?>
                        <a href="deployments.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all <?= ($currentScript === 'deployments.php') ? 'bg-blue-50 text-blue-900 border-l-4 border-blue-600 shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                            <i class="fa-solid fa-location-crosshairs text-sm w-4 text-center text-blue-600"></i>
                            <span class="flex-1 font-extrabold">Squad Deployments</span>
                            <span class="px-1.5 py-0.5 text-[9px] font-bold rounded-full bg-slate-100 text-slate-700 border border-slate-200 mono">
                                <?= $pdo->query("SELECT COUNT(*) FROM police_deployments")->fetchColumn(); ?>
                            </span>
                        </a>
                    <?php endif; ?>

                    <?php if ($hasMissing || $isSuperAdmin): ?>
                        <a href="missing_persons.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all <?= ($currentScript === 'missing_persons.php') ? 'bg-amber-50 text-amber-900 border-l-4 border-amber-600 shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                            <i class="fa-solid fa-person-circle-question text-sm w-4 text-center text-amber-600"></i>
                            <span class="flex-1 font-extrabold">Missing Persons</span>
                            <span class="px-1.5 py-0.5 text-[9px] font-bold rounded-full bg-amber-100 text-amber-800 border border-amber-200 mono">
                                <?= $pdo->query("SELECT COUNT(*) FROM missing_persons WHERE status = 'Missing'")->fetchColumn(); ?> Active
                            </span>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>

        <!-- Volunteer Relief & Missions -->
        <?php if ($hasVolunteer || $isSuperAdmin): ?>
            <div>
                <div class="px-3 mb-1.5 text-[10px] font-black uppercase tracking-wider text-slate-500 flex items-center justify-between mono">
                    <span>Relief &amp; Volunteers</span>
                    <i class="fa-solid fa-hand-holding-heart text-[10px] text-emerald-600"></i>
                </div>
                <nav class="space-y-1">
                    <a href="volunteers.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all <?= ($currentScript === 'volunteers.php') ? 'bg-emerald-50 text-emerald-900 border-l-4 border-emerald-600 shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                        <i class="fa-solid fa-users-gear text-sm w-4 text-center text-emerald-600"></i>
                        <span class="flex-1 font-extrabold">Volunteer Management</span>
                        <span class="px-1.5 py-0.5 text-[9px] font-bold rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200 mono">
                            <?= $pdo->query("SELECT COUNT(*) FROM volunteers")->fetchColumn(); ?>
                        </span>
                    </a>

                    <a href="tasks.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all <?= ($currentScript === 'tasks.php') ? 'bg-blue-50 text-blue-900 border-l-4 border-[#1d63d8] shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                        <i class="fa-solid fa-list-check text-sm w-4 text-center text-[#1d63d8]"></i>
                        <span class="flex-1 font-extrabold">Relief Missions</span>
                        <span class="px-1.5 py-0.5 text-[9px] font-bold rounded-full bg-blue-100 text-blue-800 border border-blue-200 mono">
                            <?= $pdo->query("SELECT COUNT(*) FROM volunteer_tasks WHERE status = 'Open'")->fetchColumn(); ?> Open
                        </span>
                    </a>

                    <a href="relief.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all <?= ($currentScript === 'relief.php') ? 'bg-teal-50 text-teal-900 border-l-4 border-teal-600 shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                        <i class="fa-solid fa-truck-ramp-box text-sm w-4 text-center text-teal-600"></i>
                        <span class="flex-1 font-extrabold">Relief Aid Ledger</span>
                    </a>
                </nav>
            </div>
        <?php endif; ?>

        <!-- Disasters Operations -->
        <?php if ($hasDisasters || $isSuperAdmin): ?>
            <div>
                <div class="px-3 mb-1.5 text-[10px] font-black uppercase tracking-wider text-slate-500 flex items-center justify-between mono">
                    <span>Disaster Incidents</span>
                    <i class="fa-solid fa-triangle-exclamation text-[10px] text-amber-600"></i>
                </div>
                <nav class="space-y-1">
                    <a href="disasters.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all <?= ($currentScript === 'disasters.php') ? 'bg-amber-50 text-amber-900 border-l-4 border-amber-600 shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                        <i class="fa-solid fa-tornado text-sm w-4 text-center text-amber-600"></i>
                        <span class="flex-1 font-extrabold">Disaster Declarations</span>
                        <span class="px-1.5 py-0.5 text-[9px] font-bold rounded-full bg-amber-100 text-amber-800 border border-amber-200 mono">
                            <?= $pdo->query("SELECT COUNT(*) FROM disasters WHERE status = 'Active'")->fetchColumn(); ?> Live
                        </span>
                    </a>
                </nav>
            </div>
        <?php endif; ?>

        <!-- Administration & Control -->
        <?php if ($hasUsers || $hasRoles || $hasLogs || $isSuperAdmin): ?>
            <div>
                <div class="px-3 mb-1.5 text-[10px] font-black uppercase tracking-wider text-slate-500 flex items-center justify-between mono">
                    <span>System Administration</span>
                    <i class="fa-solid fa-crown text-[10px] text-purple-600"></i>
                </div>
                <nav class="space-y-1">
                    <?php if ($hasUsers || $isSuperAdmin): ?>
                        <a href="users.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all <?= ($currentScript === 'users.php') ? 'bg-blue-50 text-[#1d4ed8] border-l-4 border-[#1d63d8] shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                            <i class="fa-solid fa-users text-sm w-4 text-center text-[#1d63d8]"></i>
                            <span class="flex-1 font-extrabold">User Directory &amp; RBAC</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($hasRoles || $isSuperAdmin): ?>
                        <a href="roles.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all <?= ($currentScript === 'roles.php') ? 'bg-purple-50 text-purple-900 border-l-4 border-purple-600 shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                            <i class="fa-solid fa-shield-halved text-sm w-4 text-center text-purple-600"></i>
                            <span class="flex-1 font-extrabold">Roles &amp; Access Matrix</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($hasLogs || $isSuperAdmin): ?>
                        <a href="activity_logs.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all <?= ($currentScript === 'activity_logs.php') ? 'bg-blue-50 text-blue-900 border-l-4 border-[#1d63d8] shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                            <i class="fa-solid fa-clock-rotate-left text-sm w-4 text-center text-blue-600"></i>
                            <span class="flex-1 font-extrabold">Audit Activity Logs</span>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>

    </div>

    <!-- Bottom User Status & Log Out Action -->
    <div class="p-3 border-t border-slate-200 bg-slate-50 shrink-0">
        <div class="p-2.5 bg-white border border-slate-200 flex items-center justify-between">
            <div class="flex items-center gap-2.5 min-w-0">
                <img src="<?= htmlspecialchars($currentUser['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($currentUser['name'] ?? 'User') . '&background=1d63d8&color=fff') ?>" 
                     alt="Avatar" class="w-8 h-8 object-cover border border-slate-200 shrink-0">
                <div class="min-w-0">
                    <p class="text-xs font-black text-slate-900 truncate"><?= htmlspecialchars($currentUser['name'] ?? 'User') ?></p>
                    <p class="text-[10px] font-bold text-[var(--role-primary)] uppercase tracking-wider truncate mono"><?= htmlspecialchars($currentUser['role_name'] ?? 'User') ?></p>
                </div>
            </div>
            <a href="logout.php" class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-colors shrink-0" title="Sign Out">
                <i class="fa-solid fa-arrow-right-from-bracket text-xs"></i>
            </a>
        </div>
    </div>

</aside>
