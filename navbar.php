<?php
// navbar.php - DisasterSafe Clean Minimalist Navigation Bar (No icons, small text boxes)
if (!isset($currentUser)) {
    $currentUser = getCurrentUser($pdo);
}
$flash = getFlash();
$currentScript = basename($_SERVER['PHP_SELF']);
?>
<!-- Top Navigation Header -->
<header class="bg-white border-b border-slate-200 sticky top-0 z-30 shrink-0">
    <div class="h-14 px-3 sm:px-4 lg:px-6 flex items-center justify-between gap-2">
        
        <!-- Left Section: Menu Toggle & Title -->
        <div class="flex items-center gap-2 sm:gap-3">
            
            <!-- Sidebar Drawer Toggle Button (Small text box) -->
            <button type="button" onclick="toggleMainSidebar()" 
                    class="px-2.5 py-1 rounded bg-slate-50 hover:bg-slate-100 border border-slate-300 text-[11px] font-bold text-slate-700 transition-colors focus:outline-none cursor-pointer"
                    title="Toggle Menu">
                Menu
            </button>

            <!-- Command Center Context Title -->
            <div class="flex items-center gap-2">
                <span class="text-xs sm:text-sm font-black text-slate-900 tracking-tight">
                    <?= htmlspecialchars(PAGE_TITLE) ?>
                </span>
            </div>

        </div>

        <!-- Right Section: Actions & Controls (Small minimalist boxes, text only) -->
        <div class="flex items-center gap-1.5 sm:gap-2">
            
            <!-- Global ESP32 Hardware Status / Connect Button (Superadmin Only) -->
            <?php if (isSuperAdmin($currentUser)): ?>
                <button type="button" id="globalEsp32NavBtn" onclick="toggleGlobalSerial()" 
                        class="px-2 py-1 rounded bg-slate-50 hover:bg-slate-100 border border-slate-300 text-[11px] font-mono font-bold text-slate-700 transition-colors cursor-pointer" 
                        title="Connect / Disconnect ESP32">
                    <span id="globalEsp32Text">ESP32</span>
                </button>
            <?php endif; ?>

            <!-- Live Alert Status Box -->
            <span class="px-2 py-1 rounded bg-red-50 border border-red-200 text-[10px] font-mono font-bold text-red-700">
                LIVE
            </span>

            <!-- Role Badge -->
            <span class="hidden sm:inline-block px-2 py-1 rounded bg-slate-100 border border-slate-200 text-[10px] font-mono font-bold text-slate-700 uppercase">
                <?= htmlspecialchars($currentUser['role_slug'] ?? 'user') ?>
            </span>

            <!-- Quick Switch Demo Role Dropdown -->
            <div class="relative" id="demoAccountsWrapper">
                <button type="button" onclick="document.getElementById('demoSwitchMenu').classList.toggle('hidden')" 
                        class="px-2.5 py-1 rounded bg-slate-50 hover:bg-slate-100 border border-slate-300 text-[11px] font-bold text-slate-700 transition-colors cursor-pointer">
                    Roles
                </button>
                <div id="demoSwitchMenu" class="hidden absolute right-0 mt-1.5 w-60 bg-white border border-slate-200 shadow-lg p-1.5 z-50 rounded">
                    <div class="px-2.5 py-1 text-[10px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-100 mono">
                        Switch Demo Role
                    </div>
                    <div class="space-y-0.5 mt-1 max-h-72 overflow-y-auto text-xs">
                        <a href="login.php?quick_login=superadmin@system.local" class="block px-2.5 py-1.5 rounded hover:bg-slate-100 text-slate-800 transition-colors">
                            <span class="font-bold block">Super Administrator</span>
                            <span class="text-[10px] text-slate-400 block">Supreme Command</span>
                        </a>
                        <a href="login.php?quick_login=ndrf.commander@disaster.local" class="block px-2.5 py-1.5 rounded hover:bg-slate-100 text-slate-800 transition-colors">
                            <span class="font-bold block">NDRF Force Commander</span>
                            <span class="text-[10px] text-slate-400 block">Tactical Response</span>
                        </a>
                        <a href="login.php?quick_login=police.command@disaster.local" class="block px-2.5 py-1.5 rounded hover:bg-slate-100 text-slate-800 transition-colors">
                            <span class="font-bold block">Police Commander</span>
                            <span class="text-[10px] text-slate-400 block">Perimeter Security</span>
                        </a>
                        <a href="login.php?quick_login=fire.chief@disaster.local" class="block px-2.5 py-1.5 rounded hover:bg-slate-100 text-slate-800 transition-colors">
                            <span class="font-bold block">Fire &amp; Rescue Chief</span>
                            <span class="text-[10px] text-slate-400 block">Fire Suppression</span>
                        </a>
                        <a href="login.php?quick_login=medical.dir@disaster.local" class="block px-2.5 py-1.5 rounded hover:bg-slate-100 text-slate-800 transition-colors">
                            <span class="font-bold block">Medical EMS Director</span>
                            <span class="text-[10px] text-slate-400 block">Hospital &amp; ICU</span>
                        </a>
                        <a href="login.php?quick_login=volunteer@disaster.local" class="block px-2.5 py-1.5 rounded hover:bg-slate-100 text-slate-800 transition-colors">
                            <span class="font-bold block">Volunteer Corps</span>
                            <span class="text-[10px] text-slate-400 block">Field Aid &amp; Relief</span>
                        </a>
                        <a href="login.php?quick_login=citizen@example.com" class="block px-2.5 py-1.5 rounded hover:bg-slate-100 text-slate-800 transition-colors">
                            <span class="font-bold block">Public Citizen</span>
                            <span class="text-[10px] text-slate-400 block">SOS Distress Beacon</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- User Profile Dropdown Button -->
            <div class="relative">
                <button type="button" onclick="document.getElementById('userMenu').classList.toggle('hidden')" 
                        class="px-2.5 py-1 rounded bg-slate-50 hover:bg-slate-100 border border-slate-300 text-[11px] font-bold text-slate-800 transition-colors focus:outline-none cursor-pointer">
                    <?= htmlspecialchars(explode(' ', $currentUser['name'] ?? 'User')[0]) ?>
                </button>

                <div id="userMenu" class="hidden absolute right-0 mt-1.5 w-48 bg-white border border-slate-200 shadow-lg p-1.5 z-50 space-y-0.5 rounded text-xs">
                    <div class="px-2.5 py-1.5 border-b border-slate-100">
                        <p class="font-bold text-slate-900 truncate"><?= htmlspecialchars($currentUser['name'] ?? 'User') ?></p>
                        <p class="text-[10px] text-slate-400 font-mono truncate"><?= htmlspecialchars($currentUser['email'] ?? '') ?></p>
                    </div>
                    <a href="profile.php" class="block px-2.5 py-1.5 rounded text-slate-700 hover:bg-slate-100 transition-colors font-medium">
                        Profile
                    </a>
                    <a href="logout.php" class="block px-2.5 py-1.5 rounded text-red-600 hover:bg-red-50 transition-colors font-medium">
                        Sign Out
                    </a>
                </div>
            </div>

            <!-- Dedicated Sign Out Box Button -->
            <a href="logout.php" title="Sign Out" class="px-2.5 py-1 rounded bg-red-50 hover:bg-red-100 border border-red-200 text-[11px] font-bold text-red-700 transition-colors">
                Logout
            </a>

        </div>
    </div>
</header>

<!-- Alert Toast Notification / Flash Messages -->
<?php if ($flash): ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 4500,
                timerProgressBar: true
            });
            Toast.fire({
                icon: '<?= $flash['type'] === 'error' ? 'error' : ($flash['type'] === 'warning' ? 'warning' : 'success') ?>',
                title: '<?= addslashes(htmlspecialchars($flash['message'])) ?>'
            });
        });
    </script>
<?php endif; ?>
