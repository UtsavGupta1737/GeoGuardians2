<?php
// citizen.php - Dedicated Public Citizen Emergency SOS Beacon & Safe Evacuation Portal
define('PAGE_TITLE', 'Public Emergency SOS Beacon');
require_once __DIR__ . '/auth.php';

$currentUser = getCurrentUser($pdo);

// Handle SOS Broadcast Submission (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'broadcast_sos') {
        $senderName = trim($_POST['sender_name'] ?? ($currentUser['name'] ?? 'Citizen in Distress'));
        $senderPhone = trim($_POST['sender_phone'] ?? ($currentUser['phone'] ?? '+91 98765 43210'));
        $latitude = filter_var($_POST['latitude'] ?? 28.6139, FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($_POST['longitude'] ?? 77.2090, FILTER_VALIDATE_FLOAT);
        $emergencyType = trim($_POST['emergency_type'] ?? 'Flood');
        $personsCount = trim($_POST['persons_count'] ?? '1 - 4');
        $bloodType = trim($_POST['blood_type'] ?? 'Unknown');
        $age = !empty($_POST['age']) ? (int)$_POST['age'] : null;
        $priority = trim($_POST['priority'] ?? 'Critical');
        $message = trim($_POST['message'] ?? '');

        // Auto-determine medical needs and agency via system triage
        $triage = determineSystemTriage($emergencyType, $priority, $personsCount);
        $dispatchAgency = $triage['agency'];
        $medicalNeeds = $triage['needs'];

        try {
            $stmt = $pdo->prepare("
                INSERT INTO emergency_sos (sender_name, sender_phone, gps_lat, gps_lng, blood_type, age, persons_count, priority, emergency_type, medical_needs, dispatch_agency, message, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
            ");
            $stmt->execute([$senderName, $senderPhone, $latitude, $longitude, $bloodType, $age, $personsCount, $priority, $emergencyType, $medicalNeeds, $dispatchAgency, $message]);

            $sosId = $pdo->lastInsertId();
            logActivity($pdo, 'CITIZEN_SOS_BROADCAST', "Public SOS #{$sosId} broadcast by {$senderName} [GPS: {$latitude}, {$longitude}] ({$emergencyType}) -> Auto-assigned to {$dispatchAgency}");
            setFlash('success', "🚨 Emergency distress signal transmitted (SOS #{$sosId})! Auto-assigned to {$dispatchAgency}. Help is en-route.");
        } catch (Exception $e) {
            setFlash('error', "Failed to broadcast distress signal: " . $e->getMessage());
        }

        header("Location: citizen.php");
        exit;
    }

    // 2. CITIZEN VOLUNTEER REGISTRATION REQUEST
    if ($action === 'apply_volunteer') {
        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $skills = trim($_POST['skills'] ?? '');
        $qualifications = trim($_POST['qualifications'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $bloodType = trim($_POST['blood_type'] ?? 'Unknown');
        $experience = (int) ($_POST['experience_years'] ?? 0);

        if (!empty($fullName) && !empty($phone) && !empty($location)) {
            $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($fullName) . '&background=10b981&color=fff';
            $stmt = $pdo->prepare("INSERT INTO volunteers (full_name, phone, email, skills, qualifications, team_name, current_location, availability_status, blood_type, application_status, experience_years, avatar) VALUES (?, ?, ?, ?, ?, 'Unassigned (Applicant)', ?, 'Pending Verification', ?, 'Pending Approval', ?, ?)");
            $stmt->execute([$fullName, $phone, $email, $skills, $qualifications, $location, $bloodType, $experience, $avatar]);

            logActivity($pdo, 'CITIZEN_VOLUNTEER_APPLY', "Citizen {$fullName} applied to join DisasterSafe Volunteer Corps from {$location}");
            setFlash('success', "🎉 Volunteer application submitted successfully! Our NGO and Disaster Response Coordinators will review your details shortly.");
        } else {
            setFlash('error', 'Please fill in all required fields (Name, Phone, Location).');
        }
        header("Location: citizen.php");
        exit;
    }
}

// Queries for nearby safe zones
$activeSos = $pdo->query("SELECT * FROM emergency_sos ORDER BY id DESC LIMIT 5")->fetchAll();
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-[#0a0f1d] text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Emergency SOS Beacon | DisasterSafe</title>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        navy: {
                            950: '#060a14',
                            900: '#0a0f1d',
                            800: '#11192e',
                            700: '#1c2b4e'
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
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; background: #0a0f1d; }
        .glass-panel {
            background: rgba(17, 25, 46, 0.85);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        @keyframes pulseDot {
            0% { transform: scale(0.95); opacity: 0.8; }
            50% { transform: scale(1.3); opacity: 1; }
            100% { transform: scale(0.95); opacity: 0.8; }
        }
        .live-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #ba1a1a;
            display: inline-block;
            box-shadow: 0 0 10px #ba1a1a;
            animation: pulseDot 1.5s infinite;
        }
    </style>
</head>
<body class="min-h-full bg-[#0a0f1d] text-slate-100 flex flex-col antialiased">

    <!-- Standalone Public Emergency Topbar -->
    <header class="h-16 bg-[#0c1326]/90 backdrop-blur-xl border-b border-[#243049] px-4 sm:px-8 flex items-center justify-between sticky top-0 z-30">
        <div class="flex items-center gap-3">
            <a href="login.php" class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-red-600 to-indigo-600 flex items-center justify-center font-black text-white text-base shadow-md shadow-indigo-600/30">
                    <i class="fa-solid fa-shield-halved text-sm"></i>
                </div>
                <div>
                    <span class="text-base font-extrabold text-white tracking-tight">DisasterSafe</span>
                    <span class="text-[10px] font-bold text-rose-400 uppercase tracking-widest ml-1.5 px-1.5 py-0.5 rounded bg-rose-500/20 border border-rose-500/30">PUBLIC SOS</span>
                </div>
            </a>
        </div>

        <div class="flex items-center gap-3">
            <!-- Emergency Helplines Badge -->
            <div class="hidden md:flex items-center gap-3 text-xs font-semibold bg-[#11192e] px-3.5 py-1.5 rounded-full border border-[#243049]">
                <span class="text-rose-400"><i class="fa-solid fa-phone-volume mr-1"></i> Helpline: 112 / 1078 (NDRF)</span>
            </div>

            <button type="button" onclick="document.getElementById('applyVolunteerModal').classList.remove('hidden')" class="px-3.5 py-1.5 rounded-xl bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-300 text-xs font-bold border border-emerald-500/30 transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-hand-holding-heart"></i>
                <span>Join Volunteer Corps</span>
            </button>
            
            <a href="login.php" class="px-3.5 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold border border-slate-700 transition-all">
                Official Agency Login →
            </a>
        </div>
    </header>

    <!-- Main Public Distress Portal Content -->
    <main class="flex-1 max-w-5xl w-full mx-auto p-4 sm:p-6 lg:p-8 space-y-6">
        
        <!-- Big Emergency SOS Button Card -->
        <div class="glass-panel p-6 sm:p-8 rounded-3xl border border-rose-500/40 bg-gradient-to-br from-rose-950/40 via-[#11192e] to-[#0a0f1d] relative overflow-hidden shadow-2xl text-center">
            <div class="max-w-xl mx-auto space-y-4">
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-rose-500/20 text-rose-300 border border-rose-500/40 tracking-wider uppercase">
                    One-Touch Public Distress Beacon
                </span>
                <h1 class="text-2xl sm:text-4xl font-black text-white">Press for Immediate Rescue Dispatch</h1>
                <p class="text-slate-300 text-xs sm:text-sm">Your browser will detect your exact GPS coordinates and transmit a high-priority distress alert directly to NDRF, Police, and local Medical rescue teams.</p>

                <!-- Giant Pulsing SOS Button -->
                <div class="py-4">
                    <button type="button" id="btnBigSos" onclick="triggerQuickSos()" class="w-36 h-36 sm:w-44 sm:h-44 mx-auto rounded-full bg-gradient-to-tr from-red-700 via-red-600 to-rose-500 text-white font-black text-2xl sm:text-3xl tracking-wider shadow-2xl shadow-red-600/70 border-4 border-white/20 flex flex-col items-center justify-center gap-1 hover:scale-105 active:scale-95 transition-all duration-300 animate-pulse">
                        <i class="fa-solid fa-triangle-exclamation text-3xl sm:text-4xl"></i>
                        <span>SOS</span>
                        <span class="text-[10px] font-semibold tracking-widest uppercase opacity-80">BROADCAST</span>
                    </button>
                </div>

                <div id="gpsStatusPill" class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs bg-slate-800 border border-slate-700 text-slate-300">
                    <i class="fa-solid fa-location-crosshairs text-indigo-400"></i>
                    <span id="gpsStatusText">GPS Locator Ready (Click SOS button to transmit)</span>
                </div>
            </div>
        </div>

        <!-- Detailed Emergency Form & Safe Evacuation Finder Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            
            <!-- Left: Specific Distress Details Form -->
            <div class="lg:col-span-7 glass-panel p-6 rounded-2xl border border-[#243049] space-y-5">
                <div class="flex items-center gap-2.5 pb-4 border-b border-[#243049]">
                    <div class="w-8 h-8 rounded-lg bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400">
                        <i class="fa-solid fa-clipboard-list text-sm"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-white text-sm sm:text-base">Specific Distress Details (Optional)</h3>
                        <p class="text-xs text-slate-400">Provide additional information to help rescue squads prepare equipment.</p>
                    </div>
                </div>

                <form method="POST" action="citizen.php" class="space-y-4">
                    <input type="hidden" name="action" value="broadcast_sos">
                    <input type="hidden" name="latitude" id="inputLat" value="28.6139">
                    <input type="hidden" name="longitude" id="inputLng" value="77.2090">
                    <input type="hidden" name="priority" value="Critical">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Your Full Name *</label>
                            <input type="text" name="sender_name" required value="<?= htmlspecialchars($currentUser['name'] ?? '') ?>" placeholder="e.g. Rahul Sharma" class="w-full px-3.5 py-2.5 bg-[#0a0f1d] border border-[#243049] rounded-xl text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Phone / WhatsApp Number *</label>
                            <input type="text" name="sender_phone" required value="<?= htmlspecialchars($currentUser['phone'] ?? '+91 ') ?>" placeholder="+91 98112 34567" class="w-full px-3.5 py-2.5 bg-[#0a0f1d] border border-[#243049] rounded-xl text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 font-mono">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Emergency Type *</label>
                            <select name="emergency_type" class="w-full px-3.5 py-2.5 bg-[#0a0f1d] border border-[#243049] rounded-xl text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                                <option value="Flood">🌊 Flood</option>
                                <option value="Fire">🔥 Fire</option>
                                <option value="Earthquake">🌋 Earthquake</option>
                                <option value="Building Collapse">🏚️ Building Collapse</option>
                                <option value="Medical Trauma">🚑 Medical Trauma</option>
                                <option value="Cyclone / Storm">🌪️ Cyclone / Storm</option>
                                <option value="Other Distress">⚠️ Other Distress</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">People in Need of Rescue *</label>
                            <select name="persons_count" class="w-full px-3.5 py-2.5 bg-[#0a0f1d] border border-[#243049] rounded-xl text-sm text-slate-100 focus:outline-none focus:border-indigo-500 font-semibold">
                                <option value="1 - 4">1 - 4 Persons</option>
                                <option value="4 - 8">4 - 8 Persons</option>
                                <option value="8 - 12">8 - 12 Persons</option>
                                <option value="12+">12+ Persons (Large Group)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Blood Group (Optional)</label>
                            <select name="blood_type" class="w-full px-3.5 py-2.5 bg-[#0a0f1d] border border-[#243049] rounded-xl text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                                <option value="Unknown">Unknown / Not Sure</option>
                                <option value="O+">O+</option>
                                <option value="A+">A+</option>
                                <option value="B+">B+</option>
                                <option value="AB+">AB+</option>
                                <option value="O-">O-</option>
                                <option value="A-">A-</option>
                                <option value="B-">B-</option>
                                <option value="AB-">AB-</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Age of Primary Caller (Optional)</label>
                            <input type="number" name="age" min="1" max="110" placeholder="e.g. 35" class="w-full px-3.5 py-2.5 bg-[#0a0f1d] border border-[#243049] rounded-xl text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>

                    <div class="p-3 rounded-xl bg-indigo-950/40 border border-indigo-500/20 text-xs text-indigo-200 flex items-start gap-2.5">
                        <i class="fa-solid fa-robot text-indigo-400 mt-0.5"></i>
                        <span><strong>Automatic System Dispatch:</strong> Rescue equipment & medical supplies will be auto-triaged and assigned directly to the nearest NDRF, Fire, Police, or EMS unit based on your GPS coordinates.</span>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Optional Message / Condition</label>
                        <textarea name="message" rows="2" placeholder="Describe water level, any trapped children/elderly, or injuries (optional)..." class="w-full px-3.5 py-2.5 bg-[#0a0f1d] border border-[#243049] rounded-xl text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500"></textarea>
                    </div>

                    <button type="submit" class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 hover:to-rose-500 text-white font-bold text-sm tracking-wide shadow-lg shadow-red-600/30 transition-all flex items-center justify-center gap-2">
                        <i class="fa-solid fa-tower-broadcast animate-pulse"></i>
                        TRANSMIT DISTRESS BEACON WITH GPS
                    </button>
                </form>
            </div>

            <!-- Right: Verified Safe Evacuation Shelters & Hospitals -->
            <div class="lg:col-span-5 space-y-6">
                
                <div class="glass-panel p-5 rounded-2xl border border-[#243049] space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-[#243049]">
                        <h3 class="font-bold text-white text-sm flex items-center gap-2">
                            <i class="fa-solid fa-tents text-emerald-400"></i>
                            Verified Safe Shelters & Hospitals
                        </h3>
                        <span class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 font-bold border border-emerald-500/20">Operational</span>
                    </div>

                    <div class="space-y-3 text-xs">
                        <div class="p-3 rounded-xl bg-[#0a0f1d] border border-[#243049]">
                            <div class="flex items-center justify-between font-bold text-slate-200 mb-1">
                                <span>District Multi-Specialty Hospital</span>
                                <span class="text-emerald-400 font-mono">42 Beds Open</span>
                            </div>
                            <p class="text-slate-400 mb-1.5">Connaught Place Area • Trauma Unit Ready</p>
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-indigo-400 font-medium"><i class="fa-solid fa-phone mr-1"></i> 011-23345566</span>
                                <span class="text-slate-500">Distance ~2.1 km</span>
                            </div>
                        </div>

                        <div class="p-3 rounded-xl bg-[#0a0f1d] border border-[#243049]">
                            <div class="flex items-center justify-between font-bold text-slate-200 mb-1">
                                <span>Sector 4 Community Relief Shelter</span>
                                <span class="text-emerald-400 font-mono">120 Slots Open</span>
                            </div>
                            <p class="text-slate-400 mb-1.5">Civil Lines Community Hall • Food & Water Depot</p>
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-indigo-400 font-medium"><i class="fa-solid fa-phone mr-1"></i> +91 98765 00001</span>
                                <span class="text-slate-500">Distance ~3.8 km</span>
                            </div>
                        </div>

                        <div class="p-3 rounded-xl bg-[#0a0f1d] border border-[#243049]">
                            <div class="flex items-center justify-between font-bold text-slate-200 mb-1">
                                <span>Govt. Stadium Evacuation Camp</span>
                                <span class="text-amber-400 font-mono">80 Slots Open</span>
                            </div>
                            <p class="text-slate-400 mb-1.5">Stadium Complex • Medical Triage Operational</p>
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-indigo-400 font-medium"><i class="fa-solid fa-phone mr-1"></i> +91 98765 00002</span>
                                <span class="text-slate-500">Distance ~5.2 km</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Emergency Helplines Notice -->
                <div class="glass-panel p-5 rounded-2xl border border-[#243049] space-y-3 bg-gradient-to-br from-indigo-950/30 to-[#11192e]">
                    <h3 class="font-bold text-white text-sm flex items-center gap-2">
                        <i class="fa-solid fa-headset text-indigo-400"></i>
                        National Emergency Numbers
                    </h3>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div class="p-2.5 rounded-lg bg-[#0a0f1d] border border-[#243049]">
                            <p class="text-slate-400 text-[10px]">National Emergency</p>
                            <p class="text-white font-mono font-bold text-base">112</p>
                        </div>
                        <div class="p-2.5 rounded-lg bg-[#0a0f1d] border border-[#243049]">
                            <p class="text-slate-400 text-[10px]">NDRF Crisis Control</p>
                            <p class="text-rose-400 font-mono font-bold text-base">1078</p>
                        </div>
                        <div class="p-2.5 rounded-lg bg-[#0a0f1d] border border-[#243049]">
                            <p class="text-slate-400 text-[10px]">Ambulance Service</p>
                            <p class="text-emerald-400 font-mono font-bold text-base">108</p>
                        </div>
                        <div class="p-2.5 rounded-lg bg-[#0a0f1d] border border-[#243049]">
                            <p class="text-slate-400 text-[10px]">Fire Rescue Service</p>
                            <p class="text-amber-400 font-mono font-bold text-base">101</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </main>

    <!-- Public Footer -->
    <footer class="h-14 border-t border-[#243049] bg-[#0c1326] px-4 sm:px-8 flex items-center justify-between text-xs text-slate-500">
        <div>GeoGuardians • DisasterSafe Public Emergency Platform</div>
        <div>Smart India Hackathon (SIH)</div>
    </footer>

    <script>
    // Web Audio API Siren Alert Synthesizer
    function playSirenAudio() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(880, ctx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(440, ctx.currentTime + 0.3);
            gain.gain.setValueAtTime(0.2, ctx.currentTime);
            gain.gain.linearRampToValueAtTime(0.01, ctx.currentTime + 0.3);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.35);
        } catch(e) {}
    }

    function triggerQuickSos() {
        playSirenAudio();
        const pill = document.getElementById('gpsStatusPill');
        const text = document.getElementById('gpsStatusText');
        text.innerText = 'Acquiring GPS location...';

        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    document.getElementById('inputLat').value = pos.coords.latitude;
                    document.getElementById('inputLng').value = pos.coords.longitude;
                    document.getElementById('inputLocationText').value = `GPS: ${pos.coords.latitude.toFixed(5)}, ${pos.coords.longitude.toFixed(5)}`;
                    text.innerText = `GPS Acquired: ${pos.coords.latitude.toFixed(4)}, ${pos.coords.longitude.toFixed(4)}`;
                    pill.classList.replace('bg-slate-800', 'bg-emerald-950');
                    pill.classList.replace('border-slate-700', 'border-emerald-700');
                    
                    // Submit form
                    document.querySelector('form').submit();
                },
                (err) => {
                    text.innerText = 'Broadcasting with default area coordinates...';
                    document.querySelector('form').submit();
                }
            );
        } else {
            document.querySelector('form').submit();
        }
    }
    </script>

    <!-- SweetAlert2 Toast Notifications -->
    <?php if ($flash): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: <?= json_encode($flash['type'] === 'error' ? 'error' : ($flash['type'] === 'warning' ? 'warning' : 'success')) ?>,
                title: <?= json_encode($flash['message']) ?>,
                background: '#11192e',
                color: '#f8fafc',
                confirmButtonColor: '#2563eb'
            });
        });
    </script>
    <?php endif; ?>

    <!-- CITIZEN VOLUNTEER REGISTRATION MODAL -->
    <div id="applyVolunteerModal" class="fixed inset-0 bg-[#060a14]/85 backdrop-blur-md z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-[#0f172a] border border-[#243049] rounded-3xl w-full max-w-xl max-h-[90vh] flex flex-col shadow-2xl overflow-hidden">
            <div class="h-16 px-6 bg-[#0c1326] border-b border-[#243049] flex items-center justify-between shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
                        <i class="fa-solid fa-hand-holding-heart text-sm"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-extrabold text-white">Join DisasterSafe Volunteer Corps</h3>
                        <p class="text-[11px] text-slate-400">Offer your skills for flood relief, medical aid, or food packaging</p>
                    </div>
                </div>
                <button type="button" onclick="document.getElementById('applyVolunteerModal').classList.add('hidden')" class="text-slate-400 hover:text-white p-2">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form method="POST" action="citizen.php" class="flex-1 overflow-y-auto p-6 space-y-4 text-xs">
                <input type="hidden" name="action" value="apply_volunteer">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Your Full Name *</label>
                        <input type="text" name="full_name" required placeholder="e.g. Rahul Sharma" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Phone Number (WhatsApp) *</label>
                        <input type="text" name="phone" required placeholder="e.g. +91 98112 34567" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white font-mono">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Email Address</label>
                        <input type="email" name="email" placeholder="e.g. rahul@example.com" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Blood Group</label>
                        <select name="blood_type" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white">
                            <option value="O+">O+</option>
                            <option value="A+">A+</option>
                            <option value="B+">B+</option>
                            <option value="AB+">AB+</option>
                            <option value="O-">O-</option>
                            <option value="A-">A-</option>
                            <option value="B-">B-</option>
                            <option value="AB-">AB-</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Experience (Yrs)</label>
                        <input type="number" name="experience_years" value="1" min="0" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Current Residential Locality / City *</label>
                    <input type="text" name="location" required placeholder="e.g. Sector 18, Noida / Mayur Vihar, Delhi" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Professional Qualifications / Background</label>
                    <input type="text" name="qualifications" placeholder="e.g. Medical Student, Civil Engineer, Heavy Driver, Certified First Aider" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-300 uppercase mb-1">Specialized Skills & Capabilities</label>
                    <input type="text" name="skills" placeholder="e.g. First Aid, CPR, Food Distribution, Ham Radio, 4x4 Driving, Swimming" class="w-full px-3 py-2 bg-[#11192e] border border-[#243049] rounded-xl text-white">
                </div>

                <div class="flex justify-end gap-3 pt-3 border-t border-[#243049]">
                    <button type="button" onclick="document.getElementById('applyVolunteerModal').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold shadow-lg shadow-emerald-600/30">
                        Submit Volunteer Application
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
