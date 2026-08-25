<?php
// relief.php - Relief Aid & Supplies Distributed Ledger (Government Theme)
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

<div class="flex-1 flex flex-col min-w-0 bg-[#f8fafc] min-h-screen overflow-y-auto">
    <?php require_once __DIR__ . '/navbar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto w-full">
        
        <!-- HEADER BANNER -->
        <section class="bg-white p-5 sm:p-6 rounded-3xl border border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">Relief Supplies Distribution Ledger</h2>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 mono">
                        <?= number_format($totalItems) ?> Items Handed Out
                    </span>
                </div>
                <p class="text-xs text-slate-500 font-medium mt-1">Audit log of drinking water bottles, food rations, hygiene kits, and blankets distributed to survivors</p>
            </div>
            <button type="button" onclick="openReliefModal()" class="px-4 py-2.5 rounded-2xl bg-[#1d63d8] hover:bg-[#1553c7] text-white text-xs font-bold transition-all shadow-sm flex items-center justify-center gap-2 cursor-pointer">
                <i class="fa-solid fa-boxes-packing text-xs"></i>
                <span>Log New Distribution</span>
            </button>
        </section>

        <!-- Ledger Table Card -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50/80 text-slate-500 uppercase tracking-wider font-extrabold text-[10px] mono">
                            <th class="py-3.5 px-4 sm:px-6">Relief Item Description</th>
                            <th class="py-3.5 px-4">Quantity Distributed</th>
                            <th class="py-3.5 px-4">Distribution Sector</th>
                            <th class="py-3.5 px-4">Volunteer Officer</th>
                            <th class="py-3.5 px-4 sm:px-6 text-right">Logged At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($suppliesList)): ?>
                            <tr>
                                <td colspan="5" class="py-12 text-center text-slate-500">
                                    <div class="w-12 h-12 rounded-2xl bg-slate-100 border border-slate-200 text-slate-400 flex items-center justify-center mx-auto mb-3 text-lg">
                                        <i class="fa-solid fa-box-open"></i>
                                    </div>
                                    <p class="font-medium text-slate-500">No relief distributions logged yet.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($suppliesList as $s): ?>
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="py-4 px-4 sm:px-6 font-extrabold text-slate-900 flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-xl bg-blue-50 text-[#1d63d8] flex items-center justify-center text-xs">
                                            <i class="fa-solid fa-box-tissue"></i>
                                        </div>
                                        <span><?= htmlspecialchars($s['item_name']) ?></span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-mono font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                            <?= number_format($s['quantity']) ?> <?= htmlspecialchars($s['unit']) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4 text-blue-700 font-bold">
                                        <i class="fa-solid fa-location-dot text-red-500 mr-1 text-[11px]"></i><?= htmlspecialchars($s['location']) ?>
                                    </td>
                                    <td class="py-4 px-4 text-slate-700">
                                            <div class="w-6 h-6 rounded-full bg-emerald-600 text-white font-bold text-[10px] flex items-center justify-center shrink-0 shadow-2xs">
                                                <i class="fa-solid fa-hand-holding-heart text-[8px]"></i>
                                            </div>
                                            <span class="font-medium"><?= htmlspecialchars($s['volunteer_name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="py-4 px-4 sm:px-6 text-right font-mono text-[11px] text-slate-400">
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
<div id="reliefModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white border border-slate-200 rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl relative">
        
        <div class="flex items-center justify-between pb-4 mb-5 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-blue-50 text-[#1d63d8] flex items-center justify-center">
                    <i class="fa-solid fa-boxes-packing text-base"></i>
                </div>
                <div>
                    <h3 class="text-lg font-extrabold text-slate-900">Log Handed Out Aid</h3>
                    <p class="text-xs text-slate-500 font-medium">Record food, water bottles, and trauma kits</p>
                </div>
            </div>
            <button type="button" onclick="closeReliefModal()" class="text-slate-400 hover:text-slate-800 p-2 cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="relief.php" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="log_supplies">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Item Name *</label>
                <input type="text" name="item_name" required placeholder="e.g. Bottled Water 1L, Dry Ration Packs, Wool Blankets" 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-[#1d63d8] focus:bg-white font-medium">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Quantity Distributed *</label>
                    <input type="number" name="quantity" min="1" required placeholder="e.g. 500" 
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:outline-none focus:border-[#1d63d8] focus:bg-white font-bold">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Unit</label>
                    <select name="unit" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 font-bold focus:outline-none focus:border-[#1d63d8]">
                        <option value="bottles">Bottles</option>
                        <option value="kits">Kits</option>
                        <option value="packets">Packets</option>
                        <option value="meals">Meals</option>
                        <option value="blankets">Blankets</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Distribution Point / Sector *</label>
                <input type="text" name="location" required placeholder="e.g. Sector 3 Relief Depot, Community Center Tent 4" 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-[#1d63d8] focus:bg-white font-medium">
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 mt-6">
                <button type="button" onclick="closeReliefModal()" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors cursor-pointer">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#1d63d8] hover:bg-[#1553c7] text-white text-xs font-bold shadow-sm transition-all cursor-pointer">
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
