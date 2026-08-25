<?php
// medical_hub.php - DisasterSafe Medical Department & Hospital Command Hub (Government Theme)
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

<div class="flex-1 flex flex-col min-w-0 bg-[#f8fafc] min-h-screen overflow-y-auto">
    <?php require_once __DIR__ . '/navbar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto w-full">

        <!-- POINT OF CONTACT & DEPARTMENT BANNER -->
        <section class="bg-white p-5 sm:p-6 rounded-3xl border border-slate-200 relative overflow-hidden shadow-sm">
            <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-teal-100/60 rounded-full blur-3xl pointer-events-none"></div>

            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 relative z-10">
                <!-- Left Details -->
                <div class="space-y-2">
                    <div class="flex items-center gap-2.5">
                        <span class="w-9 h-9 rounded-2xl bg-teal-50 border border-teal-200 flex items-center justify-center text-teal-700 font-black text-base shadow-2xs">
                            <i class="fa-solid fa-truck-medical"></i>
                        </span>
                        <div>
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-teal-700 mono">Emergency Services Agency</span>
                            <h2 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">Medical Department &amp; Hospital Command Hub</h2>
                        </div>
                    </div>
                    <p class="text-xs text-slate-600 max-w-2xl leading-relaxed font-medium">
                        Superadmin direct point of contact for hospital ICU and trauma bed reserves, ALS ambulance routing, mass casualty triage, blood bank reserves, and field oxygen supply logistics.
                    </p>
                </div>

                <!-- Right Point of Contact Card -->
                <div class="p-4 rounded-2xl bg-teal-50/70 border border-teal-200 flex flex-wrap sm:flex-nowrap items-center gap-4 shrink-0 shadow-2xs">
                    <img src="https://images.unsplash.com/photo-1594824813620-4a0b2241cfd1?w=150&auto=format&fit=crop&q=80" alt="Doctor" class="w-12 h-12 rounded-2xl object-cover border border-teal-200 shrink-0">
                    <div class="text-xs space-y-1">
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mono">Department Director In-Charge</span>
                        <h4 class="font-extrabold text-slate-900 text-sm">Dr. Ananya Roy (EMS Chief)</h4>
                        <div class="flex flex-wrap items-center gap-3 pt-0.5 text-[11px]">
                            <a href="tel:108" class="text-teal-700 hover:underline font-mono font-bold flex items-center gap-1">
                                <i class="fa-solid fa-phone text-[10px]"></i> 108 / +91 11 2659 8800
                            </a>
                            <span class="text-slate-600 font-mono font-semibold flex items-center gap-1">
                                <i class="fa-solid fa-walkie-talkie text-teal-600 text-[10px]"></i> EMS Dispatch Ch-1 (155.45 MHz)
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Direct Tactical Broadcast Bar -->
            <form method="POST" action="medical_hub.php" class="mt-5 pt-4 border-t border-slate-100 flex flex-col sm:flex-row items-center gap-3">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="broadcast_order">
                <div class="relative flex-1 w-full">
                    <i class="fa-solid fa-tower-broadcast absolute left-3.5 top-3 text-teal-600 text-xs"></i>
                    <input type="text" name="message" required placeholder="Transmit urgent medical order or mass triage alert to all Hospital Emergency Rooms..." class="w-full pl-9 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-2xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-teal-600 focus:bg-white font-medium">
                </div>
                <button type="submit" class="w-full sm:w-auto px-5 py-2.5 rounded-2xl bg-[#0d9488] hover:bg-[#0f766e] text-white font-bold text-xs shadow-2xs transition-all shrink-0 flex items-center justify-center gap-1.5 cursor-pointer">
                    <i class="fa-solid fa-paper-plane"></i> Broadcast Order
                </button>
            </form>
        </section>

        <!-- KPI METRICS HUD WITH ACCENT CONTRAST -->
        <section class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="bg-white border border-slate-200 border-l-4 border-l-emerald-600 p-3.5 rounded-2xl shadow-2xs flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider mono">Trauma Hospitals</p>
                    <h3 class="text-2xl font-black text-slate-900 mt-0.5"><?= $totalHospitals ?></h3>
                    <span class="text-[10px] font-bold text-emerald-700 mono">Delhi-NCR Apex Centers</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-600 text-sm">
                    <i class="fa-solid fa-hospital"></i>
                </div>
            </div>

            <div class="bg-white border border-slate-200 border-l-4 border-l-teal-600 p-3.5 rounded-2xl shadow-2xs flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-extrabold text-teal-800 uppercase tracking-wider mono">ICU Beds Available</p>
                    <h3 class="text-2xl font-black text-teal-700 mt-0.5"><?= $availableIcuBeds ?> / <?= $totalIcuBeds ?></h3>
                    <span class="text-[10px] font-bold text-slate-500 mono">Critical Care Capacity</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-teal-50 border border-teal-200 flex items-center justify-center text-teal-600 text-sm">
                    <i class="fa-solid fa-bed-pulse"></i>
                </div>
            </div>

            <div class="bg-white border border-slate-200 border-l-4 border-l-blue-600 p-3.5 rounded-2xl shadow-2xs flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-extrabold text-blue-800 uppercase tracking-wider mono">ALS Ambulances</p>
                    <h3 class="text-2xl font-black text-blue-700 mt-0.5"><?= count($teams) ?></h3>
                    <span class="text-[10px] font-bold text-slate-500 mono"><?= $activeAmbulances ?> En-Route • <?= $availableAmbulances ?> Standby</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-blue-50 border border-blue-200 flex items-center justify-center text-blue-600 text-sm">
                    <i class="fa-solid fa-truck-medical"></i>
                </div>
            </div>

            <div class="bg-white border border-slate-200 border-l-4 border-l-rose-600 p-3.5 rounded-2xl shadow-2xs flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-extrabold text-rose-800 uppercase tracking-wider mono">Medical Staff</p>
                    <h3 class="text-2xl font-black text-rose-700 mt-0.5"><?= $totalMedicalStaff ?></h3>
                    <span class="text-[10px] font-bold text-rose-700 mono">Doctors &amp; Paramedics</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-rose-50 border border-rose-200 flex items-center justify-center text-rose-600 text-sm">
                    <i class="fa-solid fa-user-doctor"></i>
                </div>
            </div>
        </section>

        <!-- MAP & HOSPITALS CAPACITY GRID -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            
            <!-- Dedicated Medical GIS Radar Map (Left Column) -->
            <div class="lg:col-span-7 bg-white p-4 rounded-3xl border border-slate-200 flex flex-col shadow-xs min-h-[380px]">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-3">
                    <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-map-location-dot text-teal-600"></i>
                        <span>Hospital &amp; Trauma Centers GIS Radar</span>
                    </h3>
                    <span class="text-[10px] font-bold font-mono text-teal-800 bg-teal-50 px-2 py-0.5 rounded-full border border-teal-200">
                        <?= count($stations) ?> Centers • <?= count($teams) ?> Ambulances
                    </span>
                </div>
                <div id="medicalMap" class="flex-1 w-full rounded-2xl overflow-hidden min-h-[300px] border border-slate-200 bg-slate-100"></div>
            </div>

            <!-- Hospital Capacity & Trauma Centers Directory (Right Column) -->
            <div class="lg:col-span-5 bg-white p-4 rounded-3xl border border-slate-200 flex flex-col shadow-xs">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-3">
                    <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-hospital text-teal-600"></i>
                        <span>Hospital Capacities &amp; Centers</span>
                    </h3>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mono">Live Status</span>
                </div>

                <div class="space-y-2.5 overflow-y-auto max-h-[320px] pr-1">
                    <?php foreach ($stations as $st): ?>
                        <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200 hover:border-teal-300 transition-all text-xs space-y-1.5">
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
                                <span><i class="fa-solid fa-user-doctor text-teal-600 mr-1"></i> <?= htmlspecialchars($st['commander_name']) ?></span>
                                <a href="tel:<?= urlencode($st['contact_phone']) ?>" class="text-teal-700 hover:underline font-bold">
                                    <i class="fa-solid fa-phone text-[9px]"></i> <?= htmlspecialchars($st['contact_phone']) ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

        <!-- AMBULANCE SQUADS & PARAMEDIC ROSTER -->
        <section class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-truck-medical text-teal-600"></i>
                        <span>Ambulance Fleets &amp; Critical Care Transit</span>
                    </h3>
                    <p class="text-xs text-slate-500 font-medium">Active status of Type-C ALS ambulances, paramedic units, and mobile trauma triage vehicles.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3.5">
                <?php foreach ($teams as $tm): ?>
                    <?php 
                        $statusClass = match($tm['status']) {
                            'On-Scene' => 'bg-red-50 text-red-800 border-red-200',
                            'Dispatched' => 'bg-teal-50 text-teal-800 border-teal-200',
                            'Available' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                            default => 'bg-slate-100 text-slate-700 border-slate-200'
                        };
                    ?>
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 hover:border-teal-300 transition-all text-xs space-y-2.5">
                        <div class="flex items-center justify-between">
                            <span class="font-extrabold text-slate-900 text-sm flex items-center gap-1.5">
                                <i class="fa-solid fa-heart-pulse text-teal-600 text-xs"></i>
                                <?= htmlspecialchars($tm['callsign']) ?>
                            </span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border <?= $statusClass ?> mono">
                                <?= htmlspecialchars($tm['status']) ?>
                            </span>
                        </div>

                        <div class="space-y-1 text-slate-700 text-[11px] font-medium">
                            <p><b>Paramedic Lead:</b> <?= htmlspecialchars($tm['team_lead']) ?> (<b><?= $tm['members_count'] ?> Medics</b>)</p>
                            <p class="text-slate-500"><b>Apparatus:</b> <?= htmlspecialchars($tm['vehicle_equipment']) ?></p>
                            <p class="text-teal-800 font-semibold"><b>Task:</b> <?= htmlspecialchars($tm['current_task'] ?: 'Standby at ER Bay') ?></p>
                        </div>

                        <!-- Status Action Form -->
                        <form method="POST" action="medical_hub.php" class="pt-2 border-t border-slate-200 flex items-center justify-between gap-2">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="action" value="update_team_status">
                            <input type="hidden" name="team_id" value="<?= $tm['id'] ?>">
                            <input type="hidden" name="current_task" value="<?= htmlspecialchars($tm['current_task']) ?>">

                            <select name="status" onchange="this.form.submit()" class="w-full px-2.5 py-1.5 bg-white border border-slate-200 rounded-xl text-slate-800 text-[10px] font-bold focus:outline-none focus:border-teal-600">
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
            
            <!-- Medical Missions Queue (Left 7 Cols) -->
            <div class="lg:col-span-7 bg-white p-5 rounded-3xl border border-slate-200 shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                            <i class="fa-solid fa-list-check text-teal-600"></i>
                            <span>Assigned Medical &amp; EMS Missions</span>
                        </h3>
                    </div>
                    <button type="button" onclick="document.getElementById('medicalTaskModal').classList.remove('hidden')" class="px-3.5 py-2 rounded-2xl bg-[#0d9488] hover:bg-[#0f766e] text-white font-bold text-xs shadow-2xs transition-all flex items-center gap-1.5 cursor-pointer">
                        <i class="fa-solid fa-plus"></i> New Mission
                    </button>
                </div>

                <div class="space-y-3">
                    <?php foreach ($tasks as $tsk): ?>
                        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 hover:border-teal-300 transition-all text-xs space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase <?= $tsk['priority'] === 'Critical' ? 'bg-red-100 text-red-800 border border-red-200' : 'bg-amber-100 text-amber-800 border border-amber-200' ?> mono">
                                        <?= htmlspecialchars($tsk['priority']) ?>
                                    </span>
                                    <h4 class="font-extrabold text-slate-900 text-xs"><?= htmlspecialchars($tsk['title']) ?></h4>
                                </div>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold <?= $tsk['status'] === 'Completed' ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-teal-100 text-teal-800 border border-teal-200' ?> mono">
                                    <?= htmlspecialchars($tsk['status']) ?>
                                </span>
                            </div>

                            <p class="text-slate-600 text-[11px] font-medium"><?= htmlspecialchars($tsk['description']) ?></p>
                            
                            <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-slate-200 text-[11px]">
                                <span class="text-slate-500"><i class="fa-solid fa-location-dot text-red-500 mr-1"></i> <?= htmlspecialchars($tsk['location']) ?></span>
                                <span class="text-teal-700 font-bold"><i class="fa-solid fa-truck-medical mr-1"></i> <?= htmlspecialchars($tsk['assigned_team'] ?: 'Unassigned') ?></span>
                                
                                <form method="POST" action="medical_hub.php" class="inline">
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

            <!-- Medical Equipment & ICU Inventory (Right 5 Cols) -->
            <div class="lg:col-span-5 bg-white p-5 rounded-3xl border border-slate-200 shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-boxes-stacked text-teal-600"></i>
                        <span>Medical Supplies &amp; ICU Beds</span>
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
                                <span>Available: <strong class="text-teal-700"><?= $res['available_quantity'] ?> <?= htmlspecialchars($res['unit']) ?></strong></span>
                                <span>Reserved: <strong class="text-amber-700"><?= $res['allocated_quantity'] ?></strong> / <?= $res['total_quantity'] ?></span>
                            </div>

                            <!-- Progress Bar -->
                            <?php $percent = $res['total_quantity'] > 0 ? round(($res['available_quantity'] / $res['total_quantity']) * 100) : 0; ?>
                            <div class="w-full bg-slate-200 rounded-full h-1.5 overflow-hidden">
                                <div class="bg-[#0d9488] h-1.5 rounded-full" style="width: <?= $percent ?>%"></div>
                            </div>

                            <!-- Quick Reserve Allocation Form -->
                            <form method="POST" action="medical_hub.php" class="pt-1.5 flex items-center gap-2">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="allocate_resource">
                                <input type="hidden" name="resource_id" value="<?= $res['id'] ?>">
                                <input type="number" name="quantity" min="1" max="<?= $res['available_quantity'] ?>" value="1" class="w-16 px-2 py-1 bg-white border border-slate-200 rounded-xl text-slate-900 text-[10px] font-bold text-center">
                                <button type="submit" <?= $res['available_quantity'] <= 0 ? 'disabled' : '' ?> class="flex-1 py-1 rounded-xl bg-teal-50 hover:bg-teal-100 text-teal-800 border border-teal-200 text-[10px] font-bold transition-all disabled:opacity-40 cursor-pointer">
                                    Reserve for Triage
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
<div id="medicalTaskModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden">
        <div class="h-14 px-6 bg-white border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-truck-medical text-teal-600"></i> Dispatch Medical / Ambulance Mission
            </h3>
            <button type="button" onclick="document.getElementById('medicalTaskModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-800 cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <form method="POST" action="medical_hub.php" class="p-6 space-y-3.5 text-xs">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="create_task">

            <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1 mono">Incident Title *</label>
                <input type="text" name="title" required placeholder="e.g. Mass Casualty Triage & Evacuation at ISBT" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:bg-white focus:outline-none focus:border-teal-600 font-medium">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1 mono">Priority</label>
                    <select name="priority" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-bold focus:bg-white focus:outline-none focus:border-teal-600">
                        <option value="Critical">Critical (Code Red)</option>
                        <option value="High" selected>High</option>
                        <option value="Medium">Medium</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1 mono">Assign Ambulance</label>
                    <select name="assigned_team" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-bold focus:bg-white focus:outline-none focus:border-teal-600">
                        <?php foreach ($teams as $tm): ?>
                            <option value="<?= htmlspecialchars($tm['callsign']) ?>"><?= htmlspecialchars($tm['callsign']) ?> (<?= $tm['status'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1 mono">Target Location *</label>
                <input type="text" name="location" required placeholder="e.g. Kashmere Gate Terminal, Ring Road" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:bg-white focus:outline-none focus:border-teal-600 font-medium">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1 mono">Patient Vitals &amp; Directives</label>
                <textarea name="description" rows="3" placeholder="Describe trauma type, number of critical patients, oxygen requirement..." class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 leading-relaxed focus:bg-white focus:outline-none focus:border-teal-600 font-medium"></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('medicalTaskModal').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-700 font-bold cursor-pointer">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-[#0d9488] hover:bg-[#0f766e] text-white font-bold shadow-sm cursor-pointer">Dispatch Ambulance Unit</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const mMap = L.map('medicalMap', { zoomControl: false, attributionControl: false }).setView([28.6139, 77.2090], 11);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(mMap);

    const stationsData = <?= json_encode($stations) ?>;

    stationsData.forEach(st => {
        L.circleMarker([st.gps_lat, st.gps_lng], {
            radius: 9,
            fillColor: '#0d9488',
            color: '#ffffff',
            weight: 2,
            fillOpacity: 0.95
        }).addTo(mMap)
        .bindPopup(`
            <div style="color:#0f172a; font-family:'Inter', sans-serif; font-size:12px; min-width:180px;">
                <strong style="color:#0d9488;">${st.station_name}</strong><br/>
                <b>Zone:</b> ${st.zone_name}<br/>
                <span>Director: <b>${st.commander_name}</b></span><br/>
                <span>Ambulances: <b>${st.vehicles_count}</b> • Medics: <b>${st.personnel_count}</b></span><br/>
                <span style="color:#64748b; font-size:10px;">Radio: ${st.radio_channel}</span>
            </div>
        `);
    });
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
