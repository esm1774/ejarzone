<?php
// التحقق من وجود role في الجلسة
$userRole = $_SESSION['role'] ?? 'مدير';

// قائمة بالصفحات المسموح بها لكل دور
$rolePermissions = [
    'مدير' => ['dashboard', 'units', 'tenants', 'contracts', 'payments', 'reports', 'expenses', 'revenues', 'users', 'settings'],
    // 'مدير' => ['dashboard', 'units', 'tenants', 'contracts', 'payments', 'reports', 'expenses', 'revenues'],
    'مستخدم' => ['dashboard', 'units', 'tenants', 'contracts', 'payments', 'expenses']
];

// الحصول على الصفحات المسموح بها للمستخدم الحالي
$allowedPages = $rolePermissions[$userRole] ?? $rolePermissions['مدير'];

// الحصول على الصفحة الحالية
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>

<div class="sidebar">
    <div class="sidebar-header">
        <button class="btn btn-link text-dark p-0 border-0" id="sidebarToggle">
            <i class="bi bi-list fs-4"></i>
        </button>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <?php if (in_array('dashboard', $allowedPages)): ?>
            <a class="nav-link" href="<?php echo getUrl('index.php'); ?>">
                <i class="bi bi-house-door"></i>
                <span>الرئيسية</span>
            </a>
            <?php endif; ?>
        </li>
        
        <li class="nav-item has-submenu">
            <?php if (in_array('units', $allowedPages)): ?>
            <a class="nav-link" href="#">
                <i class="bi bi-building"></i>
                <span>إدارة الوحدات</span>
            </a>
            <ul class="submenu nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo getUrl('units/index.php'); ?>">
                        <i class="bi bi-list-ul"></i>
                        <span>عرض الوحدات</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo getUrl('units/add.php'); ?>">
                        <i class="bi bi-plus-lg"></i>
                        <span>إضافة وحدة</span>
                    </a>
                </li>
            </ul>
            <?php endif; ?>
        </li>

        <li class="nav-item has-submenu">
            <?php if (in_array('tenants', $allowedPages)): ?>
            <a class="nav-link" href="#">
                <i class="bi bi-people"></i>
                <span>إدارة المستأجرين</span>
            </a>
            <ul class="submenu nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo getUrl('tenants/index.php'); ?>">
                        <i class="bi bi-list-ul"></i>
                        <span>عرض المستأجرين</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo getUrl('tenants/add.php'); ?>">
                        <i class="bi bi-plus-lg"></i>
                        <span>إضافة مستأجر</span>
                    </a>
                </li>
            </ul>
            <?php endif; ?>
        </li>

        <li class="nav-item has-submenu">
            <?php if (in_array('contracts', $allowedPages)): ?>
            <a class="nav-link" href="#">
                <i class="bi bi-file-text"></i>
                <span>إدارة العقود</span>
            </a>
            <ul class="submenu nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo getUrl('contracts/index.php'); ?>">
                        <i class="bi bi-list-ul"></i>
                        <span>عرض العقود</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo getUrl('contracts/add.php'); ?>">
                        <i class="bi bi-plus-lg"></i>
                        <span>إضافة عقد</span>
                    </a>
                </li>
            </ul>
            <?php endif; ?>
        </li>

        <li class="nav-item has-submenu">
            <?php if (in_array('payments', $allowedPages)): ?>
            <a class="nav-link" href="#">
                <i class="bi bi-cash-coin"></i>
                <span>إدارة الإيجارات</span>
            </a>
            <ul class="submenu nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo getUrl('payments/index.php'); ?>">
                        <i class="bi bi-list-ul"></i>
                        <span>عرض الإيجارات</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo getUrl('payments/add.php'); ?>">
                        <i class="bi bi-plus-lg"></i>
                        <span>إضافة إيجار</span>
                    </a>
                </li>
            </ul>
            <?php endif; ?>
        </li>

        <li class="nav-item has-submenu">
            <?php if (in_array('revenues', $allowedPages)): ?>
            <a class="nav-link" href="#">
                <i class="bi bi-cash-coin "></i>
                <span>إدارة الإيرادات</span>
            </a>
            <ul class="submenu nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo getUrl('revenues/index.php'); ?>">
                        <i class="bi bi-list-ul"></i>
                        <span>عرض الإيرادات</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo getUrl('revenues/add.php'); ?>">
                        <i class="bi bi-plus-lg"></i>
                        <span>إضافة إيراد</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo getUrl('revenues/types.php'); ?>">
                        <i class="bi bi-list-check"></i>
                        <span>أنواع الإيرادات</span>
                    </a>
                </li>
            </ul>
            <?php endif; ?>
        </li>

        <li class="nav-item has-submenu">
            <?php if (in_array('expenses', $allowedPages)): ?>
            <a class="nav-link" href="#">
                <i class="bi bi-cash-stack "></i>
                <span>إدارة المصروفات</span>
            </a>
            <ul class="submenu nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo getUrl('expenses/index.php'); ?>">
                        <i class="bi bi-list-ul"></i>
                        <span>عرض المصروفات</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo getUrl('expenses/add.php'); ?>">
                        <i class="bi bi-plus-lg"></i>
                        <span>إضافة مصروف</span>
                    </a>
                </li>
            </ul>
            <?php endif; ?>
        </li>

        

        <li class="nav-item">
            <?php if (in_array('reports', $allowedPages)): ?>
            <a class="nav-link" href="<?php echo getUrl('reports/index.php'); ?>">
                <i class="bi bi-graph-up"></i>
                <span>التقارير</span>
            </a>
            <?php endif; ?>
        </li>

        <li class="nav-item">
            <?php if (in_array('users', $allowedPages)): ?>
            <a class="nav-link" href="<?php echo getUrl('users/index.php'); ?>">
                <i class="bi bi-people"></i>
                <span>إدارة المستخدمين</span>
            </a>
            <?php endif; ?>
        </li>

        <li class="nav-item">
            <?php if (in_array('settings', $allowedPages)): ?>
            <a class="nav-link" href="<?php echo getUrl('settings.php'); ?>">
                <i class="bi bi-gear"></i>
                <span>الإعدادات</span>
            </a>
            <?php endif; ?>
        </li>

        <li class="nav-item">
            <a class="nav-link text-danger" href="<?php echo getUrl('logout.php'); ?>">
                <i class="bi bi-box-arrow-right"></i>
                <span>تسجيل الخروج</span>
            </a>
        </li>
    </ul>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // استرجاع حالة القائمة من localStorage
    const sidebarState = localStorage.getItem('sidebarState');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    
    if (sidebarState === 'collapsed') {
        sidebar.classList.add('collapsed');
        mainContent.classList.add('expanded');
    }
    
    // تبديل حالة القائمة
    document.getElementById('sidebarToggle').addEventListener('click', function() {
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('expanded');
        
        // حفظ الحالة في localStorage
        localStorage.setItem('sidebarState', 
            sidebar.classList.contains('collapsed') ? 'collapsed' : 'expanded'
        );
    });
    
    // إغلاق القوائم المنسدلة الأخرى عند فتح قائمة جديدة
    const submenus = document.querySelectorAll('.submenu');
    submenus.forEach(submenu => {
        submenu.addEventListener('show.bs.collapse', function() {
            submenus.forEach(otherSubmenu => {
                if (otherSubmenu !== submenu && otherSubmenu.classList.contains('show')) {
                    bootstrap.Collapse.getInstance(otherSubmenu).hide();
                }
            });
        });
    });
    
    // تحديد القائمة النشطة بناءً على الصفحة الحالية
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        if (link.getAttribute('href') && currentPath.includes(link.getAttribute('href'))) {
            link.classList.add('active');
            // فتح القائمة المنسدلة الأم إذا كانت موجودة
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
});
</script>
