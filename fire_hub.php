<?php
// fire_hub.php - DisasterSafe Fire & Rescue Department Command Hub (Government Theme)
define('PAGE_TITLE', 'Fire & Rescue Department');
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

$csrfToken = generateCsrfToken();
$agencyType = 'Fire';

// Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token. Please refresh and retry.');
        header("Location: fire_hub.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    // 1. UPDATE TEAM STATUS
    if ($action === 'update_team_status') {
        $teamId = (int)($_POST['team_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? 'Available');
        $currentTask = trim($_POST['current_task'] ?? '');

        $stmt = $pdo->prepare("UPDATE agency_teams SET status = :status, current_task = :current_task WHERE id = :id AND agency_type = 'Fire'");
        $stmt->execute([':status' => $newStatus, ':current_task' => $currentTask, ':id' => $teamId]);
        logActivity($pdo, 'FIRE_TEAM_STATUS_UPDATED', "Fire & Rescue squad #{$teamId} status changed to {$newStatus}");
        setFlash('success', "Fire squad status updated to {$newStatus}.");
        header("Location: fire_hub.php");
        exit;
    }

    // 2. CREATE / ASSIGN NEW FIRE TASK
    if ($action === 'create_task') {
        $title = trim($_POST['title'] ?? '');
        $priority = trim($_POST['priority'] ?? 'High');
        $location = trim($_POST['location'] ?? '');
        $assignedTeam = trim($_POST['assigned_team'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($title && $location) {
            $stmt = $pdo->prepare("INSERT INTO agency_tasks (agency_type, title, priority, location, assigned_team, status, description) VALUES ('Fire', :title, :priority, :location, :assigned_team, 'In Progress', :description)");
            $stmt->execute([':title' => $title, ':priority' => $priority, ':location' => $location, ':assigned_team' => $assignedTeam, ':description' => $description]);
            logActivity($pdo, 'FIRE_TASK_DISPATCHED', "New Fire & Hazmat mission '{$title}' assigned to {$assignedTeam} at {$location}");
            setFlash('success', "Fire & Rescue mission dispatched successfully.");
        } else {
            setFlash('error', 'Please fill in Mission Title and Target Location.');
        }
        header("Location: fire_hub.php");
        exit;
    }

    // 3. UPDATE TASK STATUS
    if ($action === 'update_task_status') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $status = trim($_POST['status'] ?? 'In Progress');
        $pdo->prepare("UPDATE agency_tasks SET status = ? WHERE id = ? AND agency_type = 'Fire'")->execute([$status, $taskId]);
        logActivity($pdo, 'FIRE_TASK_STATUS_UPDATED', "Fire mission #{$taskId} set to {$status}");
        setFlash('success', "Fire mission status updated to {$status}.");
        header("Location: fire_hub.php");
        exit;
    }

    // 4. ALLOCATE RESOURCE
    if ($action === 'allocate_resource') {
        $resId = (int)($_POST['resource_id'] ?? 0);
        $qty = (int)($_POST['quantity'] ?? 0);
        
        $res = $pdo->prepare("SELECT * FROM agency_resources WHERE id = ? AND agency_type = 'Fire'");
        $res->execute([$resId]);
        $resource = $res->fetch();

        if ($resource && $qty > 0 && $qty <= $resource['available_quantity']) {
            $newAvailable = $resource['available_quantity'] - $qty;
            $newAllocated = $resource['allocated_quantity'] + $qty;
            $status = ($newAvailable === 0) ? 'Depleted' : (($newAvailable < ($resource['total_quantity'] * 0.2)) ? 'Critical Low' : 'In Stock');

            $pdo->prepare("UPDATE agency_resources SET available_quantity = ?, allocated_quantity = ?, status = ? WHERE id = ?")
                ->execute([$newAvailable, $newAllocated, $status, $resId]);
            logActivity($pdo, 'FIRE_RESOURCE_ALLOCATED', "Allocated {$qty} {$resource['unit']} of {$resource['item_name']}");
            setFlash('success', "Allocated {$qty} {$resource['unit']} to suppression tenders.");
        } else {
            setFlash('error', 'Invalid quantity requested or insufficient stock.');
        }
        header("Location: fire_hub.php");
        exit;
    }

    // 5. DIRECT BROADCAST
    if ($action === 'broadcast_order') {
        $message = trim($_POST['message'] ?? '');
        if ($message) {
            logActivity($pdo, 'SUPERADMIN_FIRE_BROADCAST', "Superadmin broadcast order: '{$message}' to Fire & Rescue command");
            setFlash('success', "Emergency fire & hazmat directive broadcasted to all Fire Stations on VHF.");
        }
        header("Location: fire_hub.php");
        exit;
    }
}

// Fetch Fire Department Data
$stations = $pdo->query("SELECT * FROM agency_stations WHERE agency_type = 'Fire' ORDER BY id ASC")->fetchAll();
$teams = $pdo->query("SELECT t.*, s.station_name FROM agency_teams t LEFT JOIN agency_stations s ON t.station_id = s.id WHERE t.agency_type = 'Fire' ORDER BY t.id ASC")->fetchAll();
$tasks = $pdo->query("SELECT * FROM agency_tasks WHERE agency_type = 'Fire' ORDER BY id DESC")->fetchAll();
$resources = $pdo->query("SELECT r.*, s.station_name FROM agency_resources r LEFT JOIN agency_stations s ON r.station_id = s.id WHERE r.agency_type = 'Fire' ORDER BY r.id ASC")->fetchAll();

// Live Metrics
$totalStations = count($stations);
$activeSquads = count(array_filter($teams, fn($t) => $t['status'] !== 'Available'));
$availableSquads = count(array_filter($teams, fn($t) => $t['status'] === 'Available'));
$openTasks = count(array_filter($tasks, fn($t) => $t['status'] !== 'Completed'));
$totalPersonnel = array_sum(array_column($stations, 'personnel_count'));

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div class="flex-1 flex flex-col min-w-0 bg-[#f8fafc] min-h-screen overflow-y-auto">
    <?php require_once __DIR__ . '/navbar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto w-full">

        <!-- POINT OF CONTACT & DEPARTMENT BANNER -->
        <section class="bg-white p-5 sm:p-6 rounded-3xl border border-slate-200 relative overflow-hidden shadow-sm">
            <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-red-100/60 rounded-full blur-3xl pointer-events-none"></div>

            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 relative z-10">
                <!-- Left Details -->
                <div class="space-y-2">
                    <div class="flex items-center gap-2.5">
                        <span class="w-9 h-9 rounded-2xl bg-red-50 border border-red-200 flex items-center justify-center text-red-600 font-black text-base shadow-2xs">
                            🚒
                        </span>
                        <div>
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-red-700 mono">Emergency Services Agency</span>
                            <h2 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">Fire &amp; Rescue Department Command Hub</h2>
                        </div>
                    </div>
                    <p class="text-xs text-slate-600 max-w-2xl leading-relaxed font-medium">
                        Superadmin direct point of contact for chemical blaze suppression, hazardous material neutralization, structural rubble cutting, high-angle rescue, and industrial tender fleet deployment.
                    </p>
                </div>

                <!-- Right Point of Contact Card -->
                <div class="p-4 rounded-2xl bg-red-50/70 border border-red-200 flex flex-wrap sm:flex-nowrap items-center gap-4 shrink-0 shadow-2xs">
                    <img src="https://images.unsplash.com/photo-1522075469751-3a6694fb2f61?w=150&auto=format&fit=crop&q=80" alt="Chief" class="w-12 h-12 rounded-2xl object-cover border border-red-200 shrink-0">
                    <div class="text-xs space-y-1">
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mono">Department Chief In-Charge</span>
                        <h4 class="font-extrabold text-slate-900 text-sm">Chief Thomas Sterling (Fire Chief)</h4>
                        <div class="flex flex-wrap items-center gap-3 pt-0.5 text-[11px]">
                            <a href="tel:101" class="text-red-700 hover:underline font-mono font-bold flex items-center gap-1">
                                <i class="fa-solid fa-phone text-[10px]"></i> 101 / +91 11 2341 2222
                            </a>
                            <span class="text-slate-600 font-mono font-semibold flex items-center gap-1">
                                <i class="fa-solid fa-walkie-talkie text-orange-600 text-[10px]"></i> VHF Fire Ch-1 (156.40 MHz)
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Direct Tactical Broadcast Bar -->
            <form method="POST" action="fire_hub.php" class="mt-5 pt-4 border-t border-slate-100 flex flex-col sm:flex-row items-center gap-3">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="broadcast_order">
                <div class="relative flex-1 w-full">
                    <i class="fa-solid fa-tower-broadcast absolute left-3.5 top-3 text-red-600 text-xs"></i>
                    <input type="text" name="message" required placeholder="Transmit direct priority order to all Fire Stations and Hazmat response units..." class="w-full pl-9 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-2xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-red-600 focus:bg-white font-medium">
                </div>
                <button type="submit" class="w-full sm:w-auto px-5 py-2.5 rounded-2xl bg-[#dc2626] hover:bg-[#b91c1c] text-white font-bold text-xs shadow-2xs transition-all shrink-0 flex items-center justify-center gap-1.5 cursor-pointer">
                    <i class="fa-solid fa-paper-plane"></i> Broadcast Order
                </button>
            </form>
        </section>

        <!-- KPI METRICS HUD WITH ACCENT CONTRAST -->
        <section class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="bg-white border border-slate-200 border-l-4 border-l-red-600 p-3.5 rounded-2xl shadow-2xs flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider mono">Fire Stations</p>
                    <h3 class="text-2xl font-black text-slate-900 mt-0.5"><?= $totalStations ?></h3>
                    <span class="text-[10px] font-bold text-red-700 mono">NCR Fire Depots</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-red-50 border border-red-200 flex items-center justify-center text-red-600 text-sm">
                    <i class="fa-solid fa-fire-extinguisher"></i>
                </div>
            </div>

            <div class="bg-white border border-slate-200 border-l-4 border-l-amber-600 p-3.5 rounded-2xl shadow-2xs flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-extrabold text-amber-800 uppercase tracking-wider mono">Active Engines</p>
                    <h3 class="text-2xl font-black text-amber-700 mt-0.5"><?= count($teams) ?></h3>
                    <span class="text-[10px] font-bold text-slate-500 mono"><?= $activeSquads ?> On-Scene • <?= $availableSquads ?> Standby</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-600 text-sm">
                    <i class="fa-solid fa-truck-droplet"></i>
                </div>
            </div>

            <div class="bg-white border border-slate-200 border-l-4 border-l-rose-600 p-3.5 rounded-2xl shadow-2xs flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-extrabold text-rose-800 uppercase tracking-wider mono">Fire Missions</p>
                    <h3 class="text-2xl font-black text-rose-700 mt-0.5"><?= $openTasks ?></h3>
                    <span class="text-[10px] font-bold text-rose-700 mono">Hazmat &amp; Suppression</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-rose-100 border border-rose-300 flex items-center justify-center text-rose-700 text-sm">
                    <i class="fa-solid fa-fire"></i>
                </div>
            </div>

            <div class="bg-white border border-slate-200 border-l-4 border-l-emerald-600 p-3.5 rounded-2xl shadow-2xs flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-extrabold text-emerald-800 uppercase tracking-wider mono">Firefighters</p>
                    <h3 class="text-2xl font-black text-emerald-700 mt-0.5"><?= $totalPersonnel ?></h3>
                    <span class="text-[10px] font-bold text-emerald-700 mono">SCBA Qualified</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-600 text-sm">
                    <i class="fa-solid fa-person-military-pointing"></i>
                </div>
            </div>
        </section>

        <!-- MAP & STATIONS GRID -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            
            <!-- Dedicated Fire Operations Radar Map (Left Column) -->
            <div class="lg:col-span-7 bg-white p-4 rounded-3xl border border-slate-200 flex flex-col shadow-xs min-h-[380px]">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-3">
                    <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-map-location-dot text-red-600"></i>
                        <span>Fire &amp; Hazmat GIS Radar</span>
                    </h3>
                    <span class="text-[10px] font-bold font-mono text-red-700 bg-red-50 px-2 py-0.5 rounded-full border border-red-200">
                        <?= count($stations) ?> Stations • <?= count($teams) ?> Engines
                    </span>
                </div>
                <div id="fireMap" class="flex-1 w-full rounded-2xl overflow-hidden min-h-[300px] border border-slate-200 bg-slate-100"></div>
            </div>

            <!-- Fire Stations Directory (Right Column) -->
            <div class="lg:col-span-5 bg-white p-4 rounded-3xl border border-slate-200 flex flex-col shadow-xs">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-3">
                    <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-fire-flame-curved text-red-600"></i>
                        <span>Fire Stations &amp; Hazmat Depots</span>
                    </h3>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mono">Directory</span>
                </div>

                <div class="space-y-2.5 overflow-y-auto max-h-[320px] pr-1">
                    <?php foreach ($stations as $st): ?>
                        <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200 hover:border-red-300 transition-all text-xs space-y-1.5">
                            <div class="flex items-center justify-between">
                                <h4 class="font-extrabold text-slate-900 text-xs flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full <?= $st['status'] === 'Operational' ? 'bg-emerald-500' : 'bg-red-500 animate-pulse' ?>"></span>
                                    <?= htmlspecialchars($st['station_name']) ?>
                                </h4>
                                <span class="text-[9px] font-bold px-2 py-0.5 rounded-full <?= $st['status'] === 'Operational' ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-red-100 text-red-800 border border-red-200' ?> mono">
                                    <?= htmlspecialchars($st['status']) ?>
                                </span>
                            </div>
                            <p class="text-slate-600 text-[11px] font-medium"><?= htmlspecialchars($st['address']) ?></p>
                            <div class="flex flex-wrap items-center justify-between gap-2 pt-1 border-t border-slate-200 text-[10px] text-slate-700 font-mono">
                                <span><i class="fa-solid fa-user-tie text-orange-600 mr-1"></i> <?= htmlspecialchars($st['commander_name']) ?></span>
                                <a href="tel:<?= urlencode($st['contact_phone']) ?>" class="text-red-700 hover:underline font-bold">
                                    <i class="fa-solid fa-phone text-[9px]"></i> <?= htmlspecialchars($st['contact_phone']) ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

        <!-- TEAMS & OPERATIONAL SQUADS ROSTER -->
        <section class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-truck-fire text-red-600"></i>
                        <span>Fire Tenders &amp; Heavy Extrication Units</span>
                    </h3>
                    <p class="text-xs text-slate-500 font-medium">Active status of foam tenders, ladder units, hydraulic cutters, and hazmat extraction squads.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3.5">
                <?php foreach ($teams as $tm): ?>
                    <?php 
                        $statusClass = match($tm['status']) {
                            'On-Scene' => 'bg-red-50 text-red-800 border-red-200',
                            'Dispatched' => 'bg-amber-50 text-amber-800 border-amber-200',
                            'Available' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                            default => 'bg-slate-100 text-slate-700 border-slate-200'
                        };
                    ?>
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 hover:border-red-300 transition-all text-xs space-y-2.5">
                        <div class="flex items-center justify-between">
                            <span class="font-extrabold text-slate-900 text-sm flex items-center gap-1.5">
                                <i class="fa-solid fa-fire-extinguisher text-red-600 text-xs"></i>
                                <?= htmlspecialchars($tm['callsign']) ?>
                            </span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border <?= $statusClass ?> mono">
                                <?= htmlspecialchars($tm['status']) ?>
                            </span>
                        </div>

                        <div class="space-y-1 text-slate-700 text-[11px] font-medium">
                            <p><b>Officer-in-Charge:</b> <?= htmlspecialchars($tm['team_lead']) ?> (<b><?= $tm['members_count'] ?> Crew</b>)</p>
                            <p class="text-slate-500"><b>Apparatus:</b> <?= htmlspecialchars($tm['vehicle_equipment']) ?></p>
                            <p class="text-red-700 font-semibold"><b>Task:</b> <?= htmlspecialchars($tm['current_task'] ?: 'Station Standby') ?></p>
                        </div>

                        <!-- Status Action Form -->
                        <form method="POST" action="fire_hub.php" class="pt-2 border-t border-slate-200 flex items-center justify-between gap-2">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="action" value="update_team_status">
                            <input type="hidden" name="team_id" value="<?= $tm['id'] ?>">
                            <input type="hidden" name="current_task" value="<?= htmlspecialchars($tm['current_task']) ?>">

                            <select name="status" onchange="this.form.submit()" class="w-full px-2.5 py-1.5 bg-white border border-slate-200 rounded-xl text-slate-800 text-[10px] font-bold focus:outline-none focus:border-red-600">
                                <option value="Available" <?= $tm['status'] === 'Available' ? 'selected' : '' ?>>🟢 Available</option>
                                <option value="Dispatched" <?= $tm['status'] === 'Dispatched' ? 'selected' : '' ?>>🔵 Dispatched</option>
                                <option value="On-Scene" <?= $tm['status'] === 'On-Scene' ? 'selected' : '' ?>>🔴 On-Scene</option>
                                <option value="Standby" <?= $tm['status'] === 'Standby' ? 'selected' : '' ?>>🟡 Standby</option>
                            </select>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- TASKS QUEUE & RESOURCES INVENTORY -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            
            <!-- Fire Missions Queue (Left 7 Cols) -->
            <div class="lg:col-span-7 bg-white p-5 rounded-3xl border border-slate-200 shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                            <i class="fa-solid fa-list-check text-red-600"></i>
                            <span>Assigned Fire &amp; Hazmat Incidents</span>
                        </h3>
                    </div>
                    <button type="button" onclick="document.getElementById('fireTaskModal').classList.remove('hidden')" class="px-3.5 py-2 rounded-2xl bg-[#dc2626] hover:bg-[#b91c1c] text-white font-bold text-xs shadow-2xs transition-all flex items-center gap-1.5 cursor-pointer">
                        <i class="fa-solid fa-plus"></i> New Mission
                    </button>
                </div>

                <div class="space-y-3">
                    <?php foreach ($tasks as $tsk): ?>
                        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 hover:border-red-300 transition-all text-xs space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase <?= $tsk['priority'] === 'Critical' ? 'bg-red-100 text-red-800 border border-red-200' : 'bg-amber-100 text-amber-800 border border-amber-200' ?> mono">
                                        <?= htmlspecialchars($tsk['priority']) ?>
                                    </span>
                                    <h4 class="font-extrabold text-slate-900 text-xs"><?= htmlspecialchars($tsk['title']) ?></h4>
                                </div>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold <?= $tsk['status'] === 'Completed' ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-red-100 text-red-800 border border-red-200' ?> mono">
                                    <?= htmlspecialchars($tsk['status']) ?>
                                </span>
                            </div>

                            <p class="text-slate-600 text-[11px] font-medium"><?= htmlspecialchars($tsk['description']) ?></p>
                            
                            <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-slate-200 text-[11px]">
                                <span class="text-slate-500"><i class="fa-solid fa-location-dot text-red-500 mr-1"></i> <?= htmlspecialchars($tsk['location']) ?></span>
                                <span class="text-red-700 font-bold"><i class="fa-solid fa-fire-extinguisher mr-1"></i> <?= htmlspecialchars($tsk['assigned_team'] ?: 'Unassigned') ?></span>
                                
                                <form method="POST" action="fire_hub.php" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="action" value="update_task_status">
                                    <input type="hidden" name="task_id" value="<?= $tsk['id'] ?>">
                                    <?php if ($tsk['status'] !== 'Completed'): ?>
                                        <button type="submit" name="status" value="Completed" class="px-2.5 py-1 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[10px] cursor-pointer">
                                            <i class="fa-solid fa-check mr-1"></i> Mark Completed
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" name="status" value="In Progress" class="px-2.5 py-1 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-800 text-[10px] font-bold cursor-pointer">
                                            Re-open
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Fire Equipment & Capacity Inventory (Right 5 Cols) -->
            <div class="lg:col-span-5 bg-white p-5 rounded-3xl border border-slate-200 shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-boxes-stacked text-red-600"></i>
                        <span>Fire Logistics &amp; Extrication Gear</span>
                    </h3>
                </div>

                <div class="space-y-3">
                    <?php foreach ($resources as $res): ?>
                        <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 text-xs space-y-2">
                            <div class="flex items-center justify-between">
                                <h4 class="font-extrabold text-slate-900 text-xs"><?= htmlspecialchars($res['item_name']) ?></h4>
                                <span class="px-2 py-0.5 rounded-full text-[9px] font-bold <?= $res['status'] === 'In Stock' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' ?> mono">
                                    <?= htmlspecialchars($res['status']) ?>
                                </span>
                            </div>

                            <div class="flex items-center justify-between text-[11px] text-slate-700 font-medium">
                                <span>Available: <strong class="text-red-700"><?= $res['available_quantity'] ?> <?= htmlspecialchars($res['unit']) ?></strong></span>
                                <span>Allocated: <strong class="text-amber-700"><?= $res['allocated_quantity'] ?></strong> / <?= $res['total_quantity'] ?></span>
                            </div>

                            <!-- Progress Bar -->
                            <?php $percent = $res['total_quantity'] > 0 ? round(($res['available_quantity'] / $res['total_quantity']) * 100) : 0; ?>
                            <div class="w-full bg-slate-200 rounded-full h-1.5 overflow-hidden">
                                <div class="bg-[#dc2626] h-1.5 rounded-full" style="width: <?= $percent ?>%"></div>
                            </div>

                            <!-- Quick Dispatch Allocation Form -->
                            <form method="POST" action="fire_hub.php" class="pt-1.5 flex items-center gap-2">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="allocate_resource">
                                <input type="hidden" name="resource_id" value="<?= $res['id'] ?>">
                                <input type="number" name="quantity" min="1" max="<?= $res['available_quantity'] ?>" value="2" class="w-16 px-2 py-1 bg-white border border-slate-200 rounded-xl text-slate-900 text-[10px] font-bold text-center">
                                <button type="submit" <?= $res['available_quantity'] <= 0 ? 'disabled' : '' ?> class="flex-1 py-1 rounded-xl bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 text-[10px] font-bold transition-all disabled:opacity-40 cursor-pointer">
                                    Deploy to Engine
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

    </main>
</div>

<!-- CREATE FIRE TASK MODAL -->
<div id="fireTaskModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden">
        <div class="h-14 px-6 bg-white border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-fire-extinguisher text-red-600"></i> Dispatch Fire / Hazmat Mission
            </h3>
            <button type="button" onclick="document.getElementById('fireTaskModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-800 cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <form method="POST" action="fire_hub.php" class="p-6 space-y-3.5 text-xs">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="create_task">

            <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1 mono">Incident Title *</label>
                <input type="text" name="title" required placeholder="e.g. Sahibabad Industrial Solvent Hazmat Fire" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:bg-white focus:outline-none focus:border-red-600 font-medium">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1 mono">Priority</label>
                    <select name="priority" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-bold focus:bg-white focus:outline-none focus:border-red-600">
                        <option value="Critical">🔴 Critical (Code Red)</option>
                        <option value="High" selected>🟠 High</option>
                        <option value="Medium">🟡 Medium</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1 mono">Assign Engine Unit</label>
                    <select name="assigned_team" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-bold focus:bg-white focus:outline-none focus:border-red-600">
                        <?php foreach ($teams as $tm): ?>
                            <option value="<?= htmlspecialchars($tm['callsign']) ?>"><?= htmlspecialchars($tm['callsign']) ?> (<?= $tm['status'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1 mono">Target Location *</label>
                <input type="text" name="location" required placeholder="e.g. Sector 4 Sahibabad Industrial Area" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:bg-white focus:outline-none focus:border-red-600 font-medium">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1 mono">Suppression Directives &amp; Hazard Details</label>
                <textarea name="description" rows="3" placeholder="Specify chemical agents involved, water bowser requirements, thermal cameras..." class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 leading-relaxed focus:bg-white focus:outline-none focus:border-red-600 font-medium"></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('fireTaskModal').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-700 font-bold cursor-pointer">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-[#dc2626] hover:bg-[#b91c1c] text-white font-bold shadow-sm cursor-pointer">Dispatch Engine Unit</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const fMap = L.map('fireMap', { zoomControl: false, attributionControl: false }).setView([28.6139, 77.2090], 11);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(fMap);

    const stationsData = <?= json_encode($stations) ?>;

    stationsData.forEach(st => {
        L.circleMarker([st.gps_lat, st.gps_lng], {
            radius: 9,
            fillColor: '#dc2626',
            color: '#ffffff',
            weight: 2,
            fillOpacity: 0.95
        }).addTo(fMap)
        .bindPopup(`
            <div style="color:#0f172a; font-family:'Inter', sans-serif; font-size:12px; min-width:180px;">
                <strong style="color:#dc2626;">🚒 ${st.station_name}</strong><br/>
                <b>Zone:</b> ${st.zone_name}<br/>
                <span>Commander: <b>${st.commander_name}</b></span><br/>
                <span>Engines: <b>${st.vehicles_count}</b> • Crew: <b>${st.personnel_count}</b></span><br/>
                <span style="color:#64748b; font-size:10px;">Radio: ${st.radio_channel}</span>
            </div>
        `);
    });
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
