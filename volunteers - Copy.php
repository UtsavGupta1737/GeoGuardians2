<?php
// volunteers.php - DisasterSafe Universal Volunteer Management & Deployment Hub
define('PAGE_TITLE', 'Volunteer Management');
require_once __DIR__ . '/auth.php';

requireLogin();
$currentUser = getCurrentUser($pdo);
$isSuperAdmin = isSuperAdmin($currentUser);
$hasVolunteerAccess = $isSuperAdmin || hasPermission($currentUser, 'access_volunteer');

if (!$hasVolunteerAccess) {
    setFlash('error', 'Access Restricted: You need Volunteer / NGO Management permissions.');
    header("Location: dashboard.php");
    exit;
}

$csrfToken = generateCsrfToken();

// Handle Form Submissions (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrf)) {
        setFlash('error', 'Invalid security token.');
        header("Location: volunteers.php");
        exit;
    }

    // 1. REGISTER NEW VOLUNTEER
    if ($action === 'create_volunteer') {
        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $skills = trim($_POST['skills'] ?? 'General Disaster Assistance');
        $qualifications = trim($_POST['qualifications'] ?? 'Community First Responder');
        $teamName = trim($_POST['team_name'] ?? 'Water & Food Relief Team');
        $organization = trim($_POST['organization'] ?? 'DisasterSafe Relief Volunteers');
        $location = trim($_POST['current_location'] ?? 'Delhi-NCR Central Base');
        $bloodType = trim($_POST['blood_type'] ?? 'O+');
        $status = trim($_POST['availability_status'] ?? 'Available / Standby');
        $experience = (int) ($_POST['experience_years'] ?? 1);

        if (!empty($fullName) && !empty($phone)) {
            $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($fullName) . '&background=10b981&color=fff';
            $stmt = $pdo->prepare("
                INSERT INTO volunteers (full_name, phone, email, skills, qualifications, team_name, organization, current_location, availability_status, blood_type, application_status, experience_years, avatar) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Approved', ?, ?)
            ");
            $stmt->execute([$fullName, $phone, $email, $skills, $qualifications, $teamName, $organization, $location, $status, $bloodType, $experience, $avatar]);

            logActivity($pdo, 'VOLUNTEER_REGISTERED', "Registered new volunteer: {$fullName} under {$teamName} ({$organization})");
            setFlash('success', "Volunteer '{$fullName}' successfully registered and added to {$teamName}!");
        } else {
            setFlash('error', 'Full Name and Phone Number are required.');
        }
        header("Location: volunteers.php");
        exit;
    }

    // 2. ASSIGN TASK TO VOLUNTEER
    if ($action === 'assign_task') {
        $volId = (int) ($_POST['volunteer_id'] ?? 0);
        $taskId = (int) ($_POST['task_id'] ?? 0);

        if ($volId > 0 && $taskId > 0) {
            $pdo->prepare("UPDATE volunteers SET assigned_task_id = ?, availability_status = 'Active on Task' WHERE id = ?")->execute([$taskId, $volId]);
            $pdo->prepare("UPDATE volunteer_tasks SET assigned_volunteers_count = assigned_volunteers_count + 1 WHERE id = ?")->execute([$taskId]);

            $vName = $pdo->query("SELECT full_name FROM volunteers WHERE id = {$volId}")->fetchColumn();
            $tName = $pdo->query("SELECT title FROM volunteer_tasks WHERE id = {$taskId}")->fetchColumn();

            logActivity($pdo, 'VOLUNTEER_TASK_ASSIGNED', "Assigned volunteer {$vName} to task '{$tName}'");
            setFlash('success', "Deployed '{$vName}' to mission: {$tName}");
        } else {
            setFlash('error', 'Please select a valid volunteer and target mission.');
        }
        header("Location: volunteers.php");
        exit;
    }

    // 3. RELEASE VOLUNTEER FROM TASK
    if ($action === 'release_task') {
        $volId = (int) ($_POST['volunteer_id'] ?? 0);
        $currTaskId = $pdo->query("SELECT assigned_task_id FROM volunteers WHERE id = {$volId}")->fetchColumn();

        if ($currTaskId) {
            $pdo->prepare("UPDATE volunteer_tasks SET assigned_volunteers_count = MAX(0, assigned_volunteers_count - 1) WHERE id = ?")->execute([$currTaskId]);
        }

        $pdo->prepare("UPDATE volunteers SET assigned_task_id = NULL, availability_status = 'Available / Standby' WHERE id = ?")->execute([$volId]);
        
        $vName = $pdo->query("SELECT full_name FROM volunteers WHERE id = {$volId}")->fetchColumn();
        logActivity($pdo, 'VOLUNTEER_RELEASED', "Released volunteer {$vName} back to Standby");
        setFlash('success', "{$vName} returned to Standby / Available pool.");
        header("Location: volunteers.php");
        exit;
    }

    // 4. BROADCAST MESSAGE TO VOLUNTEERS
    if ($action === 'broadcast_message') {
        $targetType = trim($_POST['target_type'] ?? 'ALL'); // 'ALL', 'TEAM', 'VOLUNTEER'
        $targetTeam = trim($_POST['target_team'] ?? '');
        $targetVolId = (int)($_POST['target_volunteer_id'] ?? 0);
        $priority = trim($_POST['priority'] ?? 'High Priority');
        $title = trim($_POST['title'] ?? 'Operational Directive');
        $message = trim($_POST['message'] ?? '');
        $senderName = $currentUser['name'] ?? 'Superadmin Tactical Controller';

        if (!empty($message)) {
            $stmt = $pdo->prepare("
                INSERT INTO volunteer_broadcasts (sender_name, target_type, target_team, target_volunteer_id, priority, title, message) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$senderName, $targetType, $targetTeam, $targetVolId > 0 ? $targetVolId : null, $priority, $title, $message]);

            $targetDesc = ($targetType === 'ALL') ? 'All Registered Volunteers' : (($targetType === 'TEAM') ? "Team '{$targetTeam}'" : "Volunteer ID #{$targetVolId}");
            logActivity($pdo, 'VOLUNTEER_BROADCAST', "Dispatched broadcast [{$priority}] to {$targetDesc}: '{$title}'");
            setFlash('success', "Broadcast successfully transmitted to {$targetDesc}!");
        } else {
            setFlash('error', 'Broadcast message content cannot be empty.');
        }
        header("Location: volunteers.php");
        exit;
    }

    // 5. UPDATE VOLUNTEER STATUS & TEAM
    if ($action === 'update_volunteer_status') {
        $volId = (int) ($_POST['volunteer_id'] ?? 0);
        $newStatus = trim($_POST['availability_status'] ?? 'Available / Standby');
        $newTeam = trim($_POST['team_name'] ?? '');
        $newLocation = trim($_POST['current_location'] ?? '');

        $stmt = $pdo->prepare("UPDATE volunteers SET availability_status = ?, team_name = COALESCE(NULLIF(?, ''), team_name), current_location = COALESCE(NULLIF(?, ''), current_location) WHERE id = ?");
        $stmt->execute([$newStatus, $newTeam, $newLocation, $volId]);

        $vName = $pdo->query("SELECT full_name FROM volunteers WHERE id = {$volId}")->fetchColumn();
        logActivity($pdo, 'VOLUNTEER_STATUS_UPDATED', "Updated status of {$vName} to {$newStatus}");
        setFlash('success', "Updated profile & status for {$vName}.");
        header("Location: volunteers.php");
        exit;
    }

    // 6. DELETE VOLUNTEER
    if ($action === 'delete_volunteer' && $isSuperAdmin) {
        $volId = (int) ($_POST['volunteer_id'] ?? 0);
        $pdo->prepare("DELETE FROM volunteers WHERE id = ?")->execute([$volId]);
        logActivity($pdo, 'VOLUNTEER_DELETED', "Volunteer record #{$volId} deleted");
        setFlash('info', "Volunteer record removed.");
        header("Location: volunteers.php");
        exit;
    }
}

// Queries & Filtering
$search = trim($_GET['search'] ?? '');
$filterTeam = trim($_GET['team'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterOrg = trim($_GET['org'] ?? '');

// Active Volunteers Query
$activeQuery = "
    SELECT v.*, vt.title as task_title, vt.location as task_location, vt.category as task_category
    FROM volunteers v
    LEFT JOIN volunteer_tasks vt ON v.assigned_task_id = vt.id
    WHERE v.application_status = 'Approved'
";
$activeParams = [];

if (!empty($search)) {
    $activeQuery .= " AND (v.full_name LIKE :s1 OR v.phone LIKE :s2 OR v.skills LIKE :s3 OR v.current_location LIKE :s4 OR v.team_name LIKE :s5 OR v.organization LIKE :s6)";
    $activeParams[':s1'] = "%$search%";
    $activeParams[':s2'] = "%$search%";
    $activeParams[':s3'] = "%$search%";
    $activeParams[':s4'] = "%$search%";
    $activeParams[':s5'] = "%$search%";
    $activeParams[':s6'] = "%$search%";
}

if (!empty($filterTeam)) {
    $activeQuery .= " AND v.team_name = :team";
    $activeParams[':team'] = $filterTeam;
}

if (!empty($filterStatus)) {
    $activeQuery .= " AND v.availability_status = :status";
    $activeParams[':status'] = $filterStatus;
}

if (!empty($filterOrg)) {
    $activeQuery .= " AND v.organization = :org";
    $activeParams[':org'] = $filterOrg;
}

$activeQuery .= " ORDER BY CASE WHEN v.availability_status = 'Active on Task' THEN 1 WHEN v.availability_status = 'Available / Standby' THEN 2 ELSE 3 END, v.id ASC";
$activeStmt = $pdo->prepare($activeQuery);
$activeStmt->execute($activeParams);
$volunteersList = $activeStmt->fetchAll();

// Open Volunteer Tasks (for mission assignment dropdown)
$openTasks = $pdo->query("SELECT id, title, category, location, required_volunteers, assigned_volunteers_count FROM volunteer_tasks WHERE status != 'Completed' ORDER BY id DESC")->fetchAll();

// Fetch Recent Broadcasts
$recentBroadcasts = $pdo->query("SELECT * FROM volunteer_broadcasts ORDER BY id DESC LIMIT 5")->fetchAll();

// Standard Specialized Response Teams
$STANDARD_TEAMS = [
    'Medical & First Aid Team' => ['icon' => 'fa-kit-medical', 'color' => 'rose', 'desc' => 'Triage, Paramedics, Burn Care, Ambulances'],
    'Fire & Hazmat Support Team' => ['icon' => 'fa-fire-extinguisher', 'color' => 'red', 'desc' => 'Chemical Solvent Control, Evacuation Cordon'],
    'Water & Food Relief Team' => ['icon' => 'fa-bowl-rice', 'color' => 'amber', 'desc' => 'Drinking Water, Dry Rations, Evacuee Feeding'],
    'Search & Rescue (SAR) Team' => ['icon' => 'fa-person-falling-burst', 'color' => 'blue', 'desc' => 'Zodiac Inflatable Boats, Drone Recon, Extrication'],
    'Shelter Management Team' => ['icon' => 'fa-campground', 'color' => 'emerald', 'desc' => 'Tent Cities, Bedding, Evacuee Registration'],
    'Logistics & Supply Team' => ['icon' => 'fa-boxes-packing', 'color' => 'indigo', 'desc' => 'Depot Loading, Transport Convoy, Inventory']
];

// Standard Partner Organizations
$STANDARD_ORGS = [
    'Red Cross Society Delhi-NCR',
    'Khalsa Aid India',
    'Robin Hood Army',
    'Goonj Disaster Relief',
    'Civil Defence Corps Delhi',
    'DisasterSafe Relief Volunteers'
];

// Team counts
$teamCounts = [];
foreach ($STANDARD_TEAMS as $tName => $tMeta) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM volunteers WHERE team_name = ? AND application_status = 'Approved'");
    $stmt->execute([$tName]);
    $teamCounts[$tName] = (int)$stmt->fetchColumn();
}

// KPI Aggregates
$totalEnrolled = (int) $pdo->query("SELECT COUNT(*) FROM volunteers WHERE application_status = 'Approved'")->fetchColumn();
$activeOnTask = (int) $pdo->query("SELECT COUNT(*) FROM volunteers WHERE application_status = 'Approved' AND availability_status = 'Active on Task'")->fetchColumn();
$standbyReady = (int) $pdo->query("SELECT COUNT(*) FROM volunteers WHERE application_status = 'Approved' AND availability_status = 'Available / Standby'")->fetchColumn();
$totalBroadcasts = (int) $pdo->query("SELECT COUNT(*) FROM volunteer_broadcasts")->fetchColumn();

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div class="flex-1 flex flex-col min-w-0 bg-[#0a0f1d] min-h-screen overflow-y-auto">
    <?php require_once __DIR__ . '/navbar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto w-full">
        
        <!-- HEADER BANNER & TACTICAL TOOLBAR -->
        <section class="glass-panel p-5 sm:p-6 rounded-2xl border border-[#243049] relative overflow-hidden shadow-2xl">
            <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-emerald-600/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 relative z-10">
                <div class="space-y-1.5">
                    <div class="flex items-center gap-2.5">
                        <span class="w-8 h-8 rounded-xl bg-emerald-600/20 border border-emerald-500/30 flex items-center justify-center text-emerald-400 font-black text-base shadow-inner">
                            🤝
                        </span>
                        <div>
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-400">NGO & Volunteer Response Corps</span>
                            <h2 class="text-xl sm:text-2xl font-black text-white tracking-tight">Volunteer Management & Deployment Hub</h2>
                        </div>
                    </div>
                    <p class="text-xs text-slate-300 max-w-2xl leading-relaxed">
                        Full tactical overview of all registered volunteers, specialized team rosters (Medical, Fire, Food & Water, Search & Rescue), on-ground status tracking, 1-click mission task assignment, and targeted radio broadcast transmissions.
                    </p>
                </div>

                <!-- Tactical Header Actions -->
                <div class="flex flex-wrap items-center gap-3 shrink-0">
                    <button type="button" onclick="openBroadcastModal()" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-lg shadow-indigo-600/30 transition-all flex items-center gap-2">
                        <i class="fa-solid fa-bullhorn"></i> Broadcast Directive
                    </button>
                    <button type="button" onclick="document.getElementById('registerVolunteerModal').classList.remove('hidden')" class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-lg shadow-emerald-600/30 transition-all flex items-center gap-2">
                        <i class="fa-solid fa-user-plus"></i> Register Volunteer
                    </button>
                    <a href="tasks.php" class="px-4 py-2.5 rounded-xl bg-[#11192e] hover:bg-slate-800 border border-[#243049] text-slate-200 font-bold text-xs transition-all flex items-center gap-2">
                        <i class="fa-solid fa-list-check text-emerald-400"></i> Missions Board
                    </a>
                </div>
            </div>
        </section>

        <!-- KPI METRICS STRIP -->
        <section class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="stat-card-accent p-3.5 rounded-xl border-t-2 border-t-emerald-500 shadow-md flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Volunteers</p>
                    <h3 class="text-2xl font-extrabold text-white mt-0.5"><?= $totalEnrolled ?></h3>
                    <span class="text-[10px] font-semibold text-emerald-400">Across 6 Specialized Teams</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 text-sm">
                    <i class="fa-solid fa-users"></i>
                </div>
            </div>

            <div class="stat-card-accent p-3.5 rounded-xl border-t-2 border-t-blue-500 shadow-md flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Active on Missions</p>
                    <h3 class="text-2xl font-extrabold text-blue-400 mt-0.5"><?= $activeOnTask ?></h3>
                    <span class="text-[10px] font-semibold text-blue-300">Deployed at Field Sites</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-400 text-sm">
                    <i class="fa-solid fa-person-walking-arrow-right"></i>
                </div>
            </div>

            <div class="stat-card-accent p-3.5 rounded-xl border-t-2 border-t-amber-500 shadow-md flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Available / Standby</p>
                    <h3 class="text-2xl font-extrabold text-amber-400 mt-0.5"><?= $standbyReady ?></h3>
                    <span class="text-[10px] font-semibold text-amber-300">Ready for Immediate Dispatch</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400 text-sm">
                    <i class="fa-solid fa-user-check"></i>
                </div>
            </div>

            <div class="stat-card-accent p-3.5 rounded-xl border-t-2 border-t-indigo-500 shadow-md flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Broadcast Directives</p>
                    <h3 class="text-2xl font-extrabold text-indigo-400 mt-0.5"><?= $totalBroadcasts ?></h3>
                    <span class="text-[10px] font-semibold text-slate-400">Live Team Communications</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400 text-sm">
                    <i class="fa-solid fa-tower-broadcast"></i>
                </div>
            </div>
        </section>

        <!-- SPECIALIZED RESPONSE TEAMS MATRIX CARDS -->
        <section class="space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="text-xs font-bold text-slate-300 uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-layer-group text-emerald-400"></i> Specialized Response Squads & Teams
                </h3>
                <span class="text-[11px] text-slate-400">Click any squad to filter roster</span>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <?php foreach ($STANDARD_TEAMS as $teamTitle => $tMeta): ?>
                    <?php 
                        $count = $teamCounts[$teamTitle] ?? 0;
                        $isSelected = ($filterTeam === $teamTitle);
                        $borderAccent = match($tMeta['color']) {
                            'rose' => 'border-rose-500/40 hover:border-rose-500 bg-rose-950/20',
                            'red' => 'border-red-500/40 hover:border-red-500 bg-red-950/20',
                            'amber' => 'border-amber-500/40 hover:border-amber-500 bg-amber-950/20',
                            'blue' => 'border-blue-500/40 hover:border-blue-500 bg-blue-950/20',
                            'emerald' => 'border-emerald-500/40 hover:border-emerald-500 bg-emerald-950/20',
                            default => 'border-indigo-500/40 hover:border-indigo-500 bg-indigo-950/20'
                        };
                    ?>
                    <a href="volunteers.php?team=<?= urlencode($teamTitle) ?>" class="p-3 rounded-xl border <?= $borderAccent ?> <?= $isSelected ? 'ring-2 ring-emerald-400 bg-slate-800/80' : '' ?> transition-all flex flex-col justify-between cursor-pointer group shadow-sm">
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <span class="w-7 h-7 rounded-lg bg-[#11192e] border border-[#243049] flex items-center justify-center text-xs text-white">
                                    <i class="fa-solid <?= $tMeta['icon'] ?>"></i>
                                </span>
                                <span class="px-2 py-0.5 rounded-full bg-[#11192e] text-[11px] font-extrabold text-white">
                                    <?= $count ?>
                                </span>
                            </div>
                            <h4 class="text-xs font-extrabold text-white group-hover:text-emerald-400 transition-colors leading-tight line-clamp-1">
                                <?= htmlspecialchars($teamTitle) ?>
                            </h4>
                            <p class="text-[10px] text-slate-400 line-clamp-1"><?= $tMeta['desc'] ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- LIVE BROADCAST ANNOUNCEMENTS TICKER -->
        <?php if (!empty($recentBroadcasts)): ?>
            <section class="glass-panel p-3.5 rounded-2xl border border-indigo-500/30 bg-[#0c1326]/90 shadow-xl space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-extrabold text-indigo-300 uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-bullhorn text-indigo-400 animate-pulse"></i> Live Broadcast Dispatch Feed
                    </span>
                    <button type="button" onclick="openBroadcastModal()" class="text-[10px] font-bold text-indigo-400 hover:text-indigo-300 transition-colors">
                        + New Broadcast Directive
                    </button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2.5 text-xs">
                    <?php foreach ($recentBroadcasts as $bc): ?>
                        <div class="p-2.5 rounded-xl bg-[#11192e] border border-[#243049] flex flex-col justify-between space-y-1.5">
                            <div class="flex items-center justify-between">
                                <span class="px-1.5 py-0.5 rounded text-[9px] font-extrabold <?= $bc['priority'] === 'Critical Emergency' ? 'bg-rose-950 text-rose-300 border border-rose-800' : 'bg-indigo-950 text-indigo-300 border border-indigo-800' ?>">
                                    <?= htmlspecialchars($bc['priority']) ?>
                                </span>
                                <span class="text-[10px] font-mono text-slate-400">
                                    To: <b><?= htmlspecialchars($bc['target_type'] === 'ALL' ? 'ALL SQUADS' : ($bc['target_team'] ?: 'Direct')) ?></b>
                                </span>
                            </div>
                            <p class="font-bold text-white text-[11px] truncate"><?= htmlspecialchars($bc['title']) ?></p>
                            <p class="text-[10px] text-slate-300 line-clamp-2"><?= htmlspecialchars($bc['message']) ?></p>
                            <div class="flex items-center justify-between pt-1 border-t border-[#243049]/60 text-[9px] text-slate-400">
                                <span>By: <?= htmlspecialchars($bc['sender_name']) ?></span>
                                <span><?= date('H:i, M j', strtotime($bc['created_at'])) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- VOLUNTEERS ROSTER DIRECTORY & CONTROLS -->
        <section class="glass-panel rounded-2xl border border-[#243049] shadow-2xl overflow-hidden space-y-4 p-4 sm:p-6">
            
            <!-- Filter & Search Toolbar -->
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 pb-4 border-b border-[#243049]">
                <div>
                    <h3 class="text-sm font-extrabold text-white flex items-center gap-2">
                        <i class="fa-solid fa-address-book text-emerald-400"></i> Volunteers & Field Personnel Roster
                    </h3>
                    <p class="text-[11px] text-slate-400">Showing <?= count($volunteersList) ?> verified field operatives</p>
                </div>

                <!-- Filters -->
                <form method="GET" action="volunteers.php" class="flex flex-wrap items-center gap-2 text-xs">
                    <!-- Search Input -->
                    <div class="relative min-w-[200px]">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, phone, location..." class="w-full pl-8 pr-3 py-1.5 bg-[#11192e] border border-[#243049] rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500">
                    </div>

                    <!-- Team Filter -->
                    <select name="team" onchange="this.form.submit()" class="px-2.5 py-1.5 bg-[#11192e] border border-[#243049] rounded-xl text-white font-semibold focus:outline-none">
                        <option value="">All Teams</option>
                        <?php foreach ($STANDARD_TEAMS as $tName => $tMeta): ?>
                            <option value="<?= htmlspecialchars($tName) ?>" <?= ($filterTeam === $tName) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Status Filter -->
                    <select name="status" onchange="this.form.submit()" class="px-2.5 py-1.5 bg-[#11192e] border border-[#243049] rounded-xl text-white font-semibold focus:outline-none">
                        <option value="">All Statuses</option>
                        <option value="Available / Standby" <?= ($filterStatus === 'Available / Standby') ? 'selected' : '' ?>>Available / Standby</option>
                        <option value="Active on Task" <?= ($filterStatus === 'Active on Task') ? 'selected' : '' ?>>Active on Task</option>
                        <option value="En-Route" <?= ($filterStatus === 'En-Route') ? 'selected' : '' ?>>En-Route</option>
                        <option value="Off-Duty" <?= ($filterStatus === 'Off-Duty') ? 'selected' : '' ?>>Off-Duty</option>
                    </select>

                    <!-- Reset Filters Button -->
                    <?php if (!empty($search) || !empty($filterTeam) || !empty($filterStatus) || !empty($filterOrg)): ?>
                        <a href="volunteers.php" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs transition-all">
                            Reset
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Volunteers Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-[#243049] text-slate-400 uppercase text-[10px] tracking-wider font-extrabold bg-[#0c1326]/50">
                            <th class="py-3 px-3">Operative ID & Name</th>
                            <th class="py-3 px-3">Team Assignment</th>
                            <th class="py-3 px-3">Organization / NGO</th>
                            <th class="py-3 px-3">Current Location</th>
                            <th class="py-3 px-3">Availability Status</th>
                            <th class="py-3 px-3">Active Mission</th>
                            <th class="py-3 px-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#243049]/50">
                        <?php if (empty($volunteersList)): ?>
                            <tr>
                                <td colspan="7" class="py-8 text-center text-slate-400">
                                    <i class="fa-solid fa-users-slash text-2xl text-slate-600 mb-2 block"></i>
                                    No volunteers found matching the current search / filter criteria.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($volunteersList as $v): ?>
                                <?php 
                                    $statusClass = match($v['availability_status']) {
                                        'Active on Task' => 'bg-blue-950 text-blue-300 border-blue-800',
                                        'Available / Standby' => 'bg-emerald-950 text-emerald-300 border-emerald-800',
                                        'En-Route' => 'bg-amber-950 text-amber-300 border-amber-800',
                                        default => 'bg-slate-800 text-slate-400 border-slate-700'
                                    };
                                    $teamIcon = match($v['team_name']) {
                                        'Medical & First Aid Team' => 'fa-kit-medical text-rose-400',
                                        'Fire & Hazmat Support Team' => 'fa-fire-extinguisher text-red-400',
                                        'Water & Food Relief Team' => 'fa-bowl-rice text-amber-400',
                                        'Search & Rescue (SAR) Team' => 'fa-person-falling-burst text-blue-400',
                                        'Shelter Management Team' => 'fa-campground text-emerald-400',
                                        default => 'fa-boxes-packing text-indigo-400'
                                    };
                                ?>
                                <tr class="hover:bg-[#131e36]/40 transition-colors group">
                                    <!-- ID & Name -->
                                    <td class="py-3.5 px-3">
                                        <div class="flex items-center gap-3">
                                            <img src="<?= htmlspecialchars($v['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($v['full_name']) . '&background=10b981&color=fff') ?>" alt="<?= htmlspecialchars($v['full_name']) ?>" class="w-8 h-8 rounded-full border border-[#243049] object-cover shrink-0">
                                            <div>
                                                <div class="flex items-center gap-1.5">
                                                    <span class="font-mono text-[9px] font-bold text-slate-400">VOL-<?= str_pad($v['id'], 3, '0', STR_PAD_LEFT) ?></span>
                                                    <span class="font-extrabold text-white text-xs"><?= htmlspecialchars($v['full_name']) ?></span>
                                                    <?php if ($v['blood_type'] && $v['blood_type'] !== 'Unknown'): ?>
                                                        <span class="px-1 py-0.2 rounded bg-rose-950 text-rose-300 border border-rose-800 text-[9px] font-bold"><?= htmlspecialchars($v['blood_type']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="text-[10px] text-slate-400 font-mono mt-0.5">
                                                    <i class="fa-solid fa-phone text-[9px] text-slate-500"></i> <?= htmlspecialchars($v['phone']) ?>
                                                </p>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Team Assignment -->
                                    <td class="py-3.5 px-3">
                                        <span class="flex items-center gap-1.5 font-bold text-slate-200">
                                            <i class="fa-solid <?= $teamIcon ?> text-xs"></i>
                                            <span><?= htmlspecialchars($v['team_name'] ?: 'Unassigned') ?></span>
                                        </span>
                                        <p class="text-[10px] text-slate-400 truncate max-w-[160px]"><?= htmlspecialchars($v['skills'] ?: 'General Logistics') ?></p>
                                    </td>

                                    <!-- Organization / NGO -->
                                    <td class="py-3.5 px-3">
                                        <span class="px-2 py-0.5 rounded-lg bg-[#11192e] border border-[#243049] text-[11px] font-semibold text-indigo-300">
                                            <?= htmlspecialchars($v['organization'] ?: 'DisasterSafe Relief Corps') ?>
                                        </span>
                                    </td>

                                    <!-- Current Location -->
                                    <td class="py-3.5 px-3">
                                        <span class="text-slate-300 flex items-center gap-1 text-[11px]">
                                            <i class="fa-solid fa-location-dot text-rose-400 text-[10px]"></i>
                                            <span class="truncate max-w-[150px]"><?= htmlspecialchars($v['current_location']) ?></span>
                                        </span>
                                    </td>

                                    <!-- Availability Status -->
                                    <td class="py-3.5 px-3">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold border <?= $statusClass ?>">
                                            <?= htmlspecialchars($v['availability_status']) ?>
                                        </span>
                                    </td>

                                    <!-- Active Task -->
                                    <td class="py-3.5 px-3">
                                        <?php if ($v['assigned_task_id'] && $v['task_title']): ?>
                                            <div>
                                                <span class="font-bold text-white text-[11px] flex items-center gap-1">
                                                    <i class="fa-solid fa-clipboard-check text-blue-400"></i>
                                                    <span class="truncate max-w-[160px]"><?= htmlspecialchars($v['task_title']) ?></span>
                                                </span>
                                                <form method="POST" action="volunteers.php" class="inline">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                    <input type="hidden" name="action" value="release_task">
                                                    <input type="hidden" name="volunteer_id" value="<?= $v['id'] ?>">
                                                    <button type="submit" class="text-[10px] text-rose-400 hover:text-rose-300 font-semibold underline">Release</button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-slate-500 italic text-[11px]">Unassigned (Ready)</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Actions -->
                                    <td class="py-3.5 px-3 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <!-- 1-Click Task Assignment Modal Trigger -->
                                            <button type="button" onclick="openAssignTaskModal(<?= $v['id'] ?>, '<?= htmlspecialchars(addslashes($v['full_name'])) ?>')" class="p-1.5 rounded-lg bg-blue-600/20 hover:bg-blue-600/40 text-blue-300 border border-blue-500/30 text-xs transition-all" title="Assign Task">
                                                <i class="fa-solid fa-tasks"></i>
                                            </button>

                                            <!-- Direct Radio / Message Dispatch Trigger -->
                                            <button type="button" onclick="openDirectMessageModal(<?= $v['id'] ?>, '<?= htmlspecialchars(addslashes($v['full_name'])) ?>', '<?= htmlspecialchars(addslashes($v['team_name'])) ?>')" class="p-1.5 rounded-lg bg-indigo-600/20 hover:bg-indigo-600/40 text-indigo-300 border border-indigo-500/30 text-xs transition-all" title="Broadcast Directive">
                                                <i class="fa-solid fa-bullhorn"></i>
                                            </button>

                                            <!-- Edit Status Trigger -->
                                            <button type="button" onclick="openEditStatusModal(<?= htmlspecialchars(json_encode($v)) ?>)" class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 border border-[#243049] text-xs transition-all" title="Edit Status">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </section>

    </main>
</div>

<!-- ========================================================================= -->
<!-- 1. REGISTER NEW VOLUNTEER MODAL -->
<!-- ========================================================================= -->
<div id="registerVolunteerModal" class="fixed inset-0 bg-[#060a14]/85 backdrop-blur-md z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-[#0f172a] border border-[#243049] rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden">
        <div class="h-14 px-6 bg-[#0c1326] border-b border-[#243049] flex items-center justify-between">
            <h3 class="text-sm font-extrabold text-white flex items-center gap-2">
                <i class="fa-solid fa-user-plus text-emerald-400"></i> Register New Volunteer
            </h3>
            <button type="button" onclick="document.getElementById('registerVolunteerModal').classList.add('hidden')" class="text-slate-400 hover:text-white">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <form method="POST" action="volunteers.php" class="p-6 space-y-3.5 text-xs">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="create_volunteer">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Full Name *</label>
                    <input type="text" name="full_name" required placeholder="e.g. Ramesh Kumar" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Phone Number *</label>
                    <input type="text" name="phone" required placeholder="e.g. +91 98765 43210" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white font-mono">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Email Address</label>
                    <input type="email" name="email" placeholder="e.g. ramesh@ngo.org" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Blood Group</label>
                    <select name="blood_type" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white font-bold">
                        <option value="A+">A+</option>
                        <option value="A-">A-</option>
                        <option value="B+">B+</option>
                        <option value="B-">B-</option>
                        <option value="O+" selected>O+</option>
                        <option value="O-">O-</option>
                        <option value="AB+">AB+</option>
                        <option value="AB-">AB-</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Assigned Team *</label>
                    <select name="team_name" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white font-semibold">
                        <?php foreach ($STANDARD_TEAMS as $tName => $tMeta): ?>
                            <option value="<?= htmlspecialchars($tName) ?>"><?= htmlspecialchars($tName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Organization / NGO *</label>
                    <select name="organization" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white font-semibold">
                        <?php foreach ($STANDARD_ORGS as $org): ?>
                            <option value="<?= htmlspecialchars($org) ?>"><?= htmlspecialchars($org) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Current Location / Station *</label>
                    <input type="text" name="current_location" required value="Delhi Central Relief Base" placeholder="e.g. Mayur Vihar Camp" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Initial Status</label>
                    <select name="availability_status" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white font-semibold">
                        <option value="Available / Standby">Available / Standby</option>
                        <option value="Active on Task">Active on Task</option>
                        <option value="Off-Duty">Off-Duty</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Primary Skills & Certifications</label>
                <input type="text" name="skills" placeholder="e.g. First Aid, CPR, Boat Driving, Heavy Lifting" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white">
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('registerVolunteerModal').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 font-semibold">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold shadow-lg shadow-emerald-600/30">Register Operative</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 2. BROADCAST MESSAGE MODAL -->
<!-- ========================================================================= -->
<div id="broadcastModal" class="fixed inset-0 bg-[#060a14]/85 backdrop-blur-md z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-[#0f172a] border border-[#243049] rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden">
        <div class="h-14 px-6 bg-[#0c1326] border-b border-[#243049] flex items-center justify-between">
            <h3 class="text-sm font-extrabold text-white flex items-center gap-2">
                <i class="fa-solid fa-bullhorn text-indigo-400"></i> Broadcast Tactical Directive
            </h3>
            <button type="button" onclick="document.getElementById('broadcastModal').classList.add('hidden')" class="text-slate-400 hover:text-white">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <form method="POST" action="volunteers.php" class="p-6 space-y-3.5 text-xs">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="broadcast_message">

            <div>
                <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Broadcast Target Scope *</label>
                <select name="target_type" id="broadcastTargetType" onchange="toggleBroadcastTargetFields(this.value)" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white font-bold">
                    <option value="ALL">🌐 Broadcast to ALL Registered Volunteers</option>
                    <option value="TEAM">👥 Broadcast to a Specific Response Team</option>
                    <option value="VOLUNTEER">👤 Direct Radio Dispatch to a Specific Volunteer</option>
                </select>
            </div>

            <!-- Specific Team Selector -->
            <div id="teamTargetField" class="hidden">
                <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Select Target Team *</label>
                <select name="target_team" id="broadcastTeamSelect" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white font-semibold">
                    <?php foreach ($STANDARD_TEAMS as $tName => $tMeta): ?>
                        <option value="<?= htmlspecialchars($tName) ?>"><?= htmlspecialchars($tName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Specific Volunteer Selector -->
            <div id="volunteerTargetField" class="hidden">
                <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Select Target Volunteer *</label>
                <select name="target_volunteer_id" id="broadcastVolunteerSelect" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white font-semibold">
                    <?php foreach ($volunteersList as $vol): ?>
                        <option value="<?= $vol['id'] ?>">
                            [VOL-<?= str_pad($vol['id'], 3, '0', STR_PAD_LEFT) ?>] <?= htmlspecialchars($vol['full_name']) ?> (<?= htmlspecialchars($vol['team_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Priority Level *</label>
                    <select name="priority" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white font-bold">
                        <option value="Critical Emergency">🚨 Critical Emergency</option>
                        <option value="High Priority" selected>⚡ High Priority Directive</option>
                        <option value="General Advisory">ℹ️ General Advisory</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Directive Title *</label>
                    <input type="text" name="title" required value="Flash Flood Evacuation Mobilization" placeholder="e.g. Weather Alert" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white font-bold">
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Message Content *</label>
                <textarea name="message" rows="3" required placeholder="Type operational instructions, staging locations, assembly point coordinates..." class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white"></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('broadcastModal').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 font-semibold">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold shadow-lg shadow-indigo-600/30">Transmit Directive</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 3. ASSIGN MISSION TASK MODAL -->
<!-- ========================================================================= -->
<div id="assignTaskModal" class="fixed inset-0 bg-[#060a14]/85 backdrop-blur-md z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-[#0f172a] border border-[#243049] rounded-2xl w-full max-w-md shadow-2xl overflow-hidden">
        <div class="h-14 px-6 bg-[#0c1326] border-b border-[#243049] flex items-center justify-between">
            <h3 class="text-sm font-extrabold text-white flex items-center gap-2">
                <i class="fa-solid fa-list-check text-blue-400"></i> Assign Mission to Volunteer
            </h3>
            <button type="button" onclick="document.getElementById('assignTaskModal').classList.add('hidden')" class="text-slate-400 hover:text-white">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <form method="POST" action="volunteers.php" class="p-6 space-y-3.5 text-xs">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="assign_task">
            <input type="hidden" name="volunteer_id" id="assignModalVolId" value="">

            <div>
                <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1">Target Volunteer</label>
                <p class="font-extrabold text-white text-sm" id="assignModalVolName">Ramesh Kumar</p>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Select Open Mission / Task *</label>
                <select name="task_id" required class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white font-semibold">
                    <?php if (empty($openTasks)): ?>
                        <option value="" disabled selected>No open tasks available</option>
                    <?php else: ?>
                        <?php foreach ($openTasks as $t): ?>
                            <option value="<?= $t['id'] ?>">
                                [<?= htmlspecialchars($t['category']) ?>] <?= htmlspecialchars($t['title']) ?> (<?= htmlspecialchars($t['location']) ?>)
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('assignTaskModal').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 font-semibold">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold shadow-lg shadow-blue-600/30">Deploy to Task</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 4. EDIT STATUS MODAL -->
<!-- ========================================================================= -->
<div id="editStatusModal" class="fixed inset-0 bg-[#060a14]/85 backdrop-blur-md z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-[#0f172a] border border-[#243049] rounded-2xl w-full max-w-md shadow-2xl overflow-hidden">
        <div class="h-14 px-6 bg-[#0c1326] border-b border-[#243049] flex items-center justify-between">
            <h3 class="text-sm font-extrabold text-white flex items-center gap-2">
                <i class="fa-solid fa-pen-to-square text-emerald-400"></i> Update Volunteer Profile
            </h3>
            <button type="button" onclick="document.getElementById('editStatusModal').classList.add('hidden')" class="text-slate-400 hover:text-white">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <form method="POST" action="volunteers.php" class="p-6 space-y-3.5 text-xs">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="update_volunteer_status">
            <input type="hidden" name="volunteer_id" id="editModalVolId" value="">

            <div>
                <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1">Volunteer</label>
                <p class="font-extrabold text-white text-sm" id="editModalVolName">Ramesh Kumar</p>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Availability Status</label>
                <select name="availability_status" id="editModalStatus" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white font-semibold">
                    <option value="Available / Standby">Available / Standby</option>
                    <option value="Active on Task">Active on Task</option>
                    <option value="En-Route">En-Route</option>
                    <option value="Off-Duty">Off-Duty</option>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Team Assignment</label>
                <select name="team_name" id="editModalTeam" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white font-semibold">
                    <?php foreach ($STANDARD_TEAMS as $tName => $tMeta): ?>
                        <option value="<?= htmlspecialchars($tName) ?>"><?= htmlspecialchars($tName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Current Location / Station</label>
                <input type="text" name="current_location" id="editModalLocation" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white">
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('editStatusModal').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 font-semibold">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold shadow-lg shadow-emerald-600/30">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- JAVASCRIPT UI HANDLERS -->
<!-- ========================================================================= -->
<script>
function openBroadcastModal() {
    document.getElementById('broadcastTargetType').value = 'ALL';
    toggleBroadcastTargetFields('ALL');
    document.getElementById('broadcastModal').classList.remove('hidden');
}

function openDirectMessageModal(volId, volName, teamName) {
    document.getElementById('broadcastTargetType').value = 'VOLUNTEER';
    toggleBroadcastTargetFields('VOLUNTEER');
    document.getElementById('broadcastVolunteerSelect').value = volId;
    document.getElementById('broadcastModal').classList.remove('hidden');
}

function toggleBroadcastTargetFields(type) {
    const teamBox = document.getElementById('teamTargetField');
    const volBox = document.getElementById('volunteerTargetField');
    
    if (type === 'TEAM') {
        teamBox.classList.remove('hidden');
        volBox.classList.add('hidden');
    } else if (type === 'VOLUNTEER') {
        teamBox.classList.add('hidden');
        volBox.classList.remove('hidden');
    } else {
        teamBox.classList.add('hidden');
        volBox.classList.add('hidden');
    }
}

function openAssignTaskModal(volId, volName) {
    document.getElementById('assignModalVolId').value = volId;
    document.getElementById('assignModalVolName').innerText = volName;
    document.getElementById('assignTaskModal').classList.remove('hidden');
}

function openEditStatusModal(vol) {
    document.getElementById('editModalVolId').value = vol.id;
    document.getElementById('editModalVolName').innerText = `${vol.full_name} (VOL-${String(vol.id).padStart(3, '0')})`;
    document.getElementById('editModalStatus').value = vol.availability_status;
    document.getElementById('editModalTeam').value = vol.team_name;
    document.getElementById('editModalLocation').value = vol.current_location;
    document.getElementById('editStatusModal').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
