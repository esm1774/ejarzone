document.addEventListener('DOMContentLoaded', function() {
    // تهيئة المتغيرات
    const sidebarToggle = document.getElementById('sidebarToggle');
    const wrapper = document.querySelector('.wrapper');
    
    // تفعيل زر إظهار/إخفاء القائمة الجانبية
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            wrapper.classList.toggle('sidebar-collapsed');
            
            // حفظ حالة القائمة في localStorage
            localStorage.setItem('sidebarState', 
                wrapper.classList.contains('sidebar-collapsed') ? 'collapsed' : 'expanded'
            );
        });
    }

    // استعادة حالة القائمة الجانبية
    const sidebarState = localStorage.getItem('sidebarState');
    if (sidebarState === 'collapsed') {
        wrapper.classList.add('sidebar-collapsed');
    }

    // تفعيل القوائم المنسدلة
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    dropdownToggles.forEach(toggle => {
        // إزالة أي مثيلات سابقة من Dropdown
        const dropdownElement = toggle.closest('.dropdown');
        const existingDropdown = bootstrap.Dropdown.getInstance(toggle);
        if (existingDropdown) {
            existingDropdown.dispose();
        }
        
        // إنشاء مثيل جديد من Dropdown
        const dropdown = new bootstrap.Dropdown(toggle, {
            autoClose: true
        });

        // إضافة مستمع للنقر
        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.toggle();
        });

        // إضافة مستمع لحدث الفتح
        toggle.addEventListener('show.bs.dropdown', function() {
            dropdownElement.classList.add('show');
        });

        // إضافة مستمع لحدث الإغلاق
        toggle.addEventListener('hide.bs.dropdown', function() {
            dropdownElement.classList.remove('show');
        });
    });

    // إغلاق القوائم المنسدلة عند النقر خارجها
    document.addEventListener('click', function(e) {
        const target = e.target;
        if (!target.closest('.dropdown')) {
            dropdownToggles.forEach(toggle => {
                const dropdown = bootstrap.Dropdown.getInstance(toggle);
                if (dropdown) {
                    dropdown.hide();
                }
            });
        }
    });

    // تفعيل التنبيهات القابلة للإغلاق
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        const closeBtn = alert.querySelector('.btn-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                alert.classList.remove('show');
                setTimeout(() => {
                    alert.remove();
                }, 150);
            });
        }

        // إخفاء التنبيه تلقائياً بعد 5 ثواني
        setTimeout(() => {
            alert.classList.remove('show');
            setTimeout(() => {
                alert.remove();
            }, 150);
        }, 5000);
    });

    // إضافة مؤشر التحميل
    document.addEventListener('click', function(e) {
        if (e.target.tagName === 'A' && !e.target.hasAttribute('target')) {
            document.body.classList.add('loading');
        }
    });
});
