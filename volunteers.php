<?php
// volunteers.php - DisasterSafe Universal Volunteer Management & Deployment Hub (Government Theme)
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
            $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($fullName) . '&background=1d63d8&color=fff';
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
        $targetType = trim($_POST['target_type'] ?? 'ALL');
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

// Open Volunteer Tasks
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

<div class="flex-1 flex flex-col min-w-0 bg-[#f8fafc] min-h-screen overflow-y-auto">
    <?php require_once __DIR__ . '/navbar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto w-full">
        
        <!-- HEADER BANNER & TACTICAL TOOLBAR -->
        <section class="bg-white p-5 sm:p-6 rounded-3xl border border-slate-200 relative overflow-hidden shadow-sm">
            <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-emerald-100/60 rounded-full blur-3xl pointer-events-none"></div>

            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 relative z-10">
                <div class="space-y-1.5">
                    <div class="flex items-center gap-2.5">
                        <span class="w-9 h-9 rounded-2xl bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-700 font-black text-base shadow-2xs">
                            <i class="fa-solid fa-hand-holding-heart"></i>
                        </span>
                        <div>
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-700 mono">NGO &amp; Volunteer Response Corps</span>
                            <h2 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">Volunteer Management &amp; Deployment Hub</h2>
                        </div>
                    </div>
                    <p class="text-xs text-slate-600 max-w-2xl leading-relaxed font-medium">
                        Full tactical overview of all registered volunteers, specialized team rosters (Medical, Fire, Food &amp; Water, Search &amp; Rescue), on-ground status tracking, 1-click mission task assignment, and targeted radio broadcast transmissions.
                    </p>
                </div>

                <!-- Tactical Header Actions -->
                <div class="flex flex-wrap items-center gap-3 shrink-0">
                    <button type="button" onclick="openBroadcastModal()" class="px-4 py-2.5 rounded-2xl bg-[#1d63d8] hover:bg-[#1553c7] text-white font-bold text-xs shadow-sm transition-all flex items-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-bullhorn"></i> Broadcast Directive
                    </button>
                    <button type="button" onclick="document.getElementById('registerVolunteerModal').classList.remove('hidden')" class="px-4 py-2.5 rounded-2xl bg-[#16a34a] hover:bg-[#15803d] text-white font-bold text-xs shadow-sm transition-all flex items-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-user-plus"></i> Register Volunteer
                    </button>
                    <a href="tasks.php" class="px-4 py-2.5 rounded-2xl bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-800 font-bold text-xs transition-all flex items-center gap-2 shadow-2xs">
                        <i class="fa-solid fa-list-check text-emerald-600"></i> Missions Board
                    </a>
                </div>
            </div>
        </section>

        <!-- KPI METRICS STRIP -->
        <section class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="bg-white border border-slate-200 p-3.5 rounded-2xl shadow-2xs flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mono">Total Volunteers</p>
                    <h3 class="text-2xl font-extrabold text-slate-900 mt-0.5"><?= $totalEnrolled ?></h3>
                    <span class="text-[10px] font-bold text-emerald-700 mono">Across 6 Specialized Teams</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-600 text-sm">
                    <i class="fa-solid fa-users"></i>
                </div>
            </div>

            <div class="bg-white border border-slate-200 p-3.5 rounded-2xl shadow-2xs flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mono">Active on Missions</p>
                    <h3 class="text-2xl font-extrabold text-blue-700 mt-0.5"><?= $activeOnTask ?></h3>
                    <span class="text-[10px] font-semibold text-blue-700 mono">Deployed at Field Sites</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-blue-50 border border-blue-200 flex items-center justify-center text-blue-600 text-sm">
                    <i class="fa-solid fa-person-walking-arrow-right"></i>
                </div>
            </div>

            <div class="bg-white border border-slate-200 p-3.5 rounded-2xl shadow-2xs flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mono">Available / Standby</p>
                    <h3 class="text-2xl font-extrabold text-amber-700 mt-0.5"><?= $standbyReady ?></h3>
                    <span class="text-[10px] font-semibold text-amber-700 mono">Ready for Immediate Dispatch</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-600 text-sm">
                    <i class="fa-solid fa-user-check"></i>
                </div>
            </div>

            <div class="bg-white border border-slate-200 p-3.5 rounded-2xl shadow-2xs flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mono">Broadcast Directives</p>
                    <h3 class="text-2xl font-extrabold text-indigo-700 mt-0.5"><?= $totalBroadcasts ?></h3>
                    <span class="text-[10px] font-semibold text-slate-500 mono">Live Team Communications</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-indigo-50 border border-indigo-200 flex items-center justify-center text-indigo-600 text-sm">
                    <i class="fa-solid fa-tower-broadcast"></i>
                </div>
            </div>
        </section>

        <!-- SPECIALIZED RESPONSE TEAMS MATRIX CARDS -->
        <section class="space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-2 mono">
                    <i class="fa-solid fa-layer-group text-emerald-600"></i> Specialized Response Squads &amp; Teams
                </h3>
                <span class="text-[11px] text-slate-500 font-medium">Click any squad to filter roster</span>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <?php foreach ($STANDARD_TEAMS as $teamTitle => $tMeta): ?>
                    <?php 
                        $count = $teamCounts[$teamTitle] ?? 0;
                        $isSelected = ($filterTeam === $teamTitle);
                        $borderAccent = match($tMeta['color']) {
                            'rose' => 'border-rose-200 bg-rose-50/50 hover:bg-rose-50',
                            'red' => 'border-red-200 bg-red-50/50 hover:bg-red-50',
                            'amber' => 'border-amber-200 bg-amber-50/50 hover:bg-amber-50',
                            'blue' => 'border-blue-200 bg-blue-50/50 hover:bg-blue-50',
                            'emerald' => 'border-emerald-200 bg-emerald-50/50 hover:bg-emerald-50',
                            default => 'border-indigo-200 bg-indigo-50/50 hover:bg-indigo-50'
                        };
                    ?>
                    <a href="volunteers.php?team=<?= urlencode($teamTitle) ?>" class="p-3.5 rounded-2xl border <?= $borderAccent ?> <?= $isSelected ? 'ring-2 ring-emerald-500 bg-white shadow-xs' : '' ?> transition-all flex flex-col justify-between cursor-pointer group shadow-2xs">
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <span class="w-8 h-8 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-xs text-slate-800 shadow-2xs">
                                    <i class="fa-solid <?= $tMeta['icon'] ?>"></i>
                                </span>
                                <span class="px-2 py-0.5 rounded-full bg-white border border-slate-200 text-[11px] font-extrabold text-slate-800 mono">
                                    <?= $count ?>
                                </span>
                            </div>
                            <h4 class="text-xs font-extrabold text-slate-900 group-hover:text-emerald-700 transition-colors leading-tight line-clamp-1">
                                <?= htmlspecialchars($teamTitle) ?>
                            </h4>
                            <p class="text-[10px] text-slate-500 line-clamp-1 font-medium"><?= $tMeta['desc'] ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- LIVE BROADCAST ANNOUNCEMENTS TICKER -->
        <?php if (!empty($recentBroadcasts)): ?>
            <section class="bg-white p-4 rounded-3xl border border-slate-200 shadow-xs space-y-2.5">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-extrabold text-blue-800 uppercase tracking-wider flex items-center gap-2 mono">
                        <i class="fa-solid fa-bullhorn text-[#1d63d8] animate-pulse"></i> Live Broadcast Dispatch Feed
                    </span>
                    <button type="button" onclick="openBroadcastModal()" class="text-[10px] font-bold text-[#1d63d8] hover:underline transition-colors cursor-pointer">
                        + New Broadcast Directive
                    </button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2.5 text-xs">
                    <?php foreach ($recentBroadcasts as $bc): ?>
                        <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200 flex flex-col justify-between space-y-1.5">
                            <div class="flex items-center justify-between">
                                <span class="px-2 py-0.5 rounded-full text-[9px] font-extrabold <?= $bc['priority'] === 'Critical Emergency' ? 'bg-red-100 text-red-800 border border-red-200' : 'bg-blue-100 text-blue-800 border border-blue-200' ?> mono">
                                    <?= htmlspecialchars($bc['priority']) ?>
                                </span>
                                <span class="text-[10px] font-mono text-slate-500">
                                    To: <b><?= htmlspecialchars($bc['target_type'] === 'ALL' ? 'ALL SQUADS' : ($bc['target_team'] ?: 'Direct')) ?></b>
                                </span>
                            </div>
                            <p class="font-extrabold text-slate-900 text-[11px] truncate"><?= htmlspecialchars($bc['title']) ?></p>
                            <p class="text-[10px] text-slate-600 line-clamp-2 font-medium"><?= htmlspecialchars($bc['message']) ?></p>
                            <div class="flex items-center justify-between pt-1 border-t border-slate-200 text-[9px] text-slate-500">
                                <span>By: <?= htmlspecialchars($bc['sender_name']) ?></span>
                                <span><?= date('H:i, M j', strtotime($bc['created_at'])) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- VOLUNTEERS ROSTER DIRECTORY & CONTROLS -->
        <section class="bg-white rounded-3xl border border-slate-200 shadow-xs overflow-hidden space-y-4 p-4 sm:p-6">
            
            <!-- Filter & Search Toolbar -->
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 pb-4 border-b border-slate-100">
                <div>
                    <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-address-book text-emerald-600"></i> Volunteers &amp; Field Personnel Roster
                    </h3>
                    <p class="text-[11px] text-slate-500 font-medium">Showing <?= count($volunteersList) ?> verified field operatives</p>
                </div>

                <!-- Filters -->
                <form method="GET" action="volunteers.php" class="flex flex-wrap items-center gap-2 text-xs">
                    <!-- Search Input -->
                    <div class="relative min-w-[200px]">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, phone, location..." class="w-full pl-8 pr-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 placeholder-slate-400 focus:outline-none focus:border-emerald-600 focus:bg-white font-medium">
                    </div>

                    <!-- Team Filter -->
                    <select name="team" onchange="this.form.submit()" class="px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 font-bold focus:outline-none">
                        <option value="">All Teams</option>
                        <?php foreach ($STANDARD_TEAMS as $tName => $tMeta): ?>
                            <option value="<?= htmlspecialchars($tName) ?>" <?= ($filterTeam === $tName) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Status Filter -->
                    <select name="status" onchange="this.form.submit()" class="px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 font-bold focus:outline-none">
                        <option value="">All Statuses</option>
                        <option value="Available / Standby" <?= ($filterStatus === 'Available / Standby') ? 'selected' : '' ?>>Available / Standby</option>
                        <option value="Active on Task" <?= ($filterStatus === 'Active on Task') ? 'selected' : '' ?>>Active on Task</option>
                        <option value="En-Route" <?= ($filterStatus === 'En-Route') ? 'selected' : '' ?>>En-Route</option>
                        <option value="Off-Duty" <?= ($filterStatus === 'Off-Duty') ? 'selected' : '' ?>>Off-Duty</option>
                    </select>

                    <!-- Reset Filters Button -->
                    <?php if (!empty($search) || !empty($filterTeam) || !empty($filterStatus) || !empty($filterOrg)): ?>
                        <a href="volunteers.php" class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-all">
                            Reset
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Volunteers Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-500 uppercase text-[10px] tracking-wider font-extrabold bg-slate-50/80 mono">
                            <th class="py-3 px-3">Operative ID &amp; Name</th>
                            <th class="py-3 px-3">Team Assignment</th>
                            <th class="py-3 px-3">Organization / NGO</th>
                            <th class="py-3 px-3">Current Location</th>
                            <th class="py-3 px-3">Availability Status</th>
                            <th class="py-3 px-3">Active Mission</th>
                            <th class="py-3 px-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($volunteersList)): ?>
                            <tr>
                                <td colspan="7" class="py-8 text-center text-slate-500">
                                    <i class="fa-solid fa-users-slash text-2xl text-slate-400 mb-2 block"></i>
                                    No volunteers found matching the current search / filter criteria.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($volunteersList as $v): ?>
                                <?php 
                                    $statusClass = match($v['availability_status']) {
                                        'Active on Task' => 'bg-blue-50 text-blue-800 border-blue-200',
                                        'Available / Standby' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                        'En-Route' => 'bg-amber-50 text-amber-800 border-amber-200',
                                        default => 'bg-slate-100 text-slate-700 border-slate-200'
                                    };
                                    $teamIcon = match($v['team_name']) {
                                        'Medical & First Aid Team' => 'fa-kit-medical text-rose-600',
                                        'Fire & Hazmat Support Team' => 'fa-fire-extinguisher text-red-600',
                                        'Water & Food Relief Team' => 'fa-bowl-rice text-amber-600',
                                        'Search & Rescue (SAR) Team' => 'fa-person-falling-burst text-blue-600',
                                        'Shelter Management Team' => 'fa-campground text-emerald-600',
                                        default => 'fa-boxes-packing text-indigo-600'
                                    };
                                ?>
                                <tr class="hover:bg-slate-50 transition-colors group">
                                    <!-- ID & Name -->
                                    <td class="py-3.5 px-3">
                                            <div class="w-8 h-8 rounded-full bg-emerald-700 text-white font-bold text-xs flex items-center justify-center shrink-0 border border-emerald-800 shadow-2xs">
                                                <?= strtoupper(substr($v['full_name'], 0, 2)) ?>
                                            </div>
                                            <div>
                                                <div class="flex items-center gap-1.5">
                                                    <span class="font-mono text-[9px] font-bold text-slate-500">VOL-<?= str_pad($v['id'], 3, '0', STR_PAD_LEFT) ?></span>
                                                    <span class="font-extrabold text-slate-900 text-xs"><?= htmlspecialchars($v['full_name']) ?></span>
                                                    <?php if ($v['blood_type'] && $v['blood_type'] !== 'Unknown'): ?>
                                                        <span class="px-1.5 py-0.2 rounded-full bg-red-50 text-red-700 border border-red-200 text-[9px] font-bold mono"><?= htmlspecialchars($v['blood_type']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="text-[10px] text-slate-500 font-mono mt-0.5">
                                                    <i class="fa-solid fa-phone text-[9px] text-slate-400"></i> <?= htmlspecialchars($v['phone']) ?>
                                                </p>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Team Assignment -->
                                    <td class="py-3.5 px-3">
                                        <span class="flex items-center gap-1.5 font-bold text-slate-800">
                                            <i class="fa-solid <?= $teamIcon ?> text-xs"></i>
                                            <span><?= htmlspecialchars($v['team_name'] ?: 'Unassigned') ?></span>
                                        </span>
                                        <p class="text-[10px] text-slate-500 truncate max-w-[160px] font-medium"><?= htmlspecialchars($v['skills'] ?: 'General Logistics') ?></p>
                                    </td>

                                    <!-- Organization / NGO -->
                                    <td class="py-3.5 px-3">
                                        <span class="px-2 py-0.5 rounded-lg bg-blue-50 border border-blue-200 text-[11px] font-bold text-blue-800">
                                            <?= htmlspecialchars($v['organization'] ?: 'DisasterSafe Relief Corps') ?>
                                        </span>
                                    </td>

                                    <!-- Current Location -->
                                    <td class="py-3.5 px-3">
                                        <span class="text-slate-700 flex items-center gap-1 text-[11px] font-medium">
                                            <i class="fa-solid fa-location-dot text-red-500 text-[10px]"></i>
                                            <span class="truncate max-w-[150px]"><?= htmlspecialchars($v['current_location']) ?></span>
                                        </span>
                                    </td>

                                    <!-- Availability Status -->
                                    <td class="py-3.5 px-3">
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border <?= $statusClass ?> mono">
                                            <?= htmlspecialchars($v['availability_status']) ?>
                                        </span>
                                    </td>

                                    <!-- Active Task -->
                                    <td class="py-3.5 px-3">
                                        <?php if ($v['assigned_task_id'] && $v['task_title']): ?>
                                            <div>
                                                <span class="font-bold text-slate-900 text-[11px] flex items-center gap-1">
                                                    <i class="fa-solid fa-clipboard-check text-blue-600"></i>
                                                    <span class="truncate max-w-[160px]"><?= htmlspecialchars($v['task_title']) ?></span>
                                                </span>
                                                <form method="POST" action="volunteers.php" class="inline">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                    <input type="hidden" name="action" value="release_task">
                                                    <input type="hidden" name="volunteer_id" value="<?= $v['id'] ?>">
                                                    <button type="submit" class="text-[10px] text-red-600 hover:text-red-700 font-bold underline cursor-pointer">Release</button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-slate-400 italic text-[11px]">Unassigned (Ready)</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Actions -->
                                    <td class="py-3.5 px-3 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <!-- 1-Click Task Assignment -->
                                            <button type="button" onclick="openAssignTaskModal(<?= $v['id'] ?>, '<?= htmlspecialchars(addslashes($v['full_name'])) ?>')" class="p-1.5 rounded-xl bg-blue-50 hover:bg-blue-100 text-[#1d63d8] border border-blue-200 text-xs transition-all cursor-pointer" title="Assign Task">
                                                <i class="fa-solid fa-tasks"></i>
                                            </button>

                                            <!-- Direct Radio / Message Dispatch -->
                                            <button type="button" onclick="openDirectMessageModal(<?= $v['id'] ?>, '<?= htmlspecialchars(addslashes($v['full_name'])) ?>', '<?= htmlspecialchars(addslashes($v['team_name'])) ?>')" class="p-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 text-xs transition-all cursor-pointer" title="Broadcast Directive">
                                                <i class="fa-solid fa-bullhorn"></i>
                                            </button>

                                            <!-- Edit Status -->
                                            <button type="button" onclick="openEditStatusModal(<?= htmlspecialchars(json_encode($v)) ?>)" class="p-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 text-xs transition-all cursor-pointer" title="Edit Status">
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

<!-- REGISTER NEW VOLUNTEER MODAL -->
<div id="registerVolunteerModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden">
        <div class="h-14 px-6 bg-white border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-user-plus text-emerald-600"></i> Register New Volunteer
            </h3>
            <button type="button" onclick="document.getElementById('registerVolunteerModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-800 cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <form method="POST" action="volunteers.php" class="p-6 space-y-3.5 text-xs">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="create_volunteer">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1 mono">Full Name *</label>
                    <input type="text" name="full_name" required placeholder="e.g. Ramesh Kumar" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:bg-white focus:outline-none focus:border-emerald-600 font-medium">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1 mono">Phone Number *</label>
                    <input type="text" name="phone" required placeholder="e.g. +91 98765 43210" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-mono focus:bg-white focus:outline-none focus:border-emerald-600 font-medium">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1 mono">Email Address</label>
                    <input type="email" name="email" placeholder="e.g. ramesh@ngo.org" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:bg-white focus:outline-none focus:border-emerald-600 font-medium">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1 mono">Blood Group</label>
                    <select name="blood_type" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-bold focus:bg-white focus:outline-none focus:border-emerald-600">
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
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1 mono">Assigned Team *</label>
                    <select name="team_name" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-bold focus:bg-white focus:outline-none focus:border-emerald-600">
                        <?php foreach ($STANDARD_TEAMS as $tName => $tMeta): ?>
                            <option value="<?= htmlspecialchars($tName) ?>"><?= htmlspecialchars($tName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1 mono">Organization / NGO *</label>
                    <select name="organization" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-bold focus:bg-white focus:outline-none focus:border-emerald-600">
                        <?php foreach ($STANDARD_ORGS as $org): ?>
                            <option value="<?= htmlspecialchars($org) ?>"><?= htmlspecialchars($org) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1 mono">Current Location / Station *</label>
                    <input type="text" name="current_location" required value="Delhi Central Relief Base" placeholder="e.g. Mayur Vihar Camp" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:bg-white focus:outline-none focus:border-emerald-600 font-medium">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1 mono">Initial Status</label>
                    <select name="availability_status" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-bold focus:bg-white focus:outline-none focus:border-emerald-600">
                        <option value="Available / Standby">Available / Standby</option>
                        <option value="Active on Task">Active on Task</option>
                        <option value="Off-Duty">Off-Duty</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1 mono">Primary Skills &amp; Certifications</label>
                <input type="text" name="skills" placeholder="e.g. First Aid, CPR, Boat Driving, Heavy Lifting" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:bg-white focus:outline-none focus:border-emerald-600 font-medium">
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('registerVolunteerModal').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-700 font-bold cursor-pointer">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-[#16a34a] hover:bg-[#15803d] text-white font-bold shadow-sm cursor-pointer">Register Operative</button>
            </div>
        </form>
    </div>
</div>

<!-- BROADCAST MESSAGE MODAL -->
<div id="broadcastModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden">
        <div class="h-14 px-6 bg-white border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-bullhorn text-[#1d63d8]"></i> Broadcast Tactical Directive
            </h3>
            <button type="button" onclick="document.getElementById('broadcastModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-800 cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <form method="POST" action="volunteers.php" class="p-6 space-y-3.5 text-xs">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="broadcast_message">

            <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1 mono">Broadcast Target Scope *</label>
                <select name="target_type" id="broadcastTargetType" onchange="toggleBroadcastTargetFields(this.value)" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-bold focus:bg-white focus:outline-none focus:border-[#1d63d8]">
                    <option value="ALL">Broadcast to ALL Registered Volunteers</option>
                    <option value="TEAM">Broadcast to a Specific Response Team</option>
                    <option value="VOLUNTEER">Direct Radio Dispatch to a Specific Volunteer</option>
                </select>
            </div>

            <!-- Specific Team Selector -->
            <div id="teamTargetField" class="hidden">
                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1 mono">Select Target Team *</label>
                <select name="target_team" id="broadcastTeamSelect" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-bold focus:bg-white focus:outline-none focus:border-[#1d63d8]">
                    <?php foreach ($STANDARD_TEAMS as $tName => $tMeta): ?>
                        <option value="<?= htmlspecialchars($tName) ?>"><?= htmlspecialchars($tName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Specific Volunteer Selector -->
            <div id="volunteerTargetField" class="hidden">
                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1 mono">Select Target Volunteer *</label>
                <select name="target_volunteer_id" id="broadcastVolunteerSelect" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-bold focus:bg-white focus:outline-none focus:border-[#1d63d8]">
                    <?php foreach ($volunteersList as $vol): ?>
                        <option value="<?= $vol['id'] ?>">
                            [VOL-<?= str_pad($vol['id'], 3, '0', STR_PAD_LEFT) ?>] <?= htmlspecialchars($vol['full_name']) ?> (<?= htmlspecialchars($vol['team_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1 mono">Priority Level *</label>
                    <select name="priority" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-bold focus:bg-white focus:outline-none focus:border-[#1d63d8]">
                        <option value="Critical Emergency">Critical Emergency</option>
                        <option value="High Priority" selected>High Priority Directive</option>
                        <option value="General Advisory">General Advisory</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1 mono">Directive Title *</label>
                    <input type="text" name="title" required value="Flash Flood Evacuation Mobilization" placeholder="e.g. Weather Alert" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-bold focus:bg-white focus:outline-none focus:border-[#1d63d8]">
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1 mono">Message Content *</label>
                <textarea name="message" rows="3" required placeholder="Type operational instructions, staging locations, assembly point coordinates..." class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 leading-relaxed focus:bg-white focus:outline-none focus:border-[#1d63d8] font-medium"></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('broadcastModal').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-700 font-bold cursor-pointer">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-[#1d63d8] hover:bg-[#1553c7] text-white font-bold shadow-sm cursor-pointer">Transmit Directive</button>
            </div>
        </form>
    </div>
</div>

<!-- ASSIGN MISSION TASK MODAL -->
<div id="assignTaskModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-md shadow-2xl overflow-hidden">
        <div class="h-14 px-6 bg-white border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-list-check text-[#1d63d8]"></i> Assign Mission to Volunteer
            </h3>
            <button type="button" onclick="document.getElementById('assignTaskModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-800 cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <form method="POST" action="volunteers.php" class="p-6 space-y-3.5 text-xs">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="assign_task">
            <input type="hidden" name="volunteer_id" id="assignModalVolId" value="">

            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1 mono">Target Volunteer</label>
                <p class="font-extrabold text-slate-900 text-sm" id="assignModalVolName">Ramesh Kumar</p>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1 mono">Select Open Mission / Task *</label>
                <select name="task_id" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-bold focus:bg-white focus:outline-none focus:border-[#1d63d8]">
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
                <button type="button" onclick="document.getElementById('assignTaskModal').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-700 font-bold cursor-pointer">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-[#1d63d8] hover:bg-[#1553c7] text-white font-bold shadow-sm cursor-pointer">Deploy to Task</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT STATUS MODAL -->
<div id="editStatusModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-md shadow-2xl overflow-hidden">
        <div class="h-14 px-6 bg-white border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-pen-to-square text-emerald-600"></i> Update Volunteer Profile
            </h3>
            <button type="button" onclick="document.getElementById('editStatusModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-800 cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <form method="POST" action="volunteers.php" class="p-6 space-y-3.5 text-xs">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="update_volunteer_status">
            <input type="hidden" name="volunteer_id" id="editModalVolId" value="">

            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1 mono">Volunteer</label>
                <p class="font-extrabold text-slate-900 text-sm" id="editModalVolName">Ramesh Kumar</p>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1 mono">Availability Status</label>
                <select name="availability_status" id="editModalStatus" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-bold focus:bg-white focus:outline-none focus:border-[#1d63d8]">
                    <option value="Available / Standby">Available / Standby</option>
                    <option value="Active on Task">Active on Task</option>
                    <option value="En-Route">En-Route</option>
                    <option value="Off-Duty">Off-Duty</option>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1 mono">Team Assignment</label>
                <select name="team_name" id="editModalTeam" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-bold focus:bg-white focus:outline-none focus:border-[#1d63d8]">
                    <?php foreach ($STANDARD_TEAMS as $tName => $tMeta): ?>
                        <option value="<?= htmlspecialchars($tName) ?>"><?= htmlspecialchars($tName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1 mono">Current Location / Station</label>
                <input type="text" name="current_location" id="editModalLocation" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-medium focus:bg-white focus:outline-none focus:border-[#1d63d8]">
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('editStatusModal').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-700 font-bold cursor-pointer">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-[#16a34a] hover:bg-[#15803d] text-white font-bold shadow-sm cursor-pointer">Save Changes</button>
            </div>
        </form>
    </div>
</div>

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
