<?php
// volunteer.php - DisasterSafe Volunteer Corps Command Hub (Unified Architecture)
define('PAGE_TITLE', 'Volunteer Command Hub');
require_once __DIR__ . '/auth.php';

requireLogin();
$currentUser = getCurrentUser($pdo);

// Role & Permission Checks
$isSuperAdmin = isSuperAdmin($currentUser);
$hasVolunteerAccess = $isSuperAdmin || hasPermission($currentUser, 'access_volunteer');
if (!$hasVolunteerAccess) {
    setFlash('error', 'Access denied. You do not have permission to access the Volunteer Command Hub.');
    header("Location: dashboard.php");
    exit;
}

$csrfToken = generateCsrfToken();
$userId = $currentUser['id'] ?? 0;
$userName = $currentUser['name'] ?? 'Volunteer';
$userPhone = $currentUser['phone'] ?? '+91 98765 43210';

// Handle POST Actions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token. Please refresh and retry.');
        header("Location: volunteer.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    // 1. UPDATE VOLUNTEER DUTY / FIELD STATUS
    if ($action === 'update_duty_status') {
        $status = trim($_POST['status'] ?? 'Available');
        $zone = trim($_POST['assigned_zone'] ?? 'Central Disaster Zone');
        $lat = filter_var($_POST['latitude'] ?? 28.6139, FILTER_VALIDATE_FLOAT);
        $lng = filter_var($_POST['longitude'] ?? 77.2090, FILTER_VALIDATE_FLOAT);

        try {
            $pdo->prepare("
                INSERT INTO volunteer_locations (user_id, latitude, longitude, status, updated_at) 
                VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
                ON CONFLICT(user_id) DO UPDATE SET latitude=excluded.latitude, longitude=excluded.longitude, status=excluded.status, updated_at=CURRENT_TIMESTAMP
            ")->execute([$userId, $lat, $lng, strtolower($status)]);
        } catch (Exception $e) {
            try {
                $pdo->prepare("INSERT OR REPLACE INTO volunteer_locations (user_id, latitude, longitude, status, updated_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)")
                    ->execute([$userId, $lat, $lng, strtolower($status)]);
            } catch (Exception $ex) {}
        }

        try {
            $pdo->prepare("UPDATE volunteers SET availability_status = ?, current_location = ? WHERE user_id = ? OR email = ?")
                ->execute([$status, $zone, $userId, $currentUser['email'] ?? '']);
        } catch (Exception $e) {}

        logActivity($pdo, 'VOLUNTEER_STATUS_UPDATE', "Volunteer {$userName} set status to {$status} [Zone: {$zone}]");
        setFlash('success', "Your field duty status has been updated to '{$status}'.");
        header("Location: volunteer.php");
        exit;
    }

    // 2. CREATE NEW RELIEF MISSION / TASK
    if ($action === 'create_task') {
        $disasterId = (int)($_POST['disaster_id'] ?? 1);
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? 'Relief Distribution');
        $location = trim($_POST['location'] ?? '');
        $requiredVolunteers = (int)($_POST['required_volunteers'] ?? 5);
        $description = trim($_POST['description'] ?? '');

        if ($title && $location) {
            $stmt = $pdo->prepare("
                INSERT INTO volunteer_tasks (disaster_id, title, category, location, required_volunteers, assigned_volunteers_count, status, description)
                VALUES (?, ?, ?, ?, ?, 1, 'In Progress', ?)
            ");
            $stmt->execute([$disasterId, $title, $category, $location, $requiredVolunteers, $description]);
            $taskId = $pdo->lastInsertId();

            // Assign creator to the task
            try {
                $pdo->prepare("INSERT INTO task_assignments (task_id, user_id, status, notes) VALUES (?, ?, 'Accepted', 'Task Lead / Creator')")
                    ->execute([$taskId, $userId]);
            } catch (Exception $e) {}

            logActivity($pdo, 'VOLUNTEER_TASK_CREATED', "New relief mission #{$taskId} '{$title}' launched at {$location}");
            setFlash('success', "Relief mission '{$title}' launched successfully.");
        } else {
            setFlash('error', 'Please provide a Mission Title and Target Location.');
        }
        header("Location: volunteer.php");
        exit;
    }

    // 3. CLAIM / ACCEPT TASK
    if ($action === 'claim_task') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        if ($taskId > 0) {
            $check = $pdo->prepare("SELECT id FROM task_assignments WHERE task_id = ? AND user_id = ?");
            $check->execute([$taskId, $userId]);
            if (!$check->fetch()) {
                $pdo->prepare("INSERT INTO task_assignments (task_id, user_id, status, notes) VALUES (?, ?, 'Accepted', 'Joined by Volunteer')")
                    ->execute([$taskId, $userId]);
                $pdo->prepare("UPDATE volunteer_tasks SET assigned_volunteers_count = assigned_volunteers_count + 1 WHERE id = ?")
                    ->execute([$taskId]);
                logActivity($pdo, 'VOLUNTEER_TASK_CLAIMED', "Volunteer {$userName} joined mission #{$taskId}");
                setFlash('success', "You have joined this relief mission!");
            } else {
                setFlash('info', "You are already assigned to this mission.");
            }
        }
        header("Location: volunteer.php");
        exit;
    }

    // 4. UPDATE TASK STATUS
    if ($action === 'update_task_status') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $status = trim($_POST['status'] ?? 'Completed');
        if ($taskId > 0) {
            $pdo->prepare("UPDATE volunteer_tasks SET status = ? WHERE id = ?")->execute([$status, $taskId]);
            logActivity($pdo, 'VOLUNTEER_TASK_STATUS', "Mission #{$taskId} status updated to {$status}");
            setFlash('success', "Mission status changed to {$status}.");
        }
        header("Location: volunteer.php");
        exit;
    }

    // 5. LOG AID / SUPPLIES DISTRIBUTION
    if ($action === 'distribute_aid') {
        $resId = (int)($_POST['resource_id'] ?? 0);
        $destName = trim($_POST['destination_name'] ?? 'Evacuation Shelter A');
        $destType = trim($_POST['destination_type'] ?? 'Shelter');
        $address = trim($_POST['location_address'] ?? 'Sector 4 Relief Camp');
        $quantity = (int)($_POST['quantity'] ?? 0);
        $notes = trim($_POST['notes'] ?? 'Emergency dry rations & water distribution');

        $resStmt = $pdo->prepare("SELECT * FROM master_resources WHERE id = ?");
        $resStmt->execute([$resId]);
        $resource = $resStmt->fetch();

        if ($resource && $quantity > 0 && $quantity <= $resource['available_stock']) {
            $newAvail = $resource['available_stock'] - $quantity;
            $newDist = $resource['distributed_stock'] + $quantity;
            $pdo->prepare("UPDATE master_resources SET available_stock = ?, distributed_stock = ? WHERE id = ?")
                ->execute([$newAvail, $newDist, $resId]);

            $distStmt = $pdo->prepare("
                INSERT INTO resource_distributions (resource_id, destination_type, destination_name, location_address, gps_lat, gps_lng, quantity_distributed, unit, dispatched_by, contact_officer, distribution_status, notes)
                VALUES (?, ?, ?, ?, 28.6139, 77.2090, ?, ?, ?, ?, 'Delivered', ?)
            ");
            $distStmt->execute([$resId, $destType, $destName, $address, $quantity, $resource['unit'], $userName, $userPhone, $notes]);

            logActivity($pdo, 'RELIEF_AID_DISTRIBUTED', "Distributed {$quantity} {$resource['unit']} of {$resource['name']} to {$destName}");
            setFlash('success', "Logged distribution of {$quantity} {$resource['unit']} to {$destName}.");
        } else {
            setFlash('error', 'Invalid quantity or insufficient warehouse stock.');
        }
        header("Location: volunteer.php");
        exit;
    }

    // 6. ACCEPT / RESPOND TO CITIZEN SOS
    if ($action === 'accept_sos_rescue') {
        $sosId = (int)($_POST['sos_id'] ?? 0);
        $eta = (int)($_POST['eta_minutes'] ?? 10);
        if ($sosId > 0) {
            $pdo->prepare("
                UPDATE emergency_sos 
                SET status = 'Dispatched', assigned_unit = ?, eta_minutes = ?, responder_name = ?, responder_phone = ? 
                WHERE id = ?
            ")->execute(["Volunteer Squad ({$userName})", $eta, $userName, $userPhone, $sosId]);

            // Add automatic chat confirmation
            try {
                $pdo->prepare("
                    INSERT INTO victim_volunteer_chats (sos_id, sender_id, sender_name, sender_role, message)
                    VALUES (?, ?, ?, 'volunteer', ?)
                ")->execute([$sosId, $userId, $userName, "Volunteer responder {$userName} has accepted your distress call. We are en-route with relief supplies. ETA: {$eta} mins."]);
            } catch (Exception $e) {}

            logActivity($pdo, 'VOLUNTEER_SOS_ACCEPTED', "Volunteer {$userName} accepted SOS #{$sosId} [ETA: {$eta}m]");
            setFlash('success', "SOS #{$sosId} accepted. You are now the assigned responder team.");
        }
        header("Location: volunteer.php");
        exit;
    }

    // 7. SEND DIRECT CHAT TO VICTIM
    if ($action === 'send_victim_chat') {
        $sosId = (int)($_POST['sos_id'] ?? 0);
        $msg = trim($_POST['message'] ?? '');
        if ($sosId > 0 && !empty($msg)) {
            $pdo->prepare("
                INSERT INTO victim_volunteer_chats (sos_id, sender_id, sender_name, sender_role, message)
                VALUES (?, ?, ?, 'volunteer', ?)
            ")->execute([$sosId, $userId, $userName, $msg]);
            setFlash('success', 'Message sent to victim.');
        }
        header("Location: volunteer.php?active_sos={$sosId}");
        exit;
    }
}

// Fetch Key Data for Hub
$statsOpenTasks = (int)$pdo->query("SELECT COUNT(*) FROM volunteer_tasks WHERE status = 'Open'")->fetchColumn();
$statsInProgTasks = (int)$pdo->query("SELECT COUNT(*) FROM volunteer_tasks WHERE status = 'In Progress'")->fetchColumn();
$statsTotalVolunteers = (int)$pdo->query("SELECT COUNT(*) FROM volunteers")->fetchColumn() ?: 12;
$statsActiveVolunteers = (int)$pdo->query("SELECT COUNT(*) FROM volunteers WHERE availability_status != 'Inactive'")->fetchColumn() ?: 8;
$statsDistributions = (int)$pdo->query("SELECT COUNT(*) FROM resource_distributions")->fetchColumn() ?: 24;
$statsSosPending = (int)$pdo->query("SELECT COUNT(*) FROM emergency_sos WHERE status != 'Resolved' AND (dispatch_agency LIKE '%Volunteer%' OR emergency_type IN ('Flood', 'Missing Person', 'Trapped'))")->fetchColumn();

// Fetch Active Disaster for context
$activeDisasters = $pdo->query("SELECT * FROM disasters WHERE status = 'Active' ORDER BY id DESC")->fetchAll();
$primaryDisaster = $activeDisasters[0] ?? ['id' => 1, 'title' => 'National Disaster Relief Operations', 'location' => 'Capital NCR Region'];

// Fetch Tasks
$tasksStmt = $pdo->query("
    SELECT vt.*, d.title as disaster_title 
    FROM volunteer_tasks vt 
    LEFT JOIN disasters d ON vt.disaster_id = d.id 
    ORDER BY CASE vt.status WHEN 'Open' THEN 1 WHEN 'In Progress' THEN 2 ELSE 3 END, vt.id DESC 
    LIMIT 15
");
$allTasks = $tasksStmt->fetchAll();

// Fetch Assigned Tasks for Current User
$myTasksStmt = $pdo->prepare("
    SELECT vt.*, ta.status as assignment_status 
    FROM task_assignments ta 
    JOIN volunteer_tasks vt ON ta.task_id = vt.id 
    WHERE ta.user_id = ? 
    ORDER BY vt.id DESC
");
$myTasksStmt->execute([$userId]);
$myTasks = $myTasksStmt->fetchAll();

// Fetch Direct Citizen SOS Queue (Waiting for Assistance)
$sosQueueStmt = $pdo->query("
    SELECT * FROM emergency_sos 
    WHERE status != 'Resolved' 
    ORDER BY CASE priority WHEN 'Critical' THEN 1 WHEN 'High' THEN 2 ELSE 3 END, id DESC 
    LIMIT 8
");
$sosDistressList = $sosQueueStmt->fetchAll();

// Fetch Warehouse Resources
$resourcesList = $pdo->query("SELECT * FROM master_resources ORDER BY available_stock ASC LIMIT 10")->fetchAll();

// Fetch Facilities for Shelter Radar
$sheltersList = $pdo->query("SELECT * FROM facilities WHERE status != 'Closed' LIMIT 12")->fetchAll();

// Fetch Recent Field Comms
$commsList = $pdo->query("
    SELECT cm.*, u.name as sender_name, r.slug as sender_role 
    FROM comms_messages cm 
    LEFT JOIN users u ON cm.sender_id = u.id 
    LEFT JOIN roles r ON u.role_id = r.id 
    ORDER BY cm.id DESC LIMIT 10
")->fetchAll();

// Fetch Volunteer User Status
$myVolRecord = $pdo->query("SELECT * FROM volunteers WHERE user_id = {$userId} OR email = " . $pdo->quote($currentUser['email'] ?? ''))->fetch();
$currentDutyStatus = $myVolRecord['availability_status'] ?? 'Available';
$currentAssignedZone = $myVolRecord['current_location'] ?? 'NCR Sector 4 Base';

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div class="flex-1 flex flex-col min-w-0 bg-[#f8fafc] min-h-screen overflow-y-auto">
    <?php require_once __DIR__ . '/navbar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto w-full">

        <!-- 1. Hero Volunteer Command Banner -->
        <section class="bg-gradient-to-r from-emerald-950 via-slate-900 to-emerald-900 rounded-3xl p-6 sm:p-8 text-white shadow-xl relative overflow-hidden border border-emerald-800/40">
            <div class="absolute -right-10 -bottom-10 w-72 h-72 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute right-20 top-0 w-48 h-48 bg-teal-500/10 rounded-full blur-2xl pointer-events-none"></div>

            <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                <div class="space-y-2 max-w-2xl">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/20 border border-emerald-400/30 text-emerald-300 text-xs font-bold mono">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                        <i class="fa-solid fa-hand-holding-heart text-xs"></i>
                        <span>VOLUNTEER RELIEF CORPS • FIELD HQ</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black tracking-tight text-white">
                        Volunteer Operations &amp; Relief Hub
                    </h1>
                    <p class="text-sm text-slate-300 font-medium leading-relaxed">
                        Coordinating grassroots humanitarian missions, immediate victim search aid, evacuation shelter support, and real-time relief aid logistics.
                    </p>
                    <div class="flex flex-wrap items-center gap-4 pt-2 text-xs font-bold text-slate-300">
                        <span class="flex items-center gap-1.5"><i class="fa-solid fa-shield text-emerald-400"></i> Zone: <strong class="text-white"><?= htmlspecialchars($currentAssignedZone) ?></strong></span>
                        <span class="flex items-center gap-1.5"><i class="fa-solid fa-user-tag text-teal-400"></i> Responder: <strong class="text-white"><?= htmlspecialchars($userName) ?></strong></span>
                        <span class="flex items-center gap-1.5"><i class="fa-solid fa-circle-check text-emerald-400"></i> Status: <span class="px-2 py-0.5 rounded-md bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 font-bold"><?= htmlspecialchars($currentDutyStatus) ?></span></span>
                    </div>
                </div>

                <!-- Right Quick Controls -->
                <div class="flex flex-col sm:flex-row lg:flex-col gap-2.5 shrink-0">
                    <button type="button" onclick="openCreateTaskModal()" class="px-4 py-2.5 rounded-2xl bg-emerald-500 hover:bg-emerald-600 text-slate-950 text-xs font-black transition-all shadow-lg flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-plus-circle text-sm"></i>
                        <span>Launch Relief Mission</span>
                    </button>
                    <button type="button" onclick="openDistributeAidModal()" class="px-4 py-2.5 rounded-2xl bg-white/10 hover:bg-white/20 text-white border border-white/20 text-xs font-bold transition-all flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-box-open text-sm text-amber-400"></i>
                        <span>Log Aid Distribution</span>
                    </button>
                    <button type="button" onclick="openDutyStatusModal()" class="px-4 py-2.5 rounded-2xl bg-slate-800/80 hover:bg-slate-800 text-slate-200 border border-slate-700 text-xs font-bold transition-all flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-location-crosshairs text-sm text-teal-400"></i>
                        <span>Update GPS / Duty Status</span>
                    </button>
                </div>
            </div>
        </section>

        <!-- 2. KPI Metrics Grid -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
            <!-- Active Missions -->
            <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 mono">Active Missions</span>
                    <h3 class="text-2xl font-black text-slate-900 mt-1"><?= $statsInProgTasks + $statsOpenTasks ?></h3>
                    <p class="text-xs text-emerald-600 font-bold mt-0.5 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        <?= $statsOpenTasks ?> Open • <?= $statsInProgTasks ?> In-Field
                    </p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-list-check"></i>
                </div>
            </div>

            <!-- Mobilized Volunteers -->
            <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 mono">Ground Volunteers</span>
                    <h3 class="text-2xl font-black text-slate-900 mt-1"><?= $statsTotalVolunteers ?></h3>
                    <p class="text-xs text-blue-600 font-bold mt-0.5 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                        <?= $statsActiveVolunteers ?> Ready for Deployment
                    </p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-700 border border-blue-200 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-users-gear"></i>
                </div>
            </div>

            <!-- Relief Supplies Dispatched -->
            <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 mono">Aid Dispatches</span>
                    <h3 class="text-2xl font-black text-slate-900 mt-1"><?= $statsDistributions ?></h3>
                    <p class="text-xs text-amber-600 font-bold mt-0.5 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                        Shelter &amp; Field Rations
                    </p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-700 border border-amber-200 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-truck-ramp-box"></i>
                </div>
            </div>

            <!-- Direct Victim SOS Distress -->
            <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 mono">SOS Distress Queue</span>
                    <h3 class="text-2xl font-black text-red-600 mt-1"><?= $statsSosPending ?></h3>
                    <p class="text-xs text-red-600 font-bold mt-0.5 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-ping"></span>
                        Civilians Awaiting Relief
                    </p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-red-50 text-red-600 border border-red-200 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-tower-broadcast animate-pulse"></i>
                </div>
            </div>
        </section>

        <!-- 3. Tactical Map & Live Field Comms Grid -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Tactical Operations Map (2 cols) -->
            <div class="lg:col-span-2 bg-white rounded-3xl border border-slate-200 shadow-sm p-5 flex flex-col">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center text-sm font-bold">
                            <i class="fa-solid fa-map-location-dot"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-slate-900">Tactical Volunteer GIS Map</h3>
                            <p class="text-[10px] text-slate-500 font-medium">Live GPS Pins • Citizen SOS Distress • Safe Shelters • Aid Depots</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 mono">
                            <i class="fa-solid fa-satellite text-[8px] mr-1"></i> GIS ACTIVE
                        </span>
                    </div>
                </div>

                <!-- Leaflet Map Container -->
                <div id="volunteerTacticalMap" class="w-full h-80 rounded-2xl bg-slate-100 border border-slate-200 overflow-hidden relative z-0"></div>

                <!-- Map Legend -->
                <div class="pt-3 mt-3 border-t border-slate-100 flex flex-wrap items-center justify-between text-[11px] font-bold text-slate-600 gap-2">
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-red-600"></span> Citizen SOS Distress</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-600"></span> Volunteer Squads</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-blue-600"></span> Evacuation Shelters</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-600"></span> Relief Aid Depots</span>
                </div>
            </div>

            <!-- Live Field Comms Ticker (1 col) -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-5 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-3">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-xl bg-teal-50 text-teal-700 flex items-center justify-center text-sm font-bold">
                                <i class="fa-solid fa-walkie-talkie"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-slate-900">Volunteer Radio Comms</h3>
                                <p class="text-[10px] text-slate-500 font-medium">Tactical Ground Channel (#ops)</p>
                            </div>
                        </div>
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    </div>

                    <!-- Comms Stream -->
                    <div class="space-y-2.5 max-h-72 overflow-y-auto pr-1" id="commsMessageList">
                        <?php if (empty($commsList)): ?>
                            <p class="text-xs text-slate-400 italic text-center py-6">No radio comms logged yet.</p>
                        <?php else: ?>
                            <?php foreach ($commsList as $comm): ?>
                                <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200/80 text-xs">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="font-black text-slate-900 text-[11px] flex items-center gap-1.5">
                                            <i class="fa-solid fa-user-circle text-slate-400"></i>
                                            <?= htmlspecialchars($comm['sender_name'] ?? 'Responder') ?>
                                        </span>
                                        <span class="text-[9px] font-mono text-slate-400"><?= date('H:i', strtotime($comm['created_at'])) ?></span>
                                    </div>
                                    <p class="text-slate-700 leading-relaxed font-medium"><?= htmlspecialchars($comm['message']) ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Broadcast Input Form -->
                <form method="POST" action="volunteer.php" class="pt-3 mt-3 border-t border-slate-100">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="update_duty_status">
                    <div class="flex items-center gap-2">
                        <input type="text" id="quickRadioInput" placeholder="Radio check or field update..." class="flex-1 px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-emerald-600 font-medium">
                        <button type="button" onclick="sendQuickRadioMsg()" class="p-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-all shadow-xs cursor-pointer">
                            <i class="fa-solid fa-paper-plane"></i>
                        </button>
                    </div>
                </form>
            </div>

        </section>

        <!-- 4. Multi-Victim Direct Hotline Live Chat Hub -->
        <section id="directVictimChatCard" class="bg-white rounded-3xl border border-slate-200 shadow-sm p-5 sm:p-6 space-y-4 relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-1.5 bg-gradient-to-r from-blue-600 via-amber-500 to-rose-500"></div>

            <!-- Hub Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100 pt-1">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-blue-50 text-[#1d63d8] border border-blue-200 flex items-center justify-center text-base font-bold">
                        <i class="fa-solid fa-headset"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base sm:text-lg font-black text-slate-900">Citizen &amp; Victim Live Hotlines</h2>
                            <span id="victimThreadsCountBadge" class="bg-rose-100 text-rose-800 text-[10px] font-mono font-extrabold px-2.5 py-0.5 rounded-full uppercase tracking-wider">
                                <?= count($sosDistressList) ?> ACTIVE
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 font-medium">Real-time two-way communication lifeline with trapped civilians and distress callers</p>
                    </div>
                </div>

                <div class="flex items-center gap-2 self-start sm:self-auto">
                    <button type="button" onclick="simulateVictimReply()" title="Test simulated citizen response" class="px-3.5 py-2 bg-amber-50 hover:bg-amber-100 border border-amber-300 text-amber-900 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer shadow-2xs">
                        <i class="fa-solid fa-robot text-amber-600 text-xs"></i>
                        <span>Simulate Reply</span>
                    </button>
                    <a href="tel:112" class="px-3.5 py-2 bg-emerald-50 hover:bg-emerald-100 border border-emerald-300 text-emerald-900 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer shadow-2xs">
                        <i class="fa-solid fa-phone text-emerald-600 text-xs"></i>
                        <span>Speed Dial 112</span>
                    </a>
                </div>
            </div>

            <!-- Horizontal Victim Threads Selector Carousel -->
            <div class="space-y-1.5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mono">Active Distress Caller Threads:</span>
                <div id="victimThreadList" class="flex gap-2.5 overflow-x-auto pb-2 custom-scroll">
                    <!-- Dynamically populated threads -->
                </div>
            </div>

            <!-- Live Hotline Chat Box Container -->
            <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 space-y-3">
                <!-- Active Victim Thread Info Header -->
                <div class="flex items-center justify-between pb-2.5 border-b border-slate-200/80">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-full bg-red-100 text-red-700 flex items-center justify-center font-bold text-xs">
                            <i class="fa-solid fa-person-circle-exclamation"></i>
                        </div>
                        <div>
                            <h4 id="activeVictimNameDisplay" class="text-xs font-extrabold text-slate-900">Connecting to Citizen...</h4>
                            <p id="activeVictimDetailsDisplay" class="text-[10px] text-slate-500 font-mono">Loading SOS Telemetry...</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span id="activeVictimStatusBadge" class="px-2 py-0.5 rounded text-[9px] font-black uppercase mono bg-amber-100 text-amber-800">Pending</span>
                        <a id="activeVictimCallBtn" href="tel:112" class="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-slate-700 hover:text-slate-900 text-xs font-bold shadow-2xs flex items-center gap-1">
                            <i class="fa-solid fa-phone text-[10px] text-emerald-600"></i> Call
                        </a>
                    </div>
                </div>

                <!-- Messages Stream -->
                <div id="directVictimChatFeed" class="flex flex-col gap-2.5 max-h-[220px] min-h-[140px] overflow-y-auto p-1 custom-scroll text-xs">
                    <div class="text-xs text-slate-400 italic text-center py-6">Connecting direct line with citizen...</div>
                </div>

                <!-- Quick Preset Response Chips -->
                <div class="flex items-center gap-1.5 overflow-x-auto pt-1 pb-1">
                    <span class="text-[10px] font-bold text-slate-400 mono shrink-0">Quick Presets:</span>
                    <button type="button" onclick="sendDirectVictimMsg('🚑 Volunteer rescue team is en route! ETA approx 2-3 mins.')" class="px-2.5 py-1 rounded-full bg-white border border-slate-200 hover:border-blue-400 text-[11px] font-bold text-slate-700 whitespace-nowrap shadow-2xs cursor-pointer transition-all">🚑 ETA 2 Mins</button>
                    <button type="button" onclick="sendDirectVictimMsg('🚨 Stay indoors on higher ground. Do not attempt to cross moving water.')" class="px-2.5 py-1 rounded-full bg-white border border-slate-200 hover:border-blue-400 text-[11px] font-bold text-slate-700 whitespace-nowrap shadow-2xs cursor-pointer transition-all">🚨 Stay Inside</button>
                    <button type="button" onclick="sendDirectVictimMsg('🚪 Please flash a light or wave a cloth at the window/balcony so our boat/team can spot you.')" class="px-2.5 py-1 rounded-full bg-white border border-slate-200 hover:border-blue-400 text-[11px] font-bold text-slate-700 whitespace-nowrap shadow-2xs cursor-pointer transition-all">🚪 Signal Window</button>
                    <button type="button" onclick="sendDirectVictimMsg('🩺 Medical first-aid squad with trauma kit & stretcher has been assigned to your location.')" class="px-2.5 py-1 rounded-full bg-white border border-slate-200 hover:border-blue-400 text-[11px] font-bold text-slate-700 whitespace-nowrap shadow-2xs cursor-pointer transition-all">🩺 Med Team Assigned</button>
                    <button type="button" onclick="sendDirectVictimMsg('🍞 Drinking water and food ration packets are being delivered to your shelter point.')" class="px-2.5 py-1 rounded-full bg-white border border-slate-200 hover:border-blue-400 text-[11px] font-bold text-slate-700 whitespace-nowrap shadow-2xs cursor-pointer transition-all">🍞 Supplies Ready</button>
                </div>

                <!-- Live Send Input Form -->
                <form id="directVictimChatForm" onsubmit="handleSendDirectVictimMsg(event)" class="flex items-center gap-2 pt-1">
                    <input type="text" id="directVictimInput" class="flex-1 bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:outline-none focus:border-blue-600 font-medium placeholder-slate-400 shadow-2xs" placeholder="Type direct reply to citizen..." required />
                    <button type="submit" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-xs transition-colors flex items-center justify-center gap-1.5 cursor-pointer shrink-0">
                        <i class="fa-solid fa-paper-plane text-xs"></i>
                        <span>Send</span>
                    </button>
                </form>
            </div>
        </section>

        <!-- 5. Direct Citizen SOS Rescue & Assistance Queue -->
        <section class="bg-white rounded-3xl border border-slate-200 shadow-sm p-5 sm:p-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 mb-4 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-red-50 text-red-600 border border-red-200 flex items-center justify-center text-base font-bold">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div>
                        <h2 class="text-base sm:text-lg font-black text-slate-900">Direct Citizen Distress &amp; SOS Rescue Queue</h2>
                        <p class="text-xs text-slate-500 font-medium">Live distress calls requiring immediate volunteer aid, food, first-aid, or extraction support</p>
                    </div>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-black bg-red-50 text-red-700 border border-red-200 mono self-start sm:self-auto">
                    <?= count($sosDistressList) ?> Active Signals
                </span>
            </div>

            <?php if (empty($sosDistressList)): ?>
                <div class="text-center py-8 bg-slate-50 rounded-2xl border border-slate-200 text-slate-500 text-xs font-bold">
                    <i class="fa-solid fa-circle-check text-2xl text-emerald-500 mb-2 block"></i>
                    All citizen SOS distress beacons in this sector are currently resolved!
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($sosDistressList as $sos): ?>
                        <div class="p-4 rounded-2xl border <?= $sos['priority'] === 'Critical' ? 'border-red-300 bg-red-50/40' : 'border-slate-200 bg-white' ?> shadow-2xs flex flex-col justify-between space-y-3">
                            <div>
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase mono <?= $sos['priority'] === 'Critical' ? 'bg-red-600 text-white' : 'bg-amber-100 text-amber-800' ?>">
                                        <?= htmlspecialchars($sos['priority']) ?> PRIORITY
                                    </span>
                                    <span class="text-[10px] font-mono text-slate-400"><?= date('M d, H:i', strtotime($sos['created_at'])) ?></span>
                                </div>
                                <h4 class="text-sm font-black text-slate-900 flex items-center justify-between">
                                    <span><?= htmlspecialchars($sos['sender_name'] ?: 'Distressed Citizen') ?></span>
                                    <span class="text-xs font-mono font-bold text-slate-600"><i class="fa-solid fa-phone text-xs mr-1 text-slate-400"></i><?= htmlspecialchars($sos['sender_phone'] ?: 'N/A') ?></span>
                                </h4>
                                <div class="mt-2 text-xs text-slate-600 space-y-1 font-medium">
                                    <p><strong class="text-slate-800">Distress Type:</strong> <?= htmlspecialchars($sos['emergency_type'] ?? 'General Emergency') ?> (<?= $sos['persons_count'] ?> Persons in Danger)</p>
                                    <?php if (!empty($sos['message'])): ?>
                                        <p class="p-2 rounded-lg bg-white border border-slate-200 text-slate-700 italic text-[11px]">
                                            "<?= htmlspecialchars($sos['message']) ?>"
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="pt-3 border-t border-slate-200/80 flex items-center justify-between gap-2">
                                <div class="text-[11px] mono font-bold text-slate-500">
                                    Status: <span class="text-emerald-700 font-extrabold"><?= htmlspecialchars($sos['status']) ?></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <!-- Open Direct Chat Button -->
                                    <button type="button" onclick="openVictimChatModal(<?= $sos['id'] ?>, '<?= addslashes(htmlspecialchars($sos['sender_name'])) ?>')" class="px-3 py-1.5 rounded-xl bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer">
                                        <i class="fa-solid fa-comments text-xs"></i>
                                        <span>2-Way Chat</span>
                                    </button>

                                    <!-- Accept Rescue Dispatch -->
                                    <?php if ($sos['status'] === 'Pending'): ?>
                                        <form method="POST" action="volunteer.php" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="action" value="accept_sos_rescue">
                                            <input type="hidden" name="sos_id" value="<?= $sos['id'] ?>">
                                            <input type="hidden" name="eta_minutes" value="10">
                                            <button type="submit" class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-all shadow-xs flex items-center gap-1.5 cursor-pointer">
                                                <i class="fa-solid fa-truck-fast text-xs"></i>
                                                <span>Deploy Aid</span>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 rounded-xl bg-emerald-100 text-emerald-800 text-[11px] font-black mono border border-emerald-300">
                                            <i class="fa-solid fa-check mr-1"></i> Dispatched
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- 5. Relief Tasks & Missions Board -->
        <section class="bg-white rounded-3xl border border-slate-200 shadow-sm p-5 sm:p-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 mb-4 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center justify-center text-base font-bold">
                        <i class="fa-solid fa-list-check"></i>
                    </div>
                    <div>
                        <h2 class="text-base sm:text-lg font-black text-slate-900">Ground Relief Missions &amp; Tasks</h2>
                        <p class="text-xs text-slate-500 font-medium">Claim open missions, dispatch task forces, and track field deliverables</p>
                    </div>
                </div>
                <button type="button" onclick="openCreateTaskModal()" class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-all shadow-xs flex items-center gap-2 cursor-pointer self-start sm:self-auto">
                    <i class="fa-solid fa-plus-circle text-xs"></i>
                    <span>Add New Task</span>
                </button>
            </div>

            <!-- Tasks Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50/70 text-[10px] font-black uppercase tracking-wider text-slate-500 mono">
                            <th class="py-3 px-4">Mission Title</th>
                            <th class="py-3 px-4">Category</th>
                            <th class="py-3 px-4">Location</th>
                            <th class="py-3 px-4">Volunteers Required</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($allTasks)): ?>
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-400 italic">No relief missions found. Launch a new mission above!</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($allTasks as $task): ?>
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="py-3.5 px-4 font-bold text-slate-900">
                                        <span class="block text-slate-900 font-black"><?= htmlspecialchars($task['title']) ?></span>
                                        <span class="block text-[10px] text-slate-400 font-normal"><?= htmlspecialchars($task['description'] ?: 'Ground assistance mission') ?></span>
                                    </td>
                                    <td class="py-3.5 px-4 font-medium text-slate-700">
                                        <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 border border-slate-200 font-bold text-[10px] mono">
                                            <?= htmlspecialchars($task['category'] ?? 'General') ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-600 font-medium">
                                        <i class="fa-solid fa-location-dot text-slate-400 mr-1"></i>
                                        <?= htmlspecialchars($task['location']) ?>
                                    </td>
                                    <td class="py-3.5 px-4 font-mono font-bold text-slate-700">
                                        <?= $task['assigned_volunteers_count'] ?> / <?= $task['required_volunteers'] ?> Personnel
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <?php if ($task['status'] === 'Open'): ?>
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-blue-50 text-blue-700 border border-blue-200 mono">
                                                OPEN FOR CLAIMS
                                            </span>
                                        <?php elseif ($task['status'] === 'In Progress'): ?>
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-50 text-emerald-700 border border-emerald-200 mono">
                                                IN PROGRESS
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-slate-100 text-slate-600 border border-slate-200 mono">
                                                COMPLETED
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-right space-x-1.5">
                                        <?php if ($task['status'] === 'Open'): ?>
                                            <form method="POST" action="volunteer.php" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="action" value="claim_task">
                                                <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                                <button type="submit" class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-all shadow-xs cursor-pointer">
                                                    <i class="fa-solid fa-user-plus text-xs mr-1"></i> Join Mission
                                                </button>
                                            </form>
                                        <?php elseif ($task['status'] === 'In Progress'): ?>
                                            <form method="POST" action="volunteer.php" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="action" value="update_task_status">
                                                <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                                <input type="hidden" name="status" value="Completed">
                                                <button type="submit" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold transition-all shadow-xs cursor-pointer">
                                                    <i class="fa-solid fa-check text-xs mr-1"></i> Mark Done
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-[10px] text-slate-400 font-mono italic">Archived</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- 6. Relief Aid Inventory Ledger -->
        <section class="bg-white rounded-3xl border border-slate-200 shadow-sm p-5 sm:p-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 mb-4 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-700 border border-amber-200 flex items-center justify-center text-base font-bold">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                    <div>
                        <h2 class="text-base sm:text-lg font-black text-slate-900">Relief Aid Warehouse Stock &amp; Logistics</h2>
                        <p class="text-xs text-slate-500 font-medium">Track emergency rations, mineral water, medical kits, and fast dispatch records</p>
                    </div>
                </div>
                <button type="button" onclick="openDistributeAidModal()" class="px-3.5 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 text-xs font-black transition-all shadow-xs flex items-center gap-2 cursor-pointer self-start sm:self-auto">
                    <i class="fa-solid fa-dolly text-xs"></i>
                    <span>Dispatch Supplies</span>
                </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <?php foreach ($resourcesList as $res): ?>
                    <div class="p-4 rounded-2xl border border-slate-200 bg-slate-50/50 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[10px] font-black uppercase text-slate-500 mono"><?= htmlspecialchars($res['category']) ?></span>
                                <span class="px-2 py-0.5 rounded text-[9px] font-black mono <?= $res['available_stock'] < 50 ? 'bg-red-100 text-red-800' : 'bg-emerald-100 text-emerald-800' ?>">
                                    <?= htmlspecialchars($res['status'] ?? 'In Stock') ?>
                                </span>
                            </div>
                            <h4 class="text-sm font-black text-slate-900"><?= htmlspecialchars($res['name']) ?></h4>
                            <p class="text-xs text-slate-500 font-mono mt-1">
                                Available: <strong class="text-slate-900 font-extrabold"><?= number_format($res['available_stock']) ?> <?= htmlspecialchars($res['unit']) ?></strong>
                            </p>
                        </div>
                        <div class="pt-3 mt-3 border-t border-slate-200 flex items-center justify-between text-xs">
                            <span class="text-[10px] font-mono text-slate-400">Distributed: <?= number_format($res['distributed_stock']) ?></span>
                            <button type="button" onclick="openDistributeAidModalWithId(<?= $res['id'] ?>, '<?= addslashes(htmlspecialchars($res['name'])) ?>', <?= $res['available_stock'] ?>, '<?= htmlspecialchars($res['unit']) ?>')" class="text-xs text-emerald-700 hover:text-emerald-800 font-bold cursor-pointer">
                                Dispatch &rarr;
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

    </main>
</div>

<!-- ==================== MODALS ==================== -->

<!-- 1. LAUNCH RELIEF MISSION MODAL -->
<div id="createTaskModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white border border-slate-200 rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl relative max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-100 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-700 flex items-center justify-center">
                    <i class="fa-solid fa-hand-holding-heart text-base"></i>
                </div>
                <div>
                    <h3 class="text-lg font-black text-slate-900">Launch Relief Mission</h3>
                    <p class="text-xs text-slate-500 font-medium">Create a new field task for ground volunteers</p>
                </div>
            </div>
            <button type="button" onclick="closeCreateTaskModal()" class="text-slate-400 hover:text-slate-800 p-2 cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="volunteer.php" class="space-y-4 overflow-y-auto pr-1 flex-1">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="create_task">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Mission Title *</label>
                <input type="text" name="title" required placeholder="e.g. Evacuee Food Packet Distribution" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-emerald-600 font-medium">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Category *</label>
                    <select name="category" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:outline-none focus:border-emerald-600 font-medium">
                        <option value="Relief Distribution">Relief Distribution</option>
                        <option value="First Aid & Triage">First Aid &amp; Triage</option>
                        <option value="Search & Rescue Aid">Search &amp; Rescue Aid</option>
                        <option value="Shelter Setup">Shelter Bedding Setup</option>
                        <option value="Clean Water Supply">Clean Water Supply</option>
                        <option value="Elderly Assistance">Elderly Assistance</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Required Volunteers *</label>
                    <input type="number" name="required_volunteers" value="5" min="1" max="50" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:outline-none focus:border-emerald-600 font-medium">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Target Location / Camp *</label>
                <input type="text" name="location" required placeholder="e.g. Sector 4 Community Relief Shelter" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-emerald-600 font-medium">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Mission Directives &amp; Notes</label>
                <textarea name="description" rows="3" placeholder="Specify instructions, meeting point, and required gear..." class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-emerald-600 font-medium"></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 shrink-0">
                <button type="button" onclick="closeCreateTaskModal()" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors cursor-pointer">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black shadow-sm transition-all cursor-pointer">
                    Launch Mission
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 2. LOG AID DISTRIBUTION MODAL -->
<div id="distributeAidModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white border border-slate-200 rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl relative max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-100 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-700 flex items-center justify-center">
                    <i class="fa-solid fa-box-open text-base"></i>
                </div>
                <div>
                    <h3 class="text-lg font-black text-slate-900">Log Relief Aid Distribution</h3>
                    <p class="text-xs text-slate-500 font-medium">Record relief inventory delivered to camps and shelters</p>
                </div>
            </div>
            <button type="button" onclick="closeDistributeAidModal()" class="text-slate-400 hover:text-slate-800 p-2 cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="volunteer.php" class="space-y-4 overflow-y-auto pr-1 flex-1">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="distribute_aid">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Select Inventory Item *</label>
                <select name="resource_id" id="dist_resource_id" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:outline-none focus:border-amber-600 font-medium">
                    <?php foreach ($resourcesList as $res): ?>
                        <option value="<?= $res['id'] ?>"><?= htmlspecialchars($res['name']) ?> (Avail: <?= number_format($res['available_stock']) ?> <?= htmlspecialchars($res['unit']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Quantity to Dispatch *</label>
                    <input type="number" name="quantity" id="dist_quantity" value="50" min="1" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:outline-none focus:border-amber-600 font-medium">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Destination Type *</label>
                    <select name="destination_type" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:outline-none focus:border-amber-600 font-medium">
                        <option value="Shelter">Evacuation Shelter</option>
                        <option value="Hospital">Hospital / Clinic</option>
                        <option value="FieldCamp">Temporary Field Camp</option>
                        <option value="CommunityCenter">Community Hall</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Destination Name *</label>
                <input type="text" name="destination_name" required placeholder="e.g. Sector 4 Central High School Shelter" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-amber-600 font-medium">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Location Address</label>
                <input type="text" name="location_address" placeholder="e.g. Ring Road West, Near Gate 2" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-amber-600 font-medium">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Distribution Notes</label>
                <textarea name="notes" rows="2" placeholder="Recipient officer, condition of goods..." class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-amber-600 font-medium"></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 shrink-0">
                <button type="button" onclick="closeDistributeAidModal()" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors cursor-pointer">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 text-xs font-black shadow-sm transition-all cursor-pointer">
                    Record Distribution
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 3. 2-WAY VICTIM CHAT MODAL -->
<div id="victimChatModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white border border-slate-200 rounded-3xl max-w-lg w-full p-6 shadow-2xl relative max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-100 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <i class="fa-solid fa-comments text-base"></i>
                </div>
                <div>
                    <h3 class="text-base font-black text-slate-900" id="chatVictimTitle">2-Way Victim Lifeline</h3>
                    <p class="text-[11px] text-slate-500 font-medium" id="chatVictimSubtitle">Direct emergency messaging</p>
                </div>
            </div>
            <button type="button" onclick="closeVictimChatModal()" class="text-slate-400 hover:text-slate-800 p-2 cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto space-y-2 p-3 bg-slate-50 rounded-2xl border border-slate-200 min-h-[220px] max-h-[320px]" id="victimChatMessagesBox">
            <p class="text-xs text-slate-400 text-center py-8">Loading live communications...</p>
        </div>

        <form method="POST" action="volunteer.php" class="pt-3 mt-3 border-t border-slate-100 flex items-center gap-2">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="send_victim_chat">
            <input type="hidden" name="sos_id" id="chat_sos_id" value="0">
            <input type="text" name="message" id="chat_message_input" required placeholder="Type direct update to victim..." class="flex-1 px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-blue-600 font-medium">
            <button type="submit" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition-all shadow-xs cursor-pointer">
                <i class="fa-solid fa-paper-plane"></i>
            </button>
        </form>
    </div>
</div>

<!-- 4. DUTY STATUS MODAL -->
<div id="dutyStatusModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white border border-slate-200 rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl relative">
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-teal-50 text-teal-700 flex items-center justify-center">
                    <i class="fa-solid fa-location-crosshairs text-base"></i>
                </div>
                <div>
                    <h3 class="text-lg font-black text-slate-900">Update Field Duty Status</h3>
                    <p class="text-xs text-slate-500 font-medium">Broadcast readiness to HQ</p>
                </div>
            </div>
            <button type="button" onclick="closeDutyStatusModal()" class="text-slate-400 hover:text-slate-800 p-2 cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="volunteer.php" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="update_duty_status">
            <input type="hidden" name="latitude" id="duty_lat" value="28.6139">
            <input type="hidden" name="longitude" id="duty_lng" value="77.2090">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Duty Status *</label>
                <select name="status" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:outline-none focus:border-teal-600 font-medium">
                    <option value="Available" <?= $currentDutyStatus === 'Available' ? 'selected' : '' ?>>Available (Ready for Mission)</option>
                    <option value="Deployed" <?= $currentDutyStatus === 'Deployed' ? 'selected' : '' ?>>Deployed (Active in Field)</option>
                    <option value="Standby" <?= $currentDutyStatus === 'Standby' ? 'selected' : '' ?>>Standby (On Call)</option>
                    <option value="Resting" <?= $currentDutyStatus === 'Resting' ? 'selected' : '' ?>>Resting / Off Duty</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Assigned Sector / Zone *</label>
                <input type="text" name="assigned_zone" value="<?= htmlspecialchars($currentAssignedZone) ?>" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:outline-none focus:border-teal-600 font-medium">
            </div>

            <div class="p-3 bg-teal-50 border border-teal-200 rounded-xl flex items-center justify-between text-xs">
                <span class="text-teal-900 font-bold" id="gpsStatusText"><i class="fa-solid fa-satellite-dish mr-1 text-teal-600"></i> GPS Coordinates: 28.6139, 77.2090</span>
                <button type="button" onclick="fetchLiveGps()" class="text-[11px] text-teal-800 underline font-bold cursor-pointer">Refresh GPS</button>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeDutyStatusModal()" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors cursor-pointer">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-teal-600 hover:bg-teal-700 text-white text-xs font-black shadow-sm transition-all cursor-pointer">
                    Update Status
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Tactical Map Leaflet JS Init -->
<script>
let volMap = null;

function initVolunteerMap() {
    if (volMap) return;
    const mapEl = document.getElementById('volunteerTacticalMap');
    if (!mapEl) return;

    volMap = L.map('volunteerTacticalMap').setView([28.6139, 77.2090], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(volMap);

    // 1. Plot SOS Distress Beacons
    <?php foreach ($sosDistressList as $sos): ?>
        <?php if (!empty($sos['gps_lat']) && !empty($sos['gps_lng'])): ?>
            L.circleMarker([<?= (float)$sos['gps_lat'] ?>, <?= (float)$sos['gps_lng'] ?>], {
                radius: 9,
                fillColor: '#dc2626',
                color: '#ffffff',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.9
            }).addTo(volMap).bindPopup(`
                <div style="font-family: sans-serif; font-size: 12px;">
                    <strong style="color: #dc2626;">🚨 SOS #${<?= (int)$sos['id'] ?>}: ${<?= json_encode($sos['sender_name'] ?: 'Distress Signal') ?>}</strong><br>
                    <strong>Type:</strong> ${<?= json_encode($sos['emergency_type']) ?>}<br>
                    <strong>Priority:</strong> ${<?= json_encode($sos['priority']) ?>}<br>
                    <button onclick="openVictimChatModal(${<?= (int)$sos['id'] ?>}, '${<?= addslashes(htmlspecialchars($sos['sender_name'])) ?>}')" style="margin-top: 5px; padding: 3px 8px; background: #2563eb; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px;">Open Chat</button>
                </div>
            `);
        <?php endif; ?>
    <?php endforeach; ?>

    // 2. Plot Shelters
    <?php foreach ($sheltersList as $sh): ?>
        <?php if (!empty($sh['latitude']) && !empty($sh['longitude'])): ?>
            L.circleMarker([<?= (float)$sh['latitude'] ?>, <?= (float)$sh['longitude'] ?>], {
                radius: 7,
                fillColor: '#2563eb',
                color: '#ffffff',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.8
            }).addTo(volMap).bindPopup(`
                <div style="font-family: sans-serif; font-size: 12px;">
                    <strong style="color: #2563eb;">🏠 ${<?= json_encode($sh['name']) ?>}</strong><br>
                    <strong>Type:</strong> ${<?= json_encode($sh['type']) ?>}<br>
                    <strong>Capacity:</strong> ${<?= (int)($sh['available_capacity'] ?? 50) ?>} / ${<?= (int)($sh['total_capacity'] ?? 100) ?>} Beds Avail
                </div>
            `);
        <?php endif; ?>
    <?php endforeach; ?>
}

document.addEventListener('DOMContentLoaded', function() {
    initVolunteerMap();
});

// Modal Handlers
function openCreateTaskModal() { document.getElementById('createTaskModal').classList.remove('hidden'); }
function closeCreateTaskModal() { document.getElementById('createTaskModal').classList.add('hidden'); }

function openDistributeAidModal() { document.getElementById('distributeAidModal').classList.remove('hidden'); }
function closeDistributeAidModal() { document.getElementById('distributeAidModal').classList.add('hidden'); }

function openDistributeAidModalWithId(id, name, avail, unit) {
    document.getElementById('dist_resource_id').value = id;
    document.getElementById('distributeAidModal').classList.remove('hidden');
}

function openDutyStatusModal() { document.getElementById('dutyStatusModal').classList.remove('hidden'); }
function closeDutyStatusModal() { document.getElementById('dutyStatusModal').classList.add('hidden'); }

function fetchLiveGps() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(pos) {
            document.getElementById('duty_lat').value = pos.coords.latitude;
            document.getElementById('duty_lng').value = pos.coords.longitude;
            document.getElementById('gpsStatusText').innerHTML = `<i class="fa-solid fa-check text-emerald-600 mr-1"></i> GPS Locked: ${pos.coords.latitude.toFixed(4)}, ${pos.coords.longitude.toFixed(4)}`;
        }, function() {
            alert('Could not lock GPS. Using fallback coordinates.');
        });
    }
}

// ============================================================
// DIRECT MULTI-VICTIM LIVE HOTLINE CHAT LOGIC
// ============================================================
let activeVictimSosId = <?= (int)($sosDistressList[0]['id'] ?? 1) ?>;
let localVictimThreads = <?= json_encode($sosDistressList) ?>;

function selectVictimThread(sosId) {
    activeVictimSosId = parseInt(sosId);
    const feed = document.getElementById('directVictimChatFeed');
    if (feed) {
        feed.innerHTML = '<div class="text-xs text-blue-600 font-bold py-6 text-center animate-pulse"><i class="fa-solid fa-spinner fa-spin mr-1.5"></i> Connecting to distress caller...</div>';
    }
    loadDirectVictimChat();
}

async function simulateVictimReply() {
    if (!activeVictimSosId) return;
    try {
        const res = await fetch(`api/victim_volunteer_chat_simulate.php?sos_id=${activeVictimSosId}`);
        const data = await res.json();
        if (data.success) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'info',
                title: `Citizen Reply: "${data.data.message}"`,
                timer: 4000,
                showConfirmButton: false
            });
            loadDirectVictimChat();
        }
    } catch (e) {
        console.error('Error simulating reply:', e);
    }
}

async function loadDirectVictimChat() {
    if (!activeVictimSosId) return;

    try {
        const res = await fetch(`api/victim_volunteer_chat_fetch.php?sos_id=${activeVictimSosId}`);
        const result = await res.json();

        if (result.success && result.data) {
            const victim = result.data.victim_info || {};
            const messages = result.data.messages || [];
            const allVictims = result.data.all_victims || localVictimThreads;

            // 1. Update Active Header Info
            const nameEl = document.getElementById('activeVictimNameDisplay');
            const detailsEl = document.getElementById('activeVictimDetailsDisplay');
            const badgeEl = document.getElementById('activeVictimStatusBadge');
            const callBtn = document.getElementById('activeVictimCallBtn');
            const countBadge = document.getElementById('victimThreadsCountBadge');

            if (nameEl) nameEl.innerText = `${victim.victim_name || 'Citizen'} (${victim.emergency_type || 'Distress'})`;
            if (detailsEl) detailsEl.innerText = `📞 ${victim.victim_phone || '+91 98765 43210'} • Priority: ${victim.priority || 'Critical'} • People: ${victim.people_count || '1-4'}`;
            if (badgeEl) {
                badgeEl.innerText = victim.status || 'Pending';
                badgeEl.className = `px-2 py-0.5 rounded text-[9px] font-black uppercase mono ${victim.status === 'Resolved' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'}`;
            }
            if (callBtn) callBtn.href = `tel:${victim.victim_phone || '112'}`;
            if (countBadge) countBadge.innerText = `${allVictims.length || 1} ACTIVE`;

            // 2. Render Horizontal Thread Selector
            const threadContainer = document.getElementById('victimThreadList');
            if (threadContainer && allVictims.length > 0) {
                threadContainer.innerHTML = allVictims.map(t => {
                    const isSelected = (parseInt(t.sos_id || t.id) === activeVictimSosId);
                    return `
                        <div onclick="selectVictimThread(${t.sos_id || t.id})" class="px-3 py-2 rounded-2xl border transition-all cursor-pointer flex items-center gap-2.5 shrink-0 ${isSelected ? 'bg-blue-50 border-blue-400 shadow-xs' : 'bg-white border-slate-200 hover:border-slate-300'}">
                            <div class="w-7 h-7 rounded-xl flex items-center justify-center text-xs font-black ${isSelected ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700'}">
                                ${escapeHtml((t.victim_name || t.sender_name || 'C')[0])}
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <strong class="text-xs font-bold truncate block ${isSelected ? 'text-blue-900' : 'text-slate-900'}">${escapeHtml(t.victim_name || t.sender_name || 'Citizen')}</strong>
                                    ${isSelected ? '<span class="w-1.5 h-1.5 rounded-full bg-blue-600 animate-ping"></span>' : ''}
                                </div>
                                <span class="text-[10px] text-slate-500 font-mono block">${escapeHtml(t.emergency_type || 'SOS')} • #${t.sos_id || t.id}</span>
                            </div>
                        </div>
                    `;
                }).join('');
            }

            // 3. Render Message Feed
            const feed = document.getElementById('directVictimChatFeed');
            if (feed) {
                if (messages.length === 0) {
                    feed.innerHTML = `<div class="text-xs text-slate-400 italic text-center py-6">Direct lifeline active. Send your first status or instructions to ${victim.victim_name || 'the citizen'} below.</div>`;
                } else {
                    feed.innerHTML = messages.map(m => {
                        const isVolunteer = (m.sender_role === 'volunteer');
                        return `
                            <div class="flex flex-col ${isVolunteer ? 'items-end' : 'items-start'}">
                                <div class="max-w-[82%] p-2.5 rounded-2xl text-xs ${isVolunteer ? 'bg-emerald-600 text-white rounded-br-none shadow-2xs' : 'bg-white border border-slate-200 text-slate-900 rounded-bl-none shadow-2xs'}">
                                    <div class="flex items-center justify-between gap-2 mb-0.5">
                                        <span class="text-[10px] font-mono font-bold ${isVolunteer ? 'text-emerald-200' : 'text-slate-500'}">${escapeHtml(m.sender_name)}</span>
                                        <span class="text-[9px] font-mono ${isVolunteer ? 'text-emerald-200' : 'text-slate-400'}">${m.created_at ? m.created_at.substring(11, 16) : 'Just now'}</span>
                                    </div>
                                    <p class="font-medium leading-relaxed">${escapeHtml(m.message)}</p>
                                </div>
                            </div>
                        `;
                    }).join('');
                    feed.scrollTop = feed.scrollHeight;
                }
            }
        }
    } catch (e) {
        console.error('Error in loadDirectVictimChat:', e);
    }
}

async function sendDirectVictimMsg(text) {
    if (!text || !text.trim() || !activeVictimSosId) return;

    try {
        const res = await fetch('api/victim_volunteer_chat_send.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                sos_id: activeVictimSosId,
                message: text.trim(),
                message_type: 'text'
            })
        });
        const data = await res.json();
        if (data.success) {
            loadDirectVictimChat();
        }
    } catch (e) {
        console.error('Error sending hotline message:', e);
    }
}

function handleSendDirectVictimMsg(e) {
    e.preventDefault();
    const input = document.getElementById('directVictimInput');
    const val = input.value.trim();
    if (val) {
        sendDirectVictimMsg(val);
        input.value = '';
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

document.addEventListener('DOMContentLoaded', function() {
    loadDirectVictimChat();
    setInterval(loadDirectVictimChat, 4000);
});

// 2-Way Chat Modal Handler
function openVictimChatModal(sosId, victimName) {
    activeVictimSosId = sosId;
    loadDirectVictimChat();
    document.getElementById('chat_sos_id').value = sosId;
    document.getElementById('chatVictimTitle').innerText = `Lifeline with ${victimName || 'Citizen'}`;
    document.getElementById('chatVictimSubtitle').innerText = `Direct rescue link for SOS #${sosId}`;
    document.getElementById('victimChatModal').classList.remove('hidden');
    loadVictimChat(sosId);
}

function closeVictimChatModal() {
    document.getElementById('victimChatModal').classList.add('hidden');
}

function loadVictimChat(sosId) {
    const box = document.getElementById('victimChatMessagesBox');
    box.innerHTML = `<p class="text-xs text-slate-400 text-center py-6">Connecting to emergency lifeline...</p>`;

    fetch(`api/victim_volunteer_chat_fetch.php?sos_id=${sosId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.data && data.data.messages && data.data.messages.length > 0) {
                box.innerHTML = data.data.messages.map(m => `
                    <div class="flex flex-col ${m.sender_role === 'volunteer' ? 'items-end' : 'items-start'}">
                        <div class="max-w-[80%] p-2.5 rounded-2xl text-xs ${m.sender_role === 'volunteer' ? 'bg-emerald-600 text-white rounded-br-none' : 'bg-white border border-slate-200 text-slate-900 rounded-bl-none shadow-2xs'}">
                            <span class="block text-[10px] font-mono font-bold ${m.sender_role === 'volunteer' ? 'text-emerald-200' : 'text-slate-400'}">${m.sender_name} • ${m.created_at ? m.created_at.substring(11, 16) : 'Just now'}</span>
                            <p class="font-medium mt-0.5">${m.message}</p>
                        </div>
                    </div>
                `).join('');
                box.scrollTop = box.scrollHeight;
            } else {
                box.innerHTML = `<p class="text-xs text-slate-400 text-center py-6">No previous messages. Type a reassuring message to the victim below.</p>`;
            }
        })
        .catch(() => {
            box.innerHTML = `<p class="text-xs text-slate-400 text-center py-6">Channel open. Send a message to begin.</p>`;
        });
}

function sendQuickRadioMsg() {
    const input = document.getElementById('quickRadioInput');
    const msg = input.value.trim();
    if (!msg) return;

    fetch('api/comms_send.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ message: msg, channel: 'ops' })
    }).then(() => {
        input.value = '';
        location.reload();
    }).catch(() => {
        location.reload();
    });
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
