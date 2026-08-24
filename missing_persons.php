<?php
// missing_persons.php - Missing Persons Registry & Tracking Desk (Government Theme)
define('PAGE_TITLE', 'Missing Persons Registry');
require_once __DIR__ . '/auth.php';

requireLogin();
$currentUser = getCurrentUser($pdo);
if (!hasPermission($currentUser, 'access_missing_persons') && !hasPermission($currentUser, 'access_police')) {
    setFlash('error', 'Access Denied: You do not have permission to access the Missing Persons Registry.');
    header("Location: dashboard.php");
    exit;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrf)) {
        setFlash('error', 'Invalid security token.');
        header("Location: missing_persons.php");
        exit;
    }

    // 1. REPORT MISSING PERSON
    if ($action === 'create_missing') {
        $disasterId = 1;
        $fullName = trim($_POST['full_name'] ?? '');
        $age = (int) ($_POST['age'] ?? 0);
        $gender = $_POST['gender'] ?? 'Other';
        $location = trim($_POST['last_seen_location'] ?? '');
        $reportedBy = trim($_POST['reported_by'] ?? '');
        $phone = trim($_POST['contact_phone'] ?? '');
        $photo = trim($_POST['photo'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if (!empty($fullName) && !empty($location) && !empty($reportedBy)) {
            if (empty($photo)) {
                $photo = 'https://ui-avatars.com/api/?name=' . urlencode($fullName) . '&background=dc2626&color=fff';
            }
            $stmt = $pdo->prepare("INSERT INTO missing_persons (disaster_id, full_name, age, gender, last_seen_location, reported_by, contact_phone, photo, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Missing', ?)");
            $stmt->execute([$disasterId, $fullName, $age, $gender, $location, $reportedBy, $phone, $photo, $notes]);

            logActivity($pdo, 'REPORT_MISSING_PERSON', "Registered missing person '{$fullName}' (Age: {$age}) at {$location}");
            setFlash('success', "Report registered for '{$fullName}'. Field search units notified.");
        } else {
            setFlash('error', 'Please complete all required fields.');
        }
        header("Location: missing_persons.php");
        exit;
    }

    // 2. UPDATE STATUS
    if ($action === 'update_status') {
        $personId = (int) ($_POST['person_id'] ?? 0);
        $newStatus = $_POST['status'] ?? 'Missing';
        $notes = trim($_POST['notes'] ?? '');

        $stmt = $pdo->prepare("UPDATE missing_persons SET status = ?, notes = ? WHERE id = ?");
        $stmt->execute([$newStatus, $notes, $personId]);

        logActivity($pdo, 'UPDATE_MISSING_STATUS', "Updated missing person #{$personId} status to {$newStatus}");
        setFlash('success', "Status updated to {$newStatus}.");
        header("Location: missing_persons.php");
        exit;
    }

    // 3. DELETE RECORD
    if ($action === 'delete_record') {
        $personId = (int) ($_POST['person_id'] ?? 0);
        $pdo->prepare("DELETE FROM missing_persons WHERE id = ?")->execute([$personId]);
        logActivity($pdo, 'DELETE_MISSING_RECORD', "Deleted missing person record #{$personId}");
        setFlash('success', 'Missing person case record removed.');
        header("Location: missing_persons.php");
        exit;
    }
}

// Queries
$search = trim($_GET['search'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');

$query = "
    SELECT mp.* 
    FROM missing_persons mp 
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $query .= " AND (mp.full_name LIKE ? OR mp.last_seen_location LIKE ? OR mp.reported_by LIKE ? OR mp.contact_phone LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if (!empty($filterStatus)) {
    $query .= " AND mp.status = ?";
    $params[] = $filterStatus;
}

$query .= " ORDER BY mp.id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$persons = $stmt->fetchAll();

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
                    <h2 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">Missing Persons Search Desk</h2>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-50 text-red-700 border border-red-200 mono">
                        <?= count($persons) ?> Registered Cases
                    </span>
                </div>
                <p class="text-xs text-slate-500 font-medium mt-1">Multi-agency search database, last-known locations, and family contact registries</p>
            </div>
            <button type="button" onclick="openCreateMissingModal()" class="px-4 py-2.5 rounded-2xl bg-[#dc2626] hover:bg-[#b91c1c] text-white text-xs font-bold transition-all shadow-sm flex items-center justify-center gap-2 cursor-pointer">
                <i class="fa-solid fa-person-circle-plus text-xs"></i>
                <span>Register Missing Citizen</span>
            </button>
        </section>

        <!-- Filter Bar -->
        <div class="bg-white p-4 rounded-3xl border border-slate-200 shadow-xs">
            <form method="GET" action="missing_persons.php" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
                <div class="sm:col-span-6 relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <i class="fa-solid fa-magnifying-glass text-xs"></i>
                    </div>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name, location, contact..." 
                           class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-red-600 focus:bg-white transition-colors font-medium">
                </div>

                <div class="sm:col-span-4">
                    <select name="status" class="w-full py-2 px-3 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 font-bold focus:outline-none focus:border-red-600">
                        <option value="">All Statuses</option>
                        <option value="Missing" <?= $filterStatus === 'Missing' ? 'selected' : '' ?>>Missing (Active Search)</option>
                        <option value="Located" <?= $filterStatus === 'Located' ? 'selected' : '' ?>>Located</option>
                        <option value="Rescued" <?= $filterStatus === 'Rescued' ? 'selected' : '' ?>>Rescued / Safe</option>
                        <option value="Medical Care" <?= $filterStatus === 'Medical Care' ? 'selected' : '' ?>>In Medical Care</option>
                    </select>
                </div>

                <div class="sm:col-span-2 flex items-center gap-2">
                    <button type="submit" class="w-full py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold rounded-xl border border-slate-200 transition-colors flex items-center justify-center gap-1.5 cursor-pointer">
                        <i class="fa-solid fa-filter text-[10px]"></i>
                        <span>Filter</span>
                    </button>
                    <?php if (!empty($search) || !empty($filterStatus)): ?>
                        <a href="missing_persons.php" class="p-2 bg-slate-100 hover:bg-red-50 text-slate-600 hover:text-red-600 rounded-xl border border-slate-200 transition-colors" title="Clear Filters">
                            <i class="fa-solid fa-rotate-left text-xs"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Missing Persons Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($persons as $p): ?>
                <?php
                    $isMissing = ($p['status'] === 'Missing');
                    $statusBadge = match($p['status']) {
                        'Missing' => 'bg-red-50 text-red-800 border-red-200',
                        'Located' => 'bg-amber-50 text-amber-800 border-amber-200',
                        'Rescued' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                        'Medical Care' => 'bg-teal-50 text-teal-800 border-teal-200',
                        default => 'bg-slate-100 text-slate-800 border-slate-200'
                    };
                ?>
                <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs flex flex-col justify-between space-y-4 hover:border-slate-300 hover:shadow-sm transition-all">
                    <div>
                        <div class="flex items-start gap-3.5 mb-3">
                            <img src="<?= htmlspecialchars($p['photo'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($p['full_name']) . '&background=dc2626&color=fff') ?>" 
                                 alt="Photo" class="w-14 h-14 rounded-2xl object-cover ring-2 ring-slate-100 shrink-0">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-1">
                                    <h4 class="text-sm font-extrabold text-slate-900 truncate"><?= htmlspecialchars($p['full_name']) ?></h4>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border shrink-0 <?= $statusBadge ?> mono">
                                        <?= htmlspecialchars($p['status']) ?>
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-500 font-medium mt-0.5"><?= $p['age'] ? $p['age'] . ' years old' : 'Age unknown' ?> &bull; <?= htmlspecialchars($p['gender']) ?></p>
                                <p class="text-[10px] text-slate-400 font-mono mt-0.5">Reported: <?= date('M d, H:i', strtotime($p['reported_at'])) ?></p>
                            </div>
                        </div>

                        <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 space-y-1.5 text-xs text-slate-700 font-medium">
                            <p class="flex items-start gap-2">
                                <i class="fa-solid fa-location-dot text-red-500 text-xs w-4 mt-0.5 shrink-0"></i>
                                <span class="font-bold text-red-700"><?= htmlspecialchars($p['last_seen_location']) ?></span>
                            </p>
                            <p class="flex items-start gap-2 text-slate-600">
                                <i class="fa-solid fa-user-group text-slate-400 text-xs w-4 mt-0.5 shrink-0"></i>
                                <span>Kin: <strong class="text-slate-900"><?= htmlspecialchars($p['reported_by']) ?></strong> (<?= htmlspecialchars($p['contact_phone']) ?>)</span>
                            </p>
                            <?php if (!empty($p['notes'])): ?>
                                <p class="flex items-start gap-2 text-slate-500 text-[11px] pt-1.5 border-t border-slate-200">
                                    <i class="fa-solid fa-note-sticky text-amber-500 text-xs w-4 mt-0.5 shrink-0"></i>
                                    <span class="italic"><?= htmlspecialchars($p['notes']) ?></span>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                        <button type="button" 
                                onclick='openUpdateStatusModal(<?= json_encode($p, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                class="px-3.5 py-1.5 rounded-xl bg-blue-50 hover:bg-blue-100 text-[#1d63d8] hover:text-[#1553c7] text-xs font-bold border border-blue-200 transition-all flex items-center gap-1.5 cursor-pointer">
                            <i class="fa-solid fa-pen-to-square text-[10px]"></i>
                            <span>Update Status</span>
                        </button>

                        <form method="POST" action="missing_persons.php" class="inline" onsubmit="return confirm('Delete this missing person record?');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="action" value="delete_record">
                            <input type="hidden" name="person_id" value="<?= $p['id'] ?>">
                            <button type="submit" class="p-1.5 text-slate-400 hover:text-red-600 transition-colors cursor-pointer" title="Delete">
                                <i class="fa-solid fa-trash-can text-xs"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </main>
</div>

<!-- REGISTER MISSING PERSON MODAL -->
<div id="createMissingModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white border border-slate-200 rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl relative">
        
        <div class="flex items-center justify-between pb-4 mb-5 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-red-50 text-[#dc2626] flex items-center justify-center">
                    <i class="fa-solid fa-person-circle-plus text-base"></i>
                </div>
                <div>
                    <h3 class="text-lg font-extrabold text-slate-900">Register Missing Citizen</h3>
                    <p class="text-xs text-slate-500 font-medium">File missing case to notify police &amp; volunteer search teams</p>
                </div>
            </div>
            <button type="button" onclick="closeCreateMissingModal()" class="text-slate-400 hover:text-slate-800 p-2 cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="missing_persons.php" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="create_missing">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Missing Person Full Name *</label>
                <input type="text" name="full_name" required placeholder="e.g. Arthur Pendelton" 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-red-600 focus:bg-white font-medium">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Age</label>
                    <input type="number" name="age" min="1" max="120" placeholder="e.g. 68" 
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:outline-none focus:border-red-600 focus:bg-white font-bold">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Gender</label>
                    <select name="gender" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 font-bold focus:outline-none focus:border-red-600">
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Child">Child</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Last Seen Location *</label>
                <input type="text" name="last_seen_location" required placeholder="e.g. Shoreline Fishermans Wharf near Jetty 2" 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-red-600 focus:bg-white font-medium">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Reported By (Kin) *</label>
                    <input type="text" name="reported_by" required placeholder="e.g. Martha Pendelton (Daughter)" 
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:outline-none focus:border-red-600 focus:bg-white font-medium">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5 mono">Contact Phone *</label>
                    <input type="text" name="contact_phone" required placeholder="+91 98765 43210" 
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 font-mono focus:outline-none focus:border-red-600 focus:bg-white font-medium">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Photo URL (Optional)</label>
                <input type="url" name="photo" placeholder="https://example.com/photo.jpg" 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-red-600 focus:bg-white font-medium">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Identifying Features / Medical Notes</label>
                <textarea name="notes" rows="2" placeholder="Clothing worn, physical marks, medical needs..." 
                          class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-red-600 focus:bg-white font-medium"></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 mt-6">
                <button type="button" onclick="closeCreateMissingModal()" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors cursor-pointer">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#dc2626] hover:bg-[#b91c1c] text-white text-xs font-bold shadow-sm transition-all cursor-pointer">
                    Register Case
                </button>
            </div>
        </form>

    </div>
</div>

<!-- UPDATE CASE STATUS MODAL -->
<div id="updateStatusModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white border border-slate-200 rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl relative">
        
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-100">
            <h3 class="text-base font-extrabold text-slate-900">Update Case Status</h3>
            <button type="button" onclick="closeUpdateStatusModal()" class="text-slate-400 hover:text-slate-800 p-2 cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="missing_persons.php" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="person_id" id="status_person_id">

            <p class="text-xs text-slate-500 font-medium">Target Citizen: <strong class="text-slate-900" id="status_person_name"></strong></p>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">New Case Status *</label>
                <select name="status" id="status_select" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 font-bold focus:outline-none focus:border-[#1d63d8]">
                    <option value="Missing">Missing (Active Search)</option>
                    <option value="Located">Located (Awaiting Transport)</option>
                    <option value="Rescued">Rescued &amp; Safe at Shelter</option>
                    <option value="Medical Care">Admitted to Hospital / Medical Tent</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Status Update Notes</label>
                <textarea name="notes" id="status_notes" rows="2" placeholder="Found location, condition, attending paramedic..." 
                          class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-[#1d63d8] font-medium"></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeUpdateStatusModal()" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors cursor-pointer">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#1d63d8] hover:bg-[#1553c7] text-white text-xs font-bold transition-all shadow-sm cursor-pointer">
                    Save Status
                </button>
            </div>
        </form>

    </div>
</div>

<script>
function openCreateMissingModal() {
    document.getElementById('createMissingModal').classList.remove('hidden');
}
function closeCreateMissingModal() {
    document.getElementById('createMissingModal').classList.add('hidden');
}

function openUpdateStatusModal(p) {
    document.getElementById('status_person_id').value = p.id;
    document.getElementById('status_person_name').textContent = p.full_name;
    document.getElementById('status_select').value = p.status || 'Missing';
    document.getElementById('status_notes').value = p.notes || '';
    document.getElementById('updateStatusModal').classList.remove('hidden');
}
function closeUpdateStatusModal() {
    document.getElementById('updateStatusModal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
