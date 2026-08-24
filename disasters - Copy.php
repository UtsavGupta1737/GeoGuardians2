<?php
// disasters.php - Master Disaster Command & Operations Hub
define('PAGE_TITLE', 'Disaster Command Hub');
require_once __DIR__ . '/auth.php';

$currentUser = requirePermissionGuard($pdo, 'access_disasters');

// Handle Actions (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrf)) {
        setFlash('error', 'Security session expired. Please retry.');
        header("Location: disasters.php");
        exit;
    }

    // 1. DECLARE NEW DISASTER
    if ($action === 'create_disaster') {
        $title = trim($_POST['title'] ?? '');
        $type = $_POST['type'] ?? 'Flood';
        $location = trim($_POST['location'] ?? '');
        $severity = $_POST['severity'] ?? 'Critical';
        $status = $_POST['status'] ?? 'Active';
        $casualties = (int) ($_POST['casualties'] ?? 0);
        $displaced = (int) ($_POST['displaced_people'] ?? 0);
        $description = trim($_POST['description'] ?? '');

        if (empty($title) || empty($location)) {
            setFlash('error', 'Disaster title and location are required.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO disasters (title, type, location, severity, status, casualties, displaced_people, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $type, $location, $severity, $status, $casualties, $displaced, $description]);

            logActivity($pdo, 'DECLARE_DISASTER', "Declared disaster '{$title}' ({$type}) at {$location} - Severity: {$severity}");
            setFlash('success', "Disaster incident '{$title}' declared. Volunteer & Police dispatch alerts opened.");
        }
        header("Location: disasters.php");
        exit;
    }

    // 2. UPDATE DISASTER
    if ($action === 'update_disaster') {
        $disasterId = (int) ($_POST['disaster_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $type = $_POST['type'] ?? 'Flood';
        $location = trim($_POST['location'] ?? '');
        $severity = $_POST['severity'] ?? 'Critical';
        $status = $_POST['status'] ?? 'Active';
        $casualties = (int) ($_POST['casualties'] ?? 0);
        $displaced = (int) ($_POST['displaced_people'] ?? 0);
        $description = trim($_POST['description'] ?? '');

        if (empty($disasterId) || empty($title) || empty($location)) {
            setFlash('error', 'Missing required disaster information.');
        } else {
            $stmt = $pdo->prepare("UPDATE disasters SET title = ?, type = ?, location = ?, severity = ?, status = ?, casualties = ?, displaced_people = ?, description = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$title, $type, $location, $severity, $status, $casualties, $displaced, $description, $disasterId]);

            logActivity($pdo, 'UPDATE_DISASTER', "Updated disaster incident ID #{$disasterId} ({$title})");
            setFlash('success', "Disaster incident '{$title}' updated successfully.");
        }
        header("Location: disasters.php");
        exit;
    }

    // 3. DELETE DISASTER
    if ($action === 'delete_disaster') {
        $disasterId = (int) ($_POST['disaster_id'] ?? 0);
        $target = $pdo->query("SELECT title FROM disasters WHERE id = {$disasterId}")->fetch();
        if ($target) {
            $pdo->prepare("DELETE FROM disasters WHERE id = ?")->execute([$disasterId]);
            logActivity($pdo, 'DELETE_DISASTER', "Deleted disaster incident '{$target['title']}'");
            setFlash('success', "Disaster '{$target['title']}' removed from registry.");
        }
        header("Location: disasters.php");
        exit;
    }

    // 4. POST VOLUNTEER TASK
    if ($action === 'add_volunteer_task') {
        $disasterId = (int) ($_POST['disaster_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $category = $_POST['category'] ?? 'Search & Rescue';
        $location = trim($_POST['location'] ?? '');
        $reqCount = (int) ($_POST['required_volunteers'] ?? 5);
        $desc = trim($_POST['description'] ?? '');

        if ($disasterId > 0 && !empty($title) && !empty($location)) {
            $stmt = $pdo->prepare("INSERT INTO volunteer_tasks (disaster_id, title, category, location, required_volunteers, description) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$disasterId, $title, $category, $location, $reqCount, $desc]);

            logActivity($pdo, 'ADD_VOLUNTEER_TASK', "Created volunteer mission '{$title}' for disaster ID #{$disasterId}");
            setFlash('success', "Volunteer mission '{$title}' posted to Volunteer mission board.");
        } else {
            setFlash('error', 'Please fill in all task fields.');
        }
        header("Location: disasters.php");
        exit;
    }
}

// Queries
$disasters = $pdo->query("SELECT * FROM disasters ORDER BY id DESC")->fetchAll();

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
                    <span>Disaster Command Hub</span>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                        <?= count($disasters) ?> Incidents Logged
                    </span>
                </h2>
                <p class="text-xs text-slate-400 mt-1">Declare disaster emergencies, mobilize field units, and dispatch volunteer/police operations</p>
            </div>
            <button type="button" onclick="openCreateDisasterModal()" class="px-4 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold transition-all shadow-lg shadow-amber-600/30 flex items-center justify-center gap-2">
                <i class="fa-solid fa-triangle-exclamation text-xs"></i>
                <span>Declare Disaster Event</span>
            </button>
        </div>

        <!-- Disasters Grid -->
        <div class="space-y-4">
            <?php foreach ($disasters as $d): ?>
                <?php
                    $sevBadge = match($d['severity']) {
                        'Critical' => 'bg-rose-500/10 text-rose-400 border-rose-500/30',
                        'High' => 'bg-amber-500/10 text-amber-400 border-amber-500/30',
                        'Moderate' => 'bg-yellow-500/10 text-yellow-300 border-yellow-500/30',
                        default => 'bg-blue-500/10 text-blue-400 border-blue-500/30'
                    };
                    $tasksCount = $pdo->query("SELECT COUNT(*) FROM volunteer_tasks WHERE disaster_id = {$d['id']}")->fetchColumn();
                    $deployCount = $pdo->query("SELECT COUNT(*) FROM police_deployments WHERE disaster_id = {$d['id']}")->fetchColumn();
                    $sosCount = $pdo->query("SELECT COUNT(*) FROM emergency_sos WHERE disaster_id = {$d['id']}")->fetchColumn();
                ?>
                <div class="glass-panel p-6 rounded-3xl border border-slate-800/80 hover:border-amber-500/40 transition-all space-y-4">
                    
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                        <div class="flex items-start gap-3.5">
                            <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-400 border border-amber-500/20 flex items-center justify-center text-xl shrink-0">
                                <i class="fa-solid <?= $d['type'] === 'Flood' ? 'fa-water' : ($d['type'] === 'Cyclone' ? 'fa-tornado' : ($d['type'] === 'Earthquake' ? 'fa-house-crack' : 'fa-triangle-exclamation')) ?>"></i>
                            </div>
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-base font-bold text-white"><?= htmlspecialchars($d['title']) ?></h3>
                                    <span class="px-2.5 py-0.5 rounded-lg text-[10px] font-bold border <?= $sevBadge ?>">
                                        <?= htmlspecialchars($d['severity']) ?> SEVERITY
                                    </span>
                                    <span class="px-2.5 py-0.5 rounded-lg text-[10px] font-bold bg-slate-900 border border-slate-800 text-slate-300">
                                        <?= htmlspecialchars($d['status']) ?>
                                    </span>
                                </div>
                                <p class="text-xs text-amber-300 font-medium mt-1 flex items-center gap-1.5">
                                    <i class="fa-solid fa-location-dot text-rose-400"></i>
                                    <span><?= htmlspecialchars($d['location']) ?></span>
                                    <span class="text-slate-500">&bull; Type: <?= htmlspecialchars($d['type']) ?></span>
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <button type="button" 
                                    onclick='openAddTaskModal(<?= $d['id'] ?>, "<?= addslashes(htmlspecialchars($d['title'])) ?>")'
                                    class="px-3 py-1.5 rounded-xl bg-emerald-600/20 hover:bg-emerald-600 text-emerald-300 hover:text-white text-xs font-bold border border-emerald-500/30 transition-all flex items-center gap-1.5">
                                <i class="fa-solid fa-plus text-[10px]"></i> Dispatch Task
                            </button>
                            <button type="button" 
                                    onclick='openEditDisasterModal(<?= json_encode($d, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                    class="p-2 rounded-xl bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-400 border border-indigo-500/20 transition-colors" title="Edit Incident">
                                <i class="fa-solid fa-pen-to-square text-xs"></i>
                            </button>
                            <button type="button" 
                                    onclick="confirmDeleteDisaster(<?= $d['id'] ?>, '<?= addslashes(htmlspecialchars($d['title'])) ?>')"
                                    class="p-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 transition-colors" title="Delete Incident">
                                <i class="fa-solid fa-trash-can text-xs"></i>
                            </button>
                        </div>
                    </div>

                    <p class="text-xs text-slate-300 leading-relaxed bg-slate-950/60 p-3 rounded-2xl border border-slate-800/80">
                        <?= nl2br(htmlspecialchars($d['description'])) ?>
                    </p>

                    <!-- Stats Row -->
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 pt-2">
                        <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800">
                            <p class="text-[10px] uppercase font-bold text-slate-500">Casualties</p>
                            <p class="text-sm font-bold text-rose-400 font-mono mt-0.5"><?= $d['casualties'] ?></p>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800">
                            <p class="text-[10px] uppercase font-bold text-slate-500">Displaced People</p>
                            <p class="text-sm font-bold text-amber-400 font-mono mt-0.5"><?= number_format($d['displaced_people']) ?></p>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800">
                            <p class="text-[10px] uppercase font-bold text-slate-500">Volunteer Missions</p>
                            <p class="text-sm font-bold text-emerald-400 font-mono mt-0.5"><?= $tasksCount ?> Tasks</p>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800">
                            <p class="text-[10px] uppercase font-bold text-slate-500">Police Squads</p>
                            <p class="text-sm font-bold text-blue-400 font-mono mt-0.5"><?= $deployCount ?> Units</p>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800">
                            <p class="text-[10px] uppercase font-bold text-slate-500">Distress SOS</p>
                            <p class="text-sm font-bold text-purple-400 font-mono mt-0.5"><?= $sosCount ?> Calls</p>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>

    </main>
</div>

<!-- CREATE DISASTER MODAL -->
<div id="createDisasterModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-xl w-full p-6 sm:p-8 shadow-2xl relative animate-in fade-in zoom-in duration-200">
        
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-800">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center">
                    <i class="fa-solid fa-triangle-exclamation text-base"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-white">Declare Disaster Emergency</h3>
                    <p class="text-xs text-slate-400">Post a live crisis zone to mobilize response units</p>
                </div>
            </div>
            <button type="button" onclick="closeCreateDisasterModal()" class="text-slate-400 hover:text-white p-2">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="disasters.php" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="create_disaster">

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Disaster Incident Title *</label>
                <input type="text" name="title" required placeholder="e.g. Category 4 Coastal Cyclone - Sector Alpha" 
                       class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-amber-500">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Incident Type *</label>
                    <select name="type" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-amber-500">
                        <option value="Flood">Flash Flood</option>
                        <option value="Cyclone">Cyclone / Hurricane</option>
                        <option value="Earthquake">Earthquake</option>
                        <option value="Wildfire">Wildfire</option>
                        <option value="Industrial Hazard">Industrial / Chemical Hazard</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Severity Level *</label>
                    <select name="severity" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-amber-500">
                        <option value="Critical">Critical (Red Alert)</option>
                        <option value="High">High Severity</option>
                        <option value="Moderate">Moderate Severity</option>
                        <option value="Low">Low / Advisory</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Affected Location / Sector *</label>
                <input type="text" name="location" required placeholder="e.g. Coastal Bay District & Shoreline Towns" 
                       class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-amber-500">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Casualties Count</label>
                    <input type="number" name="casualties" min="0" value="0" 
                           class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-amber-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Displaced Citizens</label>
                    <input type="number" name="displaced_people" min="0" value="0" 
                           class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-amber-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Situation Brief & Directives</label>
                <textarea name="description" rows="3" placeholder="Brief situation overview, relief requirements, evacuation orders..." 
                          class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-amber-500"></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800 mt-6">
                <button type="button" onclick="closeCreateDisasterModal()" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold shadow-lg shadow-amber-600/30 transition-all">
                    Declare Disaster
                </button>
            </div>
        </form>

    </div>
</div>

<!-- EDIT DISASTER MODAL -->
<div id="editDisasterModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-xl w-full p-6 sm:p-8 shadow-2xl relative animate-in fade-in zoom-in duration-200">
        
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-800">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center">
                    <i class="fa-solid fa-pen-to-square text-base"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-white">Update Disaster Record</h3>
                    <p class="text-xs text-slate-400">Modify casualty counts, severity, and status</p>
                </div>
            </div>
            <button type="button" onclick="closeEditDisasterModal()" class="text-slate-400 hover:text-white p-2">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="disasters.php" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="update_disaster">
            <input type="hidden" name="disaster_id" id="edit_disaster_id">

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Disaster Incident Title *</label>
                <input type="text" name="title" id="edit_title" required 
                       class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Type</label>
                    <select name="type" id="edit_type" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                        <option value="Flood">Flood</option>
                        <option value="Cyclone">Cyclone</option>
                        <option value="Earthquake">Earthquake</option>
                        <option value="Wildfire">Wildfire</option>
                        <option value="Industrial Hazard">Industrial Hazard</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Severity</label>
                    <select name="severity" id="edit_severity" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                        <option value="Critical">Critical</option>
                        <option value="High">High</option>
                        <option value="Moderate">Moderate</option>
                        <option value="Low">Low</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Status</label>
                    <select name="status" id="edit_status" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                        <option value="Active">Active</option>
                        <option value="Under Control">Under Control</option>
                        <option value="Resolved">Resolved</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Location *</label>
                <input type="text" name="location" id="edit_location" required 
                       class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Casualties</label>
                    <input type="number" name="casualties" id="edit_casualties" min="0" 
                           class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Displaced</label>
                    <input type="number" name="displaced_people" id="edit_displaced_people" min="0" 
                           class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Situation Description</label>
                <textarea name="description" id="edit_description" rows="3" 
                          class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500"></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800 mt-6">
                <button type="button" onclick="closeEditDisasterModal()" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-lg shadow-indigo-600/30 transition-all">
                    Save Changes
                </button>
            </div>
        </form>

    </div>
</div>

<!-- ADD VOLUNTEER TASK MODAL -->
<div id="addTaskModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl relative animate-in fade-in zoom-in duration-200">
        
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-800">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
                    <i class="fa-solid fa-list-check text-base"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-white">Post Volunteer Task</h3>
                    <p class="text-xs text-slate-400">Target Incident: <strong class="text-emerald-300" id="task_disaster_title"></strong></p>
                </div>
            </div>
            <button type="button" onclick="closeAddTaskModal()" class="text-slate-400 hover:text-white p-2">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="disasters.php" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="add_volunteer_task">
            <input type="hidden" name="disaster_id" id="task_disaster_id">

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Mission Title *</label>
                <input type="text" name="title" required placeholder="e.g. Distribute Clean Drinking Water & Dry Rations" 
                       class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Mission Category *</label>
                    <select name="category" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-emerald-500">
                        <option value="Search & Rescue">Search & Rescue Support</option>
                        <option value="Medical Aid">Medical First Aid & Triage</option>
                        <option value="Food & Water">Food & Water Distribution</option>
                        <option value="Shelter Management">Shelter Management</option>
                        <option value="Logistics">Logistics & Transport</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Volunteers Needed *</label>
                    <input type="number" name="required_volunteers" min="1" value="5" required 
                           class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-emerald-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Mission Location / Sector *</label>
                <input type="text" name="location" required placeholder="e.g. Sector 3 Community Center" 
                       class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Instructions for Volunteers</label>
                <textarea name="description" rows="2" placeholder="Tasks to perform, assembly point, protective gear required..." 
                          class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500"></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800 mt-6">
                <button type="button" onclick="closeAddTaskModal()" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-lg shadow-emerald-600/30 transition-all">
                    Publish to Volunteer Board
                </button>
            </div>
        </form>

    </div>
</div>

<!-- HIDDEN DELETE FORM -->
<form id="deleteDisasterForm" method="POST" action="disasters.php" class="hidden">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <input type="hidden" name="action" value="delete_disaster">
    <input type="hidden" name="disaster_id" id="delete_disaster_id">
</form>

<script>
function openCreateDisasterModal() {
    document.getElementById('createDisasterModal').classList.remove('hidden');
}
function closeCreateDisasterModal() {
    document.getElementById('createDisasterModal').classList.add('hidden');
}

function openEditDisasterModal(d) {
    document.getElementById('edit_disaster_id').value = d.id;
    document.getElementById('edit_title').value = d.title || '';
    document.getElementById('edit_type').value = d.type || 'Flood';
    document.getElementById('edit_severity').value = d.severity || 'Critical';
    document.getElementById('edit_status').value = d.status || 'Active';
    document.getElementById('edit_location').value = d.location || '';
    document.getElementById('edit_casualties').value = d.casualties || 0;
    document.getElementById('edit_displaced_people').value = d.displaced_people || 0;
    document.getElementById('edit_description').value = d.description || '';

    document.getElementById('editDisasterModal').classList.remove('hidden');
}
function closeEditDisasterModal() {
    document.getElementById('editDisasterModal').classList.add('hidden');
}

function openAddTaskModal(disasterId, disasterTitle) {
    document.getElementById('task_disaster_id').value = disasterId;
    document.getElementById('task_disaster_title').textContent = disasterTitle;
    document.getElementById('addTaskModal').classList.remove('hidden');
}
function closeAddTaskModal() {
    document.getElementById('addTaskModal').classList.add('hidden');
}

function confirmDeleteDisaster(disasterId, title) {
    Swal.fire({
        title: 'Delete Disaster Record?',
        text: `Are you sure you want to remove '${title}'? All associated tasks and assignments will be removed.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        cancelButtonColor: '#334155',
        confirmButtonText: 'Yes, delete record',
        cancelButtonText: 'Cancel',
        background: '#1e293b',
        color: '#f8fafc'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('delete_disaster_id').value = disasterId;
            document.getElementById('deleteDisasterForm').submit();
        }
    });
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
