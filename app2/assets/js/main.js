// Document Ready Handler
document.addEventListener('DOMContentLoaded', function() {
    initializeSidebar();
    initializeLoadingIndicator();
    initializeSelect2();
    initializeSubmenuBehavior();
    setActiveNavLink();
    initializeThemeToggle();
    initializeFormValidation();
});

// Sidebar Functionality
function initializeSidebar() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');

    // استرجاع حالة القائمة من localStorage
    const sidebarState = localStorage.getItem('sidebarState');
    if (sidebarState === 'collapsed' && window.innerWidth > 768) {
        sidebar.classList.add('collapsed');
        mainContent.classList.add('expanded');
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            const isMobile = window.innerWidth <= 768;

            if (isMobile) {
                sidebar.classList.toggle('show');
            } else {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
            }

            localStorage.setItem('sidebarState', 
                sidebar.classList.contains('collapsed') ? 'collapsed' : 'expanded'
            );
        });
    }

    // إغلاق القائمة عند النقر خارجها على الشاشات الصغيرة
    document.addEventListener('click', function(event) {
        const isMobile = window.innerWidth <= 768;
        const isClickInsideSidebar = sidebar.contains(event.target);
        const isClickOnToggle = sidebarToggle.contains(event.target);

        if (isMobile && !isClickInsideSidebar && !isClickOnToggle) {
            sidebar.classList.remove('show');
        }
    });
}

// Loading Indicator
function initializeLoadingIndicator() {
    const loadingIndicator = $('.loading-indicator');

    loadingIndicator.fadeIn(200);
    
    $(window).on('load', function() {
        loadingIndicator.fadeOut(200);
    });

    // إظهار مؤشر التحميل عند التنقل
    $('a:not([href^="#"])').click(function() {
        loadingIndicator.fadeIn(200);
    });
}

// Initialize Select2
function initializeSelect2() {
    if (typeof $.fn.select2 !== 'undefined') {
        $('.select2').select2({
            theme: 'bootstrap-5',
            dir: 'rtl',
            width: '100%'
        });
    }
}

// Submenu Behavior
function initializeSubmenuBehavior() {
    const submenus = document.querySelectorAll('.submenu');
    
    submenus.forEach(submenu => {
        submenu.addEventListener('show.bs.collapse', function() {
            // إغلاق القوائم المنسدلة الأخرى
            submenus.forEach(otherSubmenu => {
                if (otherSubmenu !== submenu && otherSubmenu.classList.contains('show')) {
                    bootstrap.Collapse.getInstance(otherSubmenu).hide();
                }
            });
        });
    });
}

// Set Active Nav Link
function setActiveNavLink() {
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        if (link.getAttribute('href') && currentPath.includes(link.getAttribute('href'))) {
            link.classList.add('active');
            // تفعيل القائمة الأم إذا كانت موجودة
            const parentSubmenu = link.closest('.submenu');
            if (parentSubmenu) {
                parentSubmenu.classList.add('show');
                const parentNavItem = parentSubmenu.previousElementSibling;
                if (parentNavItem) {
                    parentNavItem.classList.add('active');
                }
            }
        }
    });
}

// Theme Toggle
function initializeThemeToggle() {
    const themeToggle = document.querySelector('.theme-toggle');
    
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark';
            const themeIcon = this.querySelector('i');

            document.documentElement.setAttribute('data-bs-theme', isDarkMode ? 'light' : 'dark');
            themeIcon.classList.toggle('bi-moon-stars', isDarkMode);
            themeIcon.classList.toggle('bi-sun', !isDarkMode);

            localStorage.setItem('theme', isDarkMode ? 'light' : 'dark');
        });

        // Restore theme preference
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-bs-theme', savedTheme);
        const themeIcon = themeToggle.querySelector('i');
        themeIcon.classList.toggle('bi-sun', savedTheme === 'dark');
        themeIcon.classList.toggle('bi-moon-stars', savedTheme === 'light');
    }
}

// Form Validation
function initializeFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
}

// Utility Functions
function showAlert(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    const alertContainer = document.querySelector('.alert-container') || document.querySelector('.container');
    if (alertContainer) {
        alertContainer.insertBefore(alertDiv, alertContainer.firstChild);
        
        setTimeout(() => {
            alertDiv.classList.add('show');
        }, 100);

        setTimeout(() => {
            alertDiv.classList.remove('show');
            setTimeout(() => alertDiv.remove(), 300);
        }, 5000);
    }
}
