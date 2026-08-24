<?php
// deployments.php - Police Squad Deployments & Tactical Zone Management
define('PAGE_TITLE', 'Squad Deployments');
require_once __DIR__ . '/auth.php';

$currentUser = requirePolice($pdo);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrf)) {
        setFlash('error', 'Security token invalid.');
        header("Location: deployments.php");
        exit;
    }

    // 1. ADD SQUAD DEPLOYMENT
    if ($action === 'create_deployment') {
        $disasterId = 1;
        $zoneName = trim($_POST['zone_name'] ?? '');
        $callsign = trim($_POST['unit_callsign'] ?? '');
        $officersCount = (int) ($_POST['officers_count'] ?? 4);
        $missionType = $_POST['mission_type'] ?? 'Perimeter Security';
        $radio = trim($_POST['contact_radio'] ?? 'Freq 155.00 MHz');

        if (!empty($zoneName) && !empty($callsign)) {
            $stmt = $pdo->prepare("INSERT INTO police_deployments (disaster_id, zone_name, unit_callsign, officers_count, mission_type, status, contact_radio) VALUES (?, ?, ?, ?, ?, 'Active', ?)");
            $stmt->execute([$disasterId, $zoneName, $callsign, $officersCount, $missionType, $radio]);

            logActivity($pdo, 'CREATE_POLICE_DEPLOYMENT', "Deployed unit '{$callsign}' ({$officersCount} officers) to zone '{$zoneName}'");
            setFlash('success', "Police tactical unit '{$callsign}' deployed to {$zoneName}.");
        } else {
            setFlash('error', 'Please fill in all deployment fields.');
        }
        header("Location: deployments.php");
        exit;
    }

    // 2. UPDATE SQUAD STATUS
    if ($action === 'update_status') {
        $depId = (int) ($_POST['deployment_id'] ?? 0);
        $newStatus = $_POST['status'] ?? 'Active';

        $stmt = $pdo->prepare("UPDATE police_deployments SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $depId]);

        logActivity($pdo, 'UPDATE_DEPLOYMENT_STATUS', "Updated deployment #{$depId} status to {$newStatus}");
        setFlash('success', "Deployment status updated to {$newStatus}.");
        header("Location: deployments.php");
        exit;
    }

    // 3. DELETE DEPLOYMENT
    if ($action === 'delete_deployment') {
        $depId = (int) ($_POST['deployment_id'] ?? 0);
        $pdo->prepare("DELETE FROM police_deployments WHERE id = ?")->execute([$depId]);
        logActivity($pdo, 'DELETE_DEPLOYMENT', "Removed deployment record #{$depId}");
        setFlash('success', 'Squad deployment record removed.');
        header("Location: deployments.php");
        exit;
    }
}

// Queries
$search = trim($_GET['search'] ?? '');
$filterMission = trim($_GET['mission'] ?? '');

$query = "
    SELECT pd.* 
    FROM police_deployments pd 
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $query .= " AND (pd.unit_callsign LIKE ? OR pd.zone_name LIKE ? OR pd.contact_radio LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if (!empty($filterMission)) {
    $query .= " AND pd.mission_type = ?";
    $params[] = $filterMission;
}

$query .= " ORDER BY pd.id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$deployments = $stmt->fetchAll();

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
                    <span>Police Squad Deployments</span>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-500/10 text-blue-400 border border-blue-500/20">
                        <?= count($deployments) ?> Squads
                    </span>
                </h2>
                <p class="text-xs text-slate-400 mt-1">Manage field security units, radio communication frequencies, and zone perimeters</p>
            </div>
            <button type="button" onclick="openCreateDeploymentModal()" class="px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold transition-all shadow-lg shadow-blue-600/30 flex items-center justify-center gap-2">
                <i class="fa-solid fa-person-military-pointing text-xs"></i>
                <span>Deploy New Squad</span>
            </button>
        </div>

        <!-- Filter Bar -->
        <div class="glass-panel p-4 rounded-2xl">
            <form method="GET" action="deployments.php" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
                <div class="sm:col-span-6 relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                        <i class="fa-solid fa-magnifying-glass text-xs"></i>
                    </div>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by callsign, zone, radio frequency..." 
                           class="w-full pl-9 pr-4 py-2 bg-slate-900/90 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 transition-colors">
                </div>

                <div class="sm:col-span-4">
                    <select name="mission" class="w-full py-2 px-3 bg-slate-900/90 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-blue-500">
                        <option value="">All Mission Types</option>
                        <option value="Evacuation" <?= $filterMission === 'Evacuation' ? 'selected' : '' ?>>Evacuation Escort</option>
                        <option value="Perimeter Security" <?= $filterMission === 'Perimeter Security' ? 'selected' : '' ?>>Perimeter Security</option>
                        <option value="Traffic & Road Closure" <?= $filterMission === 'Traffic & Road Closure' ? 'selected' : '' ?>>Traffic & Road Closure</option>
                        <option value="Anti-Looting Patrol" <?= $filterMission === 'Anti-Looting Patrol' ? 'selected' : '' ?>>Anti-Looting Patrol</option>
                        <option value="Search Escort" <?= $filterMission === 'Search Escort' ? 'selected' : '' ?>>Rescue Escort</option>
                    </select>
                </div>

                <div class="sm:col-span-2 flex items-center gap-2">
                    <button type="submit" class="w-full py-2 px-3 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-xl border border-slate-700 transition-colors flex items-center justify-center gap-1.5">
                        <i class="fa-solid fa-filter text-[10px]"></i>
                        <span>Filter</span>
                    </button>
                    <?php if (!empty($search) || !empty($filterMission)): ?>
                        <a href="deployments.php" class="p-2 bg-slate-800 hover:bg-rose-500/20 text-slate-400 hover:text-rose-400 rounded-xl border border-slate-700 transition-colors" title="Clear Filters">
                            <i class="fa-solid fa-rotate-left text-xs"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Deployments Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($deployments as $d): ?>
                <?php
                    $isActive = ($d['status'] === 'Active');
                ?>
                <div class="glass-panel p-5 rounded-2xl border border-slate-800/80 flex flex-col justify-between space-y-4 hover:border-blue-500/40 transition-all">
                    <div>
                        <div class="flex items-start justify-between gap-2 mb-3">
                            <div class="flex items-center gap-2.5">
                                <div class="w-9 h-9 rounded-xl bg-blue-500/10 text-blue-400 border border-blue-500/20 flex items-center justify-center text-sm">
                                    <i class="fa-solid fa-shield-halved"></i>
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-white"><?= htmlspecialchars($d['unit_callsign']) ?></h4>
                                    <span class="text-[11px] text-blue-300 font-medium"><?= htmlspecialchars($d['mission_type']) ?></span>
                                </div>
                            </div>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $isActive ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-slate-800 text-slate-400' ?>">
                                <?= htmlspecialchars($d['status']) ?>
                            </span>
                        </div>

                        <div class="p-3 rounded-xl bg-slate-950/70 border border-slate-800/80 space-y-1.5 text-xs text-slate-300">
                            <p class="flex items-center gap-2">
                                <i class="fa-solid fa-location-dot text-rose-400 text-xs w-4"></i>
                                <span class="font-semibold text-white"><?= htmlspecialchars($d['zone_name']) ?></span>
                            </p>
                            <p class="flex items-center gap-2 text-slate-400">
                                <i class="fa-solid fa-triangle-exclamation text-amber-400 text-xs w-4"></i>
                                <span><?= htmlspecialchars($d['disaster_title']) ?></span>
                            </p>
                            <p class="flex items-center gap-2 font-mono text-indigo-300">
                                <i class="fa-solid fa-walkie-talkie text-indigo-400 text-xs w-4"></i>
                                <span><?= htmlspecialchars($d['contact_radio'] ?: 'Freq 155.00 MHz') ?></span>
                            </p>
                            <p class="flex items-center gap-2 text-slate-400">
                                <i class="fa-solid fa-users text-slate-500 text-xs w-4"></i>
                                <span><?= $d['officers_count'] ?> Officers Assigned</span>
                            </p>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-800 flex items-center justify-between">
                        <form method="POST" action="deployments.php" class="inline">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="deployment_id" value="<?= $d['id'] ?>">
                            <input type="hidden" name="status" value="<?= $isActive ? 'Relieved' : 'Active' ?>">
                            <button type="submit" class="px-2.5 py-1 rounded-lg text-xs font-semibold <?= $isActive ? 'bg-slate-800 hover:bg-slate-700 text-slate-300' : 'bg-emerald-600 hover:bg-emerald-500 text-white' ?> transition-colors">
                                <?= $isActive ? 'Mark Relieved' : 'Reactivate Squad' ?>
                            </button>
                        </form>

                        <form method="POST" action="deployments.php" class="inline" onsubmit="return confirm('Delete this deployment record?');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="action" value="delete_deployment">
                            <input type="hidden" name="deployment_id" value="<?= $d['id'] ?>">
                            <button type="submit" class="p-1.5 text-slate-500 hover:text-rose-400 transition-colors" title="Delete">
                                <i class="fa-solid fa-trash-can text-xs"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </main>
</div>

<!-- CREATE SQUAD MODAL -->
<div id="createDeploymentModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl relative animate-in fade-in zoom-in duration-200">
        
        <div class="flex items-center justify-between pb-4 mb-5 border-b border-slate-800">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-500/20 text-blue-400 flex items-center justify-center">
                    <i class="fa-solid fa-person-military-pointing text-base"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-white">Deploy Police Unit</h3>
                    <p class="text-xs text-slate-400">Deploy tactical squad to crisis zone</p>
                </div>
            </div>
            <button type="button" onclick="closeCreateDeploymentModal()" class="text-slate-400 hover:text-white p-2">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="deployments.php" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="create_deployment">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Unit Callsign *</label>
                    <input type="text" name="unit_callsign" required placeholder="e.g. Delta-Squad 4" 
                       class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Officers Count *</label>
                    <input type="number" name="officers_count" min="1" value="4" required 
                           class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-blue-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Mission Type *</label>
                <select name="mission_type" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-blue-500">
                    <option value="Evacuation">Evacuation Escort</option>
                    <option value="Perimeter Security">Perimeter Security & Cordon</option>
                    <option value="Traffic & Road Closure">Traffic & Road Closure</option>
                    <option value="Anti-Looting Patrol">Anti-Looting Patrol</option>
                    <option value="Search Escort">Rescue Security Escort</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Zone / Sector Name *</label>
                <input type="text" name="zone_name" required placeholder="e.g. Coastline Highway 101 Access Point" 
                       class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-blue-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Radio Frequency</label>
                <input type="text" name="contact_radio" placeholder="e.g. Freq 154.80 MHz" 
                       class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-blue-500">
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800 mt-6">
                <button type="button" onclick="closeCreateDeploymentModal()" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold shadow-lg shadow-blue-600/30 transition-all">
                    Deploy Squad
                </button>
            </div>
        </form>

    </div>
</div>

<script>
function openCreateDeploymentModal() {
    document.getElementById('createDeploymentModal').classList.remove('hidden');
}
function closeCreateDeploymentModal() {
    document.getElementById('createDeploymentModal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
