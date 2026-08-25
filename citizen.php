<?php
// citizen.php - DisasterSafe Citizen Emergency SOS & Crisis Portal (Converted from React to PHP)
define('PAGE_TITLE', 'Citizen Emergency & Safety Portal');
require_once __DIR__ . '/auth.php';

$currentUser = getCurrentUser($pdo);
$userName = $currentUser['name'] ?? 'Citizen';
$userPhone = $currentUser['phone'] ?? '+91 98765 43210';
$userEmail = $currentUser['email'] ?? '';

// Handle Direct POST SOS Submission
$feedback = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

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
            setFlash('success', "🚨 Emergency distress signal transmitted (SOS #{$sosId})! Auto-assigned to {$dispatchAgency}. Help is en-route.");
        } catch (Exception $e) {
            setFlash('error', "Failed to broadcast distress signal: " . $e->getMessage());
        }

        header("Location: citizen.php");
        exit;
    }

    if ($action === 'resolve_sos') {
        $sosId = (int)($_POST['sos_id'] ?? 0);
        if ($sosId > 0) {
            $pdo->prepare("UPDATE emergency_sos SET status = 'Resolved' WHERE id = ?")->execute([$sosId]);
            logActivity($pdo, 'SOS_RESOLVED_CITIZEN', "SOS #{$sosId} marked resolved by citizen");
            setFlash('success', "SOS #{$sosId} marked as resolved. Glad you are safe!");
        }
        header("Location: citizen.php");
        exit;
    }
}

// Fetch active SOS for the logged-in user
$stmt = $pdo->prepare("SELECT * FROM emergency_sos WHERE sender_phone = ? OR sender_name = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$userPhone, $userName]);
$activeSos = $stmt->fetch();

// Fetch reports history
$userReports = [];
if (!empty($userPhone) || !empty($userName)) {
    $stmt = $pdo->prepare("SELECT * FROM emergency_sos WHERE sender_phone = ? OR sender_name = ? ORDER BY id DESC LIMIT 15");
    $stmt->execute([$userPhone, $userName]);
    $userReports = $stmt->fetchAll();
}
if (empty($userReports)) {
    $userReports = $pdo->query("SELECT * FROM emergency_sos ORDER BY id DESC LIMIT 6")->fetchAll();
}

// Stats for dashboard banner
$statsTotal = $pdo->query("SELECT COUNT(*) FROM emergency_sos")->fetchColumn();
$statsActive = $pdo->query("SELECT COUNT(*) FROM emergency_sos WHERE status != 'Resolved'")->fetchColumn();
$statsShelters = $pdo->query("SELECT COUNT(*) FROM facilities WHERE type = 'Relief Shelter'")->fetchColumn() ?: 4;
$statsMedics = $pdo->query("SELECT COUNT(*) FROM facilities WHERE type = 'Hospital'")->fetchColumn() ?: 6;

// Facilities for map
$facilities = $pdo->query("SELECT * FROM facilities WHERE status != 'Closed'")->fetchAll();

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-[#fbf9f5] text-[#1c1917]" data-role="citizen">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citizen Emergency Portal | DisasterSafe</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        cream: {
                            50: '#fbf9f5',
                            100: '#f4f0ea',
                            200: '#eee7db',
                            300: '#d8d0c5',
                            400: '#b8ad9e'
                        },
                        navy: {
                            950: '#000a1e',
                            900: '#0a0f1d',
                            800: '#11192e',
                            700: '#1c2b4e'
                        },
                        crimson: {
                            500: '#dc2626',
                            600: '#c53030',
                            700: '#a82525'
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace']
                    }
                }
            }
        }
    </script>
    <style>
        *, *::before, *::after {
            border-radius: 0 !important;
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f4f0ea;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #d8d0c5;
            border-radius: 4px;
        }
        @keyframes beaconPulse {
            0% { transform: scale(0.97); box-shadow: 0 0 0 0 rgba(197, 48, 48, 0.7); }
            70% { transform: scale(1.03); box-shadow: 0 0 0 18px rgba(197, 48, 48, 0); }
            100% { transform: scale(0.97); box-shadow: 0 0 0 0 rgba(197, 48, 48, 0); }
        }
        .animate-beacon {
            animation: beaconPulse 2s infinite cubic-bezier(0.4, 0, 0.6, 1);
        }
        .pulse-ring {
            box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.5);
            animation: pulse-ring 2s infinite;
        }
        @keyframes pulse-ring {
            0% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.6); }
            70% { box-shadow: 0 0 0 14px rgba(220, 38, 38, 0); }
            100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0); }
        }
    </style>
</head>
<body class="min-h-screen bg-[#fbf9f5] text-[#1c1917] font-sans antialiased flex flex-col">

    <!-- Top Navigation Header (Yukta's Citizen Layout) -->
    <header class="sticky top-0 z-50 bg-[#000a1e] border-b border-[#1c2b4e] text-white shadow-lg backdrop-blur-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                
                <!-- Left Brand -->
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-red-600 to-amber-500 flex items-center justify-center font-black text-white text-xl shadow-md shadow-red-900/50">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-lg font-extrabold text-white tracking-tight">DisasterSafe</span>
                            <span class="px-2 py-0.5 rounded-full bg-red-500/20 text-red-400 border border-red-500/30 text-[10px] font-bold uppercase tracking-wider">Citizen Portal</span>
                        </div>
                        <p class="text-[11px] text-slate-400 font-medium hidden sm:block">Real-Time Emergency SOS & Crisis Protection</p>
                    </div>
                </div>

                <!-- Center Nav Links -->
                <nav class="hidden md:flex items-center gap-1 bg-[#11192e] p-1 rounded-xl border border-[#243049]">
                    <a href="citizen.php" class="px-3.5 py-1.5 rounded-lg bg-red-600 text-white font-bold text-xs flex items-center gap-2 shadow-sm transition-all">
                        <i class="fa-solid fa-tower-broadcast text-xs"></i>
                        <span>Emergency SOS</span>
                    </a>
                    <a href="citizen_guides.php" class="px-3.5 py-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 font-semibold text-xs flex items-center gap-2 transition-all">
                        <i class="fa-solid fa-book-medical text-xs text-amber-400"></i>
                        <span>Safety Guides</span>
                    </a>
                    <a href="citizen_contacts.php" class="px-3.5 py-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 font-semibold text-xs flex items-center gap-2 transition-all">
                        <i class="fa-solid fa-phone-volume text-xs text-teal-400"></i>
                        <span>Emergency Directory</span>
                    </a>
                </nav>

                <!-- Right Actions -->
                <div class="flex items-center gap-3">
                    <a href="tel:112" class="hidden sm:inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-red-600/20 border border-red-500/40 text-red-300 hover:bg-red-600/30 text-xs font-bold transition-all">
                        <i class="fa-solid fa-phone text-xs animate-bounce text-red-400"></i>
                        <span>Helpline: <strong>112</strong></span>
                    </a>

                    <div class="flex items-center gap-2.5 pl-2 border-l border-[#243049]">
                        <div class="w-8 h-8 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-xs font-bold text-slate-200">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <div class="hidden lg:block text-left">
                            <span class="block text-xs font-bold text-slate-200"><?= htmlspecialchars($userName) ?></span>
                            <span class="block text-[10px] text-slate-400 font-mono">Public Citizen</span>
                        </div>
                        <a href="logout.php" title="Sign Out of DisasterSafe" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-600/20 hover:bg-red-600/30 border border-red-500/40 text-red-300 text-xs font-bold transition-all shadow-xs">
                            <i class="fa-solid fa-arrow-right-from-bracket text-red-400"></i>
                            <span class="hidden sm:inline">Logout</span>
                        </a>
                    </div>
                </div>

            </div>
        </div>

        <!-- Mobile Navigation Sub-bar -->
        <div class="md:hidden flex items-center justify-around border-t border-[#1c2b4e] bg-[#0a0f1d] px-2 py-2 text-xs">
            <a href="citizen.php" class="px-3 py-1 rounded-lg bg-red-600/20 text-red-400 font-bold flex items-center gap-1.5">
                <i class="fa-solid fa-tower-broadcast"></i> SOS Beacon
            </a>
            <a href="citizen_guides.php" class="px-3 py-1 rounded-lg text-slate-400 font-semibold flex items-center gap-1.5">
                <i class="fa-solid fa-book-medical text-amber-400"></i> Guides
            </a>
            <a href="citizen_contacts.php" class="px-3 py-1 rounded-lg text-slate-400 font-semibold flex items-center gap-1.5">
                <i class="fa-solid fa-phone-volume text-teal-400"></i> Helplines
            </a>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 lg:p-8 space-y-6">
        
        <!-- Flash Alert Messages -->
        <?php if ($flash): ?>
            <div class="p-4 rounded-2xl <?= $flash['type'] === 'success' ? 'bg-emerald-50 border border-emerald-300 text-emerald-900' : 'bg-rose-50 border border-rose-300 text-rose-900' ?> flex items-start gap-3 shadow-sm">
                <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-triangle-exclamation text-rose-600' ?> text-lg mt-0.5 shrink-0"></i>
                <div class="text-sm font-semibold leading-relaxed"><?= htmlspecialchars($flash['message']) ?></div>
            </div>
        <?php endif; ?>

        <!-- Key Metrics Banner -->
        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3.5 sm:gap-4">
            <div class="p-4 rounded-2xl bg-white border border-[#d8d0c5] shadow-xs flex items-center gap-3.5">
                <div class="w-12 h-12 rounded-xl bg-red-100 border border-red-200 text-red-600 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-bell"></i>
                </div>
                <div>
                    <div class="text-2xl font-black text-[#000a1e] font-mono leading-none"><?= (int)$statsTotal ?></div>
                    <span class="text-xs font-bold text-[#78716c] uppercase tracking-wider">Distress Signals</span>
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-white border border-[#d8d0c5] shadow-xs flex items-center gap-3.5">
                <div class="w-12 h-12 rounded-xl bg-amber-100 border border-amber-200 text-amber-600 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-truck-fast"></i>
                </div>
                <div>
                    <div class="text-2xl font-black text-[#000a1e] font-mono leading-none"><?= (int)$statsActive ?></div>
                    <span class="text-xs font-bold text-[#78716c] uppercase tracking-wider">Active Operations</span>
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-white border border-[#d8d0c5] shadow-xs flex items-center gap-3.5">
                <div class="w-12 h-12 rounded-xl bg-emerald-100 border border-emerald-200 text-emerald-600 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-campground"></i>
                </div>
                <div>
                    <div class="text-2xl font-black text-[#000a1e] font-mono leading-none"><?= (int)$statsShelters ?></div>
                    <span class="text-xs font-bold text-[#78716c] uppercase tracking-wider">Safe Shelters Open</span>
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-white border border-[#d8d0c5] shadow-xs flex items-center gap-3.5">
                <div class="w-12 h-12 rounded-xl bg-blue-100 border border-blue-200 text-blue-600 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-user-doctor"></i>
                </div>
                <div>
                    <div class="text-2xl font-black text-[#000a1e] font-mono leading-none"><?= (int)$statsMedics ?></div>
                    <span class="text-xs font-bold text-[#78716c] uppercase tracking-wider">Emergency Hospitals</span>
                </div>
            </div>
        </section>

        <!-- 2-Column Responsive Layout -->
        <div class="grid lg:grid-cols-12 gap-6 items-start">
            
            <!-- Left Column (7/12): Live SOS Tracker, Interactive Map, and Quick Guides -->
            <div class="lg:col-span-7 space-y-6">
                
                <!-- Live SOS Tracker Card (Shows if active SOS exists) -->
                <?php if ($activeSos && $activeSos['status'] !== 'Resolved'): ?>
                    <section class="p-5 sm:p-6 rounded-3xl bg-gradient-to-br from-red-950 via-[#11192e] to-[#0a0f1d] border-2 border-red-500/60 shadow-xl text-white relative overflow-hidden">
                        <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-red-600/20 rounded-full blur-2xl pointer-events-none"></div>
                        
                        <div class="flex items-start justify-between gap-3 mb-4">
                            <div class="flex items-center gap-2.5">
                                <span class="w-3.5 h-3.5 rounded-full bg-red-500 animate-ping shrink-0"></span>
                                <h3 class="text-base sm:text-lg font-black text-white uppercase tracking-tight">Active Emergency SOS #<?= $activeSos['id'] ?></h3>
                            </div>
                            <span class="px-2.5 py-1 rounded-full bg-red-500/20 border border-red-500/40 text-red-300 font-mono text-[11px] font-bold uppercase">
                                <?= htmlspecialchars($activeSos['status'] ?? 'Pending') ?>
                            </span>
                        </div>

                        <!-- 4-Stage Progress Tracker -->
                        <div class="my-6 grid grid-cols-4 gap-2 relative">
                            <!-- Stage 1: Sent -->
                            <div class="text-center space-y-1.5">
                                <div class="w-8 h-8 mx-auto rounded-full bg-emerald-500 text-white flex items-center justify-center text-xs font-black shadow-md shadow-emerald-500/40">
                                    <i class="fa-solid fa-check"></i>
                                </div>
                                <span class="block text-[11px] font-bold text-slate-200">Sent</span>
                                <span class="block text-[9px] text-slate-400 font-mono">Signal Logged</span>
                            </div>

                            <!-- Stage 2: Triage -->
                            <div class="text-center space-y-1.5">
                                <div class="w-8 h-8 mx-auto rounded-full bg-emerald-500 text-white flex items-center justify-center text-xs font-black shadow-md shadow-emerald-500/40">
                                    <i class="fa-solid fa-check"></i>
                                </div>
                                <span class="block text-[11px] font-bold text-slate-200">Triaged</span>
                                <span class="block text-[9px] text-slate-400 font-mono"><?= htmlspecialchars($activeSos['emergency_type']) ?></span>
                            </div>

                            <!-- Stage 3: Dispatched / En Route -->
                            <div class="text-center space-y-1.5">
                                <div class="w-8 h-8 mx-auto rounded-full <?= in_array($activeSos['status'], ['Assigned', 'In Progress', 'En Route']) ? 'bg-amber-500 text-slate-950 animate-bounce' : 'bg-slate-700 text-slate-400' ?> flex items-center justify-center text-xs font-black">
                                    <i class="fa-solid fa-truck-medical"></i>
                                </div>
                                <span class="block text-[11px] font-bold text-slate-200">En Route</span>
                                <span class="block text-[9px] text-amber-400 font-mono font-bold">ETA ~<?= (int)($activeSos['eta_minutes'] ?? 8) ?>m</span>
                            </div>

                            <!-- Stage 4: Resolved -->
                            <div class="text-center space-y-1.5">
                                <div class="w-8 h-8 mx-auto rounded-full bg-slate-800 border border-slate-700 text-slate-500 flex items-center justify-center text-xs font-black">
                                    <i class="fa-solid fa-shield"></i>
                                </div>
                                <span class="block text-[11px] font-bold text-slate-400">Resolved</span>
                                <span class="block text-[9px] text-slate-500 font-mono">Safe</span>
                            </div>
                        </div>

                        <!-- Dispatch Info Box -->
                        <div class="p-3.5 rounded-xl bg-slate-900/80 border border-slate-700/80 text-xs space-y-2 mb-4">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400 font-semibold">Assigned Unit:</span>
                                <strong class="text-indigo-300 font-bold"><?= htmlspecialchars($activeSos['assigned_unit'] ?? $activeSos['dispatch_agency'] ?? 'NDRF Tactical Response Squad') ?></strong>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400 font-semibold">Triage Needs:</span>
                                <span class="text-slate-200 font-medium text-right"><?= htmlspecialchars($activeSos['medical_needs'] ?? 'Trauma Care & Evacuation') ?></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400 font-semibold">Location Coordinates:</span>
                                <span class="text-amber-300 font-mono"><?= number_format((float)$activeSos['gps_lat'], 5) ?>, <?= number_format((float)$activeSos['gps_lng'], 5) ?></span>
                            </div>
                        </div>

                        <form method="POST" action="citizen.php" class="flex items-center justify-between gap-3">
                            <input type="hidden" name="action" value="resolve_sos">
                            <input type="hidden" name="sos_id" value="<?= (int)$activeSos['id'] ?>">
                            <span class="text-[11px] text-slate-400">Responders are navigating to your location.</span>
                            <button type="submit" onclick="return confirm('Confirm that you are safe and wish to close this distress signal?')" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs transition-colors shadow-md shadow-emerald-950/40 cursor-pointer flex items-center gap-1.5">
                                <i class="fa-solid fa-check-double"></i>
                                <span>I am Safe (Close SOS)</span>
                            </button>
                        </form>
                    </section>
                <?php endif; ?>

                <!-- Live Responder & Volunteer Hotline Chat Hub -->
                <section id="citizenLiveHotlineCard" class="bg-white rounded-3xl border border-[#d8d0c5] p-5 sm:p-6 shadow-sm space-y-4 relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-full h-1.5 bg-gradient-to-r from-blue-600 via-amber-500 to-rose-500"></div>

                    <!-- Header -->
                    <div class="flex items-center justify-between pb-3 border-b border-[#eee7db] flex-wrap gap-2 pt-1">
                        <div class="flex items-center gap-2.5">
                            <div class="w-10 h-10 rounded-2xl bg-blue-50 border border-blue-200 text-[#1d63d8] flex items-center justify-center text-lg shadow-xs shrink-0">
                                <i class="fa-solid fa-comments"></i>
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="text-base sm:text-lg font-bold text-[#000a1e]">Citizen &amp; Volunteer Live Hotline</h3>
                                    <span class="px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-mono font-extrabold uppercase tracking-wider flex items-center gap-1">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                        <span id="citizenHotlineStatusBadge">Live Line</span>
                                    </span>
                                </div>
                                <p class="text-xs text-[#78716c]">Direct encrypted two-way comms with assigned field rescue team</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="simulateCitizenVolunteerReply()" title="Test volunteer reply" class="px-3 py-1.5 bg-amber-50 hover:bg-amber-100 border border-amber-300 text-amber-900 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer shadow-xs">
                                <i class="fa-solid fa-robot text-amber-600"></i>
                                <span>Test Reply</span>
                            </button>
                            <a id="citizenCallVolunteerBtn" href="tel:+919845011223" class="px-3.5 py-1.5 bg-[#d1fae5] hover:bg-[#a7f3d0] text-[#065f46] rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 shadow-xs">
                                <i class="fa-solid fa-phone text-[#059669]"></i>
                                <span>Call Team</span>
                            </a>
                        </div>
                    </div>

                    <!-- Active Responder HUD Box -->
                    <div class="p-3.5 bg-[#f8fafc] rounded-2xl border border-[#e2e8f0] flex items-center justify-between flex-wrap gap-2">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-[#1d63d8] text-white font-extrabold text-sm flex items-center justify-center shadow-sm shrink-0">
                                <span id="citizenResponderInitial">A</span>
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <strong id="citizenResponderName" class="text-sm font-bold text-[#000a1e]">Alexander Vance (Field Volunteer Lead)</strong>
                                    <span class="text-[10px] bg-blue-100 text-[#1e3a8a] font-bold px-1.5 py-0.5 rounded mono">UNIT #DL-04</span>
                                </div>
                                <span id="citizenResponderSubtitle" class="text-[11px] text-[#64748b] block font-medium">NDRF Tactical Rescue Squad • Lifeboat &amp; First Aid Ready</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span id="citizenResponderEtaBadge" class="text-[10px] bg-amber-100 text-amber-900 font-bold px-2 py-1 rounded-lg mono">
                                ETA ~4 mins
                            </span>
                            <span class="bg-blue-50 text-blue-900 border border-blue-200 text-[10px] font-bold px-2 py-1 rounded-lg mono uppercase">
                                EN ROUTE
                            </span>
                        </div>
                    </div>

                    <!-- Messages Stream -->
                    <div id="citizenChatFeed" class="flex flex-col gap-2.5 max-h-[220px] min-h-[140px] overflow-y-auto p-2.5 bg-[#fbf9f5] rounded-2xl border border-[#d8d0c5] custom-scrollbar text-xs">
                        <div class="text-xs text-gray-400 italic text-center py-4">Connecting direct line with responder team...</div>
                    </div>

                    <!-- Quick Preset Response Pills -->
                    <div class="flex gap-2 overflow-x-auto custom-scrollbar pt-1 pb-1">
                        <button type="button" onclick="sendCitizenMsg('🪟 We are waving a white cloth &amp; flashlight from the 2nd-floor balcony.')" class="text-xs font-bold bg-blue-50 hover:bg-blue-100 text-[#1d63d8] border border-blue-200 px-3 py-1.5 rounded-full whitespace-nowrap transition-colors cursor-pointer shrink-0">
                            🪟 Signal from Window
                        </button>
                        <button type="button" onclick="sendCitizenMsg('🌊 Water level is rising rapidly. Please expedite rescue!')" class="text-xs font-bold bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 px-3 py-1.5 rounded-full whitespace-nowrap transition-colors cursor-pointer shrink-0">
                            🌊 Water Rising
                        </button>
                        <button type="button" onclick="sendCitizenMsg('🩺 We have an elderly family member needing stretcher &amp; oxygen.')" class="text-xs font-bold bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 px-3 py-1.5 rounded-full whitespace-nowrap transition-colors cursor-pointer shrink-0">
                            🩺 Need Medical
                        </button>
                        <button type="button" onclick="sendCitizenMsg('👀 We can hear your sirens and see the rescue lights outside!')" class="text-xs font-bold bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 px-3 py-1.5 rounded-full whitespace-nowrap transition-colors cursor-pointer shrink-0">
                            👀 Seeing Vehicle
                        </button>
                    </div>

                    <!-- Send Form -->
                    <form id="citizenChatForm" onsubmit="handleSendCitizenMsg(event)" class="flex items-center gap-2 pt-1">
                        <input type="text" id="citizenChatInput" class="flex-1 bg-white border border-[#d8d0c5] rounded-full px-4 py-2.5 text-xs text-[#1c1917] focus:outline-none focus:border-[#1d63d8] focus:ring-1 focus:ring-[#1d63d8] font-medium" placeholder="Type direct message to rescue squad..." required />
                        <button type="submit" class="w-10 h-10 bg-[#1d63d8] hover:bg-[#1553c7] text-white rounded-full text-xs font-bold shadow-sm transition-colors flex items-center justify-center cursor-pointer shrink-0">
                            <i class="fa-solid fa-paper-plane text-xs"></i>
                        </button>
                    </form>
                </section>

                <!-- Interactive Citizen Map Card (Yukta's Citizen Map) -->
                <section class="bg-white rounded-3xl border border-[#d8d0c5] p-5 sm:p-6 shadow-xs space-y-4">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <div>
                            <h3 class="text-base sm:text-lg font-bold text-[#000a1e] flex items-center gap-2">
                                <i class="fa-solid fa-map-location-dot text-red-600"></i>
                                <span>Crisis Radar & Safe Shelter Map</span>
                            </h3>
                            <p class="text-xs text-[#78716c]">Find verified evacuation shelters, medical aid camps, and relief depots near you</p>
                        </div>
                        <button type="button" onclick="locateUserGPS()" class="px-3.5 py-1.5 rounded-xl bg-[#000a1e] hover:bg-[#11192e] text-white text-xs font-bold flex items-center gap-1.5 transition-all shadow-xs cursor-pointer">
                            <i class="fa-solid fa-crosshairs text-red-400"></i>
                            <span>Locate Me</span>
                        </button>
                    </div>

                    <!-- Map Container -->
                    <div id="citizenMap" class="w-full h-[380px] sm:h-[420px] rounded-2xl border border-[#d8d0c5] shadow-inner relative z-0"></div>

                    <!-- Map Legend -->
                    <div class="flex items-center justify-between flex-wrap gap-2 pt-2 text-[11px] font-bold text-[#586377] border-t border-[#eee7db]">
                        <div class="flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-full bg-blue-600 ring-2 ring-blue-300 inline-block"></span>
                            <span>Your Location</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-full bg-emerald-600 inline-block"></span>
                            <span>Safe Relief Shelters</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-full bg-rose-600 inline-block"></span>
                            <span>Hospitals / EMS</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-full bg-amber-500 inline-block"></span>
                            <span>Fire & Police Stations</span>
                        </div>
                    </div>
                </section>

                <!-- Quick Survival Guides Preview Cards -->
                <section class="bg-white rounded-3xl border border-[#d8d0c5] p-5 sm:p-6 shadow-xs space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-bold text-[#000a1e] flex items-center gap-2">
                            <i class="fa-solid fa-life-ring text-amber-500"></i>
                            <span>Emergency Survival Protocols</span>
                        </h3>
                        <a href="citizen_guides.php" class="text-xs font-bold text-red-600 hover:text-red-700 flex items-center gap-1">
                            <span>View All Guides</span>
                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>

                    <div class="grid sm:grid-cols-3 gap-3">
                        <a href="citizen_guides.php?guide=flood" class="p-3.5 rounded-2xl bg-blue-50/60 border border-blue-200 hover:border-blue-400 transition-all block group">
                            <div class="w-9 h-9 rounded-xl bg-blue-600 text-white flex items-center justify-center text-base mb-2 group-hover:scale-105 transition-transform">
                                <i class="fa-solid fa-water"></i>
                            </div>
                            <h4 class="text-xs font-bold text-[#000a1e] mb-1">Flash Flood</h4>
                            <p class="text-[11px] text-[#78716c] leading-snug">Rooftop signals, water safety, power shut-off.</p>
                        </a>

                        <a href="citizen_guides.php?guide=earthquake" class="p-3.5 rounded-2xl bg-amber-50/60 border border-amber-200 hover:border-amber-400 transition-all block group">
                            <div class="w-9 h-9 rounded-xl bg-amber-600 text-white flex items-center justify-center text-base mb-2 group-hover:scale-105 transition-transform">
                                <i class="fa-solid fa-house-crack"></i>
                            </div>
                            <h4 class="text-xs font-bold text-[#000a1e] mb-1">Earthquake</h4>
                            <p class="text-[11px] text-[#78716c] leading-snug">Drop-Cover-Hold, open ground evacuation.</p>
                        </a>

                        <a href="citizen_guides.php?guide=fire" class="p-3.5 rounded-2xl bg-rose-50/60 border border-rose-200 hover:border-rose-400 transition-all block group">
                            <div class="w-9 h-9 rounded-xl bg-rose-600 text-white flex items-center justify-center text-base mb-2 group-hover:scale-105 transition-transform">
                                <i class="fa-solid fa-fire"></i>
                            </div>
                            <h4 class="text-xs font-bold text-[#000a1e] mb-1">Fire & Smoke</h4>
                            <p class="text-[11px] text-[#78716c] leading-snug">Low crawl, wet cloth, exit routes.</p>
                        </a>
                    </div>
                </section>

            </div>

            <!-- Right Column (5/12): Rapid SOS Trigger, Detailed Form & Log -->
            <div class="lg:col-span-5 space-y-6">
                
                <!-- Main SOS Transmission Card (Yukta's Send Emergency Report) -->
                <div class="bg-white rounded-3xl border-2 border-red-200 p-5 sm:p-7 shadow-lg space-y-5">
                    
                    <div class="flex items-center justify-between pb-3 border-b border-[#eee7db]">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-red-600 text-white flex items-center justify-center text-lg shadow-md shadow-red-600/30">
                                <i class="fa-solid fa-tower-broadcast animate-pulse"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-black text-[#000a1e]">Send Emergency SOS</h3>
                                <p class="text-xs text-[#78716c]">Transmits instant GPS coordinates to emergency dispatch</p>
                            </div>
                        </div>
                    </div>

                    <!-- 1-Tap Big Rapid Red Beacon -->
                    <button type="button" onclick="triggerQuickSos()" id="rapidSosBtn" class="w-full py-4 px-4 rounded-2xl bg-gradient-to-r from-red-600 via-rose-600 to-red-700 hover:from-red-500 hover:to-rose-500 text-white font-black text-sm uppercase tracking-wider shadow-lg shadow-red-600/40 transition-all flex items-center justify-center gap-3 cursor-pointer group pulse-ring">
                        <i class="fa-solid fa-triangle-exclamation text-lg animate-bounce"></i>
                        <span>1-Tap Instant SOS Beacon</span>
                        <i class="fa-solid fa-bolt text-amber-300"></i>
                    </button>

                    <!-- Detailed Emergency Form -->
                    <form method="POST" action="citizen.php" id="sosForm" class="space-y-4">
                        <input type="hidden" name="action" value="broadcast_sos">
                        <input type="hidden" id="latitudeInput" name="latitude" value="28.6139">
                        <input type="hidden" id="longitudeInput" name="longitude" value="77.2090">

                        <!-- GPS Location Box -->
                        <div class="p-3.5 rounded-2xl bg-[#f4f0ea] border border-[#d8d0c5] space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-[#000a1e] flex items-center gap-1.5">
                                    <i class="fa-solid fa-location-crosshairs text-red-600"></i>
                                    <span>Live GPS Coordinates</span>
                                </span>
                                <button type="button" onclick="refreshGPS()" class="text-[11px] font-bold text-red-600 hover:text-red-700 flex items-center gap-1 cursor-pointer">
                                    <i class="fa-solid fa-arrows-rotate" id="gpsRefreshIcon"></i>
                                    <span>Refresh GPS</span>
                                </button>
                            </div>
                            <div class="flex items-center justify-between bg-white px-3 py-2 rounded-xl border border-[#d8d0c5] text-xs font-mono">
                                <span id="gpsCoordDisplay" class="font-bold text-[#1c1917]">Acquiring GPS location…</span>
                                <span id="gpsAccuracyBadge" class="px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 text-[10px] font-bold">Auto</span>
                            </div>
                        </div>

                        <!-- Name & Phone -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="sos_name" class="block text-xs font-bold text-[#78716c] uppercase mb-1">Your Full Name</label>
                                <input type="text" id="sos_name" name="sender_name" required value="<?= htmlspecialchars($userName) ?>"
                                       placeholder="e.g. Aarav Patel"
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-[#d8d0c5] bg-[#f4f0ea] text-sm text-[#1c1917] font-semibold focus:bg-white focus:outline-none focus:border-red-600 transition-colors">
                            </div>
                            <div>
                                <label for="sos_phone" class="block text-xs font-bold text-[#78716c] uppercase mb-1">Contact Phone</label>
                                <input type="tel" id="sos_phone" name="sender_phone" required value="<?= htmlspecialchars($userPhone) ?>"
                                       placeholder="+91 98765 43210"
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-[#d8d0c5] bg-[#f4f0ea] text-sm font-mono font-semibold text-[#1c1917] focus:bg-white focus:outline-none focus:border-red-600 transition-colors">
                            </div>
                        </div>

                        <!-- Emergency Type & Number of People -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="emergency_type" class="block text-xs font-bold text-[#78716c] uppercase mb-1">Emergency Type</label>
                                <select id="emergency_type" name="emergency_type" required
                                        class="w-full px-3.5 py-2.5 rounded-xl border border-[#d8d0c5] bg-[#f4f0ea] text-sm font-bold text-[#1c1917] focus:bg-white focus:outline-none focus:border-red-600 transition-colors cursor-pointer">
                                    <option value="Flood">🌊 Flash Flood / Water Inflow</option>
                                    <option value="Fire">🔥 Fire / Hazmat / Smoke</option>
                                    <option value="Earthquake">🏚️ Earthquake / Structural Hazard</option>
                                    <option value="Cyclone">🌀 Cyclone / Severe Storm</option>
                                    <option value="Building Collapse">🏢 Building / Wall Collapse</option>
                                    <option value="Medical Trauma">🚑 Critical Medical Emergency</option>
                                    <option value="General">🆘 Other Life-Threatening Crisis</option>
                                </select>
                            </div>
                            <div>
                                <label for="persons_count" class="block text-xs font-bold text-[#78716c] uppercase mb-1">People in Danger</label>
                                <select id="persons_count" name="persons_count"
                                        class="w-full px-3.5 py-2.5 rounded-xl border border-[#d8d0c5] bg-[#f4f0ea] text-sm font-bold text-[#1c1917] focus:bg-white focus:outline-none focus:border-red-600 transition-colors cursor-pointer">
                                    <option value="1">1 Person</option>
                                    <option value="2 - 4">2 - 4 Persons</option>
                                    <option value="5 - 10">5 - 10 Persons</option>
                                    <option value="10+">10+ Trapped Citizens</option>
                                </select>
                            </div>
                        </div>

                        <!-- Priority & Special Needs -->
                        <div>
                            <label for="priority_level" class="block text-xs font-bold text-[#78716c] uppercase mb-1">Urgency / Severity</label>
                            <div class="grid grid-cols-3 gap-2">
                                <label class="flex items-center justify-center p-2 rounded-xl border border-red-300 bg-red-50 text-red-900 font-bold text-xs cursor-pointer select-none">
                                    <input type="radio" name="priority" value="Critical" checked class="mr-1.5 accent-red-600">
                                    <span>CRITICAL</span>
                                </label>
                                <label class="flex items-center justify-center p-2 rounded-xl border border-amber-300 bg-amber-50 text-amber-900 font-bold text-xs cursor-pointer select-none">
                                    <input type="radio" name="priority" value="High" class="mr-1.5 accent-amber-600">
                                    <span>HIGH</span>
                                </label>
                                <label class="flex items-center justify-center p-2 rounded-xl border border-blue-300 bg-blue-50 text-blue-900 font-bold text-xs cursor-pointer select-none">
                                    <input type="radio" name="priority" value="Medium" class="mr-1.5 accent-blue-600">
                                    <span>MEDIUM</span>
                                </label>
                            </div>
                        </div>

                        <!-- Message / Specific Situation -->
                        <div>
                            <label for="sos_message" class="block text-xs font-bold text-[#78716c] uppercase mb-1">Situation Details & Landmarks</label>
                            <textarea id="sos_message" name="message" rows="3"
                                      placeholder="e.g. Trapped on 2nd floor, infant present, elderly patient needing oxygen support…"
                                      class="w-full px-3.5 py-2.5 rounded-xl border border-[#d8d0c5] bg-[#f4f0ea] text-sm text-[#1c1917] focus:bg-white focus:outline-none focus:border-red-600 transition-colors resize-none"></textarea>
                        </div>

                        <!-- Transmit Submit Button -->
                        <button type="submit" id="submitReportBtn" class="w-full py-3.5 px-4 rounded-2xl bg-[#000a1e] hover:bg-[#11192e] text-white font-bold text-sm shadow-md transition-all flex items-center justify-center gap-2 cursor-pointer">
                            <i class="fa-solid fa-paper-plane text-xs text-red-400"></i>
                            <span>Transmit Full Distress Report</span>
                        </button>
                    </form>

                </div>

                <!-- Submitted Reports Log (Yukta's Reports Sent) -->
                <section class="bg-white rounded-3xl border border-[#d8d0c5] p-5 shadow-xs space-y-3">
                    <div class="flex items-center justify-between pb-2 border-b border-[#eee7db]">
                        <h4 class="text-xs font-black text-[#000a1e] uppercase tracking-wider flex items-center gap-2">
                            <i class="fa-solid fa-clock-rotate-left text-slate-500"></i>
                            <span>My Emergency SOS Log</span>
                        </h4>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#f4f0ea] text-[#78716c] font-mono">
                            <?= count($userReports) ?> Recorded
                        </span>
                    </div>

                    <?php if (empty($userReports)): ?>
                        <p class="text-xs text-[#78716c] bg-[#fbf9f5] border border-[#d8d0c5] rounded-xl p-3.5 text-center">
                            No active SOS signals transmitted yet. Submitted distress beacons will appear here.
                        </p>
                    <?php else: ?>
                        <div class="space-y-2.5 max-h-72 overflow-y-auto pr-1 custom-scrollbar">
                            <?php foreach ($userReports as $rep): ?>
                                <?php
                                $isResolved = ($rep['status'] === 'Resolved');
                                $isPending = ($rep['status'] === 'Pending');
                                $badgeColor = $isResolved ? 'bg-emerald-100 text-emerald-900 border-emerald-300' : ($isPending ? 'bg-red-100 text-red-900 border-red-300' : 'bg-amber-100 text-amber-900 border-amber-300');
                                ?>
                                <div class="p-3 rounded-2xl bg-[#fbf9f5] border border-[#d8d0c5] hover:border-slate-400 transition-colors space-y-1.5">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="text-xs font-extrabold text-[#000a1e] truncate"><?= htmlspecialchars($rep['emergency_type']) ?></span>
                                            <span class="text-[10px] text-slate-500 font-mono">#<?= (int)$rep['id'] ?></span>
                                        </div>
                                        <span class="px-2 py-0.5 rounded-md border text-[10px] font-bold uppercase tracking-wider <?= $badgeColor ?> <?= $isPending ? 'animate-pulse' : '' ?>">
                                            <?= htmlspecialchars($rep['status']) ?>
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between text-[11px] text-[#586377]">
                                        <span class="font-mono truncate">GPS: <?= number_format((float)$rep['gps_lat'], 4) ?>, <?= number_format((float)$rep['gps_lng'], 4) ?></span>
                                        <span class="text-[10px] font-medium text-slate-400"><?= htmlspecialchars(date('M d, H:i', strtotime($rep['created_at'] ?? 'now'))) ?></span>
                                    </div>
                                    <?php if (!empty($rep['message'])): ?>
                                        <p class="text-[11px] text-[#1c1917] bg-white p-2 rounded-lg border border-[#eee7db] line-clamp-1 italic">
                                            "<?= htmlspecialchars($rep['message']) ?>"
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

            </div>

        </div>

    </main>

    <!-- Footer -->
    <footer class="mt-auto bg-[#000a1e] border-t border-[#1c2b4e] py-4 text-center text-xs text-slate-400">
        <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-2">
            <span>&copy; 2026 DisasterSafe Crisis Management Suite • GeoGuardians</span>
            <div class="flex items-center gap-4 text-slate-300">
                <a href="citizen_guides.php" class="hover:text-white">Safety Guides</a>
                <span>•</span>
                <a href="citizen_contacts.php" class="hover:text-white">Helplines</a>
                <span>•</span>
                <a href="login.php" class="hover:text-white">Authority Login</a>
            </div>
        </div>
    <!-- ======================================================== -->
    <!-- CITIZEN AI CRISIS & SAFETY CHATBOT (HANDCRAFTED SUITE)   -->
    <!-- ======================================================== -->
    
    <!-- Floating Trigger FAB Button -->
    <div id="aiChatFabContainer" class="fixed bottom-6 right-6 z-[9999]">
        <button type="button" onclick="toggleAiChat()" id="aiChatToggleBtn" class="group flex items-center gap-3 px-4 py-3 rounded-full bg-[#000a1e] hover:bg-[#0f172a] text-white border border-slate-700 shadow-[0_10px_35px_rgba(0,0,0,0.35)] hover:shadow-[0_15px_40px_rgba(15,23,42,0.6)] transition-all duration-300 transform hover:-translate-y-0.5 cursor-pointer">
            <div class="relative flex items-center justify-center">
                <div class="w-8 h-8 rounded-full bg-indigo-600/30 border border-indigo-400/40 text-indigo-300 flex items-center justify-center text-sm">
                    <i class="fa-solid fa-sparkles text-indigo-400"></i>
                </div>
                <span class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 bg-emerald-400 rounded-full ring-2 ring-[#000a1e]"></span>
            </div>
            <div class="text-left pr-1">
                <span class="block text-xs font-bold tracking-tight text-white leading-tight">AI Safety Advisor</span>
                <span class="block text-[10px] text-slate-400 font-medium">Instant Emergency Help</span>
            </div>
        </button>
    </div>

    <!-- AI Chatbot Modal Box -->
    <div id="aiChatWidget" class="fixed bottom-6 right-6 z-[10000] w-[95vw] sm:w-[440px] h-[630px] max-h-[90vh] bg-white rounded-2xl border border-slate-200/90 shadow-[0_25px_60px_-15px_rgba(0,10,30,0.35)] flex flex-col overflow-hidden hidden transition-all duration-300 ease-out transform scale-95 opacity-0">
        
        <!-- Chat Header (Clean Slate Navy Theme) -->
        <div class="px-5 py-4 bg-[#000a1e] text-white flex items-center justify-between border-b border-slate-800 shrink-0">
            <div class="flex items-center gap-3">
                <div class="relative">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-blue-500 text-white flex items-center justify-center font-bold text-base shadow-sm">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-emerald-400 rounded-full ring-2 ring-[#000a1e]"></span>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h4 class="text-sm font-bold text-white tracking-tight">DisasterSafe AI Guide</h4>
                        <span class="px-1.5 py-0.5 rounded-md bg-indigo-950 text-indigo-300 border border-indigo-800 text-[9px] font-mono font-semibold">Gemini</span>
                    </div>
                    <p class="text-[11px] text-slate-400 flex items-center gap-1.5 mt-0.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        <span>Live GPS &amp; Shelter Radar Connected</span>
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-1.5">
                <button type="button" onclick="toggleChatHistoryDrawer()" title="View Chat History" class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-slate-300 hover:text-white bg-slate-800/80 hover:bg-slate-700 text-xs transition cursor-pointer font-medium border border-slate-700/60">
                    <i class="fa-solid fa-clock-rotate-left text-indigo-400 text-xs"></i>
                    <span class="text-[11px]">History</span>
                </button>
                <button type="button" onclick="clearAiChat()" title="New Chat / Reset" class="w-8 h-8 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition-colors flex items-center justify-center text-xs cursor-pointer">
                    <i class="fa-solid fa-arrows-rotate"></i>
                </button>
                <button type="button" onclick="toggleAiChat()" title="Close" class="w-8 h-8 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition-colors flex items-center justify-center text-sm cursor-pointer">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>

        <!-- Real-time Context Banner -->
        <div class="px-4 py-2 bg-[#0f172a] border-b border-slate-800 flex items-center justify-between text-[11px] text-slate-400 shrink-0 font-mono">
            <div class="flex items-center gap-2 truncate">
                <span class="text-slate-300 font-semibold truncate"><i class="fa-solid fa-user text-[10px] text-slate-500 mr-1"></i><?= htmlspecialchars($userName) ?></span>
                <span class="text-slate-600">•</span>
                <span class="<?= $activeSos ? 'text-amber-400 font-bold' : 'text-emerald-400 font-semibold' ?>">
                    <?= $activeSos ? 'SOS #' . $activeSos['id'] . ' (' . htmlspecialchars($activeSos['status']) . ')' : 'Standby / Safe' ?>
                </span>
            </div>
            <span class="text-[10px] text-indigo-400 bg-indigo-950/80 border border-indigo-800/80 px-2 py-0.5 rounded font-bold">LIVE RADAR</span>
        </div>

        <!-- Relative Container for Chat Feed & History Drawer Overlay -->
        <div class="flex-1 relative overflow-hidden flex flex-col">
            <!-- Chat Messages Feed -->
            <div id="aiChatFeed" class="flex-1 overflow-y-auto p-4 space-y-4 bg-[#f8fafc] custom-scrollbar text-xs">
                <!-- Messages stream -->
            </div>

            <!-- Chat History Drawer Overlay -->
            <div id="aiHistoryDrawer" class="absolute inset-0 bg-white z-30 flex flex-col transform translate-x-full transition-transform duration-300 ease-in-out">
                <div class="p-3.5 bg-slate-900 text-white flex items-center justify-between border-b border-slate-800 shrink-0">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-clock-rotate-left text-indigo-400 text-xs"></i>
                        <span class="font-bold text-xs tracking-tight">Saved Chat History</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="clearAllChatHistory()" class="text-[11px] text-red-400 hover:text-red-300 bg-red-950/60 border border-red-800/80 px-2 py-0.5 rounded transition cursor-pointer font-semibold">
                            <i class="fa-solid fa-trash-can text-[10px] mr-1"></i> Clear All
                        </button>
                        <button type="button" onclick="toggleChatHistoryDrawer(false)" class="w-7 h-7 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition flex items-center justify-center text-xs cursor-pointer">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </div>
                <div id="aiHistoryList" class="flex-1 overflow-y-auto p-3 space-y-2 bg-[#f8fafc] custom-scrollbar text-xs">
                    <!-- History items rendered here -->
                </div>
            </div>
        </div>

        <!-- Suggested Prompt Chips -->
        <div class="px-3.5 py-2.5 bg-white border-t border-slate-200/80 flex gap-2 overflow-x-auto custom-scrollbar shrink-0">
            <button type="button" onclick="askQuickPrompt('Where is my nearest safe shelter and how do I reach it?')" class="text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200/90 px-3 py-1.5 rounded-xl whitespace-nowrap transition-colors cursor-pointer shrink-0 flex items-center gap-1.5">
                <i class="fa-solid fa-campground text-emerald-600"></i> Nearest Shelter
            </button>
            <button type="button" onclick="askQuickPrompt('What is the current status of my emergency SOS signal?')" class="text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200/90 px-3 py-1.5 rounded-xl whitespace-nowrap transition-colors cursor-pointer shrink-0 flex items-center gap-1.5">
                <i class="fa-solid fa-tower-broadcast text-red-600"></i> SOS Status
            </button>
            <button type="button" onclick="askQuickPrompt('What are the immediate survival steps for rising flood water?')" class="text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200/90 px-3 py-1.5 rounded-xl whitespace-nowrap transition-colors cursor-pointer shrink-0 flex items-center gap-1.5">
                <i class="fa-solid fa-water text-blue-600"></i> Flood Steps
            </button>
            <button type="button" onclick="askQuickPrompt('Give me immediate first aid instructions for burn or trauma injuries.')" class="text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200/90 px-3 py-1.5 rounded-xl whitespace-nowrap transition-colors cursor-pointer shrink-0 flex items-center gap-1.5">
                <i class="fa-solid fa-kit-medical text-amber-600"></i> First Aid
            </button>
            <button type="button" onclick="askQuickPrompt('List all emergency helpline contact numbers for emergency response.')" class="text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200/90 px-3 py-1.5 rounded-xl whitespace-nowrap transition-colors cursor-pointer shrink-0 flex items-center gap-1.5">
                <i class="fa-solid fa-phone text-indigo-600"></i> Helplines
            </button>
        </div>

        <!-- Chat Input Form -->
        <form id="aiChatForm" onsubmit="handleSendAiMessage(event)" class="p-3 bg-white border-t border-slate-200 flex items-center gap-2 shrink-0">
            <input type="text" id="aiChatInput" class="flex-1 bg-slate-50 hover:bg-slate-100/70 focus:bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:border-indigo-600 focus:ring-2 focus:ring-indigo-100 font-medium transition-all" placeholder="Ask for safety advice, nearest hospital, first-aid..." required />
            <button type="submit" id="aiSendBtn" class="w-10 h-10 bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white rounded-xl text-xs font-bold shadow-sm transition-all flex items-center justify-center cursor-pointer shrink-0">
                <i class="fa-solid fa-arrow-up text-xs"></i>
            </button>
        </form>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        let map;
        let userMarker;
        let userCircle;
        let currentLat = 28.6139;
        let currentLng = 77.2090;

        // Initialize Map
        function initCitizenMap() {
            map = L.map('citizenMap').setView([currentLat, currentLng], 12);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(map);

            // Add Facilities Markers
            const facilities = <?= json_encode($facilities) ?>;
            facilities.forEach(f => {
                const lat = parseFloat(f.latitude);
                const lng = parseFloat(f.longitude);
                if (lat && lng) {
                    let markerColor = '#10b981'; // Green for shelters
                    let iconClass = 'fa-campground';
                    if (f.type === 'Hospital') {
                        markerColor = '#00FF00';
                        iconClass = 'fa-hospital';
                    } else if (f.type === 'Fire Station') {
                        markerColor = '#f59e0b';
                        iconClass = 'fa-fire-extinguisher';
                    } else if (f.type === 'Police Station') {
                        markerColor = '#3b82f6';
                        iconClass = 'fa-shield-halved';
                    }

                    const customIcon = L.divIcon({
                        className: 'custom-facility-pin',
                        html: `<div style="background-color:${markerColor}; width:32px; height:32px; border-radius:0; display:flex; align-items:center; justify-content:center; color:white; border:2px solid white; box-shadow:0 3px 8px rgba(0,0,0,0.3);"><i class="fa-solid ${iconClass}" style="font-size:13px;"></i></div>`,
                        iconSize: [32, 32],
                        iconAnchor: [16, 16]
                    });

                    const marker = L.marker([lat, lng], { icon: customIcon }).addTo(map);
                    marker.bindPopup(`
                        <div style="font-family:Inter,sans-serif; min-width:180px;">
                            <span style="font-size:10px; font-weight:bold; color:#78716c; text-transform:uppercase;">${escapeHtml(f.type)}</span>
                            <h4 style="font-size:13px; font-weight:bold; color:#000a1e; margin:2px 0 4px 0;">${escapeHtml(f.name)}</h4>
                            <p style="font-size:11px; color:#586377; margin:0 0 6px 0;">Status: <strong style="color:#16a34a;">${escapeHtml(f.status || 'Operational')}</strong></p>
                            ${f.contact ? `<a href="tel:${f.contact}" style="font-size:11px; color:#c53030; font-weight:bold; text-decoration:none;">📞 Call: ${escapeHtml(f.contact)}</a>` : ''}
                        </div>
                    `);
                }
            });

            // Start Geolocation
            locateUserGPS();
        }

        // Get Live Geolocation
        function locateUserGPS() {
            if ("geolocation" in navigator) {
                const refreshIcon = document.getElementById('gpsRefreshIcon');
                if (refreshIcon) refreshIcon.classList.add('fa-spin');

                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        currentLat = position.coords.latitude;
                        currentLng = position.coords.longitude;
                        const accuracy = Math.round(position.coords.accuracy);

                        // Update Hidden Form inputs
                        document.getElementById('latitudeInput').value = currentLat;
                        document.getElementById('longitudeInput').value = currentLng;

                        // Update text displays
                        document.getElementById('gpsCoordDisplay').textContent = `${currentLat.toFixed(5)}, ${currentLng.toFixed(5)}`;
                        document.getElementById('gpsAccuracyBadge').textContent = `±${accuracy}m GPS`;

                        // Update or Create Map Marker
                        if (userMarker) {
                            userMarker.setLatLng([currentLat, currentLng]);
                        } else {
                            const userIcon = L.divIcon({
                                className: 'custom-user-pin',
                                html: `<div style="background-color:#2563eb; width:28px; height:28px; border-radius:0; display:flex; align-items:center; justify-content:center; color:white; border:3px solid white; box-shadow:0 0 0 6px rgba(37,99,235,0.4);"><i class="fa-solid fa-user" style="font-size:11px;"></i></div>`,
                                iconSize: [28, 28],
                                iconAnchor: [14, 14]
                            });
                            userMarker = L.marker([currentLat, currentLng], { icon: userIcon }).addTo(map);
                            userMarker.bindPopup("<strong>You are here</strong><br>GPS Acquired");
                        }

                        if (userCircle) {
                            userCircle.setLatLng([currentLat, currentLng]).setRadius(accuracy);
                        } else {
                            userCircle = L.circle([currentLat, currentLng], {
                                radius: accuracy,
                                color: '#2563eb',
                                fillOpacity: 0.12,
                                weight: 1.5
                            }).addTo(map);
                        }

                        map.setView([currentLat, currentLng], 14);
                        if (refreshIcon) refreshIcon.classList.remove('fa-spin');
                    },
                    (error) => {
                        console.warn("GPS lookup fallback:", error.message);
                        document.getElementById('gpsCoordDisplay').textContent = `${currentLat.toFixed(5)}, ${currentLng.toFixed(5)} (Default)`;
                        document.getElementById('gpsAccuracyBadge').textContent = `Fallback`;
                        if (refreshIcon) refreshIcon.classList.remove('fa-spin');
                    },
                    { enableHighAccuracy: true, timeout: 8000, maximumAge: 0 }
                );
            }
        }

        function refreshGPS() {
            locateUserGPS();
        }

        // Quick Rapid SOS Beacon Click
        function triggerQuickSos() {
            if (confirm("🚨 TRANSMIT EMERGENCY BEACON IMMEDIATELY?\n\nThis will send your current GPS coordinates to Emergency Services and First Responders.")) {
                document.getElementById('sosForm').submit();
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            return text.replace(/[&<>"']/g, function(m) {
                return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[m];
            });
        }

        // ============================================================
        // CITIZEN & VOLUNTEER LIVE HOTLINE CHAT
        // ============================================================
        let citizenSosId = <?= (int)($activeSos['id'] ?? 17) ?>;
        let citizenLocalMsgs = [
            {
                id: 991,
                sos_id: 17,
                sender_id: 3,
                sender_name: 'Alexander Vance',
                sender_role: 'volunteer',
                message: 'Volunteer Field Volunteer Alexander Vance filed this SOS on-scene. Tactical backup & triage requested from volunteer.',
                created_at: new Date().toISOString()
            }
        ];

        async function loadCitizenChat() {
            try {
                const res = await fetch(`api/victim_volunteer_chat_fetch.php?sos_id=${citizenSosId}`, { credentials: 'include' });
                const data = await res.json();
                const feed = document.getElementById('citizenChatFeed');
                if (!feed) return;

                let msgs = (data && data.data && data.data.messages) || [];

                if (msgs.length === 0 && citizenLocalMsgs.length === 0) {
                    msgs = [
                        {
                            id: 991,
                            sender_id: 3,
                            sender_name: 'Alexander Vance',
                            sender_role: 'volunteer',
                            message: 'Volunteer Field Volunteer Alexander Vance filed this SOS on-scene. Tactical backup & triage requested from volunteer.',
                            created_at: new Date().toISOString()
                        }
                    ];
                }

                // Merge local
                const ids = new Set(msgs.map(m => m.id));
                citizenLocalMsgs.forEach(lm => {
                    if (!ids.has(lm.id)) msgs.push(lm);
                });

                const wasAtBottom = feed.scrollHeight - feed.scrollTop - feed.clientHeight < 40;
                feed.innerHTML = '';

                msgs.forEach(m => {
                    const isMe = m.sender_role === 'victim' || parseInt(m.sender_id) === <?= (int)($currentUser['id'] ?? 0) ?>;
                    const timeStr = new Date(m.created_at || Date.now()).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    const bubble = document.createElement('div');

                    if (isMe) {
                        bubble.className = 'flex flex-col items-end self-end max-w-[90%]';
                        bubble.innerHTML = `
                            <div class="flex items-center gap-1 mb-0.5 mr-1">
                                <span class="text-[9px] font-bold text-[#1d63d8] font-mono">You (Citizen)</span>
                                <span class="text-[9px] text-[#78716c] font-mono">• ${timeStr}</span>
                            </div>
                            <div class="bg-[#1d63d8] text-white p-3 rounded-2xl rounded-tr-xs shadow-xs font-medium leading-relaxed text-xs">
                                ${escapeHtml(m.message)}
                            </div>
                        `;
                    } else {
                        bubble.className = 'flex flex-col items-start max-w-[90%]';
                        bubble.innerHTML = `
                            <div class="flex items-center gap-1 mb-0.5 ml-1">
                                <span class="text-[9px] font-bold text-amber-900 bg-amber-100 px-1.5 py-0.5 rounded font-mono">🦺 ${escapeHtml(m.sender_name || 'Volunteer Squad')}</span>
                                <span class="text-[9px] text-[#78716c] font-mono">• ${timeStr}</span>
                            </div>
                            <div class="bg-white border-2 border-amber-200 text-[#000a1e] p-3 rounded-2xl rounded-tl-xs shadow-xs font-medium leading-relaxed text-xs">
                                ${escapeHtml(m.message)}
                            </div>
                        `;
                    }
                    feed.appendChild(bubble);
                });

                if (wasAtBottom) {
                    feed.scrollTop = feed.scrollHeight;
                }
            } catch (e) {
                console.warn('Citizen chat fetch error:', e);
            }
        }

        async function sendCitizenMsg(text) {
            if (!text || !text.trim()) return;
            const txt = text.trim();

            const newMsg = {
                id: Date.now(),
                sos_id: citizenSosId,
                sender_id: <?= (int)($currentUser['id'] ?? 0) ?>,
                sender_name: '<?= addslashes($userName) ?>',
                sender_role: 'victim',
                message: txt,
                created_at: new Date().toISOString()
            };
            citizenLocalMsgs.push(newMsg);

            const feed = document.getElementById('citizenChatFeed');
            if (feed) {
                const bubble = document.createElement('div');
                bubble.className = 'flex flex-col items-end self-end max-w-[90%]';
                bubble.innerHTML = `
                    <div class="flex items-center gap-1 mb-0.5 mr-1">
                        <span class="text-[9px] font-bold text-[#1d63d8] font-mono">You (Citizen)</span>
                        <span class="text-[9px] text-[#78716c] font-mono">• Just now</span>
                    </div>
                    <div class="bg-[#1d63d8] text-white p-3 rounded-2xl rounded-tr-xs shadow-xs font-medium leading-relaxed text-xs">
                        ${escapeHtml(txt)}
                    </div>
                `;
                feed.appendChild(bubble);
                feed.scrollTop = feed.scrollHeight;
            }

            try {
                await fetch('api/victim_volunteer_chat_send.php', {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ sos_id: citizenSosId, message: txt, message_type: 'text' })
                });
            } catch (e) {
                console.warn('Message send failed, cached locally:', e);
            }
        }

        function handleSendCitizenMsg(e) {
            e.preventDefault();
            const input = document.getElementById('citizenChatInput');
            if (!input) return;
            const txt = input.value.trim();
            if (!txt) return;
            sendCitizenMsg(txt);
            input.value = '';
        }

        async function simulateCitizenVolunteerReply() {
            const replies = [
                "Hold on tight! Volunteer rescue van has entered your sector with life jackets and medical kits.",
                "We have your GPS location locked on our tactical field map. ETA is under 3 minutes.",
                "Stay calm and avoid moving flood waters. Rescue squad is actively ascending your floor.",
                "Team confirmed on-scene. Please flash your phone light towards the street so we spot you instantly."
            ];
            const reply = replies[Math.floor(Math.random() * replies.length)];
            const mockReply = {
                id: Date.now(),
                sos_id: citizenSosId,
                sender_id: 3,
                sender_name: 'Alexander Vance (Field Volunteer Lead)',
                sender_role: 'volunteer',
                message: reply,
                created_at: new Date().toISOString()
            };
            citizenLocalMsgs.push(mockReply);
            loadCitizenChat();
        }

        // ============================================================
        // CITIZEN AI CRISIS & SAFETY CHATBOT (GEMINI API)
        // ============================================================
        // Gemini API Configuration
        const GEMINI_API_KEY = atob("QVEuQWI4Uk42TE5YSGkxMkl6ZGpYdUV2NUZfdTRkZHBRTGlsbXB6SF9ZdHpONW1obnNSQVE=");
        const GEMINI_MODELS = ["gemini-3.1-flash-lite", "gemini-flash-latest", "gemini-3.5-flash"];
        const AI_STORAGE_KEY = "disastersafe_ai_chat_history_v2";
        
        let aiChatHistory = [];
        let isAiThinking = false;
        const citizenFacilities = <?= json_encode($facilities) ?>;
        const activeSosData = <?= json_encode($activeSos ?: null) ?>;
        const citizenUserData = {
            name: <?= json_encode($userName) ?>,
            phone: <?= json_encode($userPhone) ?>,
            email: <?= json_encode($userEmail) ?>
        };

        // Load persisted history from LocalStorage
        function initAiChatHistory() {
            try {
                const stored = localStorage.getItem(AI_STORAGE_KEY);
                if (stored) {
                    aiChatHistory = JSON.parse(stored);
                }
            } catch (e) {
                console.warn('Failed to parse AI chat history:', e);
                aiChatHistory = [];
            }
        }

        function saveAiChatHistory() {
            try {
                localStorage.setItem(AI_STORAGE_KEY, JSON.stringify(aiChatHistory));
            } catch (e) {
                console.warn('Failed to save AI chat history:', e);
            }
        }

        function toggleAiChat() {
            const widget = document.getElementById('aiChatWidget');
            if (!widget) return;
            const isHidden = widget.classList.contains('hidden');

            if (isHidden) {
                widget.classList.remove('hidden');
                setTimeout(() => {
                    widget.classList.remove('scale-95', 'opacity-0');
                    widget.classList.add('scale-100', 'opacity-100');
                }, 10);
                
                // Render conversation from history or show welcome message
                renderAiChatFeed();
                
                const input = document.getElementById('aiChatInput');
                if (input) input.focus();
            } else {
                toggleChatHistoryDrawer(false);
                widget.classList.remove('scale-100', 'opacity-100');
                widget.classList.add('scale-95', 'opacity-0');
                setTimeout(() => {
                    widget.classList.add('hidden');
                }, 200);
            }
        }

        function renderAiChatFeed() {
            const feed = document.getElementById('aiChatFeed');
            if (!feed) return;
            feed.innerHTML = '';

            if (aiChatHistory.length === 0) {
                renderAiWelcomeMessage();
                return;
            }

            // Render all historical messages
            aiChatHistory.forEach(item => {
                const time = item.time || new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                if (item.role === 'user') {
                    feed.insertAdjacentHTML('beforeend', `
                        <div class="flex flex-col items-end self-end max-w-[85%] ml-auto fade-in-up">
                            <div class="bg-[#000a1e] text-white px-4 py-3 rounded-2xl rounded-tr-sm shadow-xs font-normal text-xs sm:text-[13px] leading-relaxed">
                                ${escapeHtml(item.text)}
                            </div>
                            <span class="text-[10px] text-slate-400 font-mono mt-1 mr-1">${time}</span>
                        </div>
                    `);
                } else {
                    feed.insertAdjacentHTML('beforeend', `
                        <div class="flex flex-col items-start self-start max-w-[92%] space-y-1 fade-in-up">
                            <div class="bg-white border border-slate-200/90 rounded-2xl rounded-tl-sm p-4 text-xs sm:text-[13px] text-slate-800 shadow-xs leading-relaxed space-y-2">
                                <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-1.5 mb-1.5">
                                    <span class="font-bold text-xs text-slate-900 flex items-center gap-1.5">
                                        <i class="fa-solid fa-shield-halved text-indigo-600 text-xs"></i>
                                        Safety Advisor
                                    </span>
                                    <span class="text-[10px] text-slate-400 font-mono">${time}</span>
                                </div>
                                <div class="text-slate-700 space-y-1.5">
                                    ${formatAiMarkdown(item.text)}
                                </div>
                            </div>
                        </div>
                    `);
                }
            });
            feed.scrollTop = feed.scrollHeight;
        }

        function renderAiWelcomeMessage() {
            const feed = document.getElementById('aiChatFeed');
            if (!feed) return;
            feed.innerHTML = '';

            const welcomeHtml = `
                <div class="space-y-3.5">
                    <!-- Main Welcome Card -->
                    <div class="bg-white border border-slate-200/90 rounded-2xl rounded-tl-sm p-4 text-xs sm:text-[13px] text-slate-800 shadow-xs space-y-2.5">
                        <div class="flex items-center gap-2 border-b border-slate-100 pb-2">
                            <div class="w-6 h-6 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-xs font-bold">
                                <i class="fa-solid fa-sparkles"></i>
                            </div>
                            <div>
                                <h5 class="text-xs font-bold text-slate-900">Hello ${escapeHtml(citizenUserData.name)}, how can I help you?</h5>
                            </div>
                        </div>
                        <p class="text-slate-600 leading-relaxed text-xs">
                            I am your <strong>DisasterSafe AI Safety Advisor</strong> powered by live telemetry. I have instant access to your GPS coordinates, active SOS telemetry, and all nearest relief shelters.
                        </p>
                        <div class="grid grid-cols-2 gap-2 pt-0.5">
                            <div class="p-2 rounded-xl bg-slate-50 border border-slate-100 text-xs">
                                <span class="text-[10px] text-slate-400 font-mono block uppercase font-bold">Your Location</span>
                                <strong class="text-slate-800 font-mono text-[11px]">${currentLat.toFixed(4)}, ${currentLng.toFixed(4)}</strong>
                            </div>
                            <div class="p-2 rounded-xl bg-slate-50 border border-slate-100 text-xs">
                                <span class="text-[10px] text-slate-400 font-mono block uppercase font-bold">Distress Status</span>
                                <strong class="${activeSosData ? 'text-amber-600' : 'text-emerald-600'} text-[11px]">
                                    ${activeSosData ? `SOS #${activeSosData.id} (${activeSosData.status})` : 'Safe / Standby'}
                                </strong>
                            </div>
                        </div>
                    </div>

                    <!-- Actionable Guide Cards -->
                    <div class="text-[10px] font-bold text-slate-400 px-1 uppercase tracking-wider font-mono">Suggested Inquiries</div>
                    <div class="space-y-1.5">
                        <button type="button" onclick="askQuickPrompt('Where is my nearest safe shelter and how do I reach it?')" class="w-full text-left p-3 rounded-xl bg-white hover:bg-slate-50 border border-slate-200/90 transition-all flex items-center justify-between text-xs font-semibold text-slate-800 group shadow-xs cursor-pointer">
                            <span class="flex items-center gap-2.5">
                                <span class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center text-xs"><i class="fa-solid fa-campground"></i></span>
                                <span>Find Nearest Safe Relief Shelter</span>
                            </span>
                            <i class="fa-solid fa-chevron-right text-slate-300 group-hover:text-slate-600 text-[10px] transition-transform group-hover:translate-x-0.5"></i>
                        </button>
                        <button type="button" onclick="askQuickPrompt('How to filter and purify contaminated water during a flood emergency?')" class="w-full text-left p-3 rounded-xl bg-white hover:bg-slate-50 border border-slate-200/90 transition-all flex items-center justify-between text-xs font-semibold text-slate-800 group shadow-xs cursor-pointer">
                            <span class="flex items-center gap-2.5">
                                <span class="w-7 h-7 rounded-lg bg-blue-50 text-blue-700 flex items-center justify-center text-xs"><i class="fa-solid fa-droplet"></i></span>
                                <span>Water Purification &amp; Survival Steps</span>
                            </span>
                            <i class="fa-solid fa-chevron-right text-slate-300 group-hover:text-slate-600 text-[10px] transition-transform group-hover:translate-x-0.5"></i>
                        </button>
                        <button type="button" onclick="askQuickPrompt('Give me immediate first aid instructions for burn or trauma injuries.')" class="w-full text-left p-3 rounded-xl bg-white hover:bg-slate-50 border border-slate-200/90 transition-all flex items-center justify-between text-xs font-semibold text-slate-800 group shadow-xs cursor-pointer">
                            <span class="flex items-center gap-2.5">
                                <span class="w-7 h-7 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center text-xs"><i class="fa-solid fa-kit-medical"></i></span>
                                <span>Emergency First Aid Instructions</span>
                            </span>
                            <i class="fa-solid fa-chevron-right text-slate-300 group-hover:text-slate-600 text-[10px] transition-transform group-hover:translate-x-0.5"></i>
                        </button>
                    </div>
                </div>
            `;
            feed.innerHTML = welcomeHtml;
        }

        // ============================================================
        // CHAT HISTORY DRAWER & MANAGEMENT
        // ============================================================
        function toggleChatHistoryDrawer(forceState) {
            const drawer = document.getElementById('aiHistoryDrawer');
            if (!drawer) return;

            const isCurrentlyOpen = !drawer.classList.contains('translate-x-full');
            const shouldOpen = forceState !== undefined ? forceState : !isCurrentlyOpen;

            if (shouldOpen) {
                renderHistoryDrawerList();
                drawer.classList.remove('translate-x-full');
            } else {
                drawer.classList.add('translate-x-full');
            }
        }

        function renderHistoryDrawerList() {
            const list = document.getElementById('aiHistoryList');
            if (!list) return;
            list.innerHTML = '';

            const userMessages = aiChatHistory.filter(m => m.role === 'user');

            if (userMessages.length === 0) {
                list.innerHTML = `
                    <div class="p-6 text-center text-slate-400 space-y-2">
                        <i class="fa-solid fa-clock-rotate-left text-2xl text-slate-300"></i>
                        <p class="text-xs font-medium">No previous chat history found.</p>
                        <p class="text-[11px] text-slate-400">Your questions and AI responses will be securely stored here.</p>
                    </div>
                `;
                return;
            }

            // Render history items grouped by user queries
            userMessages.forEach((userMsg, idx) => {
                const modelReplyIdx = aiChatHistory.findIndex((m, i) => i > aiChatHistory.indexOf(userMsg) && m.role === 'model');
                const modelReply = modelReplyIdx !== -1 ? aiChatHistory[modelReplyIdx] : null;
                const previewText = modelReply ? modelReply.text.replace(/[*#_]/g, '').slice(0, 90) + '...' : 'Response generated';

                const cardHtml = `
                    <div onclick="toggleChatHistoryDrawer(false)" class="p-3 bg-white hover:bg-indigo-50/50 border border-slate-200 rounded-xl transition-all shadow-xs cursor-pointer group">
                        <div class="flex items-center justify-between gap-1 mb-1">
                            <span class="font-bold text-xs text-slate-900 truncate group-hover:text-indigo-600 flex items-center gap-1.5">
                                <i class="fa-regular fa-message text-[10px] text-indigo-500"></i>
                                ${escapeHtml(userMsg.text)}
                            </span>
                            <span class="text-[10px] text-slate-400 font-mono shrink-0">${userMsg.time || ''}</span>
                        </div>
                        <p class="text-[11px] text-slate-500 line-clamp-2 leading-relaxed">
                            ${escapeHtml(previewText)}
                        </p>
                    </div>
                `;
                list.insertAdjacentHTML('beforeend', cardHtml);
            });
        }

        function clearAllChatHistory() {
            if (confirm("Are you sure you want to delete all saved chat history?")) {
                aiChatHistory = [];
                localStorage.removeItem(AI_STORAGE_KEY);
                toggleChatHistoryDrawer(false);
                renderAiWelcomeMessage();
            }
        }

        function clearAiChat() {
            aiChatHistory = [];
            localStorage.removeItem(AI_STORAGE_KEY);
            toggleChatHistoryDrawer(false);
            renderAiWelcomeMessage();
        }

        function askQuickPrompt(query) {
            const input = document.getElementById('aiChatInput');
            if (input) {
                input.value = query;
                handleSendAiMessage(new Event('submit'));
            }
        }

        function calculateDistanceKm(lat1, lon1, lat2, lon2) {
            const R = 6371; // km
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                      Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                      Math.sin(dLon / 2) * Math.sin(dLon / 2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            return R * c;
        }

        function buildCitizenSystemContext() {
            let sortedFacilities = [];
            if (Array.isArray(citizenFacilities)) {
                sortedFacilities = citizenFacilities.map(f => {
                    const lat = parseFloat(f.latitude);
                    const lng = parseFloat(f.longitude);
                    let dist = null;
                    if (!isNaN(lat) && !isNaN(lng)) {
                        dist = calculateDistanceKm(currentLat, currentLng, lat, lng);
                    }
                    return {
                        name: f.name,
                        type: f.type,
                        status: f.status || 'Operational',
                        contact: f.contact || 'N/A',
                        lat: lat,
                        lng: lng,
                        distanceKm: dist !== null ? dist.toFixed(2) : 'Unknown'
                    };
                }).sort((a, b) => {
                    if (a.distanceKm === 'Unknown') return 1;
                    if (b.distanceKm === 'Unknown') return -1;
                    return parseFloat(a.distanceKm) - parseFloat(b.distanceKm);
                });
            }

            return `
You are DisasterSafe AI Safety Advisor, an intelligent real-time crisis response and disaster safety intelligence system.

CRITICAL INSTRUCTIONS (MUST FOLLOW STRICTLY):
1. NO GREETINGS OR ROBOTIC INTROS: NEVER start with "Hello [Name]", "I am your AI advisor", "It is 1:38 AM", or "Since you are safe...". Jump DIRECTLY into answering the user's question from the first word.
2. CONTEXT AWARENESS:
   - If the user asks for shelters, hospitals, evacuation routes, or places to go, use the VERIFIED NEARBY FACILITIES list below to give exact names, distances (km), and contact numbers.
   - If the user asks about their emergency SOS status or dispatch, use the ACTIVE EMERGENCY SOS STATUS below.
   - For general survival, safety guides, medical/first-aid, or questions like "how to filter water", give precise, step-by-step actionable instructions immediately.
   - If the user asks to translate, summarize, or continue based on previous turns (e.g. "tell me that in marathi"), reference the previous conversation content directly!
3. FORMATTING:
   - Use clean Markdown with bold step headers (e.g. ### 1. Sediment Removal, ### 2. Boiling).
   - Keep points concise, actionable, and life-saving.

USER & SITUATIONAL TELEMETRY:
- Citizen Name: ${citizenUserData.name || 'Citizen'}
- Live GPS: ${currentLat.toFixed(5)}, ${currentLng.toFixed(5)}
- Active SOS: ${activeSosData ? `#${activeSosData.id} | Status: ${activeSosData.status} | Type: ${activeSosData.emergency_type} (${activeSosData.priority}) | Unit: ${activeSosData.assigned_unit || 'NDRF Squad'} | ETA: ~${activeSosData.eta_minutes || 4} mins` : 'No active SOS filed (Citizen is safe)'}

VERIFIED NEARBY RELIEF FACILITIES:
${sortedFacilities.slice(0, 8).map(f => `- [${f.type}] ${f.name} (${f.status}) ~${f.distanceKm} km away. Contact: ${f.contact}`).join('\n')}

OFFICIAL EMERGENCY HELPLINES:
- National Emergency All-in-One: 112
- Police: 100
- Fire: 101
- Ambulance / EMS: 108 / 102
- NDRF Disaster Response HQ: 1070 / 1078
`;
        }

        function formatAiMarkdown(text) {
            if (!text) return '';
            let html = escapeHtml(text);

            // Replace horizontal dividers
            html = html.replace(/\*\*\*|---|___/g, '<hr class="my-2.5 border-slate-200" />');

            // Headers
            html = html.replace(/^### (.*$)/gim, '<h4 class="text-[13px] font-bold text-slate-900 mt-2 mb-1 flex items-center gap-1.5"><span class="w-1 h-3.5 bg-indigo-600 rounded-full inline-block"></span>$1</h4>');
            html = html.replace(/^## (.*$)/gim, '<h3 class="text-sm font-extrabold text-slate-900 mt-2.5 mb-1.5 flex items-center gap-1.5"><span class="w-1.5 h-4 bg-indigo-600 rounded-full inline-block"></span>$1</h3>');
            html = html.replace(/^# (.*$)/gim, '<h2 class="text-sm font-black text-slate-900 mt-3 mb-2">$1</h2>');

            // Bold **text**
            html = html.replace(/\*\*(.*?)\*\*/g, '<strong class="font-bold text-slate-900">$1</strong>');
            // Italic *text*
            html = html.replace(/\*(.*?)\*/g, '<em class="text-slate-800">$1</em>');

            // Phone numbers clickable as clean badge pills
            html = html.replace(/\b(112|100|101|108|102|1070|1078|\+91\s?\d{5}\s?\d{5}|\d{10})\b/g, '<a href="tel:$1" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 font-bold text-[11px] no-underline transition-colors"><i class="fa-solid fa-phone text-[9px]"></i> $1</a>');

            // Numbered list items: 1. Item
            html = html.replace(/^\s*(\d+)\.\s+(.*$)/gim, '<div class="flex items-start gap-2.5 my-1.5"><span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-indigo-50 border border-indigo-200/80 text-indigo-700 font-mono text-[11px] font-bold shrink-0 mt-0.5">$1</span><div class="leading-relaxed flex-1 text-slate-700">$2</div></div>');

            // Bullet points: * or -
            html = html.replace(/^\s*[\-\*]\s+(.*$)/gim, '<div class="flex items-start gap-2 my-1 pl-1"><span class="w-1.5 h-1.5 rounded-full bg-indigo-500 mt-2 shrink-0"></span><div class="leading-relaxed flex-1 text-slate-700">$1</div></div>');

            // Double line breaks to clean spacing
            html = html.replace(/\n\n/g, '<div class="h-2"></div>');
            html = html.replace(/\n/g, '<br/>');

            return html;
        }

        async function handleSendAiMessage(e) {
            if (e) e.preventDefault();
            if (isAiThinking) return;

            const input = document.getElementById('aiChatInput');
            if (!input) return;
            const query = input.value.trim();
            if (!query) return;

            input.value = '';
            const feed = document.getElementById('aiChatFeed');

            // 1. User Message Bubble (Human sleek style)
            const userTime = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            const userMsgHtml = `
                <div class="flex flex-col items-end self-end max-w-[85%] ml-auto fade-in-up">
                    <div class="bg-[#000a1e] text-white px-4 py-3 rounded-2xl rounded-tr-sm shadow-xs font-normal text-xs sm:text-[13px] leading-relaxed">
                        ${escapeHtml(query)}
                    </div>
                    <span class="text-[10px] text-slate-400 font-mono mt-1 mr-1">${userTime}</span>
                </div>
            `;
            feed.insertAdjacentHTML('beforeend', userMsgHtml);
            feed.scrollTop = feed.scrollHeight;

            // Add user message to conversation history
            aiChatHistory.push({ role: 'user', text: query, time: userTime });
            // Sliding window: keep up to last 16 turns in history
            if (aiChatHistory.length > 16) {
                aiChatHistory = aiChatHistory.slice(aiChatHistory.length - 16);
            }
            saveAiChatHistory();

            // 2. Typing Indicator (3 Bouncing Dots)
            isAiThinking = true;
            const sendBtn = document.getElementById('aiSendBtn');
            if (sendBtn) sendBtn.disabled = true;

            const typingId = 'typingIndicator_' + Date.now();
            const typingHtml = `
                <div id="${typingId}" class="flex items-center gap-1.5 p-3.5 bg-white border border-slate-200/80 rounded-2xl rounded-tl-sm shadow-xs w-20 fade-in-up">
                    <span class="w-2 h-2 rounded-full bg-slate-400 animate-pulse"></span>
                    <span class="w-2 h-2 rounded-full bg-slate-400 animate-pulse" style="animation-delay: 200ms;"></span>
                    <span class="w-2 h-2 rounded-full bg-slate-400 animate-pulse" style="animation-delay: 400ms;"></span>
                </div>
            `;
            feed.insertAdjacentHTML('beforeend', typingHtml);
            feed.scrollTop = feed.scrollHeight;

            // 3. Call Gemini API with Multi-turn Conversation Memory
            try {
                const systemContext = buildCitizenSystemContext();
                const contentsPayload = aiChatHistory.map(item => ({
                    role: item.role === 'user' ? 'user' : 'model',
                    parts: [{ text: item.text }]
                }));

                let responseText = null;

                for (const modelName of GEMINI_MODELS) {
                    try {
                        const url = `https://generativelanguage.googleapis.com/v1beta/models/${modelName}:generateContent?key=${GEMINI_API_KEY}`;
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                system_instruction: {
                                    parts: [{ text: systemContext }]
                                },
                                contents: contentsPayload,
                                generationConfig: {
                                    temperature: 0.25,
                                    maxOutputTokens: 900
                                }
                            })
                        });

                        if (response.ok) {
                            const resData = await response.json();
                            if (resData.candidates && resData.candidates[0] && resData.candidates[0].content && resData.candidates[0].content.parts) {
                                responseText = resData.candidates[0].content.parts[0].text;
                                if (responseText && responseText.trim().length > 0) {
                                    break; // Success!
                                }
                            }
                        }
                    } catch (fetchErr) {
                        console.warn(`Model ${modelName} multi-turn attempt failed:`, fetchErr);
                    }
                }

                // If completely offline or API unreachable, provide genuine relevant answer
                if (!responseText) {
                    responseText = generateFallbackCitizenResponse(query);
                }

                const aiTime = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

                // Save assistant reply to conversation history
                aiChatHistory.push({ role: 'model', text: responseText, time: aiTime });
                saveAiChatHistory();

                // 4. Remove Typing indicator and Render Response Bubble
                const typingEl = document.getElementById(typingId);
                if (typingEl) typingEl.remove();

                const aiBubbleHtml = `
                    <div class="flex flex-col items-start self-start max-w-[92%] space-y-1 fade-in-up">
                        <div class="bg-white border border-slate-200/90 rounded-2xl rounded-tl-sm p-4 text-xs sm:text-[13px] text-slate-800 shadow-xs leading-relaxed space-y-2">
                            <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-1.5 mb-1.5">
                                <span class="font-bold text-xs text-slate-900 flex items-center gap-1.5">
                                    <i class="fa-solid fa-shield-halved text-indigo-600 text-xs"></i>
                                    Safety Advisor
                                </span>
                                <span class="text-[10px] text-slate-400 font-mono">${aiTime}</span>
                            </div>
                            <div class="text-slate-700 space-y-1.5">
                                ${formatAiMarkdown(responseText)}
                            </div>
                        </div>
                    </div>
                `;
                feed.insertAdjacentHTML('beforeend', aiBubbleHtml);
                feed.scrollTop = feed.scrollHeight;

            } catch (err) {
                console.error('Error handling AI response:', err);
                const typingEl = document.getElementById(typingId);
                if (typingEl) typingEl.remove();

                feed.insertAdjacentHTML('beforeend', `
                    <div class="p-3 bg-red-50 text-red-800 rounded-xl border border-red-200 text-xs">
                        ⚠️ Unable to reach safety intelligence servers. For immediate emergencies, dial <strong>112</strong> immediately.
                    </div>
                `);
                feed.scrollTop = feed.scrollHeight;
            } finally {
                isAiThinking = false;
                if (sendBtn) sendBtn.disabled = false;
            }
        }

        // Comprehensive Offline Emergency Knowledge Base (Only used if offline)
        function generateFallbackCitizenResponse(query) {
            const q = query.toLowerCase();

            if (q.includes('filter') || (q.includes('purif') && q.includes('water')) || (q.includes('clean') && q.includes('water')) || q.includes('drink')) {
                return `### Emergency Water Filtration & Purification Steps:\n\n1. **Sedimentation (Settling):** Let murky water sit undisturbed in a container for 1-2 hours so heavy sand and mud settle at the bottom. Pour off the clear water from top.\n2. **Physical Filtration:** Pour the clear water through clean layered cloth, a cotton t-shirt, or a DIY bottle filter filled with sand, fine charcoal, and pebbles to trap fine debris.\n3. **Biological Disinfection (Critical):**\n   * **Boil:** Bring water to a rolling boil for at least 1-3 minutes.\n   * **Chlorine/Bleach:** If boiling is impossible, add 2 drops of unscented household bleach per liter of clear water, stir, and wait 30 minutes before drinking.`;
            }

            if (q.includes('shelter') || q.includes('camp') || q.includes('safe place') || q.includes('where to go')) {
                const nearest = citizenFacilities.find(f => f.type === 'Relief Shelter') || citizenFacilities[0];
                return `### Nearest Verified Safe Relief Shelter:\n\n* **Facility:** **${nearest ? nearest.name : 'Sector 4 Community Relief Shelter'}**\n* **Status:** Operational & Fully Stocked\n* **Helpline Contact:** ${nearest ? nearest.contact : '112'}\n\n**Action Advice:** Evacuate along designated safe evacuation corridors with an emergency waterproof grab-bag containing essential medicines and identity documents.`;
            }

            if (q.includes('sos') || q.includes('status') || q.includes('help') || q.includes('signal')) {
                if (activeSosData) {
                    return `### Active SOS Telemetry (SOS #${activeSosData.id}):\n\n* **Status:** **${activeSosData.status}**\n* **Emergency Type:** ${activeSosData.emergency_type} (${activeSosData.priority} Priority)\n* **Assigned Squad:** ${activeSosData.assigned_unit || 'NDRF Tactical Rescue Squad'}\n* **Estimated Arrival:** ~${activeSosData.eta_minutes || 4} mins\n\nResponders have your exact GPS coordinates locked on radar. Keep your phone line free.`;
                } else {
                    return `You currently have **no active distress signal** filed on this portal. If you are in immediate danger, click the red **1-Tap Instant SOS Beacon** button on this page or call 112.`;
                }
            }

            if (q.includes('flood') || q.includes('rising water')) {
                return `### Flash Flood Immediate Survival Protocols:\n\n1. **Ascend to High Ground:** Move immediately to upper floors or rooftop.\n2. **Isolate Power Grid:** Turn off the main electrical circuit breaker before floodwater touches switchboards.\n3. **Visible Signal:** Wave a brightly colored cloth or flashlight from your window/balcony to guide rescue boats.\n4. **Never Walk/Drive in Floodwater:** 6 inches of rapid water can sweep an adult away.\n5. **Emergency Line:** Dial 112 or 1070 for flood rescue.`;
            }

            if (q.includes('first aid') || q.includes('burn') || q.includes('bleed') || q.includes('injury') || q.includes('medic') || q.includes('wound')) {
                return `### Emergency First Aid Protocols:\n\n1. **Severe Bleeding:** Apply firm, continuous direct pressure with a clean cloth. Elevate the wounded limb above heart level if no fracture is suspected.\n2. **Burns:** Cool the burned area immediately under cool running water for 10-15 minutes. Do NOT apply ice, butter, or toothpaste. Cover loosely with a clean, dry dressing.\n3. **Fractures:** Immobilize the injured limb using a splint or rolled magazine. Do not attempt to reset bones.\n4. **Emergency EMS:** Call 108 or 112 immediately.`;
            }

            if (q.includes('helpline') || q.includes('phone') || q.includes('number') || q.includes('contact')) {
                return `### Official Emergency Helplines (24x7 Toll-Free):\n\n* **National Emergency All-In-One:** 112\n* **Police Control Room:** 100\n* **Fire Brigade:** 101\n* **Ambulance / Medical EMS:** 108\n* **NDRF Disaster Management HQ:** 1070 / 1078\n* **Women Helpline:** 1091`;
            }

            return `I have analyzed your request based on your live coordinates (${currentLat.toFixed(4)}, ${currentLng.toFixed(4)}).\n\n* For life-threatening distress, use the **1-Tap SOS Beacon**.\n* For emergency responder dispatch, call 112.\n* You can ask me specific questions like **"how to filter water"**, **"nearest shelter"**, **"burn first aid"**, or **"earthquake steps"**.`;
        }

        document.addEventListener('DOMContentLoaded', () => {
            initAiChatHistory();
            initCitizenMap();
            loadCitizenChat();
            setInterval(loadCitizenChat, 4000);
        });
    </script>
</body>
</html>
