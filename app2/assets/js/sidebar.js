document.addEventListener('DOMContentLoaded', function() {
    // العناصر
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    
    // تبديل حالة القائمة الجانبية
    function toggleSidebar() {
        sidebar.classList.toggle('active');
        mainContent.classList.toggle('expanded');
    }
    
    // إضافة مستمع الحدث لزر التبديل
    sidebarToggle.addEventListener('click', toggleSidebar);
    
    // إغلاق القائمة عند النقر خارجها في الشاشات الصغيرة
    document.addEventListener('click', function(event) {
        const isClickInsideSidebar = sidebar.contains(event.target);
        const isClickOnToggle = sidebarToggle.contains(event.target);
        
        if (!isClickInsideSidebar && !isClickOnToggle && window.innerWidth <= 768) {
            sidebar.classList.remove('active');
            mainContent.classList.remove('expanded');
        }
    });
    
    // إعادة ضبط القائمة عند تغيير حجم النافذة
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('active');
            mainContent.classList.remove('expanded');
        }
    });
});