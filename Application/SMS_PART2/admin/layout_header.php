<?php
/**
 * Master UI Layout Header
 */

if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/SosRequest.php';

// Fetch metrics for sidebar badge counter
$headerMetrics = SosRequest::getMetrics();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS SOS Disaster Command Center</title>
    
    <!-- Outfit Font & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Main Style Sheet -->
    <link rel="stylesheet" href="css/dashboard.css">
    
    <!-- Leaflet mapping components -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation Drawer -->
        <aside class="sidebar">
            <div class="brand">
                <span class="brand-icon">🚨</span>
                <div class="brand-text">
                    <h1>SOS GATEWAY</h1>
                    <span class="subtitle">Disaster Command</span>
                </div>
            </div>
            
            <nav class="nav-menu">
                <a href="dashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <span class="icon">📊</span> Overview Dashboard
                </a>
                <a href="sos.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'sos.php' ? 'active' : ''; ?>">
                    <span class="icon">🚨</span> SOS Active Alerts
                    <?php if ($headerMetrics['pending_sos'] > 0): ?>
                        <span class="badge badge-alert"><?php echo $headerMetrics['pending_sos']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="inbox.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'inbox.php' ? 'active' : ''; ?>">
                    <span class="icon">📥</span> SMS Inbox log
                </a>
                <a href="contacts.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'contacts.php' ? 'active' : ''; ?>">
                    <span class="icon">👥</span> Contact Registry
                </a>
                <a href="alerts.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'alerts.php' ? 'active' : ''; ?>">
                    <span class="icon">📢</span> Disaster Alerts
                </a>
                <a href="send_message.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'send_message.php' ? 'active' : ''; ?>">
                    <span class="icon">📤</span> Send Broadcast
                </a>
                <a href="settings.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                    <span class="icon">⚙️</span> Gateway Settings
                </a>
            </nav>

            <div class="sidebar-footer">
                <div class="gateway-status" id="sidebar-gateway-card" style="transition: all 0.3s ease;">
                    <span class="status-indicator" id="sidebar-indicator" style="background-color: var(--text-muted); box-shadow: none;"></span>
                    <div class="status-details">
                        <span class="status-title" id="sidebar-status-title">SYNCING...</span>
                        <span class="status-desc" id="sidebar-status-desc">Querying gateway</span>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Workspace Area -->
        <main class="main-content">
            <header class="content-header">
                <div class="page-title">
                    <h2><?php echo $page_title ?? 'Overview'; ?></h2>
                    <p class="breadcrumbs">Command Center &gt; <?php echo $page_title ?? 'Dashboard'; ?></p>
                </div>
                <div class="header-actions">
                    <div class="system-time">
                        <span class="icon">⏰</span> <span id="clock-display">--:--:--</span>
                    </div>
                    <a href="sos.php" class="sos-btn">🚨 LIVE SOS FEED</a>
                </div>
            </header>
            <div class="page-body">
