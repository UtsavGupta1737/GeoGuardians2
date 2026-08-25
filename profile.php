<?php
// profile.php - User Account Profile & Security Settings (Government Theme)
define('PAGE_TITLE', 'My Profile & Account Settings');
require_once __DIR__ . '/auth.php';

requireLogin();
$currentUser = getCurrentUser($pdo);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrf)) {
        setFlash('error', 'Security session expired. Please retry.');
        header("Location: profile.php");
        exit;
    }

    // 1. UPDATE PROFILE DETAILS
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $avatar = trim($_POST['avatar'] ?? '');

        if (empty($name)) {
            setFlash('error', 'Your name cannot be blank.');
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, avatar = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$name, $phone, $avatar, $currentUser['id']]);

            $_SESSION['user_name'] = $name;
            logActivity($pdo, 'UPDATE_PROFILE', "User {$name} updated account profile details");
            setFlash('success', 'Profile details updated successfully.');
        }
        header("Location: profile.php");
        exit;
    }

    // 2. CHANGE PASSWORD
    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            setFlash('error', 'Please fill in all password fields.');
        } elseif ($newPassword !== $confirmPassword) {
            setFlash('error', 'New password and confirmation do not match.');
        } elseif (strlen($newPassword) < 6) {
            setFlash('error', 'New password must be at least 6 characters long.');
        } else {
            $userDb = $pdo->query("SELECT password FROM users WHERE id = {$currentUser['id']}")->fetch();
            if (!password_verify($currentPassword, $userDb['password'])) {
                setFlash('error', 'Your current password is incorrect.');
            } else {
                $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$newHash, $currentUser['id']]);

                logActivity($pdo, 'CHANGE_PASSWORD', "User {$currentUser['name']} updated account password");
                setFlash('success', 'Password updated successfully. Please use your new password next time you sign in.');
            }
        }
        header("Location: profile.php");
        exit;
    }
}

$csrfToken = generateCsrfToken();

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div class="flex-1 flex flex-col min-w-0 bg-[#f8fafc] min-h-screen overflow-y-auto">
    <?php require_once __DIR__ . '/navbar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-4xl w-full mx-auto space-y-6">
        
        <div>
            <h2 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">Account Settings &amp; Profile</h2>
            <p class="text-xs text-slate-500 font-medium mt-1">Manage your identity, personal contact information, and security credentials</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
            
            <!-- Left: Role Identity Card -->
            <div class="md:col-span-4 bg-white p-6 rounded-3xl border border-slate-200 shadow-xs flex flex-col items-center text-center space-y-4">
                <?php
                    $pRoleSlug = $currentUser['role_slug'] ?? 'user';
                    $pAvatarBg = match($pRoleSlug) {
                        'superadmin' => 'bg-purple-600 text-white',
                        'ndrf' => 'bg-orange-600 text-white',
                        'police' => 'bg-blue-600 text-white',
                        'fire' => 'bg-red-600 text-white',
                        'medical' => 'bg-teal-600 text-white',
                        'volunteer' => 'bg-emerald-600 text-white',
                        default => 'bg-slate-700 text-white'
                    };
                    $pRoleIcon = match($pRoleSlug) {
                        'superadmin' => 'fa-crown',
                        'ndrf' => 'fa-truck-monster',
                        'police' => 'fa-shield-halved',
                        'fire' => 'fa-fire-extinguisher',
                        'medical' => 'fa-heart-pulse',
                        'volunteer' => 'fa-hand-holding-heart',
                        default => 'fa-user'
                    };
                ?>
                <div class="w-20 h-20 rounded-3xl <?= $pAvatarBg ?> flex items-center justify-center text-3xl shadow-md ring-4 ring-slate-100">
                    <i class="fa-solid <?= $pRoleIcon ?>"></i>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900"><?= htmlspecialchars($currentUser['name']) ?></h3>
                    <p class="text-xs text-slate-500 font-mono"><?= htmlspecialchars($currentUser['email']) ?></p>
                </div>
                <div class="w-full pt-3 border-t border-slate-100 space-y-2 text-xs">
                    <div class="flex items-center justify-between text-slate-500">
                        <span>Role:</span>
                        <strong class="text-blue-700 font-bold"><?= htmlspecialchars($currentUser['role_name']) ?></strong>
                    </div>
                    <div class="flex items-center justify-between text-slate-500">
                        <span>Account Status:</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 mono">Active</span>
                    </div>
                    <div class="flex items-center justify-between text-slate-500">
                        <span>Joined:</span>
                        <span class="text-slate-700 font-mono"><?= date('M d, Y', strtotime($currentUser['created_at'])) ?></span>
                    </div>
                </div>
            </div>

            <!-- Right: Forms -->
            <div class="md:col-span-8 space-y-6">
                
                <!-- Profile Info Form -->
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-4">
                    <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                        <div class="w-8 h-8 rounded-xl bg-blue-50 text-[#1d63d8] flex items-center justify-center text-sm">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <h3 class="text-sm font-extrabold text-slate-900">Personal Information</h3>
                    </div>

                    <form method="POST" action="profile.php" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="update_profile">

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Full Name *</label>
                            <input type="text" name="name" value="<?= htmlspecialchars($currentUser['name']) ?>" required 
                                   class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-[#1d63d8] focus:bg-white font-medium">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Email Address</label>
                            <input type="email" value="<?= htmlspecialchars($currentUser['email']) ?>" disabled 
                                   class="w-full px-3.5 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-xs text-slate-500 cursor-not-allowed font-mono">
                            <p class="text-[10px] text-slate-400 mt-1">Email is managed by the system administrator.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Phone Number</label>
                            <input type="text" name="phone" value="<?= htmlspecialchars($currentUser['phone'] ?? '') ?>" placeholder="+91 98765 43210" 
                                   class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-[#1d63d8] focus:bg-white font-mono font-medium">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Avatar Image URL</label>
                            <input type="url" name="avatar" value="<?= htmlspecialchars($currentUser['avatar'] ?? '') ?>" placeholder="https://example.com/avatar.jpg" 
                                   class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-[#1d63d8] focus:bg-white font-medium">
                        </div>

                        <div class="flex justify-end pt-2">
                            <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#1d63d8] hover:bg-[#1553c7] text-white text-xs font-bold shadow-sm transition-all cursor-pointer">
                                Save Profile Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Password Change Form -->
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-4">
                    <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                        <div class="w-8 h-8 rounded-xl bg-purple-50 text-purple-700 flex items-center justify-center text-sm">
                            <i class="fa-solid fa-lock"></i>
                        </div>
                        <h3 class="text-sm font-extrabold text-slate-900">Security &amp; Password Update</h3>
                    </div>

                    <form method="POST" action="profile.php" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="change_password">

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Current Password *</label>
                            <input type="password" name="current_password" required placeholder="••••••••" 
                                   class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-[#1d63d8] focus:bg-white font-medium">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">New Password *</label>
                                <input type="password" name="new_password" required minlength="6" placeholder="••••••••" 
                                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-[#1d63d8] focus:bg-white font-medium">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Confirm New Password *</label>
                                <input type="password" name="confirm_password" required minlength="6" placeholder="••••••••" 
                                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-[#1d63d8] focus:bg-white font-medium">
                            </div>
                        </div>

                        <div class="flex justify-end pt-2">
                            <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#7c3aed] hover:bg-[#6d28d9] text-white text-xs font-bold shadow-sm transition-all cursor-pointer">
                                Update Password
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>

    </main>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
