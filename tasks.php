<?php
// tasks.php - Disaster Field Missions & Task Enrollment Board (Government Theme)
define('PAGE_TITLE', 'Disaster Missions Board');
require_once __DIR__ . '/auth.php';

$currentUser = requireVolunteer($pdo);

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrf)) {
        setFlash('error', 'Security token invalid.');
        header("Location: tasks.php");
        exit;
    }

    // ENROLL IN TASK
    if ($action === 'enroll') {
        $taskId = (int) ($_POST['task_id'] ?? 0);
        $notes = trim($_POST['notes'] ?? 'Enrolled via missions board');

        $check = $pdo->prepare("SELECT id FROM task_assignments WHERE task_id = ? AND user_id = ?");
        $check->execute([$taskId, $currentUser['id']]);
        if ($check->fetch()) {
            setFlash('info', 'You are already enrolled in this disaster task.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO task_assignments (task_id, user_id, status, notes) VALUES (?, ?, 'En Route', ?)");
            $stmt->execute([$taskId, $currentUser['id'], $notes]);

            $pdo->prepare("UPDATE volunteer_tasks SET assigned_volunteers_count = assigned_volunteers_count + 1 WHERE id = ?")->execute([$taskId]);

            $tName = $pdo->query("SELECT title FROM volunteer_tasks WHERE id = {$taskId}")->fetchColumn();
            logActivity($pdo, 'VOLUNTEER_ENROLL_TASK', "Volunteer {$currentUser['name']} enrolled in '{$tName}'");
            setFlash('success', "Enrolled in '{$tName}'. Team leader notified.");
        }
        header("Location: tasks.php");
        exit;
    }
}

// Queries
$search = trim($_GET['search'] ?? '');
$filterCategory = trim($_GET['category'] ?? '');

$query = "
    SELECT vt.*,
           (SELECT status FROM task_assignments WHERE task_id = vt.id AND user_id = ?) as my_assignment_status
    FROM volunteer_tasks vt 
    WHERE 1=1
";
$params = [$currentUser['id']];

if (!empty($search)) {
    $query .= " AND (vt.title LIKE ? OR vt.location LIKE ? OR vt.description LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if (!empty($filterCategory)) {
    $query .= " AND vt.category = ?";
    $params[] = $filterCategory;
}

$query .= " ORDER BY vt.id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

$csrfToken = generateCsrfToken();

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div class="flex-1 flex flex-col min-w-0 bg-[#f8fafc] min-h-screen overflow-y-auto">
    <?php require_once __DIR__ . '/navbar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto w-full">
        
        <!-- HEADER BANNER -->
        <section class="bg-white p-5 sm:p-6 rounded-3xl border border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">Disaster Relief Missions Board</h2>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200 mono">
                        <?= count($tasks) ?> Tasks Available
                    </span>
                </div>
                <p class="text-xs text-slate-500 font-medium mt-1">Browse and join humanitarian missions across search &amp; rescue, first aid triage, and food distribution</p>
            </div>
            <a href="relief.php" class="px-4 py-2.5 rounded-2xl bg-[#1d63d8] hover:bg-[#1553c7] text-white text-xs font-bold transition-all shadow-sm flex items-center justify-center gap-2 cursor-pointer">
                <i class="fa-solid fa-boxes-packing text-xs"></i>
                <span>Relief Supply Ledger</span>
            </a>
        </section>

        <!-- Filter Bar -->
        <div class="bg-white p-4 rounded-3xl border border-slate-200 shadow-xs">
            <form method="GET" action="tasks.php" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
                <div class="sm:col-span-6 relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <i class="fa-solid fa-magnifying-glass text-xs"></i>
                    </div>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search missions, locations, details..." 
                           class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-[#1d63d8] focus:bg-white transition-colors font-medium">
                </div>

                <div class="sm:col-span-4">
                    <select name="category" class="w-full py-2 px-3 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 font-bold focus:outline-none focus:border-[#1d63d8]">
                        <option value="">All Mission Categories</option>
                        <option value="Search & Rescue" <?= $filterCategory === 'Search & Rescue' ? 'selected' : '' ?>>Search &amp; Rescue Support</option>
                        <option value="Medical Aid" <?= $filterCategory === 'Medical Aid' ? 'selected' : '' ?>>Medical Aid &amp; Triage</option>
                        <option value="Food & Water" <?= $filterCategory === 'Food & Water' ? 'selected' : '' ?>>Food &amp; Water Distribution</option>
                        <option value="Shelter Management" <?= $filterCategory === 'Shelter Management' ? 'selected' : '' ?>>Shelter Management</option>
                        <option value="Logistics" <?= $filterCategory === 'Logistics' ? 'selected' : '' ?>>Logistics &amp; Transport</option>
                    </select>
                </div>

                <div class="sm:col-span-2 flex items-center gap-2">
                    <button type="submit" class="w-full py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold rounded-xl border border-slate-200 transition-colors flex items-center justify-center gap-1.5 cursor-pointer">
                        <i class="fa-solid fa-filter text-[10px]"></i>
                        <span>Filter</span>
                    </button>
                    <?php if (!empty($search) || !empty($filterCategory)): ?>
                        <a href="tasks.php" class="p-2 bg-slate-100 hover:bg-red-50 text-slate-600 hover:text-red-600 rounded-xl border border-slate-200 transition-colors" title="Clear Filters">
                            <i class="fa-solid fa-rotate-left text-xs"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Missions Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <?php foreach ($tasks as $t): ?>
                <?php
                    $catBadge = match($t['category']) {
                        'Search & Rescue' => 'bg-rose-50 text-rose-800 border-rose-200',
                        'Medical Aid' => 'bg-red-50 text-red-800 border-red-200',
                        'Food & Water' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                        'Shelter Management' => 'bg-indigo-50 text-indigo-800 border-indigo-200',
                        default => 'bg-slate-100 text-slate-800 border-slate-200'
                    };
                    $isJoined = !empty($t['my_assignment_status']);
                ?>
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs flex flex-col justify-between space-y-4 hover:border-slate-300 hover:shadow-sm transition-all">
                    <div>
                        <div class="flex items-start justify-between gap-2 mb-3">
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold border <?= $catBadge ?> mono">
                                <?= htmlspecialchars($t['category']) ?>
                            </span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold bg-slate-100 border border-slate-200 text-slate-700">
                                <i class="fa-solid fa-users text-[10px] text-slate-400 mr-1"></i><?= $t['assigned_volunteers_count'] ?> / <?= $t['required_volunteers'] ?> Crew
                            </span>
                        </div>

                        <h3 class="text-base font-extrabold text-slate-900 leading-snug"><?= htmlspecialchars($t['title']) ?></h3>
                        <p class="text-xs text-blue-700 font-bold mt-1 flex items-center gap-1.5">
                            <i class="fa-solid fa-location-dot text-red-500"></i>
                            <span><?= htmlspecialchars($t['location']) ?></span>
                        </p>

                        <div class="mt-3 p-3.5 rounded-2xl bg-slate-50 border border-slate-200 text-xs text-slate-600 leading-relaxed font-medium">
                            <?= nl2br(htmlspecialchars($t['description'])) ?>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                        <span class="text-[10px] text-slate-400 font-mono"><?= date('M d, Y', strtotime($t['created_at'])) ?></span>
                        
                        <?php if ($isJoined): ?>
                            <span class="px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-bold flex items-center gap-1 mono">
                                <i class="fa-solid fa-circle-check text-emerald-600"></i> Enrolled (<?= htmlspecialchars($t['my_assignment_status']) ?>)
                            </span>
                        <?php else: ?>
                            <form method="POST" action="tasks.php" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="action" value="enroll">
                                <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                                <button type="submit" class="px-4 py-2 rounded-xl bg-[#16a34a] hover:bg-[#15803d] text-white text-xs font-bold transition-all shadow-sm flex items-center gap-1.5 cursor-pointer">
                                    <i class="fa-solid fa-hand-holding-heart text-xs"></i>
                                    <span>Accept Mission</span>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </main>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
