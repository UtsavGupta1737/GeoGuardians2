<?php
// login.php - DisasterSafe Unified Multi-Role Authentication Portal
require_once __DIR__ . '/auth.php';

$error = '';
$success = '';

// 1. Handle quick login demo switch if requested via URL query (Takes Precedence over existing session)
if (isset($_GET['quick_login']) && !empty($_GET['quick_login'])) {
    $quickEmail = trim($_GET['quick_login']);
    $stmt = $pdo->prepare("SELECT u.*, r.slug as role_slug FROM users u JOIN roles r ON u.role_id = r.id WHERE u.email = ? LIMIT 1");
    $stmt->execute([$quickEmail]);
    $user = $stmt->fetch();

    if ($user && $user['status'] === 'active') {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role_slug'];

        logActivity($pdo, 'LOGIN_SUCCESS', "Quick demo login as {$user['name']} ({$user['email']})");
        setFlash('success', "Welcome back, {$user['name']}!");
        $destination = getRoleHomeUrl($user['role_slug']);
        header("Location: " . $destination);
        exit;
    } else {
        $error = "User '{$quickEmail}' not found or inactive.";
    }
}

// 2. Handle Form Submission (Takes Precedence over existing session)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrf)) {
        $error = 'Invalid security session. Please refresh and try again.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Please fill in both email and password fields.';
    } else {
        $stmt = $pdo->prepare("
            SELECT u.*, r.slug as role_slug, r.name as role_name 
            FROM users u 
            JOIN roles r ON u.role_id = r.id 
            WHERE u.email = ? 
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] !== 'active') {
                $error = 'Your account is currently ' . htmlspecialchars($user['status']) . '. Please contact Administrator.';
                logActivity($pdo, 'LOGIN_BLOCKED', "Blocked login attempt for deactivated user {$email}");
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role_slug'];

                logActivity($pdo, 'LOGIN_SUCCESS', "User {$user['name']} logged in successfully");
                setFlash('success', "Welcome back, {$user['name']}!");
                $destination = getRoleHomeUrl($user['role_slug']);
                header("Location: " . $destination);
                exit;
            }
        } else {
            $error = 'Invalid email address or password. Please check your credentials.';
            logActivity($pdo, 'LOGIN_FAILED', "Failed login attempt for email {$email}");
        }
    }
}

$activeLoggedInUser = isLoggedIn() ? getCurrentUser($pdo) : null;
$csrfToken = generateCsrfToken();
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-[#0a0f1d]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | DisasterSafe Command Center</title>
    
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
</head>
<body class="h-full font-sans bg-[#0a0f1d] text-slate-100 antialiased flex items-center justify-center p-4 sm:p-6 lg:p-8 relative overflow-x-hidden">

    <!-- Background Decorative Glows -->
    <div class="absolute top-0 left-1/4 w-96 h-96 bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute bottom-0 right-1/4 w-96 h-96 bg-red-600/10 rounded-full blur-3xl pointer-events-none"></div>

    <div class="w-full max-w-5xl grid lg:grid-cols-12 gap-8 items-center relative z-10">
        
        <!-- Left Side: System Introduction & 1-Click Test Drive -->
        <div class="lg:col-span-6 space-y-6 text-center lg:text-left">
            
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 text-xs font-semibold">
                <i class="fa-solid fa-shield-halved text-xs text-indigo-400"></i>
                <span>Disaster Management & Multi-Agency Suite</span>
            </div>

            <div class="flex items-center justify-center lg:justify-start gap-3.5">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-red-600 to-indigo-600 flex items-center justify-center font-black text-white text-2xl shadow-lg shadow-indigo-600/30">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <div>
                    <h1 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight">DisasterSafe</h1>
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Tactical Command Center</span>
                </div>
            </div>

            <p class="text-sm text-slate-400 leading-relaxed max-w-lg mx-auto lg:mx-0">
                Centralized crisis coordination platform linking **Superadmin Command**, **Police Emergency Dispatch**, and **Volunteer Relief Corps** with real-time geospatial radar tracking.
            </p>

            <!-- 1-Click Demo Accounts Grid (7 Specialized Roles) -->
            <div class="p-4 rounded-2xl bg-[#11192e] border border-[#243049]">
                <div class="flex items-center justify-between mb-2.5">
                    <span class="text-xs font-extrabold text-slate-300 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-bolt text-amber-400"></i>
                        1-Click Test Drive (7 Role Hierarchy)
                    </span>
                    <span class="text-[10px] text-slate-500">Instant Access</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                    <a href="login.php?quick_login=superadmin@system.local" class="px-2 py-2 rounded-xl bg-purple-500/10 hover:bg-purple-500/20 border border-purple-500/30 text-purple-300 text-xs font-bold text-center transition-all hover:scale-[1.02]">
                        <i class="fa-solid fa-crown text-[10px] block mb-1 text-purple-400"></i>
                        Superadmin
                    </a>
                    <a href="login.php?quick_login=ndrf.commander@disaster.local" class="px-2 py-2 rounded-xl bg-orange-500/10 hover:bg-orange-500/20 border border-orange-500/30 text-orange-300 text-xs font-bold text-center transition-all hover:scale-[1.02]">
                        <i class="fa-solid fa-truck-monster text-[10px] block mb-1 text-orange-400"></i>
                        NDRF Force
                    </a>
                    <a href="login.php?quick_login=police.command@disaster.local" class="px-2 py-2 rounded-xl bg-blue-500/10 hover:bg-blue-500/20 border border-blue-500/30 text-blue-300 text-xs font-bold text-center transition-all hover:scale-[1.02]">
                        <i class="fa-solid fa-person-military-pointing text-[10px] block mb-1 text-blue-400"></i>
                        Police Command
                    </a>
                    <a href="login.php?quick_login=fire.chief@disaster.local" class="px-2 py-2 rounded-xl bg-red-500/10 hover:bg-red-500/20 border border-red-500/30 text-red-300 text-xs font-bold text-center transition-all hover:scale-[1.02]">
                        <i class="fa-solid fa-fire-extinguisher text-[10px] block mb-1 text-red-400"></i>
                        Fire & Rescue
                    </a>
                    <a href="login.php?quick_login=medical.ems@disaster.local" class="px-2 py-2 rounded-xl bg-teal-500/10 hover:bg-teal-500/20 border border-teal-500/30 text-teal-300 text-xs font-bold text-center transition-all hover:scale-[1.02]">
                        <i class="fa-solid fa-heart-pulse text-[10px] block mb-1 text-teal-400"></i>
                        Medical / EMS
                    </a>
                    <a href="login.php?quick_login=volunteer@disaster.local" class="px-2 py-2 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 border border-emerald-500/30 text-emerald-300 text-xs font-bold text-center transition-all hover:scale-[1.02]">
                        <i class="fa-solid fa-hand-holding-heart text-[10px] block mb-1 text-emerald-400"></i>
                        Volunteer Corps
                    </a>
                    <a href="login.php?quick_login=citizen@example.com" class="col-span-2 sm:col-span-3 px-2 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-300 text-xs font-bold text-center transition-all hover:scale-[1.02] flex items-center justify-center gap-2">
                        <i class="fa-solid fa-user text-xs text-slate-400"></i>
                        <span>Public Citizen (Standard Civilian)</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Right Side: Login Form Card -->
        <div class="lg:col-span-6 max-w-md mx-auto w-full">
            <div class="bg-[#11192e] backdrop-blur-xl border border-[#243049] rounded-3xl p-6 sm:p-8 shadow-2xl">
                
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg shadow-indigo-500/30">
                        <i class="fa-solid fa-lock text-white text-lg"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-white">Sign In to Command Center</h2>
                        <p class="text-xs text-slate-400">Enter your credentials or use 1-click test drive</p>
                    </div>
                </div>

                <?php if ($activeLoggedInUser): ?>
                    <div class="mb-5 p-3 rounded-2xl bg-indigo-500/10 border border-indigo-500/30 text-indigo-300 text-xs flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                        <div class="flex items-center gap-2 overflow-hidden">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse shrink-0"></span>
                            <div class="truncate">
                                <span class="text-slate-400">Signed in as:</span> <strong class="text-white"><?= htmlspecialchars($activeLoggedInUser['name']) ?></strong> <span class="text-indigo-400 font-semibold">(<?= htmlspecialchars($activeLoggedInUser['role_name'] ?? $activeLoggedInUser['role_slug']) ?>)</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <a href="<?= htmlspecialchars(getRoleHomeUrl($activeLoggedInUser)) ?>" class="px-2.5 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-[11px] transition-colors">
                                Dashboard &rarr;
                            </a>
                            <a href="logout.php" class="px-2.5 py-1 rounded-lg bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 border border-rose-500/30 font-bold text-[11px] transition-colors">
                                Sign Out
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="mb-5 p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs flex items-start gap-2.5">
                        <i class="fa-solid fa-triangle-exclamation text-rose-400 text-sm mt-0.5 shrink-0"></i>
                        <div><?= htmlspecialchars($error) ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($flash): ?>
                    <?php 
                    $fBg = match($flash['type']) {
                        'success' => 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400',
                        'info' => 'bg-blue-500/10 border-blue-500/30 text-blue-400',
                        default => 'bg-rose-500/10 border-rose-500/30 text-rose-400'
                    };
                    $fIcon = match($flash['type']) {
                        'success' => 'fa-circle-check text-emerald-400',
                        'info' => 'fa-circle-info text-blue-400',
                        default => 'fa-triangle-exclamation text-rose-400'
                    };
                    ?>
                    <div class="mb-5 p-3.5 rounded-xl border <?= $fBg ?> text-xs flex items-start gap-2.5">
                        <i class="fa-solid <?= $fIcon ?> text-sm mt-0.5 shrink-0"></i>
                        <div><?= htmlspecialchars($flash['message']) ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                    <!-- Email Field -->
                    <div>
                        <label for="email" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Email Address</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                                <i class="fa-regular fa-envelope text-sm"></i>
                            </div>
                            <input type="email" id="email" name="email" required
                                   value="<?= htmlspecialchars($_POST['email'] ?? 'superadmin@system.local') ?>"
                                   placeholder="name@disaster.local"
                                   class="w-full pl-10 pr-4 py-2.5 bg-[#0a0f1d] border border-[#243049] rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition-colors">
                        </div>
                    </div>

                    <!-- Password Field -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="password" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Password</label>
                            <span class="text-[11px] text-slate-500">Default: <strong class="text-indigo-300 font-mono">admin123</strong></span>
                        </div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                                <i class="fa-solid fa-key text-sm"></i>
                            </div>
                            <input type="password" id="password" name="password" required
                                   value="admin123"
                                   placeholder="••••••••"
                                   class="w-full pl-10 pr-10 py-2.5 bg-[#0a0f1d] border border-[#243049] rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition-colors">
                            <button type="button" onclick="togglePasswordVisibility()" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-500 hover:text-slate-300">
                                <i id="passIcon" class="fa-regular fa-eye text-sm"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="w-full mt-2 py-3 px-4 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-bold text-sm shadow-lg shadow-indigo-600/30 transition-all flex items-center justify-center gap-2">
                        <span>AUTHENTICATE SYSTEM</span>
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </button>
                </form>

                <!-- Credentials Info & Public SOS Trigger -->
                <div class="mt-6 pt-4 border-t border-[#243049] text-center space-y-2.5">
                    <p class="text-xs text-slate-400">
                        Default Password: <span class="font-mono text-indigo-300 font-bold">admin123</span>
                    </p>
                    <a href="citizen.php" class="block w-full py-2.5 px-4 rounded-xl bg-rose-600/20 hover:bg-rose-600/30 border border-rose-500/40 text-rose-300 text-xs font-bold transition-all shadow-md shadow-rose-950/40">
                        <i class="fa-solid fa-triangle-exclamation mr-1.5 animate-pulse text-rose-400"></i>
                        Are you in danger? Transmit Public SOS Beacon →
                    </a>
                </div>

            </div>
        </div>

    </div>

    <script>
        function togglePasswordVisibility() {
            const passInput = document.getElementById('password');
            const passIcon = document.getElementById('passIcon');
            if (passInput.type === 'password') {
                passInput.type = 'text';
                passIcon.classList.remove('fa-eye');
                passIcon.classList.add('fa-eye-slash');
            } else {
                passInput.type = 'password';
                passIcon.classList.remove('fa-eye-slash');
                passIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
