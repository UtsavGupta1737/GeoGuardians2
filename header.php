<?php
// header.php - Global Header, Typography, Government Design System & Leaflet GIS Assets
if (!defined('PAGE_TITLE')) {
    define('PAGE_TITLE', 'Command Center');
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-[#f8fafc] text-slate-900">
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
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        },
                        gov: {
                            blue: '#1d63d8',
                            navy: '#0f2942',
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
            --surface: #f8fafc;
            --panel: #ffffff;
            --border: #e2e8f0;
            --border-accent: #cbd5e1;
            --primary-accent: #1d63d8;
            --emerald-accent: #16a34a;
            --amber-accent: #d97706;
            --red-accent: #dc2626;
            --muted: #64748b;
            --font-sans: 'Inter', system-ui, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
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
            border-radius: 6px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        
        /* Top Sovereign Accent Line */
        .top-accent-line {
            height: 3px;
            background: linear-gradient(90deg, #1d63d8 0%, #0284c7 35%, #059669 70%, #d97706 100%);
            width: 100%;
        }

        .glass-panel {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .stat-card-accent {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-top: 3px solid #1d63d8;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            transition: all 0.2s ease;
        }
        .stat-card-accent:hover {
            transform: translateY(-2px);
            border-color: #cbd5e1;
            border-top-color: #1d63d8;
            box-shadow: 0 8px 16px -4px rgba(29, 99, 216, 0.12);
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
            border-radius: 50%;
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
<body class="h-full bg-[#f8fafc] text-slate-800 antialiased overflow-hidden">
<div class="h-screen flex flex-row w-full overflow-hidden bg-[#f8fafc]">
