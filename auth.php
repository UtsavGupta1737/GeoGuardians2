<?php
// config/auth.php - Session Management, Auth Guards, Roles & Granular Permission Helpers

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

// Global System Permissions Definitions
$SYSTEM_PERMISSIONS = [
    'Specialized Agency Command' => [
        'access_sos_database' => 'Universal SOS Database (Full Distress Call Triage & Dispatches)',
        'access_disasters' => 'Disaster Command Hub (Major Crisis Declarations & Zones)',
        'access_ndrf' => 'NDRF Tactical Operations (Heavy Machinery & National Rescue)',
        'access_police' => 'Police Law Enforcement (Perimeter Cordons & Roadblocks)',
        'access_fire' => 'Fire & Rescue Department (Fire Suppression & Hazmat)',
        'access_medical' => 'Medical & EMS Department (Hospital Beds & Ambulance Triage)',
        'access_volunteer' => 'Volunteer Relief Corps (Ground Missions & Ledger)',
        'access_missing_persons' => 'Missing Persons Registry & Search Operations'
    ],
    'Administrative & Governance' => [
        'manage_users' => 'User Management & Access Control',
        'manage_roles' => '7-Tier Role Matrix & Permission Definitions',
        'view_activity_logs' => 'Audit Trail & Platform Security Logs',
        'view_analytics' => 'Platform Analytics, Metrics & KPI Charts',
        'manage_settings' => 'System & Database Preferences'
    ],
    'Workspace & Account' => [
        'view_dashboard' => 'Access Operational Dashboard',
        'edit_profile' => 'Manage Personal Profile & Credentials'
    ]
];

// Flash message helpers
function setFlash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// CSRF Protection
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Authentication Check
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Get logged-in user profile with joined role & computed active permissions
function getCurrentUser($pdo) {
    if (!isLoggedIn()) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT u.*, r.name as role_name, r.slug as role_slug, r.permissions as role_permissions 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.id = ? AND u.status = 'active'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        logoutUser();
        return null;
    }

    $rolePerms = json_decode($user['role_permissions'] ?? '[]', true) ?: [];
    
    if (!empty($user['custom_permissions'])) {
        $customPerms = json_decode($user['custom_permissions'], true);
        if (is_array($customPerms)) {
            $user['permissions'] = $customPerms;
            $user['has_custom_permissions'] = true;
        } else {
            $user['permissions'] = $rolePerms;
            $user['has_custom_permissions'] = false;
        }
    } else {
        $user['permissions'] = $rolePerms;
        $user['has_custom_permissions'] = false;
    }

    return $user;
}

// Check if user is Superadmin
function isSuperAdmin($user) {
    if (!$user) return false;
    return ($user['role_slug'] === 'superadmin');
}

// Check if user has specific permission
function hasPermission($user, $permission) {
    if (!$user) return false;
    if (isSuperAdmin($user)) return true;
    return in_array($permission, $user['permissions'] ?? []);
}

// Helper access checkers
function isPolice($user) {
    return hasPermission($user, 'access_police');
}

// Determine the home dashboard URL for a given user or role slug
function getRoleHomeUrl($userOrSlug) {
    $roleSlug = is_array($userOrSlug) ? ($userOrSlug['role_slug'] ?? 'user') : (string)$userOrSlug;
    return match ($roleSlug) {
        'volunteer', 'ngo' => 'volunteer.php',
        'user', 'citizen', 'victim' => 'citizen.php',
        default => 'dashboard.php'
    };
}

// Check if user is a citizen/victim
function isCitizen($user) {
    if (!$user) return false;
    $slug = $user['role_slug'] ?? '';
    return in_array($slug, ['user', 'citizen', 'victim']);
}

// Auth guards for pages
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}

function requireSuperAdmin($pdo) {
    requireLogin();
    $user = getCurrentUser($pdo);
    if (!isSuperAdmin($user)) {
        setFlash('error', 'Access denied. Superadmin root authorization required.');
        header("Location: dashboard.php");
        exit;
    }
    return $user;
}

function requirePolice($pdo) {
    return requirePermissionGuard($pdo, 'access_police');
}

function requireVolunteer($pdo) {
    return requirePermissionGuard($pdo, 'access_volunteer');
}

function requirePermissionGuard($pdo, $permission) {
    requireLogin();
    $user = getCurrentUser($pdo);
    if (!hasPermission($user, $permission)) {
        setFlash('error', "Access Denied: You do not have permission for this module ({$permission}).");
        header("Location: " . getRoleHomeUrl($user));
        exit;
    }
    return $user;
}

// Activity Logger
function logActivity($pdo, $action, $details = '') {
    $userId = $_SESSION['user_id'] ?? null;
    $userName = $_SESSION['user_name'] ?? 'Guest';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, user_name, action, details, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $userName, $action, $details, $ip]);
    } catch (Exception $e) {}
}

// Logout helper
function logoutUser() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
}
