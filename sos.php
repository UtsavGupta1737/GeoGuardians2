<?php
// sos.php - DisasterSafe Universal SOS Alerts & Triage Command Hub (Government Theme)
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

// Handle Dispatch & Triage Actions (POST)
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

    // 2. CREATE MANUAL DISPATCH CALL (e.g. Phone / Radio Call)
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

    // 3. SUPERADMIN DELETE SOS
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
    $query .= " AND (s.sender_name LIKE :s1 OR s.sender_phone LIKE :s2 OR s.message LIKE :s3 OR s.emergency_type LIKE :s4 OR s.dispatch_agency LIKE :s5)";
    $params[':s1'] = "%$search%";
    $params[':s2'] = "%$search%";
    $params[':s3'] = "%$search%";
    $params[':s4'] = "%$search%";
    $params[':s5'] = "%$search%";
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
                <p class="text-xs text-slate-400 mt-1">
                    Multi-agency emergency distress queue across Delhi-NCR
                </p>
            </div>

            <div class="flex items-center gap-3">
                <button type="button" onclick="document.getElementById('manualSosModal').classList.remove('hidden')" class="px-4 py-2 rounded-lg bg-white hover:bg-slate-50 text-slate-700 font-semibold text-xs border border-slate-200 hover:border-slate-300 flex items-center gap-2 transition-colors cursor-pointer">
                    <i class="fa-solid fa-plus text-slate-400"></i>
                    <span>Log Distress Call</span>
                </button>
            </div>
        </div>

        <!-- SOS Status Filter Bar -->
        <?php $activeCount = $totalSos - $resolvedCount; ?>
        <section class="flex items-center gap-1.5 overflow-x-auto pb-1">
            <a href="sos.php<?= $search ? '?search=' . urlencode($search) : '' ?>" class="shrink-0 px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-all <?= (!$statusFilter && !$priorityFilter) ? 'bg-slate-900 text-white' : 'bg-white text-slate-500 border border-slate-200 hover:border-slate-300 hover:text-slate-700' ?>">
                All <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] <?= (!$statusFilter && !$priorityFilter) ? 'bg-white/15 text-white/80' : 'bg-slate-100 text-slate-400' ?>"><?= $totalSos ?></span>
            </a>
            <a href="sos.php?status=Pending<?= $search ? '&search=' . urlencode($search) : '' ?>" class="shrink-0 px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-all <?= $statusFilter === 'Pending' ? 'bg-slate-900 text-white' : 'bg-white text-slate-500 border border-slate-200 hover:border-slate-300 hover:text-slate-700' ?>">
                SOS <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] <?= $statusFilter === 'Pending' ? 'bg-white/15 text-white/80' : 'bg-slate-100 text-slate-400' ?>"><?= $pendingCount ?></span>
            </a>
            <a href="sos.php?status=active<?= $search ? '&search=' . urlencode($search) : '' ?>" class="shrink-0 px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-all <?= $statusFilter === 'active' ? 'bg-slate-900 text-white' : 'bg-white text-slate-500 border border-slate-200 hover:border-slate-300 hover:text-slate-700' ?>">
                Active <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] <?= $statusFilter === 'active' ? 'bg-white/15 text-white/80' : 'bg-slate-100 text-slate-400' ?>"><?= $activeCount ?></span>
            </a>
            <a href="sos.php?status=Resolved<?= $search ? '&search=' . urlencode($search) : '' ?>" class="shrink-0 px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-all <?= $statusFilter === 'Resolved' ? 'bg-slate-900 text-white' : 'bg-white text-slate-500 border border-slate-200 hover:border-slate-300 hover:text-slate-700' ?>">
                Rescued <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] <?= $statusFilter === 'Resolved' ? 'bg-white/15 text-white/80' : 'bg-slate-100 text-slate-400' ?>"><?= $resolvedCount ?></span>
            </a>
        </section>

        <!-- Compact Search & Filter Controls -->
        <section class="bg-white p-3 rounded-xl border border-slate-200">
            <form method="GET" action="sos.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-2 text-xs">
                
                <!-- Search Keyword -->
                <div class="lg:col-span-2 relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-[10px]"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search victim name, phone, agency, crisis type..." class="w-full pl-8 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-900 focus:outline-none focus:border-[#1d63d8] focus:bg-white text-xs">
                </div>

                <!-- Status Filter -->
                <div>
                    <select name="status" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-700 focus:outline-none focus:border-[#1d63d8] focus:bg-white text-xs">
                        <option value="">Status: All (<?= $totalSos ?>)</option>
                        <option value="Pending" <?= $statusFilter === 'Pending' ? 'selected' : '' ?>>Pending Critical</option>
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>All Active Rescues</option>
                        <option value="NDRF Dispatched" <?= $statusFilter === 'NDRF Dispatched' ? 'selected' : '' ?>>NDRF Dispatched</option>
                        <option value="Police Dispatched" <?= $statusFilter === 'Police Dispatched' ? 'selected' : '' ?>>Police Dispatched</option>
                        <option value="Fire Dispatched" <?= $statusFilter === 'Fire Dispatched' ? 'selected' : '' ?>>Fire Dispatched</option>
                        <option value="EMS Dispatched" <?= $statusFilter === 'EMS Dispatched' ? 'selected' : '' ?>>EMS Dispatched</option>
                        <option value="Volunteer Responding" <?= $statusFilter === 'Volunteer Responding' ? 'selected' : '' ?>>Volunteer Assigned</option>
                        <option value="Resolved" <?= $statusFilter === 'Resolved' ? 'selected' : '' ?>>Resolved / Rescued</option>
                    </select>
                </div>

                <!-- Priority Filter -->
                <div>
                    <select name="priority" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-700 focus:outline-none focus:border-[#1d63d8] focus:bg-white text-xs">
                        <option value="">Priority: All</option>
                        <option value="Critical" <?= $priorityFilter === 'Critical' ? 'selected' : '' ?>>Critical Priority</option>
                        <option value="High" <?= $priorityFilter === 'High' ? 'selected' : '' ?>>High Priority</option>
                        <option value="Medium" <?= $priorityFilter === 'Medium' ? 'selected' : '' ?>>Medium Priority</option>
                    </select>
                </div>

                <!-- Submit / Reset Buttons -->
                <div class="flex items-center gap-1.5">
                    <button type="submit" class="flex-1 py-2 px-3 rounded-lg bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs transition-colors cursor-pointer">
                        Filter
                    </button>
                    <?php if ($search || $statusFilter || $priorityFilter || $categoryFilter): ?>
                        <a href="sos.php" class="py-2 px-3 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 transition-colors text-center text-xs" title="Clear Filters">
                            <i class="fa-solid fa-rotate-left text-[10px]"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <!-- COMPACT HIGH-DENSITY SOS INCIDENT FEED -->
        <section class="space-y-2">
            <?php if (empty($sosList)): ?>
                <div class="bg-white p-8 text-center rounded-xl border border-slate-200 text-slate-400">
                    <i class="fa-solid fa-bell-slash text-2xl mb-2 text-slate-300 block"></i>
                    <h3 class="text-sm font-semibold text-slate-600">No SOS Alerts Found</h3>
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
                        'Pending' => 'border-l-red-400',
                        'Police Dispatched' => 'border-l-slate-400',
                        'Fire Dispatched' => 'border-l-slate-400',
                        'EMS Dispatched' => 'border-l-slate-400',
                        'Volunteer Responding' => 'border-l-slate-400',
                        'Resolved' => 'border-l-emerald-400',
                        default => 'border-l-slate-300'
                    };
                    $statusBadge = match($sos['status']) {
                        'Pending' => 'bg-red-50 text-red-700 border-red-200',
                        'Resolved' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                        default => 'bg-slate-100 text-slate-600 border-slate-200'
                    };
                    $typeIcon = '';
                ?>
                <div class="bg-white p-3 sm:p-3.5 rounded-xl border border-slate-200 border-l-[3px] <?= $borderColor ?> hover:border-slate-300 transition-colors cursor-pointer group" onclick="openSosModal(<?= htmlspecialchars(json_encode($sos)) ?>)">
                    
                    <!-- Row 1: Badges, Caller, Phone, GPS, Persons & Quick Dispatch Strip -->
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-2">
                        
                        <!-- Left: Badges, Identification & Coordinates -->
                        <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1 text-xs">
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide border <?= $priorityColor ?>">
                                <?= htmlspecialchars($sos['priority'] ?? 'CRITICAL') ?>
                            </span>
                            <span class="text-[11px] font-mono font-bold text-slate-500">
                                #<?= $sos['id'] ?>
                            </span>
                            <span class="text-xs font-semibold text-slate-900">
                                <?= htmlspecialchars($sos['emergency_type']) ?>
                            </span>

                            <span class="text-slate-300">|</span>

                            <!-- Caller Name -->
                            <span class="text-xs text-slate-700 flex items-center gap-1">
                                <i class="fa-solid fa-user text-[9px] text-slate-400"></i>
                                <?= htmlspecialchars($sos['sender_name']) ?>
                            </span>

                            <!-- Phone & WhatsApp -->
                            <div class="flex items-center gap-1" onclick="event.stopPropagation()">
                                <a href="tel:<?= urlencode($sos['sender_phone']) ?>" class="text-[11px] font-mono text-slate-600 hover:text-[#1d63d8] hover:underline flex items-center gap-1">
                                    <i class="fa-solid fa-phone text-[8px] text-slate-400"></i> <?= htmlspecialchars($sos['sender_phone']) ?>
                                </a>
                                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $sos['sender_phone']) ?>" target="_blank" class="text-slate-400 hover:text-slate-600 p-0.5" title="Open WhatsApp Chat">
                                    <i class="fa-brands fa-whatsapp text-xs"></i>
                                </a>
                            </div>

                            <!-- GPS Pill -->
                            <a href="https://maps.google.com/?q=<?= $sos['gps_lat'] ?>,<?= $sos['gps_lng'] ?>" target="_blank" class="text-[10px] font-mono text-slate-500 hover:text-[#1d63d8] hover:underline flex items-center gap-1" onclick="event.stopPropagation()" title="Open in Google Maps">
                                <i class="fa-solid fa-location-crosshairs text-[9px] text-slate-400"></i>
                                <?= number_format((float)$sos['gps_lat'], 4) ?>, <?= number_format((float)$sos['gps_lng'], 4) ?>
                            </a>

                            <!-- Persons Range -->
                            <span class="text-[10px] font-semibold text-slate-600 flex items-center gap-1">
                                <i class="fa-solid fa-users text-[8px] text-slate-400"></i> <?= htmlspecialchars($sos['persons_count'] ?? '1 - 4') ?>
                            </span>

                            <?php if (!empty($sos['blood_type']) && $sos['blood_type'] !== 'Unknown'): ?>
                                <span class="text-[10px] font-semibold text-slate-500">
                                    <?= htmlspecialchars($sos['blood_type']) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Right: Status Badge & Quick 1-Click Dispatch Buttons -->
                        <div class="flex items-center gap-1.5 shrink-0" onclick="event.stopPropagation()">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold border <?= $statusBadge ?> uppercase tracking-wide">
                                <?= htmlspecialchars($sos['status']) ?>
                            </span>

                            <form method="POST" action="sos.php" class="flex items-center gap-1">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="sos_id" value="<?= $sos['id'] ?>">

                                <?php if ($sos['status'] === 'Pending'): ?>
                                    <button type="submit" name="status" value="NDRF Dispatched" class="px-2 py-1 rounded border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 font-semibold text-[10px] transition-colors cursor-pointer" title="Dispatch NDRF">
                                        NDRF
                                    </button>
                                    <button type="submit" name="status" value="Police Dispatched" class="px-2 py-1 rounded border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 font-semibold text-[10px] transition-colors cursor-pointer" title="Dispatch Police">
                                        Police
                                    </button>
                                    <button type="submit" name="status" value="Fire Dispatched" class="px-2 py-1 rounded border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 font-semibold text-[10px] transition-colors cursor-pointer" title="Dispatch Fire">
                                        Fire
                                    </button>
                                    <button type="submit" name="status" value="EMS Dispatched" class="px-2 py-1 rounded border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 font-semibold text-[10px] transition-colors cursor-pointer" title="Dispatch EMS">
                                        EMS
                                    </button>
                                    <button type="submit" name="status" value="Volunteer Responding" class="px-2 py-1 rounded border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 font-semibold text-[10px] transition-colors cursor-pointer" title="Assign Volunteer">
                                        Volunteer
                                    </button>
                                <?php elseif ($sos['status'] !== 'Resolved'): ?>
                                    <button type="submit" name="status" value="Resolved" class="px-2 py-1 rounded border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 font-semibold text-[10px] transition-colors flex items-center gap-1 cursor-pointer">
                                        <i class="fa-solid fa-check text-[8px] text-slate-400"></i> Resolved
                                    </button>
                                <?php else: ?>
                                    <button type="submit" name="status" value="Pending" class="px-2 py-1 rounded border border-slate-200 bg-white hover:bg-slate-50 text-slate-500 font-semibold text-[10px] transition-colors cursor-pointer">
                                        Re-open
                                    </button>
                                <?php endif; ?>
                            </form>

                            <button type="button" onclick="openSosModal(<?= htmlspecialchars(json_encode($sos)) ?>)" class="p-1 px-1.5 rounded border border-slate-200 bg-white hover:bg-slate-50 text-slate-500 text-[10px] transition-colors cursor-pointer" title="View Full Dossier">
                                <i class="fa-solid fa-expand text-[9px]"></i>
                            </button>

                            <?php if ($isSuperAdmin): ?>
                                <form method="POST" action="sos.php" onsubmit="return confirm('Permanently delete this SOS record?');" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="action" value="delete_sos">
                                    <input type="hidden" name="sos_id" value="<?= $sos['id'] ?>">
                                    <button type="submit" class="p-1 px-1.5 rounded border border-slate-200 bg-white hover:bg-red-50 text-slate-400 hover:text-red-500 text-[10px] transition-colors cursor-pointer" title="Delete SOS">
                                        <i class="fa-solid fa-trash-can text-[9px]"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>

                    </div>

                    <!-- Row 2: Distress Note Snippet, Assigned Agency & Timestamp -->
                    <div class="mt-2 pt-2 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-1 text-xs text-slate-400">
                        <div class="flex items-center gap-2 min-w-0 flex-1">
                            <?php if (!empty($sos['message'])): ?>
                                <span class="text-slate-500 truncate text-[11px]">
                                    <i class="fa-solid fa-quote-left text-slate-300 mr-1 text-[8px]"></i>
                                    <?= htmlspecialchars($sos['message']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-slate-300 italic text-[11px]">One-touch GPS distress beacon triggered.</span>
                            <?php endif; ?>
                        </div>

                        <div class="flex items-center gap-3 shrink-0 text-[11px]">
                            <?php if (!empty($sos['dispatch_agency'])): ?>
                                <span class="text-slate-500 font-medium flex items-center gap-1 text-[11px]">
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

<!-- INTERACTIVE EXPANDED SOS DETAIL & TRIAGE MODAL -->
<div id="sosDetailModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white border border-slate-200 rounded-xl w-full max-w-2xl max-h-[90vh] flex flex-col shadow-2xl overflow-hidden">
        
        <!-- Modal Header -->
        <div class="h-14 px-6 bg-white border-b border-slate-100 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3">
                <span class="w-2 h-2 rounded-full bg-slate-900"></span>
                <h3 class="text-sm font-bold text-slate-900" id="modalSosTitle">SOS Distress Dossier</h3>
            </div>
            <button type="button" onclick="document.getElementById('sosDetailModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-700 p-1.5 rounded-lg hover:bg-slate-100 transition-colors cursor-pointer">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <!-- Modal Body (Scrollable Details) -->
        <div class="flex-1 overflow-y-auto p-6 space-y-5 text-xs text-slate-700">
            
            <!-- Callout Bar -->
            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">Current Status</span>
                    <span id="modalSosStatus" class="font-bold text-sm text-slate-900"></span>
                </div>
                <div>
                    <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">Priority Level</span>
                    <span id="modalSosPriority" class="font-bold text-sm text-slate-900"></span>
                </div>
                <div>
                    <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">People Trapped</span>
                    <span id="modalSosTrapped" class="font-bold text-sm text-slate-900"></span>
                </div>
            </div>

            <!-- Victim Dossier -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-1">
                    <span class="text-[10px] font-semibold text-slate-400 uppercase">Victim / Contact Name</span>
                    <p id="modalSosSender" class="font-semibold text-slate-900 text-sm"></p>
                </div>
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-1">
                    <span class="text-[10px] font-semibold text-slate-400 uppercase">Direct Phone / Hotline</span>
                    <p id="modalSosPhone" class="font-semibold text-slate-700 text-sm font-mono"></p>
                </div>
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-1">
                    <span class="text-[10px] font-semibold text-slate-400 uppercase">Blood Group &amp; Vitals</span>
                    <p id="modalSosBlood" class="font-semibold text-slate-700"></p>
                </div>
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-1">
                    <span class="text-[10px] font-semibold text-slate-400 uppercase">Age of Primary Caller</span>
                    <p id="modalSosAge" class="font-semibold text-slate-900"></p>
                </div>
            </div>

            <!-- GPS Coordinates -->
            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-2">
                <span class="text-[10px] font-semibold text-slate-400 uppercase flex items-center justify-between">
                    <span class="flex items-center gap-1.5"><i class="fa-solid fa-satellite-dish text-slate-400"></i> Geospatial GPS Coordinates</span>
                    <a id="modalSosMapLink" href="#" target="_blank" class="text-[#1d63d8] hover:underline font-semibold text-[11px] flex items-center gap-1">
                        <span>Open in Google Maps</span> <i class="fa-solid fa-arrow-up-right-from-square text-[8px]"></i>
                    </a>
                </span>
                <p id="modalSosGps" class="font-mono font-semibold text-slate-900 text-sm"></p>
            </div>

            <!-- Distress Message -->
            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-2">
                <span class="text-[10px] font-semibold text-slate-400 uppercase flex items-center gap-1.5">
                    <i class="fa-solid fa-comment-dots text-slate-400"></i> Distress Transcript &amp; Message
                </span>
                <p id="modalSosMessage" class="text-slate-700 leading-relaxed italic"></p>
            </div>

            <!-- Medical Needs -->
            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-1">
                <span class="text-[10px] font-semibold text-slate-400 uppercase">System Triaged Relief Supplies &amp; Equipment</span>
                <p id="modalSosNeeds" class="font-semibold text-slate-700"></p>
            </div>

            <!-- Dispatch Update Form -->
            <form method="POST" action="sos.php" class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-3">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="sos_id" id="modalFormSosId" value="">

                <span class="text-xs font-semibold text-slate-700 flex items-center gap-2">
                    <i class="fa-solid fa-paper-plane text-slate-400"></i> Command Dispatch &amp; Status Reassignment
                </span>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    <div>
                        <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Target Status</label>
                        <select name="status" id="modalFormStatusSelect" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-slate-900 text-xs focus:outline-none focus:border-[#1d63d8]">
                            <option value="Pending">Pending (Unresolved)</option>
                            <option value="NDRF Dispatched">NDRF Tactical Boat Squad</option>
                            <option value="Police Dispatched">Police Perimeter &amp; Cordon</option>
                            <option value="Fire Dispatched">Fire &amp; Hazmat Engine</option>
                            <option value="EMS Dispatched">Advanced Life Support Ambulance</option>
                            <option value="Volunteer Responding">Volunteer Relief Corps</option>
                            <option value="Resolved">Rescued &amp; Resolved</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Assigned Agency / Squad</label>
                        <input type="text" name="dispatch_agency" id="modalFormAgencyInput" placeholder="e.g. NDRF Boat Unit 4, Fire Squad 2" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-slate-900 text-xs focus:outline-none focus:border-[#1d63d8]">
                    </div>
                </div>

                <div class="flex justify-end pt-1">
                    <button type="submit" class="px-4 py-2 rounded-lg bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs transition-colors cursor-pointer">
                        Execute Tactical Dispatch
                    </button>
                </div>
            </form>

        </div>

    </div>
</div>

<!-- MANUAL LOG DISTRESS MODAL (FOR DISPATCHERS) -->
<div id="manualSosModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white border border-slate-200 rounded-xl w-full max-w-xl max-h-[90vh] flex flex-col shadow-2xl overflow-hidden">
        
        <div class="h-14 px-6 bg-white border-b border-slate-100 flex items-center justify-between shrink-0">
            <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-headset text-slate-400"></i>
                <span>Log Inbound SOS Distress Call</span>
            </h3>
            <button type="button" onclick="document.getElementById('manualSosModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-700 p-1.5 rounded-lg hover:bg-slate-100 transition-colors cursor-pointer">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <form method="POST" action="sos.php" class="flex-1 overflow-y-auto p-6 space-y-4 text-xs">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="create_sos">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Victim / Caller Name *</label>
                    <input type="text" name="sender_name" required placeholder="e.g. Suresh Kumar" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-900 focus:bg-white focus:outline-none focus:border-[#1d63d8]">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Contact Phone Number *</label>
                    <input type="text" name="sender_phone" required placeholder="e.g. +91 98112 34567" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-900 font-mono focus:bg-white focus:outline-none focus:border-[#1d63d8]">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">GPS Latitude *</label>
                    <input type="number" step="0.0001" name="gps_lat" required value="28.6139" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-900 font-mono focus:bg-white focus:outline-none focus:border-[#1d63d8]">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">GPS Longitude *</label>
                    <input type="number" step="0.0001" name="gps_lng" required value="77.2090" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-900 font-mono focus:bg-white focus:outline-none focus:border-[#1d63d8]">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Blood Group</label>
                    <select name="blood_type" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-900 focus:bg-white focus:outline-none focus:border-[#1d63d8]">
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
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Caller Age</label>
                    <input type="number" name="age" placeholder="e.g. 35" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-900 focus:bg-white focus:outline-none focus:border-[#1d63d8]">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Persons Trapped *</label>
                    <select name="persons_count" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-900 focus:bg-white focus:outline-none focus:border-[#1d63d8]">
                        <option value="1 - 4">1 - 4 Persons</option>
                        <option value="4 - 8">4 - 8 Persons</option>
                        <option value="8 - 12">8 - 12 Persons</option>
                        <option value="12+">12+ Persons</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Emergency Category *</label>
                    <select name="emergency_type" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-900 focus:bg-white focus:outline-none focus:border-[#1d63d8]">
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
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Triage Priority *</label>
                    <select name="priority" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-900 focus:bg-white focus:outline-none focus:border-[#1d63d8]">
                        <option value="Critical">Critical (Immediate Life Threat)</option>
                        <option value="High">High (Serious Threat)</option>
                        <option value="Medium">Medium (Assistance Required)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Optional Distress Message / Notes</label>
                <textarea name="message" rows="2" placeholder="Describe caller's distress situation (optional)..." class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-900 leading-relaxed focus:bg-white focus:outline-none focus:border-[#1d63d8]"></textarea>
            </div>

            <div class="flex justify-end gap-2.5 pt-3 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('manualSosModal').classList.add('hidden')" class="px-4 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold cursor-pointer text-xs">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 rounded-lg bg-slate-900 hover:bg-slate-800 text-white font-semibold cursor-pointer text-xs">
                    Submit &amp; Broadcast SOS
                </button>
            </div>
        </form>

    </div>
</div>

<script>
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
    document.getElementById('modalFormAgencyInput').value = sos.dispatch_agency || '';

    document.getElementById('sosDetailModal').classList.remove('hidden');
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
