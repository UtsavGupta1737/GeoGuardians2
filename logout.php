<?php
// logout.php - Logout handler
require_once __DIR__ . '/auth.php';

if (isLoggedIn()) {
    logActivity($pdo, 'LOGOUT', "User logged out safely");
    logoutUser();
}

setFlash('info', 'You have been successfully signed out.');
header("Location: login.php");
exit;
