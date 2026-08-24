<?php
// disasters.php - Master Disaster Command & Operations Hub (Government Theme)
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

<div class="flex-1 flex flex-col min-w-0 bg-[#f8fafc] min-h-screen overflow-y-auto">
    <?php require_once __DIR__ . '/navbar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto w-full">
        
        <!-- HEADER BANNER -->
        <section class="bg-white p-5 sm:p-6 rounded-3xl border border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">Disaster Command Hub</h2>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200 mono">
                        <?= count($disasters) ?> Incidents Logged
                    </span>
                </div>
                <p class="text-xs text-slate-500 font-medium mt-1">Declare disaster emergencies, mobilize field units, and dispatch volunteer/police operations</p>
            </div>
            <button type="button" onclick="openCreateDisasterModal()" class="px-4 py-2.5 rounded-2xl bg-[#d97706] hover:bg-[#b45309] text-white text-xs font-bold transition-all shadow-sm flex items-center justify-center gap-2 cursor-pointer">
                <i class="fa-solid fa-triangle-exclamation text-xs"></i>
                <span>Declare Disaster Event</span>
            </button>
        </section>

        <!-- Disasters Grid -->
        <div class="space-y-4">
            <?php foreach ($disasters as $d): ?>
                <?php
                    $sevBadge = match($d['severity']) {
                        'Critical' => 'bg-red-50 text-red-800 border-red-200',
                        'High' => 'bg-amber-50 text-amber-800 border-amber-200',
                        'Moderate' => 'bg-yellow-50 text-yellow-800 border-yellow-200',
                        default => 'bg-blue-50 text-blue-800 border-blue-200'
                    };
                    $tasksCount = $pdo->query("SELECT COUNT(*) FROM volunteer_tasks WHERE disaster_id = {$d['id']}")->fetchColumn();
                    $deployCount = $pdo->query("SELECT COUNT(*) FROM police_deployments WHERE disaster_id = {$d['id']}")->fetchColumn();
                    $sosCount = $pdo->query("SELECT COUNT(*) FROM emergency_sos WHERE disaster_id = {$d['id']}")->fetchColumn();
                ?>
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs hover:border-slate-300 hover:shadow-sm transition-all space-y-4">
                    
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                        <div class="flex items-start gap-3.5">
                            <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 border border-amber-200 flex items-center justify-center text-xl shrink-0">
                                <i class="fa-solid <?= $d['type'] === 'Flood' ? 'fa-water' : ($d['type'] === 'Cyclone' ? 'fa-tornado' : ($d['type'] === 'Earthquake' ? 'fa-house-crack' : 'fa-triangle-exclamation')) ?>"></i>
                            </div>
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-base font-extrabold text-slate-900"><?= htmlspecialchars($d['title']) ?></h3>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border <?= $sevBadge ?> mono">
                                        <?= htmlspecialchars($d['severity']) ?> SEVERITY
                                    </span>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 border border-slate-200 text-slate-700 mono">
                                        <?= htmlspecialchars($d['status']) ?>
                                    </span>
                                </div>
                                <p class="text-xs text-amber-700 font-bold mt-1 flex items-center gap-1.5">
                                    <i class="fa-solid fa-location-dot text-red-500"></i>
                                    <span><?= htmlspecialchars($d['location']) ?></span>
                                    <span class="text-slate-500 font-normal">&bull; Type: <?= htmlspecialchars($d['type']) ?></span>
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <button type="button" 
                                    onclick='openAddTaskModal(<?= $d['id'] ?>, "<?= addslashes(htmlspecialchars($d['title'])) ?>")'
                                    class="px-3.5 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-bold border border-emerald-200 transition-all flex items-center gap-1.5 cursor-pointer">
                                <i class="fa-solid fa-plus text-[10px]"></i> Dispatch Task
                            </button>
                            <button type="button" 
                                    onclick='openEditDisasterModal(<?= json_encode($d, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                    class="p-2 rounded-xl bg-blue-50 hover:bg-blue-100 text-[#1d63d8] border border-blue-200 transition-colors cursor-pointer" title="Edit Incident">
                                <i class="fa-solid fa-pen-to-square text-xs"></i>
                            </button>
                            <button type="button" 
                                    onclick="confirmDeleteDisaster(<?= $d['id'] ?>, '<?= addslashes(htmlspecialchars($d['title'])) ?>')"
                                    class="p-2 rounded-xl bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 transition-colors cursor-pointer" title="Delete Incident">
                                <i class="fa-solid fa-trash-can text-xs"></i>
                            </button>
                        </div>
                    </div>

                    <p class="text-xs text-slate-600 leading-relaxed bg-slate-50 p-3.5 rounded-2xl border border-slate-200 font-medium">
                        <?= nl2br(htmlspecialchars($d['description'])) ?>
                    </p>

                    <!-- Stats Row -->
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 pt-2">
                        <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200">
                            <p class="text-[10px] uppercase font-bold text-slate-500 mono">Casualties</p>
                            <p class="text-sm font-extrabold text-red-700 font-mono mt-0.5"><?= $d['casualties'] ?></p>
                        </div>
                        <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200">
                            <p class="text-[10px] uppercase font-bold text-slate-500 mono">Displaced People</p>
                            <p class="text-sm font-extrabold text-amber-700 font-mono mt-0.5"><?= number_format($d['displaced_people']) ?></p>
                        </div>
                        <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200">
                            <p class="text-[10px] uppercase font-bold text-slate-500 mono">Volunteer Missions</p>
                            <p class="text-sm font-extrabold text-emerald-700 font-mono mt-0.5"><?= $tasksCount ?> Tasks</p>
                        </div>
                        <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200">
                            <p class="text-[10px] uppercase font-bold text-slate-500 mono">Police Squads</p>
                            <p class="text-sm font-extrabold text-blue-700 font-mono mt-0.5"><?= $deployCount ?> Units</p>
                        </div>
                        <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200">
                            <p class="text-[10px] uppercase font-bold text-slate-500 mono">Distress SOS</p>
                            <p class="text-sm font-extrabold text-purple-700 font-mono mt-0.5"><?= $sosCount ?> Calls</p>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>

    </main>
</div>

<!-- CREATE DISASTER MODAL -->
<div id="createDisasterModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white border border-slate-200 rounded-3xl max-w-xl w-full p-6 sm:p-8 shadow-2xl relative">
        
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center">
                    <i class="fa-solid fa-triangle-exclamation text-base"></i>
                </div>
                <div>
                    <h3 class="text-lg font-extrabold text-slate-900">Declare Disaster Emergency</h3>
                    <p class="text-xs text-slate-500 font-medium">Post a live crisis zone to mobilize response units</p>
                </div>
            </div>
            <button type="button" onclick="closeCreateDisasterModal()" class="text-slate-400 hover:text-slate-800 p-2 cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="disasters.php" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="create_disaster">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Disaster Incident Title *</label>
                <input type="text" name="title" required placeholder="e.g. Category 4 Coastal Cyclone - Sector Alpha" 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-amber-600 focus:bg-white font-medium">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Incident Type *</label>
                    <select name="type" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 font-bold focus:outline-none focus:border-amber-600">
                        <option value="Flood">Flash Flood</option>
                        <option value="Cyclone">Cyclone / Hurricane</option>
                        <option value="Earthquake">Earthquake</option>
                        <option value="Wildfire">Wildfire</option>
                        <option value="Industrial Hazard">Industrial / Chemical Hazard</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Severity Level *</label>
                    <select name="severity" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 font-bold focus:outline-none focus:border-amber-600">
                        <option value="Critical">Critical (Red Alert)</option>
                        <option value="High">High Severity</option>
                        <option value="Moderate">Moderate Severity</option>
                        <option value="Low">Low / Advisory</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Affected Location / Sector *</label>
                <input type="text" name="location" required placeholder="e.g. Coastal Bay District &amp; Shoreline Towns" 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-amber-600 focus:bg-white font-medium">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Casualties Count</label>
                    <input type="number" name="casualties" min="0" value="0" 
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 font-bold focus:outline-none focus:border-amber-600 focus:bg-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Displaced Citizens</label>
                    <input type="number" name="displaced_people" min="0" value="0" 
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 font-bold focus:outline-none focus:border-amber-600 focus:bg-white">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Situation Brief &amp; Directives</label>
                <textarea name="description" rows="3" placeholder="Brief situation overview, relief requirements, evacuation orders..." 
                          class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-amber-600 focus:bg-white font-medium"></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 mt-6">
                <button type="button" onclick="closeCreateDisasterModal()" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors cursor-pointer">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#d97706] hover:bg-[#b45309] text-white text-xs font-bold shadow-sm transition-all cursor-pointer">
                    Declare Disaster
                </button>
            </div>
        </form>

    </div>
</div>

<!-- EDIT DISASTER MODAL -->
<div id="editDisasterModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white border border-slate-200 rounded-3xl max-w-xl w-full p-6 sm:p-8 shadow-2xl relative">
        
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-blue-50 text-[#1d63d8] flex items-center justify-center">
                    <i class="fa-solid fa-pen-to-square text-base"></i>
                </div>
                <div>
                    <h3 class="text-lg font-extrabold text-slate-900">Update Disaster Record</h3>
                    <p class="text-xs text-slate-500 font-medium">Modify casualty counts, severity, and status</p>
                </div>
            </div>
            <button type="button" onclick="closeEditDisasterModal()" class="text-slate-400 hover:text-slate-800 p-2 cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="disasters.php" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="update_disaster">
            <input type="hidden" name="disaster_id" id="edit_disaster_id">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Disaster Incident Title *</label>
                <input type="text" name="title" id="edit_title" required 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-[#1d63d8] focus:bg-white font-medium">
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Type</label>
                    <select name="type" id="edit_type" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 font-bold focus:outline-none focus:border-[#1d63d8]">
                        <option value="Flood">Flood</option>
                        <option value="Cyclone">Cyclone</option>
                        <option value="Earthquake">Earthquake</option>
                        <option value="Wildfire">Wildfire</option>
                        <option value="Industrial Hazard">Industrial Hazard</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Severity</label>
                    <select name="severity" id="edit_severity" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 font-bold focus:outline-none focus:border-[#1d63d8]">
                        <option value="Critical">Critical</option>
                        <option value="High">High</option>
                        <option value="Moderate">Moderate</option>
                        <option value="Low">Low</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Status</label>
                    <select name="status" id="edit_status" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 font-bold focus:outline-none focus:border-[#1d63d8]">
                        <option value="Active">Active</option>
                        <option value="Under Control">Under Control</option>
                        <option value="Resolved">Resolved</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Location *</label>
                <input type="text" name="location" id="edit_location" required 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-[#1d63d8] focus:bg-white font-medium">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Casualties</label>
                    <input type="number" name="casualties" id="edit_casualties" min="0" 
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 font-bold focus:outline-none focus:border-[#1d63d8] focus:bg-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Displaced</label>
                    <input type="number" name="displaced_people" id="edit_displaced_people" min="0" 
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 font-bold focus:outline-none focus:border-[#1d63d8] focus:bg-white">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Situation Description</label>
                <textarea name="description" id="edit_description" rows="3" 
                          class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-[#1d63d8] focus:bg-white font-medium"></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 mt-6">
                <button type="button" onclick="closeEditDisasterModal()" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors cursor-pointer">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#1d63d8] hover:bg-[#1553c7] text-white text-xs font-bold shadow-sm transition-all cursor-pointer">
                    Save Changes
                </button>
            </div>
        </form>

    </div>
</div>

<!-- ADD VOLUNTEER TASK MODAL -->
<div id="addTaskModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white border border-slate-200 rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl relative">
        
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-700 flex items-center justify-center">
                    <i class="fa-solid fa-list-check text-base"></i>
                </div>
                <div>
                    <h3 class="text-lg font-extrabold text-slate-900">Post Volunteer Task</h3>
                    <p class="text-xs text-slate-500 font-medium">Target Incident: <strong class="text-emerald-700" id="task_disaster_title"></strong></p>
                </div>
            </div>
            <button type="button" onclick="closeAddTaskModal()" class="text-slate-400 hover:text-slate-800 p-2 cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="disasters.php" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="add_volunteer_task">
            <input type="hidden" name="disaster_id" id="task_disaster_id">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Mission Title *</label>
                <input type="text" name="title" required placeholder="e.g. Distribute Clean Drinking Water &amp; Dry Rations" 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-emerald-600 focus:bg-white font-medium">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Mission Category *</label>
                    <select name="category" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 font-bold focus:outline-none focus:border-emerald-600">
                        <option value="Search & Rescue">Search &amp; Rescue Support</option>
                        <option value="Medical Aid">Medical First Aid &amp; Triage</option>
                        <option value="Food & Water">Food &amp; Water Distribution</option>
                        <option value="Shelter Management">Shelter Management</option>
                        <option value="Logistics">Logistics &amp; Transport</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Volunteers Needed *</label>
                    <input type="number" name="required_volunteers" min="1" value="5" required 
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 font-bold focus:outline-none focus:border-emerald-600 focus:bg-white">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Mission Location / Sector *</label>
                <input type="text" name="location" required placeholder="e.g. Sector 3 Community Center" 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-emerald-600 focus:bg-white font-medium">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Instructions for Volunteers</label>
                <textarea name="description" rows="2" placeholder="Tasks to perform, assembly point, protective gear required..." 
                          class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-emerald-600 focus:bg-white font-medium"></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 mt-6">
                <button type="button" onclick="closeAddTaskModal()" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors cursor-pointer">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#16a34a] hover:bg-[#15803d] text-white text-xs font-bold shadow-sm transition-all cursor-pointer">
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
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, delete record',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('delete_disaster_id').value = disasterId;
            document.getElementById('deleteDisasterForm').submit();
        }
    });
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
