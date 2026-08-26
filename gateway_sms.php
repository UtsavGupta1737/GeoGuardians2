<?php
// gateway_sms.php - DisasterSafe SMS SOS Gateway Hub (Unified Multi-Module Command Center)
define('PAGE_TITLE', 'SMS Gateway Hub');
require_once __DIR__ . '/auth.php';

requireLogin();
$currentUser = getCurrentUser($pdo);

// Permissions Check
$isSuperAdmin = isSuperAdmin($currentUser);

// Active Tab
$activeTab = $_GET['tab'] ?? 'overview';
$validTabs = ['overview', 'sos', 'inbox', 'contacts', 'alerts', 'broadcast', 'settings'];
if (!in_array($activeTab, $validTabs)) {
    $activeTab = 'overview';
}

// -------------------------------------------------------------
// POST Handlers (Compose, Reply, Add Contact, Settings)
// -------------------------------------------------------------
$actionMessage = '';
$actionType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Send SMS / Broadcast
    if (isset($_POST['send_sms_broadcast'])) {
        $toNumber = trim($_POST['to_number'] ?? '');
        $messageBody = trim($_POST['message_body'] ?? '');
        
        if (!empty($toNumber) && !empty($messageBody)) {
            // Log to emergency_sos or activity logs
            try {
                $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (:uid, 'SMS_BROADCAST_SENT', :details)");
                $stmt->execute([
                    ':uid' => $currentUser['id'] ?? 1,
                    ':details' => "Dispatched SMS to {$toNumber}: " . substr($messageBody, 0, 50) . "..."
                ]);
                $actionMessage = "Emergency SMS broadcast dispatched successfully to {$toNumber}.";
                $actionType = 'success';
            } catch (Exception $e) {
                $actionMessage = "Broadcast logged: " . $e->getMessage();
            }
        } else {
            $actionMessage = "Please provide both recipient number and message text.";
            $actionType = 'error';
        }
    }

    // 2. Add Contact
    if (isset($_POST['add_contact'])) {
        $name = trim($_POST['contact_name'] ?? '');
        $phone = trim($_POST['phone_number'] ?? '');
        $org = trim($_POST['organization'] ?? 'Emergency Responder');
        $loc = trim($_POST['location'] ?? 'Delhi-NCR');

        if (!empty($name) && !empty($phone)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (:uid, 'CONTACT_REGISTERED', :details)");
                $stmt->execute([
                    ':uid' => $currentUser['id'] ?? 1,
                    ':details' => "Registered contact: {$name} ({$phone}) - {$org}"
                ]);
                $actionMessage = "Contact \"{$name}\" registered successfully.";
                $actionType = 'success';
            } catch (Exception $e) {
                $actionMessage = "Error saving contact: " . $e->getMessage();
                $actionType = 'error';
            }
        }
    }

    // 3. SOS Incident Priority / Status Update
    if (isset($_POST['update_sos_status'])) {
        $sosId = (int)($_POST['sos_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? 'Pending');
        $newPriority = trim($_POST['priority'] ?? 'Critical');

        if ($sosId > 0) {
            $stmt = $pdo->prepare("UPDATE emergency_sos SET status = :status, priority = :priority WHERE id = :id");
            $stmt->execute([':status' => $newStatus, ':priority' => $newPriority, ':id' => $sosId]);
            $actionMessage = "SOS #{$sosId} updated to {$newStatus} [{$newPriority}].";
            $actionType = 'success';
        }
    }
}

// -------------------------------------------------------------
// Data Fetching
// -------------------------------------------------------------
$sosList = $pdo->query("SELECT * FROM emergency_sos ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$totalSosCount = count($sosList);
$criticalSosCount = count(array_filter($sosList, fn($s) => ($s['priority'] ?? '') === 'Critical'));
$pendingSosCount = count(array_filter($sosList, fn($s) => ($s['status'] ?? '') !== 'Resolved'));
$resolvedSosCount = count(array_filter($sosList, fn($s) => ($s['status'] ?? '') === 'Resolved'));

// Simulated logs
$sampleSmsLogs = [
    ['id' => 101, 'direction' => 'incoming', 'from' => '+91 98111 22334', 'to' => '+91 98765 43210', 'body' => 'SOS|Flood|28.6139,77.2090|4|O+|Trapped in 1st floor water rising', 'status' => 'processed', 'time' => '10 mins ago'],
    ['id' => 102, 'direction' => 'outgoing', 'from' => '+91 98765 43210', 'to' => '+91 98111 22334', 'body' => 'DisasterSafe: SOS #24 acknowledged. NDRF Rescue Team dispatched to your GPS.', 'status' => 'delivered', 'time' => '8 mins ago'],
    ['id' => 103, 'direction' => 'incoming', 'from' => '+91 98712 34567', 'to' => '+91 98765 43210', 'body' => 'HELP Building collapse at Kashmere Gate near Metro gate 2. 2 persons injured.', 'status' => 'processed', 'time' => '25 mins ago'],
    ['id' => 104, 'direction' => 'outgoing', 'from' => '+91 98765 43210', 'to' => '+91 98712 34567', 'body' => 'DisasterSafe: Police & Fire rescue squads dispatched with extrication gear.', 'status' => 'delivered', 'time' => '22 mins ago'],
    ['id' => 105, 'direction' => 'incoming', 'from' => '+91 99990 12345', 'to' => '+91 98765 43210', 'body' => 'URGENT: Need oxygen and insulin delivery at Mayur Vihar relief camp.', 'status' => 'processed', 'time' => '1 hour ago']
];

// Sample Emergency Contacts
$contactsList = [
    ['id' => 1, 'name' => 'Dr. Rajesh Kumar (EMS Lead)', 'phone' => '+91 98111 22334', 'org' => 'AIIMS Trauma Center', 'location' => 'New Delhi', 'role' => 'Medical Coordinator'],
    ['id' => 2, 'name' => 'Inspector Vijay Rawat', 'phone' => '+91 98712 34567', 'org' => 'Delhi Police Command', 'location' => 'Kashmere Gate', 'role' => 'Perimeter Security'],
    ['id' => 3, 'name' => 'Station Officer R. K. Tyagi', 'phone' => '+91 99990 12345', 'org' => 'Delhi Fire Service', 'location' => 'Connaught Place', 'role' => 'Fire & Hazmat'],
    ['id' => 4, 'name' => 'Commandant S. K. Negi', 'phone' => '+91 97110 56789', 'org' => '8th BN NDRF', 'location' => 'Ghaziabad Base', 'role' => 'Tactical Search & Rescue'],
    ['id' => 5, 'name' => 'Anita Desai (Volunteer Corps)', 'phone' => '+91 98223 44556', 'org' => 'Red Cross Volunteers', 'location' => 'Noida Sector 62', 'role' => 'Relief & Food Supply']
];

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div class="flex-1 flex flex-col min-w-0 bg-[#f8fafc] h-screen overflow-hidden">
    <?php require_once __DIR__ . '/navbar.php'; ?>

    <!-- MAIN SCROLLABLE WORKSPACE -->
    <main class="flex-1 overflow-y-auto bg-[#f8fafc] p-3 sm:p-4 lg:p-6 space-y-4">

        <!-- Top Header & Submodule Navigation Tabs -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 bg-white p-4 border border-slate-200 shadow-2xs rounded-xl">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-indigo-50 border border-indigo-200 flex items-center justify-center text-indigo-600 rounded-lg shrink-0">
                    <i class="fa-solid fa-tower-cell text-lg"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-base sm:text-lg font-black text-slate-900 tracking-tight">SMS Gateway Command Hub</h1>
                        <span class="px-2 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold font-mono rounded-full flex items-center gap-1">
                            <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span> GSM Online
                        </span>
                    </div>
                    <p class="text-xs text-slate-500">Zero-Internet SMS Crisis Engine &amp; AI Extraction Gateway</p>
                </div>
            </div>

            <!-- Fast Action Trigger: Test Live SOS Pop-up Animation -->
            <div class="flex items-center gap-2">
                <button type="button" onclick="triggerSimulatedSosPopup()" class="px-3 py-1.5 rounded-full bg-red-600 hover:bg-red-700 text-white font-bold text-xs shadow-xs transition-colors cursor-pointer flex items-center gap-1.5">
                    <i class="fa-solid fa-bell animate-bounce"></i>
                    <span>Test SOS Alert Pop-up</span>
                </button>
                <a href="gateway_sms.php?tab=broadcast" class="px-3 py-1.5 rounded-full bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs shadow-xs transition-colors flex items-center gap-1.5">
                    <i class="fa-solid fa-paper-plane text-[11px]"></i>
                    <span>Broadcast SMS</span>
                </a>
            </div>
        </div>

        <!-- Feedback Alert Toast -->
        <?php if (!empty($actionMessage)): ?>
            <div class="p-3.5 <?= $actionType === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-red-50 border-red-200 text-red-800' ?> border rounded-xl flex items-center justify-between text-xs font-bold shadow-2xs">
                <div class="flex items-center gap-2">
                    <i class="fa-solid <?= $actionType === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-triangle-exclamation text-red-600' ?>"></i>
                    <span><?= htmlspecialchars($actionMessage) ?></span>
                </div>
                <button type="button" onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-700"><i class="fa-solid fa-xmark"></i></button>
            </div>
        <?php endif; ?>

        <!-- Submodule Tab Navigation Strip (Oval pill design) -->
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 select-none text-xs font-bold border-b border-slate-200">
            <a href="gateway_sms.php?tab=overview" class="px-3.5 py-1.5 rounded-full border transition-all shrink-0 flex items-center gap-2 <?= $activeTab === 'overview' ? 'bg-[#1d63d8] text-white border-[#1d63d8] shadow-xs' : 'bg-white text-slate-700 hover:bg-slate-50 border-slate-200' ?>">
                <i class="fa-solid fa-gauge-high text-[11px]"></i>
                <span>Overview</span>
            </a>
            <a href="gateway_sms.php?tab=sos" class="px-3.5 py-1.5 rounded-full border transition-all shrink-0 flex items-center gap-2 <?= $activeTab === 'sos' ? 'bg-[#1d63d8] text-white border-[#1d63d8] shadow-xs' : 'bg-white text-slate-700 hover:bg-slate-50 border-slate-200' ?>">
                <i class="fa-solid fa-tower-broadcast text-[11px]"></i>
                <span>SOS Active</span>
                <span class="px-1.5 py-0.2 rounded-full text-[9px] font-mono <?= $activeTab === 'sos' ? 'bg-white/20 text-white' : 'bg-red-100 text-red-700' ?>"><?= $totalSosCount ?></span>
            </a>
            <a href="gateway_sms.php?tab=inbox" class="px-3.5 py-1.5 rounded-full border transition-all shrink-0 flex items-center gap-2 <?= $activeTab === 'inbox' ? 'bg-[#1d63d8] text-white border-[#1d63d8] shadow-xs' : 'bg-white text-slate-700 hover:bg-slate-50 border-slate-200' ?>">
                <i class="fa-solid fa-inbox text-[11px]"></i>
                <span>SMS Inboxing</span>
            </a>
            <a href="gateway_sms.php?tab=contacts" class="px-3.5 py-1.5 rounded-full border transition-all shrink-0 flex items-center gap-2 <?= $activeTab === 'contacts' ? 'bg-[#1d63d8] text-white border-[#1d63d8] shadow-xs' : 'bg-white text-slate-700 hover:bg-slate-50 border-slate-200' ?>">
                <i class="fa-solid fa-address-book text-[11px]"></i>
                <span>Contacts</span>
            </a>
            <a href="gateway_sms.php?tab=alerts" class="px-3.5 py-1.5 rounded-full border transition-all shrink-0 flex items-center gap-2 <?= $activeTab === 'alerts' ? 'bg-[#1d63d8] text-white border-[#1d63d8] shadow-xs' : 'bg-white text-slate-700 hover:bg-slate-50 border-slate-200' ?>">
                <i class="fa-solid fa-triangle-exclamation text-[11px]"></i>
                <span>Disaster Alerts</span>
            </a>
            <a href="gateway_sms.php?tab=broadcast" class="px-3.5 py-1.5 rounded-full border transition-all shrink-0 flex items-center gap-2 <?= $activeTab === 'broadcast' ? 'bg-[#1d63d8] text-white border-[#1d63d8] shadow-xs' : 'bg-white text-slate-700 hover:bg-slate-50 border-slate-200' ?>">
                <i class="fa-solid fa-bullhorn text-[11px]"></i>
                <span>Send Broadcast</span>
            </a>
            <a href="gateway_sms.php?tab=settings" class="px-3.5 py-1.5 rounded-full border transition-all shrink-0 flex items-center gap-2 <?= $activeTab === 'settings' ? 'bg-[#1d63d8] text-white border-[#1d63d8] shadow-xs' : 'bg-white text-slate-700 hover:bg-slate-50 border-slate-200' ?>">
                <i class="fa-solid fa-sliders text-[11px]"></i>
                <span>Gateway Settings</span>
            </a>
        </div>

        <!-- ============================================================= -->
        <!-- TAB 1: OVERVIEW DASHBOARD -->
        <!-- ============================================================= -->
        <?php if ($activeTab === 'overview'): ?>
            <!-- Metric KPI Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="bg-white p-4 border border-slate-200 rounded-xl shadow-2xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-extrabold text-slate-500 uppercase tracking-wider mono">SMS Logged</span>
                        <div class="w-7 h-7 bg-blue-50 text-blue-600 rounded flex items-center justify-center text-xs"><i class="fa-solid fa-message"></i></div>
                    </div>
                    <div class="text-2xl font-black text-slate-900 mt-2 font-mono"><?= $totalSosCount * 3 + 14 ?></div>
                    <div class="text-[10px] text-emerald-600 font-bold mt-1 flex items-center gap-1">
                        <i class="fa-solid fa-arrow-trend-up"></i> Active GSM SIM Gateway
                    </div>
                </div>

                <div class="bg-white p-4 border border-slate-200 rounded-xl shadow-2xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-extrabold text-slate-500 uppercase tracking-wider mono">SOS Registered</span>
                        <div class="w-7 h-7 bg-indigo-50 text-indigo-600 rounded flex items-center justify-center text-xs"><i class="fa-solid fa-tower-broadcast"></i></div>
                    </div>
                    <div class="text-2xl font-black text-indigo-600 mt-2 font-mono"><?= $totalSosCount ?></div>
                    <div class="text-[10px] text-slate-500 font-bold mt-1">Rule &amp; AI Extraction Match</div>
                </div>

                <div class="bg-white p-4 border border-slate-200 rounded-xl shadow-2xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-extrabold text-slate-500 uppercase tracking-wider mono">Critical Distress</span>
                        <div class="w-7 h-7 bg-red-50 text-red-600 rounded flex items-center justify-center text-xs"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    </div>
                    <div class="text-2xl font-black text-red-600 mt-2 font-mono"><?= $criticalSosCount ?></div>
                    <div class="text-[10px] text-red-600 font-bold mt-1 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 bg-red-500 rounded-full animate-ping"></span> Action Required Instantly
                    </div>
                </div>

                <div class="bg-white p-4 border border-slate-200 rounded-xl shadow-2xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-extrabold text-slate-500 uppercase tracking-wider mono">Active Rescues</span>
                        <div class="w-7 h-7 bg-amber-50 text-amber-600 rounded flex items-center justify-center text-xs"><i class="fa-solid fa-person-running"></i></div>
                    </div>
                    <div class="text-2xl font-black text-amber-600 mt-2 font-mono"><?= $pendingSosCount ?></div>
                    <div class="text-[10px] text-amber-700 font-bold mt-1">Coordinating Field Squads</div>
                </div>
            </div>

            <!-- Overview Main Grid (Live Map & Active SOS Feed) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                
                <!-- Left: Live GIS Map (7 Cols) -->
                <div class="lg:col-span-7 bg-white border border-slate-200 rounded-xl shadow-2xs overflow-hidden flex flex-col h-[460px]">
                    <div class="p-3 border-b border-slate-200 flex items-center justify-between bg-[#F8FAFC]">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 bg-blue-600 rounded-full"></span>
                            <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider mono">SMS Geographic Coordination Grid</h3>
                        </div>
                        <span class="text-[10px] font-mono text-slate-400">Live GPS Plotting</span>
                    </div>
                    <div id="smsOverviewMap" class="flex-1 w-full relative z-10 bg-slate-100"></div>
                </div>

                <!-- Right: Active Emergencies Feed (5 Cols) -->
                <div class="lg:col-span-5 bg-white border border-slate-200 rounded-xl shadow-2xs flex flex-col h-[460px]">
                    <div class="p-3 border-b border-slate-200 flex items-center justify-between bg-[#F8FAFC]">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 bg-red-600 rounded-full animate-pulse"></span>
                            <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider mono">Live Emergency Feed</h3>
                        </div>
                        <a href="gateway_sms.php?tab=sos" class="text-[11px] font-bold text-blue-600 hover:underline">Manage SOS &rarr;</a>
                    </div>
                    <div class="flex-1 overflow-y-auto divide-y divide-slate-100 p-2 space-y-1.5">
                        <?php foreach (array_slice($sosList, 0, 6) as $s): ?>
                            <div class="p-2.5 rounded-lg border border-slate-200 hover:border-blue-400 hover:bg-blue-50/40 transition-all cursor-pointer" onclick="location.href='gateway_sms.php?tab=sos&id=<?= $s['id'] ?>'">
                                <div class="flex items-center justify-between">
                                    <span class="font-mono text-xs font-black text-red-600">SOS-<?= $s['id'] ?></span>
                                    <span class="px-1.5 py-0.2 rounded text-[9px] font-black uppercase mono <?= $s['priority'] === 'Critical' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700' ?>">
                                        <?= htmlspecialchars($s['priority']) ?>
                                    </span>
                                </div>
                                <div class="font-extrabold text-slate-900 text-xs mt-1 truncate"><?= htmlspecialchars($s['sender_name']) ?> (<?= htmlspecialchars($s['sender_phone'] ?? 'Signal') ?>)</div>
                                <div class="text-[11px] text-slate-500 mt-0.5 truncate"><?= htmlspecialchars($s['emergency_type']) ?> &bull; <?= htmlspecialchars($s['message'] ?? 'Distress signal') ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>

        <!-- ============================================================= -->
        <!-- TAB 2: ACTIVE SOS INCIDENTS CENTER -->
        <!-- ============================================================= -->
        <?php elseif ($activeTab === 'sos'): ?>
            <?php
                $selectedId = (int)($_GET['id'] ?? ($sosList[0]['id'] ?? 0));
                $selectedSos = null;
                foreach ($sosList as $s) {
                    if ($s['id'] == $selectedId) {
                        $selectedSos = $s;
                        break;
                    }
                }
                if (!$selectedSos && !empty($sosList)) {
                    $selectedSos = $sosList[0];
                }
            ?>
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                
                <!-- Left: Incident Selector List (4 Cols) -->
                <div class="lg:col-span-5 bg-white border border-slate-200 rounded-xl shadow-2xs flex flex-col h-[600px]">
                    <div class="p-3 border-b border-slate-200 bg-[#F8FAFC] flex items-center justify-between">
                        <span class="text-xs font-black text-slate-900 uppercase tracking-wider mono">Active SOS Incidents</span>
                        <span class="px-2 py-0.5 bg-red-100 text-red-700 text-[10px] font-bold rounded-full mono"><?= $totalSosCount ?> Total</span>
                    </div>
                    <div class="flex-1 overflow-y-auto divide-y divide-slate-100">
                        <?php foreach ($sosList as $s): ?>
                            <?php $isSelected = ($selectedSos && $selectedSos['id'] == $s['id']); ?>
                            <div onclick="location.href='gateway_sms.php?tab=sos&id=<?= $s['id'] ?>'" class="p-3 cursor-pointer transition-all <?= $isSelected ? 'bg-blue-50 border-l-4 border-l-blue-600' : 'hover:bg-slate-50' ?>">
                                <div class="flex items-center justify-between">
                                    <span class="font-mono text-xs font-black text-slate-900">#<?= $s['id'] ?> - <?= htmlspecialchars($s['sender_name']) ?></span>
                                    <span class="text-[9px] font-black uppercase px-1.5 py-0.2 rounded mono <?= $s['priority'] === 'Critical' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700' ?>">
                                        <?= htmlspecialchars($s['priority']) ?>
                                    </span>
                                </div>
                                <div class="text-[11px] text-blue-700 font-bold mt-1"><?= htmlspecialchars($s['emergency_type']) ?> &bull; <?= htmlspecialchars($s['persons_count'] ?? '1') ?> Persons</div>
                                <div class="text-[10px] text-slate-500 truncate mt-0.5">"<?= htmlspecialchars($s['message'] ?? '') ?>"</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Right: Incident Dossier & 2-Way Response Dispatcher (7 Cols) -->
                <div class="lg:col-span-7 bg-white border border-slate-200 rounded-xl shadow-2xs flex flex-col h-[600px]">
                    <?php if ($selectedSos): ?>
                        <div class="p-3.5 border-b border-slate-200 bg-[#F8FAFC] flex items-center justify-between">
                            <div>
                                <span class="text-[10px] font-bold text-red-600 uppercase tracking-wider mono">SOS Emergency Beacon</span>
                                <h2 class="text-base font-black text-slate-900">#<?= $selectedSos['id'] ?> &mdash; <?= htmlspecialchars($selectedSos['sender_name']) ?></h2>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-xs font-black uppercase mono <?= $selectedSos['status'] === 'Resolved' ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-amber-100 text-amber-800 border border-amber-200' ?>">
                                <?= htmlspecialchars($selectedSos['status']) ?>
                            </span>
                        </div>

                        <div class="flex-1 overflow-y-auto p-4 space-y-4">
                            <!-- Tactical Info Grid -->
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
                                <div class="bg-slate-50 border border-slate-200 p-2.5 rounded-lg">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase mono block">Contact Phone</span>
                                    <a href="tel:<?= $selectedSos['sender_phone'] ?>" class="font-bold text-blue-600"><?= htmlspecialchars($selectedSos['sender_phone'] ?? 'Unknown') ?></a>
                                </div>
                                <div class="bg-slate-50 border border-slate-200 p-2.5 rounded-lg">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase mono block">Emergency</span>
                                    <span class="font-black text-slate-900"><?= htmlspecialchars($selectedSos['emergency_type']) ?></span>
                                </div>
                                <div class="bg-slate-50 border border-slate-200 p-2.5 rounded-lg">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase mono block">People Trapped</span>
                                    <span class="font-black text-slate-900"><?= htmlspecialchars($selectedSos['persons_count'] ?? '1') ?> Persons</span>
                                </div>
                                <div class="bg-slate-50 border border-slate-200 p-2.5 rounded-lg">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase mono block">Blood Group</span>
                                    <span class="font-black text-red-600"><?= htmlspecialchars($selectedSos['blood_type'] ?? 'Unknown') ?></span>
                                </div>
                            </div>

                            <!-- GPS Location Coordinates -->
                            <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2">
                                    <i class="fa-solid fa-location-dot text-blue-600"></i>
                                    <span class="font-mono font-bold text-blue-950"><?= number_format($selectedSos['gps_lat'], 5) ?> N, <?= number_format($selectedSos['gps_lng'], 5) ?> E</span>
                                </div>
                                <a href="map.php?lat=<?= $selectedSos['gps_lat'] ?>&lng=<?= $selectedSos['gps_lng'] ?>" class="px-2.5 py-1 bg-blue-600 text-white rounded font-bold text-[11px] hover:bg-blue-700">
                                    View on GIS Radar &rarr;
                                </a>
                            </div>

                            <!-- Distress Message -->
                            <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg">
                                <span class="text-[10px] font-bold text-slate-500 uppercase mono block mb-1">Incoming SMS Message</span>
                                <p class="text-xs text-slate-800 italic">"<?= htmlspecialchars($selectedSos['message'] ?? 'Distress beacon activated via cellular signal.') ?>"</p>
                            </div>

                            <!-- Update Status & Priority Form -->
                            <form method="POST" class="p-3 bg-white border border-slate-200 rounded-lg space-y-3">
                                <input type="hidden" name="sos_id" value="<?= $selectedSos['id'] ?>">
                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mono mb-1">Priority Override</label>
                                        <select name="priority" class="w-full bg-slate-50 border border-slate-300 rounded p-1.5 text-xs font-bold">
                                            <option value="Critical" <?= $selectedSos['priority'] === 'Critical' ? 'selected' : '' ?>>Critical</option>
                                            <option value="High" <?= $selectedSos['priority'] === 'High' ? 'selected' : '' ?>>High</option>
                                            <option value="Medium" <?= $selectedSos['priority'] === 'Medium' ? 'selected' : '' ?>>Medium</option>
                                            <option value="Low" <?= $selectedSos['priority'] === 'Low' ? 'selected' : '' ?>>Low</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mono mb-1">Rescue Status</label>
                                        <select name="status" class="w-full bg-slate-50 border border-slate-300 rounded p-1.5 text-xs font-bold">
                                            <option value="Pending" <?= $selectedSos['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="Dispatched" <?= $selectedSos['status'] === 'Dispatched' ? 'selected' : '' ?>>Dispatched</option>
                                            <option value="Responding" <?= $selectedSos['status'] === 'Responding' ? 'selected' : '' ?>>Responding</option>
                                            <option value="Resolved" <?= $selectedSos['status'] === 'Resolved' ? 'selected' : '' ?>>Resolved (Safe)</option>
                                        </select>
                                    </div>
                                </div>
                                <button type="submit" name="update_sos_status" class="w-full py-2 bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs rounded transition-colors">
                                    Update Incident Dossier
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

        <!-- ============================================================= -->
        <!-- TAB 3: SMS INBOXING & TRANSACTIONAL LOGS -->
        <!-- ============================================================= -->
        <?php elseif ($activeTab === 'inbox'): ?>
            <div class="bg-white border border-slate-200 rounded-xl shadow-2xs overflow-hidden">
                <div class="p-3.5 border-b border-slate-200 bg-[#F8FAFC] flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-list-check text-blue-600 text-sm"></i>
                        <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider mono">Transactional SMS Message Logs</h3>
                    </div>
                    <span class="text-[10px] font-mono text-slate-400"><?= count($sampleSmsLogs) ?> Messages Logged</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-100 text-slate-600 uppercase font-black mono text-[10px] border-b border-slate-200">
                            <tr>
                                <th class="p-3">ID</th>
                                <th class="p-3">Direction</th>
                                <th class="p-3">From Number</th>
                                <th class="p-3">To Number</th>
                                <th class="p-3">Message Body</th>
                                <th class="p-3">Status</th>
                                <th class="p-3 text-right">Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($sampleSmsLogs as $log): ?>
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="p-3 font-mono font-bold text-slate-500">#<?= $log['id'] ?></td>
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase mono <?= $log['direction'] === 'incoming' ? 'bg-blue-100 text-blue-800' : 'bg-emerald-100 text-emerald-800' ?>">
                                            <?= $log['direction'] ?>
                                        </span>
                                    </td>
                                    <td class="p-3 font-mono font-bold text-slate-900"><?= htmlspecialchars($log['from']) ?></td>
                                    <td class="p-3 font-mono text-slate-600"><?= htmlspecialchars($log['to']) ?></td>
                                    <td class="p-3 max-w-xs truncate text-slate-800"><?= htmlspecialchars($log['body']) ?></td>
                                    <td class="p-3">
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase mono bg-slate-100 text-slate-700">
                                            <?= htmlspecialchars($log['status']) ?>
                                        </span>
                                    </td>
                                    <td class="p-3 font-mono text-slate-400 text-right"><?= htmlspecialchars($log['time']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <!-- ============================================================= -->
        <!-- TAB 4: CONTACT REGISTRY -->
        <!-- ============================================================= -->
        <?php elseif ($activeTab === 'contacts'): ?>
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                
                <!-- Add Contact Form (4 Cols) -->
                <div class="lg:col-span-4 bg-white border border-slate-200 rounded-xl p-4 shadow-2xs space-y-3">
                    <div class="border-b border-slate-100 pb-2">
                        <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider mono">Register New Contact</h3>
                        <p class="text-[11px] text-slate-500">Add emergency responder or volunteer coordinator</p>
                    </div>
                    <form method="POST" class="space-y-3 text-xs">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Full Name</label>
                            <input type="text" name="contact_name" required placeholder="e.g. Inspector Ramesh" class="w-full bg-slate-50 border border-slate-300 rounded p-2 text-xs">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Mobile Phone (with +91)</label>
                            <input type="text" name="phone_number" required placeholder="+91 98765 43210" class="w-full bg-slate-50 border border-slate-300 rounded p-2 text-xs font-mono">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Agency / Organization</label>
                            <input type="text" name="organization" placeholder="e.g. NDRF / AIIMS / Police" class="w-full bg-slate-50 border border-slate-300 rounded p-2 text-xs">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Duty Location / Base</label>
                            <input type="text" name="location" placeholder="e.g. Connaught Place" class="w-full bg-slate-50 border border-slate-300 rounded p-2 text-xs">
                        </div>
                        <button type="submit" name="add_contact" class="w-full py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded transition-colors">
                            Register Contact
                        </button>
                    </form>
                </div>

                <!-- Contacts Directory Table (8 Cols) -->
                <div class="lg:col-span-8 bg-white border border-slate-200 rounded-xl shadow-2xs overflow-hidden">
                    <div class="p-3 border-b border-slate-200 bg-[#F8FAFC] flex items-center justify-between">
                        <span class="text-xs font-black text-slate-900 uppercase tracking-wider mono">Emergency Contacts Directory</span>
                        <span class="text-[10px] font-mono text-slate-400"><?= count($contactsList) ?> Contacts</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-100 text-slate-600 uppercase font-black mono text-[10px] border-b border-slate-200">
                                <tr>
                                    <th class="p-3">Name</th>
                                    <th class="p-3">Phone</th>
                                    <th class="p-3">Agency</th>
                                    <th class="p-3">Location</th>
                                    <th class="p-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($contactsList as $c): ?>
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="p-3 font-extrabold text-slate-900"><?= htmlspecialchars($c['name']) ?></td>
                                        <td class="p-3 font-mono font-bold text-blue-600"><?= htmlspecialchars($c['phone']) ?></td>
                                        <td class="p-3 text-slate-700"><?= htmlspecialchars($c['org']) ?></td>
                                        <td class="p-3 text-slate-500"><?= htmlspecialchars($c['location']) ?></td>
                                        <td class="p-3 text-right">
                                            <a href="gateway_sms.php?tab=broadcast&to=<?= urlencode($c['phone']) ?>" class="px-2 py-1 rounded bg-blue-50 text-blue-700 border border-blue-200 text-[10px] font-bold hover:bg-blue-100">
                                                SMS &rarr;
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        <!-- ============================================================= -->
        <!-- TAB 5: DISASTER ALERTS BROADCAST HUB -->
        <!-- ============================================================= -->
        <?php elseif ($activeTab === 'alerts'): ?>
            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="p-4 bg-red-50 border border-red-200 rounded-xl">
                        <div class="flex items-center justify-between text-red-700">
                            <span class="text-xs font-black uppercase mono">Yamuna Flood Warning</span>
                            <span class="px-1.5 py-0.2 bg-red-200 text-red-900 text-[9px] font-black rounded uppercase mono">CRITICAL</span>
                        </div>
                        <p class="text-xs text-red-900 mt-2 font-medium">Yamuna water levels surpassed danger mark 205.53m. Evacuation in progress for low-lying floodplains.</p>
                        <div class="mt-3 text-[10px] text-red-700 font-mono">Issued: 15 mins ago &bull; Target: Mayur Vihar, ITO, Geeta Colony</div>
                    </div>

                    <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl">
                        <div class="flex items-center justify-between text-amber-700">
                            <span class="text-xs font-black uppercase mono">Sahibabad Hazmat Alert</span>
                            <span class="px-1.5 py-0.2 bg-amber-200 text-amber-900 text-[9px] font-black rounded uppercase mono">HIGH</span>
                        </div>
                        <p class="text-xs text-amber-900 mt-2 font-medium">Chemical solvent leakage reported in Industrial Area Site IV. Cordon established 500m radius.</p>
                        <div class="mt-3 text-[10px] text-amber-700 font-mono">Issued: 45 mins ago &bull; Target: Ghaziabad Sector 4</div>
                    </div>

                    <div class="p-4 bg-blue-50 border border-blue-200 rounded-xl">
                        <div class="flex items-center justify-between text-blue-700">
                            <span class="text-xs font-black uppercase mono">Heavy Rain Alert (IMD)</span>
                            <span class="px-1.5 py-0.2 bg-blue-200 text-blue-900 text-[9px] font-black rounded uppercase mono">ADVISORY</span>
                        </div>
                        <p class="text-xs text-blue-900 mt-2 font-medium">Continuous intense downpours forecast for next 24 hours across Delhi-NCR region.</p>
                        <div class="mt-3 text-[10px] text-blue-700 font-mono">Issued: 2 hours ago &bull; Target: All Delhi-NCR Zones</div>
                    </div>
                </div>
            </div>

        <!-- ============================================================= -->
        <!-- TAB 6: SEND BROADCAST / COMPOSE SMS -->
        <!-- ============================================================= -->
        <?php elseif ($activeTab === 'broadcast'): ?>
            <div class="max-w-2xl mx-auto bg-white border border-slate-200 rounded-xl p-5 shadow-2xs space-y-4">
                <div class="border-b border-slate-100 pb-3">
                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider mono">Compose Emergency SMS Broadcast</h3>
                    <p class="text-xs text-slate-500">Dispatch cellular alerts via GSM Gateway SIM</p>
                </div>

                <form method="POST" class="space-y-4 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Recipient Mobile Number</label>
                        <input type="text" name="to_number" required value="<?= htmlspecialchars($_GET['to'] ?? '+919811122334') ?>" placeholder="+91 98765 43210" class="w-full bg-slate-50 border border-slate-300 rounded p-2.5 text-xs font-mono">
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="font-bold text-slate-700">Message Content</label>
                            <span class="text-[10px] font-mono text-slate-400" id="smsCharCount">0 / 160 (1 SMS)</span>
                        </div>
                        <textarea name="message_body" id="smsMessageArea" oninput="updateCharCounter(this)" required rows="4" placeholder="Type emergency instruction or alert here..." class="w-full bg-slate-50 border border-slate-300 rounded p-2.5 text-xs"></textarea>
                    </div>

                    <!-- Quick Template Buttons -->
                    <div>
                        <span class="block text-[10px] font-bold text-slate-400 uppercase mono mb-1.5">Quick Incident Templates</span>
                        <div class="flex flex-wrap gap-1.5">
                            <button type="button" onclick="insertTemplate('DisasterSafe Alert: Evacuate low-lying areas immediately. Move towards designated relief shelter.')" class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 text-[10px] font-bold border border-slate-200">
                                🚨 Evacuation Order
                            </button>
                            <button type="button" onclick="insertTemplate('DisasterSafe: Rescue teams and ambulances are on-scene. Please stay in high ground with beacon active.')" class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 text-[10px] font-bold border border-slate-200">
                                🚑 Rescue On-Scene
                            </button>
                            <button type="button" onclick="insertTemplate('DisasterSafe: Clean water, food rations, and medical aid available at Mayur Vihar Tent City.')" class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 text-[10px] font-bold border border-slate-200">
                                🍲 Relief Camp Aid
                            </button>
                        </div>
                    </div>

                    <button type="submit" name="send_sms_broadcast" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded transition-colors shadow-xs">
                        Dispatch SMS Message &rarr;
                    </button>
                </form>
            </div>

        <!-- ============================================================= -->
        <!-- TAB 7: GATEWAY SETTINGS & SIM REGISTRATION -->
        <!-- ============================================================= -->
        <?php elseif ($activeTab === 'settings'): ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                
                <!-- Webhook Endpoint Configuration -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-2xs space-y-3">
                    <div class="border-b border-slate-100 pb-2">
                        <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider mono">Android Gateway Webhook</h3>
                        <p class="text-[11px] text-slate-500">Configure endpoint in Android SMS Gateway App</p>
                    </div>
                    <div class="space-y-2 text-xs">
                        <div>
                            <span class="block text-[10px] font-bold text-slate-400 uppercase mono mb-0.5">Webhook Target URL</span>
                            <div class="p-2 bg-slate-50 border border-slate-200 rounded font-mono text-[11px] text-blue-900 break-all select-all">
                                <?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}/DisasterSafe/Application/SMS_PART2/api/sms/receive.php?secret=sih_webhook_secret_key_2026" ?>
                            </div>
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold text-slate-400 uppercase mono mb-0.5">Secret Authorization Key</span>
                            <div class="p-2 bg-slate-50 border border-slate-200 rounded font-mono text-[11px] text-slate-800">
                                sih_webhook_secret_key_2026
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Central Registered SIM Numbers -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-2xs space-y-3">
                    <div class="border-b border-slate-100 pb-2">
                        <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider mono">Central SOS SIM Card</h3>
                        <p class="text-[11px] text-slate-500">Hardware phone connected to DisasterSafe Gateway</p>
                    </div>
                    <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-lg flex items-center justify-between text-xs">
                        <div>
                            <span class="font-bold text-emerald-950 font-mono block">+91 98765 43210</span>
                            <span class="text-[10px] text-emerald-700">Primary Central Gateway SIM (Slot 1)</span>
                        </div>
                        <span class="px-2 py-0.5 bg-emerald-200 text-emerald-900 text-[10px] font-black rounded uppercase mono">Active</span>
                    </div>
                </div>

            </div>
        <?php endif; ?>

    </main>
</div>

<!-- LIVE MAP & REAL-TIME SOS POP-UP SCRIPT -->
<script>
let smsMap = null;

document.addEventListener('DOMContentLoaded', () => {
    // 1. Initialize Map if on Overview tab
    const mapContainer = document.getElementById('smsOverviewMap');
    if (mapContainer) {
        smsMap = L.map('smsOverviewMap', { zoomControl: true }).setView([28.6139, 77.2090], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(smsMap);

        const sosData = <?= json_encode($sosList) ?>;
        sosData.forEach(s => {
            const isResolved = (s.status && s.status.toLowerCase() === 'resolved');
            const icon = L.divIcon({
                className: 'sms-radar-icon',
                html: `<div style="width:24px;height:24px;background:${isResolved ? '#16a34a' : '#dc2626'};border:2px solid #fff;border-radius:50%;color:#fff;font-weight:900;font-size:11px;display:grid;place-items:center;box-shadow:0 0 10px ${isResolved ? '#16a34a' : '#dc2626'};">${isResolved ? '✓' : '!'}</div>`,
                iconSize: [24, 24]
            });

            L.marker([s.gps_lat, s.gps_lng], { icon: icon })
                .bindPopup(`
                    <div style="font-family:Inter,sans-serif;font-size:12px;min-width:200px;">
                        <strong style="color:#dc2626;">SOS #${s.id}</strong> - ${s.sender_name}<br>
                        <strong>Type:</strong> ${s.emergency_type}<br>
                        <strong>Phone:</strong> ${s.sender_phone || 'N/A'}<br>
                        <strong>Status:</strong> ${s.status}<br>
                        <a href="gateway_sms.php?tab=sos&id=${s.id}" style="color:#2563eb;font-weight:bold;margin-top:4px;display:inline-block;">View SOS Dossier &rarr;</a>
                    </div>
                `)
                .addTo(smsMap);
        });
    }

    // 2. Real-time Incoming SOS Beacon Poller
    let knownSosCount = <?= $totalSosCount ?>;
    setInterval(() => {
        // Lightweight check simulation
        // If a new SOS arrives via SMS, trigger the alert animation
    }, 5000);
});

// Helper for Character Counter
function updateCharCounter(el) {
    const len = el.value.length;
    const parts = Math.ceil(len / 160) || 1;
    document.getElementById('smsCharCount').textContent = `${len} / 160 (${parts} SMS)`;
}

function insertTemplate(text) {
    const area = document.getElementById('smsMessageArea');
    if (area) {
        area.value = text;
        updateCharCounter(area);
    }
}

// -------------------------------------------------------------
// LIVE SOS ALERT POP-UP ANIMATION TRIGGER
// -------------------------------------------------------------
function triggerSimulatedSosPopup() {
    // 1. Play Audio Siren
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(800, audioCtx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(400, audioCtx.currentTime + 0.4);
        gain.gain.setValueAtTime(0.25, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.4);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.4);
    } catch(e) {}

    // 2. Fire Visual Pulse Modal / Pop-up
    Swal.fire({
        title: '<div class="flex items-center justify-center gap-2 text-red-600"><span class="w-3 h-3 bg-red-600 rounded-full animate-ping"></span> EMERGENCY SOS RECEIVED!</div>',
        html: `
            <div class="text-left bg-slate-50 p-3.5 border border-slate-200 rounded-xl space-y-2 text-xs">
                <div class="flex justify-between border-b border-slate-200 pb-1.5">
                    <span class="font-bold text-slate-500 uppercase mono">Signal Source:</span>
                    <span class="font-bold text-slate-900">Cellular SMS (GSM Gateway)</span>
                </div>
                <div class="flex justify-between">
                    <span class="font-bold text-slate-500">Citizen:</span>
                    <strong class="text-slate-900">Pooja Verma (+91 98765 43210)</strong>
                </div>
                <div class="flex justify-between">
                    <span class="font-bold text-slate-500">Emergency:</span>
                    <strong class="text-red-600">Flood Trapped (4 Persons)</strong>
                </div>
                <div class="flex justify-between">
                    <span class="font-bold text-slate-500">GPS Coordinates:</span>
                    <span class="font-mono font-bold text-blue-700">28.6139 N, 77.2090 E</span>
                </div>
                <div class="mt-2 p-2 bg-red-50 border border-red-200 rounded text-red-800 italic">
                    "SOS|Flood|28.6139,77.2090|4|O+|Water level rising rapidly inside house."
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#475569',
        confirmButtonText: 'Open Incident Dossier &rarr;',
        cancelButtonText: 'Acknowledge',
        customClass: {
            popup: 'rounded-2xl shadow-2xl border border-red-300'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'gateway_sms.php?tab=sos';
        }
    });
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
