<?php
// fire_hub.php - DisasterSafe Fire & Rescue Tactical CAD Hub (Unified Government Theme)
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

    // 1. UPDATE TEAM / APPARATUS STATUS
    if ($action === 'update_team_status') {
        $teamId = (int)($_POST['team_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? 'Available');
        $currentTask = trim($_POST['current_task'] ?? '');

        $stmt = $pdo->prepare("UPDATE agency_teams SET status = :status, current_task = :current_task WHERE id = :id AND agency_type = 'Fire'");
        $stmt->execute([':status' => $newStatus, ':current_task' => $currentTask, ':id' => $teamId]);
        logActivity($pdo, 'FIRE_APPARATUS_STATUS_UPDATED', "Fire Engine / Squad #{$teamId} status changed to {$newStatus}");
        setFlash('success', "Fire tender status updated to {$newStatus}.");
        header("Location: fire_hub.php");
        exit;
    }

    // 2. DISPATCH NEW FIRE MISSION
    if ($action === 'create_task') {
        $title = trim($_POST['title'] ?? '');
        $priority = trim($_POST['priority'] ?? 'High');
        $location = trim($_POST['location'] ?? '');
        $assignedTeam = trim($_POST['assigned_team'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($title && $location) {
            $stmt = $pdo->prepare("INSERT INTO agency_tasks (agency_type, title, priority, location, assigned_team, status, description) VALUES ('Fire', :title, :priority, :location, :assigned_team, 'In Progress', :description)");
            $stmt->execute([':title' => $title, ':priority' => $priority, ':location' => $location, ':assigned_team' => $assignedTeam, ':description' => $description]);
            logActivity($pdo, 'FIRE_TASK_DISPATCHED', "New Fire CAD mission '{$title}' dispatched to {$assignedTeam} at {$location}");
            setFlash('success', "Fire response mission dispatched successfully.");
        } else {
            setFlash('error', 'Please fill in Incident Title and Location.');
        }
        header("Location: fire_hub.php");
        exit;
    }

    // 3. UPDATE TASK STATUS
    if ($action === 'update_task_status') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $status = trim($_POST['status'] ?? 'In Progress');
        $pdo->prepare("UPDATE agency_tasks SET status = ? WHERE id = ? AND agency_type = 'Fire'")->execute([$status, $taskId]);
        logActivity($pdo, 'FIRE_TASK_STATUS_UPDATED', "Fire incident #{$taskId} set to {$status}");
        setFlash('success', "Incident CAD status updated to {$status}.");
        header("Location: fire_hub.php");
        exit;
    }

    // 4. ALLOCATE RESOURCE / FOAM / EQUIPMENT
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
            setFlash('success', "Allocated {$qty} {$resource['unit']} to fire ground crew.");
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
            logActivity($pdo, 'SUPERADMIN_FIRE_BROADCAST', "Superadmin broadcast order: '{$message}' to all Fire & Rescue channels");
            setFlash('success', "Fire directive broadcasted to all Fire Stations & Engines on VHF Ch-2.");
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
$fireSos = $pdo->query("SELECT * FROM emergency_sos WHERE dispatch_agency = 'Fire' OR emergency_type IN ('Fire / Smoke', 'Fire', 'Gas Leak', 'Structural Collapse') ORDER BY id DESC LIMIT 6")->fetchAll();

// Live Metrics
$totalStations = count($stations);
$activeTenders = count(array_filter($teams, fn($t) => $t['status'] !== 'Available'));
$availableTenders = count(array_filter($teams, fn($t) => $t['status'] === 'Available'));
$openTasks = count(array_filter($tasks, fn($t) => $t['status'] !== 'Completed'));
$totalPersonnel = array_sum(array_column($stations, 'personnel_count'));

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div class="flex-1 flex flex-col min-w-0 bg-[#f8fafc] min-h-screen overflow-y-auto">
    <?php require_once __DIR__ . '/navbar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto w-full">

        <!-- 1. HERO COMMAND BANNER -->
        <section class="bg-gradient-to-r from-red-950 via-slate-900 to-red-900 rounded-3xl p-6 sm:p-8 text-white shadow-xl relative overflow-hidden border border-red-800/40">
            <div class="absolute -right-10 -bottom-10 w-72 h-72 bg-red-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute right-20 top-0 w-48 h-48 bg-amber-500/10 rounded-full blur-2xl pointer-events-none"></div>

            <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                <div class="space-y-2 max-w-2xl">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-red-500/20 border border-red-400/30 text-red-300 text-xs font-bold mono">
                        <span class="w-2 h-2 rounded-full bg-red-400 animate-ping"></span>
                        <i class="fa-solid fa-fire-extinguisher text-xs"></i>
                        <span>FIRE &amp; RESCUE &bull; TACTICAL INCIDENT CAD</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black tracking-tight text-white">
                        Fire Suppression &amp; Hazmat Command
                    </h1>
                    <p class="text-sm text-slate-300 font-medium leading-relaxed">
                        Computer-Aided Dispatch (CAD) for fire engines, hydraulic rescue platforms, hazmat containment squads, and structural collapse cutting teams across Delhi-NCR.
                    </p>
                    <div class="flex flex-wrap items-center gap-4 pt-2 text-xs font-bold text-slate-300">
                        <span class="flex items-center gap-1.5"><i class="fa-solid fa-fire-flame-curved text-red-400"></i> Chief: <strong class="text-white">Chief Vikram Rathore (CFO)</strong></span>
                        <span class="flex items-center gap-1.5"><i class="fa-solid fa-walkie-talkie text-amber-400"></i> Radio: <strong class="text-white">VHF Ch-2 (156.80 MHz)</strong></span>
                        <span class="flex items-center gap-1.5"><i class="fa-solid fa-phone text-emerald-400"></i> Hotline: <strong class="text-white">101 / +91 11 2341 2222</strong></span>
                    </div>
                </div>

                <!-- Right Quick Controls -->
                <div class="flex flex-col sm:flex-row lg:flex-col gap-2.5 shrink-0">
                    <button type="button" onclick="openCreateTaskModal()" class="px-4 py-2.5 rounded-2xl bg-red-600 hover:bg-red-700 text-white text-xs font-black transition-all shadow-lg flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-plus-circle text-sm"></i>
                        <span>Dispatch Fire Engine</span>
                    </button>
                    <a href="disasters.php" class="px-4 py-2.5 rounded-2xl bg-white/10 hover:bg-white/20 text-white border border-white/20 text-xs font-bold transition-all flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-triangle-exclamation text-sm text-amber-400"></i>
                        <span>Disaster Incidents Hub</span>
                    </a>
                </div>
            </div>

            <!-- Direct Tactical Broadcast Bar -->
            <form method="POST" action="fire_hub.php" class="mt-6 pt-4 border-t border-white/10 flex flex-col sm:flex-row items-center gap-3">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="broadcast_order">
                <div class="relative flex-1 w-full">
                    <i class="fa-solid fa-tower-broadcast absolute left-3.5 top-3 text-red-400 text-xs"></i>
                    <input type="text" name="message" required placeholder="Transmit direct priority CAD directive to all Fire Stations & Engine companies..." class="w-full pl-9 pr-4 py-2 bg-slate-900/60 border border-slate-700 text-white rounded-xl text-xs placeholder-slate-400 focus:outline-none focus:border-red-500 font-medium">
                </div>
                <button type="submit" class="w-full sm:w-auto px-4 py-2 rounded-xl bg-red-600 hover:bg-red-500 text-white font-bold text-xs shadow-xs transition-all shrink-0 flex items-center justify-center gap-1.5 cursor-pointer">
                    <i class="fa-solid fa-paper-plane text-xs"></i> Broadcast Order
                </button>
            </form>
        </section>

        <!-- 2. KPI METRICS GRID -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
            <!-- Active Stations -->
            <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 mono">Fire Stations</span>
                    <h3 class="text-2xl font-black text-slate-900 mt-1"><?= $totalStations ?></h3>
                    <p class="text-xs text-red-600 font-bold mt-0.5 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                        High-Pressure Hydrant Hubs
                    </p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-red-50 text-red-600 border border-red-200 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-building-flag"></i>
                </div>
            </div>

            <!-- Rolling Apparatus -->
            <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 mono">Rolling Tenders</span>
                    <h3 class="text-2xl font-black text-slate-900 mt-1"><?= count($teams) ?></h3>
                    <p class="text-xs text-amber-600 font-bold mt-0.5 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                        <?= $activeTenders ?> En Route &bull; <?= $availableTenders ?> In Quarters
                    </p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 border border-amber-200 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-truck-droplet"></i>
                </div>
            </div>

            <!-- Active Fire Incidents -->
            <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 mono">Active Fire CAD</span>
                    <h3 class="text-2xl font-black text-slate-900 mt-1"><?= $openTasks ?></h3>
                    <p class="text-xs text-rose-600 font-bold mt-0.5 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                        Structural &amp; Hazmat Calls
                    </p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 border border-rose-200 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-fire-extinguisher"></i>
                </div>
            </div>

            <!-- On-Duty Firefighters -->
            <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 mono">Active Firefighters</span>
                    <h3 class="text-2xl font-black text-slate-900 mt-1"><?= number_format($totalPersonnel) ?></h3>
                    <p class="text-xs text-emerald-600 font-bold mt-0.5 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        Turnout Ready on Shift
                    </p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 border border-emerald-200 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-helmet-safety"></i>
                </div>
            </div>
        </section>

        <!-- 3. TACTICAL FIRE RADAR MAP & ALARM QUEUE (2 col / 1 col Grid) -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Leaflet Fire Map (2 cols) -->
            <div class="lg:col-span-2 bg-white rounded-3xl border border-slate-200 shadow-sm p-5 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-3">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-xl bg-red-50 text-red-600 flex items-center justify-center text-sm font-bold">
                                <i class="fa-solid fa-map-location-dot"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-slate-900">Fire Stations, Hydrants &amp; Incident Radar</h3>
                                <p class="text-[10px] text-slate-500 font-medium">Real-time GPS mapping of fire headquarters, high-pressure hydrants, and active fire alarms</p>
                            </div>
                        </div>
                        <span class="px-2.5 py-0.5 rounded-full bg-red-50 text-red-700 border border-red-200 text-[10px] font-bold mono">
                            <?= count($stations) ?> STATIONS PLOTTED
                        </span>
                    </div>

                    <div id="fireTacticalMap" class="w-full h-80 rounded-2xl bg-slate-100 border border-slate-200 overflow-hidden relative z-0"></div>

                    <div class="pt-3 mt-3 border-t border-slate-100 flex flex-wrap items-center justify-between text-[11px] font-bold text-slate-600 gap-2">
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-red-600"></span> Fire Stations</span>
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-600"></span> Heavy Water Tenders</span>
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-rose-600"></span> Active Fire SOS Beacons</span>
                    </div>
                </div>
            </div>

            <!-- Fire CAD Alarms Queue (1 col) -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-5 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-3">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-xl bg-red-50 text-red-600 flex items-center justify-center text-sm font-bold">
                                <i class="fa-solid fa-bell"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-slate-900">Emergency 101 Alarms</h3>
                                <p class="text-[10px] text-slate-500 font-medium">Real-Time CAD Distress Queue</p>
                            </div>
                        </div>
                        <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
                    </div>

                    <!-- Incidents List -->
                    <div class="space-y-2.5 max-h-72 overflow-y-auto pr-1">
                        <?php if (empty($fireSos)): ?>
                            <p class="text-xs text-slate-400 italic text-center py-6">No fire or gas emergencies reported.</p>
                        <?php else: ?>
                            <?php foreach ($fireSos as $sos): ?>
                                <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs space-y-1">
                                    <div class="flex items-center justify-between">
                                        <span class="font-black text-slate-900 text-[11px] flex items-center gap-1">
                                            <i class="fa-solid fa-fire text-red-500 text-[10px]"></i>
                                            <?= htmlspecialchars($sos['sender_name']) ?> (#<?= $sos['id'] ?>)
                                        </span>
                                        <span class="px-2 py-0.5 rounded text-[9px] font-bold <?= $sos['priority'] === 'Critical' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800' ?> mono">
                                            <?= htmlspecialchars($sos['priority']) ?>
                                        </span>
                                    </div>
                                    <p class="text-slate-600 font-medium text-[11px]"><?= htmlspecialchars($sos['emergency_type']) ?> &bull; <?= htmlspecialchars($sos['message'] ?: 'Emergency response required') ?></p>
                                    <div class="flex items-center justify-between text-[10px] text-slate-400 font-mono pt-0.5">
                                        <span>GPS: <?= $sos['gps_lat'] ?>, <?= $sos['gps_lng'] ?></span>
                                        <span><?= $sos['status'] ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="pt-3 mt-3 border-t border-slate-100 text-center">
                    <a href="sos.php" class="text-xs font-bold text-red-600 hover:text-red-700 flex items-center justify-center gap-1">
                        <span>View Universal SOS Console</span>
                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </a>
                </div>
            </div>

        </section>

        <!-- 4. APPARATUS FLEET & ENGINES LEDGER -->
        <section class="bg-white rounded-3xl border border-slate-200 shadow-sm p-5 sm:p-6 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-red-50 text-red-600 border border-red-200 flex items-center justify-center text-base font-bold">
                        <i class="fa-solid fa-truck-droplet"></i>
                    </div>
                    <div>
                        <h2 class="text-base sm:text-lg font-black text-slate-900">Fire Apparatus &amp; Heavy Rescue Fleet</h2>
                        <p class="text-xs text-slate-500 font-medium">Turnout status of water tenders, foam crash tenders, and hydraulic turntables</p>
                    </div>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-black bg-red-50 text-red-700 border border-red-200 mono">
                    <?= count($teams) ?> Registered Engines
                </span>
            </div>

            <!-- Teams Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($teams as $team): ?>
                    <?php
                        $statusColor = match($team['status']) {
                            'Available' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                            'Deployed', 'En Route' => 'bg-red-50 text-red-700 border-red-200',
                            'Standby' => 'bg-amber-50 text-amber-700 border-amber-200',
                            default => 'bg-slate-100 text-slate-700 border-slate-200'
                        };
                    ?>
                    <div class="p-4 rounded-2xl border border-slate-200 bg-slate-50/50 hover:bg-white hover:border-red-300 transition-all shadow-2xs space-y-3">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h4 class="font-black text-slate-900 text-sm"><?= htmlspecialchars($team['team_code']) ?></h4>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black border <?= $statusColor ?> mono">
                                        <?= htmlspecialchars($team['status']) ?>
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500 font-medium mt-0.5"><?= htmlspecialchars($team['team_name'] ?: 'Fire Tender Unit') ?></p>
                            </div>
                            <button type="button" onclick="openUpdateTeamModal(<?= $team['id'] ?>, '<?= htmlspecialchars($team['team_code']) ?>', '<?= htmlspecialchars($team['status']) ?>', '<?= addslashes(htmlspecialchars($team['current_task'] ?? '')) ?>')" class="p-1.5 text-slate-400 hover:text-red-600 transition-colors cursor-pointer" title="Update Apparatus Status">
                                <i class="fa-solid fa-pen-to-square text-xs"></i>
                            </button>
                        </div>

                        <div class="text-xs space-y-1.5 font-medium text-slate-600 bg-white p-2.5 rounded-xl border border-slate-200/80">
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-slate-400">Station Base:</span>
                                <strong class="text-slate-800"><?= htmlspecialchars($team['station_name'] ?: 'Central Sector Fire HQ') ?></strong>
                            </div>
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-slate-400">Station Officer:</span>
                                <strong class="text-slate-800"><?= htmlspecialchars($team['leader_name'] ?: 'Fire Station Lead') ?></strong>
                            </div>
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-slate-400">Current Assignment:</span>
                                <span class="text-red-700 font-bold truncate max-w-[150px]"><?= htmlspecialchars($team['current_task'] ?: 'In Quarters / Ready') ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- 5. ACTIVE FIRE CAD MISSIONS & INCIDENTS -->
        <section class="bg-white rounded-3xl border border-slate-200 shadow-sm p-5 sm:p-6 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 border border-amber-200 flex items-center justify-center text-base font-bold">
                        <i class="fa-solid fa-list-check"></i>
                    </div>
                    <div>
                        <h2 class="text-base sm:text-lg font-black text-slate-900">Active Fire &amp; Hazmat Incidents</h2>
                        <p class="text-xs text-slate-500 font-medium">Structural fires, gas leaks, industrial hazmat containment, and extraction calls</p>
                    </div>
                </div>
                <button type="button" onclick="openCreateTaskModal()" class="px-3.5 py-1.5 rounded-xl bg-red-50 text-red-700 hover:bg-red-100 border border-red-200 text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer self-start sm:self-auto">
                    <i class="fa-solid fa-plus text-xs"></i>
                    <span>Dispatch Incident</span>
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php if (empty($tasks)): ?>
                    <p class="col-span-2 text-xs text-slate-400 italic text-center py-6">No fire incidents currently reported.</p>
                <?php else: ?>
                    <?php foreach ($tasks as $task): ?>
                        <div class="p-4 rounded-2xl border border-slate-200 bg-white shadow-2xs flex flex-col justify-between space-y-3">
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black <?= $task['priority'] === 'Critical' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-amber-50 text-amber-700 border border-amber-200' ?> mono">
                                        <?= htmlspecialchars($task['priority']) ?> PRIORITY
                                    </span>
                                    <span class="text-[10px] font-bold text-slate-500 mono">
                                        <?= htmlspecialchars($task['status']) ?>
                                    </span>
                                </div>
                                <h4 class="font-black text-slate-900 text-sm"><?= htmlspecialchars($task['title']) ?></h4>
                                <p class="text-xs text-slate-600 font-medium leading-relaxed"><?= htmlspecialchars($task['description'] ?: 'Fire suppression and search operations.') ?></p>
                                <div class="flex items-center gap-4 text-[11px] font-bold text-slate-500 pt-1">
                                    <span><i class="fa-solid fa-location-dot text-red-500 mr-1"></i> <?= htmlspecialchars($task['location']) ?></span>
                                    <span><i class="fa-solid fa-truck-droplet text-amber-500 mr-1"></i> <?= htmlspecialchars($task['assigned_team'] ?: 'Unassigned') ?></span>
                                </div>
                            </div>

                            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                                <?php if ($task['status'] !== 'Completed'): ?>
                                    <form method="POST" action="fire_hub.php">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="action" value="update_task_status">
                                        <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                        <input type="hidden" name="status" value="Completed">
                                        <button type="submit" class="px-3 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-bold border border-emerald-200 transition-all cursor-pointer">
                                            <i class="fa-solid fa-check mr-1"></i> Mark Extinguished &amp; Safe
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-xs font-bold text-emerald-600 flex items-center gap-1"><i class="fa-solid fa-check-circle"></i> Incident Closed</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- 6. FIRE LOGISTICS & FOAM DEPOT -->
        <section class="bg-white rounded-3xl border border-slate-200 shadow-sm p-5 sm:p-6 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-red-50 text-red-600 border border-red-200 flex items-center justify-center text-base font-bold">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                    <div>
                        <h2 class="text-base sm:text-lg font-black text-slate-900">AFFF Foam, SCBA Gear &amp; Suppression Logistics</h2>
                        <p class="text-xs text-slate-500 font-medium">Aqueous film-forming foam (AFFF), breathing apparatus, thermal cameras, and hydraulic cutters</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($resources as $res): ?>
                    <?php
                        $pct = ($res['total_quantity'] > 0) ? round(($res['available_quantity'] / $res['total_quantity']) * 100) : 0;
                    ?>
                    <div class="p-4 rounded-2xl border border-slate-200 bg-slate-50/50 space-y-3">
                        <div class="flex items-start justify-between">
                            <div>
                                <h4 class="font-black text-slate-900 text-sm"><?= htmlspecialchars($res['item_name']) ?></h4>
                                <span class="text-[10px] font-bold text-slate-400 mono uppercase"><?= htmlspecialchars($res['category'] ?? 'Fire Supplies') ?></span>
                            </div>
                            <button type="button" onclick="openAllocateModal(<?= $res['id'] ?>, '<?= addslashes(htmlspecialchars($res['item_name'])) ?>', <?= (int)$res['available_quantity'] ?>, '<?= htmlspecialchars($res['unit']) ?>')" class="px-2.5 py-1 rounded-lg bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 text-xs font-bold transition-all cursor-pointer">
                                Allocate
                            </button>
                        </div>

                        <div>
                            <div class="flex items-center justify-between text-xs font-bold mb-1">
                                <span class="text-slate-600">Stock: <?= $res['available_quantity'] ?> / <?= $res['total_quantity'] ?> <?= htmlspecialchars($res['unit']) ?></span>
                                <span class="text-red-600 font-mono"><?= $pct ?>%</span>
                            </div>
                            <div class="w-full bg-slate-200 h-2 rounded-full overflow-hidden">
                                <div class="bg-red-600 h-full rounded-full transition-all" style="width: <?= $pct ?>%"></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

    </main>
</div>

<!-- ==================== MODALS ==================== -->

<!-- 1. Dispatch Fire Task Modal -->
<div id="createTaskModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white border border-slate-200 rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <h3 class="text-base font-black text-slate-900">Dispatch Fire &amp; Rescue Incident</h3>
            <button type="button" onclick="document.getElementById('createTaskModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-700 cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="fire_hub.php" class="space-y-3 text-xs">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="create_task">

            <div>
                <label class="block font-bold text-slate-700 mb-1">Incident Title</label>
                <input type="text" name="title" required placeholder="e.g. Commercial Complex 3rd Alarm Fire & Hazmat" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl font-medium focus:outline-none focus:border-red-600">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Priority</label>
                    <select name="priority" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-medium focus:outline-none focus:border-red-600">
                        <option value="Critical">Critical Priority</option>
                        <option value="High" selected>High Priority</option>
                        <option value="Medium">Medium Priority</option>
                        <option value="Low">Low Priority</option>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Assigned Apparatus</label>
                    <select name="assigned_team" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-medium focus:outline-none focus:border-red-600">
                        <?php foreach ($teams as $t): ?>
                            <option value="<?= htmlspecialchars($t['team_code']) ?>"><?= htmlspecialchars($t['team_code']) ?> - <?= htmlspecialchars($t['team_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">Incident Location</label>
                <input type="text" name="location" required placeholder="e.g. Okhla Industrial Area Phase 2, New Delhi" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl font-medium focus:outline-none focus:border-red-600">
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">Incident Brief &amp; Hazmat Details</label>
                <textarea name="description" rows="3" placeholder="Specify smoke density, chemical hazards, hydrants in vicinity, and trapped casualties..." class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl font-medium focus:outline-none focus:border-red-600"></textarea>
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                <button type="button" onclick="document.getElementById('createTaskModal').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold cursor-pointer">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white font-bold cursor-pointer">Dispatch Apparatus</button>
            </div>
        </form>
    </div>
</div>

<!-- 2. Update Team Status Modal -->
<div id="updateTeamModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white border border-slate-200 rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <h3 class="text-base font-black text-slate-900" id="teamModalTitle">Update Tender Status</h3>
            <button type="button" onclick="document.getElementById('updateTeamModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-700 cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="fire_hub.php" class="space-y-3 text-xs">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="update_team_status">
            <input type="hidden" name="team_id" id="modal_team_id">

            <div>
                <label class="block font-bold text-slate-700 mb-1">Operational Status</label>
                <select name="status" id="modal_team_status" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-medium focus:outline-none focus:border-red-600">
                    <option value="Available">Available (In Quarters)</option>
                    <option value="Deployed">Deployed (On Scene)</option>
                    <option value="En Route">En Route (Sirens On)</option>
                    <option value="Standby">Standby (Refilling Water Tank)</option>
                </select>
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">Current Assignment / Fire Scene</label>
                <input type="text" name="current_task" id="modal_team_task" placeholder="e.g. Connaught Place Fire Suppression" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl font-medium focus:outline-none focus:border-red-600">
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                <button type="button" onclick="document.getElementById('updateTeamModal').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold cursor-pointer">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white font-bold cursor-pointer">Save Status</button>
            </div>
        </form>
    </div>
</div>

<!-- 3. Allocate Resource Modal -->
<div id="allocateModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white border border-slate-200 rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <h3 class="text-base font-black text-slate-900" id="resModalTitle">Allocate Fire Equipment</h3>
            <button type="button" onclick="document.getElementById('allocateModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-700 cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="fire_hub.php" class="space-y-3 text-xs">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="allocate_resource">
            <input type="hidden" name="resource_id" id="modal_res_id">

            <p class="text-slate-500 font-medium" id="modal_res_info">Available in depot: 0</p>

            <div>
                <label class="block font-bold text-slate-700 mb-1">Quantity to Allocate</label>
                <input type="number" name="quantity" min="1" required class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl font-medium focus:outline-none focus:border-red-600">
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                <button type="button" onclick="document.getElementById('allocateModal').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold cursor-pointer">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white font-bold cursor-pointer">Confirm Allocation</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== LEAFLET MAP SCRIPT ==================== -->
<script>
let fireMap = null;

function initFireMap() {
    if (fireMap) return;
    const mapEl = document.getElementById('fireTacticalMap');
    if (!mapEl) return;

    fireMap = L.map('fireTacticalMap').setView([28.6139, 77.2090], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(fireMap);

    // Plot Fire Stations
    <?php foreach ($stations as $st): ?>
        <?php if (!empty($st['latitude']) && !empty($st['longitude'])): ?>
            L.circleMarker([<?= (float)$st['latitude'] ?>, <?= (float)$st['longitude'] ?>], {
                radius: 9,
                fillColor: '#dc2626',
                color: '#ffffff',
                weight: 2.5,
                opacity: 1,
                fillOpacity: 0.95
            }).addTo(fireMap).bindPopup(`
                <div style="font-family: sans-serif; font-size: 12px;">
                    <strong style="color: #dc2626;">🚒 <?= htmlspecialchars($st['station_name']) ?></strong><br>
                    <strong>Address:</strong> <?= htmlspecialchars($st['address'] ?? 'Fire Headquarters') ?><br>
                    <strong>Firefighters Ready:</strong> <?= (int)$st['personnel_count'] ?><br>
                    <strong>Hotline:</strong> <?= htmlspecialchars($st['contact_phone'] ?? '101') ?>
                </div>
            `);
        <?php endif; ?>
    <?php endforeach; ?>

    // Plot Fire SOS Distress beacons
    <?php foreach ($fireSos as $sos): ?>
        <?php if (!empty($sos['gps_lat']) && !empty($sos['gps_lng'])): ?>
            L.circleMarker([<?= (float)$sos['gps_lat'] ?>, <?= (float)$sos['gps_lng'] ?>], {
                radius: 8,
                fillColor: '#ea580c',
                color: '#ffffff',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.9
            }).addTo(fireMap).bindPopup(`
                <div style="font-family: sans-serif; font-size: 12px;">
                    <strong style="color: #ea580c;">🔥 <?= htmlspecialchars($sos['emergency_type']) ?> (#<?= $sos['id'] ?>)</strong><br>
                    <strong>Caller:</strong> <?= htmlspecialchars($sos['sender_name']) ?><br>
                    <strong>Phone:</strong> <?= htmlspecialchars($sos['sender_phone']) ?><br>
                    <strong>Priority:</strong> <?= htmlspecialchars($sos['priority']) ?>
                </div>
            `);
        <?php endif; ?>
    <?php endforeach; ?>
}

function openCreateTaskModal() {
    document.getElementById('createTaskModal').classList.remove('hidden');
}

function openUpdateTeamModal(id, code, status, task) {
    document.getElementById('modal_team_id').value = id;
    document.getElementById('teamModalTitle').innerText = `Update Status for ${code}`;
    document.getElementById('modal_team_status').value = status;
    document.getElementById('modal_team_task').value = task || '';
    document.getElementById('updateTeamModal').classList.remove('hidden');
}

function openAllocateModal(id, name, available, unit) {
    document.getElementById('modal_res_id').value = id;
    document.getElementById('resModalTitle').innerText = `Allocate ${name}`;
    document.getElementById('modal_res_info').innerText = `Available in Depot: ${available} ${unit}`;
    document.getElementById('allocateModal').classList.remove('hidden');
}

document.addEventListener('DOMContentLoaded', function() {
    initFireMap();
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
