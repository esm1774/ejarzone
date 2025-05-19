<?php
require_once dirname(__DIR__) . '/config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$messages = getMessages();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    
    <!-- CSS Dependencies -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo getUrl('assets/css/style.css'); ?>">
</head>
<body>
    <!-- Loading Indicator -->
    <div class="loading-indicator"></div>

    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container-fluid">
            <button class="btn btn-link sidebar-toggle p-0" id="sidebarToggle">
                <i class="bi bi-list fs-4"></i>
            </button>
            
            <a class="navbar-brand mx-3" href="<?php echo getUrl('index.php'); ?>">
                <?php echo APP_NAME; ?>
            </a>

            <div class="ms-auto d-flex align-items-center">
                <!-- Notifications -->
                <div class="dropdown me-3">
                    <button class="btn btn-link text-body border-0 p-0 position-relative" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-bell fs-5"></i>
                        <span class="badge bg-danger rounded-pill notification-badge">3</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end mt-2">
                        <li><a class="dropdown-item" href="#"><i class="bi bi-info-circle me-2"></i>إشعار جديد</a></li>
                        <li><a class="dropdown-item" href="#"><i class="bi bi-info-circle me-2"></i>طلب جديد</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#"><i class="bi bi-bell me-2"></i>كل الإشعارات</a></li>
                    </ul>
                </div>

                <!-- Theme Toggle -->
                <button class="btn btn-link text-body border-0 p-0 me-3 theme-toggle">
                    <i class="bi bi-moon-stars fs-5"></i>
                </button>

                <!-- User Menu -->
                <div class="dropdown">
                    <button class="btn btn-link text-body dropdown-toggle border-0 d-flex align-items-center" type="button" data-bs-toggle="dropdown">
                        <div class="user-avatar">
                            <?php echo substr($_SESSION['user_name'] ?? 'م', 0, 1); ?>
                        </div>
                        <span class="d-none d-md-inline"><?php echo $_SESSION['user_name'] ?? 'المستخدم'; ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end mt-2">
                        <li>
                            <a class="dropdown-item d-flex align-items-center" href="<?php echo getUrl('profile.php'); ?>">
                                <i class="bi bi-person-circle me-2"></i> الملف الشخصي
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center" href="<?php echo getUrl('settings.php'); ?>">
                                <i class="bi bi-gear me-2"></i> الإعدادات
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center text-danger" href="<?php echo getUrl('logout.php'); ?>">
                                <i class="bi bi-box-arrow-right me-2"></i> تسجيل الخروج
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="wrapper">        
        <div class="main-content">
            <?php if ($messages['error']): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-circle-fill me-2"></i>
                    <?php echo $messages['error']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($messages['success']): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?php echo $messages['success']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

    <!-- JavaScript Dependencies -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="/Rental/assets/js/script.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');

            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    const isMobile = window.innerWidth <= 768;

                    if (isMobile) {
                        sidebar.classList.toggle('show');
                    } else {
                        sidebar.classList.toggle('collapsed');
                        mainContent.classList.toggle('expanded');
                    }

                    // حفظ الحالة في localStorage
                    localStorage.setItem('sidebarState', sidebar.classList.contains('collapsed') ? 'collapsed' : 'expanded');
                });
            }

            // إغلاق الـ sidebar عند النقر خارج الـ sidebar على الشاشات الصغيرة
            document.addEventListener('click', function(event) {
                const isMobile = window.innerWidth <= 768;
                const isClickInsideSidebar = sidebar.contains(event.target);
                const isClickOnToggle = sidebarToggle.contains(event.target);

                if (isMobile && !isClickInsideSidebar && !isClickOnToggle) {
                    sidebar.classList.remove('show');
                }
            });

            // استرجاع حالة الـ sidebar من localStorage
            const sidebarState = localStorage.getItem('sidebarState');
            if (sidebarState === 'collapsed' && window.innerWidth > 768) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
            }
        });

        // Page Loading Indicator
        $(document).ready(function() {
            const loadingIndicator = $('.loading-indicator');

            loadingIndicator.addClass('active').fadeIn(200);

            $(window).on('load', function() {
                loadingIndicator.addClass('fade-out').fadeOut(200, function() {
                    $(this).removeClass('active fade-out');
                });
            });

            $('a:not([href^="#"])').click(function() {
                loadingIndicator.addClass('active').fadeIn(200);
            });

            // Initialize Select2
            $('.select2').select2({
                theme: 'bootstrap-5',
                dir: 'rtl'
            });
        });

        // Theme Toggle
        document.querySelector('.theme-toggle').addEventListener('click', function() {
            const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark';
            const themeIcon = this.querySelector('i');

            document.documentElement.setAttribute('data-bs-theme', isDarkMode ? 'light' : 'dark');
            themeIcon.classList.toggle('bi-moon-stars', isDarkMode);
            themeIcon.classList.toggle('bi-sun', !isDarkMode);

            localStorage.setItem('theme', isDarkMode ? 'light' : 'dark');
        });

        // Restore UI State
        document.addEventListener('DOMContentLoaded', function() {
            // Restore Theme
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', theme);
            const themeIcon = document.querySelector('.theme-toggle i');
            if (theme === 'dark') {
                themeIcon.classList.replace('bi-moon-stars', 'bi-sun');
            }
        });
    </script>
</body>
</html>