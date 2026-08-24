<?php
// index.php - Main Router / Gateway
require_once __DIR__ . '/auth.php';

if (isLoggedIn()) {
    $user = getCurrentUser($pdo);
    if ($user) {
        if ($user['role_slug'] === 'superadmin') {
            header("Location: dashboard.php");
            exit;
        } elseif ($user['role_slug'] === 'police') {
            header("Location: deployments.php");
            exit;
        } elseif ($user['role_slug'] === 'volunteer') {
            header("Location: dashboard.php");
            exit;
        } else {
            header("Location: dashboard.php");
            exit;
        }
    }
}

header("Location: login.php");
exit;
