<?php
/**
 * Global Header Component
 * GeoGuardians - DisasterSafe
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DisasterSafe | AI-Powered Disaster Management & Citizen SOS Grid</title>
    <meta name="description" content="National Disaster Response Force & Citizen Safe Evacuation Coordination Platform">
    
    <!-- Google Fonts & Leaflet GIS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <!-- Custom CSS Design System -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- Main Navigation Header -->
    <header class="navbar">
        <div class="brand-container" onclick="window.location.href='index.php'">
            <div class="brand-logo-icon"><i class="fa-solid fa-shield-halved"></i></div>
            <div class="brand-text">
                <h1>Disaster<span>Safe</span> <span class="brand-badge">NDRF</span></h1>
            </div>
        </div>

        <nav class="nav-links">
            <a href="dashboard.php" class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>"><i class="fa-solid fa-gauge-high"></i> Command Map</a>
            <a href="citizen.php" class="nav-link <?= $currentPage === 'citizen' ? 'active' : '' ?>"><i class="fa-solid fa-tower-broadcast"></i> Citizen SOS</a>
            <a href="volunteer.php" class="nav-link <?= $currentPage === 'volunteer' ? 'active' : '' ?>"><i class="fa-solid fa-users"></i> Volunteer Grid</a>
        </nav>

        <div class="nav-controls">
            <div class="time-pill">
                <span class="live-dot"></span>
                <span id="liveClock">--:--:--</span>
            </div>

            <button class="btn btn-secondary btn-sm" id="btnAudioToggle" onclick="toggleAudioAlerts()" title="Toggle SOS Siren Sound">
                <i class="fa-solid fa-volume-high"></i> Siren
            </button>
            <button class="btn btn-secondary btn-sm" id="btnThemeToggle" onclick="toggleTheme()" title="Toggle Dark/Light Mode">
                <i class="fa-solid fa-moon"></i> Theme
            </button>

            <?php if (isAuthenticated()): ?>
                <span class="badge-role badge-<?= getUserRole() ?>">
                    <i class="fa-solid fa-user"></i> <?= htmlspecialchars(getUserName()) ?> (<?= ucfirst(getUserRole()) ?>)
                </span>
                <button class="btn btn-secondary btn-sm" onclick="logoutUser()"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</button>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-key"></i> Login</a>
            <?php endif; ?>

            <a href="citizen.php" class="btn btn-danger btn-sm"><i class="fa-solid fa-tower-broadcast"></i> Instant SOS</a>
        </div>
    </header>
