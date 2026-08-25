<?php
// sos.php - DisasterSafe Universal SOS Alerts, Volunteer Assignment & Triage Command Hub (Government Theme)
define('PAGE_TITLE', 'SOS Distress Hub');
require_once __DIR__ . '/auth.php';

requireLogin();
$currentUser = getCurrentUser($pdo);
$isSuperAdmin = isSuperAdmin($currentUser);
$hasSosAccess = $isSuperAdmin || hasPermission($currentUser, 'access_sos_database');

if (!$hasSosAccess) {
    setFlash('error', 'Access Restricted: You do not have access to the SOS triage database.');
    header("Location: " . getRoleHomeUrl($currentUser));
    exit;
}

// Fetch list of volunteers for the assign dropdown (Rajesh Kumar first)
$volunteersList = $pdo->query("SELECT * FROM volunteers ORDER BY CASE WHEN full_name LIKE '%Rajesh%' THEN 1 ELSE 2 END, id ASC")->fetchAll();

// Handle Dispatch, Volunteer Assignment & Triage Actions (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrf)) {
        setFlash('error', 'Invalid security token.');
        header("Location: sos.php");
        exit;
    }

    // 1. UPDATE STATUS & DISPATCH AGENCY
    if ($action === 'update_status') {
        $sosId = (int) ($_POST['sos_id'] ?? 0);
        $status = trim($_POST['status'] ?? 'Pending');
        $agency = trim($_POST['dispatch_agency'] ?? '');

        $stmt = $pdo->prepare("UPDATE emergency_sos SET status = :status, dispatch_agency = :agency WHERE id = :id");
        $stmt->execute([
            ':status' => $status,
            ':agency' => $agency,
            ':id' => $sosId
        ]);

        logActivity($pdo, 'SOS_DISPATCH_UPDATE', "SOS #{$sosId} updated to '{$status}' (Agency: {$agency}) by {$currentUser['name']}");
        setFlash('success', "SOS Call #{$sosId} successfully updated to: {$status}");
        header("Location: sos.php");
        exit;
    }

    // 2. ASSIGN VOLUNTEER (e.g. Rajesh Kumar) TO SOS & OPEN LIVE COMMUNICATION
    if ($action === 'assign_volunteer') {
        $sosId = (int) ($_POST['sos_id'] ?? 0);
        $volunteerId = (int) ($_POST['volunteer_id'] ?? 0);
        $etaMinutes = (int) ($_POST['eta_minutes'] ?? 8);
        $dispatchNotes = trim($_POST['dispatch_notes'] ?? '');

        // Fetch volunteer details
        $volStmt = $pdo->prepare("SELECT * FROM volunteers WHERE id = ?");
        $volStmt->execute([$volunteerId]);
        $vol = $volStmt->fetch();

        $volName = $vol ? $vol['full_name'] : 'Rajesh Kumar (Volunteer Corps)';
        $volPhone = $vol ? $vol['phone'] : '+91 98765 43210';
        $volUserId = $vol ? ($vol['user_id'] ?: 11) : 11;

        // Fetch SOS details for victim name
        $sosInfoStmt = $pdo->prepare("SELECT sender_name FROM emergency_sos WHERE id = ?");
        $sosInfoStmt->execute([$sosId]);
        $victimName = $sosInfoStmt->fetchColumn() ?: 'Citizen';

        $stmt = $pdo->prepare("UPDATE emergency_sos SET status = 'Volunteer Responding', dispatch_agency = 'Volunteers', assigned_unit = :assigned_unit, responder_name = :responder_name, responder_phone = :responder_phone, eta_minutes = :eta WHERE id = :id");
        $stmt->execute([
            ':assigned_unit' => $volName,
            ':responder_name' => $volName,
            ':responder_phone' => $volPhone,
            ':eta' => $etaMinutes,
            ':id' => $sosId
        ]);

        // Insert Admin Dispatch Notice into chat
        $adminMsg = "🚨 [DISPATCH ORDER] Admin {$currentUser['name']} assigned Volunteer {$volName} to SOS #{$sosId}. Estimated arrival: {$etaMinutes} mins." . ($dispatchNotes ? " Instructions: {$dispatchNotes}" : "");
        $pdo->prepare("INSERT INTO victim_volunteer_chats (sos_id, sender_id, sender_name, sender_role, message, message_type) VALUES (?, ?, ?, 'admin', ?, 'dispatch')")
            ->execute([$sosId, $currentUser['id'] ?? 1, 'Disaster Command (Admin)', $adminMsg]);

        // Insert initial friendly volunteer greeting from Rajesh / Assigned Volunteer
        $volGreeting = "👋 Hello {$victimName}, this is {$volName}. I have been deployed by Disaster Command and am heading to your coordinates with emergency relief supplies (ETA: ~{$etaMinutes} mins). Please stay in a safe elevated position and reply here if your situation changes!";
        $pdo->prepare("INSERT INTO victim_volunteer_chats (sos_id, sender_id, sender_name, sender_role, message, message_type) VALUES (?, ?, ?, 'volunteer', ?, 'text')")
            ->execute([$sosId, $volUserId, $volName, $volGreeting]);

        logActivity($pdo, 'SOS_VOLUNTEER_ASSIGNED', "Assigned volunteer {$volName} to SOS #{$sosId} with {$etaMinutes}m ETA");
        setFlash('success', "Volunteer {$volName} successfully assigned to SOS #{$sosId}! 2-way live communication channel is now active.");
        header("Location: sos.php");
        exit;
    }

    // 3. CREATE MANUAL DISPATCH CALL (e.g. Phone / Radio Call)
    if ($action === 'create_sos') {
        $senderName = trim($_POST['sender_name'] ?? '');
        $senderPhone = trim($_POST['sender_phone'] ?? '');
        $gpsLat = filter_var($_POST['gps_lat'] ?? 28.6139, FILTER_VALIDATE_FLOAT);
        $gpsLng = filter_var($_POST['gps_lng'] ?? 77.2090, FILTER_VALIDATE_FLOAT);
        $bloodType = trim($_POST['blood_type'] ?? 'Unknown');
        $age = !empty($_POST['age']) ? (int)$_POST['age'] : null;
        $personsCount = trim($_POST['persons_count'] ?? '1 - 4');
        $priority = trim($_POST['priority'] ?? 'Critical');
        $emergencyType = trim($_POST['emergency_type'] ?? 'Flood');
        $message = trim($_POST['message'] ?? '');

        // Determine triage via system helper if not manually specified
        $triage = determineSystemTriage($emergencyType, $priority, $personsCount);
        $dispatchAgency = trim($_POST['dispatch_agency'] ?? '') ?: $triage['agency'];
        $medicalNeeds = trim($_POST['medical_needs'] ?? '') ?: $triage['needs'];

        if ($senderName && $senderPhone) {
            $stmt = $pdo->prepare("INSERT INTO emergency_sos (sender_name, sender_phone, gps_lat, gps_lng, blood_type, age, persons_count, priority, emergency_type, medical_needs, dispatch_agency, message, status) VALUES (:sender_name, :sender_phone, :gps_lat, :gps_lng, :blood_type, :age, :persons_count, :priority, :emergency_type, :medical_needs, :dispatch_agency, :message, 'Pending')");
            $stmt->execute([
                ':sender_name' => $senderName,
                ':sender_phone' => $senderPhone,
                ':gps_lat' => $gpsLat,
                ':gps_lng' => $gpsLng,
                ':blood_type' => $bloodType,
                ':age' => $age,
                ':persons_count' => $personsCount,
                ':priority' => $priority,
                ':emergency_type' => $emergencyType,
                ':medical_needs' => $medicalNeeds,
                ':dispatch_agency' => $dispatchAgency,
                ':message' => $message
            ]);

            $sosId = $pdo->lastInsertId();
            logActivity($pdo, 'SOS_LOGGED_MANUALLY', "New SOS #{$sosId} logged for {$senderName} [GPS: {$gpsLat}, {$gpsLng}] by {$currentUser['name']}");
            setFlash('success', "Emergency SOS #{$sosId} logged successfully! Alerting regional dispatchers.");
        } else {
            setFlash('error', 'Please fill in all required fields (Victim Name and Contact Phone).');
        }
        header("Location: sos.php");
        exit;
    }

    // 4. SUPERADMIN DELETE SOS
    if ($action === 'delete_sos' && $isSuperAdmin) {
        $sosId = (int) ($_POST['sos_id'] ?? 0);
        $pdo->prepare("DELETE FROM emergency_sos WHERE id = ?")->execute([$sosId]);
        logActivity($pdo, 'SOS_DELETED', "SOS record #{$sosId} deleted by Superadmin");
        setFlash('info', "SOS record #{$sosId} removed from database.");
        header("Location: sos.php");
        exit;
    }
}

// Filtering & Search
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$priorityFilter = trim($_GET['priority'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');

$query = "SELECT s.* FROM emergency_sos s WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (s.sender_name LIKE :s1 OR s.sender_phone LIKE :s2 OR s.message LIKE :s3 OR s.emergency_type LIKE :s4 OR s.dispatch_agency LIKE :s5 OR s.assigned_unit LIKE :s6)";
    $params[':s1'] = "%$search%";
    $params[':s2'] = "%$search%";
    $params[':s3'] = "%$search%";
    $params[':s4'] = "%$search%";
    $params[':s5'] = "%$search%";
    $params[':s6'] = "%$search%";
}

if ($statusFilter) {
    if ($statusFilter === 'active') {
        $query .= " AND s.status != 'Resolved'";
    } else {
        $query .= " AND s.status = :status";
        $params[':status'] = $statusFilter;
    }
}

if ($priorityFilter) {
    $query .= " AND s.priority = :priority";
    $params[':priority'] = $priorityFilter;
}

if ($categoryFilter) {
    $query .= " AND s.emergency_type LIKE :cat";
    $params[':cat'] = "%$categoryFilter%";
}

$query .= " ORDER BY CASE WHEN s.status = 'Pending' THEN 1 WHEN s.status != 'Resolved' THEN 2 ELSE 3 END, s.id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$sosList = $stmt->fetchAll();

// Aggregates
$totalSos = (int) $pdo->query("SELECT COUNT(*) FROM emergency_sos")->fetchColumn();
$pendingCount = (int) $pdo->query("SELECT COUNT(*) FROM emergency_sos WHERE status = 'Pending'")->fetchColumn();
$dispatchedCount = (int) $pdo->query("SELECT COUNT(*) FROM emergency_sos WHERE status IN ('Police Dispatched', 'Volunteer Responding', 'NDRF Dispatched', 'Fire Dispatched', 'EMS Dispatched')")->fetchColumn();
$resolvedCount = (int) $pdo->query("SELECT COUNT(*) FROM emergency_sos WHERE status = 'Resolved'")->fetchColumn();

$disasters = $pdo->query("SELECT id, title FROM disasters WHERE status = 'Active'")->fetchAll();
$csrfToken = generateCsrfToken();

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div class="flex-1 flex flex-col min-w-0 bg-[#f8fafc] overflow-hidden">
    <?php require_once __DIR__ . '/navbar.php'; ?>

    <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 space-y-6">
        
        <!-- Header & Action Row -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">
                    SOS Alerts &amp; Triage Hub
                </h1>
                <p class="text-xs text-slate-500 mt-1">
                    Multi-agency emergency distress queue, volunteer dispatch, and live 2-way lifeline communication
                </p>
            </div>

            <div class="flex items-center gap-3">
                <button type="button" onclick="document.getElementById('manualSosModal').classList.remove('hidden')" class="px-4 py-2 rounded-xl bg-white hover:bg-slate-50 text-slate-700 font-semibold text-xs border border-slate-200 hover:border-slate-300 flex items-center gap-2 transition-colors cursor-pointer shadow-2xs">
                    <i class="fa-solid fa-plus text-slate-400"></i>
                    <span>Log Distress Call</span>
                </button>
            </div>
        </div>

        <!-- SOS Status Filter Bar -->
        <?php $activeCount = $totalSos - $resolvedCount; ?>
        <section class="flex items-center gap-1.5 overflow-x-auto pb-1">
            <a href="sos.php<?= $search ? '?search=' . urlencode($search) : '' ?>" class="shrink-0 px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all <?= (!$statusFilter && !$priorityFilter) ? 'bg-slate-900 text-white' : 'bg-white text-slate-500 border border-slate-200 hover:border-slate-300 hover:text-slate-700' ?>">
                All <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] <?= (!$statusFilter && !$priorityFilter) ? 'bg-white/15 text-white/80' : 'bg-slate-100 text-slate-400' ?>"><?= $totalSos ?></span>
            </a>
            <a href="sos.php?status=Pending<?= $search ? '&search=' . urlencode($search) : '' ?>" class="shrink-0 px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all <?= $statusFilter === 'Pending' ? 'bg-slate-900 text-white' : 'bg-white text-slate-500 border border-slate-200 hover:border-slate-300 hover:text-slate-700' ?>">
                Pending SOS <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] <?= $statusFilter === 'Pending' ? 'bg-white/15 text-white/80' : 'bg-slate-100 text-slate-400' ?>"><?= $pendingCount ?></span>
            </a>
            <a href="sos.php?status=Volunteer+Responding<?= $search ? '&search=' . urlencode($search) : '' ?>" class="shrink-0 px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all <?= $statusFilter === 'Volunteer Responding' ? 'bg-blue-600 text-white' : 'bg-white text-slate-500 border border-slate-200 hover:border-slate-300 hover:text-slate-700' ?>">
                Volunteer Assigned <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] bg-blue-100 text-blue-700 font-bold"><?= count(array_filter($sosList, fn($s) => ($s['status'] ?? '') === 'Volunteer Responding')) ?></span>
            </a>
            <a href="sos.php?status=active<?= $search ? '&search=' . urlencode($search) : '' ?>" class="shrink-0 px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all <?= $statusFilter === 'active' ? 'bg-slate-900 text-white' : 'bg-white text-slate-500 border border-slate-200 hover:border-slate-300 hover:text-slate-700' ?>">
                All Active <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] <?= $statusFilter === 'active' ? 'bg-white/15 text-white/80' : 'bg-slate-100 text-slate-400' ?>"><?= $activeCount ?></span>
            </a>
            <a href="sos.php?status=Resolved<?= $search ? '&search=' . urlencode($search) : '' ?>" class="shrink-0 px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all <?= $statusFilter === 'Resolved' ? 'bg-emerald-600 text-white' : 'bg-white text-slate-500 border border-slate-200 hover:border-slate-300 hover:text-slate-700' ?>">
                Rescued <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] <?= $statusFilter === 'Resolved' ? 'bg-white/15 text-white/80' : 'bg-slate-100 text-slate-400' ?>"><?= $resolvedCount ?></span>
            </a>
        </section>

        <!-- Compact Search & Filter Controls -->
        <section class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-2xs">
            <form method="GET" action="sos.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-2.5 text-xs">
                
                <!-- Search Keyword -->
                <div class="lg:col-span-2 relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-[10px]"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search victim, phone, Rajesh, volunteer, flood..." class="w-full pl-8 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:border-[#1d63d8] focus:bg-white text-xs">
                </div>

                <!-- Status Filter -->
                <div>
                    <select name="status" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:outline-none focus:border-[#1d63d8] focus:bg-white text-xs">
                        <option value="">Status: All (<?= $totalSos ?>)</option>
                        <option value="Pending" <?= $statusFilter === 'Pending' ? 'selected' : '' ?>>Pending Critical</option>
                        <option value="Volunteer Responding" <?= $statusFilter === 'Volunteer Responding' ? 'selected' : '' ?>>Volunteer Assigned</option>
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>All Active Rescues</option>
                        <option value="NDRF Dispatched" <?= $statusFilter === 'NDRF Dispatched' ? 'selected' : '' ?>>NDRF Dispatched</option>
                        <option value="Police Dispatched" <?= $statusFilter === 'Police Dispatched' ? 'selected' : '' ?>>Police Dispatched</option>
                        <option value="Fire Dispatched" <?= $statusFilter === 'Fire Dispatched' ? 'selected' : '' ?>>Fire Dispatched</option>
                        <option value="EMS Dispatched" <?= $statusFilter === 'EMS Dispatched' ? 'selected' : '' ?>>EMS Dispatched</option>
                        <option value="Resolved" <?= $statusFilter === 'Resolved' ? 'selected' : '' ?>>Resolved / Rescued</option>
                    </select>
                </div>

                <!-- Priority Filter -->
                <div>
                    <select name="priority" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:outline-none focus:border-[#1d63d8] focus:bg-white text-xs">
                        <option value="">Priority: All</option>
                        <option value="Critical" <?= $priorityFilter === 'Critical' ? 'selected' : '' ?>>Critical Priority</option>
                        <option value="High" <?= $priorityFilter === 'High' ? 'selected' : '' ?>>High Priority</option>
                        <option value="Medium" <?= $priorityFilter === 'Medium' ? 'selected' : '' ?>>Medium Priority</option>
                    </select>
                </div>

                <!-- Submit / Reset Buttons -->
                <div class="flex items-center gap-1.5">
                    <button type="submit" class="flex-1 py-2 px-3 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs transition-colors cursor-pointer">
                        Filter
                    </button>
                    <?php if ($search || $statusFilter || $priorityFilter || $categoryFilter): ?>
                        <a href="sos.php" class="py-2 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 transition-colors text-center text-xs font-bold" title="Clear Filters">
                            <i class="fa-solid fa-rotate-left text-[10px]"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <!-- COMPACT HIGH-DENSITY SOS INCIDENT FEED -->
        <section class="space-y-3">
            <?php if (empty($sosList)): ?>
                <div class="bg-white p-8 text-center rounded-2xl border border-slate-200 text-slate-400">
                    <i class="fa-solid fa-bell-slash text-2xl mb-2 text-slate-300 block"></i>
                    <h3 class="text-sm font-bold text-slate-600">No SOS Alerts Found</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Try clearing your search query or status filter.</p>
                </div>
            <?php endif; ?>

            <?php foreach ($sosList as $sos): ?>
                <?php 
                    $priorityColor = match($sos['priority'] ?? 'Critical') {
                        'Critical' => 'bg-red-50 text-red-700 border-red-200',
                        'High' => 'bg-amber-50 text-amber-700 border-amber-200',
                        'Medium' => 'bg-slate-100 text-slate-600 border-slate-200',
                        default => 'bg-slate-100 text-slate-600 border-slate-200'
                    };
                    $borderColor = match($sos['status']) {
                        'Pending' => 'border-l-red-500',
                        'Volunteer Responding' => 'border-l-blue-500',
                        'NDRF Dispatched' => 'border-l-orange-500',
                        'Police Dispatched' => 'border-l-indigo-500',
                        'Fire Dispatched' => 'border-l-rose-500',
                        'EMS Dispatched' => 'border-l-teal-500',
                        'Resolved' => 'border-l-emerald-500',
                        default => 'border-l-slate-300'
                    };
                    $statusBadge = match($sos['status']) {
                        'Pending' => 'bg-red-50 text-red-700 border-red-200',
                        'Volunteer Responding' => 'bg-blue-50 text-blue-700 border-blue-200',
                        'Resolved' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                        default => 'bg-slate-100 text-slate-600 border-slate-200'
                    };
                ?>
                <div class="bg-white p-4 rounded-2xl border border-slate-200 border-l-4 <?= $borderColor ?> hover:border-slate-300 transition-all shadow-2xs space-y-2.5">
                    
                    <!-- Row 1: Badges, Caller, Phone, GPS, Persons & Quick Dispatch Strip -->
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                        
                        <!-- Left: Badges, Identification & Coordinates -->
                        <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1.5 text-xs">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wide border <?= $priorityColor ?> mono">
                                <?= htmlspecialchars($sos['priority'] ?? 'CRITICAL') ?>
                            </span>
                            <span class="text-[11px] font-mono font-black text-slate-400">
                                #<?= $sos['id'] ?>
                            </span>
                            <span class="text-xs font-black text-slate-900">
                                <?= htmlspecialchars($sos['emergency_type']) ?>
                            </span>

                            <span class="text-slate-300">|</span>

                            <!-- Caller Name -->
                            <span class="text-xs font-bold text-slate-800 flex items-center gap-1">
                                <i class="fa-solid fa-user text-[10px] text-slate-400"></i>
                                <?= htmlspecialchars($sos['sender_name']) ?>
                            </span>

                            <!-- Phone & WhatsApp -->
                            <div class="flex items-center gap-1.5" onclick="event.stopPropagation()">
                                <a href="tel:<?= urlencode($sos['sender_phone']) ?>" class="text-[11px] font-mono font-bold text-blue-600 hover:underline flex items-center gap-1">
                                    <i class="fa-solid fa-phone text-[9px] text-slate-400"></i> <?= htmlspecialchars($sos['sender_phone']) ?>
                                </a>
                                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $sos['sender_phone']) ?>" target="_blank" class="text-emerald-500 hover:text-emerald-600 p-0.5" title="Open WhatsApp Chat">
                                    <i class="fa-brands fa-whatsapp text-xs"></i>
                                </a>
                            </div>

                            <!-- GPS Pill -->
                            <a href="https://maps.google.com/?q=<?= $sos['gps_lat'] ?>,<?= $sos['gps_lng'] ?>" target="_blank" class="text-[10px] font-mono text-slate-500 hover:text-[#1d63d8] hover:underline flex items-center gap-1" onclick="event.stopPropagation()" title="Open in Google Maps">
                                <i class="fa-solid fa-location-crosshairs text-[9px] text-red-500"></i>
                                <?= number_format((float)$sos['gps_lat'], 4) ?>, <?= number_format((float)$sos['gps_lng'], 4) ?>
                            </a>

                            <!-- Persons Range -->
                            <span class="text-[10px] font-bold text-slate-600 flex items-center gap-1 bg-slate-100 px-2 py-0.5 rounded-full">
                                <i class="fa-solid fa-users text-[9px] text-slate-400"></i> <?= htmlspecialchars($sos['persons_count'] ?? '1 - 4') ?>
                            </span>

                            <?php if (!empty($sos['blood_type']) && $sos['blood_type'] !== 'Unknown'): ?>
                                <span class="text-[10px] font-black text-rose-600 bg-rose-50 border border-rose-200 px-2 py-0.5 rounded-full mono">
                                    Blood: <?= htmlspecialchars($sos['blood_type']) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Right: Action Buttons (Assign Volunteer, Live Chat, View Dossier) -->
                        <div class="flex flex-wrap items-center gap-1.5 shrink-0" onclick="event.stopPropagation()">
                            
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black border <?= $statusBadge ?> uppercase tracking-wide mono">
                                <?= htmlspecialchars($sos['status']) ?>
                            </span>

                            <!-- 1. ASSIGN VOLUNTEER BUTTON (Key Requested Feature) -->
                            <button type="button" onclick="openAssignVolunteerModal(<?= $sos['id'] ?>, '<?= addslashes(htmlspecialchars($sos['sender_name'])) ?>', '<?= htmlspecialchars($sos['priority']) ?>', '<?= addslashes(htmlspecialchars($sos['assigned_unit'] ?? '')) ?>')" class="px-2.5 py-1 rounded-xl bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 font-bold text-xs flex items-center gap-1.5 transition-all shadow-2xs cursor-pointer" title="Assign Volunteer to SOS">
                                <i class="fa-solid fa-user-plus text-xs"></i>
                                <span><?= !empty($sos['assigned_unit']) ? 'Reassign' : 'Assign Volunteer' ?></span>
                            </button>

                            <!-- 2. LIVE LIFELINE CHAT MODAL TRIGGER -->
                            <button type="button" onclick="openAdminChatModal(<?= $sos['id'] ?>, '<?= addslashes(htmlspecialchars($sos['sender_name'])) ?>', '<?= addslashes(htmlspecialchars($sos['assigned_unit'] ?? 'Volunteer Assigned')) ?>')" class="px-2.5 py-1 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 font-bold text-xs flex items-center gap-1.5 transition-all shadow-2xs cursor-pointer" title="Open 2-Way Live Chat with Victim & Volunteer">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                <i class="fa-solid fa-comments text-xs"></i>
                                <span>Live Chat</span>
                            </button>

                            <!-- Quick Resolve / Re-open -->
                            <form method="POST" action="sos.php" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="sos_id" value="<?= $sos['id'] ?>">

                                <?php if ($sos['status'] !== 'Resolved'): ?>
                                    <button type="submit" name="status" value="Resolved" class="px-2.5 py-1 rounded-xl border border-slate-200 bg-white hover:bg-emerald-50 text-slate-700 hover:text-emerald-700 font-bold text-xs transition-colors flex items-center gap-1 cursor-pointer">
                                        <i class="fa-solid fa-check text-[10px] text-emerald-500"></i> Mark Safe
                                    </button>
                                <?php else: ?>
                                    <button type="submit" name="status" value="Pending" class="px-2.5 py-1 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-500 font-bold text-xs transition-colors cursor-pointer">
                                        Re-open
                                    </button>
                                <?php endif; ?>
                            </form>

                            <!-- View Full Dossier Modal -->
                            <button type="button" onclick="openSosModal(<?= htmlspecialchars(json_encode($sos)) ?>)" class="p-1.5 px-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 text-xs transition-colors cursor-pointer" title="View Full Dossier">
                                <i class="fa-solid fa-expand text-xs"></i>
                            </button>

                            <?php if ($isSuperAdmin): ?>
                                <form method="POST" action="sos.php" onsubmit="return confirm('Permanently delete this SOS record?');" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="action" value="delete_sos">
                                    <input type="hidden" name="sos_id" value="<?= $sos['id'] ?>">
                                    <button type="submit" class="p-1.5 px-2 rounded-xl border border-slate-200 bg-white hover:bg-red-50 text-slate-400 hover:text-red-600 text-xs transition-colors cursor-pointer" title="Delete SOS">
                                        <i class="fa-solid fa-trash-can text-xs"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>

                    </div>

                    <!-- Row 2: Assigned Volunteer Pill, Distress Note & Timestamp -->
                    <div class="pt-2 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                        <div class="flex flex-wrap items-center gap-2 min-w-0 flex-1">
                            <?php if (!empty($sos['assigned_unit'])): ?>
                                <span class="px-2.5 py-0.5 rounded-full bg-blue-50 text-blue-700 border border-blue-200 font-black text-[11px] flex items-center gap-1.5 shadow-2xs">
                                    <i class="fa-solid fa-hand-holding-heart text-blue-500"></i>
                                    <span>Assigned: <strong><?= htmlspecialchars($sos['assigned_unit']) ?></strong></span>
                                    <?php if (!empty($sos['eta_minutes'])): ?>
                                        <span class="text-slate-400">&bull; ETA: <?= $sos['eta_minutes'] ?>m</span>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>

                            <?php if (!empty($sos['message'])): ?>
                                <span class="text-slate-600 truncate text-[11px] font-medium">
                                    <i class="fa-solid fa-quote-left text-slate-300 mr-1 text-[9px]"></i>
                                    <?= htmlspecialchars($sos['message']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-slate-400 italic text-[11px]">One-touch GPS distress beacon triggered.</span>
                            <?php endif; ?>
                        </div>

                        <div class="flex items-center gap-3 shrink-0 text-[11px] font-medium text-slate-500">
                            <?php if (!empty($sos['dispatch_agency'])): ?>
                                <span class="flex items-center gap-1">
                                    <i class="fa-solid fa-shield-halved text-[9px] text-slate-400"></i>
                                    <?= htmlspecialchars($sos['dispatch_agency']) ?>
                                </span>
                            <?php endif; ?>
                            <span class="text-slate-400 font-mono text-[10px] flex items-center gap-1">
                                <i class="fa-regular fa-clock text-[9px]"></i>
                                <?= date('d M, H:i', strtotime($sos['created_at'])) ?>
                            </span>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        </section>

    </main>
</div>

<!-- ==================== MODALS ==================== -->

<!-- 1. ASSIGN VOLUNTEER MODAL (Supports Rajesh Kumar & All Volunteers) -->
<div id="assignVolunteerModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden space-y-4 p-6">
        
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-2xl bg-blue-50 text-blue-600 border border-blue-200 flex items-center justify-center text-sm font-black">
                    <i class="fa-solid fa-user-plus"></i>
                </div>
                <div>
                    <h3 class="text-sm font-black text-slate-900">Assign Volunteer to SOS Distress</h3>
                    <p class="text-[11px] text-slate-500 font-medium" id="assignModalSubtitle">Deploy volunteer &amp; establish live 2-way chat</p>
                </div>
            </div>
            <button type="button" onclick="document.getElementById('assignVolunteerModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-700 p-1.5 rounded-lg hover:bg-slate-100 transition-colors cursor-pointer">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>

        <form method="POST" action="sos.php" class="space-y-4 text-xs">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="assign_volunteer">
            <input type="hidden" name="sos_id" id="assignModalSosId" value="">

            <!-- Volunteer Selection (Rajesh Kumar prominently listed) -->
            <div>
                <label class="block text-slate-700 font-black mb-1.5">Select Dedicated Volunteer</label>
                <select name="volunteer_id" id="assignModalVolunteerSelect" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-2xl text-slate-900 font-medium focus:outline-none focus:border-blue-600 focus:bg-white text-xs">
                    <?php foreach ($volunteersList as $v): ?>
                        <option value="<?= $v['id'] ?>" <?= str_contains($v['full_name'], 'Rajesh') ? 'selected' : '' ?>>
                            <?= htmlspecialchars($v['full_name']) ?> &bull; <?= htmlspecialchars($v['phone']) ?> (<?= htmlspecialchars($v['team_name'] ?: 'Volunteer Corps') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-[10px] text-slate-500 font-medium mt-1">
                    <i class="fa-solid fa-circle-info text-blue-500 mr-1"></i> Pre-selected <strong>Rajesh Kumar</strong> (Emergency Field Specialist).
                </p>
            </div>

            <!-- ETA in Minutes -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-700 font-black mb-1.5">Estimated Arrival (ETA)</label>
                    <div class="relative">
                        <input type="number" name="eta_minutes" min="1" max="120" value="8" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-2xl text-slate-900 font-medium focus:outline-none focus:border-blue-600 focus:bg-white text-xs pl-8">
                        <i class="fa-solid fa-stopwatch absolute left-3 top-3 text-slate-400 text-xs"></i>
                    </div>
                </div>
                <div>
                    <label class="block text-slate-700 font-black mb-1.5">Priority Dispatch</label>
                    <input type="text" id="assignModalPriority" readonly class="w-full px-3.5 py-2.5 bg-slate-100 border border-slate-200 rounded-2xl text-slate-600 font-bold text-xs cursor-not-allowed">
                </div>
            </div>

            <!-- Tactical Dispatch Notes -->
            <div>
                <label class="block text-slate-700 font-black mb-1.5">Dispatch Instructions / Notes (Optional)</label>
                <textarea name="dispatch_notes" rows="2" placeholder="e.g. Bring life jackets and potable water. Victim is on 2nd floor balcony." class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-2xl text-slate-900 font-medium focus:outline-none focus:border-blue-600 focus:bg-white text-xs"></textarea>
            </div>

            <div class="p-3 rounded-2xl bg-blue-50/70 border border-blue-200/80 text-[11px] text-blue-900 font-medium space-y-1">
                <div class="flex items-center gap-1.5 font-bold text-blue-950">
                    <i class="fa-solid fa-tower-broadcast text-blue-600"></i>
                    <span>Automated Lifeline Link</span>
                </div>
                <p>Assigning a volunteer automatically sends a greeting into the live hotline chat so the citizen and volunteer can communicate instantly.</p>
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                <button type="button" onclick="document.getElementById('assignVolunteerModal').classList.add('hidden')" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs cursor-pointer">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-xs cursor-pointer">
                    <i class="fa-solid fa-paper-plane"></i>
                    <span>Assign &amp; Open Lifeline</span>
                </button>
            </div>
        </form>

    </div>
</div>

<!-- 2. ADMIN & VOLUNTEER 2-WAY LIVE CHAT LIFELINE MODAL -->
<div id="adminChatModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-2xl h-[620px] max-h-[92vh] flex flex-col shadow-2xl overflow-hidden">
        
        <!-- Chat Header -->
        <div class="p-4 bg-slate-900 text-white flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white flex items-center justify-center text-lg font-black shadow-md shrink-0">
                    <i class="fa-solid fa-headset"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-black text-white" id="adminChatVictimName">Citizen Distress Lifeline</h3>
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                        <span class="text-[10px] font-bold bg-emerald-500/20 border border-emerald-400/30 text-emerald-300 px-2 py-0.5 rounded-full mono">LIVE COMM</span>
                    </div>
                    <p class="text-[11px] text-slate-300 font-medium" id="adminChatVolunteerSub">Assigned: Rajesh Kumar (Volunteer Corps)</p>
                </div>
            </div>
            <button type="button" onclick="closeAdminChatModal()" class="text-slate-400 hover:text-white p-1.5 rounded-xl hover:bg-slate-800 transition-colors cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <!-- Chat Messages Feed (Scrollable) -->
        <div id="adminChatFeed" class="flex-1 overflow-y-auto p-4 space-y-3 bg-[#f8fafc]">
            <div class="text-center py-6 text-slate-400 text-xs italic">
                <i class="fa-solid fa-circle-notch fa-spin mr-1"></i> Synchronizing live communication channel...
            </div>
        </div>

        <!-- Quick Reply Action Chips -->
        <div class="px-4 py-2 bg-white border-t border-slate-100 flex items-center gap-1.5 overflow-x-auto text-[11px]">
            <span class="text-[10px] font-bold text-slate-400 uppercase mono shrink-0">Quick Transmit:</span>
            <button type="button" onclick="sendAdminQuickMsg('🚑 Volunteer Rajesh is 3 minutes away from your location!')" class="shrink-0 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold transition-all cursor-pointer">
                🚑 Rajesh 3m Away
            </button>
            <button type="button" onclick="sendAdminQuickMsg('🚪 Please flash your phone light or wave a bright cloth from the window/roof.')" class="shrink-0 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold transition-all cursor-pointer">
                🚪 Signal Window
            </button>
            <button type="button" onclick="sendAdminQuickMsg('📦 Relief kit & rations have been secured for your family.')" class="shrink-0 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold transition-all cursor-pointer">
                📦 Rations Ready
            </button>
            <button type="button" onclick="sendAdminQuickMsg('🩺 Medical first responder is attached to the rescue unit.')" class="shrink-0 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold transition-all cursor-pointer">
                🩺 Med Unit En Route
            </button>
        </div>

        <!-- Message Composer Form -->
        <div class="p-3 bg-white border-t border-slate-200 shrink-0">
            <form onsubmit="submitAdminChatMsg(event)" class="flex items-center gap-2">
                <input type="text" id="adminChatInput" required placeholder="Type message to citizen & assigned volunteer..." class="flex-1 px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-2xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-blue-600 focus:bg-white font-medium">
                <button type="submit" class="px-5 py-2.5 rounded-2xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs flex items-center gap-1.5 transition-all shadow-xs shrink-0 cursor-pointer">
                    <i class="fa-solid fa-paper-plane text-xs"></i>
                    <span>Send</span>
                </button>
            </form>
        </div>

    </div>
</div>

<!-- 3. INTERACTIVE EXPANDED SOS DETAIL & TRIAGE MODAL -->
<div id="sosDetailModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-2xl max-h-[90vh] flex flex-col shadow-2xl overflow-hidden">
        
        <!-- Modal Header -->
        <div class="h-14 px-6 bg-white border-b border-slate-100 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3">
                <span class="w-2.5 h-2.5 rounded-full bg-slate-900"></span>
                <h3 class="text-sm font-black text-slate-900" id="modalSosTitle">SOS Distress Dossier</h3>
            </div>
            <button type="button" onclick="document.getElementById('sosDetailModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-700 p-1.5 rounded-lg hover:bg-slate-100 transition-colors cursor-pointer">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>

        <!-- Modal Body (Scrollable Details) -->
        <div class="flex-1 overflow-y-auto p-6 space-y-5 text-xs text-slate-700">
            
            <!-- Callout Bar -->
            <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mono">Current Status</span>
                    <span id="modalSosStatus" class="font-black text-sm text-slate-900"></span>
                </div>
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mono">Priority Level</span>
                    <span id="modalSosPriority" class="font-black text-sm text-slate-900"></span>
                </div>
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mono">People Trapped</span>
                    <span id="modalSosTrapped" class="font-black text-sm text-slate-900"></span>
                </div>
            </div>

            <!-- Victim Dossier -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 space-y-1">
                    <span class="text-[10px] font-bold text-slate-400 uppercase mono">Victim / Contact Name</span>
                    <p id="modalSosSender" class="font-black text-slate-900 text-sm"></p>
                </div>
                <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 space-y-1">
                    <span class="text-[10px] font-bold text-slate-400 uppercase mono">Direct Phone / Hotline</span>
                    <p id="modalSosPhone" class="font-bold text-slate-700 text-sm font-mono"></p>
                </div>
                <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 space-y-1">
                    <span class="text-[10px] font-bold text-slate-400 uppercase mono">Blood Group &amp; Vitals</span>
                    <p id="modalSosBlood" class="font-bold text-slate-700"></p>
                </div>
                <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 space-y-1">
                    <span class="text-[10px] font-bold text-slate-400 uppercase mono">Age of Primary Caller</span>
                    <p id="modalSosAge" class="font-bold text-slate-900"></p>
                </div>
            </div>

            <!-- GPS Coordinates -->
            <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 space-y-2">
                <span class="text-[10px] font-bold text-slate-400 uppercase flex items-center justify-between mono">
                    <span class="flex items-center gap-1.5"><i class="fa-solid fa-satellite-dish text-slate-400"></i> Geospatial GPS Coordinates</span>
                    <a id="modalSosMapLink" href="#" target="_blank" class="text-[#1d63d8] hover:underline font-bold text-[11px] flex items-center gap-1">
                        <span>Open in Google Maps</span> <i class="fa-solid fa-arrow-up-right-from-square text-[8px]"></i>
                    </a>
                </span>
                <p id="modalSosGps" class="font-mono font-bold text-slate-900 text-sm"></p>
            </div>

            <!-- Distress Message -->
            <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 space-y-2">
                <span class="text-[10px] font-bold text-slate-400 uppercase flex items-center gap-1.5 mono">
                    <i class="fa-solid fa-comment-dots text-slate-400"></i> Distress Transcript &amp; Message
                </span>
                <p id="modalSosMessage" class="text-slate-700 leading-relaxed italic font-medium"></p>
            </div>

            <!-- Medical Needs -->
            <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 space-y-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase mono">System Triaged Relief Supplies &amp; Equipment</span>
                <p id="modalSosNeeds" class="font-bold text-slate-700"></p>
            </div>

            <!-- Dispatch Update Form -->
            <form method="POST" action="sos.php" class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="sos_id" id="modalFormSosId" value="">

                <span class="text-xs font-black text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-paper-plane text-blue-600"></i> Command Dispatch &amp; Status Reassignment
                </span>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1 mono">Target Status</label>
                        <select name="status" id="modalFormStatusSelect" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-slate-900 text-xs focus:outline-none focus:border-[#1d63d8] font-medium">
                            <option value="Pending">Pending (Unresolved)</option>
                            <option value="Volunteer Responding">Volunteer Relief Corps</option>
                            <option value="NDRF Dispatched">NDRF Tactical Boat Squad</option>
                            <option value="Police Dispatched">Police Perimeter &amp; Cordon</option>
                            <option value="Fire Dispatched">Fire &amp; Hazmat Engine</option>
                            <option value="EMS Dispatched">Advanced Life Support Ambulance</option>
                            <option value="Resolved">Rescued &amp; Resolved</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1 mono">Assigned Agency / Squad</label>
                        <input type="text" name="dispatch_agency" id="modalFormAgencyInput" placeholder="e.g. Rajesh Kumar (Volunteer), NDRF Boat Unit 4" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-slate-900 text-xs focus:outline-none focus:border-[#1d63d8] font-medium">
                    </div>
                </div>

                <div class="flex justify-end pt-1">
                    <button type="submit" class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs transition-colors cursor-pointer shadow-xs">
                        Execute Tactical Dispatch
                    </button>
                </div>
            </form>

        </div>

    </div>
</div>

<!-- 4. MANUAL LOG DISTRESS MODAL (FOR DISPATCHERS) -->
<div id="manualSosModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-xl max-h-[90vh] flex flex-col shadow-2xl overflow-hidden">
        
        <div class="h-14 px-6 bg-white border-b border-slate-100 flex items-center justify-between shrink-0">
            <h3 class="text-sm font-black text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-headset text-blue-600"></i>
                <span>Log Inbound SOS Distress Call</span>
            </h3>
            <button type="button" onclick="document.getElementById('manualSosModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-700 p-1.5 rounded-lg hover:bg-slate-100 transition-colors cursor-pointer">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>

        <form method="POST" action="sos.php" class="flex-1 overflow-y-auto p-6 space-y-4 text-xs">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="create_sos">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 mb-1">Victim / Caller Name *</label>
                    <input type="text" name="sender_name" required placeholder="e.g. Suresh Kumar" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:bg-white focus:outline-none focus:border-[#1d63d8] font-medium">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 mb-1">Contact Phone Number *</label>
                    <input type="text" name="sender_phone" required placeholder="e.g. +91 98112 34567" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-mono focus:bg-white focus:outline-none focus:border-[#1d63d8] font-medium">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 mb-1">GPS Latitude *</label>
                    <input type="number" step="0.0001" name="gps_lat" required value="28.6139" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-mono focus:bg-white focus:outline-none focus:border-[#1d63d8]">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 mb-1">GPS Longitude *</label>
                    <input type="number" step="0.0001" name="gps_lng" required value="77.2090" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-mono focus:bg-white focus:outline-none focus:border-[#1d63d8]">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 mb-1">Blood Group</label>
                    <select name="blood_type" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:bg-white focus:outline-none focus:border-[#1d63d8]">
                        <option value="Unknown">Unknown</option>
                        <option value="A+">A+</option>
                        <option value="A-">A-</option>
                        <option value="B+">B+</option>
                        <option value="B-">B-</option>
                        <option value="O+">O+</option>
                        <option value="O-">O-</option>
                        <option value="AB+">AB+</option>
                        <option value="AB-">AB-</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 mb-1">Caller Age</label>
                    <input type="number" name="age" placeholder="e.g. 35" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:bg-white focus:outline-none focus:border-[#1d63d8]">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 mb-1">Persons Trapped *</label>
                    <select name="persons_count" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:bg-white focus:outline-none focus:border-[#1d63d8]">
                        <option value="1 - 4">1 - 4 Persons</option>
                        <option value="4 - 8">4 - 8 Persons</option>
                        <option value="8 - 12">8 - 12 Persons</option>
                        <option value="12+">12+ Persons</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 mb-1">Emergency Category *</label>
                    <select name="emergency_type" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:bg-white focus:outline-none focus:border-[#1d63d8]">
                        <option value="Flood">Flood</option>
                        <option value="Fire">Fire</option>
                        <option value="Earthquake">Earthquake</option>
                        <option value="Building Collapse">Building Collapse</option>
                        <option value="Medical Trauma">Medical Trauma</option>
                        <option value="Cyclone / Storm">Cyclone / Storm</option>
                        <option value="Other Distress">Other Distress</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 mb-1">Triage Priority *</label>
                    <select name="priority" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:bg-white focus:outline-none focus:border-[#1d63d8]">
                        <option value="Critical">Critical (Immediate Life Threat)</option>
                        <option value="High">High (Serious Threat)</option>
                        <option value="Medium">Medium (Assistance Required)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Optional Distress Message / Notes</label>
                <textarea name="message" rows="2" placeholder="Describe caller's distress situation (optional)..." class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 leading-relaxed focus:bg-white focus:outline-none focus:border-[#1d63d8] font-medium"></textarea>
            </div>

            <div class="flex justify-end gap-2.5 pt-3 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('manualSosModal').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold cursor-pointer text-xs">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold cursor-pointer text-xs">
                    Submit &amp; Broadcast SOS
                </button>
            </div>
        </form>

    </div>
</div>

<!-- ==================== JAVASCRIPT LOGIC ==================== -->
<script>
let currentAdminChatSosId = null;
let adminChatPoller = null;

function openAssignVolunteerModal(sosId, victimName, priority, currentAssigned) {
    document.getElementById('assignModalSosId').value = sosId;
    document.getElementById('assignModalSubtitle').innerText = `Assign Volunteer to #${sosId} (${victimName})`;
    document.getElementById('assignModalPriority').value = priority.toUpperCase() + ' PRIORITY';
    document.getElementById('assignVolunteerModal').classList.remove('hidden');
}

function openAdminChatModal(sosId, victimName, assignedUnit) {
    currentAdminChatSosId = sosId;
    document.getElementById('adminChatVictimName').innerText = `Lifeline: #${sosId} ${victimName}`;
    document.getElementById('adminChatVolunteerSub').innerText = `Assigned: ${assignedUnit || 'Volunteer Rajesh Kumar'}`;
    document.getElementById('adminChatModal').classList.remove('hidden');

    loadAdminChatMessages();
    if (adminChatPoller) clearInterval(adminChatPoller);
    adminChatPoller = setInterval(loadAdminChatMessages, 3000);
}

function closeAdminChatModal() {
    if (adminChatPoller) clearInterval(adminChatPoller);
    adminChatPoller = null;
    document.getElementById('adminChatModal').classList.add('hidden');
}

async function loadAdminChatMessages() {
    if (!currentAdminChatSosId) return;
    try {
        const res = await fetch(`api/victim_volunteer_chat_fetch.php?sos_id=${currentAdminChatSosId}`);
        const data = await res.json();
        if (data.success && data.data) {
            renderAdminChatFeed(data.data.messages || [], data.data.victim_info);
        }
    } catch (e) {
        console.error('Error fetching chat:', e);
    }
}

function renderAdminChatFeed(messages, victimInfo) {
    const feed = document.getElementById('adminChatFeed');
    if (!feed) return;

    if (!messages || messages.length === 0) {
        feed.innerHTML = `
            <div class="text-center py-8 text-slate-400 text-xs space-y-2">
                <i class="fa-solid fa-comments text-2xl text-slate-300"></i>
                <p>No messages yet in this channel. Send the first message or dispatch order below.</p>
            </div>
        `;
        return;
    }

    let html = '';
    messages.forEach(m => {
        const isSelfAdmin = (m.sender_role === 'admin');
        const isVolunteer = (m.sender_role === 'volunteer');
        const isDispatch = (m.message_type === 'dispatch');

        if (isDispatch) {
            html += `
                <div class="flex justify-center my-1.5">
                    <span class="px-3 py-1 rounded-full bg-slate-200/80 text-slate-700 text-[10px] font-bold border border-slate-300 mono text-center max-w-md">
                        ${escapeHtml(m.message)}
                    </span>
                </div>
            `;
        } else if (isSelfAdmin) {
            html += `
                <div class="flex flex-col items-end space-y-0.5">
                    <span class="text-[10px] font-bold text-slate-500 mr-1 flex items-center gap-1">
                        <i class="fa-solid fa-shield text-indigo-500 text-[9px]"></i> ${escapeHtml(m.sender_name)} (Command)
                    </span>
                    <div class="bg-indigo-600 text-white rounded-2xl rounded-tr-xs px-3.5 py-2 text-xs shadow-xs max-w-sm">
                        ${escapeHtml(m.message)}
                    </div>
                    <span class="text-[9px] text-slate-400 font-mono mr-1">${formatTime(m.created_at)}</span>
                </div>
            `;
        } else if (isVolunteer) {
            html += `
                <div class="flex flex-col items-end space-y-0.5">
                    <span class="text-[10px] font-bold text-emerald-700 mr-1 flex items-center gap-1">
                        <i class="fa-solid fa-hand-holding-heart text-emerald-500 text-[9px]"></i> ${escapeHtml(m.sender_name)} (Volunteer)
                    </span>
                    <div class="bg-emerald-600 text-white rounded-2xl rounded-tr-xs px-3.5 py-2 text-xs shadow-xs max-w-sm">
                        ${escapeHtml(m.message)}
                    </div>
                    <span class="text-[9px] text-slate-400 font-mono mr-1">${formatTime(m.created_at)}</span>
                </div>
            `;
        } else {
            // Victim / Citizen message
            html += `
                <div class="flex flex-col items-start space-y-0.5">
                    <span class="text-[10px] font-bold text-slate-600 ml-1 flex items-center gap-1">
                        <i class="fa-solid fa-user text-slate-400 text-[9px]"></i> ${escapeHtml(m.sender_name)} (Citizen)
                    </span>
                    <div class="bg-white text-slate-900 border border-slate-200 rounded-2xl rounded-tl-xs px-3.5 py-2 text-xs shadow-2xs max-w-sm">
                        ${escapeHtml(m.message)}
                    </div>
                    <span class="text-[9px] text-slate-400 font-mono ml-1">${formatTime(m.created_at)}</span>
                </div>
            `;
        }
    });

    feed.innerHTML = html;
    feed.scrollTop = feed.scrollHeight;
}

async function submitAdminChatMsg(e) {
    e.preventDefault();
    const input = document.getElementById('adminChatInput');
    const msg = input.value.trim();
    if (!msg || !currentAdminChatSosId) return;

    input.value = '';
    try {
        const res = await fetch('api/victim_volunteer_chat_send.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                sos_id: currentAdminChatSosId,
                message: msg,
                sender_name: 'Disaster Command (Admin)',
                message_type: 'text'
            })
        });
        const data = await res.json();
        if (data.success) {
            loadAdminChatMessages();
        }
    } catch (err) {
        console.error('Error sending message:', err);
    }
}

function sendAdminQuickMsg(msg) {
    document.getElementById('adminChatInput').value = msg;
    const form = document.querySelector('#adminChatModal form');
    if (form) {
        form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    }
}

function openSosModal(sos) {
    document.getElementById('modalSosTitle').textContent = `SOS Distress Dossier #${sos.id} • ${sos.emergency_type}`;
    document.getElementById('modalSosStatus').textContent = sos.status;
    document.getElementById('modalSosPriority').textContent = (sos.priority || 'Critical').toUpperCase();
    document.getElementById('modalSosTrapped').textContent = (sos.persons_count || '1 - 4') + ' Persons';
    
    document.getElementById('modalSosSender').textContent = sos.sender_name;
    document.getElementById('modalSosPhone').textContent = sos.sender_phone;
    document.getElementById('modalSosBlood').textContent = sos.blood_type || 'Unknown';
    document.getElementById('modalSosAge').textContent = sos.age ? (sos.age + ' years old') : 'Not specified';
    
    document.getElementById('modalSosGps').textContent = `${Number(sos.gps_lat).toFixed(5)}° N, ${Number(sos.gps_lng).toFixed(5)}° E`;
    document.getElementById('modalSosMapLink').href = `https://maps.google.com/?q=${sos.gps_lat},${sos.gps_lng}`;
    document.getElementById('modalSosMessage').textContent = sos.message ? `"${sos.message}"` : 'No additional text message attached.';
    document.getElementById('modalSosNeeds').textContent = sos.medical_needs || 'Standard Search & Rescue Assistance';

    document.getElementById('modalFormSosId').value = sos.id;
    document.getElementById('modalFormStatusSelect').value = sos.status;
    document.getElementById('modalFormAgencyInput').value = sos.assigned_unit || sos.dispatch_agency || '';

    document.getElementById('sosDetailModal').classList.remove('hidden');
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function formatTime(dtStr) {
    if (!dtStr) return '';
    const d = new Date(dtStr);
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

// Auto-open specific SOS if passed in URL e.g. sos.php?id=4
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const targetId = urlParams.get('id');
    if (targetId) {
        const found = <?= json_encode($sosList) ?>.find(item => item.id == targetId);
        if (found) {
            openSosModal(found);
        }
    }
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
