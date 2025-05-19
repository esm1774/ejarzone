<nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
        <!-- زر القائمة للشاشات الصغيرة -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
            <i class="bi bi-list"></i>
        </button>

        <!-- زر تبديل الشريط الجانبي -->
        <button class="btn btn-link sidebar-toggle">
            <i class="bi bi-layout-sidebar-reverse"></i>
        </button>

        <div class="collapse navbar-collapse" id="navbarContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <!-- إشعارات -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-bell"></i>
                        <span class="badge bg-danger rounded-pill">3</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#">إشعار 1</a></li>
                        <li><a class="dropdown-item" href="#">إشعار 2</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#">عرض كل الإشعارات</a></li>
                    </ul>
                </li>

                <!-- الملف الشخصي -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i>
                        <?php echo $_SESSION['name'] ?? 'المستخدم'; ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?php echo getUrl('profile.php'); ?>">الملف الشخصي</a></li>
                        <li><a class="dropdown-item" href="<?php echo getUrl('settings.php'); ?>">الإعدادات</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo getUrl('logout.php'); ?>">تسجيل الخروج</a></li>
                    </ul>
                </li>

                <!-- زر تبديل الوضع المظلم -->
                <li class="nav-item">
                    <button class="btn btn-link nav-link theme-toggle">
                        <i class="bi bi-moon-stars"></i>
                    </button>
                </li>
            </ul>
        </div>
    </div>
</nav>

<script>
// تبديل الشريط الجانبي
document.querySelector('.sidebar-toggle').addEventListener('click', function() {
    document.body.classList.toggle('sidebar-collapsed');
    localStorage.setItem('sidebar-collapsed', document.body.classList.contains('sidebar-collapsed'));
});

// تبديل الوضع المظلم
document.querySelector('.theme-toggle').addEventListener('click', function() {
    if (document.documentElement.getAttribute('data-bs-theme') === 'dark') {
        document.documentElement.setAttribute('data-bs-theme', 'light');
        this.querySelector('i').classList.replace('bi-sun', 'bi-moon-stars');
        localStorage.setItem('theme', 'light');
    } else {
        document.documentElement.setAttribute('data-bs-theme', 'dark');
        this.querySelector('i').classList.replace('bi-moon-stars', 'bi-sun');
        localStorage.setItem('theme', 'dark');
    }
});

// استعادة حالة الشريط الجانبي والوضع المظلم
document.addEventListener('DOMContentLoaded', function() {
    if (localStorage.getItem('sidebar-collapsed') === 'true') {
        document.body.classList.add('sidebar-collapsed');
    }
    
    const theme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-bs-theme', theme);
    const themeIcon = document.querySelector('.theme-toggle i');
    if (theme === 'dark') {
        themeIcon.classList.replace('bi-moon-stars', 'bi-sun');
    }
});
</script>
