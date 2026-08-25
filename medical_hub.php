<?php
// medical_hub.php - DisasterSafe Medical EMS & Trauma Hospital Hub (Unified Government Theme)
define('PAGE_TITLE', 'Medical & EMS Department');
require_once __DIR__ . '/auth.php';

requireLogin();
$currentUser = getCurrentUser($pdo);

// Role & Permission Checks
$isSuperAdmin = isSuperAdmin($currentUser);
$hasMedicalAccess = $isSuperAdmin || hasPermission($currentUser, 'access_medical');
if (!$hasMedicalAccess) {
    setFlash('error', 'Access denied. You do not have permission to view Medical & EMS Department Operations.');
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
        logActivity($pdo, 'MEDICAL_AMBULANCE_STATUS_UPDATED', "ALS Ambulance / Squad #{$teamId} status changed to {$newStatus}");
        setFlash('success', "Ambulance status updated to {$newStatus}.");
        header("Location: medical_hub.php");
        exit;
    }

    // 2. ADD NEW AMBULANCE UNIT / SQUAD
    if ($action === 'add_team') {
        $stationId = (int)($_POST['station_id'] ?? 8);
        $callsign = trim($_POST['callsign'] ?? '');
        $teamLead = trim($_POST['team_lead'] ?? '');
        $membersCount = (int)($_POST['members_count'] ?? 4);
        $vehicleEquipment = trim($_POST['vehicle_equipment'] ?? '');
        $status = trim($_POST['status'] ?? 'Available');
        $currentTask = trim($_POST['current_task'] ?? 'Ready at Bay');
        $contactRadio = trim($_POST['contact_radio'] ?? 'EMS Dispatch Ch-1');

        if ($callsign && $teamLead) {
            $stmt = $pdo->prepare("INSERT INTO agency_teams (agency_type, station_id, callsign, team_lead, members_count, vehicle_equipment, status, current_task, contact_radio) VALUES ('Medical', :station_id, :callsign, :team_lead, :members_count, :vehicle_equipment, :status, :current_task, :contact_radio)");
            $stmt->execute([
                ':station_id' => $stationId,
                ':callsign' => $callsign,
                ':team_lead' => $teamLead,
                ':members_count' => $membersCount,
                ':vehicle_equipment' => $vehicleEquipment,
                ':status' => $status,
                ':current_task' => $currentTask,
                ':contact_radio' => $contactRadio
            ]);
            logActivity($pdo, 'MEDICAL_AMBULANCE_ADDED', "Registered new ALS Ambulance {$callsign} under {$teamLead}");
            setFlash('success', "Ambulance Unit '{$callsign}' registered and ready for emergency patient dispatch.");
        } else {
            setFlash('error', 'Please provide Ambulance Callsign and Paramedic Lead name.');
        }
        header("Location: medical_hub.php");
        exit;
    }

    // 3. DISPATCH NEW MEDICAL TASK
    if ($action === 'create_task') {
        $title = trim($_POST['title'] ?? '');
        $priority = trim($_POST['priority'] ?? 'High');
        $location = trim($_POST['location'] ?? '');
        $assignedTeam = trim($_POST['assigned_team'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($title && $location) {
            $stmt = $pdo->prepare("INSERT INTO agency_tasks (agency_type, title, priority, location, assigned_team, status, description) VALUES ('Medical', :title, :priority, :location, :assigned_team, 'In Progress', :description)");
            $stmt->execute([':title' => $title, ':priority' => $priority, ':location' => $location, ':assigned_team' => $assignedTeam, ':description' => $description]);
            
            // Update assigned ambulance status
            if ($assignedTeam) {
                $pdo->prepare("UPDATE agency_teams SET status = 'On-Scene', current_task = ? WHERE callsign = ? AND agency_type = 'Medical'")
                    ->execute([$title . " at " . $location, $assignedTeam]);
            }

            logActivity($pdo, 'MEDICAL_TASK_DISPATCHED', "New Medical EMS mission '{$title}' dispatched to {$assignedTeam} at {$location}");
            setFlash('success', "Medical response mission dispatched successfully.");
        } else {
            setFlash('error', 'Please fill in Mission Title and Target Location.');
        }
        header("Location: medical_hub.php");
        exit;
    }

    // 4. UPDATE TASK STATUS
    if ($action === 'update_task_status') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $status = trim($_POST['status'] ?? 'In Progress');
        $pdo->prepare("UPDATE agency_tasks SET status = ? WHERE id = ? AND agency_type = 'Medical'")->execute([$status, $taskId]);
        logActivity($pdo, 'MEDICAL_TASK_STATUS_UPDATED', "Medical mission #{$taskId} set to {$status}");
        setFlash('success', "Medical mission status updated to {$status}.");
        header("Location: medical_hub.php");
        exit;
    }

    // 5. ALLOCATE RESOURCE / DRUGS / BLOOD PACKS
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
            logActivity($pdo, 'MEDICAL_RESOURCE_ALLOCATED', "Allocated {$qty} {$resource['unit']} of {$resource['item_name']}");
            setFlash('success', "Allocated {$qty} {$resource['unit']} to hospital ER trauma team.");
        } else {
            setFlash('error', 'Invalid quantity requested or insufficient stock.');
        }
        header("Location: medical_hub.php");
        exit;
    }

    // 6. DIRECT BROADCAST
    if ($action === 'broadcast_order') {
        $message = trim($_POST['message'] ?? '');
        if ($message) {
            logActivity($pdo, 'SUPERADMIN_MEDICAL_BROADCAST', "Superadmin broadcast order: '{$message}' to all Medical Trauma channels");
            setFlash('success', "Medical priority directive broadcasted to all Trauma Centers & ALS Ambulances on EMS Dispatch Ch-1.");
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
$medicalSos = $pdo->query("SELECT * FROM emergency_sos WHERE dispatch_agency = 'Medical' OR emergency_type IN ('Medical Emergency', 'Injury / Bleeding', 'Gas Leak', 'Fire') ORDER BY id DESC LIMIT 6")->fetchAll();

// Live Metrics
$totalHospitals = count($stations);
$activeAmbulances = count(array_filter($teams, fn($t) => in_array($t['status'], ['Deployed', 'On-Scene', 'Dispatched', 'En Route'])));
$availableAmbulances = count(array_filter($teams, fn($t) => $t['status'] === 'Available'));
$openTasks = count(array_filter($tasks, fn($t) => $t['status'] !== 'Completed'));
$totalPersonnel = array_sum(array_column($stations, 'personnel_count'));

// Calculate ICU Beds from resources or facilities
$icuBedsAvailable = $pdo->query("SELECT available_quantity FROM agency_resources WHERE agency_type = 'Medical' AND item_name LIKE '%ICU%' LIMIT 1")->fetchColumn() ?: '98';

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div class="flex-1 flex flex-col min-w-0 bg-[#f8fafc] min-h-screen overflow-y-auto">
    <?php require_once __DIR__ . '/navbar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto w-full">

        <!-- 1. HERO COMMAND BANNER -->
        <section class="bg-gradient-to-r from-teal-950 via-slate-900 to-teal-900 rounded-3xl p-6 sm:p-8 text-white shadow-xl relative overflow-hidden border border-teal-800/40">
            <div class="absolute -right-10 -bottom-10 w-72 h-72 bg-teal-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute right-20 top-0 w-48 h-48 bg-emerald-500/10 rounded-full blur-2xl pointer-events-none"></div>

            <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                <div class="space-y-2 max-w-2xl">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-teal-500/20 border border-teal-400/30 text-teal-300 text-xs font-bold mono">
                        <span class="w-2 h-2 rounded-full bg-teal-400 animate-ping"></span>
                        <i class="fa-solid fa-truck-medical text-xs"></i>
                        <span>EMS &bull; HOSPITAL TRAUMA &amp; CASUALTY COMMAND</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black tracking-tight text-white">
                        Emergency Medical Services &amp; ICU Bed Grid
                    </h1>
                    <p class="text-sm text-slate-300 font-medium leading-relaxed">
                        Computerized medical dispatching for Advanced Life Support (ALS) ambulances, trauma center triage, blood bank distribution, and ventilator capacity management across Delhi-NCR.
                    </p>
                    <div class="flex flex-wrap items-center gap-4 pt-2 text-xs font-bold text-slate-300">
                        <span class="flex items-center gap-1.5"><i class="fa-solid fa-user-doctor text-teal-400"></i> Medical Dir: <strong class="text-white">Dr. Aris Thorne (Chief EMS)</strong></span>
                        <span class="flex items-center gap-1.5"><i class="fa-solid fa-walkie-talkie text-emerald-400"></i> Radio: <strong class="text-white">EMS Dispatch Ch-1 (155.45 MHz)</strong></span>
                        <span class="flex items-center gap-1.5"><i class="fa-solid fa-phone text-emerald-400"></i> Ambulance: <strong class="text-white">108 / +91 11 2659 8800</strong></span>
                    </div>
                </div>

                <!-- Right Quick Controls -->
                <div class="flex flex-col sm:flex-row lg:flex-col gap-2.5 shrink-0">
                    <button type="button" onclick="openAddTeamModal()" class="px-4 py-2.5 rounded-2xl bg-teal-600 hover:bg-teal-700 text-white text-xs font-black transition-all shadow-lg flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-truck-medical text-sm"></i>
                        <span>Register Ambulance</span>
                    </button>
                    <button type="button" onclick="openCreateTaskModal()" class="px-4 py-2.5 rounded-2xl bg-white/10 hover:bg-white/20 text-white border border-white/20 text-xs font-bold transition-all flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-plus-circle text-sm text-teal-400"></i>
                        <span>Dispatch Medical Mission</span>
                    </button>
                </div>
            </div>

            <!-- Direct Tactical Broadcast Bar -->
            <form method="POST" action="medical_hub.php" class="mt-6 pt-4 border-t border-white/10 flex flex-col sm:flex-row items-center gap-3">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="broadcast_order">
                <div class="relative flex-1 w-full">
                    <i class="fa-solid fa-tower-broadcast absolute left-3.5 top-3 text-teal-400 text-xs"></i>
                    <input type="text" name="message" required placeholder="Transmit direct priority medical directive to all Hospital ERs & Ambulance units..." class="w-full pl-9 pr-4 py-2 bg-slate-900/60 border border-slate-700 text-white rounded-xl text-xs placeholder-slate-400 focus:outline-none focus:border-teal-500 font-medium">
                </div>
                <button type="submit" class="w-full sm:w-auto px-4 py-2 rounded-xl bg-teal-600 hover:bg-teal-500 text-white font-bold text-xs shadow-xs transition-all shrink-0 flex items-center justify-center gap-1.5 cursor-pointer">
                    <i class="fa-solid fa-paper-plane text-xs"></i> Broadcast Directive
                </button>
            </form>
        </section>

        <!-- 2. KPI METRICS GRID -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
            <!-- Trauma Centers -->
            <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 mono">Trauma Centers</span>
                    <h3 class="text-2xl font-black text-slate-900 mt-1"><?= $totalHospitals ?></h3>
                    <p class="text-xs text-teal-600 font-bold mt-0.5 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-teal-500"></span>
                        Apex Tertiary ER Bases
                    </p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-teal-50 text-teal-600 border border-teal-200 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-hospital"></i>
                </div>
            </div>

            <!-- Rolling Ambulances -->
            <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 mono">ALS Ambulances</span>
                    <h3 class="text-2xl font-black text-slate-900 mt-1"><?= count($teams) ?></h3>
                    <p class="text-xs text-indigo-600 font-bold mt-0.5 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                        <?= $activeAmbulances ?> Deployed &bull; <?= $availableAmbulances ?> Ready
                    </p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 border border-indigo-200 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-truck-medical"></i>
                </div>
            </div>

            <!-- ICU Beds Available -->
            <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 mono">Available ICU Beds</span>
                    <h3 class="text-2xl font-black text-slate-900 mt-1"><?= $icuBedsAvailable ?></h3>
                    <p class="text-xs text-emerald-600 font-bold mt-0.5 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        Ventilator Synchronized
                    </p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 border border-emerald-200 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-bed-pulse"></i>
                </div>
            </div>

            <!-- Medical Staff -->
            <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 mono">Doctors &amp; Paramedics</span>
                    <h3 class="text-2xl font-black text-slate-900 mt-1"><?= number_format($totalPersonnel) ?></h3>
                    <p class="text-xs text-blue-600 font-bold mt-0.5 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                        Trauma &amp; Burn Specialists
                    </p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 border border-blue-200 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-user-doctor"></i>
                </div>
            </div>
        </section>

        <!-- 3. TACTICAL GIS RADAR MAP & LIVE TELEMETRY (2 col / 1 col Grid) -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Leaflet Medical Map (2 cols) -->
            <div class="lg:col-span-2 bg-white rounded-3xl border border-slate-200 shadow-sm p-5 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-3">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center text-sm font-bold">
                                <i class="fa-solid fa-map-location-dot"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-slate-900">Hospital ICU &amp; Ambulance GIS Radar</h3>
                                <p class="text-[10px] text-slate-500 font-medium">Live GPS coordinates of trauma hospitals, mobile ICUs, and casualty beacons</p>
                            </div>
                        </div>
                        <span class="px-2.5 py-0.5 rounded-full bg-teal-50 text-teal-700 border border-teal-200 text-[10px] font-bold mono">
                            <?= count($stations) ?> HOSPITALS PLOTTED
                        </span>
                    </div>

                    <div id="medicalTacticalMap" class="w-full h-80 rounded-2xl bg-slate-100 border border-slate-200 overflow-hidden relative z-0"></div>

                    <div class="pt-3 mt-3 border-t border-slate-100 flex flex-wrap items-center justify-between text-[11px] font-bold text-slate-600 gap-2">
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-teal-600"></span> Trauma Hospitals</span>
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-indigo-600"></span> ALS Ambulances</span>
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-red-600"></span> Medical SOS Distress</span>
                    </div>
                </div>
            </div>

            <!-- Medical Comms & Distress Queue (1 col) -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-5 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-3">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center text-sm font-bold">
                                <i class="fa-solid fa-bullhorn"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-slate-900">Medical Distress Feed</h3>
                                <p class="text-[10px] text-slate-500 font-medium">Emergency Casualty Signals</p>
                            </div>
                        </div>
                        <span class="w-2 h-2 rounded-full bg-teal-500 animate-pulse"></span>
                    </div>

                    <!-- Incidents List -->
                    <div class="space-y-2.5 max-h-72 overflow-y-auto pr-1">
                        <?php if (empty($medicalSos)): ?>
                            <p class="text-xs text-slate-400 italic text-center py-6">No emergency medical calls active.</p>
                        <?php else: ?>
                            <?php foreach ($medicalSos as $sos): ?>
                                <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs space-y-1">
                                    <div class="flex items-center justify-between">
                                        <span class="font-black text-slate-900 text-[11px] flex items-center gap-1">
                                            <i class="fa-solid fa-triangle-exclamation text-red-500 text-[10px]"></i>
                                            <?= htmlspecialchars($sos['sender_name']) ?> (#<?= $sos['id'] ?>)
                                        </span>
                                        <span class="px-2 py-0.5 rounded text-[9px] font-bold <?= $sos['priority'] === 'Critical' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800' ?> mono">
                                            <?= htmlspecialchars($sos['priority']) ?>
                                        </span>
                                    </div>
                                    <p class="text-slate-600 font-medium text-[11px]"><?= htmlspecialchars($sos['emergency_type']) ?> &bull; <?= htmlspecialchars($sos['message'] ?: 'Paramedic assistance requested') ?></p>
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
                    <a href="sos.php" class="text-xs font-bold text-teal-600 hover:text-teal-700 flex items-center justify-center gap-1">
                        <span>View All Emergency Distress Beacons</span>
                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </a>
                </div>
            </div>

        </section>

        <!-- 4. AMBULANCE FLEET & HOSPITAL WARDS LEDGER -->
        <section class="bg-white rounded-3xl border border-slate-200 shadow-sm p-5 sm:p-6 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-teal-50 text-teal-600 border border-teal-200 flex items-center justify-center text-base font-bold">
                        <i class="fa-solid fa-truck-medical"></i>
                    </div>
                    <div>
                        <h2 class="text-base sm:text-lg font-black text-slate-900">Advanced Life Support (ALS) Ambulance Fleet</h2>
                        <p class="text-xs text-slate-500 font-medium">Real-time status of emergency ambulances, mobile ICUs, and neonatal transport squads</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="openAddTeamModal()" class="px-3 py-1.5 rounded-xl bg-teal-50 text-teal-700 hover:bg-teal-100 border border-teal-200 text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer">
                        <i class="fa-solid fa-plus text-xs"></i>
                        <span>Add Unit</span>
                    </button>
                    <span class="px-3 py-1 rounded-full text-xs font-black bg-teal-50 text-teal-700 border border-teal-200 mono">
                        <?= count($teams) ?> Registered Ambulances
                    </span>
                </div>
            </div>

            <!-- Teams Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($teams as $team): ?>
                    <?php
                        $statusColor = match($team['status']) {
                            'Available' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                            'On-Scene', 'Deployed', 'En Route' => 'bg-teal-50 text-teal-700 border-teal-200',
                            'Dispatched' => 'bg-blue-50 text-blue-700 border-blue-200',
                            'Standby' => 'bg-amber-50 text-amber-700 border-amber-200',
                            default => 'bg-slate-100 text-slate-700 border-slate-200'
                        };
                    ?>
                    <div class="p-4 rounded-2xl border border-slate-200 bg-slate-50/50 hover:bg-white hover:border-teal-300 transition-all shadow-2xs space-y-3">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h4 class="font-black text-slate-900 text-sm"><?= htmlspecialchars($team['callsign'] ?: 'Ambulance #' . $team['id']) ?></h4>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black border <?= $statusColor ?> mono">
                                        <?= htmlspecialchars($team['status']) ?>
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500 font-medium mt-0.5"><?= htmlspecialchars($team['vehicle_equipment'] ?: 'ALS Paramedic Cruiser') ?></p>
                            </div>
                            <button type="button" onclick="openUpdateTeamModal(<?= $team['id'] ?>, '<?= addslashes(htmlspecialchars($team['callsign'] ?: 'Ambulance #' . $team['id'])) ?>', '<?= htmlspecialchars($team['status']) ?>', '<?= addslashes(htmlspecialchars($team['current_task'] ?? '')) ?>')" class="px-2.5 py-1 rounded-lg bg-teal-50 hover:bg-teal-100 text-teal-700 border border-teal-200 text-xs font-bold transition-all flex items-center gap-1 cursor-pointer" title="Update Ambulance Status">
                                <i class="fa-solid fa-pen-to-square text-[10px]"></i> Update
                            </button>
                        </div>

                        <div class="text-xs space-y-1.5 font-medium text-slate-600 bg-white p-2.5 rounded-xl border border-slate-200/80">
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-slate-400">Hospital Base:</span>
                                <strong class="text-slate-800"><?= htmlspecialchars($team['station_name'] ?: 'AIIMS Trauma Hub') ?></strong>
                            </div>
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-slate-400">Lead Paramedic / Doctor:</span>
                                <strong class="text-slate-800"><?= htmlspecialchars($team['team_lead'] ?: 'EMS Specialist') ?> (<?= $team['members_count'] ?> crew)</strong>
                            </div>
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-slate-400">Dispatch Radio:</span>
                                <span class="font-mono text-teal-700 font-bold"><?= htmlspecialchars($team['contact_radio'] ?: 'EMS Dispatch Ch-1') ?></span>
                            </div>
                            <div class="flex items-center justify-between text-[11px] pt-1 border-t border-slate-100">
                                <span class="text-slate-400">Assignment:</span>
                                <span class="text-slate-800 font-bold truncate max-w-[170px]" title="<?= htmlspecialchars($team['current_task']) ?>"><?= htmlspecialchars($team['current_task'] ?: 'Ready at Bay') ?></span>
                            </div>
                        </div>

                        <!-- 1-Click Fast Status Buttons -->
                        <div class="flex items-center gap-1 pt-1">
                            <form method="POST" action="medical_hub.php" class="flex-1">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="update_team_status">
                                <input type="hidden" name="team_id" value="<?= $team['id'] ?>">
                                <input type="hidden" name="status" value="Available">
                                <input type="hidden" name="current_task" value="Ready at Bay">
                                <button type="submit" class="w-full py-1 text-[10px] font-bold rounded-lg transition-all <?= $team['status'] === 'Available' ? 'bg-emerald-600 text-white' : 'bg-slate-100 hover:bg-slate-200 text-slate-700' ?> cursor-pointer">
                                    At Bay
                                </button>
                            </form>
                            <form method="POST" action="medical_hub.php" class="flex-1">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="update_team_status">
                                <input type="hidden" name="team_id" value="<?= $team['id'] ?>">
                                <input type="hidden" name="status" value="On-Scene">
                                <input type="hidden" name="current_task" value="<?= htmlspecialchars($team['current_task'] ?: 'Emergency Patient Care') ?>">
                                <button type="submit" class="w-full py-1 text-[10px] font-bold rounded-lg transition-all <?= in_array($team['status'], ['On-Scene', 'Deployed']) ? 'bg-teal-600 text-white' : 'bg-slate-100 hover:bg-slate-200 text-slate-700' ?> cursor-pointer">
                                    On-Scene
                                </button>
                            </form>
                            <form method="POST" action="medical_hub.php" class="flex-1">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="update_team_status">
                                <input type="hidden" name="team_id" value="<?= $team['id'] ?>">
                                <input type="hidden" name="status" value="Standby">
                                <input type="hidden" name="current_task" value="Sanitizing & Standby">
                                <button type="submit" class="w-full py-1 text-[10px] font-bold rounded-lg transition-all <?= $team['status'] === 'Standby' ? 'bg-amber-600 text-white' : 'bg-slate-100 hover:bg-slate-200 text-slate-700' ?> cursor-pointer">
                                    Standby
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- 5. ACTIVE MEDICAL MISSIONS & CASUALTY DISPATCHES -->
        <section class="bg-white rounded-3xl border border-slate-200 shadow-sm p-5 sm:p-6 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 border border-amber-200 flex items-center justify-center text-base font-bold">
                        <i class="fa-solid fa-list-check"></i>
                    </div>
                    <div>
                        <h2 class="text-base sm:text-lg font-black text-slate-900">Active Medical &amp; Casualty Dispatches</h2>
                        <p class="text-xs text-slate-500 font-medium">Mass casualty evacuations, ventilator patient transfers, and mobile first-aid clinics</p>
                    </div>
                </div>
                <button type="button" onclick="openCreateTaskModal()" class="px-3.5 py-1.5 rounded-xl bg-teal-50 text-teal-700 hover:bg-teal-100 border border-teal-200 text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer self-start sm:self-auto">
                    <i class="fa-solid fa-plus text-xs"></i>
                    <span>Dispatch Mission</span>
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php if (empty($tasks)): ?>
                    <p class="col-span-2 text-xs text-slate-400 italic text-center py-6">No active medical dispatches.</p>
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
                                <p class="text-xs text-slate-600 font-medium leading-relaxed"><?= htmlspecialchars($task['description'] ?: 'Emergency medical patient care.') ?></p>
                                <div class="flex items-center gap-4 text-[11px] font-bold text-slate-500 pt-1">
                                    <span><i class="fa-solid fa-location-dot text-red-500 mr-1"></i> <?= htmlspecialchars($task['location']) ?></span>
                                    <span><i class="fa-solid fa-truck-medical text-teal-500 mr-1"></i> <?= htmlspecialchars($task['assigned_team'] ?: 'Unassigned') ?></span>
                                </div>
                            </div>

                            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                                <?php if ($task['status'] !== 'Completed'): ?>
                                    <form method="POST" action="medical_hub.php">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="action" value="update_task_status">
                                        <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                        <input type="hidden" name="status" value="Completed">
                                        <button type="submit" class="px-3 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-bold border border-emerald-200 transition-all cursor-pointer">
                                            <i class="fa-solid fa-check mr-1"></i> Mark Transport Complete
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-xs font-bold text-emerald-600 flex items-center gap-1"><i class="fa-solid fa-check-circle"></i> Patient Transferred</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- 6. MEDICAL SUPPLIES & DRUG INVENTORY -->
        <section class="bg-white rounded-3xl border border-slate-200 shadow-sm p-5 sm:p-6 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-teal-50 text-teal-600 border border-teal-200 flex items-center justify-center text-base font-bold">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                    <div>
                        <h2 class="text-base sm:text-lg font-black text-slate-900">Hospital Trauma Reserves &amp; Critical Supplies</h2>
                        <p class="text-xs text-slate-500 font-medium">O-Negative blood units, portable ventilators, emergency oxygen, and trauma packs</p>
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
                                <span class="text-[10px] font-bold text-slate-400 mono uppercase"><?= htmlspecialchars($res['category'] ?? 'Medical') ?></span>
                            </div>
                            <button type="button" onclick="openAllocateModal(<?= $res['id'] ?>, '<?= addslashes(htmlspecialchars($res['item_name'])) ?>', <?= (int)$res['available_quantity'] ?>, '<?= htmlspecialchars($res['unit']) ?>')" class="px-2.5 py-1 rounded-lg bg-teal-50 hover:bg-teal-100 text-teal-700 border border-teal-200 text-xs font-bold transition-all cursor-pointer">
                                Allocate
                            </button>
                        </div>

                        <div>
                            <div class="flex items-center justify-between text-xs font-bold mb-1">
                                <span class="text-slate-600">Stock: <?= $res['available_quantity'] ?> / <?= $res['total_quantity'] ?> <?= htmlspecialchars($res['unit']) ?></span>
                                <span class="text-teal-600 font-mono"><?= $pct ?>%</span>
                            </div>
                            <div class="w-full bg-slate-200 h-2 rounded-full overflow-hidden">
                                <div class="bg-teal-600 h-full rounded-full transition-all" style="width: <?= $pct ?>%"></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

    </main>
</div>

<!-- ==================== MODALS ==================== -->

<!-- 1. Add New Unit Modal -->
<div id="addTeamModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white border border-slate-200 rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <h3 class="text-base font-black text-slate-900">Register ALS Ambulance Unit</h3>
            <button type="button" onclick="document.getElementById('addTeamModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-700 cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="medical_hub.php" class="space-y-3 text-xs">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="add_team">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Ambulance Callsign *</label>
                    <input type="text" name="callsign" required placeholder="e.g. ALS Cruiser 4" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl font-medium focus:outline-none focus:border-teal-600">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Hospital Base</label>
                    <select name="station_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-medium focus:outline-none focus:border-teal-600">
                        <?php foreach ($stations as $st): ?>
                            <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['station_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Lead Doctor / Paramedic *</label>
                    <input type="text" name="team_lead" required placeholder="e.g. Dr. Sonia Kaul" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl font-medium focus:outline-none focus:border-teal-600">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Paramedic Crew Count</label>
                    <input type="number" name="members_count" min="1" value="4" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl font-medium focus:outline-none focus:border-teal-600">
                </div>
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">Equipment &amp; Life Support Setup</label>
                <input type="text" name="vehicle_equipment" placeholder="e.g. Type-C ALS Ambulance + Defibrillator & Ventilator" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl font-medium focus:outline-none focus:border-teal-600">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Initial Status</label>
                    <select name="status" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-medium focus:outline-none focus:border-teal-600">
                        <option value="Available" selected>Available (At Bay)</option>
                        <option value="On-Scene">On-Scene (Treating)</option>
                        <option value="Dispatched">Dispatched (En Route)</option>
                        <option value="Standby">Standby (Sanitizing)</option>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Dispatch Radio Channel</label>
                    <input type="text" name="contact_radio" value="EMS Dispatch Ch-1 (155.45 MHz)" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl font-medium focus:outline-none focus:border-teal-600">
                </div>
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                <button type="button" onclick="document.getElementById('addTeamModal').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold cursor-pointer">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-teal-600 hover:bg-teal-700 text-white font-bold cursor-pointer">Register Ambulance</button>
            </div>
        </form>
    </div>
</div>

<!-- 2. Dispatch Task Modal -->
<div id="createTaskModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white border border-slate-200 rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <h3 class="text-base font-black text-slate-900">Dispatch Medical EMS Mission</h3>
            <button type="button" onclick="document.getElementById('createTaskModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-700 cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="medical_hub.php" class="space-y-3 text-xs">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="create_task">

            <div>
                <label class="block font-bold text-slate-700 mb-1">Mission Title</label>
                <input type="text" name="title" required placeholder="e.g. Critical Patient Transfer & Oxygen Support" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl font-medium focus:outline-none focus:border-teal-600">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Priority</label>
                    <select name="priority" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-medium focus:outline-none focus:border-teal-600">
                        <option value="Critical">Critical Priority</option>
                        <option value="High" selected>High Priority</option>
                        <option value="Medium">Medium Priority</option>
                        <option value="Low">Low Priority</option>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Assigned Ambulance</label>
                    <select name="assigned_team" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-medium focus:outline-none focus:border-teal-600">
                        <?php foreach ($teams as $t): ?>
                            <option value="<?= htmlspecialchars($t['callsign'] ?: 'Ambulance #' . $t['id']) ?>"><?= htmlspecialchars($t['callsign'] ?: 'Ambulance #' . $t['id']) ?> - <?= htmlspecialchars($t['team_lead']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">Target Location</label>
                <input type="text" name="location" required placeholder="e.g. Mayur Vihar Sector 1 Relief Center" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl font-medium focus:outline-none focus:border-teal-600">
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">Patient Telemetry &amp; Trauma Notes</label>
                <textarea name="description" rows="3" placeholder="Provide patient condition, injuries, required oxygen liters, destination ER..." class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl font-medium focus:outline-none focus:border-teal-600"></textarea>
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                <button type="button" onclick="document.getElementById('createTaskModal').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold cursor-pointer">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-teal-600 hover:bg-teal-700 text-white font-bold cursor-pointer">Dispatch Ambulance</button>
            </div>
        </form>
    </div>
</div>

<!-- 3. Update Team Status Modal -->
<div id="updateTeamModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white border border-slate-200 rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <h3 class="text-base font-black text-slate-900" id="teamModalTitle">Update Ambulance Status</h3>
            <button type="button" onclick="document.getElementById('updateTeamModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-700 cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="medical_hub.php" class="space-y-3 text-xs">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="update_team_status">
            <input type="hidden" name="team_id" id="modal_team_id">

            <div>
                <label class="block font-bold text-slate-700 mb-1">Ambulance Readiness Status</label>
                <select name="status" id="modal_team_status" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-medium focus:outline-none focus:border-teal-600">
                    <option value="Available">Available (Ready at Bay)</option>
                    <option value="On-Scene">On-Scene (Treating / Transporting Patient)</option>
                    <option value="Dispatched">Dispatched (En Route to Casualty)</option>
                    <option value="Standby">Standby (Sanitizing / Restocking O2)</option>
                </select>
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">Current Patient Care Assignment</label>
                <input type="text" name="current_task" id="modal_team_task" placeholder="e.g. Transferring Patient to AIIMS Trauma" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl font-medium focus:outline-none focus:border-teal-600">
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                <button type="button" onclick="document.getElementById('updateTeamModal').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold cursor-pointer">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-teal-600 hover:bg-teal-700 text-white font-bold cursor-pointer">Save Status</button>
            </div>
        </form>
    </div>
</div>

<!-- 4. Allocate Resource Modal -->
<div id="allocateModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white border border-slate-200 rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <h3 class="text-base font-black text-slate-900" id="resModalTitle">Allocate Medical Resource</h3>
            <button type="button" onclick="document.getElementById('allocateModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-700 cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="medical_hub.php" class="space-y-3 text-xs">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="allocate_resource">
            <input type="hidden" name="resource_id" id="modal_res_id">

            <p class="text-slate-500 font-medium" id="modal_res_info">Available in trauma store: 0</p>

            <div>
                <label class="block font-bold text-slate-700 mb-1">Quantity to Allocate</label>
                <input type="number" name="quantity" min="1" required class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl font-medium focus:outline-none focus:border-teal-600">
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                <button type="button" onclick="document.getElementById('allocateModal').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold cursor-pointer">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-teal-600 hover:bg-teal-700 text-white font-bold cursor-pointer">Confirm Allocation</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== LEAFLET MAP SCRIPT ==================== -->
<script>
let medicalMap = null;

function initMedicalMap() {
    if (medicalMap) return;
    const mapEl = document.getElementById('medicalTacticalMap');
    if (!mapEl) return;

    medicalMap = L.map('medicalTacticalMap').setView([28.5672, 77.2100], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(medicalMap);

    // Plot Hospitals
    <?php foreach ($stations as $st): ?>
        <?php if (!empty($st['gps_lat']) && !empty($st['gps_lng'])): ?>
            L.circleMarker([<?= (float)$st['gps_lat'] ?>, <?= (float)$st['gps_lng'] ?>], {
                radius: 9,
                fillColor: '#0d9488',
                color: '#ffffff',
                weight: 2.5,
                opacity: 1,
                fillOpacity: 0.95
            }).addTo(medicalMap).bindPopup(`
                <div style="font-family: sans-serif; font-size: 12px;">
                    <strong style="color: #0d9488;">🏥 <?= htmlspecialchars($st['station_name']) ?></strong><br>
                    <strong>Address:</strong> <?= htmlspecialchars($st['address'] ?? 'Trauma Center HQ') ?><br>
                    <strong>Medical Personnel:</strong> <?= (int)$st['personnel_count'] ?><br>
                    <strong>Emergency Hotline:</strong> <?= htmlspecialchars($st['contact_phone'] ?? '108') ?><br>
                    <strong>Dispatch Radio:</strong> <?= htmlspecialchars($st['radio_channel'] ?? 'EMS Dispatch Ch-1') ?>
                </div>
            `);
        <?php endif; ?>
    <?php endforeach; ?>

    // Plot Medical SOS Distress beacons
    <?php foreach ($medicalSos as $sos): ?>
        <?php if (!empty($sos['gps_lat']) && !empty($sos['gps_lng'])): ?>
            L.circleMarker([<?= (float)$sos['gps_lat'] ?>, <?= (float)$sos['gps_lng'] ?>], {
                radius: 8,
                fillColor: '#dc2626',
                color: '#ffffff',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.9
            }).addTo(medicalMap).bindPopup(`
                <div style="font-family: sans-serif; font-size: 12px;">
                    <strong style="color: #dc2626;">🚨 <?= htmlspecialchars($sos['emergency_type']) ?> (#<?= $sos['id'] ?>)</strong><br>
                    <strong>Citizen:</strong> <?= htmlspecialchars($sos['sender_name']) ?><br>
                    <strong>Phone:</strong> <?= htmlspecialchars($sos['sender_phone']) ?><br>
                    <strong>Priority:</strong> <?= htmlspecialchars($sos['priority']) ?>
                </div>
            `);
        <?php endif; ?>
    <?php endforeach; ?>
}

function openAddTeamModal() {
    document.getElementById('addTeamModal').classList.remove('hidden');
}

function openCreateTaskModal() {
    document.getElementById('createTaskModal').classList.remove('hidden');
}

function openUpdateTeamModal(id, callsign, status, task) {
    document.getElementById('modal_team_id').value = id;
    document.getElementById('teamModalTitle').innerText = `Update Status for ${callsign}`;
    document.getElementById('modal_team_status').value = status;
    document.getElementById('modal_team_task').value = task || '';
    document.getElementById('updateTeamModal').classList.remove('hidden');
}

function openAllocateModal(id, name, available, unit) {
    document.getElementById('modal_res_id').value = id;
    document.getElementById('resModalTitle').innerText = `Allocate ${name}`;
    document.getElementById('modal_res_info').innerText = `Available in Trauma Store: ${available} ${unit}`;
    document.getElementById('allocateModal').classList.remove('hidden');
}

document.addEventListener('DOMContentLoaded', function() {
    initMedicalMap();
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
