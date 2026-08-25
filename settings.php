<?php
// settings.php - DisasterSafe System Settings & ESP32 Hardware Connectivity Hub
define('PAGE_TITLE', 'Settings & ESP32');
require_once __DIR__ . '/auth.php';

requireLogin();
$currentUser = getCurrentUser($pdo);
$isSuperAdmin = isSuperAdmin($currentUser);

// Fetch latest 10 SOS alerts from database to show live sync status
$recentSosAlerts = $pdo->query("SELECT * FROM emergency_sos ORDER BY id DESC LIMIT 6")->fetchAll();
$totalSosCount = (int)$pdo->query("SELECT COUNT(*) FROM emergency_sos")->fetchColumn();
$pendingSosCount = (int)$pdo->query("SELECT COUNT(*) FROM emergency_sos WHERE status = 'Pending'")->fetchColumn();

$csrfToken = generateCsrfToken();

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div class="flex-1 flex flex-col min-w-0 bg-[#0a0f1d] min-h-screen overflow-y-auto">
    <?php require_once __DIR__ . '/navbar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto w-full">

        <!-- HEADER BANNER -->
        <section class="glass-panel p-5 sm:p-6 rounded-2xl border border-[#243049] relative overflow-hidden shadow-2xl">
            <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-indigo-600/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 relative z-10">
                <div class="space-y-1.5">
                    <div class="flex items-center gap-2.5">
                        <span class="w-8 h-8 rounded-xl bg-indigo-600/20 border border-indigo-500/30 flex items-center justify-center text-indigo-400 font-black text-base shadow-inner">
                            <i class="fa-solid fa-gear"></i>
                        </span>
                        <div>
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-indigo-400">Hardware & System Settings</span>
                            <h2 class="text-xl sm:text-2xl font-black text-white tracking-tight">ESP32 Gateway Connectivity & Platform Settings</h2>
                        </div>
                    </div>
                    <p class="text-xs text-slate-300 max-w-2xl leading-relaxed">
                        Connect and monitor live ESP32 Emergency SOS Beacons via USB Serial (115200 baud). All incoming serial dispatches and voice notes automatically synchronize into the DisasterSafe central database in real time.
                    </p>
                </div>

                <!-- Fast Actions -->
                <div class="flex flex-wrap items-center gap-3 shrink-0">
                    <button type="button" id="btnConnectSerial" onclick="toggleSerialConnection()" class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-lg shadow-emerald-600/30 transition-all flex items-center gap-2">
                        <i class="fa-solid fa-plug"></i> <span id="btnConnectLabel">Connect ESP32 USB</span>
                    </button>
                    <button type="button" onclick="injectTestEsp32Alert()" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-lg shadow-indigo-600/30 transition-all flex items-center gap-2">
                        <i class="fa-solid fa-bolt"></i> <span>Simulate Serial SOS</span>
                    </button>
                </div>
            </div>
        </section>

        <!-- ESP32 HARDWARE CONNECTIVITY & STATUS CARDS -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            
            <!-- Connection Status Card -->
            <div class="glass-panel p-5 rounded-2xl border border-[#243049] shadow-xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[#243049]">
                    <span class="text-xs font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-microchip text-indigo-400"></i> Hardware Status
                    </span>
                    <span id="serialStatusBadge" class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-800 text-slate-400 border border-slate-700">
                        Disconnected
                    </span>
                </div>

                <div class="space-y-2.5 text-xs">
                    <div class="flex justify-between items-center py-1 border-b border-[#243049]/50">
                        <span class="text-slate-400">USB Serial Port</span>
                        <span id="lblPortName" class="font-mono font-bold text-slate-300">None (Click Connect)</span>
                    </div>
                    <div class="flex justify-between items-center py-1 border-b border-[#243049]/50">
                        <span class="text-slate-400">Baud Rate</span>
                        <span class="font-mono font-bold text-indigo-400">115200 Baud (8-N-1)</span>
                    </div>
                    <div class="flex justify-between items-center py-1 border-b border-[#243049]/50">
                        <span class="text-slate-400">Database Sync Engine</span>
                        <span class="font-bold text-emerald-400 flex items-center gap-1">
                            <i class="fa-solid fa-check-circle"></i> Live Auto-Insert
                        </span>
                    </div>
                    <div class="flex justify-between items-center py-1">
                        <span class="text-slate-400">Web Serial API</span>
                        <span id="lblWebSerialSupport" class="font-bold text-emerald-400">Supported (Chrome/Edge)</span>
                    </div>
                </div>

                <div class="pt-2">
                    <button type="button" onclick="toggleSerialConnection()" id="btnCardConnect" class="w-full py-2 rounded-xl bg-[#11192e] hover:bg-slate-800 border border-[#243049] text-white font-bold text-xs transition-all flex items-center justify-center gap-2">
                        <i class="fa-solid fa-link text-indigo-400"></i> Pair ESP32 Device
                    </button>
                </div>
            </div>

            <!-- ESP32 Wi-Fi SoftAP & Captive Portal Telemetry Card -->
            <div class="glass-panel p-5 rounded-2xl border border-[#243049] shadow-xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[#243049]">
                    <span class="text-xs font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-wifi text-emerald-400"></i> Captive Portal Spec
                    </span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-950 text-emerald-300 border border-emerald-800">
                        Offline Ready
                    </span>
                </div>

                <div class="space-y-2.5 text-xs">
                    <div class="flex justify-between items-center py-1 border-b border-[#243049]/50">
                        <span class="text-slate-400">SoftAP SSID</span>
                        <span class="font-mono font-bold text-amber-300">EMERGENCY-SOS-PORTAL</span>
                    </div>
                    <div class="flex justify-between items-center py-1 border-b border-[#243049]/50">
                        <span class="text-slate-400">Gateway Local IP</span>
                        <span class="font-mono font-bold text-slate-300">192.168.4.1</span>
                    </div>
                    <div class="flex justify-between items-center py-1 border-b border-[#243049]/50">
                        <span class="text-slate-400">DNS Interceptor</span>
                        <span class="font-bold text-slate-300">Wildcard Captive (*:53)</span>
                    </div>
                    <div class="flex justify-between items-center py-1">
                        <span class="text-slate-400">Audio Voice SOS</span>
                        <span class="font-bold text-rose-400">15s Offline Recorder</span>
                    </div>
                </div>

                <div class="pt-2">
                    <a href="http://192.168.4.1" target="_blank" class="w-full py-2 rounded-xl bg-[#11192e] hover:bg-slate-800 border border-[#243049] text-slate-300 font-bold text-xs transition-all flex items-center justify-center gap-2">
                        <i class="fa-solid fa-arrow-up-right-from-square text-emerald-400"></i> Open Gateway Portal (192.168.4.1)
                    </a>
                </div>
            </div>

            <!-- Database Synchronization Stats Card -->
            <div class="glass-panel p-5 rounded-2xl border border-[#243049] shadow-xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[#243049]">
                    <span class="text-xs font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-database text-blue-400"></i> Database Sync Health
                    </span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-950 text-blue-300 border border-blue-800">
                        SQLite Active
                    </span>
                </div>

                <div class="space-y-2.5 text-xs">
                    <div class="flex justify-between items-center py-1 border-b border-[#243049]/50">
                        <span class="text-slate-400">Total Ingested SOS Alerts</span>
                        <span class="font-mono font-extrabold text-white text-sm"><?= $totalSosCount ?></span>
                    </div>
                    <div class="flex justify-between items-center py-1 border-b border-[#243049]/50">
                        <span class="text-slate-400">Pending Triage Queue</span>
                        <span class="font-mono font-bold text-rose-400"><?= $pendingSosCount ?> Pending</span>
                    </div>
                    <div class="flex justify-between items-center py-1 border-b border-[#243049]/50">
                        <span class="text-slate-400">Serial Packets Received</span>
                        <span id="lblPacketsCount" class="font-mono font-bold text-indigo-400">0 Packets</span>
                    </div>
                    <div class="flex justify-between items-center py-1">
                        <span class="text-slate-400">Ingest API Endpoint</span>
                        <span class="font-mono text-[10px] text-slate-300">/api/esp32_sos_ingest.php</span>
                    </div>
                </div>

                <div class="pt-2">
                    <a href="sos.php" class="w-full py-2 rounded-xl bg-indigo-600/20 hover:bg-indigo-600/40 border border-indigo-500/30 text-indigo-300 font-bold text-xs transition-all flex items-center justify-center gap-2">
                        <i class="fa-solid fa-tower-broadcast"></i> View SOS Alerts Hub
                    </a>
                </div>
            </div>

        </section>

        <!-- LIVE STREAM TELEMETRY & INGESTION CARDS FEED -->
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            
            <!-- Left: Real-time Ingested ESP32 SOS Feed (7 Cols) -->
            <div class="lg:col-span-7 glass-panel p-5 rounded-2xl border border-[#243049] shadow-2xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[#243049]">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-rose-500 animate-pulse"></span>
                        <h3 class="text-xs font-bold text-white uppercase tracking-wider">
                            Live ESP32 Serial Alerts Feed
                        </h3>
                    </div>
                    <span class="text-[10px] font-mono text-slate-400" id="lblLiveFeedMeta">Listening on USB Serial</span>
                </div>

                <!-- Dynamic Container for Live Serial SOS Cards -->
                <div id="esp32LiveAlertsContainer" class="space-y-3 max-h-[580px] overflow-y-auto pr-1">
                    
                    <!-- Preloaded from Database -->
                    <?php if (empty($recentSosAlerts)): ?>
                        <div id="noAlertsPlaceholder" class="p-8 rounded-xl bg-[#0c1326] border border-[#243049] text-center text-xs text-slate-400">
                            <i class="fa-solid fa-tower-cell text-3xl text-slate-600 mb-2 block"></i>
                            Waiting for incoming ESP32 serial dispatches...<br/>
                            Connect USB or click <b>Simulate Serial SOS</b> to test real-time database ingestion.
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentSosAlerts as $sos): ?>
                            <div class="p-4 rounded-xl bg-[#0c1326] border border-[#243049] border-l-4 border-l-rose-500 hover:border-slate-500 transition-all text-xs space-y-2.5 shadow-md">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="font-extrabold text-white text-sm"><i class="fa-solid fa-user"></i> <?= htmlspecialchars($sos['sender_name']) ?></span>
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-rose-950 text-rose-300 border border-rose-800">
                                            <?= htmlspecialchars($sos['emergency_type']) ?>
                                        </span>
                                    </div>
                                    <span class="px-2 py-0.5 rounded-lg bg-emerald-950 text-emerald-300 border border-emerald-800 font-mono text-[10px] font-extrabold">
                                        DB Synced #<?= $sos['id'] ?>
                                    </span>
                                </div>

                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 bg-[#11192e] p-2.5 rounded-lg border border-[#243049]/60 text-[11px]">
                                    <div><span class="text-slate-400 block text-[9px]">PHONE</span><b><?= htmlspecialchars($sos['sender_phone']) ?></b></div>
                                    <div><span class="text-slate-400 block text-[9px]">GPS COORDINATES</span><b><?= number_format($sos['gps_lat'], 4) ?>, <?= number_format($sos['gps_lng'], 4) ?></b></div>
                                    <div><span class="text-slate-400 block text-[9px]">BLOOD GROUP</span><b><?= htmlspecialchars($sos['blood_type'] ?: 'Unknown') ?></b></div>
                                    <div><span class="text-slate-400 block text-[9px]">ASSIGNED UNIT</span><b class="text-blue-400"><?= htmlspecialchars($sos['dispatch_agency'] ?: 'Pending') ?></b></div>
                                </div>

                                <?php if (!empty($sos['medical_needs'])): ?>
                                    <div class="text-[11px] text-slate-300">
                                        <strong class="text-amber-400">Aid Needs:</strong> <?= htmlspecialchars($sos['medical_needs']) ?>
                                    </div>
                                <?php endif; ?>

                                <p class="text-[11px] text-slate-300 italic bg-[#11192e]/60 p-2 rounded border border-[#243049]/40">
                                    "<?= htmlspecialchars($sos['message']) ?>"
                                </p>

                                <div class="flex items-center justify-between pt-1 border-t border-[#243049]/60 text-[10px] text-slate-400">
                                    <span>Timestamp: <?= htmlspecialchars($sos['created_at']) ?></span>
                                    <div class="flex items-center gap-2">
                                        <a href="sos.php?id=<?= $sos['id'] ?>" class="text-indigo-400 hover:text-indigo-300 font-bold">Open Triage Dossier →</a>
                                        <a href="https://www.google.com/maps?q=<?= $sos['gps_lat'] ?>,<?= $sos['gps_lng'] ?>" target="_blank" class="text-teal-400 hover:text-teal-300 font-bold">Maps ↗</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>
            </div>

            <!-- Right: Live USB Serial Stream Monitor & Raw Terminal (5 Cols) -->
            <div class="lg:col-span-5 glass-panel p-5 rounded-2xl border border-[#243049] shadow-2xl flex flex-col justify-between space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[#243049]">
                    <span class="text-xs font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-terminal text-indigo-400"></i> Serial Stream Monitor
                    </span>
                    <button type="button" onclick="clearSerialConsole()" class="text-[10px] font-bold text-slate-400 hover:text-white px-2 py-0.5 rounded bg-slate-800 border border-slate-700">
                        Clear
                    </button>
                </div>

                <!-- Raw Terminal Window -->
                <div id="serialConsoleLog" class="flex-1 min-h-[360px] max-h-[420px] bg-[#060a14] border border-[#243049] rounded-xl p-3 font-mono text-[11px] text-emerald-400 overflow-y-auto space-y-1 select-all">
                    <div class="text-slate-500">[SYSTEM] DisasterSafe Web Serial Receiver initialized.</div>
                    <div class="text-slate-500">[SYSTEM] Baud rate locked at 115200. Ready to pair with ESP32 USB.</div>
                    <div class="text-indigo-400">[READY] Click 'Connect ESP32 USB' or 'Simulate Serial SOS' to stream packets.</div>
                </div>

                <!-- Bottom Protocol Legend -->
                <div class="bg-[#0c1326] p-3 rounded-xl border border-[#243049] space-y-1.5 text-xs">
                    <div class="flex items-center justify-between text-[10px] font-bold text-slate-400 uppercase">
                        <span>Frame Protocol</span>
                        <span class="text-indigo-400 font-mono">---SOS_START--- ... ---SOS_END---</span>
                    </div>
                    <p class="text-[10px] text-slate-400 leading-relaxed">
                        Automatic packet deserialization parses JSON payloads and executes async POST requests to <code>/api/esp32_sos_ingest.php</code> with zero schema conflicts.
                    </p>
                </div>
            </div>

        </section>

        <!-- SYSTEM & ESP32 CONFIGURATION PREFERENCES FORM -->
        <section class="glass-panel p-6 rounded-2xl border border-[#243049] shadow-2xl space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-[#243049]">
                <h3 class="text-sm font-extrabold text-white flex items-center gap-2">
                    <i class="fa-solid fa-sliders text-indigo-400"></i> Platform &amp; Hardware Preferences
                </h3>
                <span class="text-xs text-slate-400">Superadmin Master Parameters</span>
            </div>

            <form method="POST" action="settings.php" onsubmit="event.preventDefault(); Swal.fire('Settings Saved', 'Platform hardware configuration updated successfully.', 'success');" class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                <div>
                    <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Rescue Mesh Node ID</label>
                    <input type="text" value="RESCUE-BEACON-04 (Delhi-NCR Apex)" readonly class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white font-mono">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Serial Port Baud Rate</label>
                    <input type="text" value="115200 Baud" readonly class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-indigo-300 font-bold font-mono">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Auto-Triage Dispatch Agency</label>
                    <select class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white font-semibold">
                        <option selected>Automatic System Triage (NDRF / Fire / Police / EMS)</option>
                        <option>Force Police Patrol First Response</option>
                        <option>Force Medical &amp; EMS First Response</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Central GPS Anchor Coordinates</label>
                    <input type="text" value="28.613900, 77.209000" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white font-mono">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Emergency Captive SSID</label>
                    <input type="text" value="EMERGENCY-SOS-PORTAL" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white font-mono">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Database Auto-Sync Mode</label>
                    <input type="text" value="Enabled (Real-time SQLite Ingest)" readonly class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-emerald-400 font-bold">
                </div>

                <div class="md:col-span-3 flex justify-end gap-2 pt-2 border-t border-[#243049]">
                    <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold shadow-lg shadow-indigo-600/30 transition-all">
                        Save Preferences
                    </button>
                </div>
            </form>
        </section>

    </main>
</div>

<!-- ========================================================================= -->
<!-- JAVASCRIPT: WEB SERIAL API LISTENER & AUTOMATIC DATABASE INGESTER -->
<!-- ========================================================================= -->
<script>
let serialPacketsReceived = 0;

// Expose logger for global serial service
window.logSettingsTerminal = function(msg, color = 'emerald') {
    const consoleBox = document.getElementById('serialConsoleLog');
    if (!consoleBox) return;

    const timeStr = new Date().toLocaleTimeString();
    const line = document.createElement('div');
    
    if (color === 'red') line.className = 'text-rose-400 font-bold';
    else if (color === 'yellow') line.className = 'text-amber-300';
    else if (color === 'indigo') line.className = 'text-indigo-300';
    else if (color === 'dim') line.className = 'text-slate-500';
    else line.className = 'text-emerald-400';

    line.innerText = `[${timeStr}] ${msg}`;
    consoleBox.appendChild(line);
    consoleBox.scrollTop = consoleBox.scrollHeight;
};

function clearSerialConsole() {
    const consoleBox = document.getElementById('serialConsoleLog');
    if (consoleBox) {
        consoleBox.innerHTML = '<div class="text-slate-500">[SYSTEM] Console cleared.</div>';
    }
}

// Update settings UI state when global serial connects/disconnects
window.updateSettingsSerialUI = function(isConnected, portInfo = '') {
    const btnLabel = document.getElementById('btnConnectLabel');
    const btnConnect = document.getElementById('btnConnectSerial');
    const btnCard = document.getElementById('btnCardConnect');
    const badge = document.getElementById('serialStatusBadge');
    const lblPort = document.getElementById('lblPortName');

    if (isConnected) {
        if (btnLabel) btnLabel.innerText = "Disconnect USB";
        if (btnConnect) {
            btnConnect.className = "px-4 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs shadow-lg shadow-rose-600/30 transition-all flex items-center gap-2";
        }
        if (btnCard) {
            btnCard.className = "w-full py-2 rounded-xl bg-rose-600/20 hover:bg-rose-600/30 border border-rose-500/40 text-rose-300 font-bold text-xs transition-all flex items-center justify-center gap-2";
            btnCard.innerHTML = '<i class="fa-solid fa-link-slash"></i> Disconnect ESP32 USB';
        }
        if (badge) {
            badge.className = "px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-950 text-emerald-300 border border-emerald-800";
            badge.innerText = "Connected (115200)";
        }
        if (lblPort) lblPort.innerText = "ESP32 USB Active (Persistent)";
    } else {
        if (btnLabel) btnLabel.innerText = "Connect ESP32 USB";
        if (btnConnect) {
            btnConnect.className = "px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-lg shadow-emerald-600/30 transition-all flex items-center gap-2";
        }
        if (btnCard) {
            btnCard.className = "w-full py-2 rounded-xl bg-[#11192e] hover:bg-slate-800 border border-[#243049] text-white font-bold text-xs transition-all flex items-center justify-center gap-2";
            btnCard.innerHTML = '<i class="fa-solid fa-link text-indigo-400"></i> Pair ESP32 Device';
        }
        if (badge) {
            badge.className = "px-2 py-0.5 rounded text-[10px] font-bold bg-slate-800 text-slate-400 border border-slate-700";
            badge.innerText = "Disconnected";
        }
        if (lblPort) lblPort.innerText = "None (Click Connect)";
    }
};

// Toggle connection via global background service
function toggleSerialConnection() {
    if (window.DisasterSafeSerial) {
        window.DisasterSafeSerial.toggleConnection();
    }
}

// Listen for incoming ESP32 SOS dispatches from global background worker
window.addEventListener('esp32_sos_received', function(event) {
    const data = event.detail.data;
    const dbId = event.detail.dbId;

    serialPacketsReceived++;
    const countBadge = document.getElementById('lblPacketsCount');
    if (countBadge) countBadge.innerText = `${serialPacketsReceived} Packets`;

    renderEsp32Card(data, dbId);
});

// Render dynamic card in Live Feed
function renderEsp32Card(data, dbId) {
    const container = document.getElementById('esp32LiveAlertsContainer');
    const placeholder = document.getElementById('noAlertsPlaceholder');
    if (placeholder) placeholder.remove();

    const name = data.sender_name || data.victim_name || 'Unknown Citizen';
    const phone = data.sender_phone || data.phone || 'N/A';
    const lat = parseFloat(data.gps_lat || data.latitude) || 28.6139;
    const lng = parseFloat(data.gps_lng || data.longitude) || 77.2090;
    const type = data.emergency_type || 'Emergency';
    const priority = data.priority || data.severity || 'Critical';
    const blood = data.blood_type || 'Unknown';
    const message = data.message || 'ESP32 Distress Signal Received';
    const hasVoice = Boolean(data.voice_note);
    const duration = data.voice_duration_sec || 0;
    const cardId = 'card-esp32-' + Date.now();

    let voiceHtml = '';
    if (hasVoice) {
        voiceHtml = `
            <div class="bg-[#11192e] p-2.5 rounded-lg border border-rose-500/40 space-y-2 mt-2">
                <div class="flex justify-between items-center text-[10px] text-rose-300 font-extrabold uppercase">
                    <span>Emergency Voice SOS Recording</span>
                    <span>${duration ? duration + 's' : 'Audio Attached'}</span>
                </div>
                <audio id="audio-${cardId}" src="${data.voice_note}" preload="metadata"></audio>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="toggleCardVoice('${cardId}')" id="btn-audio-${cardId}" class="px-2.5 py-1 rounded bg-rose-600 hover:bg-rose-500 text-white font-bold text-[10px]">
                        ▶ Play Voice Note
                    </button>
                    <span id="time-${cardId}" class="text-[10px] font-mono text-slate-400">00:00</span>
                </div>
            </div>
        `;
    }

    const card = document.createElement('div');
    card.className = "p-4 rounded-xl bg-[#0c1326] border border-indigo-500/50 border-l-4 border-l-rose-500 transition-all text-xs space-y-2.5 shadow-xl animate-fade-in";
    card.innerHTML = `
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="font-extrabold text-white text-sm"><i class="fa-solid fa-user"></i> ${escapeHtml(name)}</span>
                <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-rose-950 text-rose-300 border border-rose-800">
                    ${escapeHtml(type)}
                </span>
                ${hasVoice ? '<span class="px-1.5 py-0.5 rounded text-[9px] font-extrabold bg-rose-500 text-white animate-pulse">VOICE SOS</span>' : ''}
            </div>
            <span class="px-2 py-0.5 rounded-lg bg-emerald-950 text-emerald-300 border border-emerald-800 font-mono text-[10px] font-extrabold">
                ${dbId ? `DB Synced #${dbId}` : 'Incoming Telemetry'}
            </span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 bg-[#11192e] p-2.5 rounded-lg border border-[#243049]/60 text-[11px]">
            <div><span class="text-slate-400 block text-[9px]">PHONE</span><b>${escapeHtml(phone)}</b></div>
            <div><span class="text-slate-400 block text-[9px]">GPS COORDINATES</span><b>${lat.toFixed(4)}, ${lng.toFixed(4)}</b></div>
            <div><span class="text-slate-400 block text-[9px]">BLOOD GROUP</span><b>${escapeHtml(blood)}</b></div>
            <div><span class="text-slate-400 block text-[9px]">PRIORITY</span><b class="text-rose-400">${escapeHtml(priority)}</b></div>
        </div>

        <p class="text-[11px] text-slate-300 italic bg-[#11192e]/60 p-2 rounded border border-[#243049]/40">
            "${escapeHtml(message)}"
        </p>

        ${voiceHtml}

        <div class="flex items-center justify-between pt-1 border-t border-[#243049]/60 text-[10px] text-slate-400">
            <span>Received via ESP32 Node: <b>${escapeHtml(data.beacon_node_id || 'RESCUE-BEACON-04')}</b></span>
            <div class="flex items-center gap-2">
                ${dbId ? `<a href="sos.php?id=${dbId}" class="text-indigo-400 hover:text-indigo-300 font-bold">Open SOS Dossier →</a>` : ''}
                <a href="https://www.google.com/maps?q=${lat},${lng}" target="_blank" class="text-teal-400 hover:text-teal-300 font-bold">Maps ↗</a>
            </div>
        </div>
    `;

    container.insertBefore(card, container.firstChild);
}

function toggleCardVoice(cardId) {
    const audio = document.getElementById(`audio-${cardId}`);
    const btn = document.getElementById(`btn-audio-${cardId}`);
    const time = document.getElementById(`time-${cardId}`);

    if (!audio) return;

    if (audio.paused) {
        document.querySelectorAll('audio').forEach(a => { if (a !== audio) a.pause(); });
        audio.play();
        btn.innerText = "⏸ Pause";
        audio.ontimeupdate = () => {
            if (audio.duration) {
                time.innerText = Math.round(audio.currentTime) + 's / ' + Math.round(audio.duration) + 's';
            }
        };
        audio.onended = () => {
            btn.innerText = "▶ Play Voice Note";
        };
    } else {
        audio.pause();
        btn.innerText = "▶ Play Voice Note";
    }
}

// Simulate ESP32 SOS Serial Packet using global service
function injectTestEsp32Alert() {
    const testSamples = [
        {
            event: "SOS_DISPATCH",
            beacon_node_id: "RESCUE-BEACON-04",
            sender_name: "Rohan Sharma (ESP32 Mesh)",
            victim_name: "Rohan Sharma (ESP32 Mesh)",
            sender_phone: "+91 98765 43210",
            phone: "+91 98765 43210",
            latitude: 28.6080,
            gps_lat: 28.6080,
            longitude: 77.2980,
            gps_lng: 77.2980,
            emergency_type: "Flood",
            priority: "Critical",
            medical_needs: "Inflatable rescue boat, potable water cans",
            quick_needs: "Inflatable rescue boat, potable water cans",
            blood_type: "O+",
            age: 34,
            message: "Yamuna floodwaters reaching 1st floor level at Mayur Vihar, 2 elderly citizens trapped on terrace.",
            voice_duration_sec: 7,
            is_voice_sos: true
        },
        {
            event: "SOS_DISPATCH",
            beacon_node_id: "RESCUE-BEACON-02",
            sender_name: "Dr. Aarti Sen (Field Medical)",
            victim_name: "Dr. Aarti Sen (Field Medical)",
            sender_phone: "+91 98111 22334",
            phone: "+91 98111 22334",
            latitude: 28.5672,
            gps_lat: 28.5672,
            longitude: 77.2100,
            gps_lng: 77.2100,
            emergency_type: "Medical Trauma",
            priority: "Critical",
            medical_needs: "High-flow oxygen cylinders, emergency blood units",
            quick_needs: "High-flow oxygen cylinders, emergency blood units",
            blood_type: "AB+",
            age: 28,
            message: "Mass casualty intake at AIIMS trauma triage corridor. Critical oxygen manifolds needed.",
            voice_duration_sec: 5,
            is_voice_sos: false
        }
    ];

    const randomSample = testSamples[Math.floor(Math.random() * testSamples.length)];
    if (window.logSettingsTerminal) {
        window.logSettingsTerminal('---SOS_START---', 'yellow');
        window.logSettingsTerminal(JSON.stringify(randomSample), 'dim');
        window.logSettingsTerminal('---SOS_END---', 'yellow');
    }

    if (window.DisasterSafeSerial) {
        window.DisasterSafeSerial.injectTestAlert(randomSample);
    }
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
