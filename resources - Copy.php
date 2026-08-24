<?php
// resources.php - DisasterSafe Universal Resources & Field Distribution Hub
define('PAGE_TITLE', 'Resources');
require_once __DIR__ . '/auth.php';

requireLogin();
$currentUser = getCurrentUser($pdo);

// Role & Permission Checks
$isSuperAdmin = isSuperAdmin($currentUser);
$hasVolunteerAccess = hasPermission($currentUser, 'access_volunteer');
$hasDisasterAccess = hasPermission($currentUser, 'access_disasters');
if (!$isSuperAdmin && !$hasVolunteerAccess && !$hasDisasterAccess) {
    setFlash('error', 'Access denied. You do not have permission to view Resources.');
    header("Location: dashboard.php");
    exit;
}

$csrfToken = generateCsrfToken();

// Handle POST Requests (Dispatch / Allocate Resource Distribution)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token. Please refresh and retry.');
        header("Location: resources.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    // 1. DISPATCH / DISTRIBUTE RESOURCE
    if ($action === 'dispatch_distribution') {
        $resourceId = (int)($_POST['resource_id'] ?? 0);
        $destinationType = trim($_POST['destination_type'] ?? 'Relief Camp');
        $destinationName = trim($_POST['destination_name'] ?? '');
        $locationAddress = trim($_POST['location_address'] ?? '');
        $gpsLat = (float)($_POST['gps_lat'] ?? 28.6139);
        $gpsLng = (float)($_POST['gps_lng'] ?? 77.2090);
        $quantity = (int)($_POST['quantity'] ?? 0);
        $contactOfficer = trim($_POST['contact_officer'] ?? 'On-site Coordinator');
        $notes = trim($_POST['notes'] ?? '');

        // Fetch resource
        $stmt = $pdo->prepare("SELECT * FROM master_resources WHERE id = ?");
        $stmt->execute([$resourceId]);
        $resource = $stmt->fetch();

        if ($resource && $quantity > 0 && $quantity <= $resource['available_stock']) {
            $unit = $resource['unit'];
            $dispatchedBy = $currentUser['name'] ?? 'Superadmin Tactical Command';

            // Insert into resource_distributions
            $insStmt = $pdo->prepare("INSERT INTO resource_distributions (resource_id, destination_type, destination_name, location_address, gps_lat, gps_lng, quantity_distributed, unit, dispatched_by, contact_officer, distribution_status, notes) VALUES (:resource_id, :destination_type, :destination_name, :location_address, :gps_lat, :gps_lng, :quantity_distributed, :unit, :dispatched_by, :contact_officer, 'Delivered / On-Site', :notes)");
            $insStmt->execute([
                ':resource_id' => $resourceId,
                ':destination_type' => $destinationType,
                ':destination_name' => $destinationName,
                ':location_address' => $locationAddress,
                ':gps_lat' => $gpsLat,
                ':gps_lng' => $gpsLng,
                ':quantity_distributed' => $quantity,
                ':unit' => $unit,
                ':dispatched_by' => $dispatchedBy,
                ':contact_officer' => $contactOfficer,
                ':notes' => $notes
            ]);

            // Update master_resources stock
            $newAvailable = $resource['available_stock'] - $quantity;
            $newDistributed = $resource['distributed_stock'] + $quantity;
            $newStatus = ($newAvailable === 0) ? 'Critical Depleted' : (($newAvailable < ($resource['total_stock'] * 0.2)) ? 'Low Stock' : 'In Stock');

            $updStmt = $pdo->prepare("UPDATE master_resources SET available_stock = ?, distributed_stock = ?, status = ? WHERE id = ?");
            $updStmt->execute([$newAvailable, $newDistributed, $newStatus, $resourceId]);

            logActivity($pdo, 'RESOURCE_DISTRIBUTED', "Dispatched {$quantity} {$unit} of {$resource['name']} to {$destinationName}");
            setFlash('success', "Dispatched {$quantity} {$unit} of {$resource['name']} to {$destinationName} successfully.");
        } else {
            setFlash('error', 'Invalid dispatch quantity or insufficient available stock in warehouse.');
        }
        header("Location: resources.php");
        exit;
    }

    // 2. ADD NEW RESOURCE ITEM
    if ($action === 'add_resource') {
        $code = trim($_POST['resource_code'] ?? 'RES-' . strtoupper(bin2hex(random_bytes(2))));
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? 'Food & Water');
        $totalStock = (int)($_POST['total_stock'] ?? 0);
        $unit = trim($_POST['unit'] ?? 'units');
        $warehouse = trim($_POST['primary_warehouse'] ?? 'Delhi Central Disaster Base');
        $notes = trim($_POST['notes'] ?? '');

        if ($name && $totalStock > 0) {
            $ins = $pdo->prepare("INSERT INTO master_resources (resource_code, name, category, total_stock, available_stock, distributed_stock, unit, primary_warehouse, status, icon, color, notes) VALUES (?, ?, ?, ?, ?, 0, ?, ?, 'In Stock', 'fa-box', 'indigo', ?)");
            $ins->execute([$code, $name, $category, $totalStock, $totalStock, $unit, $warehouse, $notes]);
            logActivity($pdo, 'RESOURCE_CREATED', "Added new master resource: {$name} ({$code})");
            setFlash('success', "Resource '{$name}' added to inventory catalog.");
        } else {
            setFlash('error', 'Please enter a valid Resource Name and Initial Stock.');
        }
        header("Location: resources.php");
        exit;
    }
}

// Fetch all resources with their distribution sites count
$resources = $pdo->query("
    SELECT r.*, 
           COUNT(d.id) as distribution_sites_count,
           COALESCE(SUM(d.quantity_distributed), 0) as verified_distributed_sum
    FROM master_resources r
    LEFT JOIN resource_distributions d ON r.id = d.resource_id
    GROUP BY r.id
    ORDER BY r.id ASC
")->fetchAll();

// Fetch all distribution records indexed by resource_id
$allDistributions = $pdo->query("SELECT * FROM resource_distributions ORDER BY id DESC")->fetchAll();
$distributionsByResource = [];
foreach ($allDistributions as $dist) {
    $distributionsByResource[$dist['resource_id']][] = $dist;
}

// Calculate Summary KPI Metrics
$totalResourceCount = count($resources);
$totalStockSum = array_sum(array_column($resources, 'total_stock'));
$totalAvailableSum = array_sum(array_column($resources, 'available_stock'));
$totalDistributedSum = array_sum(array_column($resources, 'distributed_stock'));
$lowStockCount = count(array_filter($resources, fn($r) => $r['status'] !== 'In Stock'));

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div class="flex-1 flex flex-col min-w-0 bg-[#0a0f1d] min-h-screen overflow-y-auto">
    <?php require_once __DIR__ . '/navbar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto w-full">

        <!-- HEADER BANNER & COMMAND CONTROLS -->
        <section class="glass-panel p-5 sm:p-6 rounded-2xl border border-[#243049] relative overflow-hidden shadow-2xl">
            <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-indigo-600/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 relative z-10">
                <div class="space-y-1.5">
                    <div class="flex items-center gap-2.5">
                        <span class="w-8 h-8 rounded-xl bg-indigo-600/20 border border-indigo-500/30 flex items-center justify-center text-indigo-400 font-black text-base shadow-inner">
                            📦
                        </span>
                        <div>
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-indigo-400">Universal Area Supply Chain</span>
                            <h2 class="text-xl sm:text-2xl font-black text-white tracking-tight">Area Disaster Resources & Distribution Hub</h2>
                        </div>
                    </div>
                    <p class="text-xs text-slate-300 max-w-2xl leading-relaxed">
                        Comprehensive monitor for all disaster resources available across Delhi-NCR. Click on any resource item to track exactly where its supplies have been distributed on the ground.
                    </p>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-wrap items-center gap-3 shrink-0">
                    <button type="button" onclick="openDispatchModal()" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-lg shadow-indigo-600/30 transition-all flex items-center gap-2">
                        <i class="fa-solid fa-truck-ramp-box"></i> Dispatch Resource
                    </button>
                    <button type="button" onclick="document.getElementById('addResourceModal').classList.remove('hidden')" class="px-4 py-2.5 rounded-xl bg-[#11192e] hover:bg-slate-800 border border-[#243049] text-slate-200 font-bold text-xs transition-all flex items-center gap-2">
                        <i class="fa-solid fa-plus text-indigo-400"></i> Add Stock Item
                    </button>
                </div>
            </div>
        </section>

        <!-- KPI METRICS HUD -->
        <section class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="stat-card-accent p-3.5 rounded-xl border-t-2 border-t-indigo-500 shadow-md flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Catalog Items</p>
                    <h3 class="text-2xl font-extrabold text-white mt-0.5"><?= $totalResourceCount ?></h3>
                    <span class="text-[10px] font-semibold text-indigo-400">6 Specialized Categories</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400 text-sm">
                    <i class="fa-solid fa-boxes-stacked"></i>
                </div>
            </div>

            <div class="stat-card-accent p-3.5 rounded-xl border-t-2 border-t-emerald-500 shadow-md flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Warehouse Stock</p>
                    <h3 class="text-2xl font-extrabold text-emerald-400 mt-0.5"><?= number_format($totalAvailableSum) ?></h3>
                    <span class="text-[10px] font-semibold text-emerald-300">Ready for Deployment</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 text-sm">
                    <i class="fa-solid fa-warehouse"></i>
                </div>
            </div>

            <div class="stat-card-accent p-3.5 rounded-xl border-t-2 border-t-blue-500 shadow-md flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Distributed to Field</p>
                    <h3 class="text-2xl font-extrabold text-blue-400 mt-0.5"><?= number_format($totalDistributedSum) ?></h3>
                    <span class="text-[10px] font-semibold text-blue-300">Active at Camps & Hospitals</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-400 text-sm">
                    <i class="fa-solid fa-truck-fast"></i>
                </div>
            </div>

            <div class="stat-card-accent p-3.5 rounded-xl border-t-2 border-t-rose-500 shadow-md flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Stock Health</p>
                    <h3 class="text-2xl font-extrabold <?= $lowStockCount > 0 ? 'text-amber-400' : 'text-emerald-400' ?> mt-0.5">
                        <?= $lowStockCount > 0 ? $lowStockCount . ' Depleting' : '100% Stable' ?>
                    </h3>
                    <span class="text-[10px] font-semibold text-slate-400">Total <?= number_format($totalStockSum) ?> Units Managed</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400 text-sm">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
            </div>
        </section>

        <!-- CATEGORY FILTER CHIPS & SEARCH BAR -->
        <section class="flex flex-col md:flex-row md:items-center justify-between gap-3 bg-[#0c1326] p-3 rounded-2xl border border-[#243049]">
            <!-- Category Chips -->
            <div class="flex items-center gap-1.5 overflow-x-auto py-1 text-xs">
                <button type="button" onclick="filterByCategory('all', this)" class="category-chip px-3 py-1.5 rounded-xl bg-indigo-600 text-white font-bold whitespace-nowrap shadow-sm transition-all">
                    🌟 All Resources (<?= count($resources) ?>)
                </button>
                <button type="button" onclick="filterByCategory('Food & Water', this)" class="category-chip px-3 py-1.5 rounded-xl bg-[#11192e] text-slate-300 hover:bg-slate-800 font-semibold whitespace-nowrap border border-[#243049] transition-all">
                    🥪 Food & Water
                </button>
                <button type="button" onclick="filterByCategory('Medical Supplies', this)" class="category-chip px-3 py-1.5 rounded-xl bg-[#11192e] text-slate-300 hover:bg-slate-800 font-semibold whitespace-nowrap border border-[#243049] transition-all">
                    💉 Medical & Blood
                </button>
                <button type="button" onclick="filterByCategory('Power & Energy', this)" class="category-chip px-3 py-1.5 rounded-xl bg-[#11192e] text-slate-300 hover:bg-slate-800 font-semibold whitespace-nowrap border border-[#243049] transition-all">
                    ⚡ Power & Generators
                </button>
                <button type="button" onclick="filterByCategory('Vehicles & Mobility', this)" class="category-chip px-3 py-1.5 rounded-xl bg-[#11192e] text-slate-300 hover:bg-slate-800 font-semibold whitespace-nowrap border border-[#243049] transition-all">
                    🚒 Vehicles & Boats
                </button>
                <button type="button" onclick="filterByCategory('Shelter & Bedding', this)" class="category-chip px-3 py-1.5 rounded-xl bg-[#11192e] text-slate-300 hover:bg-slate-800 font-semibold whitespace-nowrap border border-[#243049] transition-all">
                    🏕️ Shelter & Tents
                </button>
                <button type="button" onclick="filterByCategory('Tactical & Rescue Gear', this)" class="category-chip px-3 py-1.5 rounded-xl bg-[#11192e] text-slate-300 hover:bg-slate-800 font-semibold whitespace-nowrap border border-[#243049] transition-all">
                    🦺 Tactical & Gear
                </button>
            </div>

            <!-- Instant Search -->
            <div class="relative min-w-[240px]">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3 text-slate-400 text-xs"></i>
                <input type="text" id="resourceSearchInput" onkeyup="filterBySearch()" placeholder="Search resources, code, or warehouse..." class="w-full pl-9 pr-4 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
            </div>
        </section>

        <!-- MASTER RESOURCES CATALOG GRID -->
        <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="resourcesGrid">
            <?php foreach ($resources as $res): ?>
                <?php 
                    $percentDistributed = $res['total_stock'] > 0 ? round(($res['distributed_stock'] / $res['total_stock']) * 100) : 0;
                    $statusBadge = match($res['status']) {
                        'In Stock' => 'bg-emerald-950 text-emerald-300 border-emerald-800',
                        'Low Stock' => 'bg-amber-950 text-amber-300 border-amber-800',
                        default => 'bg-rose-950 text-rose-300 border-rose-800'
                    };
                    $categoryColor = match($res['category']) {
                        'Food & Water' => 'border-l-blue-500',
                        'Medical Supplies' => 'border-l-rose-500',
                        'Power & Energy' => 'border-l-yellow-500',
                        'Vehicles & Mobility' => 'border-l-teal-500',
                        'Shelter & Bedding' => 'border-l-emerald-500',
                        default => 'border-l-indigo-500'
                    };
                ?>
                <div class="resource-card glass-panel p-4 rounded-2xl border border-[#243049] border-l-4 <?= $categoryColor ?> hover:border-slate-400 transition-all cursor-pointer shadow-xl flex flex-col justify-between group"
                     data-category="<?= htmlspecialchars($res['category']) ?>"
                     data-search="<?= htmlspecialchars(strtolower($res['name'] . ' ' . $res['resource_code'] . ' ' . $res['primary_warehouse'] . ' ' . $res['category'])) ?>"
                     onclick="openDistributionDossier(<?= htmlspecialchars(json_encode($res)) ?>, <?= htmlspecialchars(json_encode($distributionsByResource[$res['id']] ?? [])) ?>)">
                    
                    <div class="space-y-3">
                        <!-- Top Code & Status -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded-lg bg-[#11192e] border border-[#243049] text-[10px] font-mono font-bold text-slate-300">
                                    <?= htmlspecialchars($res['resource_code']) ?>
                                </span>
                                <span class="text-[10px] font-bold text-slate-400"><?= htmlspecialchars($res['category']) ?></span>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold border <?= $statusBadge ?>">
                                <?= htmlspecialchars($res['status']) ?>
                            </span>
                        </div>

                        <!-- Resource Title & Warehouse -->
                        <div>
                            <h3 class="text-sm font-extrabold text-white group-hover:text-indigo-400 transition-colors leading-snug">
                                <?= htmlspecialchars($res['name']) ?>
                            </h3>
                            <p class="text-[11px] text-slate-400 mt-1 flex items-center gap-1.5">
                                <i class="fa-solid fa-warehouse text-slate-500 text-[10px]"></i>
                                <span class="truncate"><?= htmlspecialchars($res['primary_warehouse']) ?></span>
                            </p>
                        </div>

                        <!-- Stock Progress Bar -->
                        <div class="space-y-1.5 pt-1">
                            <div class="flex items-center justify-between text-xs font-semibold">
                                <span class="text-slate-300">Available: <strong class="text-emerald-400 font-extrabold"><?= number_format($res['available_stock']) ?> <?= htmlspecialchars($res['unit']) ?></strong></span>
                                <span class="text-slate-400 text-[11px]">Total: <?= number_format($res['total_stock']) ?></span>
                            </div>
                            <div class="w-full bg-[#11192e] rounded-full h-2 overflow-hidden border border-[#243049]/50">
                                <div class="bg-gradient-to-r from-blue-500 to-indigo-500 h-2 rounded-full transition-all" style="width: <?= $percentDistributed ?>%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Distribution Action Banner (Clickable Callout) -->
                    <div class="mt-4 pt-3 border-t border-[#243049] flex items-center justify-between text-xs">
                        <span class="text-blue-400 font-bold flex items-center gap-1.5 text-[11px]">
                            <i class="fa-solid fa-truck-ramp-box"></i>
                            <span><?= number_format($res['distributed_stock']) ?> <?= htmlspecialchars($res['unit']) ?> in <?= $res['distribution_sites_count'] ?> Sites</span>
                        </span>
                        <span class="text-indigo-400 font-bold group-hover:translate-x-1 transition-transform text-[11px] flex items-center gap-1">
                            <span>View Distribution</span>
                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </span>
                    </div>

                </div>
            <?php endforeach; ?>
        </section>

    </main>
</div>

<!-- ========================================================================= -->
<!-- LIVE RESOURCE DISTRIBUTION DOSSIER MODAL (OPENS ON CLICKING ANY RESOURCE) -->
<!-- ========================================================================= -->
<div id="distributionDossierModal" class="fixed inset-0 bg-[#060a14]/85 backdrop-blur-md z-50 flex items-center justify-center p-3 sm:p-6 hidden">
    <div class="bg-[#0f172a] border border-[#243049] rounded-2xl w-full max-w-4xl max-h-[90vh] shadow-2xl flex flex-col overflow-hidden">
        
        <!-- Modal Header -->
        <div class="h-16 px-6 bg-[#0c1326] border-b border-[#243049] flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-indigo-600/20 border border-indigo-500/30 flex items-center justify-center text-indigo-400 font-bold text-base">
                    <i class="fa-solid fa-truck-fast"></i>
                </div>
                <div>
                    <h3 class="text-sm sm:text-base font-extrabold text-white tracking-tight" id="modalResourceName">
                        Resource Distribution Tracking
                    </h3>
                    <p class="text-[11px] text-slate-400" id="modalResourceMeta">
                        Live Field Deployment Sites & Recipient Centers
                    </p>
                </div>
            </div>
            <button type="button" onclick="closeDistributionModal()" class="text-slate-400 hover:text-white p-2 rounded-lg hover:bg-slate-800 transition-colors">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <!-- Modal Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-6 space-y-6">
            
            <!-- Summary Stats Banner for Selected Resource -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-[#0c1326] p-4 rounded-xl border border-[#243049]">
                <div>
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Total Area Stock</span>
                    <strong class="text-lg font-extrabold text-white" id="modalTotalStock">0</strong>
                </div>
                <div>
                    <span class="text-[10px] uppercase font-bold text-emerald-400 block">Warehouse Available</span>
                    <strong class="text-lg font-extrabold text-emerald-400" id="modalAvailStock">0</strong>
                </div>
                <div>
                    <span class="text-[10px] uppercase font-bold text-blue-400 block">Deployed to Field</span>
                    <strong class="text-lg font-extrabold text-blue-400" id="modalDistStock">0</strong>
                </div>
                <div>
                    <span class="text-[10px] uppercase font-bold text-amber-400 block">Distribution Sites</span>
                    <strong class="text-lg font-extrabold text-amber-400" id="modalSitesCount">0</strong>
                </div>
            </div>

            <!-- Interactive Distribution Sites Radar Map -->
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-bold text-white uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-map-location-dot text-indigo-400"></i>
                        <span>Geospatial Distribution Radar</span>
                    </h4>
                    <span class="text-[10px] text-slate-400">Locations receiving this specific supply</span>
                </div>
                <div id="distributionMiniMap" class="w-full h-52 rounded-xl border border-[#243049] overflow-hidden"></div>
            </div>

            <!-- Distribution Sites Ledger Table / Cards -->
            <div class="space-y-3">
                <h4 class="text-xs font-bold text-white uppercase tracking-wider flex items-center gap-1.5">
                    <i class="fa-solid fa-list-check text-indigo-400"></i>
                    <span>Field Distribution Logs & Recipients</span>
                </h4>

                <div id="distributionSitesList" class="space-y-2.5">
                    <!-- Populated dynamically via JS -->
                </div>
            </div>

            <!-- Inline Quick Additional Dispatch Form -->
            <div class="bg-[#0c1326] p-4 rounded-xl border border-indigo-500/30 space-y-3">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-bold text-white uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-paper-plane text-indigo-400"></i>
                        <span>Dispatch Additional Stock from Warehouse</span>
                    </h4>
                </div>
                <form method="POST" action="resources.php" class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="dispatch_distribution">
                    <input type="hidden" name="resource_id" id="inlineFormResourceId" value="">

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Destination Camp / Hospital *</label>
                        <input type="text" name="destination_name" required placeholder="e.g. Mayur Vihar Tent City" class="w-full px-3 py-1.5 bg-[#11192e] border border-[#243049] rounded-lg text-white">
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Destination Type</label>
                        <select name="destination_type" class="w-full px-3 py-1.5 bg-[#11192e] border border-[#243049] rounded-lg text-white font-semibold">
                            <option value="Relief Camp">🏕️ Relief Camp</option>
                            <option value="Hospital">🏥 Hospital</option>
                            <option value="Field Clinic">🩺 Field Clinic</option>
                            <option value="Police Cordon">🚓 Police Cordon</option>
                            <option value="Fire Unit">🚒 Fire Unit</option>
                            <option value="Disaster Evacuation Zone">🌊 Evacuation Zone</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Quantity to Deploy *</label>
                        <div class="flex items-center gap-2">
                            <input type="number" name="quantity" min="1" required placeholder="Qty" class="w-24 px-3 py-1.5 bg-[#11192e] border border-[#243049] rounded-lg text-white text-center font-bold">
                            <button type="submit" class="flex-1 px-4 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-bold shadow-md transition-all">
                                Deploy Now
                            </button>
                        </div>
                    </div>
                </form>
            </div>

        </div>

    </div>
</div>

<!-- ========================================================================= -->
<!-- GLOBAL DISPATCH RESOURCE MODAL -->
<!-- ========================================================================= -->
<div id="globalDispatchModal" class="fixed inset-0 bg-[#060a14]/85 backdrop-blur-md z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-[#0f172a] border border-[#243049] rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden">
        <div class="h-14 px-6 bg-[#0c1326] border-b border-[#243049] flex items-center justify-between">
            <h3 class="text-sm font-extrabold text-white flex items-center gap-2">
                <i class="fa-solid fa-truck-ramp-box text-indigo-400"></i> Dispatch Resource to Field Site
            </h3>
            <button type="button" onclick="document.getElementById('globalDispatchModal').classList.add('hidden')" class="text-slate-400 hover:text-white">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <form method="POST" action="resources.php" class="p-6 space-y-3.5 text-xs">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="dispatch_distribution">

            <div>
                <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Select Resource Item *</label>
                <select name="resource_id" required class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white font-semibold">
                    <?php foreach ($resources as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= $r['available_stock'] <= 0 ? 'disabled' : '' ?>>
                            [<?= $r['resource_code'] ?>] <?= htmlspecialchars($r['name']) ?> (<?= $r['available_stock'] ?> <?= $r['unit'] ?> in Stock)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Destination Type *</label>
                    <select name="destination_type" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white font-semibold">
                        <option value="Relief Camp">🏕️ Relief Camp</option>
                        <option value="Hospital">🏥 Hospital</option>
                        <option value="Field Clinic">🩺 Field Clinic</option>
                        <option value="Police Cordon">🚓 Police Cordon</option>
                        <option value="Fire Unit">🚒 Fire Unit</option>
                        <option value="Disaster Evacuation Zone">🌊 Evacuation Zone</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Dispatch Quantity *</label>
                    <input type="number" name="quantity" min="1" required value="50" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white font-bold text-center">
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Destination Name *</label>
                <input type="text" name="destination_name" required placeholder="e.g. Mayur Vihar Flood Relief Tent City" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Location Address & Landmark</label>
                <input type="text" name="location_address" placeholder="e.g. Pocket 1, Mayur Vihar Phase 1, East Delhi" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">On-Site Recipient Officer & Contact</label>
                <input type="text" name="contact_officer" placeholder="e.g. Lead Vol. Elena Rostova (+91 98101 22334)" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Distribution Directives / Notes</label>
                <textarea name="notes" rows="2" placeholder="Specific distribution instructions, ration guidelines, cold chain requirements..." class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white"></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('globalDispatchModal').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 font-semibold">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold shadow-lg shadow-indigo-600/30">Confirm & Dispatch</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- ADD NEW RESOURCE CATALOG ITEM MODAL -->
<!-- ========================================================================= -->
<div id="addResourceModal" class="fixed inset-0 bg-[#060a14]/85 backdrop-blur-md z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-[#0f172a] border border-[#243049] rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden">
        <div class="h-14 px-6 bg-[#0c1326] border-b border-[#243049] flex items-center justify-between">
            <h3 class="text-sm font-extrabold text-white flex items-center gap-2">
                <i class="fa-solid fa-box text-indigo-400"></i> Add Master Resource Item
            </h3>
            <button type="button" onclick="document.getElementById('addResourceModal').classList.add('hidden')" class="text-slate-400 hover:text-white">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <form method="POST" action="resources.php" class="p-6 space-y-3.5 text-xs">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="add_resource">

            <div>
                <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Resource Item Name *</label>
                <input type="text" name="name" required placeholder="e.g. Solar Powered Water Purification Units" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Category *</label>
                    <select name="category" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white font-semibold">
                        <option value="Food & Water">🥪 Food & Water</option>
                        <option value="Medical Supplies">💉 Medical Supplies</option>
                        <option value="Power & Energy">⚡ Power & Energy</option>
                        <option value="Vehicles & Mobility">🚒 Vehicles & Mobility</option>
                        <option value="Shelter & Bedding">🏕️ Shelter & Bedding</option>
                        <option value="Tactical & Rescue Gear">🦺 Tactical & Rescue Gear</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Initial Total Stock *</label>
                    <input type="number" name="total_stock" min="1" required value="100" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white font-bold text-center">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Unit of Measurement</label>
                    <input type="text" name="unit" value="units" placeholder="e.g. cans, kits, units, cylinders" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Primary Warehouse</label>
                    <input type="text" name="primary_warehouse" value="Delhi Central Disaster Logistics Base, Okhla" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white">
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Technical Specifications / Notes</label>
                <textarea name="notes" rows="2" placeholder="Item details, storage conditions, manufacturer..." class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white"></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('addResourceModal').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 font-semibold">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold shadow-lg shadow-indigo-600/30">Add to Catalog</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- JAVASCRIPT LOGIC & INTERACTION ENGINE -->
<!-- ========================================================================= -->
<script>
let distMap = null;
let distMarkerGroup = null;

// Filter Catalog by Category
function filterByCategory(category, btnElement) {
    // Update chip styling
    document.querySelectorAll('.category-chip').forEach(btn => {
        btn.className = "category-chip px-3 py-1.5 rounded-xl bg-[#11192e] text-slate-300 hover:bg-slate-800 font-semibold whitespace-nowrap border border-[#243049] transition-all";
    });
    btnElement.className = "category-chip px-3 py-1.5 rounded-xl bg-indigo-600 text-white font-bold whitespace-nowrap shadow-sm transition-all";

    // Filter cards
    const cards = document.querySelectorAll('.resource-card');
    cards.forEach(card => {
        if (category === 'all' || card.dataset.category === category) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}

// Instant Search Filtering
function filterBySearch() {
    const term = document.getElementById('resourceSearchInput').value.toLowerCase().trim();
    const cards = document.querySelectorAll('.resource-card');
    cards.forEach(card => {
        if (!term || card.dataset.search.includes(term)) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}

// Open Global Dispatch Modal
function openDispatchModal() {
    document.getElementById('globalDispatchModal').classList.remove('hidden');
}

// Open Detailed Distribution Dossier Modal on Clicking Any Resource
function openDistributionDossier(resource, distributions) {
    document.getElementById('modalResourceName').innerText = `${resource.name} (${resource.resource_code})`;
    document.getElementById('modalResourceMeta').innerText = `Category: ${resource.category} • Primary Warehouse: ${resource.primary_warehouse}`;
    
    document.getElementById('modalTotalStock').innerText = `${Number(resource.total_stock).toLocaleString()} ${resource.unit}`;
    document.getElementById('modalAvailStock').innerText = `${Number(resource.available_stock).toLocaleString()} ${resource.unit}`;
    document.getElementById('modalDistStock').innerText = `${Number(resource.distributed_stock).toLocaleString()} ${resource.unit}`;
    document.getElementById('modalSitesCount').innerText = `${distributions.length} Sites`;

    document.getElementById('inlineFormResourceId').value = resource.id;

    // Populate Distribution Sites List
    const sitesContainer = document.getElementById('distributionSitesList');
    if (!distributions || distributions.length === 0) {
        sitesContainer.innerHTML = `
            <div class="p-6 rounded-xl bg-[#080d1a] border border-[#243049] text-center text-xs text-slate-400">
                <i class="fa-solid fa-box-open text-2xl text-slate-600 mb-2 block"></i>
                No field distributions have been dispatched yet for this resource item.<br/>
                All ${Number(resource.available_stock).toLocaleString()} ${resource.unit} are available in warehouse storage.
            </div>
        `;
    } else {
        let html = '';
        distributions.forEach(d => {
            const typeIcon = d.destination_type === 'Hospital' ? '🏥' : (d.destination_type === 'Relief Camp' ? '🏕️' : (d.destination_type === 'Fire Unit' ? '🚒' : (d.destination_type === 'Police Cordon' ? '🚓' : '📍')));
            html += `
                <div class="p-3.5 rounded-xl bg-[#080d1a] border border-[#243049] hover:border-slate-500 transition-all text-xs space-y-2">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-base">${typeIcon}</span>
                            <div>
                                <h5 class="font-extrabold text-white text-xs">${d.destination_name}</h5>
                                <span class="text-[10px] font-bold text-indigo-400">${d.destination_type}</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="px-2 py-0.5 rounded-lg bg-blue-950 text-blue-300 border border-blue-800 text-[11px] font-extrabold">
                                ${Number(d.quantity_distributed).toLocaleString()} ${d.unit} Deployed
                            </span>
                        </div>
                    </div>

                    <p class="text-slate-400 text-[11px]"><i class="fa-solid fa-location-dot text-rose-400 mr-1"></i> ${d.location_address || 'Delhi-NCR Strategic Location'}</p>

                    <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-[#243049]/60 text-[11px]">
                        <span class="text-slate-300 font-mono"><b>Officer:</b> ${d.contact_officer || 'On-site Lead'}</span>
                        <span class="text-emerald-400 font-bold"><i class="fa-solid fa-check-circle mr-1"></i> ${d.distribution_status || 'Delivered'}</span>
                    </div>
                </div>
            `;
        });
        sitesContainer.innerHTML = html;
    }

    // Open Modal
    document.getElementById('distributionDossierModal').classList.remove('hidden');

    // Initialize or Refresh Leaflet Mini-Map
    setTimeout(() => {
        initDistributionMap(distributions);
    }, 150);
}

// Close Modal
function closeDistributionModal() {
    document.getElementById('distributionDossierModal').classList.add('hidden');
}

// Initialize Leaflet Map inside Modal
function initDistributionMap(distributions) {
    if (!distMap) {
        distMap = L.map('distributionMiniMap', { zoomControl: false, attributionControl: false }).setView([28.6139, 77.2090], 11);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', { maxZoom: 19 }).addTo(distMap);
        distMarkerGroup = L.layerGroup().addTo(distMap);
    } else {
        distMarkerGroup.clearLayers();
        distMap.invalidateSize();
    }

    if (distributions && distributions.length > 0) {
        const bounds = [];
        distributions.forEach(d => {
            if (d.gps_lat && d.gps_lng) {
                const marker = L.circleMarker([d.gps_lat, d.gps_lng], {
                    radius: 8,
                    fillColor: '#6366f1',
                    color: '#ffffff',
                    weight: 2,
                    fillOpacity: 0.95
                }).addTo(distMarkerGroup)
                .bindPopup(`
                    <div style="color:#0f172a; font-family:'Inter', sans-serif; font-size:12px; min-width:180px;">
                        <strong style="color:#4f46e5;">📍 ${d.destination_name}</strong><br/>
                        <b>Type:</b> ${d.destination_type}<br/>
                        <span>Deployed: <b>${Number(d.quantity_distributed).toLocaleString()} ${d.unit}</b></span><br/>
                        <span style="color:#64748b; font-size:10px;">Contact: ${d.contact_officer}</span>
                    </div>
                `);
                bounds.push([d.gps_lat, d.gps_lng]);
            }
        });

        if (bounds.length > 0) {
            distMap.fitBounds(bounds, { padding: [30, 30], maxZoom: 14 });
        }
    } else {
        distMap.setView([28.6139, 77.2090], 11);
    }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
