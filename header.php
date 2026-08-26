<?php
// header.php - Global Header, Typography, Government Design System & Leaflet GIS Assets
if (!defined('PAGE_TITLE')) {
    define('PAGE_TITLE', 'Command Center');
}
$_roleSlug = $_SESSION['user_role'] ?? 'superadmin';
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-[#f8fafc] text-slate-900" data-role="<?= htmlspecialchars($_roleSlug) ?>">
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
                            950: '#0f172a',
                            900: '#1e293b',
                            850: '#334155',
                            800: '#475569',
                            700: '#64748b',
                            600: '#94a3b8'
                        },
                        brand: {
                            50: 'var(--role-accent-bg)',
                            100: 'var(--role-accent-bg)',
                            500: 'var(--role-primary)',
                            600: 'var(--role-primary)',
                            700: 'var(--role-primary)',
                            800: 'var(--role-deep)',
                            900: 'var(--role-deep)',
                        },
                        gov: {
                            blue: 'var(--role-primary)',
                            navy: 'var(--role-deep)',
                            red: '#dc2626',
                            amber: '#d97706',
                            green: '#16a34a'
                        },
                        alert: {
                            red: '#ba1a1a',
                            crimson: '#dc2626',
                            amber: '#d97706',
                            emerald: '#16a34a',
                            blue: '#2563eb'
                        },
                        role: {
                            primary: 'var(--role-primary)',
                            deep: 'var(--role-deep)',
                            light: 'var(--role-accent-bg)',
                            muted: 'var(--role-accent-muted)',
                            accent: 'var(--role-accent-border)',
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
        /* ================================================================
           GLOBAL SHARP EDGE GEOMETRY - ZERO RADIUS ENFORCEMENT
           ================================================================ */
        *, *::before, *::after {
            border-radius: 0 !important;
        }

        /* ================================================================
           ROLE-BASED THEME PALETTE SYSTEM
           Each role gets its own set of CSS variables injected via [data-role]
           ================================================================ */
        :root {
            --surface: #f8fafc;
            --panel: #ffffff;
            --border-color: #e2e8f0;
            --border-accent: #cbd5e1;
            --primary-accent: #1d63d8;
            --emerald-accent: #16a34a;
            --amber-accent: #d97706;
            --red-accent: #dc2626;
            --muted: #64748b;
            --font-sans: 'Inter', system-ui, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;

            /* Default Role Variables (Super Admin Blue) */
            --role-primary: #1d63d8;
            --role-primary-hover: #1553c7;
            --role-deep: #152238;
            --role-accent-bg: #CDEBFE;
            --role-accent-muted: #0073E6;
            --role-accent-border: #0047AB;
            --role-gradient-from: #0047AB;
            --role-gradient-to: #0073E6;
        }

        /* --- SUPER ADMIN: Pure White + Blue Shades --- */
        [data-role="superadmin"] {
            --role-primary: #1d63d8;
            --role-primary-hover: #1553c7;
            --role-deep: #152238;
            --role-accent-bg: #CDEBFE;
            --role-accent-muted: #0073E6;
            --role-accent-border: #0047AB;
            --role-gradient-from: #0047AB;
            --role-gradient-to: #0073E6;
        }

        /* --- NDRF FORCE: Combat Fatigue / Olive Green / Khaki / Camo --- */
        [data-role="ndrf"] {
            --role-primary: #556B2F;
            --role-primary-hover: #4a5c28;
            --role-deep: #2d3a18;
            --role-accent-bg: #E6EFDB;
            --role-accent-muted: #6B8E23;
            --role-accent-border: #8B7355;
            --role-gradient-from: #556B2F;
            --role-gradient-to: #6B8E23;
        }

        /* --- POLICE COMMAND: Indian Police Khaki + Deep Police Blue --- */
        [data-role="police"] {
            --role-primary: #001F5B;
            --role-primary-hover: #001745;
            --role-deep: #000F33;
            --role-accent-bg: #E6DBCF;
            --role-accent-muted: #A08060;
            --role-accent-border: #C3A381;
            --role-gradient-from: #001F5B;
            --role-gradient-to: #C3A381;
        }

        /* --- FIRE & RESCUE: Fire Red / Scarlet / High-Vis Orange --- */
        [data-role="fire"] {
            --role-primary: #CE2029;
            --role-primary-hover: #b51c24;
            --role-deep: #7F171F;
            --role-accent-bg: #EFDECD;
            --role-accent-muted: #FF4500;
            --role-accent-border: #CE2029;
            --role-gradient-from: #CE2029;
            --role-gradient-to: #FF4500;
        }

        /* --- MEDICAL HUB: Hospital Green / Teal / Soft Mint --- */
        [data-role="medical"] {
            --role-primary: #008080;
            --role-primary-hover: #006666;
            --role-deep: #004D4D;
            --role-accent-bg: #E6FAF1;
            --role-accent-muted: #3EB489;
            --role-accent-border: #008080;
            --role-gradient-from: #004D4D;
            --role-gradient-to: #3EB489;
        }

        /* --- VOLUNTEERS: Pumpkin Orange / Amber / Soft Peach --- */
        [data-role="volunteer"] {
            --role-primary: #FF6700;
            --role-primary-hover: #e65c00;
            --role-deep: #8B4513;
            --role-accent-bg: #FFF5EC;
            --role-accent-muted: #FBB917;
            --role-accent-border: #FF6700;
            --role-gradient-from: #8B4513;
            --role-gradient-to: #FF6700;
        }

        /* --- CITIZEN (User): Neutral Slate --- */
        [data-role="user"] {
            --role-primary: #475569;
            --role-primary-hover: #334155;
            --role-deep: #1e293b;
            --role-accent-bg: #f1f5f9;
            --role-accent-muted: #94a3b8;
            --role-accent-border: #cbd5e1;
            --role-gradient-from: #334155;
            --role-gradient-to: #64748b;
        }

        body {
            font-family: var(--font-sans);
            background: #f8fafc;
            color: #0f172a;
        }

        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Top Sovereign Accent Line - Role-Aware Gradient */
        .top-accent-line {
            height: 3px;
            background: linear-gradient(90deg, var(--role-gradient-from) 0%, var(--role-primary) 35%, var(--role-accent-muted) 70%, var(--role-accent-border) 100%);
            width: 100%;
        }

        .glass-panel {
            background: #ffffff;
            border: 1px solid var(--border-color);
        }

        .stat-card-accent {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-top: 3px solid var(--role-primary);
            transition: all 0.2s ease;
        }
        .stat-card-accent:hover {
            transform: translateY(-2px);
            border-color: var(--border-accent);
            border-top-color: var(--role-primary);
        }

        .accent-card-red {
            border-top: 3px solid #dc2626 !important;
        }
        .accent-card-emerald {
            border-top: 3px solid #16a34a !important;
        }
        .accent-card-amber {
            border-top: 3px solid #d97706 !important;
        }
        .accent-card-teal {
            border-top: 3px solid #0d9488 !important;
        }
        .accent-card-purple {
            border-top: 3px solid #7c3aed !important;
        }

        .live-dot {
            width: 8px;
            height: 8px;
            background: #ba1a1a;
            display: inline-block;
            box-shadow: 0 0 8px rgba(186, 26, 26, 0.5);
            animation: pulseDot 1.5s infinite;
        }
        @keyframes pulseDot {
            0% { transform: scale(0.95); opacity: 0.8; }
            50% { transform: scale(1.25); opacity: 1; box-shadow: 0 0 12px rgba(186, 26, 26, 0.8); }
            100% { transform: scale(0.95); opacity: 0.8; }
        }

        /* SOS Citizen Distress Marker - Strict RED #FF0000 */
        .radar-pulse-marker {
            width: 26px;
            height: 26px;
            background: #FF0000;
            display: grid;
            place-items: center;
            color: white;
            font-weight: 900;
            font-size: 12px;
            border: 2px solid #ffffff;
            box-shadow: 0 0 0 rgba(255, 0, 0, 0.8);
            animation: pulseRadar 1.5s infinite;
        }
        @keyframes pulseRadar {
            0% { box-shadow: 0 0 0 0 rgba(255, 0, 0, 0.9); }
            70% { box-shadow: 0 0 0 20px rgba(255, 0, 0, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 0, 0, 0); }
        }

        /* Medical Sign Marker - Strict GREEN #00FF00 */
        .medical-sign-marker {
            width: 30px;
            height: 30px;
            background: #00FF00;
            display: grid;
            place-items: center;
            color: #004D00;
            font-weight: 900;
            font-size: 14px;
            border: 2px solid #ffffff;
            box-shadow: 0 0 0 rgba(0, 255, 0, 0.6);
            animation: pulseMedical 2s infinite;
        }
        @keyframes pulseMedical {
            0% { box-shadow: 0 0 0 0 rgba(0, 255, 0, 0.7); }
            70% { box-shadow: 0 0 0 14px rgba(0, 255, 0, 0); }
            100% { box-shadow: 0 0 0 0 rgba(0, 255, 0, 0); }
        }

        /* ================================================================
           LEAFLET MAP OVERRIDES - Sharp 90-degree corners, clean borders
           ================================================================ */
        .leaflet-popup-content-wrapper {
            border-radius: 0 !important;
            border: 1px solid var(--border-color) !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12) !important;
        }
        .leaflet-popup-tip {
            border-radius: 0 !important;
        }
        .leaflet-control-zoom a {
            border-radius: 0 !important;
        }
        .leaflet-control-layers {
            border-radius: 0 !important;
            border: 1px solid var(--border-color) !important;
        }
        .leaflet-control-attribution {
            border-radius: 0 !important;
        }

        /* Smooth Sliding Sidebar Drawer Styles */
        #main-sidebar {
            transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1), margin-left 0.28s cubic-bezier(0.4, 0, 0.2, 1);
        }
        body.sidebar-collapsed #main-sidebar {
            transform: translateX(-100%);
            margin-left: -18rem;
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
        /* Top Navbar Oval Buttons Styling */
        header .rounded-full,
        .nav-pill-btn {
            border-radius: 9999px !important;
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
<body class="h-full bg-[#f8fafc] text-slate-800 antialiased overflow-hidden">
<div class="h-screen flex flex-row w-full overflow-hidden bg-[#f8fafc]">
