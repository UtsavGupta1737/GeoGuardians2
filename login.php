<?php
// login.php - DisasterSafe Unified Multi-Role Authentication Portal (Government Theme)
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
<html lang="en" class="h-full bg-[#f8fafc]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | DisasterSafe Command Center</title>
    
    <!-- Google Fonts: Inter & JetBrains Mono -->
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
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace']
                    }
                }
            }
        }
    </script>
</head>
<body class="h-full font-sans bg-[#f8fafc] text-slate-800 antialiased flex items-center justify-center p-4 sm:p-6 lg:p-8 relative overflow-x-hidden">

    <!-- Background Decorative Glows -->
    <div class="absolute top-0 left-1/4 w-96 h-96 bg-blue-100/60 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute bottom-0 right-1/4 w-96 h-96 bg-rose-100/60 rounded-full blur-3xl pointer-events-none"></div>

    <div class="w-full max-w-5xl grid lg:grid-cols-12 gap-8 items-center relative z-10">
        
        <!-- Left Side: System Introduction & 1-Click Test Drive -->
        <div class="lg:col-span-6 space-y-6 text-center lg:text-left">
            
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-blue-50 border border-blue-200 text-blue-800 text-xs font-bold shadow-2xs">
                <i class="fa-solid fa-shield-halved text-xs text-[#1d63d8]"></i>
                <span>Disaster Management &amp; Multi-Agency Suite</span>
            </div>

            <div class="flex items-center justify-center lg:justify-start gap-3.5">
                <div class="w-12 h-12 rounded-2xl bg-[#0f172a] text-white flex items-center justify-center font-black text-2xl shadow-sm">
                    <i class="fa-solid fa-shield-halved text-white text-xl"></i>
                </div>
                <div>
                    <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">Disaster<span class="text-[#1d63d8]">Safe</span></h1>
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-widest mono">Government Crisis Command</span>
                </div>
            </div>

            <p class="text-sm text-slate-600 leading-relaxed max-w-lg mx-auto lg:mx-0">
                Centralized crisis coordination platform linking <strong>Superadmin Command</strong>, <strong>Police &amp; NDRF Dispatch</strong>, and <strong>Volunteer Relief Corps</strong> with real-time geospatial radar tracking.
            </p>

            <!-- 1-Click Demo Accounts Grid (7 Specialized Roles) -->
            <div class="p-5 rounded-3xl bg-white border border-slate-200 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-extrabold text-slate-700 uppercase tracking-wider flex items-center gap-1.5 mono">
                        <i class="fa-solid fa-bolt text-amber-500"></i>
                        1-Click Test Drive (7 Role Hierarchy)
                    </span>
                    <span class="text-[10px] text-slate-400 font-semibold mono">Instant Access</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                    <a href="login.php?quick_login=superadmin@system.local" class="px-2.5 py-2.5 rounded-2xl bg-purple-50 hover:bg-purple-100 border border-purple-200 text-purple-900 text-xs font-bold text-center transition-all hover:scale-[1.02] shadow-2xs">
                        <i class="fa-solid fa-crown text-xs block mb-1 text-purple-600"></i>
                        Superadmin
                    </a>
                    <a href="login.php?quick_login=ndrf.commander@disaster.local" class="px-2.5 py-2.5 rounded-2xl bg-orange-50 hover:bg-orange-100 border border-orange-200 text-orange-900 text-xs font-bold text-center transition-all hover:scale-[1.02] shadow-2xs">
                        <i class="fa-solid fa-truck-monster text-xs block mb-1 text-orange-600"></i>
                        NDRF Force
                    </a>
                    <a href="login.php?quick_login=police.command@disaster.local" class="px-2.5 py-2.5 rounded-2xl bg-blue-50 hover:bg-blue-100 border border-blue-200 text-blue-900 text-xs font-bold text-center transition-all hover:scale-[1.02] shadow-2xs">
                        <i class="fa-solid fa-person-military-pointing text-xs block mb-1 text-blue-600"></i>
                        Police Command
                    </a>
                    <a href="login.php?quick_login=fire.chief@disaster.local" class="px-2.5 py-2.5 rounded-2xl bg-red-50 hover:bg-red-100 border border-red-200 text-red-900 text-xs font-bold text-center transition-all hover:scale-[1.02] shadow-2xs">
                        <i class="fa-solid fa-fire-extinguisher text-xs block mb-1 text-red-600"></i>
                        Fire &amp; Rescue
                    </a>
                    <a href="login.php?quick_login=medical.ems@disaster.local" class="px-2.5 py-2.5 rounded-2xl bg-teal-50 hover:bg-teal-100 border border-teal-200 text-teal-900 text-xs font-bold text-center transition-all hover:scale-[1.02] shadow-2xs">
                        <i class="fa-solid fa-heart-pulse text-xs block mb-1 text-teal-600"></i>
                        Medical / EMS
                    </a>
                    <a href="login.php?quick_login=volunteer@disaster.local" class="px-2.5 py-2.5 rounded-2xl bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-900 text-xs font-bold text-center transition-all hover:scale-[1.02] shadow-2xs">
                        <i class="fa-solid fa-hand-holding-heart text-xs block mb-1 text-emerald-600"></i>
                        Volunteer Corps
                    </a>
                    <a href="login.php?quick_login=citizen@example.com" class="col-span-2 sm:col-span-3 px-3 py-2.5 rounded-2xl bg-slate-100 hover:bg-slate-200 border border-slate-200 text-slate-800 text-xs font-bold text-center transition-all hover:scale-[1.02] flex items-center justify-center gap-2 shadow-2xs">
                        <i class="fa-solid fa-user text-xs text-slate-600"></i>
                        <span>Public Citizen (Standard Civilian)</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Right Side: Login Form Card -->
        <div class="lg:col-span-6 max-w-md mx-auto w-full">
            <div class="bg-white border border-slate-200 rounded-3xl p-6 sm:p-8 shadow-xl">
                
                <div class="flex items-center gap-3.5 mb-6">
                    <div class="w-11 h-11 rounded-2xl bg-blue-50 border border-blue-200 text-[#1d63d8] flex items-center justify-center font-bold text-lg shadow-2xs">
                        <i class="fa-solid fa-lock text-base"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-extrabold text-slate-900 tracking-tight">Command Sign In</h2>
                        <p class="text-xs text-slate-500 font-medium">Enter credentials or use 1-click test drive</p>
                    </div>
                </div>

                <?php if ($activeLoggedInUser): ?>
                    <div class="mb-5 p-3.5 rounded-2xl bg-blue-50 border border-blue-200 text-blue-900 text-xs flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 shadow-2xs">
                        <div class="flex items-center gap-2 overflow-hidden">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse shrink-0"></span>
                            <div class="truncate">
                                <span class="text-slate-500">Signed in as:</span> <strong class="text-slate-900"><?= htmlspecialchars($activeLoggedInUser['name']) ?></strong> <span class="text-blue-700 font-bold mono">(<?= htmlspecialchars($activeLoggedInUser['role_name'] ?? $activeLoggedInUser['role_slug']) ?>)</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <a href="<?= htmlspecialchars(getRoleHomeUrl($activeLoggedInUser)) ?>" class="px-3 py-1 rounded-xl bg-[#1d63d8] hover:bg-[#1553c7] text-white font-bold text-[11px] transition-colors shadow-2xs">
                                Dashboard &rarr;
                            </a>
                            <a href="logout.php" class="px-3 py-1 rounded-xl bg-red-100 hover:bg-red-200 text-red-800 font-bold text-[11px] transition-colors">
                                Sign Out
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="mb-5 p-3.5 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-xs flex items-start gap-2.5">
                        <i class="fa-solid fa-triangle-exclamation text-red-600 text-sm mt-0.5 shrink-0"></i>
                        <div><?= htmlspecialchars($error) ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($flash): ?>
                    <?php 
                    $fBg = match($flash['type']) {
                        'success' => 'bg-emerald-50 border-emerald-200 text-emerald-800',
                        'info' => 'bg-blue-50 border-blue-200 text-blue-800',
                        default => 'bg-red-50 border-red-200 text-red-800'
                    };
                    $fIcon = match($flash['type']) {
                        'success' => 'fa-circle-check text-emerald-600',
                        'info' => 'fa-circle-info text-blue-600',
                        default => 'fa-triangle-exclamation text-red-600'
                    };
                    ?>
                    <div class="mb-5 p-3.5 rounded-2xl border <?= $fBg ?> text-xs flex items-start gap-2.5">
                        <i class="fa-solid <?= $fIcon ?> text-sm mt-0.5 shrink-0"></i>
                        <div><?= htmlspecialchars($flash['message']) ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                    <!-- Email Field -->
                    <div>
                        <label for="email" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Email Address</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i class="fa-regular fa-envelope text-sm"></i>
                            </div>
                            <input type="email" id="email" name="email" required
                                   value="<?= htmlspecialchars($_POST['email'] ?? 'superadmin@system.local') ?>"
                                   placeholder="name@disaster.local"
                                   class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-2xl text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:border-[#1d63d8] focus:bg-white transition-all font-medium">
                        </div>
                    </div>

                    <!-- Password Field -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="password" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mono">Password</label>
                            <span class="text-[11px] text-slate-500">Default: <strong class="text-blue-700 font-mono">admin123</strong></span>
                        </div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i class="fa-solid fa-key text-sm"></i>
                            </div>
                            <input type="password" id="password" name="password" required
                                   value="admin123"
                                   placeholder="••••••••"
                                   class="w-full pl-10 pr-10 py-2.5 bg-slate-50 border border-slate-200 rounded-2xl text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:border-[#1d63d8] focus:bg-white transition-all font-medium">
                            <button type="button" onclick="togglePasswordVisibility()" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 cursor-pointer">
                                <i id="passIcon" class="fa-regular fa-eye text-sm"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="w-full mt-2 py-3 px-4 rounded-2xl bg-[#1d63d8] hover:bg-[#1553c7] text-white font-extrabold text-xs uppercase tracking-wider shadow-sm transition-all flex items-center justify-center gap-2 cursor-pointer">
                        <span>AUTHENTICATE SYSTEM</span>
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </button>
                </form>

                <!-- Credentials Info & Public SOS Trigger -->
                <div class="mt-6 pt-4 border-t border-slate-100 text-center space-y-2.5">
                    <p class="text-xs text-slate-500">
                        Default System Password: <span class="font-mono text-blue-700 font-bold">admin123</span>
                    </p>
                    <a href="citizen.php" class="block w-full py-2.5 px-4 rounded-2xl bg-red-50 hover:bg-red-100 border border-red-200 text-red-700 text-xs font-bold transition-all shadow-2xs">
                        <i class="fa-solid fa-triangle-exclamation mr-1.5 animate-pulse text-red-600"></i>
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
