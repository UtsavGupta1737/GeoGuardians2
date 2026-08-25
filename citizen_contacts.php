<?php
// citizen_contacts.php - Emergency Contact Directory (Converted from React to PHP)
define('PAGE_TITLE', 'Emergency Contact Directory');
require_once __DIR__ . '/auth.php';

$currentUser = getCurrentUser($pdo);
$userName = $currentUser['name'] ?? 'Citizen';

// Master Emergency Directory List
$contacts = [
    [
        'id' => 'c1',
        'name' => 'National Emergency Helpline (Universal)',
        'category' => 'national',
        'number' => '112',
        'badge' => '24/7 Universal Response',
        'description' => 'Single unified emergency response number for Police, Fire, Medical, and Disaster extraction.',
        'color' => 'red',
        'icon' => 'fa-tower-broadcast'
    ],
    [
        'id' => 'c2',
        'name' => 'National Disaster Response Force (NDRF)',
        'category' => 'disaster',
        'number' => '1070',
        'badge' => 'Heavy Rescue / Floods',
        'description' => 'Heavy search & rescue, tactical boat extraction, flood evacuation, and earthquake extrication.',
        'color' => 'orange',
        'icon' => 'fa-truck-monster'
    ],
    [
        'id' => 'c3',
        'name' => 'Police Law Enforcement & Cordons',
        'category' => 'police',
        'number' => '100',
        'badge' => 'Emergency Dispatch',
        'description' => 'Law enforcement, missing persons, highway cordons, evacuation security, and mobile patrol.',
        'color' => 'blue',
        'icon' => 'fa-shield-halved'
    ],
    [
        'id' => 'c4',
        'name' => 'Fire & Rescue Hazmat Brigade',
        'category' => 'fire',
        'number' => '101',
        'badge' => 'Fire / Hazmat',
        'description' => 'Structure fires, smoke ventilation, hazmat leak containment, and high-angle rope rescue.',
        'color' => 'rose',
        'icon' => 'fa-fire-extinguisher'
    ],
    [
        'id' => 'c5',
        'name' => 'Emergency Ambulance & Medical (EMS)',
        'category' => 'medical',
        'number' => '108',
        'badge' => 'Trauma & Paramedics',
        'description' => 'Advanced Life Support (ALS) ambulances, oxygen logistics, on-site triage, and hospital transfer.',
        'color' => 'teal',
        'icon' => 'fa-heart-pulse'
    ],
    [
        'id' => 'c6',
        'name' => 'Women Safety & Distress Helpline',
        'category' => 'women',
        'number' => '1091',
        'badge' => 'Specialized Helpline',
        'description' => 'Immediate protection, crisis intervention, shelter assistance, and legal aid for women in distress.',
        'color' => 'purple',
        'icon' => 'fa-person-dress'
    ],
    [
        'id' => 'c7',
        'name' => 'National Disaster Management Authority (NDMA)',
        'category' => 'disaster',
        'number' => '1078',
        'badge' => 'Disaster Control',
        'description' => 'Central disaster control room, cyclone warnings, early tsunami alerts, and interstate coordination.',
        'color' => 'amber',
        'icon' => 'fa-building-shield'
    ],
    [
        'id' => 'c8',
        'name' => 'District Emergency Operation Center (DEOC)',
        'category' => 'disaster',
        'number' => '1077',
        'badge' => 'District Command',
        'description' => 'District collectorate control room for local relief camps, food distribution, and shelter queries.',
        'color' => 'indigo',
        'icon' => 'fa-headset'
    ],
    [
        'id' => 'c9',
        'name' => 'Highway & Road Accident Emergency',
        'category' => 'police',
        'number' => '1073',
        'badge' => 'Expressway Rescue',
        'description' => 'Expressway vehicle pileup assistance, heavy crane vehicle extrication, and highway patrol.',
        'color' => 'slate',
        'icon' => 'fa-road'
    ]
];
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-[#fbf9f5] text-[#1c1917]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Helplines Directory | DisasterSafe</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        cream: {
                            50: '#fbf9f5',
                            100: '#f4f0ea',
                            200: '#eee7db',
                            300: '#d8d0c5'
                        },
                        navy: {
                            950: '#000a1e',
                            900: '#0a0f1d',
                            800: '#11192e',
                            700: '#1c2b4e'
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace']
                    }
                }
            }
        }
    </script>
</head>
<body class="min-h-screen bg-[#fbf9f5] text-[#1c1917] font-sans antialiased flex flex-col">

    <!-- Top Navigation Header -->
    <header class="sticky top-0 z-50 bg-[#000a1e] border-b border-[#1c2b4e] text-white shadow-lg backdrop-blur-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-red-600 to-amber-500 flex items-center justify-center font-black text-white text-xl shadow-md shadow-red-900/50">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-lg font-extrabold text-white tracking-tight">DisasterSafe</span>
                            <span class="px-2 py-0.5 rounded-full bg-teal-500/20 text-teal-400 border border-teal-500/30 text-[10px] font-bold uppercase tracking-wider">Helpline Directory</span>
                        </div>
                        <p class="text-[11px] text-slate-400 font-medium hidden sm:block">Verified Crisis & Emergency Helplines</p>
                    </div>
                </div>

                <nav class="hidden md:flex items-center gap-1 bg-[#11192e] p-1 rounded-xl border border-[#243049]">
                    <a href="citizen.php" class="px-3.5 py-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 font-semibold text-xs flex items-center gap-2 transition-all">
                        <i class="fa-solid fa-tower-broadcast text-xs text-red-400"></i>
                        <span>Emergency SOS</span>
                    </a>
                    <a href="citizen_guides.php" class="px-3.5 py-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 font-semibold text-xs flex items-center gap-2 transition-all">
                        <i class="fa-solid fa-book-medical text-xs text-amber-400"></i>
                        <span>Safety Guides</span>
                    </a>
                    <a href="citizen_contacts.php" class="px-3.5 py-1.5 rounded-lg bg-teal-600 text-white font-bold text-xs flex items-center gap-2 shadow-sm transition-all">
                        <i class="fa-solid fa-phone-volume text-xs"></i>
                        <span>Emergency Directory</span>
                    </a>
                </nav>

                <div class="flex items-center gap-3">
                    <a href="citizen.php" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-red-600 hover:bg-red-500 text-white text-xs font-bold transition-all shadow-md shadow-red-950/40">
                        <i class="fa-solid fa-tower-broadcast text-xs animate-pulse"></i>
                        <span>Transmit SOS</span>
                    </a>
                    <div class="flex items-center gap-2.5 pl-2 border-l border-[#243049]">
                        <span class="hidden sm:block text-xs font-bold text-slate-200"><?= htmlspecialchars($userName) ?></span>
                        <a href="logout.php" title="Sign Out of DisasterSafe" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-600/20 hover:bg-red-600/30 border border-red-500/40 text-red-300 text-xs font-bold transition-all shadow-xs">
                            <i class="fa-solid fa-arrow-right-from-bracket text-red-400"></i>
                            <span class="hidden sm:inline">Logout</span>
                        </a>
                    </div>
                </div>

            </div>
        </div>

        <div class="md:hidden flex items-center justify-around border-t border-[#1c2b4e] bg-[#0a0f1d] px-2 py-2 text-xs">
            <a href="citizen.php" class="px-3 py-1 rounded-lg text-slate-400 font-semibold flex items-center gap-1.5">
                <i class="fa-solid fa-tower-broadcast text-red-400"></i> SOS Beacon
            </a>
            <a href="citizen_guides.php" class="px-3 py-1 rounded-lg text-slate-400 font-semibold flex items-center gap-1.5">
                <i class="fa-solid fa-book-medical text-amber-400"></i> Guides
            </a>
            <a href="citizen_contacts.php" class="px-3 py-1 rounded-lg bg-teal-600/20 text-teal-400 font-bold flex items-center gap-1.5">
                <i class="fa-solid fa-phone-volume"></i> Helplines
            </a>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 lg:p-8 space-y-6">
        
        <!-- Search & Filter Banner -->
        <section class="bg-[#f4f0ea] border border-[#d8d0c5] rounded-3xl p-6 sm:p-8 space-y-5">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="space-y-1">
                    <span class="text-xs font-extrabold uppercase tracking-widest text-teal-700">Official Directory</span>
                    <h1 class="text-2xl sm:text-3xl font-black text-[#000a1e] tracking-tight">Verified Emergency Hotlines & Agencies</h1>
                    <p class="text-sm text-[#586377]">1-tap calling for instant connection to first responders, emergency services, and medical desks.</p>
                </div>
                
                <div class="w-full md:w-80">
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="text" id="contactSearchInput" oninput="filterContacts()" placeholder="Search helpline, police, hospital…"
                               class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-white border border-[#d8d0c5] text-sm text-[#1c1917] focus:outline-none focus:border-teal-600 transition-colors">
                    </div>
                </div>
            </div>

            <!-- Category Filter Pills -->
            <div class="flex items-center gap-2 flex-wrap pt-2 border-t border-[#d8d0c5]/60">
                <button type="button" onclick="setCategoryFilter('all')" class="cat-filter-btn px-3.5 py-1.5 rounded-xl text-xs font-bold bg-[#000a1e] text-white transition-all shadow-xs cursor-pointer" data-cat="all">
                    All Services (<?= count($contacts) ?>)
                </button>
                <button type="button" onclick="setCategoryFilter('national')" class="cat-filter-btn px-3.5 py-1.5 rounded-xl text-xs font-bold bg-white hover:bg-[#eee7db] text-[#1c1917] border border-[#d8d0c5] transition-all cursor-pointer" data-cat="national">
                    National Emergency
                </button>
                <button type="button" onclick="setCategoryFilter('disaster')" class="cat-filter-btn px-3.5 py-1.5 rounded-xl text-xs font-bold bg-white hover:bg-[#eee7db] text-[#1c1917] border border-[#d8d0c5] transition-all cursor-pointer" data-cat="disaster">
                    NDRF & Disaster
                </button>
                <button type="button" onclick="setCategoryFilter('medical')" class="cat-filter-btn px-3.5 py-1.5 rounded-xl text-xs font-bold bg-white hover:bg-[#eee7db] text-[#1c1917] border border-[#d8d0c5] transition-all cursor-pointer" data-cat="medical">
                    Medical / EMS
                </button>
                <button type="button" onclick="setCategoryFilter('police')" class="cat-filter-btn px-3.5 py-1.5 rounded-xl text-xs font-bold bg-white hover:bg-[#eee7db] text-[#1c1917] border border-[#d8d0c5] transition-all cursor-pointer" data-cat="police">
                    Police & Cordons
                </button>
                <button type="button" onclick="setCategoryFilter('fire')" class="cat-filter-btn px-3.5 py-1.5 rounded-xl text-xs font-bold bg-white hover:bg-[#eee7db] text-[#1c1917] border border-[#d8d0c5] transition-all cursor-pointer" data-cat="fire">
                    Fire & Rescue
                </button>
                <button type="button" onclick="setCategoryFilter('women')" class="cat-filter-btn px-3.5 py-1.5 rounded-xl text-xs font-bold bg-white hover:bg-[#eee7db] text-[#1c1917] border border-[#d8d0c5] transition-all cursor-pointer" data-cat="women">
                    Women Safety
                </button>
            </div>
        </section>

        <!-- Helplines Cards Grid -->
        <section class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5" id="contactsGrid">
            <?php foreach ($contacts as $c): ?>
                <?php
                $bgColors = [
                    'red' => 'bg-red-100 text-red-600 border-red-200',
                    'orange' => 'bg-orange-100 text-orange-600 border-orange-200',
                    'blue' => 'bg-blue-100 text-blue-600 border-blue-200',
                    'rose' => 'bg-rose-100 text-rose-600 border-rose-200',
                    'teal' => 'bg-teal-100 text-teal-600 border-teal-200',
                    'purple' => 'bg-purple-100 text-purple-600 border-purple-200',
                    'amber' => 'bg-amber-100 text-amber-600 border-amber-200',
                    'indigo' => 'bg-indigo-100 text-indigo-600 border-indigo-200',
                    'slate' => 'bg-slate-100 text-slate-600 border-slate-200',
                ];
                $iconStyle = $bgColors[$c['color']] ?? 'bg-slate-100 text-slate-600 border-slate-200';
                ?>
                <div class="contact-card bg-white rounded-3xl border border-[#d8d0c5] p-6 shadow-xs hover:shadow-md hover:border-teal-500 transition-all flex flex-col justify-between space-y-4" data-category="<?= $c['category'] ?>" data-name="<?= htmlspecialchars(strtolower($c['name'])) ?>" data-number="<?= htmlspecialchars($c['number']) ?>">
                    <div>
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div class="w-12 h-12 rounded-2xl border flex items-center justify-center text-2xl <?= $iconStyle ?>">
                                <i class="fa-solid <?= $c['icon'] ?>"></i>
                            </div>
                            <span class="px-2.5 py-1 rounded-full bg-[#f4f0ea] border border-[#d8d0c5] text-[10px] font-bold text-[#78716c] uppercase tracking-wider font-mono">
                                <?= htmlspecialchars($c['badge']) ?>
                            </span>
                        </div>

                        <h3 class="text-base font-bold text-[#000a1e] mb-1.5 leading-snug"><?= htmlspecialchars($c['name']) ?></h3>
                        <p class="text-xs text-[#586377] leading-relaxed mb-4"><?= htmlspecialchars($c['description']) ?></p>
                        
                        <div class="p-3.5 rounded-2xl bg-[#fbf9f5] border border-[#d8d0c5] flex items-center justify-between">
                            <div>
                                <span class="text-[10px] font-bold text-[#78716c] uppercase tracking-wider block">Dial Helpline</span>
                                <span class="text-2xl font-black text-[#000a1e] font-mono"><?= htmlspecialchars($c['number']) ?></span>
                            </div>
                            <button type="button" onclick="copyNumber('<?= htmlspecialchars($c['number']) ?>', this)" title="Copy Number" class="w-9 h-9 rounded-xl bg-white border border-[#d8d0c5] hover:bg-[#eee7db] text-slate-700 flex items-center justify-center transition-colors cursor-pointer text-xs">
                                <i class="fa-regular fa-copy"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Direct Actions -->
                    <div class="grid grid-cols-2 gap-2 pt-2 border-t border-[#eee7db]">
                        <a href="tel:<?= htmlspecialchars($c['number']) ?>" class="py-2.5 px-3 rounded-xl bg-red-600 hover:bg-red-500 text-white font-bold text-xs flex items-center justify-center gap-1.5 shadow-sm transition-all">
                            <i class="fa-solid fa-phone text-xs"></i>
                            <span>Call Now</span>
                        </a>
                        <a href="https://api.whatsapp.com/send?text=Emergency%20Helpline%20for%20<?= urlencode($c['name']) ?>:%20<?= urlencode($c['number']) ?>" target="_blank" class="py-2.5 px-3 rounded-xl bg-[#000a1e] hover:bg-[#11192e] text-white font-bold text-xs flex items-center justify-center gap-1.5 transition-all">
                            <i class="fa-brands fa-whatsapp text-xs text-emerald-400"></i>
                            <span>Share</span>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>

    </main>

    <!-- Toast Notification -->
    <div id="copyToast" class="fixed bottom-6 right-6 px-4 py-2.5 rounded-2xl bg-[#000a1e] text-white text-xs font-bold shadow-2xl border border-[#243049] flex items-center gap-2 transform translate-y-20 opacity-0 transition-all duration-300 z-50 pointer-events-none">
        <i class="fa-solid fa-circle-check text-emerald-400"></i>
        <span id="toastMessage">Number copied to clipboard!</span>
    </div>

    <!-- Footer -->
    <footer class="mt-auto bg-[#000a1e] border-t border-[#1c2b4e] py-4 text-center text-xs text-slate-400">
        <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-2">
            <span>&copy; 2026 DisasterSafe Crisis Management Suite • GeoGuardians</span>
            <div class="flex items-center gap-4 text-slate-300">
                <a href="citizen.php" class="hover:text-white">Emergency SOS</a>
                <span>•</span>
                <a href="citizen_guides.php" class="hover:text-white">Safety Guides</a>
            </div>
        </div>
    </footer>

    <script>
        let currentCategory = 'all';

        function setCategoryFilter(cat) {
            currentCategory = cat;

            // Highlight buttons
            document.querySelectorAll('.cat-filter-btn').forEach(btn => {
                if (btn.getAttribute('data-cat') === cat) {
                    btn.className = "cat-filter-btn px-3.5 py-1.5 rounded-xl text-xs font-bold bg-[#000a1e] text-white transition-all shadow-xs cursor-pointer";
                } else {
                    btn.className = "cat-filter-btn px-3.5 py-1.5 rounded-xl text-xs font-bold bg-white hover:bg-[#eee7db] text-[#1c1917] border border-[#d8d0c5] transition-all cursor-pointer";
                }
            });

            filterContacts();
        }

        function filterContacts() {
            const query = document.getElementById('contactSearchInput').value.toLowerCase().trim();
            const cards = document.querySelectorAll('.contact-card');

            cards.forEach(card => {
                const name = card.getAttribute('data-name');
                const num = card.getAttribute('data-number');
                const cat = card.getAttribute('data-category');

                const matchesCat = (currentCategory === 'all' || cat === currentCategory);
                const matchesQuery = (!query || name.includes(query) || num.includes(query));

                if (matchesCat && matchesQuery) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function copyNumber(num, btn) {
            navigator.clipboard.writeText(num).then(() => {
                showToast(`Helpline ${num} copied to clipboard!`);
            }).catch(() => {
                showToast(`Number: ${num}`);
            });
        }

        function showToast(msg) {
            const toast = document.getElementById('copyToast');
            document.getElementById('toastMessage').textContent = msg;
            toast.classList.remove('translate-y-20', 'opacity-0');
            toast.classList.add('translate-y-0', 'opacity-100');

            setTimeout(() => {
                toast.classList.remove('translate-y-0', 'opacity-100');
                toast.classList.add('translate-y-20', 'opacity-0');
            }, 2500);
        }
    </script>
</body>
</html>
