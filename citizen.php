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

        <!-- 3. Primary Emergency SOS Trigger & Form Grid -->
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
                <div class="w-10 h-10 rounded-2xl bg-red-50 text-red-600 flex items-center justify-center">
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

        <div class="flex-1 overflow-y-auto space-y-2 p-3 bg-slate-50 rounded-2xl border border-slate-200 min-h-[220px] max-h-[320px]" id="responderChatMessagesBox">
            <p class="text-xs text-slate-400 text-center py-8">Connecting to responder team...</p>
        </div>

        <form method="POST" action="citizen.php" class="pt-3 mt-3 border-t border-slate-100 flex items-center gap-2">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="send_responder_chat">
            <input type="hidden" name="sos_id" id="modal_chat_sos_id" value="<?= $activeSos['id'] ?? 0 ?>">
            <input type="text" name="message" required placeholder="Send update, landmark, or request to responder..." class="flex-1 px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-red-600 font-medium">
            <button type="submit" class="px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-bold transition-all shadow-xs cursor-pointer">
                <i class="fa-solid fa-paper-plane"></i>
            </button>
        </form>
    </div>
</div>

<!-- Leaflet Radar Map Script -->
<script>
let citizenMap = null;

function initCitizenMap() {
    if (citizenMap) return;
    const mapEl = document.getElementById('citizenRadarMap');
    if (!mapEl) return;

    citizenMap = L.map('citizenRadarMap').setView([28.6139, 77.2090], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(citizenMap);

    // Plot Citizen Current Position
    const userMarker = L.circleMarker([28.6139, 77.2090], {
        radius: 10,
        fillColor: '#dc2626',
        color: '#ffffff',
        weight: 3,
        opacity: 1,
        fillOpacity: 1
    }).addTo(citizenMap).bindPopup(`
        <div style="font-family: sans-serif; font-size: 12px;">
            <strong style="color: #dc2626;">📍 YOUR LOCATION</strong><br>
            <span>Coordinates: 28.6139, 77.2090</span>
        </div>
    `);

    // Plot Shelters & Hospitals
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
                        <?= $fac['type'] === 'Hospital' ? '🏥' : '🏠' ?> ${<?= json_encode($fac['name']) ?>}
                    </strong><br>
                    <strong>Type:</strong> ${<?= json_encode($fac['type']) ?>}<br>
                    <strong>Address:</strong> ${<?= json_encode($fac['address'] ?? 'Central Disaster Perimeter') ?>}<br>
                    <strong>Available Beds:</strong> ${<?= (int)$fac['available_capacity'] ?>} / ${<?= (int)$fac['total_capacity'] ?>}<br>
                    <a href="https://www.google.com/maps/dir/?api=1&destination=<?= (float)$fac['latitude'] ?>,<?= (float)$fac['longitude'] ?>" target="_blank" style="display:inline-block; margin-top:5px; color: #2563eb; font-weight: bold;">Get Directions &rarr;</a>
                </div>
            `);
        <?php endif; ?>
    <?php endforeach; ?>
}

document.addEventListener('DOMContentLoaded', function() {
    initCitizenMap();
});

// Auto GPS Detection
function acquireCitizenGps() {
    if (navigator.geolocation) {
        document.getElementById('citizenGpsLabel').innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-1 text-red-600"></i> Acquiring GPS Satellites...`;
        navigator.geolocation.getCurrentPosition(function(pos) {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            document.getElementById('citizenGpsCoords').innerText = `${lat.toFixed(4)}° N, ${lng.toFixed(4)}° E (GPS Locked ±${Math.round(pos.coords.accuracy)}m)`;
            document.getElementById('form_latitude').value = lat;
            document.getElementById('form_longitude').value = lng;
            document.getElementById('citizenGpsLabel').innerHTML = `<i class="fa-solid fa-check text-emerald-600 mr-1"></i> GPS Locked`;

            if (citizenMap) {
                citizenMap.setView([lat, lng], 14);
            }
        }, function(err) {
            document.getElementById('citizenGpsLabel').innerHTML = `<i class="fa-solid fa-circle-exclamation text-amber-600 mr-1"></i> GPS Error`;
            alert('Location permission denied or unavailable. Using default base coordinates.');
        });
    }
}

// 1-Touch Big Red Panic Trigger
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

// Responder Chat Modal
function openResponderChatModal(sosId) {
    document.getElementById('modal_chat_sos_id').value = sosId;
    document.getElementById('responderChatModal').classList.remove('hidden');

    const box = document.getElementById('responderChatMessagesBox');
    box.innerHTML = `<p class="text-xs text-slate-400 text-center py-6">Connecting to emergency lifeline...</p>`;

    fetch(`api/victim_volunteer_chat_history.php?sos_id=${sosId}`)
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success' && data.messages && data.messages.length > 0) {
                box.innerHTML = data.messages.map(m => `
                    <div class="flex flex-col ${m.sender_role === 'victim' ? 'items-end' : 'items-start'}">
                        <div class="max-w-[80%] p-2.5 rounded-2xl text-xs ${m.sender_role === 'victim' ? 'bg-red-600 text-white rounded-br-none' : 'bg-white border border-slate-200 text-slate-900 rounded-bl-none shadow-2xs'}">
                            <span class="block text-[10px] font-mono font-bold ${m.sender_role === 'victim' ? 'text-red-200' : 'text-slate-400'}">${m.sender_name} (${m.sender_role})</span>
                            <p class="font-medium mt-0.5">${m.message}</p>
                        </div>
                    </div>
                `).join('');
                box.scrollTop = box.scrollHeight;
            } else {
                box.innerHTML = `<p class="text-xs text-slate-400 text-center py-6">No previous messages. Type a direct note to your responders below.</p>`;
            }
        })
        .catch(() => {
            box.innerHTML = `<p class="text-xs text-slate-400 text-center py-6">Lifeline channel ready. Send a message below.</p>`;
        });
}

function closeResponderChatModal() {
    document.getElementById('responderChatModal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
