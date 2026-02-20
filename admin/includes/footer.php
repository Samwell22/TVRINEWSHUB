        </div>
    </main>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        // SIDEBAR TOGGLE (Mobile)
        (function() {
            const sidebar = document.getElementById('adminSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const toggle = document.getElementById('mobileToggle');
            
            function openSidebar() {
                sidebar.classList.add('open');
                overlay.classList.add('active');
                overlay.style.display = 'block';
                document.body.style.overflow = 'hidden';
            }
            
            function closeSidebar() {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
                setTimeout(() => { overlay.style.display = 'none'; }, 250);
                document.body.style.overflow = '';
            }
            
            if (toggle) {
                toggle.addEventListener('click', function() {
                    if (sidebar.classList.contains('open')) {
                        closeSidebar();
                    } else {
                        openSidebar();
                    }
                });
            }
            
            if (overlay) {
                overlay.addEventListener('click', closeSidebar);
            }
            
            // Close sidebar on nav link click (mobile)
            document.querySelectorAll('.sidebar-nav .nav-link').forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth < 992) {
                        closeSidebar();
                    }
                });
            });
        })();

        // INITIALIZE DATATABLES
        $(document).ready(function() {
            if ($('.data-table').length) {
                $('.data-table').DataTable({
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
                    },
                    pageLength: 25,
                    order: [[0, 'desc']]
                });
            }
        });
        
        // AUTO-HIDE ALERTS
        setTimeout(function() {
            $('.alert').each(function() {
                $(this).fadeOut('slow', function() { $(this).remove(); });
            });
        }, 5000);
        
        // CONFIRM DELETE
        $('.btn-delete').on('click', function(e) {
            if (!confirm('Yakin ingin menghapus data ini?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
