<?php
// activity_logs.php - Audit Trail & System Activity Logging
define('PAGE_TITLE', 'System Audit & Activity Logs');
require_once __DIR__ . '/auth.php';

$currentUser = requirePermissionGuard($pdo, 'view_activity_logs');

// Handle Clear Logs action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_logs') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $pdo->exec("DELETE FROM activity_logs");
        logActivity($pdo, 'CLEAR_LOGS', 'Superadmin purged system activity audit logs');
        setFlash('success', 'Activity logs cleared successfully.');
    } else {
        setFlash('error', 'Security token invalid.');
    }
    header("Location: activity_logs.php");
    exit;
}

// Search and filter parameters
$search = trim($_GET['search'] ?? '');
$filterAction = trim($_GET['action_filter'] ?? '');

$query = "SELECT * FROM activity_logs WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (user_name LIKE ? OR details LIKE ? OR ip_address LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if (!empty($filterAction)) {
    $query .= " AND action = ?";
    $params[] = $filterAction;
}

$query .= " ORDER BY id DESC LIMIT 100";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Unique actions for dropdown
$actionsList = $pdo->query("SELECT DISTINCT action FROM activity_logs ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);

$csrfToken = generateCsrfToken();

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div class="flex-1 flex flex-col min-w-0 bg-slate-950">
    <?php require_once __DIR__ . '/navbar.php'; ?>

    <main class="flex-1 p-4 sm:p-8 max-w-7xl w-full mx-auto space-y-6">
        
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-extrabold text-white flex items-center gap-3">
                    <span>System Audit Trail</span>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-purple-500/10 text-purple-400 border border-purple-500/20">
                        <?= count($logs) ?> Events
                    </span>
                </h2>
                <p class="text-xs text-slate-400 mt-1">Immutable record of logins, user creations, role alterations, and disaster ops</p>
            </div>
            
            <form method="POST" action="activity_logs.php" onsubmit="return confirmClearLogs(event)" class="inline">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="clear_logs">
                <button type="submit" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-rose-500/20 border border-slate-700 hover:border-rose-500/30 text-slate-400 hover:text-rose-400 text-xs font-bold transition-all flex items-center gap-2">
                    <i class="fa-solid fa-trash-can text-xs"></i>
                    <span>Purge Logs</span>
                </button>
            </form>
        </div>

        <!-- Filter Bar -->
        <div class="glass-panel p-4 rounded-2xl">
            <form method="GET" action="activity_logs.php" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
                <div class="sm:col-span-6 relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                        <i class="fa-solid fa-magnifying-glass text-xs"></i>
                    </div>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by user, details, IP address..." 
                           class="w-full pl-9 pr-4 py-2 bg-slate-900/90 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition-colors">
                </div>

                <div class="sm:col-span-4">
                    <select name="action_filter" class="w-full py-2 px-3 bg-slate-900/90 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                        <option value="">All Action Types</option>
                        <?php foreach ($actionsList as $act): ?>
                            <option value="<?= htmlspecialchars($act) ?>" <?= $filterAction === $act ? 'selected' : '' ?>>
                                <?= htmlspecialchars($act) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sm:col-span-2 flex items-center gap-2">
                    <button type="submit" class="w-full py-2 px-3 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-xl border border-slate-700 transition-colors flex items-center justify-center gap-1.5">
                        <i class="fa-solid fa-filter text-[10px]"></i>
                        <span>Filter</span>
                    </button>
                    <?php if (!empty($search) || !empty($filterAction)): ?>
                        <a href="activity_logs.php" class="p-2 bg-slate-800 hover:bg-rose-500/20 text-slate-400 hover:text-rose-400 rounded-xl border border-slate-700 transition-colors" title="Clear Filters">
                            <i class="fa-solid fa-rotate-left text-xs"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Logs Table Card -->
        <div class="glass-panel rounded-3xl overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-800/80 bg-slate-900/40 text-slate-400 uppercase tracking-wider font-semibold">
                            <th class="py-3.5 px-4 sm:px-6">Timestamp</th>
                            <th class="py-3.5 px-4">User</th>
                            <th class="py-3.5 px-4">Action</th>
                            <th class="py-3.5 px-4 sm:px-6">Details</th>
                            <th class="py-3.5 px-4 text-right">IP Address</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 text-slate-300">
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="5" class="py-12 text-center text-slate-500">
                                    <div class="w-12 h-12 rounded-2xl bg-slate-900 border border-slate-800 text-slate-600 flex items-center justify-center mx-auto mb-3 text-lg">
                                        <i class="fa-solid fa-clock-rotate-left"></i>
                                    </div>
                                    <p class="font-medium text-slate-400">No activity logs recorded matching criteria</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <?php
                                    $actionBadge = match(true) {
                                        str_contains($log['action'], 'DELETE') => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
                                        str_contains($log['action'], 'CREATE') => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                                        str_contains($log['action'], 'LOGIN') => 'bg-blue-500/10 text-blue-400 border-blue-500/20',
                                        str_contains($log['action'], 'DISASTER') => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                        default => 'bg-slate-800 text-indigo-300 border-slate-700'
                                    };
                                ?>
                                <tr class="hover:bg-slate-800/30 transition-colors">
                                    <td class="py-3.5 px-4 sm:px-6 text-slate-400 font-mono text-[11px] whitespace-nowrap">
                                        <?= date('M d, Y H:i:s', strtotime($log['created_at'])) ?>
                                    </td>
                                    <td class="py-3.5 px-4 font-semibold text-white whitespace-nowrap">
                                        <?= htmlspecialchars($log['user_name'] ?: 'System / Guest') ?>
                                    </td>
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <span class="inline-block px-2.5 py-0.5 rounded-lg text-[10px] font-bold border <?= $actionBadge ?>">
                                            <?= htmlspecialchars($log['action']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 sm:px-6 text-slate-300">
                                        <?= htmlspecialchars($log['details'] ?: 'No additional details') ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-mono text-[11px] text-slate-500">
                                        <?= htmlspecialchars($log['ip_address'] ?: '127.0.0.1') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<script>
function confirmClearLogs(e) {
    e.preventDefault();
    Swal.fire({
        title: 'Purge Activity Logs?',
        text: 'Are you sure you want to clear all system audit logs? This cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        cancelButtonColor: '#334155',
        confirmButtonText: 'Yes, clear logs',
        cancelButtonText: 'Cancel',
        background: '#1e293b',
        color: '#f8fafc'
    }).then((result) => {
        if (result.isConfirmed) {
            e.target.submit();
        }
    });
    return false;
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
