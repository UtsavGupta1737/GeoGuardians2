<?php
// user/profile.php - User Account Profile & Security Settings
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

<div class="flex-1 flex flex-col min-w-0 bg-slate-950">
    <?php require_once __DIR__ . '/navbar.php'; ?>

    <main class="flex-1 p-4 sm:p-8 max-w-4xl w-full mx-auto space-y-6">
        
        <div>
            <h2 class="text-2xl font-extrabold text-white">Account Settings & Profile</h2>
            <p class="text-xs text-slate-400 mt-1">Manage your identity, personal contact information, and security credentials</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
            
            <!-- Left: Avatar Card -->
            <div class="md:col-span-4 glass-panel p-6 rounded-3xl flex flex-col items-center text-center space-y-4">
                <img src="<?= htmlspecialchars($currentUser['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($currentUser['name']) . '&background=6366f1&color=fff') ?>" 
                     alt="Avatar" class="w-24 h-24 rounded-3xl object-cover ring-4 ring-indigo-500/30 shadow-xl">
                <div>
                    <h3 class="text-base font-bold text-white"><?= htmlspecialchars($currentUser['name']) ?></h3>
                    <p class="text-xs text-slate-400"><?= htmlspecialchars($currentUser['email']) ?></p>
                </div>
                <div class="w-full pt-3 border-t border-slate-800 space-y-2 text-xs">
                    <div class="flex items-center justify-between text-slate-400">
                        <span>Role:</span>
                        <strong class="text-indigo-300"><?= htmlspecialchars($currentUser['role_name']) ?></strong>
                    </div>
                    <div class="flex items-center justify-between text-slate-400">
                        <span>Account Status:</span>
                        <span class="text-emerald-400 font-bold uppercase text-[10px]">Active</span>
                    </div>
                    <div class="flex items-center justify-between text-slate-400">
                        <span>Joined:</span>
                        <span class="text-slate-300"><?= date('M d, Y', strtotime($currentUser['created_at'])) ?></span>
                    </div>
                </div>
            </div>

            <!-- Right: Forms -->
            <div class="md:col-span-8 space-y-6">
                
                <!-- Profile Info Form -->
                <div class="glass-panel p-6 rounded-3xl space-y-4">
                    <div class="flex items-center gap-3 pb-3 border-b border-slate-800">
                        <div class="w-8 h-8 rounded-xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center text-sm">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <h3 class="text-sm font-bold text-white">Personal Information</h3>
                    </div>

                    <form method="POST" action="profile.php" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="update_profile">

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Full Name *</label>
                            <input type="text" name="name" value="<?= htmlspecialchars($currentUser['name']) ?>" required 
                                   class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Email Address</label>
                            <input type="email" value="<?= htmlspecialchars($currentUser['email']) ?>" disabled 
                                   class="w-full px-3.5 py-2.5 bg-slate-900 border border-slate-800 rounded-xl text-xs text-slate-500 cursor-not-allowed">
                            <p class="text-[10px] text-slate-500 mt-1">Email is managed by the system administrator.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Phone Number</label>
                            <input type="text" name="phone" value="<?= htmlspecialchars($currentUser['phone'] ?? '') ?>" placeholder="+1 (555) 000-0000" 
                                   class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Avatar Image URL</label>
                            <input type="url" name="avatar" value="<?= htmlspecialchars($currentUser['avatar'] ?? '') ?>" placeholder="https://example.com/avatar.jpg" 
                                   class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
                        </div>

                        <div class="flex justify-end pt-2">
                            <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-lg shadow-indigo-600/30 transition-all">
                                Save Profile Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Password Change Form -->
                <div class="glass-panel p-6 rounded-3xl space-y-4">
                    <div class="flex items-center gap-3 pb-3 border-b border-slate-800">
                        <div class="w-8 h-8 rounded-xl bg-purple-500/20 text-purple-400 flex items-center justify-center text-sm">
                            <i class="fa-solid fa-lock"></i>
                        </div>
                        <h3 class="text-sm font-bold text-white">Security & Password Update</h3>
                    </div>

                    <form method="POST" action="profile.php" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="change_password">

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Current Password *</label>
                            <input type="password" name="current_password" required placeholder="••••••••" 
                                   class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">New Password *</label>
                                <input type="password" name="new_password" required minlength="6" placeholder="••••••••" 
                                       class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Confirm New Password *</label>
                                <input type="password" name="confirm_password" required minlength="6" placeholder="••••••••" 
                                       class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
                            </div>
                        </div>

                        <div class="flex justify-end pt-2">
                            <button type="submit" class="px-5 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold shadow-lg shadow-purple-600/30 transition-all">
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
