<?php
// relief.php - Relief Aid & Supplies Distributed Ledger
define('PAGE_TITLE', 'Relief Supply Distribution');
require_once __DIR__ . '/auth.php';

$currentUser = requireVolunteer($pdo);

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrf)) {
        setFlash('error', 'Security token invalid.');
        header("Location: relief.php");
        exit;
    }

    if ($action === 'log_supplies') {
        $disasterId = 1;
        $itemName = trim($_POST['item_name'] ?? '');
        $quantity = (int) ($_POST['quantity'] ?? 0);
        $unit = trim($_POST['unit'] ?? 'kits');
        $location = trim($_POST['location'] ?? '');

        if (!empty($itemName) && $quantity > 0 && !empty($location)) {
            $stmt = $pdo->prepare("INSERT INTO relief_supplies (disaster_id, item_name, quantity, unit, distributed_by_user_id, location) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$disasterId, $itemName, $quantity, $unit, $currentUser['id'], $location]);

            logActivity($pdo, 'LOG_RELIEF_SUPPLIES', "Distributed {$quantity} {$unit} of {$itemName} at {$location}");
            setFlash('success', "Logged {$quantity} {$unit} of {$itemName} successfully.");
        } else {
            setFlash('error', 'Please fill in all supply distribution details.');
        }
        header("Location: relief.php");
        exit;
    }
}

// Queries
$suppliesList = $pdo->query("
    SELECT rs.*, u.name as volunteer_name, u.avatar as volunteer_avatar 
    FROM relief_supplies rs 
    JOIN users u ON rs.distributed_by_user_id = u.id 
    ORDER BY rs.id DESC
")->fetchAll();

$totalItems = (int) $pdo->query("SELECT SUM(quantity) FROM relief_supplies")->fetchColumn();
$totalLogs = count($suppliesList);

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
                    <span>Relief Supplies Distribution Ledger</span>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        <?= number_format($totalItems) ?> Items Handed Out
                    </span>
                </h2>
                <p class="text-xs text-slate-400 mt-1">Audit log of drinking water bottles, food rations, hygiene kits, and blankets distributed to survivors</p>
            </div>
            <button type="button" onclick="openReliefModal()" class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition-all shadow-lg shadow-emerald-600/30 flex items-center justify-center gap-2">
                <i class="fa-solid fa-boxes-packing text-xs"></i>
                <span>Log New Distribution</span>
            </button>
        </div>

        <!-- Ledger Table Card -->
        <div class="glass-panel rounded-3xl overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-800/80 bg-slate-900/40 text-slate-400 uppercase tracking-wider font-semibold">
                            <th class="py-3.5 px-4 sm:px-6">Relief Item Description</th>
                            <th class="py-3.5 px-4">Quantity Distributed</th>
                            <th class="py-3.5 px-4">Distribution Sector</th>
                            <th class="py-3.5 px-4">Volunteer Officer</th>
                            <th class="py-3.5 px-4 sm:px-6 text-right">Logged At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 text-slate-300">
                        <?php if (empty($suppliesList)): ?>
                            <tr>
                                <td colspan="5" class="py-12 text-center text-slate-500">
                                    <div class="w-12 h-12 rounded-2xl bg-slate-900 border border-slate-800 text-slate-600 flex items-center justify-center mx-auto mb-3 text-lg">
                                        <i class="fa-solid fa-box-open"></i>
                                    </div>
                                    <p class="font-medium text-slate-400">No relief distributions logged yet.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($suppliesList as $s): ?>
                                <tr class="hover:bg-slate-800/30 transition-colors">
                                    <td class="py-4 px-4 sm:px-6 font-bold text-white flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-xs">
                                            <i class="fa-solid fa-box-tissue"></i>
                                        </div>
                                        <span><?= htmlspecialchars($s['item_name']) ?></span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                            <?= number_format($s['quantity']) ?> <?= htmlspecialchars($s['unit']) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4 text-emerald-300 font-medium">
                                        <i class="fa-solid fa-location-dot text-rose-400 mr-1 text-[11px]"></i><?= htmlspecialchars($s['location']) ?>
                                    </td>
                                    <td class="py-4 px-4 text-slate-300">
                                        <div class="flex items-center gap-2">
                                            <img src="<?= htmlspecialchars($s['volunteer_avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($s['volunteer_name']) . '&background=10b981&color=fff') ?>" 
                                                 alt="Avatar" class="w-6 h-6 rounded-full object-cover">
                                            <span><?= htmlspecialchars($s['volunteer_name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="py-4 px-4 sm:px-6 text-right font-mono text-[11px] text-slate-500">
                                        <?= date('M d, H:i', strtotime($s['created_at'])) ?>
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

<!-- LOG RELIEF MODAL -->
<div id="reliefModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl relative animate-in fade-in zoom-in duration-200">
        
        <div class="flex items-center justify-between pb-4 mb-5 border-b border-slate-800">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
                    <i class="fa-solid fa-boxes-packing text-base"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-white">Log Handed Out Aid</h3>
                    <p class="text-xs text-slate-400">Record food, water bottles, and trauma kits</p>
                </div>
            </div>
            <button type="button" onclick="closeReliefModal()" class="text-slate-400 hover:text-white p-2">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="relief.php" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="log_supplies">

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Item Name *</label>
                <input type="text" name="item_name" required placeholder="e.g. Bottled Water 1L, Dry Ration Packs, Wool Blankets" 
                       class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Quantity Distributed *</label>
                    <input type="number" name="quantity" min="1" required placeholder="e.g. 500" 
                           class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Unit</label>
                    <select name="unit" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-emerald-500">
                        <option value="bottles">Bottles</option>
                        <option value="kits">Kits</option>
                        <option value="packets">Packets</option>
                        <option value="meals">Meals</option>
                        <option value="blankets">Blankets</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Distribution Point / Sector *</label>
                <input type="text" name="location" required placeholder="e.g. Sector 3 Relief Depot, Community Center Tent 4" 
                       class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500">
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800 mt-6">
                <button type="button" onclick="closeReliefModal()" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-lg shadow-emerald-600/30 transition-all">
                    Submit Supply Entry
                </button>
            </div>
        </form>

    </div>
</div>

<script>
function openReliefModal() {
    document.getElementById('reliefModal').classList.remove('hidden');
}
function closeReliefModal() {
    document.getElementById('reliefModal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
