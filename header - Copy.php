<?php
// header.php - Global Header, Typography, Design System & Leaflet GIS Assets
if (!defined('PAGE_TITLE')) {
    define('PAGE_TITLE', 'Command Center');
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-[#0a0f1d] text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(PAGE_TITLE) ?> | DisasterSafe Command Center</title>
    
    <!-- Google Fonts: Inter & Plus Jakarta Sans & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    
    <!-- FontAwesome 6 CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Tailwind CSS CDN with Custom DisasterSafe Theme -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        navy: {
                            950: '#060a14',
                            900: '#0a0f1d',
                            850: '#0f172a',
                            800: '#131e36',
                            700: '#1c2b4e',
                            600: '#2a3d6b'
                        },
                        brand: {
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                        },
                        alert: {
                            red: '#ba1a1a',
                            crimson: '#dc2626',
                            amber: '#d97706',
                            emerald: '#16a34a',
                            blue: '#2563eb'
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'Plus Jakarta Sans', 'system-ui', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    }
                }
            }
        }
    </script>
    
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Leaflet GIS Map CDN -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    
    <style>
        :root {
            --navy: #000a1e;
            --muted: #586377;
            --border: #243049;
            --surface: #0a0f1d;
            --panel: #11192e;
            --font-sans: 'Inter', system-ui, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }
        body {
            font-family: var(--font-sans);
            background: #0a0f1d;
            color: #f1f5f9;
        }
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #0a0f1d;
        }
        ::-webkit-scrollbar-thumb {
            background: #243049;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #3b82f6;
        }
        .glass-panel {
            background: rgba(17, 25, 46, 0.85);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .stat-card-accent {
            background: rgba(17, 25, 46, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.06);
            transition: all 0.2s ease;
        }
        .stat-card-accent:hover {
            transform: translateY(-2px);
            border-color: rgba(59, 130, 246, 0.4);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5);
        }
        .live-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #ba1a1a;
            display: inline-block;
            box-shadow: 0 0 10px #ba1a1a;
            animation: pulseDot 1.5s infinite;
        }
        @keyframes pulseDot {
            0% { transform: scale(0.95); opacity: 0.8; }
            50% { transform: scale(1.3); opacity: 1; box-shadow: 0 0 14px #ba1a1a; }
            100% { transform: scale(0.95); opacity: 0.8; }
        }
        .radar-pulse-marker {
            width: 26px;
            height: 26px;
            background: radial-gradient(circle, #ef4444, #991b1b);
            border-radius: 50%;
            display: grid;
            place-items: center;
            color: white;
            font-weight: 900;
            font-size: 12px;
            border: 2px solid #ffffff;
            box-shadow: 0 0 0 rgba(239, 68, 68, 0.8);
            animation: pulseRadar 1.5s infinite;
        }
        @keyframes pulseRadar {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.9); }
            70% { box-shadow: 0 0 0 20px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }

        /* Smooth Sliding Sidebar Drawer Styles */
        #main-sidebar {
            transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1), margin-left 0.28s cubic-bezier(0.4, 0, 0.2, 1);
        }
        body.sidebar-collapsed #main-sidebar {
            transform: translateX(-100%);
            margin-left: -18rem; /* Collapses 72 Tailwind width */
        }
        @media (max-width: 1023px) {
            body.sidebar-collapsed #main-sidebar {
                margin-left: 0;
                transform: translateX(-100%);
            }
            body.sidebar-open #main-sidebar {
                transform: translateX(0);
            }
        }
    </style>
    <script>
        function toggleMainSidebar() {
            if (window.innerWidth >= 1024) {
                document.body.classList.toggle('sidebar-collapsed');
                localStorage.setItem('sidebar_collapsed', document.body.classList.contains('sidebar-collapsed') ? 'true' : 'false');
            } else {
                const sidebar = document.getElementById('main-sidebar');
                const backdrop = document.getElementById('mobile-sidebar-backdrop');
                if (sidebar) {
                    sidebar.classList.toggle('-translate-x-full');
                }
                if (backdrop) {
                    backdrop.classList.toggle('hidden');
                }
            }
        }

        // Restore saved collapsed state
        document.addEventListener('DOMContentLoaded', () => {
            if (window.innerWidth >= 1024 && localStorage.getItem('sidebar_collapsed') === 'true') {
                document.body.classList.add('sidebar-collapsed');
            }
        });
    </script>
</head>
<body class="h-full bg-[#0a0f1d] text-slate-100 antialiased overflow-hidden">
<div class="h-screen flex flex-row w-full overflow-hidden">
