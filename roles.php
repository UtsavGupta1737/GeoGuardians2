<?php
// roles.php - Dynamic Role Creation & Permission Management
define('PAGE_TITLE', 'Role Management');
require_once __DIR__ . '/auth.php';

$currentUser = requirePermissionGuard($pdo, 'manage_roles');

// Handle Actions (POST requests)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrf)) {
        setFlash('error', 'Invalid security token. Please try again.');
        header("Location: roles.php");
        exit;
    }

    // 1. CREATE ROLE
    if ($action === 'create_role') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $permissions = $_POST['permissions'] ?? [];

        if (empty($name)) {
            setFlash('error', 'Role name cannot be empty.');
        } else {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '_', $name)));
            
            $checkStmt = $pdo->prepare("SELECT id FROM roles WHERE name = ? OR slug = ?");
            $checkStmt->execute([$name, $slug]);
            if ($checkStmt->fetch()) {
                setFlash('error', "A role with name '{$name}' or slug '{$slug}' already exists.");
            } else {
                $permissionsJson = json_encode(array_values($permissions));
                $stmt = $pdo->prepare("INSERT INTO roles (name, slug, description, permissions) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $slug, $description, $permissionsJson]);

                logActivity($pdo, 'CREATE_ROLE', "Created custom role '{$name}' with slug '{$slug}'");
                setFlash('success', "Role '{$name}' created successfully! You can now assign it to users.");
            }
        }
        header("Location: roles.php");
        exit;
    }

    // 2. UPDATE ROLE
    if ($action === 'update_role') {
        $roleId = (int) ($_POST['role_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $permissions = $_POST['permissions'] ?? [];

        if (empty($roleId) || empty($name)) {
            setFlash('error', 'Role name is required.');
        } else {
            $existing = $pdo->query("SELECT slug FROM roles WHERE id = {$roleId}")->fetch();
            if (!$existing) {
                setFlash('error', 'Role not found.');
            } else {
                if ($existing['slug'] === 'superadmin') {
                    $allPerms = [];
                    foreach ($SYSTEM_PERMISSIONS as $group) {
                        foreach ($group as $k => $v) $allPerms[] = $k;
                    }
                    $permissionsJson = json_encode($allPerms);
                } else {
                    $permissionsJson = json_encode(array_values($permissions));
                }

                $stmt = $pdo->prepare("UPDATE roles SET name = ?, description = ?, permissions = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$name, $description, $permissionsJson, $roleId]);

                logActivity($pdo, 'UPDATE_ROLE', "Updated role ID #{$roleId} ('{$name}')");
                setFlash('success', "Role '{$name}' permissions updated. Active users under this role will immediately reflect the changes!");
            }
        }
        header("Location: roles.php");
        exit;
    }

    // 3. DELETE ROLE
    if ($action === 'delete_role') {
        $roleId = (int) ($_POST['role_id'] ?? 0);
        
        $targetRole = $pdo->query("SELECT * FROM roles WHERE id = {$roleId}")->fetch();
        if (!$targetRole) {
            setFlash('error', 'Role not found.');
        } elseif ($targetRole['slug'] === 'superadmin') {
            setFlash('error', 'The Superadmin root role cannot be deleted.');
        } else {
            $assignedCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role_id = {$roleId}")->fetchColumn();
            if ($assignedCount > 0) {
                setFlash('error', "Cannot delete role '{$targetRole['name']}' because {$assignedCount} user(s) are currently assigned to it. Please reassign them first.");
            } else {
                $delStmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
                $delStmt->execute([$roleId]);

                logActivity($pdo, 'DELETE_ROLE', "Deleted role '{$targetRole['name']}' [ID #{$roleId}]");
                setFlash('success', "Role '{$targetRole['name']}' was removed successfully.");
            }
        }
        header("Location: roles.php");
        exit;
    }
}

// Fetch all roles with assigned user count
$rolesStmt = $pdo->query("
    SELECT r.*, COUNT(u.id) as user_count 
    FROM roles r 
    LEFT JOIN users u ON r.id = u.role_id 
    GROUP BY r.id 
    ORDER BY r.id ASC
");
$rolesList = $rolesStmt->fetchAll();

$csrfToken = generateCsrfToken();

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div class="flex-1 flex flex-col min-w-0 bg-slate-950">
    <?php require_once __DIR__ . '/navbar.php'; ?>

    <main class="flex-1 p-4 sm:p-8 max-w-7xl w-full mx-auto space-y-6">
        
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-extrabold text-white flex items-center gap-3">
                    <span>Role Management</span>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-purple-500/10 text-purple-400 border border-purple-500/20">
                        <?= count($rolesList) ?> Roles Configured
                    </span>
                </h2>
                <p class="text-xs text-slate-400 mt-1">Configure global role identities and granted permissions matrix</p>
            </div>
            <button type="button" onclick="openCreateRoleModal()" class="px-4 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold transition-all shadow-lg shadow-purple-600/30 flex items-center justify-center gap-2">
                <i class="fa-solid fa-shield-plus text-xs"></i>
                <span>Create New Role</span>
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($rolesList as $r): ?>
                <?php 
                    $perms = json_decode($r['permissions'] ?? '[]', true) ?: [];
                    $isSystemRoot = ($r['slug'] === 'superadmin');
                ?>
                <div class="glass-panel p-6 rounded-3xl flex flex-col justify-between relative group hover:border-purple-500/40 transition-all">
                    
                    <div>
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-2xl <?= $isSystemRoot ? 'bg-purple-500/20 text-purple-400' : 'bg-slate-800 text-indigo-400' ?> flex items-center justify-center text-lg shrink-0">
                                    <i class="fa-solid <?= $isSystemRoot ? 'fa-crown' : 'fa-user-shield' ?>"></i>
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-white"><?= htmlspecialchars($r['name']) ?></h3>
                                    <span class="text-[11px] font-mono text-slate-400"><?= htmlspecialchars($r['slug']) ?></span>
                                </div>
                            </div>
                            <span class="px-2.5 py-1 text-[11px] font-bold rounded-full bg-slate-800 border border-slate-700 text-slate-300">
                                <?= $r['user_count'] ?> <?= $r['user_count'] == 1 ? 'User' : 'Users' ?>
                            </span>
                        </div>

                        <p class="text-xs text-slate-400 mb-4 leading-relaxed line-clamp-2">
                            <?= htmlspecialchars($r['description'] ?: 'No description provided.') ?>
                        </p>

                        <div class="mb-4">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-2">Granted Permissions (<?= count($perms) ?>)</p>
                            <div class="flex flex-wrap gap-1.5 max-h-24 overflow-y-auto pr-1">
                                <?php if ($isSystemRoot): ?>
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-purple-500/10 text-purple-400 border border-purple-500/20">
                                        <i class="fa-solid fa-infinity text-[8px] mr-1"></i> Full Root Access (All Permissions)
                                    </span>
                                <?php elseif (empty($perms)): ?>
                                    <span class="text-[10px] text-slate-500 italic">No permissions assigned</span>
                                <?php else: ?>
                                    <?php foreach ($perms as $p): ?>
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-medium bg-slate-900 border border-slate-800 text-slate-300">
                                            <?= htmlspecialchars(str_replace('_', ' ', $p)) ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-800/80 flex items-center justify-between">
                        <span class="text-[10px] text-slate-500">
                            Created: <?= date('M d, Y', strtotime($r['created_at'])) ?>
                        </span>
                        
                        <div class="flex items-center gap-1.5">
                            <button type="button" 
                                    onclick='openEditRoleModal(<?= json_encode($r, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                    class="p-2 rounded-lg bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-400 border border-indigo-500/20 transition-colors" title="Edit Role & Permissions">
                                <i class="fa-solid fa-pen-to-square text-xs"></i>
                            </button>

                            <?php if (!$isSystemRoot): ?>
                                <button type="button" 
                                        onclick="confirmDeleteRole(<?= $r['id'] ?>, '<?= addslashes(htmlspecialchars($r['name'])) ?>', <?= $r['user_count'] ?>)"
                                        class="p-2 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 transition-colors" title="Delete Role">
                                    <i class="fa-solid fa-trash-can text-xs"></i>
                                </button>
                            <?php else: ?>
                                <span class="p-2 rounded-lg bg-slate-800 text-slate-600 cursor-not-allowed opacity-50" title="System root role is protected">
                                    <i class="fa-solid fa-shield-halved text-xs"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>

    </main>
</div>

<!-- CREATE ROLE MODAL -->
<div id="createRoleModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-2xl w-full p-6 sm:p-8 shadow-2xl relative max-h-[90vh] flex flex-col animate-in fade-in zoom-in duration-200">
        
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-800 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-purple-500/20 text-purple-400 flex items-center justify-center">
                    <i class="fa-solid fa-shield-plus text-base"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-white">Create New Role</h3>
                    <p class="text-xs text-slate-400">Define role identity and assign default module permissions</p>
                </div>
            </div>
            <button type="button" onclick="closeCreateRoleModal()" class="text-slate-400 hover:text-white p-2">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="roles.php" class="space-y-4 overflow-y-auto pr-1 flex-1">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="create_role">

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Role Name *</label>
                <input type="text" name="name" required placeholder="e.g. Triage Coordinator, Logistics Officer" 
                       class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-purple-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Description</label>
                <textarea name="description" rows="2" placeholder="Brief summary of duties and permissions for this role..." 
                          class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-purple-500"></textarea>
            </div>

            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider">Assign Default Permissions</label>
                    <button type="button" onclick="selectAllCreatePerms()" class="text-[11px] text-purple-400 hover:text-purple-300 font-semibold">Select All</button>
                </div>

                <div class="space-y-4 bg-slate-950 p-4 rounded-2xl border border-slate-800">
                    <?php foreach ($SYSTEM_PERMISSIONS as $category => $perms): ?>
                        <div>
                            <p class="text-[11px] font-bold text-indigo-400 uppercase tracking-wider mb-2"><?= htmlspecialchars($category) ?></p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                <?php foreach ($perms as $permKey => $permDesc): ?>
                                    <label class="flex items-start gap-2.5 p-2.5 rounded-xl bg-slate-900/80 border border-slate-800/80 hover:border-purple-500/40 cursor-pointer transition-colors">
                                        <input type="checkbox" name="permissions[]" value="<?= htmlspecialchars($permKey) ?>" class="create-perm-check mt-0.5 rounded bg-slate-800 border-slate-700 text-purple-600 focus:ring-purple-500">
                                        <div class="min-w-0">
                                            <p class="text-xs font-semibold text-white"><?= htmlspecialchars(str_replace('_', ' ', $permKey)) ?></p>
                                            <p class="text-[10px] text-slate-400 leading-tight"><?= htmlspecialchars($permDesc) ?></p>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800 shrink-0">
                <button type="button" onclick="closeCreateRoleModal()" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold shadow-lg shadow-purple-600/30 transition-all">
                    Create Role
                </button>
            </div>
        </form>

    </div>
</div>

<!-- EDIT ROLE MODAL -->
<div id="editRoleModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-2xl w-full p-6 sm:p-8 shadow-2xl relative max-h-[90vh] flex flex-col animate-in fade-in zoom-in duration-200">
        
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-800 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center">
                    <i class="fa-solid fa-pen-to-square text-base"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-white">Edit Role Configuration</h3>
                    <p class="text-xs text-slate-400">Update role name and granted permissions</p>
                </div>
            </div>
            <button type="button" onclick="closeEditRoleModal()" class="text-slate-400 hover:text-white p-2">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="roles.php" class="space-y-4 overflow-y-auto pr-1 flex-1">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="update_role">
            <input type="hidden" name="role_id" id="edit_role_id">

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Role Name *</label>
                <input type="text" name="name" id="edit_role_name" required 
                       class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Description</label>
                <textarea name="description" id="edit_role_description" rows="2" 
                          class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500"></textarea>
            </div>

            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider">Granted Permissions</label>
                    <button type="button" onclick="selectAllEditPerms()" class="text-[11px] text-indigo-400 hover:text-indigo-300 font-semibold">Toggle All</button>
                </div>

                <div class="space-y-4 bg-slate-950 p-4 rounded-2xl border border-slate-800">
                    <?php foreach ($SYSTEM_PERMISSIONS as $category => $perms): ?>
                        <div>
                            <p class="text-[11px] font-bold text-indigo-400 uppercase tracking-wider mb-2"><?= htmlspecialchars($category) ?></p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                <?php foreach ($perms as $permKey => $permDesc): ?>
                                    <label class="flex items-start gap-2.5 p-2.5 rounded-xl bg-slate-900/80 border border-slate-800/80 hover:border-indigo-500/40 cursor-pointer transition-colors">
                                        <input type="checkbox" name="permissions[]" value="<?= htmlspecialchars($permKey) ?>" id="edit_perm_<?= $permKey ?>" class="edit-perm-check mt-0.5 rounded bg-slate-800 border-slate-700 text-indigo-600 focus:ring-indigo-500">
                                        <div class="min-w-0">
                                            <p class="text-xs font-semibold text-white"><?= htmlspecialchars(str_replace('_', ' ', $permKey)) ?></p>
                                            <p class="text-[10px] text-slate-400 leading-tight"><?= htmlspecialchars($permDesc) ?></p>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800 shrink-0">
                <button type="button" onclick="closeEditRoleModal()" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition-colors">
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
<form id="deleteRoleForm" method="POST" action="roles.php" class="hidden">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <input type="hidden" name="action" value="delete_role">
    <input type="hidden" name="role_id" id="delete_role_id">
</form>

<script>
function openCreateRoleModal() {
    document.getElementById('createRoleModal').classList.remove('hidden');
}
function closeCreateRoleModal() {
    document.getElementById('createRoleModal').classList.add('hidden');
}

function selectAllCreatePerms() {
    const checks = document.querySelectorAll('.create-perm-check');
    const allChecked = Array.from(checks).every(c => c.checked);
    checks.forEach(c => c.checked = !allChecked);
}

function selectAllEditPerms() {
    const checks = document.querySelectorAll('.edit-perm-check');
    const allChecked = Array.from(checks).every(c => c.checked);
    checks.forEach(c => c.checked = !allChecked);
}

function openEditRoleModal(role) {
    document.getElementById('edit_role_id').value = role.id;
    document.getElementById('edit_role_name').value = role.name || '';
    document.getElementById('edit_role_description').value = role.description || '';

    document.querySelectorAll('.edit-perm-check').forEach(c => c.checked = false);

    try {
        const perms = typeof role.permissions === 'string' ? JSON.parse(role.permissions) : role.permissions;
        if (Array.isArray(perms)) {
            perms.forEach(p => {
                const el = document.getElementById('edit_perm_' + p);
                if (el) el.checked = true;
            });
        }
    } catch (e) {}

    document.getElementById('editRoleModal').classList.remove('hidden');
}
function closeEditRoleModal() {
    document.getElementById('editRoleModal').classList.add('hidden');
}

function confirmDeleteRole(roleId, roleName, userCount) {
    if (userCount > 0) {
        Swal.fire({
            title: 'Cannot Delete Role',
            text: `There are currently ${userCount} user(s) assigned to '${roleName}'. Please reassign those users to another role first.`,
            icon: 'warning',
            confirmButtonColor: '#6366f1',
            background: '#1e293b',
            color: '#f8fafc'
        });
        return;
    }

    Swal.fire({
        title: 'Delete Role?',
        text: `Are you sure you want to delete '${roleName}'?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        cancelButtonColor: '#334155',
        confirmButtonText: 'Yes, delete role',
        cancelButtonText: 'Cancel',
        background: '#1e293b',
        color: '#f8fafc'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('delete_role_id').value = roleId;
            document.getElementById('deleteRoleForm').submit();
        }
    });
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
