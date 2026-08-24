<?php
// medical_hub.php - DisasterSafe Medical Department & Hospital Command Hub
define('PAGE_TITLE', 'Medical Department');
require_once __DIR__ . '/auth.php';

requireLogin();
$currentUser = getCurrentUser($pdo);

// Role & Permission Checks
$isSuperAdmin = isSuperAdmin($currentUser);
$hasMedicalAccess = $isSuperAdmin || hasPermission($currentUser, 'access_medical');
if (!$hasMedicalAccess) {
    setFlash('error', 'Access denied. You do not have permission to view Medical Department Operations.');
    header("Location: dashboard.php");
    exit;
}

$csrfToken = generateCsrfToken();
$agencyType = 'Medical';

// Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token. Please refresh and retry.');
        header("Location: medical_hub.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    // 1. UPDATE TEAM / AMBULANCE STATUS
    if ($action === 'update_team_status') {
        $teamId = (int)($_POST['team_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? 'Available');
        $currentTask = trim($_POST['current_task'] ?? '');

        $stmt = $pdo->prepare("UPDATE agency_teams SET status = :status, current_task = :current_task WHERE id = :id AND agency_type = 'Medical'");
        $stmt->execute([':status' => $newStatus, ':current_task' => $currentTask, ':id' => $teamId]);
        logActivity($pdo, 'MEDICAL_TEAM_STATUS_UPDATED', "Medical / ALS squad #{$teamId} status changed to {$newStatus}");
        setFlash('success', "Medical team status updated to {$newStatus}.");
        header("Location: medical_hub.php");
        exit;
    }

    // 2. CREATE / ASSIGN NEW MEDICAL TASK
    if ($action === 'create_task') {
        $title = trim($_POST['title'] ?? '');
        $priority = trim($_POST['priority'] ?? 'High');
        $location = trim($_POST['location'] ?? '');
        $assignedTeam = trim($_POST['assigned_team'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($title && $location) {
            $stmt = $pdo->prepare("INSERT INTO agency_tasks (agency_type, title, priority, location, assigned_team, status, description) VALUES ('Medical', :title, :priority, :location, :assigned_team, 'In Progress', :description)");
            $stmt->execute([':title' => $title, ':priority' => $priority, ':location' => $location, ':assigned_team' => $assignedTeam, ':description' => $description]);
            logActivity($pdo, 'MEDICAL_TASK_DISPATCHED', "New Medical/EMS mission '{$title}' assigned to {$assignedTeam} at {$location}");
            setFlash('success', "Medical mission dispatched successfully.");
        } else {
            setFlash('error', 'Please fill in Mission Title and Target Location.');
        }
        header("Location: medical_hub.php");
        exit;
    }

    // 3. UPDATE TASK STATUS
    if ($action === 'update_task_status') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $status = trim($_POST['status'] ?? 'In Progress');
        $pdo->prepare("UPDATE agency_tasks SET status = ? WHERE id = ? AND agency_type = 'Medical'")->execute([$status, $taskId]);
        logActivity($pdo, 'MEDICAL_TASK_STATUS_UPDATED', "Medical mission #{$taskId} set to {$status}");
        setFlash('success', "Medical mission status updated to {$status}.");
        header("Location: medical_hub.php");
        exit;
    }

    // 4. ALLOCATE / RESERVE HOSPITAL CAPACITY & SUPPLIES
    if ($action === 'allocate_resource') {
        $resId = (int)($_POST['resource_id'] ?? 0);
        $qty = (int)($_POST['quantity'] ?? 0);
        
        $res = $pdo->prepare("SELECT * FROM agency_resources WHERE id = ? AND agency_type = 'Medical'");
        $res->execute([$resId]);
        $resource = $res->fetch();

        if ($resource && $qty > 0 && $qty <= $resource['available_quantity']) {
            $newAvailable = $resource['available_quantity'] - $qty;
            $newAllocated = $resource['allocated_quantity'] + $qty;
            $status = ($newAvailable === 0) ? 'Depleted' : (($newAvailable < ($resource['total_quantity'] * 0.2)) ? 'Critical Low' : 'In Stock');

            $pdo->prepare("UPDATE agency_resources SET available_quantity = ?, allocated_quantity = ?, status = ? WHERE id = ?")
                ->execute([$newAvailable, $newAllocated, $status, $resId]);
            logActivity($pdo, 'MEDICAL_RESOURCE_RESERVED', "Reserved {$qty} {$resource['unit']} of {$resource['item_name']}");
            setFlash('success', "Reserved {$qty} {$resource['unit']} for trauma triage & patients.");
        } else {
            setFlash('error', 'Invalid quantity requested or insufficient capacity.');
        }
        header("Location: medical_hub.php");
        exit;
    }

    // 5. DIRECT BROADCAST
    if ($action === 'broadcast_order') {
        $message = trim($_POST['message'] ?? '');
        if ($message) {
            logActivity($pdo, 'SUPERADMIN_MEDICAL_BROADCAST', "Superadmin broadcast order: '{$message}' to Hospital Control Hubs");
            setFlash('success', "Critical medical directive broadcasted to all Trauma Centers and Ambulance Despatches.");
        }
        header("Location: medical_hub.php");
        exit;
    }
}

// Fetch Medical Department Data
$stations = $pdo->query("SELECT * FROM agency_stations WHERE agency_type = 'Medical' ORDER BY id ASC")->fetchAll();
$teams = $pdo->query("SELECT t.*, s.station_name FROM agency_teams t LEFT JOIN agency_stations s ON t.station_id = s.id WHERE t.agency_type = 'Medical' ORDER BY t.id ASC")->fetchAll();
$tasks = $pdo->query("SELECT * FROM agency_tasks WHERE agency_type = 'Medical' ORDER BY id DESC")->fetchAll();
$resources = $pdo->query("SELECT r.*, s.station_name FROM agency_resources r LEFT JOIN agency_stations s ON r.station_id = s.id WHERE r.agency_type = 'Medical' ORDER BY r.id ASC")->fetchAll();

// Live Metrics
$totalHospitals = count($stations);
$activeAmbulances = count(array_filter($teams, fn($t) => $t['status'] !== 'Available'));
$availableAmbulances = count(array_filter($teams, fn($t) => $t['status'] === 'Available'));
$openTasks = count(array_filter($tasks, fn($t) => $t['status'] !== 'Completed'));
$totalMedicalStaff = array_sum(array_column($stations, 'personnel_count'));

// Calculate Total ICU Bed Capacity
$icuResource = array_values(array_filter($resources, fn($r) => str_contains($r['item_name'], 'ICU')));
$totalIcuBeds = $icuResource[0]['total_quantity'] ?? 143;
$availableIcuBeds = $icuResource[0]['available_quantity'] ?? 98;

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div class="flex-1 flex flex-col min-w-0 bg-[#0a0f1d] min-h-screen overflow-y-auto">
    <?php require_once __DIR__ . '/navbar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto w-full">

        <!-- POINT OF CONTACT & DEPARTMENT BANNER -->
        <section class="glass-panel p-5 sm:p-6 rounded-2xl border border-[#243049] relative overflow-hidden shadow-2xl">
            <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-emerald-600/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 relative z-10">
                <!-- Left Details -->
                <div class="space-y-2">
                    <div class="flex items-center gap-2.5">
                        <span class="w-8 h-8 rounded-xl bg-emerald-600/20 border border-emerald-500/30 flex items-center justify-center text-emerald-400 font-black text-base shadow-inner">
                            🚑
                        </span>
                        <div>
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-400">Emergency Services Agency</span>
                            <h2 class="text-xl sm:text-2xl font-black text-white tracking-tight">Medical Department & Hospital Command Hub</h2>
                        </div>
                    </div>
                    <p class="text-xs text-slate-300 max-w-2xl leading-relaxed">
                        Superadmin direct point of contact for hospital ICU and trauma bed reserves, ALS ambulance routing, mass casualty triage, blood bank reserves, and field oxygen supply logistics.
                    </p>
                </div>

                <!-- Right Point of Contact Card -->
                <div class="p-4 rounded-xl bg-[#0c1326] border border-emerald-500/30 flex flex-wrap sm:flex-nowrap items-center gap-4 shrink-0 shadow-lg">
                    <img src="https://images.unsplash.com/photo-1594824813620-4a0b2241cfd1?w=150&auto=format&fit=crop&q=80" alt="Doctor" class="w-12 h-12 rounded-xl object-cover border border-emerald-500/40 shrink-0">
                    <div class="text-xs space-y-1">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Department Director In-Charge</span>
                        <h4 class="font-extrabold text-white text-sm">Dr. Ananya Roy (EMS Chief)</h4>
                        <div class="flex flex-wrap items-center gap-3 pt-0.5 text-[11px]">
                            <a href="tel:108" class="text-emerald-400 hover:text-emerald-300 font-mono font-bold flex items-center gap-1">
                                <i class="fa-solid fa-phone text-[10px]"></i> 108 / +91 11 2659 8800
                            </a>
                            <span class="text-slate-400 font-mono font-semibold flex items-center gap-1">
                                <i class="fa-solid fa-walkie-talkie text-teal-400 text-[10px]"></i> EMS Dispatch Ch-1 (155.45 MHz)
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Direct Tactical Broadcast Bar (Superadmin Point of Contact Action) -->
            <form method="POST" action="medical_hub.php" class="mt-5 pt-4 border-t border-[#243049] flex flex-col sm:flex-row items-center gap-3">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="broadcast_order">
                <div class="relative flex-1 w-full">
                    <i class="fa-solid fa-tower-broadcast absolute left-3.5 top-3 text-emerald-400 text-xs"></i>
                    <input type="text" name="message" required placeholder="Transmit urgent medical order or mass triage alert to all Hospital Emergency Rooms..." class="w-full pl-9 pr-4 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500">
                </div>
                <button type="submit" class="w-full sm:w-auto px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-lg shadow-emerald-600/30 transition-all shrink-0 flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-paper-plane"></i> Broadcast Order
                </button>
            </form>
        </section>

        <!-- KPI METRICS HUD -->
        <section class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="stat-card-accent p-3.5 rounded-xl border-t-2 border-t-emerald-500 shadow-md flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Trauma Hospitals</p>
                    <h3 class="text-2xl font-extrabold text-white mt-0.5"><?= $totalHospitals ?></h3>
                    <span class="text-[10px] font-semibold text-emerald-400">Delhi-NCR Apex Centers</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 text-sm">
                    <i class="fa-solid fa-hospital"></i>
                </div>
            </div>

            <div class="stat-card-accent p-3.5 rounded-xl border-t-2 border-t-teal-500 shadow-md flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">ICU Beds Available</p>
                    <h3 class="text-2xl font-extrabold text-teal-400 mt-0.5"><?= $availableIcuBeds ?> / <?= $totalIcuBeds ?></h3>
                    <span class="text-[10px] font-semibold text-slate-400">Critical Care Capacity</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-teal-500/10 border border-teal-500/20 flex items-center justify-center text-teal-400 text-sm">
                    <i class="fa-solid fa-bed-pulse"></i>
                </div>
            </div>

            <div class="stat-card-accent p-3.5 rounded-xl border-t-2 border-t-blue-500 shadow-md flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">ALS Ambulances</p>
                    <h3 class="text-2xl font-extrabold text-blue-400 mt-0.5"><?= count($teams) ?></h3>
                    <span class="text-[10px] font-semibold text-blue-300"><?= $activeAmbulances ?> En-Route • <?= $availableAmbulances ?> Standby</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-400 text-sm">
                    <i class="fa-solid fa-truck-medical"></i>
                </div>
            </div>

            <div class="stat-card-accent p-3.5 rounded-xl border-t-2 border-t-rose-500 shadow-md flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Medical Staff</p>
                    <h3 class="text-2xl font-extrabold text-rose-400 mt-0.5"><?= $totalMedicalStaff ?></h3>
                    <span class="text-[10px] font-semibold text-rose-300">Doctors & Paramedics</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400 text-sm">
                    <i class="fa-solid fa-user-doctor"></i>
                </div>
            </div>
        </section>

        <!-- MAP & HOSPITALS CAPACITY GRID -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            
            <!-- Dedicated Medical GIS Radar Map (Left Column) -->
            <div class="lg:col-span-7 glass-panel p-4 rounded-2xl border border-[#243049] flex flex-col shadow-xl min-h-[380px]">
                <div class="flex items-center justify-between pb-3 border-b border-[#243049] mb-3">
                    <h3 class="text-sm font-extrabold text-white flex items-center gap-2">
                        <i class="fa-solid fa-map-location-dot text-emerald-400"></i>
                        <span>Hospital & Trauma Centers GIS Radar</span>
                    </h3>
                    <span class="text-[10px] font-mono text-emerald-300 bg-emerald-950 px-2 py-0.5 rounded border border-emerald-800">
                        <?= count($stations) ?> Centers • <?= count($teams) ?> Ambulances
                    </span>
                </div>
                <div id="medicalMap" class="flex-1 w-full rounded-xl overflow-hidden min-h-[300px] border border-[#243049]"></div>
            </div>

            <!-- Hospital Capacity & Trauma Centers Directory (Right Column) -->
            <div class="lg:col-span-5 glass-panel p-4 rounded-2xl border border-[#243049] flex flex-col shadow-xl">
                <div class="flex items-center justify-between pb-3 border-b border-[#243049] mb-3">
                    <h3 class="text-sm font-extrabold text-white flex items-center gap-2">
                        <i class="fa-solid fa-hospital text-emerald-400"></i>
                        <span>Hospital Capacities & Centers</span>
                    </h3>
                    <span class="text-[10px] font-bold text-slate-400">Live Status</span>
                </div>

                <div class="space-y-2.5 overflow-y-auto max-h-[320px] pr-1">
                    <?php foreach ($stations as $st): ?>
                        <div class="p-3 rounded-xl bg-[#0c1326] border border-[#243049] hover:border-emerald-500/50 transition-all text-xs space-y-1.5">
                            <div class="flex items-center justify-between">
                                <h4 class="font-extrabold text-white text-xs flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full <?= $st['status'] === 'Operational' ? 'bg-emerald-400' : 'bg-amber-400 animate-pulse' ?>"></span>
                                    <?= htmlspecialchars($st['station_name']) ?>
                                </h4>
                                <span class="text-[9px] font-extrabold px-1.5 py-0.5 rounded <?= $st['status'] === 'Operational' ? 'bg-emerald-950 text-emerald-300 border border-emerald-800' : 'bg-amber-950 text-amber-300 border border-amber-800' ?>">
                                    <?= htmlspecialchars($st['status']) ?>
                                </span>
                            </div>
                            <p class="text-slate-400 text-[11px]"><?= htmlspecialchars($st['address']) ?></p>
                            <div class="flex flex-wrap items-center justify-between gap-2 pt-1 border-t border-[#243049]/60 text-[10px] text-slate-300 font-mono">
                                <span><i class="fa-solid fa-user-doctor text-teal-400 mr-1"></i> <?= htmlspecialchars($st['commander_name']) ?></span>
                                <a href="tel:<?= urlencode($st['contact_phone']) ?>" class="text-emerald-400 hover:underline font-bold">
                                    <i class="fa-solid fa-phone text-[9px]"></i> <?= htmlspecialchars($st['contact_phone']) ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

        <!-- AMBULANCE SQUADS & PARAMEDIC ROSTER -->
        <section class="glass-panel p-5 rounded-2xl border border-[#243049] shadow-xl space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-[#243049]">
                <div>
                    <h3 class="text-base font-extrabold text-white flex items-center gap-2">
                        <i class="fa-solid fa-truck-medical text-emerald-400"></i>
                        <span>Ambulance Fleets & Critical Care Transit</span>
                    </h3>
                    <p class="text-xs text-slate-400">Active status of Type-C ALS ambulances, paramedic units, and mobile trauma triage vehicles.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3.5">
                <?php foreach ($teams as $tm): ?>
                    <?php 
                        $statusClass = match($tm['status']) {
                            'On-Scene' => 'bg-rose-950 text-rose-300 border-rose-800',
                            'Dispatched' => 'bg-teal-950 text-teal-300 border-teal-800',
                            'Available' => 'bg-emerald-950 text-emerald-300 border-emerald-800',
                            default => 'bg-slate-800 text-slate-300 border-slate-700'
                        };
                    ?>
                    <div class="p-3.5 rounded-xl bg-[#0c1326] border border-[#243049] hover:border-slate-500 transition-all text-xs space-y-2.5">
                        <div class="flex items-center justify-between">
                            <span class="font-extrabold text-white text-sm flex items-center gap-1.5">
                                <i class="fa-solid fa-heart-pulse text-emerald-400 text-xs"></i>
                                <?= htmlspecialchars($tm['callsign']) ?>
                            </span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold border <?= $statusClass ?>">
                                <?= htmlspecialchars($tm['status']) ?>
                            </span>
                        </div>

                        <div class="space-y-1 text-slate-300 text-[11px]">
                            <p><b>Paramedic Lead:</b> <?= htmlspecialchars($tm['team_lead']) ?> (<b><?= $tm['members_count'] ?> Crew</b>)</p>
                            <p class="text-slate-400"><b>Equipment:</b> <?= htmlspecialchars($tm['vehicle_equipment']) ?></p>
                            <p class="text-emerald-300 font-medium"><b>Mission:</b> <?= htmlspecialchars($tm['current_task'] ?: 'Standby at Base Hospital') ?></p>
                        </div>

                        <!-- Status Action Form -->
                        <form method="POST" action="medical_hub.php" class="pt-2 border-t border-[#243049] flex items-center justify-between gap-2">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="action" value="update_team_status">
                            <input type="hidden" name="team_id" value="<?= $tm['id'] ?>">
                            <input type="hidden" name="current_task" value="<?= htmlspecialchars($tm['current_task']) ?>">

                            <select name="status" onchange="this.form.submit()" class="w-full px-2 py-1 bg-[#11192e] border border-[#243049] rounded-lg text-slate-200 text-[10px] font-semibold">
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

        <!-- TASKS QUEUE & HOSPITAL CAPACITIES INVENTORY -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            
            <!-- Medical Tasks & Patient Evacuations (Left 7 Cols) -->
            <div class="lg:col-span-7 glass-panel p-5 rounded-2xl border border-[#243049] shadow-xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[#243049]">
                    <div>
                        <h3 class="text-base font-extrabold text-white flex items-center gap-2">
                            <i class="fa-solid fa-notes-medical text-emerald-400"></i>
                            <span>Assigned Medical Missions & Triage</span>
                        </h3>
                    </div>
                    <button type="button" onclick="document.getElementById('medicalTaskModal').classList.remove('hidden')" class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md shadow-emerald-600/20 transition-all flex items-center gap-1.5">
                        <i class="fa-solid fa-plus"></i> New Mission
                    </button>
                </div>

                <div class="space-y-3">
                    <?php foreach ($tasks as $tsk): ?>
                        <div class="p-3.5 rounded-xl bg-[#0c1326] border border-[#243049] hover:border-slate-500 transition-all text-xs space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-extrabold uppercase <?= $tsk['priority'] === 'Critical' ? 'bg-rose-950 text-rose-300 border border-rose-800' : 'bg-amber-950 text-amber-300 border border-amber-800' ?>">
                                        <?= htmlspecialchars($tsk['priority']) ?>
                                    </span>
                                    <h4 class="font-bold text-white text-xs"><?= htmlspecialchars($tsk['title']) ?></h4>
                                </div>
                                <span class="px-2 py-0.5 rounded text-[10px] font-extrabold <?= $tsk['status'] === 'Completed' ? 'bg-emerald-950 text-emerald-300 border border-emerald-800' : 'bg-teal-950 text-teal-300 border border-teal-800' ?>">
                                    <?= htmlspecialchars($tsk['status']) ?>
                                </span>
                            </div>

                            <p class="text-slate-300 text-[11px]"><?= htmlspecialchars($tsk['description']) ?></p>
                            
                            <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-[#243049]/60 text-[11px]">
                                <span class="text-slate-400"><i class="fa-solid fa-location-dot text-rose-400 mr-1"></i> <?= htmlspecialchars($tsk['location']) ?></span>
                                <span class="text-emerald-400 font-semibold"><i class="fa-solid fa-truck-medical mr-1"></i> <?= htmlspecialchars($tsk['assigned_team'] ?: 'Unassigned') ?></span>
                                
                                <form method="POST" action="medical_hub.php" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="action" value="update_task_status">
                                    <input type="hidden" name="task_id" value="<?= $tsk['id'] ?>">
                                    <?php if ($tsk['status'] !== 'Completed'): ?>
                                        <button type="submit" name="status" value="Completed" class="px-2 py-0.5 rounded bg-emerald-600/80 hover:bg-emerald-500 text-white font-bold text-[10px]">
                                            <i class="fa-solid fa-check mr-1"></i> Mark Completed
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" name="status" value="In Progress" class="px-2 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 text-[10px]">
                                            Re-open
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Hospital Capacity & Medical Supplies Inventory (Right 5 Cols) -->
            <div class="lg:col-span-5 glass-panel p-5 rounded-2xl border border-[#243049] shadow-xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[#243049]">
                    <h3 class="text-base font-extrabold text-white flex items-center gap-2">
                        <i class="fa-solid fa-boxes-stacked text-emerald-400"></i>
                        <span>Hospital Capacities & Logistics</span>
                    </h3>
                </div>

                <div class="space-y-3">
                    <?php foreach ($resources as $res): ?>
                        <div class="p-3.5 rounded-xl bg-[#0c1326] border border-[#243049] text-xs space-y-2">
                            <div class="flex items-center justify-between">
                                <h4 class="font-extrabold text-white text-xs"><?= htmlspecialchars($res['item_name']) ?></h4>
                                <span class="px-1.5 py-0.5 rounded text-[9px] font-bold <?= $res['status'] === 'In Stock' ? 'bg-emerald-950 text-emerald-300' : 'bg-rose-950 text-rose-300' ?>">
                                    <?= htmlspecialchars($res['status']) ?>
                                </span>
                            </div>

                            <div class="flex items-center justify-between text-[11px] text-slate-300">
                                <span>Available: <strong class="text-emerald-400"><?= $res['available_quantity'] ?> <?= htmlspecialchars($res['unit']) ?></strong></span>
                                <span>Occupied: <strong class="text-amber-400"><?= $res['allocated_quantity'] ?></strong> / <?= $res['total_quantity'] ?></span>
                            </div>

                            <!-- Progress Bar -->
                            <?php $percent = $res['total_quantity'] > 0 ? round(($res['available_quantity'] / $res['total_quantity']) * 100) : 0; ?>
                            <div class="w-full bg-[#11192e] rounded-full h-1.5 overflow-hidden">
                                <div class="bg-emerald-500 h-1.5 rounded-full" style="width: <?= $percent ?>%"></div>
                            </div>

                            <!-- Quick Dispatch Allocation Form -->
                            <form method="POST" action="medical_hub.php" class="pt-1.5 flex items-center gap-2">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="allocate_resource">
                                <input type="hidden" name="resource_id" value="<?= $res['id'] ?>">
                                <input type="number" name="quantity" min="1" max="<?= $res['available_quantity'] ?>" value="5" class="w-16 px-2 py-1 bg-[#11192e] border border-[#243049] rounded-lg text-white text-[10px] text-center">
                                <button type="submit" <?= $res['available_quantity'] <= 0 ? 'disabled' : '' ?> class="flex-1 py-1 rounded-lg bg-emerald-600/20 hover:bg-emerald-600/40 text-emerald-300 border border-emerald-500/30 text-[10px] font-bold transition-all disabled:opacity-40">
                                    Reserve / Allocate
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

    </main>
</div>

<!-- CREATE MEDICAL TASK MODAL -->
<div id="medicalTaskModal" class="fixed inset-0 bg-[#060a14]/85 backdrop-blur-md z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-[#0f172a] border border-[#243049] rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden">
        <div class="h-14 px-6 bg-[#0c1326] border-b border-[#243049] flex items-center justify-between">
            <h3 class="text-sm font-extrabold text-white flex items-center gap-2">
                <i class="fa-solid fa-notes-medical text-emerald-400"></i> Dispatch New Medical Mission
            </h3>
            <button type="button" onclick="document.getElementById('medicalTaskModal').classList.add('hidden')" class="text-slate-400 hover:text-white">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <form method="POST" action="medical_hub.php" class="p-6 space-y-3.5 text-xs">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="create_task">

            <div>
                <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Mission Title *</label>
                <input type="text" name="title" required placeholder="e.g. Critical COPD Patient Evacuation & Trauma Bed Prep" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Priority</label>
                    <select name="priority" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white font-semibold">
                        <option value="Critical">🔴 Critical</option>
                        <option value="High" selected>🟠 High</option>
                        <option value="Medium">🟡 Medium</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Assign Ambulance Squad</label>
                    <select name="assigned_team" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white font-semibold">
                        <?php foreach ($teams as $tm): ?>
                            <option value="<?= htmlspecialchars($tm['callsign']) ?>"><?= htmlspecialchars($tm['callsign']) ?> (<?= $tm['status'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Target Location *</label>
                <input type="text" name="location" required placeholder="e.g. Sector 74 Noida / AIIMS Trauma Annex" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Medical Directives & Triage Notes</label>
                <textarea name="description" rows="3" placeholder="Specify oxygen cylinder requirements, blood type reserves, or burn dressings..." class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white leading-relaxed"></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('medicalTaskModal').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 font-semibold">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold shadow-lg shadow-emerald-600/30">Dispatch Medical Unit</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const mMap = L.map('medicalMap', { zoomControl: false, attributionControl: false }).setView([28.6139, 77.2090], 11);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', { maxZoom: 19 }).addTo(mMap);

    const stationsData = <?= json_encode($stations) ?>;

    // Hospital Center Pins
    stationsData.forEach(st => {
        L.circleMarker([st.gps_lat, st.gps_lng], {
            radius: 9,
            fillColor: '#10b981',
            color: '#ffffff',
            weight: 2,
            fillOpacity: 0.95
        }).addTo(mMap)
        .bindPopup(`
            <div style="color:#0f172a; font-family:'Inter', sans-serif; font-size:12px; min-width:180px;">
                <strong style="color:#10b981;">🏥 ${st.station_name}</strong><br/>
                <b>Zone:</b> ${st.zone_name}<br/>
                <span>Supt: <b>${st.commander_name}</b></span><br/>
                <span>Ambulances: <b>${st.vehicles_count}</b> • Staff: <b>${st.personnel_count}</b></span><br/>
                <span style="color:#64748b; font-size:10px;">Comms: ${st.radio_channel}</span>
            </div>
        `);
    });
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
