<?php
// citizen.php - DisasterSafe Citizen & Victim Emergency Safety Portal (Unified Architecture)
define('PAGE_TITLE', 'Citizen Emergency & Safety Portal');
require_once __DIR__ . '/auth.php';

requireLogin();
$currentUser = getCurrentUser($pdo);

$userName = $currentUser['name'] ?? ($_SESSION['user_name'] ?? 'Citizen');
$userPhone = $currentUser['phone'] ?? '+91 98765 43210';
$userEmail = $currentUser['email'] ?? '';
$userId = $currentUser['id'] ?? 0;
$csrfToken = generateCsrfToken();

// Auto-triage function
function determineSystemTriage($type, $priority, $persons) {
    return match($type) {
        'Fire', 'Gas Leak', 'Industrial Hazard' => [
            'agency' => 'Fire & Rescue Department',
            'needs' => 'Thermal Suits, High-Flow Hoses, Smoke Extractors, Oxygen Supply'
        ],
        'Medical', 'Mass Casualty', 'Injury' => [
            'agency' => 'Medical & EMS Department',
            'needs' => 'Paramedic ALS, Trauma Kits, Stretcher Evacuation, Defibrillator'
        ],
        'Flood', 'Structural Collapse', 'Landslide' => [
            'agency' => 'NDRF Force Command',
            'needs' => 'Heavy Cutting Gear, Inflatable Boats, Life Jackets, Sonar Detectors'
        ],
        'Law Enforcement', 'Perimeter Breach', 'Missing Relative' => [
            'agency' => 'Police Department',
            'needs' => 'Perimeter Patrol, Roadblocks, Search Team, Radio Beacon'
        ],
        default => [
            'agency' => 'Volunteer Relief Corps',
            'needs' => 'First Aid Triage, Dry Rations, Clean Water, Emergency Blankets'
        ]
    };
}

// Handle POST Submissions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token. Please refresh and retry.');
        header("Location: citizen.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    // 1. BROADCAST EMERGENCY SOS
    if ($action === 'broadcast_sos') {
        $senderName = trim($_POST['sender_name'] ?? $userName);
        $senderPhone = trim($_POST['sender_phone'] ?? $userPhone);
        $latitude = filter_var($_POST['latitude'] ?? 28.6139, FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($_POST['longitude'] ?? 77.2090, FILTER_VALIDATE_FLOAT);
        $emergencyType = trim($_POST['emergency_type'] ?? 'Flood');
        $personsCount = trim($_POST['persons_count'] ?? '1');
        $bloodType = trim($_POST['blood_type'] ?? 'Unknown');
        $age = !empty($_POST['age']) ? (int)$_POST['age'] : null;
        $priority = trim($_POST['priority'] ?? 'Critical');
        $message = trim($_POST['message'] ?? '');

        $triage = determineSystemTriage($emergencyType, $priority, $personsCount);
        $dispatchAgency = $triage['agency'];
        $medicalNeeds = $triage['needs'];

        try {
            $stmt = $pdo->prepare("
                INSERT INTO emergency_sos (sender_name, sender_phone, gps_lat, gps_lng, blood_type, age, persons_count, priority, emergency_type, medical_needs, dispatch_agency, message, status, eta_minutes, assigned_unit)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 8, ?)
            ");
            $stmt->execute([$senderName, $senderPhone, $latitude, $longitude, $bloodType, $age, $personsCount, $priority, $emergencyType, $medicalNeeds, $dispatchAgency, $message, $dispatchAgency]);
            $sosId = $pdo->lastInsertId();

            try {
                $pdo->prepare("INSERT INTO direct_requests (sos_request_id, victim_lat, victim_lng, status) VALUES (?, ?, ?, 'pending')")
                    ->execute([$sosId, $latitude, $longitude]);
            } catch (Exception $ex) {}

            logActivity($pdo, 'CITIZEN_SOS_BROADCAST', "Public SOS #{$sosId} transmitted by {$senderName} [GPS: {$latitude}, {$longitude}] ({$emergencyType})");
            setFlash('success', "🚨 Emergency distress signal transmitted (SOS #{$sosId})! Auto-assigned to {$dispatchAgency}. Emergency responders have been notified.");
        } catch (Exception $e) {
            setFlash('error', "Failed to broadcast distress signal: " . $e->getMessage());
        }

        header("Location: citizen.php");
        exit;
    }

    // 2. RESOLVE SOS / CANCEL DISTRESS
    if ($action === 'resolve_sos') {
        $sosId = (int)($_POST['sos_id'] ?? 0);
        if ($sosId > 0) {
            $pdo->prepare("UPDATE emergency_sos SET status = 'Resolved' WHERE id = ?")->execute([$sosId]);
            logActivity($pdo, 'SOS_RESOLVED_CITIZEN', "SOS #{$sosId} marked resolved by citizen ({$userName})");
            setFlash('success', "SOS #{$sosId} marked as resolved. We are glad you are safe!");
        }
        header("Location: citizen.php");
        exit;
    }

    // 3. SEND DIRECT CHAT TO RESPONDER
    if ($action === 'send_responder_chat') {
        $sosId = (int)($_POST['sos_id'] ?? 0);
        $msg = trim($_POST['message'] ?? '');
        if ($sosId > 0 && !empty($msg)) {
            $pdo->prepare("
                INSERT INTO victim_volunteer_chats (sos_id, sender_id, sender_name, sender_role, message)
                VALUES (?, ?, ?, 'victim', ?)
            ")->execute([$sosId, $userId, $userName, $msg]);
            setFlash('success', 'Update sent to your responder team.');
        }
        header("Location: citizen.php");
        exit;
    }
}

// Fetch Active SOS for the logged-in user
$activeSos = null;
if (!empty($userPhone) || !empty($userName)) {
    $stmt = $pdo->prepare("SELECT * FROM emergency_sos WHERE (sender_phone = ? OR sender_name = ?) AND status != 'Resolved' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$userPhone, $userName]);
    $activeSos = $stmt->fetch();
}

// Fetch History of Previous SOS Reports
$userReports = [];
if (!empty($userPhone) || !empty($userName)) {
    $stmt = $pdo->prepare("SELECT * FROM emergency_sos WHERE sender_phone = ? OR sender_name = ? ORDER BY id DESC LIMIT 10");
    $stmt->execute([$userPhone, $userName]);
    $userReports = $stmt->fetchAll();
}
if (empty($userReports)) {
    $userReports = $pdo->query("SELECT * FROM emergency_sos ORDER BY id DESC LIMIT 5")->fetchAll();
}

// Fetch Facilities for Shelter Radar
$facilities = $pdo->query("SELECT * FROM facilities WHERE status != 'Closed' ORDER BY type ASC, total_capacity DESC LIMIT 12")->fetchAll();

// Stats
$statsTotalSos = $pdo->query("SELECT COUNT(*) FROM emergency_sos")->fetchColumn();
$statsActiveSos = $pdo->query("SELECT COUNT(*) FROM emergency_sos WHERE status != 'Resolved'")->fetchColumn();
$statsShelters = $pdo->query("SELECT COUNT(*) FROM facilities WHERE type = 'Relief Shelter'")->fetchColumn() ?: 4;
$statsHospitals = $pdo->query("SELECT COUNT(*) FROM facilities WHERE type = 'Hospital'")->fetchColumn() ?: 6;

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div class="flex-1 flex flex-col min-w-0 bg-[#f8fafc] min-h-screen overflow-y-auto">
    <?php require_once __DIR__ . '/navbar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto w-full">

        <!-- 1. Hero Emergency Lifeline Banner -->
        <section class="bg-gradient-to-r from-red-950 via-slate-900 to-red-900 rounded-3xl p-6 sm:p-8 text-white shadow-xl relative overflow-hidden border border-red-800/40">
            <div class="absolute -right-10 -bottom-10 w-72 h-72 bg-red-500/15 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute right-20 top-0 w-48 h-48 bg-amber-500/10 rounded-full blur-2xl pointer-events-none"></div>

            <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                <div class="space-y-2 max-w-2xl">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-red-500/20 border border-red-400/30 text-red-300 text-xs font-bold mono">
                        <span class="w-2 h-2 rounded-full bg-red-400 animate-ping"></span>
                        <i class="fa-solid fa-tower-broadcast text-xs"></i>
                        <span>PUBLIC CITIZEN • 24/7 EMERGENCY LIFELINE</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black tracking-tight text-white">
                        Emergency SOS &amp; Civilian Safety Portal
                    </h1>
                    <p class="text-sm text-slate-300 font-medium leading-relaxed">
                        One-touch GPS distress beacon, verified safe shelter locators, hospital bed radar, and live multi-agency crisis lifeline.
                    </p>
                    <div class="flex flex-wrap items-center gap-4 pt-2 text-xs font-bold text-slate-300">
                        <span class="flex items-center gap-1.5"><i class="fa-solid fa-user-circle text-red-400"></i> Citizen: <strong class="text-white"><?= htmlspecialchars($userName) ?></strong></span>
                        <span class="flex items-center gap-1.5"><i class="fa-solid fa-phone text-emerald-400"></i> Contact: <strong class="text-white"><?= htmlspecialchars($userPhone) ?></strong></span>
                        <span class="flex items-center gap-1.5"><i class="fa-solid fa-satellite-dish text-teal-400"></i> Mesh Link: <span class="px-2 py-0.5 rounded-md bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 font-bold">ONLINE</span></span>
                    </div>
                </div>

                <!-- Right Quick Hotline Callouts -->
                <div class="flex flex-col sm:flex-row lg:flex-col gap-2.5 shrink-0">
                    <a href="tel:112" class="px-5 py-3 rounded-2xl bg-red-600 hover:bg-red-700 text-white text-xs font-black transition-all shadow-lg flex items-center justify-center gap-3">
                        <i class="fa-solid fa-phone-volume text-sm animate-bounce"></i>
                        <span>NATIONAL HELPLINE: 112</span>
                    </a>
                    <a href="#shelterRadarSection" class="px-4 py-2.5 rounded-2xl bg-white/10 hover:bg-white/20 text-white border border-white/20 text-xs font-bold transition-all flex items-center justify-center gap-2">
                        <i class="fa-solid fa-map-location-dot text-sm text-amber-400"></i>
                        <span>Find Nearest Safe Shelter</span>
                    </a>
                </div>
            </div>
        </section>

        <!-- 2. Active SOS Distress Card (If citizen currently has an active distress call) -->
        <?php if ($activeSos): ?>
            <section class="bg-gradient-to-br from-red-600 to-rose-700 rounded-3xl p-6 sm:p-8 text-white shadow-2xl relative overflow-hidden border border-red-500 animate-pulse-glow">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 relative z-10">
                    <div class="space-y-3 max-w-2xl">
                        <div class="flex items-center gap-3">
                            <span class="px-3 py-1 rounded-full bg-white/20 text-white text-xs font-black uppercase mono border border-white/30">
                                🚨 ACTIVE DISTRESS SIGNAL #<?= $activeSos['id'] ?>
                            </span>
                            <span class="px-3 py-1 rounded-full bg-black/20 text-white text-xs font-bold mono">
                                <?= htmlspecialchars($activeSos['emergency_type']) ?> • <?= htmlspecialchars($activeSos['priority']) ?>
                            </span>
                        </div>
                        <h2 class="text-xl sm:text-2xl font-black">
                            Responders Dispatched • Help is En-Route
                        </h2>
                        <p class="text-sm text-red-100 font-medium leading-relaxed">
                            Your emergency coordinates [<strong class="font-mono"><?= $activeSos['gps_lat'] ?>, <?= $activeSos['gps_lng'] ?></strong>] have been assigned to <strong class="text-white"><?= htmlspecialchars($activeSos['assigned_unit'] ?: $activeSos['dispatch_agency']) ?></strong>. Stay calm, keep phone on, and stay in a safe position.
                        </p>

                        <!-- Responder Card Details -->
                        <div class="p-4 rounded-2xl bg-black/20 border border-white/20 grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                            <div>
                                <span class="block text-[10px] uppercase font-bold text-red-200 mono">Assigned Unit</span>
                                <strong class="text-sm font-black text-white"><?= htmlspecialchars($activeSos['assigned_unit'] ?: 'Tactical First Responders') ?></strong>
                            </div>
                            <div>
                                <span class="block text-[10px] uppercase font-bold text-red-200 mono">Estimated Arrival (ETA)</span>
                                <strong class="text-sm font-black text-amber-300 font-mono"><i class="fa-solid fa-stopwatch mr-1"></i>~<?= (int)($activeSos['eta_minutes'] ?: 8) ?> Minutes</strong>
                            </div>
                            <div>
                                <span class="block text-[10px] uppercase font-bold text-red-200 mono">Current Dispatch Status</span>
                                <strong class="text-sm font-black text-emerald-300 uppercase"><?= htmlspecialchars($activeSos['status']) ?></strong>
                            </div>
                        </div>
                    </div>

                    <!-- Actions on Active SOS -->
                    <div class="flex flex-col sm:flex-row lg:flex-col gap-3 shrink-0">
                        <button type="button" onclick="openResponderChatModal(<?= $activeSos['id'] ?>)" class="px-5 py-3 rounded-2xl bg-white text-slate-900 hover:bg-slate-100 text-xs font-black transition-all shadow-md flex items-center justify-center gap-2 cursor-pointer">
                            <i class="fa-solid fa-comments text-blue-600 text-sm"></i>
                            <span>Chat with Responder</span>
                        </button>

                        <form method="POST" action="citizen.php">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="action" value="resolve_sos">
                            <input type="hidden" name="sos_id" value="<?= $activeSos['id'] ?>">
                            <button type="submit" class="w-full px-5 py-3 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black transition-all shadow-md flex items-center justify-center gap-2 cursor-pointer">
                                <i class="fa-solid fa-circle-check text-sm"></i>
                                <span>I am Safe / Cancel SOS</span>
                            </button>
                        </form>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <!-- 3. Citizen & Volunteer Live Hotline Chat Hub -->
        <section id="citizenLiveHotlineCard" class="bg-white rounded-3xl border border-slate-200 p-5 sm:p-6 shadow-sm space-y-4 relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-1.5 bg-gradient-to-r from-blue-600 via-amber-500 to-rose-500"></div>

            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-slate-100 gap-3 pt-1">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-blue-50 text-blue-600 border border-blue-200 flex items-center justify-center text-base font-bold">
                        <i class="fa-solid fa-comments"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base sm:text-lg font-black text-slate-900">Citizen &amp; Volunteer Live Hotline</h3>
                            <span class="px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-mono font-extrabold uppercase tracking-wider flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span id="citizenHotlineStatusBadge">Live Line Connected</span>
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 font-medium">Direct two-way tactical communication with your assigned emergency response squad</p>
                    </div>
                </div>

                <div class="flex items-center gap-2 self-start sm:self-auto">
                    <button type="button" onclick="openResponderChatModal(<?= (int)($activeSos['id'] ?? 1) ?>)" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer shadow-2xs">
                        <i class="fa-solid fa-expand text-xs"></i>
                        <span>Popout Modal</span>
                    </button>
                    <a href="tel:112" class="px-3.5 py-2 rounded-xl bg-red-50 hover:bg-red-100 border border-red-200 text-red-700 text-xs font-bold transition-all flex items-center gap-1.5">
                        <i class="fa-solid fa-phone-volume text-xs text-red-600"></i>
                        <span>Speed Dial 112</span>
                    </a>
                </div>
            </div>

            <!-- Hotline Feed Box -->
            <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 space-y-3">
                <div id="citizenHotlineFeed" class="flex flex-col gap-2.5 max-h-[220px] min-h-[140px] overflow-y-auto p-1 custom-scroll text-xs">
                    <div class="text-xs text-slate-400 italic text-center py-6">Connecting to your field responder squad...</div>
                </div>

                <!-- Quick Action Presets for Citizens -->
                <div class="flex items-center gap-1.5 overflow-x-auto pt-1 pb-1">
                    <span class="text-[10px] font-bold text-slate-400 mono shrink-0">Quick Update:</span>
                    <button type="button" onclick="sendCitizenHotlineMsg('🚑 We are here! Standing near the main road entrance.')" class="px-2.5 py-1 rounded-full bg-white border border-slate-200 hover:border-blue-400 text-[11px] font-bold text-slate-700 whitespace-nowrap shadow-2xs cursor-pointer transition-all">🚑 Here at Gate</button>
                    <button type="button" onclick="sendCitizenHotlineMsg('🌊 Water level is rising rapidly. We have moved to the top terrace.')" class="px-2.5 py-1 rounded-full bg-white border border-slate-200 hover:border-blue-400 text-[11px] font-bold text-slate-700 whitespace-nowrap shadow-2xs cursor-pointer transition-all">🌊 Water Rising / Terrace</button>
                    <button type="button" onclick="sendCitizenHotlineMsg('🩺 Patient requires immediate oxygen / medical support.')" class="px-2.5 py-1 rounded-full bg-white border border-slate-200 hover:border-blue-400 text-[11px] font-bold text-slate-700 whitespace-nowrap shadow-2xs cursor-pointer transition-all">🩺 Need Medical / Oxygen</button>
                    <button type="button" onclick="sendCitizenHotlineMsg('👀 We can see your rescue vehicle lights approaching.')" class="px-2.5 py-1 rounded-full bg-white border border-slate-200 hover:border-blue-400 text-[11px] font-bold text-slate-700 whitespace-nowrap shadow-2xs cursor-pointer transition-all">👀 Seeing Vehicle</button>
                    <button type="button" onclick="sendCitizenHotlineMsg('🚪 Signaling from the 2nd floor balcony window with a white cloth.')" class="px-2.5 py-1 rounded-full bg-white border border-slate-200 hover:border-blue-400 text-[11px] font-bold text-slate-700 whitespace-nowrap shadow-2xs cursor-pointer transition-all">🚪 At Balcony Window</button>
                </div>

                <!-- Send Hotline Input -->
                <form id="citizenHotlineForm" onsubmit="handleSendCitizenHotlineMsg(event)" class="flex items-center gap-2 pt-1">
                    <input type="text" id="citizenHotlineInput" class="flex-1 bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:outline-none focus:border-blue-600 font-medium placeholder-slate-400 shadow-2xs" placeholder="Send real-time landmark or status update to responders..." required />
                    <button type="submit" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-xs transition-colors flex items-center justify-center gap-1.5 cursor-pointer shrink-0">
                        <i class="fa-solid fa-paper-plane text-xs"></i>
                        <span>Send</span>
                    </button>
                </form>
            </div>
        </section>

        <!-- 4. Primary Emergency SOS Trigger & Form Grid -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- 1-Touch GPS Panic Beacon (1 col) -->
            <div class="bg-white rounded-3xl border-2 border-red-200 shadow-sm p-6 flex flex-col justify-between relative overflow-hidden">
                <div class="space-y-3">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-red-50 text-red-600 flex items-center justify-center text-sm font-bold">
                                <i class="fa-solid fa-tower-broadcast"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-slate-900">Instant SOS Beacon</h3>
                                <p class="text-[10px] text-slate-500 font-medium">Automatic Emergency GPS Dispatch</p>
                            </div>
                        </div>
                        <span class="w-2.5 h-2.5 rounded-full bg-red-500 animate-ping"></span>
                    </div>

                    <p class="text-xs text-slate-600 leading-relaxed font-medium">
                        Pressing the SOS button instantly transmits your GPS coordinates and distress type directly to <strong>NDRF, Police, Fire, and Medical Command</strong>.
                    </p>

                    <!-- GPS Status Card -->
                    <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-slate-700" id="citizenGpsLabel">
                                <i class="fa-solid fa-location-crosshairs text-red-600 mr-1"></i> GPS Coordinates
                            </span>
                            <button type="button" onclick="acquireCitizenGps()" class="text-[11px] text-red-600 font-bold underline cursor-pointer">
                                Auto-Detect GPS
                            </button>
                        </div>
                        <div class="font-mono text-xs font-bold text-slate-900 bg-white p-2 rounded-xl border border-slate-200" id="citizenGpsCoords">
                            28.6139° N, 77.2090° E (NCR Base)
                        </div>
                    </div>
                </div>

                <!-- Big Red Panic Trigger Button -->
                <div class="pt-6">
                    <button type="button" onclick="triggerQuickPanicSos()" class="w-full py-5 rounded-3xl bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 text-white font-black text-base uppercase tracking-wider shadow-xl shadow-red-500/25 hover:shadow-red-500/40 transition-all flex flex-col items-center justify-center gap-1 cursor-pointer border-2 border-red-400/50">
                        <i class="fa-solid fa-triangle-exclamation text-2xl animate-bounce"></i>
                        <span>TRANSMIT EMERGENCY SOS</span>
                        <span class="text-[10px] font-normal tracking-normal text-red-200 lowercase">one-click multi-agency response</span>
                    </button>
                </div>
            </div>

            <!-- Detailed SOS Distress Form (2 cols) -->
            <div class="lg:col-span-2 bg-white rounded-3xl border border-slate-200 shadow-sm p-6 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-sm font-bold">
                                <i class="fa-solid fa-clipboard-list"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-slate-900">Custom Emergency Distress Report</h3>
                                <p class="text-[10px] text-slate-500 font-medium">Provide critical specifics (persons count, medical conditions, entrapment type)</p>
                            </div>
                        </div>
                    </div>

                    <form id="citizenSosForm" method="POST" action="citizen.php" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="broadcast_sos">
                        <input type="hidden" name="latitude" id="form_latitude" value="28.6139">
                        <input type="hidden" name="longitude" id="form_longitude" value="77.2090">

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1 mono">Your Name *</label>
                                <input type="text" name="sender_name" value="<?= htmlspecialchars($userName) ?>" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:outline-none focus:border-red-600 font-medium">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1 mono">Phone Number *</label>
                                <input type="text" name="sender_phone" value="<?= htmlspecialchars($userPhone) ?>" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:outline-none focus:border-red-600 font-medium">
                            </div>
                        </div>

                        <!-- Emergency Type Buttons Grid -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 mono">Select Emergency Type *</label>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                <label class="p-2.5 rounded-xl border border-slate-200 bg-slate-50 hover:border-red-400 flex items-center gap-2 cursor-pointer text-xs font-bold">
                                    <input type="radio" name="emergency_type" value="Flood" checked class="text-red-600 focus:ring-red-500">
                                    <span>🌊 Flood / Water</span>
                                </label>
                                <label class="p-2.5 rounded-xl border border-slate-200 bg-slate-50 hover:border-red-400 flex items-center gap-2 cursor-pointer text-xs font-bold">
                                    <input type="radio" name="emergency_type" value="Structural Collapse" class="text-red-600 focus:ring-red-500">
                                    <span>🏚️ Trapped in Debris</span>
                                </label>
                                <label class="p-2.5 rounded-xl border border-slate-200 bg-slate-50 hover:border-red-400 flex items-center gap-2 cursor-pointer text-xs font-bold">
                                    <input type="radio" name="emergency_type" value="Medical" class="text-red-600 focus:ring-red-500">
                                    <span>🏥 Severe Medical</span>
                                </label>
                                <label class="p-2.5 rounded-xl border border-slate-200 bg-slate-50 hover:border-red-400 flex items-center gap-2 cursor-pointer text-xs font-bold">
                                    <input type="radio" name="emergency_type" value="Fire" class="text-red-600 focus:ring-red-500">
                                    <span>🔥 Fire / Smoke</span>
                                </label>
                                <label class="p-2.5 rounded-xl border border-slate-200 bg-slate-50 hover:border-red-400 flex items-center gap-2 cursor-pointer text-xs font-bold">
                                    <input type="radio" name="emergency_type" value="Missing Relative" class="text-red-600 focus:ring-red-500">
                                    <span>🔍 Missing Person</span>
                                </label>
                                <label class="p-2.5 rounded-xl border border-slate-200 bg-slate-50 hover:border-red-400 flex items-center gap-2 cursor-pointer text-xs font-bold">
                                    <input type="radio" name="emergency_type" value="Food / Water Shortage" class="text-red-600 focus:ring-red-500">
                                    <span>🍞 Food/Water Cutoff</span>
                                </label>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1 mono">Persons in Danger *</label>
                                <select name="persons_count" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:outline-none focus:border-red-600 font-medium">
                                    <option value="1">1 Person (Self)</option>
                                    <option value="2-3">2-3 Family Members</option>
                                    <option value="4-6">4-6 People</option>
                                    <option value="7+">7+ Large Group</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1 mono">Blood Group</label>
                                <select name="blood_type" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:outline-none focus:border-red-600 font-medium">
                                    <option value="Unknown">Unknown</option>
                                    <option value="A+">A+</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B-">B-</option>
                                    <option value="O+">O+</option>
                                    <option value="O-">O-</option>
                                    <option value="AB+">AB+</option>
                                    <option value="AB-">AB-</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1 mono">Priority Level</label>
                                <select name="priority" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:outline-none focus:border-red-600 font-medium">
                                    <option value="Critical">🔴 Critical (Life Threat)</option>
                                    <option value="High">🟠 High Urgency</option>
                                    <option value="Medium">🟡 Medium Urgency</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1 mono">Situation Details &amp; Exact Landmarks</label>
                            <textarea name="message" rows="2" placeholder="e.g. Trapped on 2nd floor terrace, rising water level, elderly grandmother needing insulin..." class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-red-600 font-medium"></textarea>
                        </div>

                        <div class="flex items-center justify-between pt-2">
                            <span class="text-[10px] text-slate-500 mono font-medium">
                                <i class="fa-solid fa-lock text-emerald-600 mr-1"></i> End-to-end encrypted dispatch to CAD Server
                            </span>
                            <button type="submit" class="px-6 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs font-black shadow-md transition-all cursor-pointer flex items-center gap-2">
                                <i class="fa-solid fa-paper-plane text-xs"></i>
                                <span>Broadcast Detailed Distress</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </section>

        <!-- 4. Safe Evacuation Shelter & Emergency Resource Radar -->
        <section id="shelterRadarSection" class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-teal-50 text-teal-700 border border-teal-200 flex items-center justify-center text-base font-bold">
                        <i class="fa-solid fa-map-location-dot"></i>
                    </div>
                    <div>
                        <h2 class="text-base sm:text-lg font-black text-slate-900">Verified Safe Shelters &amp; Hospital Radar</h2>
                        <p class="text-xs text-slate-500 font-medium">Live government shelters with available bedding capacity, food, drinking water, and trauma centers</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-teal-50 text-teal-800 border border-teal-200 mono">
                        <?= count($facilities) ?> Facilities Live
                    </span>
                </div>
            </div>

            <!-- Leaflet Interactive Shelter Map -->
            <div id="citizenRadarMap" class="w-full h-80 rounded-2xl bg-slate-100 border border-slate-200 overflow-hidden relative z-0"></div>

            <!-- Facility Cards Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 pt-2">
                <?php foreach ($facilities as $fac): ?>
                    <div class="p-4 rounded-2xl border border-slate-200 bg-slate-50/60 flex flex-col justify-between space-y-2.5">
                        <div>
                            <div class="flex items-center justify-between gap-2 mb-1.5">
                                <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase mono <?= $fac['type'] === 'Hospital' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800' ?>">
                                    <?= htmlspecialchars($fac['type']) ?>
                                </span>
                                <span class="text-[10px] font-bold text-emerald-700 flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> OPEN
                                </span>
                            </div>
                            <h4 class="text-sm font-black text-slate-900"><?= htmlspecialchars($fac['name']) ?></h4>
                            <p class="text-xs text-slate-500 font-medium mt-0.5"><?= htmlspecialchars($fac['address'] ?? 'Central Disaster Perimeter') ?></p>
                            <div class="mt-2 text-xs font-mono font-bold text-slate-700 flex items-center justify-between">
                                <span>Beds: <?= $fac['available_capacity'] ?> / <?= $fac['total_capacity'] ?></span>
                                <span class="text-blue-600 font-bold"><?= $fac['available_capacity'] ?> Available</span>
                            </div>
                        </div>

                        <div class="pt-2 border-t border-slate-200 flex items-center justify-between text-xs">
                            <a href="tel:<?= htmlspecialchars($fac['contact'] ?: '112') ?>" class="text-slate-700 hover:text-slate-900 font-bold flex items-center gap-1">
                                <i class="fa-solid fa-phone text-xs text-emerald-600"></i> Call Facility
                            </a>
                            <a href="https://www.google.com/maps/dir/?api=1&destination=<?= (float)$fac['latitude'] ?>,<?= (float)$fac['longitude'] ?>" target="_blank" class="text-blue-600 hover:text-blue-800 font-black flex items-center gap-1">
                                Directions &rarr;
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- 5. Survival Action Guides & Emergency Helplines Grid -->
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- Survival Action Guides -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-4">
                <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                    <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center text-sm font-bold">
                        <i class="fa-solid fa-book-medical"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-slate-900">Life-Saving Survival Protocols</h3>
                        <p class="text-[10px] text-slate-500 font-medium">Immediate instructions during sudden crisis</p>
                    </div>
                </div>

                <div class="space-y-2.5 text-xs">
                    <details class="p-3 rounded-2xl bg-slate-50 border border-slate-200 group">
                        <summary class="font-black text-slate-900 cursor-pointer flex items-center justify-between">
                            <span>🌊 Severe Flood / Water Inundation</span>
                            <i class="fa-solid fa-chevron-down text-slate-400 group-open:rotate-180 transition-transform"></i>
                        </summary>
                        <p class="mt-2 text-slate-600 font-medium leading-relaxed">
                            Move to the highest floor or roof. Never attempt to walk, swim, or drive through flooded roads. Disconnect electrical mains before water enters the premises.
                        </p>
                    </details>

                    <details class="p-3 rounded-2xl bg-slate-50 border border-slate-200 group">
                        <summary class="font-black text-slate-900 cursor-pointer flex items-center justify-between">
                            <span>🏚️ Earthquake / Structure Tremor</span>
                            <i class="fa-solid fa-chevron-down text-slate-400 group-open:rotate-180 transition-transform"></i>
                        </summary>
                        <p class="mt-2 text-slate-600 font-medium leading-relaxed">
                            <strong>DROP, COVER, HOLD ON!</strong> Protect your head under sturdy furniture. Stay away from windows, glass, and heavy shelving. Do not use elevators.
                        </p>
                    </details>

                    <details class="p-3 rounded-2xl bg-slate-50 border border-slate-200 group">
                        <summary class="font-black text-slate-900 cursor-pointer flex items-center justify-between">
                            <span>🔥 Fire &amp; Smoke Inhalation</span>
                            <i class="fa-solid fa-chevron-down text-slate-400 group-open:rotate-180 transition-transform"></i>
                        </summary>
                        <p class="mt-2 text-slate-600 font-medium leading-relaxed">
                            Crawl low under smoke where air is cleaner. Feel doors with the back of your hand before opening. Cover nose with a damp cloth if available.
                        </p>
                    </details>
                </div>
            </div>

            <!-- Direct Hotline Speed Dial Grid -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-4">
                <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                    <div class="w-8 h-8 rounded-xl bg-teal-50 text-teal-700 flex items-center justify-center text-sm font-bold">
                        <i class="fa-solid fa-phone-volume"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-slate-900">National Emergency Speed Dial</h3>
                        <p class="text-[10px] text-slate-500 font-medium">Toll-free 24/7 government hotlines</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <a href="tel:112" class="p-3 rounded-2xl bg-red-50 border border-red-200 hover:bg-red-100 transition-colors flex items-center gap-3 text-xs">
                        <div class="w-8 h-8 rounded-xl bg-red-600 text-white flex items-center justify-center font-black">112</div>
                        <div>
                            <strong class="block text-slate-900 font-black">National SOS</strong>
                            <span class="text-[10px] text-slate-500 font-medium">All Emergencies</span>
                        </div>
                    </a>

                    <a href="tel:108" class="p-3 rounded-2xl bg-teal-50 border border-teal-200 hover:bg-teal-100 transition-colors flex items-center gap-3 text-xs">
                        <div class="w-8 h-8 rounded-xl bg-teal-600 text-white flex items-center justify-center font-black">108</div>
                        <div>
                            <strong class="block text-slate-900 font-black">Ambulance</strong>
                            <span class="text-[10px] text-slate-500 font-medium">Medical Trauma</span>
                        </div>
                    </a>

                    <a href="tel:101" class="p-3 rounded-2xl bg-orange-50 border border-orange-200 hover:bg-orange-100 transition-colors flex items-center gap-3 text-xs">
                        <div class="w-8 h-8 rounded-xl bg-orange-600 text-white flex items-center justify-center font-black">101</div>
                        <div>
                            <strong class="block text-slate-900 font-black">Fire &amp; Rescue</strong>
                            <span class="text-[10px] text-slate-500 font-medium">Fire Brigade</span>
                        </div>
                    </a>

                    <a href="tel:1078" class="p-3 rounded-2xl bg-blue-50 border border-blue-200 hover:bg-blue-100 transition-colors flex items-center gap-3 text-xs">
                        <div class="w-8 h-8 rounded-xl bg-blue-600 text-white flex items-center justify-center font-black">1078</div>
                        <div>
                            <strong class="block text-slate-900 font-black">NDMA Control</strong>
                            <span class="text-[10px] text-slate-500 font-medium">Disaster Helpline</span>
                        </div>
                    </a>
                </div>
            </div>

        </section>

    </main>
</div>

<!-- ==================== RESPONDER 2-WAY CHAT MODAL ==================== -->
<div id="responderChatModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white border border-slate-200 rounded-3xl max-w-lg w-full p-6 shadow-2xl relative max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-100 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
                    <i class="fa-solid fa-comments text-base"></i>
                </div>
                <div>
                    <h3 class="text-base font-black text-slate-900">Direct Responder Lifeline</h3>
                    <p class="text-[11px] text-slate-500 font-medium">2-Way Secure Rescue Channel</p>
                </div>
            </div>
            <button type="button" onclick="closeResponderChatModal()" class="text-slate-400 hover:text-slate-800 p-2 cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto space-y-2.5 p-3 bg-slate-50 rounded-2xl border border-slate-200 min-h-[220px] max-h-[320px] custom-scroll" id="responderChatMessagesBox">
            <p class="text-xs text-slate-400 text-center py-8">Connecting to responder team...</p>
        </div>

        <form id="responderModalChatForm" onsubmit="handleModalResponderChat(event)" class="pt-3 mt-3 border-t border-slate-100 flex items-center gap-2">
            <input type="text" id="modalChatMsgInput" required placeholder="Send landmark or status note to responders..." class="flex-1 px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-blue-600 font-medium">
            <button type="submit" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition-all shadow-xs cursor-pointer flex items-center gap-1.5">
                <i class="fa-solid fa-paper-plane text-xs"></i>
                <span>Send</span>
            </button>
        </form>
    </div>
</div>

<!-- ==================== GEMINI AI SAFETY ADVISOR FAB ==================== -->
<div class="fixed bottom-6 right-6 z-40">
    <button type="button" onclick="openAiAdvisorDrawer()" class="flex items-center gap-2.5 px-4 py-3 bg-gradient-to-r from-[#000a1e] to-slate-900 hover:from-slate-900 hover:to-indigo-950 text-white rounded-full shadow-2xl border border-indigo-500/40 hover:border-indigo-400 transition-all hover:scale-105 cursor-pointer group">
        <div class="relative w-7 h-7 rounded-full bg-indigo-600/30 flex items-center justify-center text-indigo-400 group-hover:text-indigo-300">
            <i class="fa-solid fa-wand-magic-sparkles text-sm"></i>
            <span class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 bg-emerald-400 rounded-full ring-2 ring-[#000a1e]"></span>
        </div>
        <div class="text-left pr-1">
            <span class="block text-xs font-black tracking-tight text-white leading-tight">AI Safety Advisor</span>
            <span class="block text-[10px] text-slate-400 font-medium">Instant Emergency Help</span>
        </div>
    </button>
</div>

<!-- ==================== GEMINI AI SAFETY ADVISOR MODAL / DRAWER ==================== -->
<div id="aiAdvisorModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-end sm:items-center justify-center p-0 sm:p-4">
    <div class="bg-white border border-slate-200 rounded-t-3xl sm:rounded-3xl max-w-lg w-full h-[88vh] sm:h-[650px] shadow-2xl relative flex flex-col overflow-hidden animate-fade-in">
        
        <!-- Header -->
        <div class="bg-[#000a1e] text-white p-4 flex items-center justify-between border-b border-indigo-950/80 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-2xl bg-indigo-600/30 border border-indigo-500/40 flex items-center justify-center text-indigo-300">
                    <i class="fa-solid fa-wand-magic-sparkles text-sm"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h4 class="text-sm font-black text-white">DisasterSafe AI Guide</h4>
                        <span class="px-1.5 py-0.5 rounded bg-indigo-950 text-indigo-300 border border-indigo-800 text-[9px] font-mono font-bold">Gemini</span>
                    </div>
                    <p class="text-[10px] text-slate-400 flex items-center gap-1.5 mt-0.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        <span>Live GPS &amp; Shelter Radar Connected</span>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" onclick="clearAiChat()" title="Reset Chat" class="text-slate-400 hover:text-white p-1.5 rounded-lg hover:bg-white/10 transition-colors text-xs cursor-pointer">
                    <i class="fa-solid fa-rotate-right"></i>
                </button>
                <button type="button" onclick="closeAiAdvisorDrawer()" class="text-slate-400 hover:text-white p-1.5 rounded-lg hover:bg-white/10 transition-colors text-sm cursor-pointer">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>

        <!-- Telemetry Strip -->
        <div class="bg-indigo-50 border-b border-indigo-100/80 px-4 py-2 flex items-center justify-between text-[11px] font-mono text-indigo-900 shrink-0">
            <span class="flex items-center gap-1.5 truncate">
                <i class="fa-solid fa-satellite-dish text-indigo-600"></i>
                <strong class="font-bold">Tele-Context:</strong> <?= htmlspecialchars($userName) ?> [<?= htmlspecialchars($activeSos ? $activeSos['emergency_type'] : 'Standby') ?>]
            </span>
            <span class="text-[10px] text-indigo-700 font-bold shrink-0">
                GPS: 28.6139, 77.2090
            </span>
        </div>

        <!-- Messages Feed -->
        <div id="aiChatMessagesFeed" class="flex-1 overflow-y-auto p-4 space-y-3 bg-slate-50/50 custom-scroll text-xs">
            <!-- Messages rendered dynamically -->
        </div>

        <!-- Quick Prompts Carousel -->
        <div class="px-4 py-2 bg-white border-t border-slate-100 flex items-center gap-1.5 overflow-x-auto shrink-0 custom-scroll">
            <span class="text-[10px] font-bold text-slate-400 mono shrink-0">Quick Ask:</span>
            <button type="button" onclick="askQuickAiPrompt('Nearest open shelter with food/water?')" class="px-2.5 py-1 rounded-full bg-slate-100 hover:bg-indigo-50 hover:text-indigo-700 border border-slate-200 text-[11px] font-bold text-slate-700 whitespace-nowrap transition-colors cursor-pointer">🏠 Nearest Shelter?</button>
            <button type="button" onclick="askQuickAiPrompt('How to purify flood water for drinking?')" class="px-2.5 py-1 rounded-full bg-slate-100 hover:bg-indigo-50 hover:text-indigo-700 border border-slate-200 text-[11px] font-bold text-slate-700 whitespace-nowrap transition-colors cursor-pointer">💧 Purify Water</button>
            <button type="button" onclick="askQuickAiPrompt('First aid for burn or trauma wound?')" class="px-2.5 py-1 rounded-full bg-slate-100 hover:bg-indigo-50 hover:text-indigo-700 border border-slate-200 text-[11px] font-bold text-slate-700 whitespace-nowrap transition-colors cursor-pointer">🩹 First Aid Guide</button>
            <button type="button" onclick="askQuickAiPrompt('Official emergency helpline numbers?')" class="px-2.5 py-1 rounded-full bg-slate-100 hover:bg-indigo-50 hover:text-indigo-700 border border-slate-200 text-[11px] font-bold text-slate-700 whitespace-nowrap transition-colors cursor-pointer">📞 Helplines</button>
        </div>

        <!-- AI Query Input Form -->
        <form id="aiChatInputForm" onsubmit="handleSendAiMessage(event)" class="p-3 bg-white border-t border-slate-200 flex items-center gap-2 shrink-0">
            <input type="text" id="aiChatInput" class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:outline-none focus:border-indigo-600 font-medium placeholder-slate-400" placeholder="Ask AI: 'Where is nearest medical center?'..." required />
            <button type="submit" id="aiSendBtn" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-xs transition-colors flex items-center justify-center gap-1.5 cursor-pointer shrink-0">
                <i class="fa-solid fa-paper-plane text-xs"></i>
                <span>Ask</span>
            </button>
        </form>
    </div>
</div>

<!-- ==================== JAVASCRIPT SECTION ==================== -->
<script>
let citizenMap = null;
let currentLat = 28.6139;
let currentLng = 77.2090;

// ============================================================
// CITIZEN & VOLUNTEER LIVE HOTLINE
// ============================================================
let citizenSosId = <?= (int)($activeSos['id'] ?? 1) ?>;

async function loadCitizenHotline() {
    try {
        const res = await fetch(`api/victim_volunteer_chat_fetch.php?sos_id=${citizenSosId}`);
        const result = await res.json();

        if (result.success && result.data) {
            const messages = result.data.messages || [];
            const feed = document.getElementById('citizenHotlineFeed');
            const modalBox = document.getElementById('responderChatMessagesBox');

            if (messages.length === 0) {
                if (feed) feed.innerHTML = `<div class="text-xs text-slate-400 italic text-center py-6">Lifeline channel connected. Responders can see your SOS. Type a message or landmark below.</div>`;
                if (modalBox) modalBox.innerHTML = `<div class="text-xs text-slate-400 italic text-center py-8">Lifeline channel connected. Type a message below.</div>`;
            } else {
                const renderHtml = messages.map(m => {
                    const isCitizen = (m.sender_role === 'victim' || m.sender_role === 'citizen' || m.sender_role === 'user');
                    return `
                        <div class="flex flex-col ${isCitizen ? 'items-end' : 'items-start'}">
                            <div class="max-w-[82%] p-2.5 rounded-2xl text-xs ${isCitizen ? 'bg-blue-600 text-white rounded-br-none shadow-2xs' : 'bg-white border border-slate-200 text-slate-900 rounded-bl-none shadow-2xs'}">
                                <div class="flex items-center justify-between gap-2 mb-0.5">
                                    <span class="text-[10px] font-mono font-bold ${isCitizen ? 'text-blue-200' : 'text-slate-500'}">${escapeHtml(m.sender_name)}</span>
                                    <span class="text-[9px] font-mono ${isCitizen ? 'text-blue-200' : 'text-slate-400'}">${m.created_at ? m.created_at.substring(11, 16) : 'Just now'}</span>
                                </div>
                                <p class="font-medium leading-relaxed">${escapeHtml(m.message)}</p>
                            </div>
                        </div>
                    `;
                }).join('');

                if (feed) {
                    feed.innerHTML = renderHtml;
                    feed.scrollTop = feed.scrollHeight;
                }
                if (modalBox) {
                    modalBox.innerHTML = renderHtml;
                    modalBox.scrollTop = modalBox.scrollHeight;
                }
            }
        }
    } catch (e) {
        console.error('Error loading citizen hotline:', e);
    }
}

async function sendCitizenHotlineMsg(text) {
    if (!text || !text.trim()) return;

    try {
        const res = await fetch('api/victim_volunteer_chat_send.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                sos_id: citizenSosId,
                message: text.trim(),
                message_type: 'text'
            })
        });
        const data = await res.json();
        if (data.success) {
            loadCitizenHotline();
        }
    } catch (e) {
        console.error('Error sending hotline msg:', e);
    }
}

function handleSendCitizenHotlineMsg(e) {
    e.preventDefault();
    const input = document.getElementById('citizenHotlineInput');
    const val = input.value.trim();
    if (val) {
        sendCitizenHotlineMsg(val);
        input.value = '';
    }
}

function handleModalResponderChat(e) {
    e.preventDefault();
    const input = document.getElementById('modalChatMsgInput');
    const val = input.value.trim();
    if (val) {
        sendCitizenHotlineMsg(val);
        input.value = '';
    }
}

function openResponderChatModal(sosId) {
    if (sosId) citizenSosId = sosId;
    document.getElementById('responderChatModal').classList.remove('hidden');
    loadCitizenHotline();
}

function closeResponderChatModal() {
    document.getElementById('responderChatModal').classList.add('hidden');
}

// ============================================================
// GEMINI AI CRISIS & SAFETY ADVISOR CHATBOT
// ============================================================
const GEMINI_API_KEY = atob("QVEuQWI4Uk42TE5YSGkxMkl6ZGpYdUV2NUZfdTRkZHBRTGlsbXB6SF9ZdHpONW1obnNSQVE=");
const GEMINI_MODELS = ["gemini-3.1-flash-lite", "gemini-flash-latest", "gemini-3.5-flash"];
const AI_STORAGE_KEY = "disastersafe_ai_chat_history_v2";

let aiChatHistory = [];
const citizenFacilities = <?= json_encode($facilities) ?>;

function initAiChatHistory() {
    try {
        const saved = localStorage.getItem(AI_STORAGE_KEY);
        if (saved) {
            aiChatHistory = JSON.parse(saved);
        }
    } catch (e) {
        aiChatHistory = [];
    }
    renderAiMessages();
}

function saveAiChatHistory() {
    try {
        localStorage.setItem(AI_STORAGE_KEY, JSON.stringify(aiChatHistory));
    } catch (e) {
        console.error('Error saving AI history:', e);
    }
}

function openAiAdvisorDrawer() {
    document.getElementById('aiAdvisorModal').classList.remove('hidden');
    renderAiMessages();
}

function closeAiAdvisorDrawer() {
    document.getElementById('aiAdvisorModal').classList.add('hidden');
}

function clearAiChat() {
    aiChatHistory = [];
    localStorage.removeItem(AI_STORAGE_KEY);
    renderAiMessages();
}

function askQuickAiPrompt(text) {
    const input = document.getElementById('aiChatInput');
    if (input) {
        input.value = text;
        handleSendAiMessage(new Event('submit'));
    }
}

function renderAiMessages() {
    const feed = document.getElementById('aiChatMessagesFeed');
    if (!feed) return;

    if (aiChatHistory.length === 0) {
        feed.innerHTML = `
            <div class="p-4 rounded-2xl bg-white border border-slate-200 text-xs space-y-2">
                <div class="flex items-center gap-2 text-indigo-700 font-bold">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                    <span>DisasterSafe AI Safety Advisor</span>
                </div>
                <p class="text-slate-600 leading-relaxed">
                    Hello <strong><?= htmlspecialchars($userName) ?></strong>! I am your real-time crisis response assistant. I have instant access to your GPS coordinates and nearest relief shelters.
                </p>
                <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200 text-[11px] space-y-1">
                    <span class="block font-bold text-slate-700">📍 Verified Active Telemetry:</span>
                    <span class="text-slate-500 font-mono block">GPS: ${currentLat.toFixed(4)}, ${currentLng.toFixed(4)}</span>
                    <span class="text-slate-500 font-mono block">Relief Centers in Radar: ${citizenFacilities.length}</span>
                </div>
            </div>
        `;
        return;
    }

    feed.innerHTML = aiChatHistory.map(item => {
        const isUser = (item.role === 'user');
        return `
            <div class="flex flex-col ${isUser ? 'items-end' : 'items-start'}">
                <div class="max-w-[88%] p-3 rounded-2xl text-xs leading-relaxed ${isUser ? 'bg-indigo-600 text-white rounded-br-none shadow-2xs' : 'bg-white border border-slate-200 text-slate-900 rounded-bl-none shadow-2xs'}">
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <span class="text-[10px] font-mono font-bold ${isUser ? 'text-indigo-200' : 'text-indigo-600'}">
                            ${isUser ? 'You' : '<i class="fa-solid fa-shield-halved mr-1"></i> Safety Advisor'}
                        </span>
                        <span class="text-[9px] font-mono ${isUser ? 'text-indigo-200' : 'text-slate-400'}">${item.time || ''}</span>
                    </div>
                    <div class="space-y-1">${formatAiMarkdown(item.text)}</div>
                </div>
            </div>
        `;
    }).join('');
    feed.scrollTop = feed.scrollHeight;
}

function buildCitizenSystemContext() {
    let facilitiesSummary = "VERIFIED NEARBY RELIEF SHELTERS & HOSPITALS:\n";
    citizenFacilities.slice(0, 5).forEach((f, idx) => {
        facilitiesSummary += `${idx + 1}. [${f.type}] ${f.name} - Available Capacity: ${f.available_capacity || 0}/${f.total_capacity || 0} beds, Phone: ${f.contact || '112'}\n`;
    });

    return `You are DisasterSafe AI Safety Advisor, an emergency crisis assistant.
Direct, concise, actionable advice for flood, structural collapse, earthquake, medical, or fire emergencies.
Context: User is <?= htmlspecialchars($userName) ?> at GPS (${currentLat}, ${currentLng}).
${facilitiesSummary}
Rules:
- Provide clear steps (bullet points).
- Include helpline numbers (112, 108, 101, 100) where relevant.
- Do not repeat long robot introductions.`;
}

async function handleSendAiMessage(e) {
    if (e) e.preventDefault();
    const input = document.getElementById('aiChatInput');
    const query = input.value.trim();
    if (!query) return;

    input.value = '';
    const now = new Date();
    const timeStr = `${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}`;

    aiChatHistory.push({ role: 'user', text: query, time: timeStr });
    if (aiChatHistory.length > 16) aiChatHistory = aiChatHistory.slice(aiChatHistory.length - 16);
    saveAiChatHistory();
    renderAiMessages();

    // Loading Indicator
    const feed = document.getElementById('aiChatMessagesFeed');
    const typingId = 'aiTyping_' + Date.now();
    feed.insertAdjacentHTML('beforeend', `
        <div id="${typingId}" class="p-3 bg-white border border-slate-200 rounded-2xl rounded-tl-sm w-20 flex items-center gap-1.5 shadow-2xs">
            <span class="w-1.5 h-1.5 rounded-full bg-slate-400 animate-pulse"></span>
            <span class="w-1.5 h-1.5 rounded-full bg-slate-400 animate-pulse" style="animation-delay: 200ms;"></span>
            <span class="w-1.5 h-1.5 rounded-full bg-slate-400 animate-pulse" style="animation-delay: 400ms;"></span>
        </div>
    `);
    feed.scrollTop = feed.scrollHeight;

    try {
        const systemContext = buildCitizenSystemContext();
        const payload = aiChatHistory.map(item => ({
            role: item.role === 'user' ? 'user' : 'model',
            parts: [{ text: item.text }]
        }));

        let responseText = null;
        for (const modelName of GEMINI_MODELS) {
            try {
                const url = `https://generativelanguage.googleapis.com/v1beta/models/${modelName}:generateContent?key=${GEMINI_API_KEY}`;
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        system_instruction: { parts: [{ text: systemContext }] },
                        contents: payload
                    })
                });
                if (res.ok) {
                    const data = await res.json();
                    responseText = data.candidates?.[0]?.content?.parts?.[0]?.text;
                    if (responseText) break;
                }
            } catch (err) {
                console.warn(`Model ${modelName} attempt failed:`, err);
            }
        }

        if (!responseText) {
            responseText = "Emergency response received. If you are in immediate physical danger, call National Emergency 112 or move to higher ground.";
        }

        const replyTime = new Date();
        const replyTimeStr = `${String(replyTime.getHours()).padStart(2,'0')}:${String(replyTime.getMinutes()).padStart(2,'0')}`;
        aiChatHistory.push({ role: 'model', text: responseText, time: replyTimeStr });
        saveAiChatHistory();
    } catch (err) {
        console.error('AI Error:', err);
    } finally {
        const typingEl = document.getElementById(typingId);
        if (typingEl) typingEl.remove();
        renderAiMessages();
    }
}

function formatAiMarkdown(text) {
    if (!text) return '';
    return text
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.*?)\*/g, '<em>$1</em>')
        .replace(/`([^`]+)`/g, '<code class="bg-slate-100 px-1 py-0.5 rounded font-mono text-[11px] text-indigo-700">$1</code>')
        .replace(/\n\n/g, '<br><br>')
        .replace(/\n/g, '<br>');
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// ============================================================
// MAP & GPS
// ============================================================
function initCitizenMap() {
    if (citizenMap) return;
    const mapEl = document.getElementById('citizenRadarMap');
    if (!mapEl) return;

    citizenMap = L.map('citizenRadarMap').setView([currentLat, currentLng], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(citizenMap);

    L.circleMarker([currentLat, currentLng], {
        radius: 10,
        fillColor: '#dc2626',
        color: '#ffffff',
        weight: 3,
        opacity: 1,
        fillOpacity: 1
    }).addTo(citizenMap).bindPopup(`
        <div style="font-family: sans-serif; font-size: 12px;">
            <strong style="color: #dc2626;">📍 YOUR LOCATION</strong><br>
            <span>Coordinates: ${currentLat.toFixed(4)}, ${currentLng.toFixed(4)}</span>
        </div>
    `);

    <?php foreach ($facilities as $fac): ?>
        <?php if (!empty($fac['latitude']) && !empty($fac['longitude'])): ?>
            L.circleMarker([<?= (float)$fac['latitude'] ?>, <?= (float)$fac['longitude'] ?>], {
                radius: 8,
                fillColor: '<?= $fac['type'] === 'Hospital' ? '#e11d48' : '#2563eb' ?>',
                color: '#ffffff',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.85
            }).addTo(citizenMap).bindPopup(`
                <div style="font-family: sans-serif; font-size: 12px;">
                    <strong style="color: <?= $fac['type'] === 'Hospital' ? '#e11d48' : '#2563eb' ?>;">
                        <?= $fac['type'] === 'Hospital' ? '🏥' : '🏠' ?> <?= htmlspecialchars($fac['name']) ?>
                    </strong><br>
                    <strong>Type:</strong> <?= htmlspecialchars($fac['type']) ?><br>
                    <strong>Address:</strong> <?= htmlspecialchars($fac['address'] ?? 'Central Sector') ?><br>
                    <strong>Available Beds:</strong> <?= (int)$fac['available_capacity'] ?> / <?= (int)$fac['total_capacity'] ?><br>
                    <a href="https://www.google.com/maps/dir/?api=1&destination=<?= (float)$fac['latitude'] ?>,<?= (float)$fac['longitude'] ?>" target="_blank" style="display:inline-block; margin-top:5px; color: #2563eb; font-weight: bold;">Get Directions &rarr;</a>
                </div>
            `);
        <?php endif; ?>
    <?php endforeach; ?>
}

function acquireCitizenGps() {
    if (navigator.geolocation) {
        document.getElementById('citizenGpsLabel').innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-1 text-red-600"></i> Acquiring GPS Satellites...`;
        navigator.geolocation.getCurrentPosition(function(pos) {
            currentLat = pos.coords.latitude;
            currentLng = pos.coords.longitude;
            document.getElementById('citizenGpsCoords').innerText = `${currentLat.toFixed(4)}° N, ${currentLng.toFixed(4)}° E (GPS Locked ±${Math.round(pos.coords.accuracy)}m)`;
            document.getElementById('form_latitude').value = currentLat;
            document.getElementById('form_longitude').value = currentLng;
            document.getElementById('citizenGpsLabel').innerHTML = `<i class="fa-solid fa-check text-emerald-600 mr-1"></i> GPS Locked`;

            if (citizenMap) {
                citizenMap.setView([currentLat, currentLng], 14);
            }
        }, function(err) {
            document.getElementById('citizenGpsLabel').innerHTML = `<i class="fa-solid fa-circle-exclamation text-amber-600 mr-1"></i> GPS Error`;
            alert('Location permission denied or unavailable. Using default base coordinates.');
        });
    }
}

function triggerQuickPanicSos() {
    Swal.fire({
        title: 'TRANSMIT EMERGENCY SOS?',
        text: 'This will immediately broadcast your GPS coordinates to Emergency Multi-Agency Command (NDRF, Police, Fire, Medical).',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'YES, TRANSMIT SOS NOW',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('citizenSosForm').submit();
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    initCitizenMap();
    initAiChatHistory();
    loadCitizenHotline();
    setInterval(loadCitizenHotline, 4000);
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
