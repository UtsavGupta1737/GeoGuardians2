<?php
// police_hub.php - DisasterSafe Police Department Command Hub (Government Theme)
define('PAGE_TITLE', 'Police Department');
require_once __DIR__ . '/auth.php';

requireLogin();
$currentUser = getCurrentUser($pdo);

// Role & Permission Checks
$isSuperAdmin = isSuperAdmin($currentUser);
$hasPoliceAccess = $isSuperAdmin || hasPermission($currentUser, 'access_police');
if (!$hasPoliceAccess) {
    setFlash('error', 'Access denied. You do not have permission to view Police Department Operations.');
    header("Location: dashboard.php");
    exit;
}

$csrfToken = generateCsrfToken();
$agencyType = 'Police';

// Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token. Please refresh and retry.');
        header("Location: police_hub.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    // 1. UPDATE TEAM STATUS
    if ($action === 'update_team_status') {
        $teamId = (int)($_POST['team_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? 'Available');
        $currentTask = trim($_POST['current_task'] ?? '');

        $stmt = $pdo->prepare("UPDATE agency_teams SET status = :status, current_task = :current_task WHERE id = :id AND agency_type = 'Police'");
        $stmt->execute([':status' => $newStatus, ':current_task' => $currentTask, ':id' => $teamId]);
        logActivity($pdo, 'POLICE_TEAM_STATUS_UPDATED', "Police squad #{$teamId} status changed to {$newStatus}");
        setFlash('success', "Police team status updated to {$newStatus}.");
        header("Location: police_hub.php");
        exit;
    }

    // 2. CREATE / ASSIGN NEW POLICE TASK
    if ($action === 'create_task') {
        $title = trim($_POST['title'] ?? '');
        $priority = trim($_POST['priority'] ?? 'High');
        $location = trim($_POST['location'] ?? '');
        $assignedTeam = trim($_POST['assigned_team'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($title && $location) {
            $stmt = $pdo->prepare("INSERT INTO agency_tasks (agency_type, title, priority, location, assigned_team, status, description) VALUES ('Police', :title, :priority, :location, :assigned_team, 'In Progress', :description)");
            $stmt->execute([':title' => $title, ':priority' => $priority, ':location' => $location, ':assigned_team' => $assignedTeam, ':description' => $description]);
            logActivity($pdo, 'POLICE_TASK_DISPATCHED', "New Police mission '{$title}' assigned to {$assignedTeam} at {$location}");
            setFlash('success', "Police mission dispatched successfully.");
        } else {
            setFlash('error', 'Please fill in Mission Title and Target Location.');
        }
        header("Location: police_hub.php");
        exit;
    }

    // 3. UPDATE TASK STATUS
    if ($action === 'update_task_status') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $status = trim($_POST['status'] ?? 'In Progress');
        $pdo->prepare("UPDATE agency_tasks SET status = ? WHERE id = ? AND agency_type = 'Police'")->execute([$status, $taskId]);
        logActivity($pdo, 'POLICE_TASK_STATUS_UPDATED', "Police mission #{$taskId} set to {$status}");
        setFlash('success', "Police mission status updated to {$status}.");
        header("Location: police_hub.php");
        exit;
    }

    // 4. ALLOCATE RESOURCE
    if ($action === 'allocate_resource') {
        $resId = (int)($_POST['resource_id'] ?? 0);
        $qty = (int)($_POST['quantity'] ?? 0);
        
        $res = $pdo->prepare("SELECT * FROM agency_resources WHERE id = ? AND agency_type = 'Police'");
        $res->execute([$resId]);
        $resource = $res->fetch();

        if ($resource && $qty > 0 && $qty <= $resource['available_quantity']) {
            $newAvailable = $resource['available_quantity'] - $qty;
            $newAllocated = $resource['allocated_quantity'] + $qty;
            $status = ($newAvailable === 0) ? 'Depleted' : (($newAvailable < ($resource['total_quantity'] * 0.2)) ? 'Critical Low' : 'In Stock');

            $pdo->prepare("UPDATE agency_resources SET available_quantity = ?, allocated_quantity = ?, status = ? WHERE id = ?")
                ->execute([$newAvailable, $newAllocated, $status, $resId]);
            logActivity($pdo, 'POLICE_RESOURCE_ALLOCATED', "Allocated {$qty} {$resource['unit']} of {$resource['item_name']}");
            setFlash('success', "Allocated {$qty} {$resource['unit']} to field squads.");
        } else {
            setFlash('error', 'Invalid quantity requested or insufficient stock.');
        }
        header("Location: police_hub.php");
        exit;
    }

    // 5. DIRECT BROADCAST
    if ($action === 'broadcast_order') {
        $message = trim($_POST['message'] ?? '');
        if ($message) {
            logActivity($pdo, 'SUPERADMIN_POLICE_BROADCAST', "Superadmin broadcast order: '{$message}' to all Police channels");
            setFlash('success', "Tactical priority order broadcasted to all Police stations & patrol units on VHF.");
        }
        header("Location: police_hub.php");
        exit;
    }
}

// Fetch Police Department Data
$stations = $pdo->query("SELECT * FROM agency_stations WHERE agency_type = 'Police' ORDER BY id ASC")->fetchAll();
$teams = $pdo->query("SELECT t.*, s.station_name FROM agency_teams t LEFT JOIN agency_stations s ON t.station_id = s.id WHERE t.agency_type = 'Police' ORDER BY t.id ASC")->fetchAll();
$tasks = $pdo->query("SELECT * FROM agency_tasks WHERE agency_type = 'Police' ORDER BY id DESC")->fetchAll();
$resources = $pdo->query("SELECT r.*, s.station_name FROM agency_resources r LEFT JOIN agency_stations s ON r.station_id = s.id WHERE r.agency_type = 'Police' ORDER BY r.id ASC")->fetchAll();

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
            <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-blue-100/60 rounded-full blur-3xl pointer-events-none"></div>

            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 relative z-10">
                <!-- Left Details -->
                <div class="space-y-2">
                    <div class="flex items-center gap-2.5">
                        <span class="w-9 h-9 rounded-2xl bg-blue-50 border border-blue-200 flex items-center justify-center text-[#1d63d8] font-black text-base shadow-2xs">
                            <i class="fa-solid fa-shield-halved"></i>
                        </span>
                        <div>
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-blue-700 mono">Emergency Services Agency</span>
                            <h2 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">Police Department Command Hub</h2>
                        </div>
                    </div>
                    <p class="text-xs text-slate-600 max-w-2xl leading-relaxed font-medium">
                        Superadmin direct point of contact for law enforcement operations, highway flood cordons, evacuation security, perimeter control, and mobile patrol dispatch across Delhi-NCR.
                    </p>
                </div>

                <!-- Right Point of Contact Card -->
                <div class="p-4 rounded-2xl bg-blue-50/70 border border-blue-200 flex flex-wrap sm:flex-nowrap items-center gap-4 shrink-0 shadow-2xs">
                    <img src="https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=150&auto=format&fit=crop&q=80" alt="Commander" class="w-12 h-12 rounded-2xl object-cover border border-blue-200 shrink-0">
                    <div class="text-xs space-y-1">
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mono">Department Commander In-Charge</span>
                        <h4 class="font-extrabold text-slate-900 text-sm">Capt. Marcus Vance (Joint CP)</h4>
                        <div class="flex flex-wrap items-center gap-3 pt-0.5 text-[11px]">
                            <a href="tel:112" class="text-[#1d63d8] hover:underline font-mono font-bold flex items-center gap-1">
                                <i class="fa-solid fa-phone text-[10px]"></i> 112 / +91 11 2341 0100
                            </a>
                            <span class="text-slate-600 font-mono font-semibold flex items-center gap-1">
                                <i class="fa-solid fa-walkie-talkie text-blue-600 text-[10px]"></i> VHF Ch-4 (154.80 MHz)
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Direct Tactical Broadcast Bar (Superadmin Point of Contact Action) -->
            <form method="POST" action="police_hub.php" class="mt-5 pt-4 border-t border-slate-100 flex flex-col sm:flex-row items-center gap-3">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="broadcast_order">
                <div class="relative flex-1 w-full">
                    <i class="fa-solid fa-tower-broadcast absolute left-3.5 top-3 text-[#1d63d8] text-xs"></i>
                    <input type="text" name="message" required placeholder="Transmit direct priority tactical directive to all Police precinct control rooms..." class="w-full pl-9 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-2xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-[#1d63d8] focus:bg-white font-medium">
                </div>
                <button type="submit" class="w-full sm:w-auto px-5 py-2.5 rounded-2xl bg-[#1d63d8] hover:bg-[#1553c7] text-white font-bold text-xs shadow-2xs transition-all shrink-0 flex items-center justify-center gap-1.5 cursor-pointer">
                    <i class="fa-solid fa-paper-plane"></i> Broadcast Order
                </button>
            </form>
        </section>

        <!-- KPI METRICS HUD WITH ACCENT CONTRAST -->
        <section class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="bg-white border border-slate-200 border-l-4 border-l-blue-600 p-3.5 rounded-2xl shadow-2xs flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider mono">Active Stations</p>
                    <h3 class="text-2xl font-black text-slate-900 mt-0.5"><?= $totalStations ?></h3>
                    <span class="text-[10px] font-bold text-blue-700 mono">Delhi-NCR Precincts</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-blue-50 border border-blue-200 flex items-center justify-center text-blue-600 text-sm">
                    <i class="fa-solid fa-building-shield"></i>
                </div>
            </div>

            <div class="bg-white border border-slate-200 border-l-4 border-l-indigo-600 p-3.5 rounded-2xl shadow-2xs flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-extrabold text-indigo-800 uppercase tracking-wider mono">Field Squads</p>
                    <h3 class="text-2xl font-black text-indigo-700 mt-0.5"><?= count($teams) ?></h3>
                    <span class="text-[10px] font-bold text-slate-500 mono"><?= $activeSquads ?> Active • <?= $availableSquads ?> Standby</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-indigo-50 border border-indigo-200 flex items-center justify-center text-indigo-600 text-sm">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
            </div>

            <div class="bg-white border border-slate-200 border-l-4 border-l-amber-600 p-3.5 rounded-2xl shadow-2xs flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-extrabold text-amber-800 uppercase tracking-wider mono">Active Missions</p>
                    <h3 class="text-2xl font-black text-amber-700 mt-0.5"><?= $openTasks ?></h3>
                    <span class="text-[10px] font-bold text-amber-700 mono">Cordons &amp; Escorts</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-600 text-sm">
                    <i class="fa-solid fa-list-check"></i>
                </div>
            </div>

            <div class="bg-white border border-slate-200 border-l-4 border-l-emerald-600 p-3.5 rounded-2xl shadow-2xs flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-extrabold text-emerald-800 uppercase tracking-wider mono">Total Officers</p>
                    <h3 class="text-2xl font-black text-emerald-700 mt-0.5"><?= number_format($totalPersonnel) ?></h3>
                    <span class="text-[10px] font-bold text-emerald-700 mono">On Roster Across Units</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-600 text-sm">
                    <i class="fa-solid fa-user-shield"></i>
                </div>
            </div>
        </section>

        <!-- MAP & STATIONS GRID -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            
            <!-- Dedicated Police Operations Radar Map (Left Column) -->
            <div class="lg:col-span-7 bg-white p-4 rounded-3xl border border-slate-200 flex flex-col shadow-xs min-h-[380px]">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-3">
                    <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-map-location-dot text-[#1d63d8]"></i>
                        <span>Police Operations GIS Radar</span>
                    </h3>
                    <span class="text-[10px] font-bold font-mono text-blue-700 bg-blue-50 px-2 py-0.5 rounded-full border border-blue-200">
                        <?= count($stations) ?> Stations • <?= count($teams) ?> Units
                    </span>
                </div>
                <div id="policeMap" class="flex-1 w-full rounded-2xl overflow-hidden min-h-[300px] border border-slate-200 bg-slate-100"></div>
            </div>

            <!-- Police Stations Directory (Right Column) -->
            <div class="lg:col-span-5 bg-white p-4 rounded-3xl border border-slate-200 flex flex-col shadow-xs">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-3">
                    <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-building-shield text-[#1d63d8]"></i>
                        <span>Precincts &amp; Stations</span>
                    </h3>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mono">Directory</span>
                </div>

                <div class="space-y-2.5 overflow-y-auto max-h-[320px] pr-1">
                    <?php foreach ($stations as $st): ?>
                        <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200 hover:border-blue-300 transition-all text-xs space-y-1.5">
                            <div class="flex items-center justify-between">
                                <h4 class="font-extrabold text-slate-900 text-xs flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full <?= $st['status'] === 'Operational' ? 'bg-emerald-500' : 'bg-amber-500 animate-pulse' ?>"></span>
                                    <?= htmlspecialchars($st['station_name']) ?>
                                </h4>
                                <span class="text-[9px] font-bold px-2 py-0.5 rounded-full <?= $st['status'] === 'Operational' ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-amber-100 text-amber-800 border border-amber-200' ?> mono">
                                    <?= htmlspecialchars($st['status']) ?>
                                </span>
                            </div>
                            <p class="text-slate-600 text-[11px] font-medium"><?= htmlspecialchars($st['address']) ?></p>
                            <div class="flex flex-wrap items-center justify-between gap-2 pt-1 border-t border-slate-200 text-[10px] text-slate-700 font-mono">
                                <span><i class="fa-solid fa-user-tie text-blue-600 mr-1"></i> <?= htmlspecialchars($st['commander_name']) ?></span>
                                <a href="tel:<?= urlencode($st['contact_phone']) ?>" class="text-blue-700 hover:underline font-bold">
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
                        <i class="fa-solid fa-users-line text-[#1d63d8]"></i>
                        <span>Police Patrol Squads &amp; Tactical Units</span>
                    </h3>
                    <p class="text-xs text-slate-500 font-medium">Active roster of field units, personnel strength, vehicle gear, and live deployment status.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3.5">
                <?php foreach ($teams as $tm): ?>
                    <?php 
                        $statusClass = match($tm['status']) {
                            'On-Scene' => 'bg-red-50 text-red-800 border-red-200',
                            'Dispatched' => 'bg-blue-50 text-blue-800 border-blue-200',
                            'Available' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                            default => 'bg-slate-100 text-slate-700 border-slate-200'
                        };
                    ?>
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 hover:border-blue-300 transition-all text-xs space-y-2.5">
                        <div class="flex items-center justify-between">
                            <span class="font-extrabold text-slate-900 text-sm flex items-center gap-1.5">
                                <i class="fa-solid fa-shield-halved text-[#1d63d8] text-xs"></i>
                                <?= htmlspecialchars($tm['callsign']) ?>
                            </span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border <?= $statusClass ?> mono">
                                <?= htmlspecialchars($tm['status']) ?>
                            </span>
                        </div>

                        <div class="space-y-1 text-slate-700 text-[11px] font-medium">
                            <p><b>Team Lead:</b> <?= htmlspecialchars($tm['team_lead']) ?> (<b><?= $tm['members_count'] ?> Officers</b>)</p>
                            <p class="text-slate-500"><b>Vehicle:</b> <?= htmlspecialchars($tm['vehicle_equipment']) ?></p>
                            <p class="text-blue-800 font-semibold"><b>Task:</b> <?= htmlspecialchars($tm['current_task'] ?: 'Standby in Sector') ?></p>
                        </div>

                        <!-- Status Action Form -->
                        <form method="POST" action="police_hub.php" class="pt-2 border-t border-slate-200 flex items-center justify-between gap-2">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="action" value="update_team_status">
                            <input type="hidden" name="team_id" value="<?= $tm['id'] ?>">
                            <input type="hidden" name="current_task" value="<?= htmlspecialchars($tm['current_task']) ?>">

                            <select name="status" onchange="this.form.submit()" class="w-full px-2.5 py-1.5 bg-white border border-slate-200 rounded-xl text-slate-800 text-[10px] font-bold focus:outline-none focus:border-[#1d63d8]">
                                <option value="Available" <?= $tm['status'] === 'Available' ? 'selected' : '' ?>>Available</option>
                                <option value="Dispatched" <?= $tm['status'] === 'Dispatched' ? 'selected' : '' ?>>Dispatched</option>
                                <option value="On-Scene" <?= $tm['status'] === 'On-Scene' ? 'selected' : '' ?>>On-Scene</option>
                                <option value="Standby" <?= $tm['status'] === 'Standby' ? 'selected' : '' ?>>Standby</option>
                            </select>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- TASKS QUEUE & RESOURCES INVENTORY -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            
            <!-- Police Missions Queue (Left 7 Cols) -->
            <div class="lg:col-span-7 bg-white p-5 rounded-3xl border border-slate-200 shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                            <i class="fa-solid fa-list-check text-[#1d63d8]"></i>
                            <span>Assigned Police Missions</span>
                        </h3>
                    </div>
                    <button type="button" onclick="document.getElementById('policeTaskModal').classList.remove('hidden')" class="px-3.5 py-2 rounded-2xl bg-[#1d63d8] hover:bg-[#1553c7] text-white font-bold text-xs shadow-2xs transition-all flex items-center gap-1.5 cursor-pointer">
                        <i class="fa-solid fa-plus"></i> New Mission
                    </button>
                </div>

                <div class="space-y-3">
                    <?php foreach ($tasks as $tsk): ?>
                        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 hover:border-blue-300 transition-all text-xs space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase <?= $tsk['priority'] === 'Critical' ? 'bg-red-100 text-red-800 border border-red-200' : 'bg-amber-100 text-amber-800 border border-amber-200' ?> mono">
                                        <?= htmlspecialchars($tsk['priority']) ?>
                                    </span>
                                    <h4 class="font-extrabold text-slate-900 text-xs"><?= htmlspecialchars($tsk['title']) ?></h4>
                                </div>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold <?= $tsk['status'] === 'Completed' ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-blue-100 text-blue-800 border border-blue-200' ?> mono">
                                    <?= htmlspecialchars($tsk['status']) ?>
                                </span>
                            </div>

                            <p class="text-slate-600 text-[11px] font-medium"><?= htmlspecialchars($tsk['description']) ?></p>
                            
                            <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-slate-200 text-[11px]">
                                <span class="text-slate-500"><i class="fa-solid fa-location-dot text-red-500 mr-1"></i> <?= htmlspecialchars($tsk['location']) ?></span>
                                <span class="text-blue-700 font-bold"><i class="fa-solid fa-shield-halved mr-1"></i> <?= htmlspecialchars($tsk['assigned_team'] ?: 'Unassigned') ?></span>
                                
                                <form method="POST" action="police_hub.php" class="inline">
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

            <!-- Police Equipment & Capacity Inventory (Right 5 Cols) -->
            <div class="lg:col-span-5 bg-white p-5 rounded-3xl border border-slate-200 shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-boxes-stacked text-[#1d63d8]"></i>
                        <span>Police Logistics &amp; Inventory</span>
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
                                <span>Available: <strong class="text-blue-700"><?= $res['available_quantity'] ?> <?= htmlspecialchars($res['unit']) ?></strong></span>
                                <span>Deployed: <strong class="text-amber-700"><?= $res['allocated_quantity'] ?></strong> / <?= $res['total_quantity'] ?></span>
                            </div>

                            <!-- Progress Bar -->
                            <?php $percent = $res['total_quantity'] > 0 ? round(($res['available_quantity'] / $res['total_quantity']) * 100) : 0; ?>
                            <div class="w-full bg-slate-200 rounded-full h-1.5 overflow-hidden">
                                <div class="bg-[#1d63d8] h-1.5 rounded-full" style="width: <?= $percent ?>%"></div>
                            </div>

                            <!-- Quick Dispatch Allocation Form -->
                            <form method="POST" action="police_hub.php" class="pt-1.5 flex items-center gap-2">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="allocate_resource">
                                <input type="hidden" name="resource_id" value="<?= $res['id'] ?>">
                                <input type="number" name="quantity" min="1" max="<?= $res['available_quantity'] ?>" value="5" class="w-16 px-2 py-1 bg-white border border-slate-200 rounded-xl text-slate-900 text-[10px] font-bold text-center">
                                <button type="submit" <?= $res['available_quantity'] <= 0 ? 'disabled' : '' ?> class="flex-1 py-1 rounded-xl bg-blue-50 hover:bg-blue-100 text-[#1d63d8] border border-blue-200 text-[10px] font-bold transition-all disabled:opacity-40 cursor-pointer">
                                    Deploy to Squads
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

    </main>
</div>

<!-- CREATE POLICE TASK MODAL -->
<div id="policeTaskModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden">
        <div class="h-14 px-6 bg-white border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-shield-halved text-[#1d63d8]"></i> Dispatch New Police Mission
            </h3>
            <button type="button" onclick="document.getElementById('policeTaskModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-800 cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <form method="POST" action="police_hub.php" class="p-6 space-y-3.5 text-xs">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="create_task">

            <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1 mono">Mission Title *</label>
                <input type="text" name="title" required placeholder="e.g. Geeta Colony Cordon &amp; Bridge Closure" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:bg-white focus:outline-none focus:border-[#1d63d8] font-medium">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1 mono">Priority</label>
                    <select name="priority" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-bold focus:bg-white focus:outline-none focus:border-[#1d63d8]">
                        <option value="Critical">Critical</option>
                        <option value="High" selected>High</option>
                        <option value="Medium">Medium</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1 mono">Assign Unit</label>
                    <select name="assigned_team" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-bold focus:bg-white focus:outline-none focus:border-[#1d63d8]">
                        <?php foreach ($teams as $tm): ?>
                            <option value="<?= htmlspecialchars($tm['callsign']) ?>"><?= htmlspecialchars($tm['callsign']) ?> (<?= $tm['status'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1 mono">Target Location *</label>
                <input type="text" name="location" required placeholder="e.g. ITO Ring Road Intersection" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:bg-white focus:outline-none focus:border-[#1d63d8] font-medium">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1 mono">Mission Directives &amp; Details</label>
                <textarea name="description" rows="3" placeholder="Describe tactical objectives, vehicle access cordons, or radio frequencies..." class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 leading-relaxed focus:bg-white focus:outline-none focus:border-[#1d63d8] font-medium"></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('policeTaskModal').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-700 font-bold cursor-pointer">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-[#1d63d8] hover:bg-[#1553c7] text-white font-bold shadow-sm cursor-pointer">Dispatch Police Unit</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const pMap = L.map('policeMap', { zoomControl: false, attributionControl: false }).setView([28.6139, 77.2090], 11);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(pMap);

    const stationsData = <?= json_encode($stations) ?>;
    const teamsData = <?= json_encode($teams) ?>;

    // Stations Pins
    stationsData.forEach(st => {
        L.circleMarker([st.gps_lat, st.gps_lng], {
            radius: 9,
            fillColor: '#2563eb',
            color: '#ffffff',
            weight: 2,
            fillOpacity: 0.95
        }).addTo(pMap)
        .bindPopup(`
            <div style="color:#0f172a; font-family:'Inter', sans-serif; font-size:12px; min-width:180px;">
                <strong style="color:#2563eb;">${st.station_name}</strong><br/>
                <b>Zone:</b> ${st.zone_name}<br/>
                <span>In-Charge: <b>${st.commander_name}</b></span><br/>
                <span>Vehicles: <b>${st.vehicles_count}</b> • Officers: <b>${st.personnel_count}</b></span><br/>
                <span style="color:#64748b; font-size:10px;">Radio: ${st.radio_channel}</span>
            </div>
        `);
    });
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
