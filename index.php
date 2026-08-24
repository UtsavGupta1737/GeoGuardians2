<?php
// index.php - Main Router / Gateway
require_once __DIR__ . '/auth.php';

if (isLoggedIn()) {
    $user = getCurrentUser($pdo);
    if ($user) {
        header("Location: " . getRoleHomeUrl($user));
        exit;
    }
}

header("Location: login.php");
exit;
