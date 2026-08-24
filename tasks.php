<?php
// tasks.php - Disaster Field Missions & Task Enrollment Board
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

<div class="flex-1 flex flex-col min-w-0 bg-slate-950">
    <?php require_once __DIR__ . '/navbar.php'; ?>

    <main class="flex-1 p-4 sm:p-8 max-w-7xl w-full mx-auto space-y-6">
        
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-extrabold text-white flex items-center gap-3">
                    <span>Disaster Relief Missions Board</span>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        <?= count($tasks) ?> Tasks Available
                    </span>
                </h2>
                <p class="text-xs text-slate-400 mt-1">Browse and join humanitarian missions across search & rescue, first aid triage, and food distribution</p>
            </div>
            <a href="relief.php" class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition-all shadow-lg shadow-emerald-600/30 flex items-center justify-center gap-2">
                <i class="fa-solid fa-boxes-packing text-xs"></i>
                <span>Relief Supply Ledger</span>
            </a>
        </div>

        <!-- Filter Bar -->
        <div class="glass-panel p-4 rounded-2xl">
            <form method="GET" action="tasks.php" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
                <div class="sm:col-span-6 relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                        <i class="fa-solid fa-magnifying-glass text-xs"></i>
                    </div>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search missions, locations, details..." 
                           class="w-full pl-9 pr-4 py-2 bg-slate-900/90 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 transition-colors">
                </div>

                <div class="sm:col-span-4">
                    <select name="category" class="w-full py-2 px-3 bg-slate-900/90 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-emerald-500">
                        <option value="">All Mission Categories</option>
                        <option value="Search & Rescue" <?= $filterCategory === 'Search & Rescue' ? 'selected' : '' ?>>Search & Rescue Support</option>
                        <option value="Medical Aid" <?= $filterCategory === 'Medical Aid' ? 'selected' : '' ?>>Medical Aid & Triage</option>
                        <option value="Food & Water" <?= $filterCategory === 'Food & Water' ? 'selected' : '' ?>>Food & Water Distribution</option>
                        <option value="Shelter Management" <?= $filterCategory === 'Shelter Management' ? 'selected' : '' ?>>Shelter Management</option>
                        <option value="Logistics" <?= $filterCategory === 'Logistics' ? 'selected' : '' ?>>Logistics & Transport</option>
                    </select>
                </div>

                <div class="sm:col-span-2 flex items-center gap-2">
                    <button type="submit" class="w-full py-2 px-3 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-xl border border-slate-700 transition-colors flex items-center justify-center gap-1.5">
                        <i class="fa-solid fa-filter text-[10px]"></i>
                        <span>Filter</span>
                    </button>
                    <?php if (!empty($search) || !empty($filterCategory)): ?>
                        <a href="tasks.php" class="p-2 bg-slate-800 hover:bg-rose-500/20 text-slate-400 hover:text-rose-400 rounded-xl border border-slate-700 transition-colors" title="Clear Filters">
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
                        'Search & Rescue' => 'bg-rose-500/10 text-rose-400 border-rose-500/30',
                        'Medical Aid' => 'bg-red-500/10 text-red-300 border-red-500/30',
                        'Food & Water' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
                        'Shelter Management' => 'bg-indigo-500/10 text-indigo-400 border-indigo-500/30',
                        default => 'bg-slate-800 text-slate-300 border-slate-700'
                    };
                    $isJoined = !empty($t['my_assignment_status']);
                ?>
                <div class="glass-panel p-6 rounded-3xl border border-slate-800/80 flex flex-col justify-between space-y-4 hover:border-emerald-500/40 transition-all">
                    <div>
                        <div class="flex items-start justify-between gap-2 mb-3">
                            <span class="px-2.5 py-1 rounded-lg text-xs font-bold border <?= $catBadge ?>">
                                <?= htmlspecialchars($t['category']) ?>
                            </span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-slate-900 border border-slate-800 text-slate-300">
                                <i class="fa-solid fa-users text-[10px] text-slate-500 mr-1"></i><?= $t['assigned_volunteers_count'] ?> / <?= $t['required_volunteers'] ?> Crew
                            </span>
                        </div>

                        <h3 class="text-base font-bold text-white leading-snug"><?= htmlspecialchars($t['title']) ?></h3>
                        <p class="text-xs text-emerald-300 font-semibold mt-1 flex items-center gap-1.5">
                            <i class="fa-solid fa-location-dot text-rose-400"></i>
                            <span><?= htmlspecialchars($t['location']) ?></span>
                        </p>

                        <div class="mt-3 p-3 rounded-2xl bg-slate-950/70 border border-slate-800/80 text-xs text-slate-300 leading-relaxed">
                            <?= nl2br(htmlspecialchars($t['description'])) ?>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-800 flex items-center justify-between">
                        <span class="text-[10px] text-slate-500"><?= date('M d, Y', strtotime($t['created_at'])) ?></span>
                        
                        <?php if ($isJoined): ?>
                            <span class="px-3 py-1.5 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-xs font-bold flex items-center gap-1">
                                <i class="fa-solid fa-circle-check"></i> Enrolled (<?= htmlspecialchars($t['my_assignment_status']) ?>)
                            </span>
                        <?php else: ?>
                            <form method="POST" action="tasks.php" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="action" value="enroll">
                                <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                                <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition-all shadow-md shadow-emerald-600/30 flex items-center gap-1.5">
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
