<?php
// users.php - User Management & Granular Access Control Module
define('PAGE_TITLE', 'User & Access Control');
require_once __DIR__ . '/auth.php';

$currentUser = requirePermissionGuard($pdo, 'manage_users');

// Handle Actions (POST requests)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrf)) {
        setFlash('error', 'Invalid security session. Please refresh and try again.');
        header("Location: users.php");
        exit;
    }

    // 1. CREATE USER
    if ($action === 'create_user') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role_id = (int) ($_POST['role_id'] ?? 0);
        $status = $_POST['status'] ?? 'active';
        $phone = trim($_POST['phone'] ?? '');
        $avatar = trim($_POST['avatar'] ?? '');

        if (empty($name) || empty($email) || empty($password) || empty($role_id)) {
            setFlash('error', 'Please fill in all required fields (Name, Email, Password, and Role).');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'Please provide a valid email address.');
        } else {
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $checkStmt->execute([$email]);
            if ($checkStmt->fetch()) {
                setFlash('error', "A user with email '{$email}' already exists.");
            } else {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                if (empty($avatar)) {
                    $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=6366f1&color=fff';
                }

                $insertStmt = $pdo->prepare("
                    INSERT INTO users (name, email, password, role_id, status, phone, avatar) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $insertStmt->execute([$name, $email, $hashedPassword, $role_id, $status, $phone, $avatar]);

                $roleName = $pdo->query("SELECT name FROM roles WHERE id = {$role_id}")->fetchColumn();
                logActivity($pdo, 'CREATE_USER', "Created new user '{$name}' ({$email}) with role '{$roleName}'");
                setFlash('success', "User '{$name}' created successfully with assigned role '{$roleName}'.");
            }
        }
        header("Location: users.php");
        exit;
    }

    // 2. UPDATE USER
    if ($action === 'update_user') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role_id = (int) ($_POST['role_id'] ?? 0);
        $status = $_POST['status'] ?? 'active';
        $phone = trim($_POST['phone'] ?? '');
        $avatar = trim($_POST['avatar'] ?? '');

        if (empty($userId) || empty($name) || empty($email) || empty($role_id)) {
            setFlash('error', 'Please fill in all required fields.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'Please provide a valid email address.');
        } else {
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $checkStmt->execute([$email, $userId]);
            if ($checkStmt->fetch()) {
                setFlash('error', "Email '{$email}' is already in use by another user.");
            } else {
                if (!empty($password)) {
                    $hashed = password_hash($password, PASSWORD_BCRYPT);
                    $updateStmt = $pdo->prepare("
                        UPDATE users 
                        SET name = ?, email = ?, password = ?, role_id = ?, status = ?, phone = ?, avatar = ?, updated_at = CURRENT_TIMESTAMP 
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$name, $email, $hashed, $role_id, $status, $phone, $avatar, $userId]);
                } else {
                    $updateStmt = $pdo->prepare("
                        UPDATE users 
                        SET name = ?, email = ?, role_id = ?, status = ?, phone = ?, avatar = ?, updated_at = CURRENT_TIMESTAMP 
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$name, $email, $role_id, $status, $phone, $avatar, $userId]);
                }

                $roleName = $pdo->query("SELECT name FROM roles WHERE id = {$role_id}")->fetchColumn();
                logActivity($pdo, 'UPDATE_USER', "Updated user ID #{$userId} ({$name}) - Assigned role: {$roleName}");
                setFlash('success', "User '{$name}' has been updated successfully.");
            }
        }
        header("Location: users.php");
        exit;
    }

    // 3. CUSTOMIZE USER PERMISSIONS
    if ($action === 'update_user_permissions') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $permissionMode = $_POST['permission_mode'] ?? 'role_default';
        $selectedPerms = $_POST['permissions'] ?? [];

        $targetUser = $pdo->query("SELECT name, email FROM users WHERE id = {$userId}")->fetch();
        if (!$targetUser) {
            setFlash('error', 'User not found.');
        } else {
            if ($permissionMode === 'role_default') {
                $pdo->prepare("UPDATE users SET custom_permissions = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$userId]);
                logActivity($pdo, 'RESET_USER_PERMISSIONS', "Reset user '{$targetUser['name']}' to default role permissions");
                setFlash('success', "Access permissions for '{$targetUser['name']}' reset to default role configuration.");
            } else {
                $permsJson = json_encode(array_values($selectedPerms));
                $pdo->prepare("UPDATE users SET custom_permissions = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$permsJson, $userId]);
                logActivity($pdo, 'CUSTOMIZE_USER_PERMISSIONS', "Updated granular custom permissions for '{$targetUser['name']}' (" . count($selectedPerms) . " active)");
                setFlash('success', "Custom permissions updated for '{$targetUser['name']}'. Modules will automatically reflect immediately!");
            }
        }
        header("Location: users.php");
        exit;
    }

    // 4. DELETE USER
    if ($action === 'delete_user') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        
        if ($userId === (int) $currentUser['id']) {
            setFlash('error', 'Security Violation: You cannot delete your own active account.');
        } else {
            $targetUser = $pdo->query("SELECT name, email FROM users WHERE id = {$userId}")->fetch();
            if ($targetUser) {
                $delStmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $delStmt->execute([$userId]);
                logActivity($pdo, 'DELETE_USER', "Deleted user '{$targetUser['name']}' ({$targetUser['email']}) [ID #{$userId}]");
                setFlash('success', "User '{$targetUser['name']}' was permanently removed.");
            } else {
                setFlash('error', 'User not found.');
            }
        }
        header("Location: users.php");
        exit;
    }

    // 5. TOGGLE STATUS
    if ($action === 'toggle_status') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $newStatus = $_POST['status'] === 'active' ? 'inactive' : 'active';

        if ($userId === (int) $currentUser['id']) {
            setFlash('error', 'You cannot deactivate your own account.');
        } else {
            $stmt = $pdo->prepare("UPDATE users SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$newStatus, $userId]);
            logActivity($pdo, 'TOGGLE_STATUS', "Changed user ID #{$userId} status to {$newStatus}");
            setFlash('success', "User status updated to " . ucfirst($newStatus) . ".");
        }
        header("Location: users.php");
        exit;
    }
}

// Search and Filter Params
$search = trim($_GET['search'] ?? '');
$filterRole = (int) ($_GET['role'] ?? 0);
$filterStatus = trim($_GET['status'] ?? '');

$query = "
    SELECT u.*, r.name as role_name, r.slug as role_slug, r.permissions as role_permissions 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $query .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if ($filterRole > 0) {
    $query .= " AND u.role_id = ?";
    $params[] = $filterRole;
}

if (!empty($filterStatus)) {
    $query .= " AND u.status = ?";
    $params[] = $filterStatus;
}

$query .= " ORDER BY u.id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$usersList = $stmt->fetchAll();

// Fetch all roles for dropdowns
$allRoles = $pdo->query("SELECT * FROM roles ORDER BY name ASC")->fetchAll();

$csrfToken = generateCsrfToken();

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div class="flex-1 flex flex-col min-w-0 bg-slate-950">
    <?php require_once __DIR__ . '/navbar.php'; ?>

    <main class="flex-1 p-4 sm:p-8 max-w-7xl w-full mx-auto space-y-6">
        
        <!-- Header & Action Row -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-extrabold text-white flex items-center gap-3">
                    <span>User & Access Control</span>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                        <?= count($usersList) ?> Users
                    </span>
                </h2>
                <p class="text-xs text-slate-400 mt-1">Manage user lifecycle, assign roles, and customize granular module permissions per user</p>
            </div>
            <button type="button" onclick="openCreateUserModal()" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition-all shadow-lg shadow-indigo-600/30 flex items-center justify-center gap-2">
                <i class="fa-solid fa-user-plus text-xs"></i>
                <span>Add New User</span>
            </button>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="glass-panel p-4 rounded-2xl">
            <form method="GET" action="users.php" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
                <div class="sm:col-span-5 relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                        <i class="fa-solid fa-magnifying-glass text-xs"></i>
                    </div>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name, email, phone..." 
                           class="w-full pl-9 pr-4 py-2 bg-slate-900/90 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition-colors">
                </div>

                <div class="sm:col-span-3">
                    <select name="role" class="w-full py-2 px-3 bg-slate-900/90 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                        <option value="0">All Roles</option>
                        <?php foreach ($allRoles as $role): ?>
                            <option value="<?= $role['id'] ?>" <?= $filterRole == $role['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($role['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <select name="status" class="w-full py-2 px-3 bg-slate-900/90 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                        <option value="">All Statuses</option>
                        <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="suspended" <?= $filterStatus === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                    </select>
                </div>

                <div class="sm:col-span-2 flex items-center gap-2">
                    <button type="submit" class="w-full py-2 px-3 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-xl border border-slate-700 transition-colors flex items-center justify-center gap-1.5">
                        <i class="fa-solid fa-filter text-[10px]"></i>
                        <span>Filter</span>
                    </button>
                    <?php if (!empty($search) || $filterRole > 0 || !empty($filterStatus)): ?>
                        <a href="users.php" class="p-2 bg-slate-800 hover:bg-rose-500/20 text-slate-400 hover:text-rose-400 rounded-xl border border-slate-700 transition-colors" title="Clear Filters">
                            <i class="fa-solid fa-rotate-left text-xs"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Users Table Card -->
        <div class="glass-panel rounded-3xl overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-800/80 bg-slate-900/40 text-slate-400 uppercase tracking-wider font-semibold">
                            <th class="py-3.5 px-4 sm:px-6">User Details</th>
                            <th class="py-3.5 px-4">Assigned Role</th>
                            <th class="py-3.5 px-4">Permission State</th>
                            <th class="py-3.5 px-4">Account Status</th>
                            <th class="py-3.5 px-4">Joined Date</th>
                            <th class="py-3.5 px-4 sm:px-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 text-slate-300">
                        <?php if (empty($usersList)): ?>
                            <tr>
                                <td colspan="6" class="py-12 text-center text-slate-500">
                                    <div class="w-12 h-12 rounded-2xl bg-slate-900 border border-slate-800 text-slate-600 flex items-center justify-center mx-auto mb-3 text-lg">
                                        <i class="fa-solid fa-user-slash"></i>
                                    </div>
                                    <p class="font-medium text-slate-400">No users found matching your criteria</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($usersList as $u): ?>
                                <?php
                                    $hasCustom = !empty($u['custom_permissions']);
                                    $effectivePerms = $hasCustom ? (json_decode($u['custom_permissions'], true) ?: []) : (json_decode($u['role_permissions'], true) ?: []);
                                    $roleColor = match($u['role_slug']) {
                                        'superadmin' => 'bg-purple-500/10 text-purple-400 border-purple-500/30',
                                        'police' => 'bg-blue-500/10 text-blue-400 border-blue-500/30',
                                        'volunteer' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
                                        'admin' => 'bg-cyan-500/10 text-cyan-400 border-cyan-500/30',
                                        default => 'bg-slate-800 text-slate-300 border-slate-700'
                                    };
                                ?>
                                <tr class="hover:bg-slate-800/30 transition-colors">
                                    
                                    <td class="py-4 px-4 sm:px-6">
                                        <div class="flex items-center gap-3.5">
                                            <img src="<?= htmlspecialchars($u['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($u['name']) . '&background=6366f1&color=fff') ?>" 
                                                 alt="Avatar" class="w-10 h-10 rounded-xl object-cover ring-1 ring-slate-700 shrink-0">
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <span class="font-bold text-white"><?= htmlspecialchars($u['name']) ?></span>
                                                    <?php if ((int)$u['id'] === (int)$currentUser['id']): ?>
                                                        <span class="px-1.5 py-0.2 text-[9px] font-bold rounded bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">YOU</span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="text-slate-400 font-mono text-[11px]"><?= htmlspecialchars($u['email']) ?></p>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="py-4 px-4">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold border <?= $roleColor ?>">
                                            <i class="fa-solid <?= $u['role_slug'] === 'superadmin' ? 'fa-crown text-[10px]' : ($u['role_slug'] === 'police' ? 'fa-shield-halved text-[10px]' : ($u['role_slug'] === 'volunteer' ? 'fa-hand-holding-heart text-[10px]' : 'fa-user text-[10px]')) ?>"></i>
                                            <span><?= htmlspecialchars($u['role_name']) ?></span>
                                        </span>
                                    </td>

                                    <td class="py-4 px-4">
                                        <?php if ($hasCustom): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/30">
                                                <i class="fa-solid fa-sliders text-[10px]"></i>
                                                <span>Custom (<?= count($effectivePerms) ?> Active)</span>
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-medium bg-slate-900 border border-slate-800 text-slate-400">
                                                <i class="fa-solid fa-link text-[10px]"></i>
                                                <span>Role Default (<?= count($effectivePerms) ?>)</span>
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="py-4 px-4">
                                        <form method="POST" action="users.php" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="status" value="<?= $u['status'] ?>">
                                            
                                            <?php if ($u['status'] === 'active'): ?>
                                                <button type="submit" title="Click to Deactivate" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500/20 transition-colors">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Active
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" title="Click to Activate" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20 hover:bg-amber-500/20 transition-colors">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span> <?= ucfirst(htmlspecialchars($u['status'])) ?>
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </td>

                                    <td class="py-4 px-4 text-slate-400 text-[11px]">
                                        <?= date('M d, Y', strtotime($u['created_at'])) ?>
                                    </td>

                                    <td class="py-4 px-4 sm:px-6 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button type="button" 
                                                    onclick='openPermissionsModal(<?= json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                                    class="p-2 rounded-lg bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border border-amber-500/20 transition-colors" title="Manage Permissions & Access Matrix">
                                                <i class="fa-solid fa-key text-xs"></i>
                                            </button>

                                            <button type="button" 
                                                    onclick='openEditUserModal(<?= json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                                    class="p-2 rounded-lg bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-400 border border-indigo-500/20 transition-colors" title="Edit User Details">
                                                <i class="fa-solid fa-pen-to-square text-xs"></i>
                                            </button>

                                            <?php if ((int)$u['id'] !== (int)$currentUser['id']): ?>
                                                <button type="button" 
                                                        onclick="confirmDeleteUser(<?= $u['id'] ?>, '<?= addslashes(htmlspecialchars($u['name'])) ?>')"
                                                        class="p-2 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 transition-colors" title="Delete User">
                                                    <i class="fa-solid fa-trash-can text-xs"></i>
                                                </button>
                                            <?php else: ?>
                                                <span class="p-2 rounded-lg bg-slate-800 text-slate-600 cursor-not-allowed opacity-50">
                                                    <i class="fa-solid fa-lock text-xs"></i>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<!-- MANAGE USER PERMISSIONS MODAL -->
<div id="permissionsModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-2xl w-full p-6 sm:p-8 shadow-2xl relative max-h-[90vh] flex flex-col animate-in fade-in zoom-in duration-200">
        
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-800 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center">
                    <i class="fa-solid fa-key text-base"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-white">Direct Permission Access Matrix</h3>
                    <p class="text-xs text-slate-400">Grant or revoke module visibility for: <strong class="text-white" id="perm_user_name"></strong></p>
                </div>
            </div>
            <button type="button" onclick="closePermissionsModal()" class="text-slate-400 hover:text-white p-2">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="users.php" class="space-y-5 overflow-y-auto pr-1 flex-1">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="update_user_permissions">
            <input type="hidden" name="user_id" id="perm_user_id">

            <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800 space-y-3">
                <p class="text-xs font-bold text-slate-300 uppercase tracking-wider">Access Mode</p>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="flex items-start gap-3 p-3 rounded-xl bg-slate-900 border border-slate-800 cursor-pointer hover:border-indigo-500/40 transition-colors">
                        <input type="radio" name="permission_mode" value="role_default" id="mode_role_default" class="mt-0.5 text-indigo-600 focus:ring-indigo-500" onchange="toggleCustomPerms(false)">
                        <div>
                            <p class="text-xs font-bold text-white">Inherit Role Permissions</p>
                            <p class="text-[11px] text-slate-400">Automatically sync with assigned role (<span id="perm_role_name"></span>)</p>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3 rounded-xl bg-slate-900 border border-slate-800 cursor-pointer hover:border-amber-500/40 transition-colors">
                        <input type="radio" name="permission_mode" value="custom_override" id="mode_custom_override" class="mt-0.5 text-amber-500 focus:ring-amber-500" onchange="toggleCustomPerms(true)">
                        <div>
                            <p class="text-xs font-bold text-amber-400">Custom Permission Override</p>
                            <p class="text-[11px] text-slate-400">Grant or revoke specific modules individually for this user</p>
                        </div>
                    </label>
                </div>
            </div>

            <div id="customPermsContainer" class="space-y-4">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-bold text-slate-300 uppercase tracking-wider">Granted Module Access</p>
                    <button type="button" onclick="selectAllUserPerms()" class="text-xs font-semibold text-amber-400 hover:text-amber-300">Toggle All</button>
                </div>

                <div class="space-y-4 bg-slate-950 p-4 rounded-2xl border border-slate-800">
                    <?php foreach ($SYSTEM_PERMISSIONS as $category => $perms): ?>
                        <div>
                            <p class="text-[11px] font-bold text-indigo-400 uppercase tracking-wider mb-2"><?= htmlspecialchars($category) ?></p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                <?php foreach ($perms as $pKey => $pDesc): ?>
                                    <label class="flex items-start gap-2.5 p-2.5 rounded-xl bg-slate-900/80 border border-slate-800/80 hover:border-amber-500/40 cursor-pointer transition-colors">
                                        <input type="checkbox" name="permissions[]" value="<?= htmlspecialchars($pKey) ?>" id="user_perm_<?= $pKey ?>" class="user-perm-check mt-0.5 rounded bg-slate-800 border-slate-700 text-amber-500 focus:ring-amber-500">
                                        <div class="min-w-0">
                                            <p class="text-xs font-semibold text-white"><?= htmlspecialchars(str_replace('_', ' ', $pKey)) ?></p>
                                            <p class="text-[10px] text-slate-400 leading-tight"><?= htmlspecialchars($pDesc) ?></p>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800 shrink-0">
                <button type="button" onclick="closePermissionsModal()" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold shadow-lg shadow-amber-600/30 transition-all">
                    Save Permission Access
                </button>
            </div>
        </form>

    </div>
</div>

<!-- CREATE USER MODAL -->
<div id="createUserModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl relative animate-in fade-in zoom-in duration-200">
        
        <div class="flex items-center justify-between pb-4 mb-5 border-b border-slate-800">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center">
                    <i class="fa-solid fa-user-plus text-base"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-white">Create New User</h3>
                    <p class="text-xs text-slate-400">Add user and assign access role</p>
                </div>
            </div>
            <button type="button" onclick="closeCreateUserModal()" class="text-slate-400 hover:text-white p-2">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="users.php" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="create_user">

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Full Name *</label>
                <input type="text" name="name" required placeholder="e.g. John Doe" 
                       class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Email Address *</label>
                <input type="email" name="email" required placeholder="e.g. john@company.com" 
                       class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Password *</label>
                    <input type="password" name="password" required placeholder="••••••••" 
                           class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Phone Number</label>
                    <input type="text" name="phone" placeholder="+1 (555) 000-0000" 
                           class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Assign Role *</label>
                    <select name="role_id" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                        <?php foreach ($allRoles as $role): ?>
                            <option value="<?= $role['id'] ?>" <?= $role['slug'] === 'user' ? 'selected' : '' ?>>
                                <?= htmlspecialchars($role['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Status</label>
                    <select name="status" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Avatar Image URL (Optional)</label>
                <input type="url" name="avatar" placeholder="https://example.com/avatar.jpg" 
                       class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800 mt-6">
                <button type="button" onclick="closeCreateUserModal()" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-lg shadow-indigo-600/30 transition-all">
                    Create User & Assign Role
                </button>
            </div>
        </form>

    </div>
</div>

<!-- EDIT USER MODAL -->
<div id="editUserModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl relative animate-in fade-in zoom-in duration-200">
        
        <div class="flex items-center justify-between pb-4 mb-5 border-b border-slate-800">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center">
                    <i class="fa-solid fa-user-pen text-base"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-white">Edit User Profile</h3>
                    <p class="text-xs text-slate-400">Modify details, password, and assign roles</p>
                </div>
            </div>
            <button type="button" onclick="closeEditUserModal()" class="text-slate-400 hover:text-white p-2">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="users.php" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="user_id" id="edit_user_id">

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Full Name *</label>
                <input type="text" name="name" id="edit_name" required 
                       class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Email Address *</label>
                <input type="email" name="email" id="edit_email" required 
                       class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">New Password (Leave blank to keep)</label>
                    <input type="password" name="password" placeholder="••••••••" 
                           class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Phone Number</label>
                    <input type="text" name="phone" id="edit_phone" placeholder="+1 (555) 000-0000" 
                           class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Assigned Role *</label>
                    <select name="role_id" id="edit_role_id" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                        <?php foreach ($allRoles as $role): ?>
                            <option value="<?= $role['id'] ?>">
                                <?= htmlspecialchars($role['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Status</label>
                    <select name="status" id="edit_status" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Avatar Image URL</label>
                <input type="url" name="avatar" id="edit_avatar" placeholder="https://example.com/avatar.jpg" 
                       class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800 mt-6">
                <button type="button" onclick="closeEditUserModal()" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-lg shadow-indigo-600/30 transition-all">
                    Save Changes
                </button>
            </div>
        </form>

    </div>
</div>

<!-- HIDDEN DELETE FORM -->
<form id="deleteUserForm" method="POST" action="users.php" class="hidden">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <input type="hidden" name="action" value="delete_user">
    <input type="hidden" name="user_id" id="delete_user_id">
</form>

<script>
function openCreateUserModal() {
    document.getElementById('createUserModal').classList.remove('hidden');
}
function closeCreateUserModal() {
    document.getElementById('createUserModal').classList.add('hidden');
}

function openEditUserModal(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_name').value = user.name || '';
    document.getElementById('edit_email').value = user.email || '';
    document.getElementById('edit_phone').value = user.phone || '';
    document.getElementById('edit_role_id').value = user.role_id || '';
    document.getElementById('edit_status').value = user.status || 'active';
    document.getElementById('edit_avatar').value = user.avatar || '';

    document.getElementById('editUserModal').classList.remove('hidden');
}
function closeEditUserModal() {
    document.getElementById('editUserModal').classList.add('hidden');
}

function openPermissionsModal(user) {
    document.getElementById('perm_user_id').value = user.id;
    document.getElementById('perm_user_name').textContent = user.name + ' (' + user.email + ')';
    document.getElementById('perm_role_name').textContent = user.role_name;

    const hasCustom = user.custom_permissions && user.custom_permissions.trim() !== '';
    if (hasCustom) {
        document.getElementById('mode_custom_override').checked = true;
        toggleCustomPerms(true);
    } else {
        document.getElementById('mode_role_default').checked = true;
        toggleCustomPerms(false);
    }

    document.querySelectorAll('.user-perm-check').forEach(c => c.checked = false);

    let perms = [];
    try {
        if (hasCustom) {
            perms = JSON.parse(user.custom_permissions);
        } else if (user.role_permissions) {
            perms = JSON.parse(user.role_permissions);
        }
    } catch (e) {}

    if (Array.isArray(perms)) {
        perms.forEach(p => {
            const el = document.getElementById('user_perm_' + p);
            if (el) el.checked = true;
        });
    }

    document.getElementById('permissionsModal').classList.remove('hidden');
}
function closePermissionsModal() {
    document.getElementById('permissionsModal').classList.add('hidden');
}

function toggleCustomPerms(isCustom) {
    const container = document.getElementById('customPermsContainer');
    if (isCustom) {
        container.style.opacity = '1';
        container.style.pointerEvents = 'auto';
    } else {
        container.style.opacity = '0.4';
        container.style.pointerEvents = 'none';
    }
}

function selectAllUserPerms() {
    const checks = document.querySelectorAll('.user-perm-check');
    const allChecked = Array.from(checks).every(c => c.checked);
    checks.forEach(c => c.checked = !allChecked);
}

function confirmDeleteUser(userId, userName) {
    Swal.fire({
        title: 'Delete User?',
        text: `Are you sure you want to permanently delete '${userName}'? This action cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        cancelButtonColor: '#334155',
        confirmButtonText: 'Yes, delete user',
        cancelButtonText: 'Cancel',
        background: '#1e293b',
        color: '#f8fafc'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('deleteUserForm').submit();
        }
    });
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
