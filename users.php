<?php
// users.php - User Management & Granular Access Control Module (Government Theme)
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
                    $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=1d63d8&color=fff';
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

<div class="flex-1 flex flex-col min-w-0 bg-[#f8fafc] min-h-screen overflow-y-auto">
    <?php require_once __DIR__ . '/navbar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto w-full">
        
        <!-- Header & Action Row -->
        <section class="bg-white p-5 sm:p-6 rounded-3xl border border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">User &amp; Access Control</h2>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200 mono">
                        <?= count($usersList) ?> Users
                    </span>
                </div>
                <p class="text-xs text-slate-500 font-medium mt-1">Manage user lifecycle, assign roles, and customize granular module permissions per user</p>
            </div>
            <button type="button" onclick="openCreateUserModal()" class="px-4 py-2.5 rounded-2xl bg-[#1d63d8] hover:bg-[#1553c7] text-white text-xs font-bold transition-all shadow-sm flex items-center justify-center gap-2 cursor-pointer">
                <i class="fa-solid fa-user-plus text-xs"></i>
                <span>Add New User</span>
            </button>
        </section>

        <!-- Filter & Search Toolbar -->
        <div class="bg-white p-4 rounded-3xl border border-slate-200 shadow-xs">
            <form method="GET" action="users.php" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
                <div class="sm:col-span-5 relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <i class="fa-solid fa-magnifying-glass text-xs"></i>
                    </div>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name, email, phone..." 
                           class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-[#1d63d8] focus:bg-white transition-colors font-medium">
                </div>

                <div class="sm:col-span-3">
                    <select name="role" class="w-full py-2 px-3 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 font-bold focus:outline-none focus:border-[#1d63d8]">
                        <option value="0">All Roles</option>
                        <?php foreach ($allRoles as $role): ?>
                            <option value="<?= $role['id'] ?>" <?= $filterRole == $role['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($role['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <select name="status" class="w-full py-2 px-3 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 font-bold focus:outline-none focus:border-[#1d63d8]">
                        <option value="">All Statuses</option>
                        <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="suspended" <?= $filterStatus === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                    </select>
                </div>

                <div class="sm:col-span-2 flex items-center gap-2">
                    <button type="submit" class="w-full py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold rounded-xl border border-slate-200 transition-colors flex items-center justify-center gap-1.5 cursor-pointer">
                        <i class="fa-solid fa-filter text-[10px]"></i>
                        <span>Filter</span>
                    </button>
                    <?php if (!empty($search) || $filterRole > 0 || !empty($filterStatus)): ?>
                        <a href="users.php" class="p-2 bg-slate-100 hover:bg-red-50 text-slate-600 hover:text-red-600 rounded-xl border border-slate-200 transition-colors" title="Clear Filters">
                            <i class="fa-solid fa-rotate-left text-xs"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Users Table Card -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50/80 text-slate-500 uppercase tracking-wider font-extrabold text-[10px] mono">
                            <th class="py-3.5 px-4 sm:px-6">User Details</th>
                            <th class="py-3.5 px-4">Assigned Role</th>
                            <th class="py-3.5 px-4">Permission State</th>
                            <th class="py-3.5 px-4">Account Status</th>
                            <th class="py-3.5 px-4">Joined Date</th>
                            <th class="py-3.5 px-4 sm:px-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($usersList)): ?>
                            <tr>
                                <td colspan="6" class="py-12 text-center text-slate-500">
                                    <div class="w-12 h-12 rounded-2xl bg-slate-100 border border-slate-200 text-slate-400 flex items-center justify-center mx-auto mb-3 text-lg">
                                        <i class="fa-solid fa-user-slash"></i>
                                    </div>
                                    <p class="font-medium text-slate-500">No users found matching your criteria</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($usersList as $u): ?>
                                <?php
                                    $hasCustom = !empty($u['custom_permissions']);
                                    $effectivePerms = $hasCustom ? (json_decode($u['custom_permissions'], true) ?: []) : (json_decode($u['role_permissions'], true) ?: []);
                                    $roleColor = match($u['role_slug']) {
                                        'superadmin' => 'bg-purple-50 text-purple-800 border-purple-200',
                                        'police' => 'bg-blue-50 text-blue-800 border-blue-200',
                                        'volunteer' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                        'fire' => 'bg-red-50 text-red-800 border-red-200',
                                        'medical' => 'bg-teal-50 text-teal-800 border-teal-200',
                                        default => 'bg-slate-100 text-slate-800 border-slate-200'
                                    };
                                ?>
                                <tr class="hover:bg-slate-50 transition-colors">
                                    
                                    <td class="py-4 px-4 sm:px-6">
                                            <?php
                                                $uRoleSlug = $u['role_slug'] ?? 'user';
                                                $uAvatarBg = match($uRoleSlug) {
                                                    'superadmin' => 'bg-purple-600 text-white',
                                                    'ndrf' => 'bg-orange-600 text-white',
                                                    'police' => 'bg-blue-600 text-white',
                                                    'fire' => 'bg-red-600 text-white',
                                                    'medical' => 'bg-teal-600 text-white',
                                                    'volunteer' => 'bg-emerald-600 text-white',
                                                    default => 'bg-slate-700 text-white'
                                                };
                                                $uRoleIcon = match($uRoleSlug) {
                                                    'superadmin' => 'fa-crown',
                                                    'ndrf' => 'fa-truck-monster',
                                                    'police' => 'fa-shield-halved',
                                                    'fire' => 'fa-fire-extinguisher',
                                                    'medical' => 'fa-heart-pulse',
                                                    'volunteer' => 'fa-hand-holding-heart',
                                                    default => 'fa-user'
                                                };
                                            ?>
                                            <div class="w-10 h-10 rounded-2xl <?= $uAvatarBg ?> flex items-center justify-center font-bold text-sm shrink-0 shadow-2xs">
                                                <i class="fa-solid <?= $uRoleIcon ?>"></i>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <span class="font-extrabold text-slate-900 text-xs"><?= htmlspecialchars($u['name']) ?></span>
                                                    <?php if ((int)$u['id'] === (int)$currentUser['id']): ?>
                                                        <span class="px-1.5 py-0.2 text-[9px] font-bold rounded-full bg-blue-100 text-blue-800 border border-blue-200 mono">YOU</span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="text-slate-500 font-mono text-[11px]"><?= htmlspecialchars($u['email']) ?></p>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="py-4 px-4">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold border <?= $roleColor ?> mono">
                                            <i class="fa-solid <?= $u['role_slug'] === 'superadmin' ? 'fa-crown text-[10px]' : ($u['role_slug'] === 'police' ? 'fa-shield-halved text-[10px]' : ($u['role_slug'] === 'volunteer' ? 'fa-hand-holding-heart text-[10px]' : 'fa-user text-[10px]')) ?>"></i>
                                            <span><?= htmlspecialchars($u['role_name']) ?></span>
                                        </span>
                                    </td>

                                    <td class="py-4 px-4">
                                        <?php if ($hasCustom): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-800 border border-amber-200 mono">
                                                <i class="fa-solid fa-sliders text-[10px]"></i>
                                                <span>Custom (<?= count($effectivePerms) ?> Active)</span>
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-100 border border-slate-200 text-slate-600 mono">
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
                                                <button type="submit" title="Click to Deactivate" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 hover:bg-emerald-100 transition-colors cursor-pointer mono">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" title="Click to Activate" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-800 border border-amber-200 hover:bg-amber-100 transition-colors cursor-pointer mono">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> <?= ucfirst(htmlspecialchars($u['status'])) ?>
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </td>

                                    <td class="py-4 px-4 text-slate-500 text-[11px] font-mono">
                                        <?= date('M d, Y', strtotime($u['created_at'])) ?>
                                    </td>

                                    <td class="py-4 px-4 sm:px-6 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button type="button" 
                                                    onclick='openPermissionsModal(<?= json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                                    class="p-2 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 transition-colors cursor-pointer" title="Manage Permissions & Access Matrix">
                                                <i class="fa-solid fa-key text-xs"></i>
                                            </button>

                                            <button type="button" 
                                                    onclick='openEditUserModal(<?= json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                                    class="p-2 rounded-xl bg-blue-50 hover:bg-blue-100 text-[#1d63d8] border border-blue-200 transition-colors cursor-pointer" title="Edit User Details">
                                                <i class="fa-solid fa-pen-to-square text-xs"></i>
                                            </button>

                                            <?php if ((int)$u['id'] !== (int)$currentUser['id']): ?>
                                                <button type="button" 
                                                        onclick="confirmDeleteUser(<?= $u['id'] ?>, '<?= addslashes(htmlspecialchars($u['name'])) ?>')"
                                                        class="p-2 rounded-xl bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 transition-colors cursor-pointer" title="Delete User">
                                                    <i class="fa-solid fa-trash-can text-xs"></i>
                                                </button>
                                            <?php else: ?>
                                                <span class="p-2 rounded-xl bg-slate-100 text-slate-400 cursor-not-allowed opacity-50">
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
<div id="permissionsModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white border border-slate-200 rounded-3xl max-w-2xl w-full p-6 sm:p-8 shadow-2xl relative max-h-[90vh] flex flex-col">
        
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-100 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center">
                    <i class="fa-solid fa-key text-base"></i>
                </div>
                <div>
                    <h3 class="text-lg font-extrabold text-slate-900">Direct Permission Access Matrix</h3>
                    <p class="text-xs text-slate-500 font-medium">Grant or revoke module visibility for: <strong class="text-slate-900" id="perm_user_name"></strong></p>
                </div>
            </div>
            <button type="button" onclick="closePermissionsModal()" class="text-slate-400 hover:text-slate-800 p-2 cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="users.php" class="space-y-5 overflow-y-auto pr-1 flex-1">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="update_user_permissions">
            <input type="hidden" name="user_id" id="perm_user_id">

            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
                <p class="text-xs font-bold text-slate-700 uppercase tracking-wider mono">Access Mode</p>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="flex items-start gap-3 p-3.5 rounded-2xl bg-white border border-slate-200 cursor-pointer hover:border-blue-300 transition-colors shadow-2xs">
                        <input type="radio" name="permission_mode" value="role_default" id="mode_role_default" class="mt-0.5 text-[#1d63d8] focus:ring-[#1d63d8]" onchange="toggleCustomPerms(false)">
                        <div>
                            <p class="text-xs font-extrabold text-slate-900">Inherit Role Permissions</p>
                            <p class="text-[11px] text-slate-500 font-medium">Automatically sync with assigned role (<span id="perm_role_name"></span>)</p>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3.5 rounded-2xl bg-white border border-slate-200 cursor-pointer hover:border-amber-400 transition-colors shadow-2xs">
                        <input type="radio" name="permission_mode" value="custom_override" id="mode_custom_override" class="mt-0.5 text-amber-600 focus:ring-amber-500" onchange="toggleCustomPerms(true)">
                        <div>
                            <p class="text-xs font-extrabold text-amber-700">Custom Permission Override</p>
                            <p class="text-[11px] text-slate-500 font-medium">Grant or revoke specific modules individually for this user</p>
                        </div>
                    </label>
                </div>
            </div>

            <div id="customPermsContainer" class="space-y-4">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-bold text-slate-700 uppercase tracking-wider mono">Granted Module Access</p>
                    <button type="button" onclick="selectAllUserPerms()" class="text-xs font-bold text-[#1d63d8] hover:underline cursor-pointer">Toggle All</button>
                </div>

                <div class="space-y-4 bg-slate-50 p-4 rounded-2xl border border-slate-200">
                    <?php foreach ($SYSTEM_PERMISSIONS as $category => $perms): ?>
                        <div>
                            <p class="text-[11px] font-extrabold text-blue-700 uppercase tracking-wider mb-2 mono"><?= htmlspecialchars($category) ?></p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                <?php foreach ($perms as $pKey => $pDesc): ?>
                                    <label class="flex items-start gap-2.5 p-2.5 rounded-xl bg-white border border-slate-200 hover:border-amber-300 cursor-pointer transition-colors shadow-2xs">
                                        <input type="checkbox" name="permissions[]" value="<?= htmlspecialchars($pKey) ?>" id="user_perm_<?= $pKey ?>" class="user-perm-check mt-0.5 rounded bg-slate-50 border-slate-300 text-amber-600 focus:ring-amber-500">
                                        <div class="min-w-0">
                                            <p class="text-xs font-bold text-slate-900"><?= htmlspecialchars(str_replace('_', ' ', $pKey)) ?></p>
                                            <p class="text-[10px] text-slate-500 leading-tight font-medium"><?= htmlspecialchars($pDesc) ?></p>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 shrink-0">
                <button type="button" onclick="closePermissionsModal()" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors cursor-pointer">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#d97706] hover:bg-[#b45309] text-white text-xs font-bold shadow-sm transition-all cursor-pointer">
                    Save Permission Access
                </button>
            </div>
        </form>

    </div>
</div>

<!-- CREATE USER MODAL -->
<div id="createUserModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white border border-slate-200 rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl relative">
        
        <div class="flex items-center justify-between pb-4 mb-5 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-blue-50 text-[#1d63d8] flex items-center justify-center">
                    <i class="fa-solid fa-user-plus text-base"></i>
                </div>
                <div>
                    <h3 class="text-lg font-extrabold text-slate-900">Create New User</h3>
                    <p class="text-xs text-slate-500 font-medium">Add user and assign access role</p>
                </div>
            </div>
            <button type="button" onclick="closeCreateUserModal()" class="text-slate-400 hover:text-slate-800 p-2 cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="users.php" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="create_user">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Full Name *</label>
                <input type="text" name="name" required placeholder="e.g. John Doe" 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-[#1d63d8] focus:bg-white font-medium">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Email Address *</label>
                <input type="email" name="email" required placeholder="e.g. john@company.com" 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-[#1d63d8] focus:bg-white font-medium">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Password *</label>
                    <input type="password" name="password" required placeholder="••••••••" 
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-[#1d63d8] focus:bg-white font-medium">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Phone Number</label>
                    <input type="text" name="phone" placeholder="+91 98765 43210" 
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-[#1d63d8] focus:bg-white font-mono font-medium">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Assign Role *</label>
                    <select name="role_id" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 font-bold focus:outline-none focus:border-[#1d63d8]">
                        <?php foreach ($allRoles as $role): ?>
                            <option value="<?= $role['id'] ?>" <?= $role['slug'] === 'user' ? 'selected' : '' ?>>
                                <?= htmlspecialchars($role['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Status</label>
                    <select name="status" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 font-bold focus:outline-none focus:border-[#1d63d8]">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Avatar Image URL (Optional)</label>
                <input type="url" name="avatar" placeholder="https://example.com/avatar.jpg" 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-[#1d63d8] focus:bg-white font-medium">
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 mt-6">
                <button type="button" onclick="closeCreateUserModal()" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors cursor-pointer">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#1d63d8] hover:bg-[#1553c7] text-white text-xs font-bold shadow-sm transition-all cursor-pointer">
                    Create User &amp; Assign Role
                </button>
            </div>
        </form>

    </div>
</div>

<!-- EDIT USER MODAL -->
<div id="editUserModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white border border-slate-200 rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl relative">
        
        <div class="flex items-center justify-between pb-4 mb-5 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-blue-50 text-[#1d63d8] flex items-center justify-center">
                    <i class="fa-solid fa-user-pen text-base"></i>
                </div>
                <div>
                    <h3 class="text-lg font-extrabold text-slate-900">Edit User Profile</h3>
                    <p class="text-xs text-slate-500 font-medium">Modify details, password, and assign roles</p>
                </div>
            </div>
            <button type="button" onclick="closeEditUserModal()" class="text-slate-400 hover:text-slate-800 p-2 cursor-pointer">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="users.php" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="user_id" id="edit_user_id">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Full Name *</label>
                <input type="text" name="name" id="edit_name" required 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-[#1d63d8] focus:bg-white font-medium">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Email Address *</label>
                <input type="email" name="email" id="edit_email" required 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-[#1d63d8] focus:bg-white font-medium">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">New Password (Optional)</label>
                    <input type="password" name="password" placeholder="••••••••" 
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-[#1d63d8] focus:bg-white font-medium">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Phone Number</label>
                    <input type="text" name="phone" id="edit_phone" placeholder="+91 98765 43210" 
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-[#1d63d8] focus:bg-white font-mono font-medium">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Assigned Role *</label>
                    <select name="role_id" id="edit_role_id" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 font-bold focus:outline-none focus:border-[#1d63d8]">
                        <?php foreach ($allRoles as $role): ?>
                            <option value="<?= $role['id'] ?>">
                                <?= htmlspecialchars($role['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Status</label>
                    <select name="status" id="edit_status" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 font-bold focus:outline-none focus:border-[#1d63d8]">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 mono">Avatar Image URL</label>
                <input type="url" name="avatar" id="edit_avatar" placeholder="https://example.com/avatar.jpg" 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-[#1d63d8] focus:bg-white font-medium">
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 mt-6">
                <button type="button" onclick="closeEditUserModal()" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors cursor-pointer">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#1d63d8] hover:bg-[#1553c7] text-white text-xs font-bold shadow-sm transition-all cursor-pointer">
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
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, delete user',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('deleteUserForm').submit();
        }
    });
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
