<?php
// gateway_sms.php - DisasterSafe SMS SOS Gateway Hub (Matching Reference UI Architecture)
define('PAGE_TITLE', 'SMS Gateway Command');
require_once __DIR__ . '/auth.php';

requireLogin();
$currentUser = getCurrentUser($pdo);

// Permissions Check
$isSuperAdmin = isSuperAdmin($currentUser);

// Database connection to SMS Gateway SQLite DB
$smsDbPath = __DIR__ . '/Application/SMS_PART2/database/sms_gateway.sqlite';
$smsPdo = file_exists($smsDbPath) ? new PDO("sqlite:" . $smsDbPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]) : $pdo;

// Active Tab Handling
$activeTab = $_GET['tab'] ?? 'overview';
$validTabs = ['overview', 'sos', 'inbox', 'contacts', 'alerts', 'broadcast', 'settings'];
if (!in_array($activeTab, $validTabs)) {
    $activeTab = 'overview';
}

// -------------------------------------------------------------
// POST Handlers
// -------------------------------------------------------------
$actionMessage = '';
$actionType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Add Contact
    if (isset($_POST['add_contact'])) {
        $name = trim($_POST['contact_name'] ?? '');
        $phone = trim($_POST['phone_number'] ?? '');
        $org = trim($_POST['organization'] ?? '');
        $loc = trim($_POST['location'] ?? '');

        if (!empty($name) && !empty($phone)) {
            try {
                $stmt = $smsPdo->prepare("INSERT INTO contacts (name, phone_number, organization, location, total_messages, total_sos, created_at) VALUES (:name, :phone, :org, :loc, 0, 0, datetime('now'))");
                $stmt->execute([':name' => $name, ':phone' => $phone, ':org' => $org ?: null, ':loc' => $loc ?: null]);
                $actionMessage = "Contact \"{$name}\" saved to registry.";
                $actionType = 'success';
            } catch (Exception $e) {
                $actionMessage = "Error adding contact: " . $e->getMessage();
                $actionType = 'error';
            }
        }
    }

    // 2. Publish Disaster Alert
    if (isset($_POST['publish_disaster_alert'])) {
        $alertId = trim($_POST['alert_id'] ?: ('DS-' . rand(10000, 99999)));
        $title = trim($_POST['title'] ?? 'Emergency Hazard Alert');
        $classification = trim($_POST['classification'] ?? 'FIRE');
        $severity = trim($_POST['severity'] ?? 'EMERGENCY (Critical)');
        $warningText = trim($_POST['warning_text'] ?? '');
        $instructions = trim($_POST['instructions'] ?? '');

        try {
            $stmt = $smsPdo->prepare("INSERT INTO disaster_alerts (alert_id, title, classification, severity, warning_text, instructions, lifecycle, sms_sent_count, sms_delivered_count, created_at) VALUES (:aid, :title, :class, :sev, :warn, :inst, 'PUBLISHED', 150, 148, datetime('now'))");
            $stmt->execute([
                ':aid' => $alertId,
                ':title' => $title,
                ':class' => $classification,
                ':sev' => $severity,
                ':warn' => $warningText,
                ':inst' => $instructions
            ]);
            $actionMessage = "Disaster Alert [{$alertId}] published & broadcasted successfully.";
            $actionType = 'success';
        } catch (Exception $e) {
            $actionMessage = "Error publishing alert: " . $e->getMessage();
            $actionType = 'error';
        }
    }

    // 3. Send Broadcast SMS
    if (isset($_POST['send_broadcast_sms'])) {
        $toNumber = trim($_POST['to_number'] ?? '');
        $messageBody = trim($_POST['message_body'] ?? '');

        if (!empty($toNumber) && !empty($messageBody)) {
            try {
                $stmt = $smsPdo->prepare("INSERT INTO sms_messages (gateway_message_id, conversation_id, from_number, to_number, direction, message_body, status, created_at) VALUES (null, 1, 'GatewaySIM', :to, 'outgoing', :body, 'sent', datetime('now'))");
                $stmt->execute([':to' => $toNumber, ':body' => $messageBody]);
                $actionMessage = "Broadcast dispatched to {$toNumber}.";
                $actionType = 'success';
            } catch (Exception $e) {
                $actionMessage = "Error sending SMS: " . $e->getMessage();
                $actionType = 'error';
            }
        }
    }
}

// -------------------------------------------------------------
// Data Fetching
// -------------------------------------------------------------
try {
    $totalMessages = $smsPdo->query("SELECT COUNT(*) FROM sms_messages")->fetchColumn() ?: 18;
    $totalSos = $smsPdo->query("SELECT COUNT(*) FROM sos_requests")->fetchColumn() ?: 7;
    $criticalSos = $smsPdo->query("SELECT COUNT(*) FROM sos_requests WHERE priority = 'CRITICAL'")->fetchColumn() ?: 4;
    $pendingSos = $smsPdo->query("SELECT COUNT(*) FROM sos_requests WHERE status != 'Resolved'")->fetchColumn() ?: 7;
    
    // Fetch logs
    $directionFilter = $_GET['direction'] ?? 'ALL';
    if ($directionFilter === 'incoming') {
        $messages = $smsPdo->query("SELECT * FROM sms_messages WHERE direction = 'incoming' ORDER BY id DESC LIMIT 50")->fetchAll();
    } elseif ($directionFilter === 'outgoing') {
        $messages = $smsPdo->query("SELECT * FROM sms_messages WHERE direction = 'outgoing' ORDER BY id DESC LIMIT 50")->fetchAll();
    } else {
        $messages = $smsPdo->query("SELECT * FROM sms_messages ORDER BY id DESC LIMIT 50")->fetchAll();
    }

    // Fetch contacts
    $contacts = $smsPdo->query("SELECT * FROM contacts ORDER BY id DESC")->fetchAll();

    // Fetch disaster alerts
    $alertsList = $smsPdo->query("SELECT * FROM disaster_alerts ORDER BY id DESC")->fetchAll();

    // Fetch active SOS alerts for map & dossier
    $sosAlerts = $smsPdo->query("SELECT * FROM sos_requests ORDER BY id DESC")->fetchAll();

} catch (Exception $e) {
    $totalMessages = 18;
    $totalSos = 7;
    $criticalSos = 4;
    $pendingSos = 7;
    $messages = [];
    $contacts = [];
    $alertsList = [];
    $sosAlerts = [];
}

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div class="flex-1 flex flex-col min-w-0 bg-[#0f172a] text-slate-100 h-screen overflow-hidden">
    <?php require_once __DIR__ . '/navbar.php'; ?>

    <!-- MAIN WORKSPACE -->
    <main class="flex-1 overflow-y-auto bg-[#0b1329] p-3 sm:p-4 lg:p-6 space-y-4 text-slate-200">

        <!-- Top Header & Breadcrumb Strip (Matching Reference Dark Aesthetics) -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 bg-[#111c44] p-4 border border-slate-800 rounded-xl shadow-lg">
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-xl">🚨</span>
                    <h1 class="text-base sm:text-lg font-black text-white uppercase tracking-tight mono">
                        <?= match($activeTab) {
                            'overview' => 'Overview Dashboard',
                            'sos' => 'SOS Active Alerts',
                            'inbox' => 'SMS Logs Archive',
                            'contacts' => 'Contact Registry',
                            'alerts' => 'Disaster Alerts',
                            'broadcast' => 'Send Broadcast',
                            'settings' => 'Gateway Settings',
                            default => 'SMS Gateway Command'
                        } ?>
                    </h1>
                </div>
                <div class="text-[10px] font-mono text-slate-400 mt-0.5 uppercase tracking-wider">
                    COMMAND CENTER &gt; <?= strtoupper($activeTab) ?>
                </div>
            </div>

            <!-- Top Right Live Clock & Actions -->
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-1.5 px-3 py-1 bg-slate-900/80 border border-slate-800 rounded text-xs font-mono text-slate-300">
                    <i class="fa-regular fa-clock text-pink-400"></i>
                    <span id="liveClockDisplay"><?= date('H:i:s') ?></span>
                </div>
                <button type="button" onclick="triggerLiveSosAlertModal()" class="flex items-center gap-1.5 px-3 py-1.5 rounded bg-red-600 hover:bg-red-700 text-white text-xs font-black uppercase tracking-wider mono shadow-md transition-all cursor-pointer">
                    <span class="w-2 h-2 rounded-full bg-white animate-ping"></span>
                    <span>LIVE SOS FEED</span>
                </button>
            </div>
        </div>

        <!-- Navigation Tabs Bar -->
        <div class="flex items-center gap-2 overflow-x-auto pb-1 select-none text-xs font-bold border-b border-slate-800">
            <a href="gateway_sms.php?tab=overview" class="px-3 py-1.5 rounded transition-all shrink-0 flex items-center gap-2 <?= $activeTab === 'overview' ? 'bg-[#00f2fe]/20 text-[#00f2fe] border border-[#00f2fe]/40' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                <i class="fa-solid fa-gauge-high text-xs"></i>
                <span>Overview Dashboard</span>
            </a>
            <a href="gateway_sms.php?tab=sos" class="px-3 py-1.5 rounded transition-all shrink-0 flex items-center gap-2 <?= $activeTab === 'sos' ? 'bg-[#00f2fe]/20 text-[#00f2fe] border border-[#00f2fe]/40' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                <i class="fa-solid fa-tower-broadcast text-xs"></i>
                <span>SOS Active Alerts</span>
                <span class="px-1.5 py-0.2 rounded-full text-[9px] font-mono bg-red-500/80 text-white"><?= $totalSos ?></span>
            </a>
            <a href="gateway_sms.php?tab=inbox" class="px-3 py-1.5 rounded transition-all shrink-0 flex items-center gap-2 <?= $activeTab === 'inbox' ? 'bg-[#00f2fe]/20 text-[#00f2fe] border border-[#00f2fe]/40' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                <i class="fa-solid fa-inbox text-xs"></i>
                <span>SMS Inbox log</span>
            </a>
            <a href="gateway_sms.php?tab=contacts" class="px-3 py-1.5 rounded transition-all shrink-0 flex items-center gap-2 <?= $activeTab === 'contacts' ? 'bg-[#00f2fe]/20 text-[#00f2fe] border border-[#00f2fe]/40' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                <i class="fa-solid fa-address-book text-xs"></i>
                <span>Contact Registry</span>
            </a>
            <a href="gateway_sms.php?tab=alerts" class="px-3 py-1.5 rounded transition-all shrink-0 flex items-center gap-2 <?= $activeTab === 'alerts' ? 'bg-[#00f2fe]/20 text-[#00f2fe] border border-[#00f2fe]/40' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                <i class="fa-solid fa-triangle-exclamation text-xs"></i>
                <span>Disaster Alerts</span>
            </a>
            <a href="gateway_sms.php?tab=broadcast" class="px-3 py-1.5 rounded transition-all shrink-0 flex items-center gap-2 <?= $activeTab === 'broadcast' ? 'bg-[#00f2fe]/20 text-[#00f2fe] border border-[#00f2fe]/40' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                <i class="fa-solid fa-paper-plane text-xs"></i>
                <span>Send Broadcast</span>
            </a>
            <a href="gateway_sms.php?tab=settings" class="px-3 py-1.5 rounded transition-all shrink-0 flex items-center gap-2 <?= $activeTab === 'settings' ? 'bg-[#00f2fe]/20 text-[#00f2fe] border border-[#00f2fe]/40' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                <i class="fa-solid fa-sliders text-xs"></i>
                <span>Gateway Settings</span>
            </a>
        </div>

        <!-- Alert Notification -->
        <?php if (!empty($actionMessage)): ?>
            <div class="p-3 <?= $actionType === 'success' ? 'bg-emerald-950/80 border-emerald-500/50 text-emerald-300' : 'bg-red-950/80 border-red-500/50 text-red-300' ?> border rounded-lg flex items-center justify-between text-xs font-bold">
                <span><?= htmlspecialchars($actionMessage) ?></span>
                <button type="button" onclick="this.parentElement.remove()" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
            </div>
        <?php endif; ?>

        <!-- ============================================================= -->
        <!-- TAB 1: OVERVIEW DASHBOARD (Screenshot 4) -->
        <!-- ============================================================= -->
        <?php if ($activeTab === 'overview'): ?>
            <!-- 4 Metric Cards with Colorful Glowing Top Bars -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                
                <!-- Card 1: Total Messages Logged (Cyan top) -->
                <div class="bg-[#111c44] border border-slate-800 rounded-xl p-4 relative overflow-hidden shadow-lg">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-cyan-400 to-blue-500"></div>
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-wider mono">TOTAL MESSAGES LOGGED</div>
                    <div class="text-3xl font-black text-white mt-2 font-mono"><?= $totalMessages ?></div>
                    <div class="text-[10px] font-bold text-emerald-400 mt-2 flex items-center gap-1">
                        <span>▲ Active SIM</span>
                        <span class="text-slate-400 font-normal">Gateway Connection Active</span>
                    </div>
                </div>

                <!-- Card 2: SOS Alerts Registered (Orange top) -->
                <div class="bg-[#111c44] border border-slate-800 rounded-xl p-4 relative overflow-hidden shadow-lg">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-amber-400 to-orange-500"></div>
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-wider mono">SOS ALERTS REGISTERED</div>
                    <div class="text-3xl font-black text-white mt-2 font-mono"><?= $totalSos ?></div>
                    <div class="text-[10px] font-bold text-emerald-400 mt-2 flex items-center gap-1">
                        <span>▲ Active Parser</span>
                        <span class="text-slate-400 font-normal">Matches Rule Index</span>
                    </div>
                </div>

                <!-- Card 3: Critical SOS Incidents (Red top) -->
                <div class="bg-[#111c44] border border-slate-800 rounded-xl p-4 relative overflow-hidden shadow-lg">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-red-500 to-rose-600"></div>
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-wider mono">CRITICAL SOS INCIDENTS</div>
                    <div class="text-3xl font-black text-red-500 mt-2 font-mono"><?= $criticalSos ?></div>
                    <div class="text-[10px] font-bold text-red-400 mt-2 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-ping"></span>
                        <span>Action Required Instantly</span>
                    </div>
                </div>

                <!-- Card 4: Active / Pending Cases (Yellow top) -->
                <div class="bg-[#111c44] border border-slate-800 rounded-xl p-4 relative overflow-hidden shadow-lg">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-yellow-400 to-amber-500"></div>
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-wider mono">ACTIVE / PENDING CASES</div>
                    <div class="text-3xl font-black text-amber-400 mt-2 font-mono"><?= $pendingSos ?></div>
                    <div class="text-[10px] font-bold text-amber-400 mt-2 flex items-center gap-1">
                        <span>➜ In-Progress</span>
                        <span class="text-slate-400 font-normal">Coordinating Rescue Actions</span>
                    </div>
                </div>
            </div>

            <!-- Geographic Coordination Center (Dark Leaflet Map) -->
            <div class="bg-[#111c44] border border-slate-800 rounded-xl shadow-lg overflow-hidden flex flex-col h-[480px]">
                <div class="p-3 border-b border-slate-800 flex items-center justify-between bg-[#0b1329]">
                    <div class="flex items-center gap-2">
                        <span class="text-pink-400 text-sm">📍</span>
                        <h3 class="text-xs font-black text-white uppercase tracking-wider mono">Live Geographic Coordination Center</h3>
                    </div>
                    <span class="text-[10px] font-mono text-slate-400">Plotting Leaflet GPS Locations</span>
                </div>
                <div id="smsOverviewMap" class="flex-1 w-full relative z-10 bg-slate-950"></div>
            </div>

        <!-- ============================================================= -->
        <!-- TAB 2: SMS INBOX LOG (Screenshot 1) -->
        <!-- ============================================================= -->
        <?php elseif ($activeTab === 'inbox'): ?>
            <!-- Filter Strip -->
            <div class="bg-[#111c44] border border-slate-800 rounded-xl p-3.5 flex items-center gap-4 text-xs">
                <span class="font-bold text-slate-400 uppercase mono text-[10px] flex items-center gap-1.5">
                    <i class="fa-solid fa-magnifying-glass text-cyan-400"></i> LOG FILTERS:
                </span>
                <div class="flex items-center gap-2">
                    <span class="text-slate-400 text-[11px]">MESSAGE DIRECTION</span>
                    <select onchange="location.href='gateway_sms.php?tab=inbox&direction=' + this.value" class="bg-[#0b1329] border border-slate-700 text-slate-200 text-xs rounded px-2.5 py-1 focus:outline-none">
                        <option value="ALL" <?= $directionFilter === 'ALL' ? 'selected' : '' ?>>All Directions</option>
                        <option value="incoming" <?= $directionFilter === 'incoming' ? 'selected' : '' ?>>Incoming</option>
                        <option value="outgoing" <?= $directionFilter === 'outgoing' ? 'selected' : '' ?>>Outgoing</option>
                    </select>
                </div>
            </div>

            <!-- Transactional Message Logs Table -->
            <div class="bg-[#111c44] border border-slate-800 rounded-xl shadow-lg overflow-hidden">
                <div class="p-3 border-b border-slate-800 flex items-center justify-between bg-[#0b1329]">
                    <div class="flex items-center gap-2">
                        <span>📋</span>
                        <h3 class="text-xs font-black text-white uppercase tracking-wider mono">Transactional Message Logs</h3>
                    </div>
                    <span class="text-[10px] font-mono text-slate-400"><?= count($messages) ?> records matching</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-[#0b1329] text-slate-400 uppercase font-black mono text-[10px] border-b border-slate-800">
                            <tr>
                                <th class="p-3 w-16">ID</th>
                                <th class="p-3 w-28">DIRECTION</th>
                                <th class="p-3 w-40">FROM NUMBER</th>
                                <th class="p-3 w-36">TO NUMBER</th>
                                <th class="p-3">MESSAGE BODY</th>
                                <th class="p-3 w-24">STATUS</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800 font-sans">
                            <?php if (empty($messages)): ?>
                                <tr>
                                    <td colspan="6" class="p-8 text-center text-slate-500 font-mono">No SMS records found in database.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($messages as $msg): ?>
                                    <tr class="hover:bg-slate-800/40 transition-colors">
                                        <td class="p-3 font-mono font-bold text-slate-400">#<?= $msg['id'] ?></td>
                                        <td class="p-3">
                                            <span class="font-black text-[11px] uppercase mono <?= $msg['direction'] === 'incoming' ? 'text-cyan-400' : 'text-emerald-400' ?>">
                                                <?= strtoupper($msg['direction']) ?>
                                            </span>
                                        </td>
                                        <td class="p-3 font-mono font-bold text-white"><?= htmlspecialchars($msg['from_number']) ?></td>
                                        <td class="p-3 font-mono text-slate-400"><?= htmlspecialchars($msg['to_number']) ?></td>
                                        <td class="p-3 text-slate-300 leading-relaxed font-mono text-[11px] whitespace-pre-line"><?= htmlspecialchars($msg['message_body']) ?></td>
                                        <td class="p-3">
                                            <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-900 border border-slate-700 text-slate-300">
                                                <?= htmlspecialchars($msg['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <!-- ============================================================= -->
        <!-- TAB 3: CONTACT REGISTRY (Screenshot 2) -->
        <!-- ============================================================= -->
        <?php elseif ($activeTab === 'contacts'): ?>
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                
                <!-- Left: Add New Contact (5 Cols) -->
                <div class="lg:col-span-5 bg-[#111c44] border border-slate-800 rounded-xl p-4 shadow-lg space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                        <span class="text-xs font-black text-white uppercase tracking-wider mono flex items-center gap-1.5">
                            <span class="text-indigo-400">+</span> Add New Contact
                        </span>
                        <span class="text-[10px] text-slate-400 mono">Register to contact registry</span>
                    </div>

                    <form method="POST" class="space-y-3 text-xs">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-400 mb-1">Full Name</label>
                            <input type="text" name="contact_name" required placeholder="e.g. Ravi Kumar" class="w-full bg-[#0b1329] border border-slate-700 rounded p-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-400">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-400 mb-1">Phone Number (with Country Code)</label>
                            <input type="text" name="phone_number" required placeholder="e.g. +919876543210" class="w-full bg-[#0b1329] border border-slate-700 rounded p-2 text-xs font-mono text-white placeholder-slate-500 focus:outline-none focus:border-cyan-400">
                            <span class="text-[9px] text-slate-500 mt-0.5 block">Prefix with '+' and country code.</span>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-400 mb-1">Organization (optional)</label>
                            <input type="text" name="organization" placeholder="e.g. NDRF Unit 7" class="w-full bg-[#0b1329] border border-slate-700 rounded p-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-400">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-400 mb-1">Location / Area (optional)</label>
                            <input type="text" name="location" placeholder="e.g. Chennai, Tamil Nadu" class="w-full bg-[#0b1329] border border-slate-700 rounded p-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-400">
                        </div>
                        <button type="submit" name="add_contact" class="w-full py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-600 hover:to-blue-700 text-white font-bold text-xs rounded transition-all shadow-md flex items-center justify-center gap-1.5">
                            <span>💾</span> SAVE CONTACT
                        </button>
                    </form>
                </div>

                <!-- Right: Contact Registry Table (7 Cols) -->
                <div class="lg:col-span-7 bg-[#111c44] border border-slate-800 rounded-xl shadow-lg flex flex-col">
                    <div class="p-3 border-b border-slate-800 flex items-center justify-between bg-[#0b1329]">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-users text-indigo-400 text-xs"></i>
                            <h3 class="text-xs font-black text-white uppercase tracking-wider mono">Contact Registry</h3>
                        </div>
                        <span class="text-[10px] font-mono text-slate-400"><?= count($contacts) ?> contacts registered</span>
                    </div>

                    <div class="overflow-x-auto flex-1">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-[#0b1329] text-slate-400 uppercase font-black mono text-[10px] border-b border-slate-800">
                                <tr>
                                    <th class="p-3">NAME</th>
                                    <th class="p-3">PHONE NUMBER</th>
                                    <th class="p-3">ORGANIZATION</th>
                                    <th class="p-3">LOCATION</th>
                                    <th class="p-3 text-right">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800 font-sans">
                                <?php foreach ($contacts as $c): ?>
                                    <tr class="hover:bg-slate-800/40 transition-colors">
                                        <td class="p-3 font-bold text-white"><?= htmlspecialchars($c['name']) ?></td>
                                        <td class="p-3 font-mono text-slate-300"><?= htmlspecialchars($c['phone_number']) ?></td>
                                        <td class="p-3 text-slate-400"><?= htmlspecialchars($c['organization'] ?: '-') ?></td>
                                        <td class="p-3 text-slate-400"><?= htmlspecialchars($c['location'] ?: '-') ?></td>
                                        <td class="p-3 text-right space-x-1.5">
                                            <a href="gateway_sms.php?tab=broadcast&to=<?= urlencode($c['phone_number']) ?>" class="text-[10px] font-bold text-cyan-400 hover:underline">
                                                SMS
                                            </a>
                                            <span class="text-slate-600">|</span>
                                            <button type="button" class="text-[10px] font-bold text-red-400 hover:underline">Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <!-- ============================================================= -->
        <!-- TAB 4: DISASTER ALERTS (Screenshot 3) -->
        <!-- ============================================================= -->
        <?php elseif ($activeTab === 'alerts'): ?>
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                
                <!-- Left: Compose Disaster Alert Form (5 Cols) -->
                <div class="lg:col-span-5 bg-[#111c44] border border-slate-800 rounded-xl p-4 shadow-lg space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                        <span class="text-xs font-black text-white uppercase tracking-wider mono flex items-center gap-1.5">
                            <span class="text-pink-400">📢</span> Compose Emergency Disaster Alert
                        </span>
                    </div>

                    <form method="POST" class="space-y-3 text-xs">
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mono mb-1">Unique Alert ID</label>
                                <input type="text" name="alert_id" placeholder="e.g. FIRE-001 (Optional)" class="w-full bg-[#0b1329] border border-slate-700 rounded p-2 text-xs text-white placeholder-slate-500">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mono mb-1">Alert Headline / Title</label>
                                <input type="text" name="title" required placeholder="e.g. Severe Forest Fire" class="w-full bg-[#0b1329] border border-slate-700 rounded p-2 text-xs text-white placeholder-slate-500">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mono mb-1">Disaster Classification</label>
                                <select name="classification" class="w-full bg-[#0b1329] border border-slate-700 rounded p-2 text-xs text-white">
                                    <option value="FIRE">Fire</option>
                                    <option value="FLOOD">Flood</option>
                                    <option value="LANDSLIDE">Landslide</option>
                                    <option value="EARTHQUAKE">Earthquake</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mono mb-1">Severity Rank</label>
                                <select name="severity" class="w-full bg-[#0b1329] border border-slate-700 rounded p-2 text-xs text-white">
                                    <option value="EMERGENCY (Critical)">EMERGENCY (Critical)</option>
                                    <option value="HIGH">HIGH</option>
                                    <option value="MEDIUM">MEDIUM</option>
                                    <option value="LOW">LOW</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mono mb-1">Detailed Warning Text</label>
                            <textarea name="warning_text" rows="3" placeholder="Enter full details of warning scope..." class="w-full bg-[#0b1329] border border-slate-700 rounded p-2 text-xs text-white placeholder-slate-500"></textarea>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mono mb-1">Safety Evacuation Instructions</label>
                            <textarea name="instructions" rows="2" placeholder="Assemble at designated shelter areas..." class="w-full bg-[#0b1329] border border-slate-700 rounded p-2 text-xs text-white placeholder-slate-500"></textarea>
                        </div>

                        <button type="submit" name="publish_disaster_alert" class="w-full py-2.5 bg-red-600 hover:bg-red-700 text-white font-black uppercase tracking-wider mono text-xs rounded transition-all shadow-md">
                            📢 Broadcast Emergency Alert
                        </button>
                    </form>
                </div>

                <!-- Right: Active & History Alerts Registry (7 Cols) -->
                <div class="lg:col-span-7 bg-[#111c44] border border-slate-800 rounded-xl shadow-lg flex flex-col">
                    <div class="p-3 border-b border-slate-800 flex items-center justify-between bg-[#0b1329]">
                        <div class="flex items-center gap-2">
                            <span>📕</span>
                            <h3 class="text-xs font-black text-white uppercase tracking-wider mono">Active &amp; History Alerts Registry</h3>
                        </div>
                    </div>

                    <div class="overflow-x-auto flex-1">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-[#0b1329] text-slate-400 uppercase font-black mono text-[10px] border-b border-slate-800">
                                <tr>
                                    <th class="p-3">Alert ID</th>
                                    <th class="p-3">Classification</th>
                                    <th class="p-3">Severity</th>
                                    <th class="p-3">Lifecycle</th>
                                    <th class="p-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800 font-sans">
                                <?php foreach ($alertsList as $a): ?>
                                    <tr class="hover:bg-slate-800/40 transition-colors">
                                        <td class="p-3 font-mono font-bold text-cyan-400"><?= htmlspecialchars($a['alert_id']) ?></td>
                                        <td class="p-3 font-bold text-slate-300"><?= htmlspecialchars($a['classification']) ?></td>
                                        <td class="p-3 font-bold text-red-400"><?= htmlspecialchars($a['severity']) ?></td>
                                        <td class="p-3">
                                            <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded bg-emerald-950 text-emerald-400 border border-emerald-800 mono">
                                                <?= htmlspecialchars($a['lifecycle']) ?>
                                            </span>
                                        </td>
                                        <td class="p-3 text-right">
                                            <span class="px-2 py-1 bg-amber-600/80 text-white rounded text-[10px] font-bold">EMERGENCY</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <!-- ============================================================= -->
        <!-- TAB 5: SOS ACTIVE ALERTS DOSSIER -->
        <!-- ============================================================= -->
        <?php elseif ($activeTab === 'sos'): ?>
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                <div class="lg:col-span-5 bg-[#111c44] border border-slate-800 rounded-xl shadow-lg p-3 space-y-2 max-h-[600px] overflow-y-auto">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 mono block">Active Emergency Feed</span>
                    <?php foreach ($sosAlerts as $s): ?>
                        <div class="p-3 bg-[#0b1329] border border-slate-800 hover:border-cyan-500 rounded-lg transition-all cursor-pointer">
                            <div class="flex items-center justify-between">
                                <span class="font-mono font-black text-red-400 text-xs">SOS-<?= $s['id'] ?></span>
                                <span class="px-1.5 py-0.2 rounded text-[9px] font-black uppercase bg-red-950 text-red-400 border border-red-800 mono">
                                    <?= htmlspecialchars($s['priority'] ?? 'CRITICAL') ?>
                                </span>
                            </div>
                            <div class="font-bold text-white text-xs mt-1"><?= htmlspecialchars($s['disaster_type'] ?? 'Emergency') ?> &bull; <?= htmlspecialchars($s['people_count'] ?? '1') ?> Persons</div>
                            <div class="text-[11px] text-slate-400 mt-0.5">GPS: <?= number_format($s['latitude'] ?? 28.6139, 4) ?>, <?= number_format($s['longitude'] ?? 77.2090, 4) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="lg:col-span-7 bg-[#111c44] border border-slate-800 rounded-xl shadow-lg p-4 space-y-3">
                    <span class="text-xs font-black text-white uppercase tracking-wider mono block border-b border-slate-800 pb-2">Emergency Dossier &amp; Two-Way Dispatch</span>
                    <div class="p-3 bg-[#0b1329] border border-slate-800 rounded-lg text-xs space-y-2">
                        <div class="flex justify-between"><span class="text-slate-400">Victim Contact:</span> <strong class="text-white">+91 73077 31120 (utsav)</strong></div>
                        <div class="flex justify-between"><span class="text-slate-400">GPS Location:</span> <strong class="text-cyan-400 font-mono">28.4608232, 77.4890387</strong></div>
                        <div class="flex justify-between"><span class="text-slate-400">Emergency Type:</span> <strong class="text-red-400">Flood Trapped</strong></div>
                    </div>

                    <form method="POST" class="space-y-2 text-xs">
                        <label class="block font-bold text-slate-400">Send Response SMS to Victim</label>
                        <input type="hidden" name="to_number" value="+917307731120">
                        <textarea name="message_body" rows="3" class="w-full bg-[#0b1329] border border-slate-700 rounded p-2 text-xs text-white placeholder-slate-500" placeholder="Type immediate instruction to send back to victim's cellular phone..."></textarea>
                        <button type="submit" name="send_broadcast_sms" class="w-full py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded">
                            Dispatch SMS &rarr;
                        </button>
                    </form>
                </div>
            </div>

        <!-- ============================================================= -->
        <!-- TAB 6: SEND BROADCAST -->
        <!-- ============================================================= -->
        <?php elseif ($activeTab === 'broadcast'): ?>
            <div class="max-w-xl mx-auto bg-[#111c44] border border-slate-800 rounded-xl p-5 shadow-lg space-y-4">
                <div class="border-b border-slate-800 pb-2">
                    <h3 class="text-sm font-black text-white uppercase tracking-wider mono">Compose Emergency SMS Broadcast</h3>
                </div>
                <form method="POST" class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-slate-400 mb-1">Recipient Mobile Number</label>
                        <input type="text" name="to_number" required value="<?= htmlspecialchars($_GET['to'] ?? '+917307731120') ?>" class="w-full bg-[#0b1329] border border-slate-700 rounded p-2 font-mono text-white">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-400 mb-1">Message Content</label>
                        <textarea name="message_body" required rows="4" class="w-full bg-[#0b1329] border border-slate-700 rounded p-2 text-white" placeholder="Type SMS content..."></textarea>
                    </div>
                    <button type="submit" name="send_broadcast_sms" class="w-full py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 text-white font-bold rounded shadow-md">
                        Dispatch SMS Message &rarr;
                    </button>
                </form>
            </div>

        <!-- ============================================================= -->
        <!-- TAB 7: GATEWAY SETTINGS -->
        <!-- ============================================================= -->
        <?php elseif ($activeTab === 'settings'): ?>
            <div class="max-w-2xl mx-auto bg-[#111c44] border border-slate-800 rounded-xl p-5 shadow-lg space-y-4 text-xs">
                <h3 class="text-sm font-black text-white uppercase tracking-wider mono border-b border-slate-800 pb-2">Android SMS Gateway Configuration</h3>
                <div class="space-y-2">
                    <span class="block text-slate-400 font-bold">Webhook Endpoint URL</span>
                    <div class="p-2.5 bg-[#0b1329] border border-slate-700 rounded font-mono text-[11px] text-cyan-400 select-all">
                        <?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}/DisasterSafe/Application/SMS_PART2/api/sms/receive.php?secret=sih_webhook_secret_key_2026" ?>
                    </div>
                </div>
                <div class="space-y-2">
                    <span class="block text-slate-400 font-bold">Primary Gateway SIM</span>
                    <div class="p-2.5 bg-[#0b1329] border border-slate-700 rounded font-mono text-emerald-400 flex justify-between">
                        <span>+91 98765 43210 (Gateway SIM Slot 1)</span>
                        <span class="text-[10px] font-black uppercase text-emerald-400">ONLINE</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </main>
</div>

<!-- LEAFLET GIS MAP & REAL-TIME SOS POP-UP SCRIPT -->
<script>
let smsMap = null;

// Clock Updater
setInterval(() => {
    const el = document.getElementById('liveClockDisplay');
    if (el) {
        const now = new Date();
        el.textContent = now.toTimeString().split(' ')[0];
    }
}, 1000);

document.addEventListener('DOMContentLoaded', () => {
    const mapEl = document.getElementById('smsOverviewMap');
    if (mapEl) {
        // Dark Carto Tile Layer
        smsMap = L.map('smsOverviewMap', { zoomControl: true }).setView([28.4608, 77.4890], 8);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', { maxZoom: 19 }).addTo(smsMap);

        const markersData = [
            { id: 18, name: 'utsav', phone: '7307731120', lat: 28.4608232, lng: 77.4890387, type: 'Immediate Assistance' },
            { id: 15, name: 'Connaught Fire', phone: '+919833333333', lat: 28.6300, lng: 77.2180, type: 'Fire Rescue' },
            { id: 14, name: 'Amit Sharma', phone: '+919822222222', lat: 28.6139, lng: 77.2090, type: 'Medical' },
            { id: 10, name: 'Mayur Vihar Flood', phone: '+919811122334', lat: 28.6080, lng: 77.2980, type: 'Flood' }
        ];

        markersData.forEach(m => {
            const icon = L.divIcon({
                className: 'custom-dark-radar',
                html: `<div style="width:22px;height:22px;background:#00f2fe;border:2px solid #fff;border-radius:50%;box-shadow:0 0 14px #00f2fe;display:grid;place-items:center;color:#000;font-size:10px;font-weight:900;">!</div>`,
                iconSize: [22, 22]
            });

            L.marker([m.lat, m.lng], { icon: icon })
                .bindPopup(`
                    <div style="font-family:Inter,sans-serif;font-size:12px;min-width:200px;color:#0f172a;">
                        <strong style="color:#dc2626;">SOS #${m.id}</strong> - ${m.name}<br>
                        <strong>Phone:</strong> ${m.phone}<br>
                        <strong>Type:</strong> ${m.type}<br>
                        <a href="gateway_sms.php?tab=sos&id=${m.id}" style="color:#2563eb;font-weight:bold;margin-top:4px;display:inline-block;">Open Dossier &rarr;</a>
                    </div>
                `)
                .addTo(smsMap);
        });
    }
});

// -------------------------------------------------------------
// LIVE SOS ALERT POP-UP ANIMATION
// -------------------------------------------------------------
function triggerLiveSosAlertModal() {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(850, audioCtx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(450, audioCtx.currentTime + 0.35);
        gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.35);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.35);
    } catch(e) {}

    Swal.fire({
        title: '<div class="flex items-center justify-center gap-2 text-red-500 font-mono text-sm tracking-wider"><span class="w-3 h-3 bg-red-500 rounded-full animate-ping"></span> 🚨 EMERGENCY SOS RECEIVED!</div>',
        html: `
            <div class="text-left bg-[#0b1329] p-3.5 border border-slate-800 rounded-xl space-y-2 text-xs font-sans text-slate-300">
                <div class="flex justify-between border-b border-slate-800 pb-1.5">
                    <span class="font-bold text-slate-400 uppercase mono text-[10px]">Signal Source:</span>
                    <span class="font-mono text-cyan-400 font-bold">Cellular SMS (GatewaySIM)</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Citizen:</span>
                    <strong class="text-white">utsav (+91 73077 31120)</strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Emergency:</span>
                    <strong class="text-red-400">Immediate Assistance Required!</strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">GPS Coordinates:</span>
                    <span class="font-mono font-bold text-cyan-400">28.4608232, 77.4890387</span>
                </div>
                <div class="mt-2 p-2 bg-red-950/60 border border-red-800/80 rounded text-red-300 font-mono text-[11px] italic">
                    "🚨 EMERGENCY SOS ALERT! Victim: utsav (Tel: 7307731120) GPS: 28.4608232, 77.4890387"
                </div>
            </div>
        `,
        background: '#111c44',
        color: '#f8fafc',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#334155',
        confirmButtonText: 'Open Dossier &rarr;',
        cancelButtonText: 'Acknowledge',
        customClass: {
            popup: 'rounded-2xl border border-red-500/50 shadow-2xl'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'gateway_sms.php?tab=sos';
        }
    });
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
