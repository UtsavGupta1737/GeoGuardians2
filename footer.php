<?php
// footer.php - Page Footer and Shared Scripts (Government Theme)
$flash = getFlash();
?>
    </div>

    <!-- Sidebar Drawer JavaScript Toggle Helper -->
    <script>
        function toggleSidebar() {
            toggleMainSidebar();
        }
    </script>

    <!-- SweetAlert2 Toast Notifications -->
    <?php if ($flash): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true,
                background: '#ffffff',
                color: '#0f172a',
                customClass: {
                    popup: 'border border-slate-200 shadow-xl rounded-2xl'
                },
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer)
                    toast.addEventListener('mouseleave', Swal.resumeTimer)
                }
            });

            Toast.fire({
                icon: <?= json_encode($flash['type'] === 'error' ? 'error' : ($flash['type'] === 'warning' ? 'warning' : 'success')) ?>,
                title: <?= json_encode($flash['message']) ?>
            });
        });
    </script>
    <?php endif; ?>
</body>
</html>
