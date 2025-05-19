    </div> <!-- End main-content -->
    </div> <!-- End wrapper -->

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Toggle Sidebar
        $('#sidebarToggle').click(function() {
            $('.sidebar').toggleClass('collapsed');
            $('.main-content').toggleClass('expanded');
            
            // Save state to localStorage
            const isSidebarCollapsed = $('.sidebar').hasClass('collapsed');
            localStorage.setItem('sidebarCollapsed', isSidebarCollapsed);
        });

        // Mobile Sidebar Toggle
        $('#sidebarToggleMobile').click(function() {
            $('.sidebar').toggleClass('show');
        });

        // Restore sidebar state from localStorage
        const savedState = localStorage.getItem('sidebarCollapsed');
        if (savedState === 'true') {
            $('.sidebar').addClass('collapsed');
            $('.main-content').addClass('expanded');
        }

        // Dropdown Menu Toggle
        $('.nav-item.has-submenu > .nav-link').click(function(e) {
            e.preventDefault();
            const $submenu = $(this).siblings('.submenu');
            
            // Close other open submenus
            $('.submenu').not($submenu).slideUp();
            $('.nav-item.has-submenu').not($(this).parent()).removeClass('active');
            
            // Toggle current submenu
            $submenu.slideToggle();
            $(this).parent().toggleClass('active');
        });

        // Close sidebar on mobile when clicking outside
        $(document).click(function(event) {
            if (!$(event.target).closest('.sidebar, #sidebarToggle').length) {
                $('.sidebar').removeClass('show');
            }
        });

        // Add active class to current page link
        const currentPage = window.location.pathname;
        $('.nav-link').each(function() {
            const linkPath = $(this).attr('href');
            if (linkPath && currentPage.includes(linkPath) && linkPath !== '#') {
                $(this).addClass('active');
                $(this).parents('.has-submenu').addClass('active');
                $(this).parents('.submenu').show();
            }
        });

        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function(tooltipTriggerEl) {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
    </script>
</body>
</html>
