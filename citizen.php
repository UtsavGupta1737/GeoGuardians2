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
            setFlash('success', "Emergency distress signal transmitted (SOS #{$sosId})! Auto-assigned to {$dispatchAgency}. Help is en-route.");
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
                        <a href="logout.php" title="Sign Out" class="p-2 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 transition-colors">
                            <i class="fa-solid fa-right-from-bracket text-xs"></i>
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
                                    <option value="Flood">Flash Flood / Water Inflow</option>
                                    <option value="Fire">Fire / Hazmat / Smoke</option>
                                    <option value="Earthquake">Earthquake / Structural Hazard</option>
                                    <option value="Cyclone">Cyclone / Severe Storm</option>
                                    <option value="Building Collapse">Building / Wall Collapse</option>
                                    <option value="Medical Trauma">Critical Medical Emergency</option>
                                    <option value="General">Other Life-Threatening Crisis</option>
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
    </footer>

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
                            ${f.contact ? `<a href="tel:${f.contact}" style="font-size:11px; color:#c53030; font-weight:bold; text-decoration:none;"><i class='fa-solid fa-phone' style='font-size:10px;'></i> Call: ${escapeHtml(f.contact)}</a>` : ''}
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
            if (confirm("TRANSMIT EMERGENCY BEACON IMMEDIATELY?\n\nThis will send your current GPS coordinates to Emergency Services and First Responders.")) {
                document.getElementById('sosForm').submit();
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            return text.replace(/[&<>"']/g, function(m) {
                return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[m];
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            initCitizenMap();
        });
    </script>
</body>
</html>
