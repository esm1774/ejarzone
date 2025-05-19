-- إنشاء قاعدة البيانات
CREATE DATABASE IF NOT EXISTS rental_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rental_db;

-- جدول المستخدمين
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    role ENUM('مدير النظام', 'مدير', 'مشرف', 'مستخدم') NOT NULL DEFAULT 'مستخدم',
    status ENUM('نشط', 'غير نشط') DEFAULT 'نشط',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول الوحدات
CREATE TABLE units (
    unit_id INT PRIMARY KEY AUTO_INCREMENT,
    unit_name VARCHAR(100) NOT NULL,
    floor ENUM('الأرضي', 'الأول', 'الثاني', 'الثالث', 'الرابع') NOT NULL,
    building VARCHAR(50) NOT NULL,
    status ENUM('متاح', 'غير متاح') DEFAULT 'متاح',
    description TEXT,
    images TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول المستأجرين
CREATE TABLE tenants (
    tenant_id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(150) NOT NULL,
    nationality VARCHAR(50) NOT NULL,
    id_number VARCHAR(20) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    status ENUM('نشط', 'غير نشط') DEFAULT 'نشط',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول العقود
CREATE TABLE contracts (
    contract_id INT PRIMARY KEY AUTO_INCREMENT,
    unit_id INT NOT NULL,
    tenant_id INT NOT NULL,
    rent_amount DECIMAL(10,2) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    next_payment_date DATE NOT NULL,
    rent_type ENUM('يومي', 'شهري', 'نصف سنوي', 'سنوي') NOT NULL,
    status ENUM('ساري', 'منتهي') DEFAULT 'ساري',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (unit_id) REFERENCES units(unit_id) ON DELETE RESTRICT,
    FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول الإيرادات
CREATE TABLE revenues (
    revenue_id INT PRIMARY KEY AUTO_INCREMENT,
    contract_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_type ENUM('نقدي', 'تحويل بنكي', 'شيك') NOT NULL,
    description TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES contracts(contract_id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول المصروفات
CREATE TABLE expenses (
    expense_id INT PRIMARY KEY AUTO_INCREMENT,
    expense_type VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    expense_date DATE NOT NULL,
    payment_type ENUM('نقدي', 'تحويل بنكي', 'شيك') NOT NULL,
    description TEXT,
    unit_id INT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (unit_id) REFERENCES units(unit_id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول المدفوعات
CREATE TABLE payments (
    payment_id INT PRIMARY KEY AUTO_INCREMENT,
    contract_id INT,
    amount DECIMAL(10,2),
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payment_type ENUM('كاش', 'تحويل') NOT NULL,
    receipt_number VARCHAR(50),
    received_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES contracts(contract_id),
    FOREIGN KEY (received_by) REFERENCES users(user_id)
);

-- جدول الصلاحيات
CREATE TABLE permissions (
    permission_id INT PRIMARY KEY AUTO_INCREMENT,
    permission_name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول صلاحيات المستخدمين
CREATE TABLE user_permissions (
    user_id INT,
    permission_id INT,
    PRIMARY KEY (user_id, permission_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(permission_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إدخال الصلاحيات الأساسية
INSERT INTO permissions (permission_name, description) VALUES
('view_dashboard', 'عرض لوحة التحكم'),
('view_units', 'عرض الوحدات'),
('add_units', 'إضافة وحدات'),
('edit_units', 'تعديل الوحدات'),
('delete_units', 'حذف الوحدات'),
('view_tenants', 'عرض المستأجرين'),
('add_tenants', 'إضافة مستأجرين'),
('edit_tenants', 'تعديل المستأجرين'),
('delete_tenants', 'حذف المستأجرين'),
('view_contracts', 'عرض العقود'),
('add_contracts', 'إضافة عقود'),
('edit_contracts', 'تعديل العقود'),
('delete_contracts', 'حذف العقود'),
('view_revenues', 'عرض الإيرادات'),
('add_revenues', 'إضافة إيرادات'),
('edit_revenues', 'تعديل الإيرادات'),
('delete_revenues', 'حذف الإيرادات'),
('view_expenses', 'عرض المصروفات'),
('add_expenses', 'إضافة مصروفات'),
('edit_expenses', 'تعديل المصروفات'),
('delete_expenses', 'حذف المصروفات'),
('view_payments', 'عرض المدفوعات'),
('add_payments', 'إضافة مدفوعات'),
('edit_payments', 'تعديل المدفوعات'),
('delete_payments', 'حذف المدفوعات'),
('view_users', 'عرض المستخدمين'),
('add_users', 'إضافة مستخدمين'),
('edit_users', 'تعديل المستخدمين'),
('delete_users', 'حذف المستخدمين'),
('view_reports', 'عرض التقارير'),
('manage_settings', 'إدارة إعدادات النظام');

-- إنشاء حساب مدير النظام
INSERT INTO users (username, password, full_name, email, role, status) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مدير النظام', 'admin@example.com', 'مدير النظام', 'نشط');

-- إضافة جميع الصلاحيات لمدير النظام
INSERT INTO user_permissions (user_id, permission_id)
SELECT 1, permission_id FROM permissions;

-- Trigger لتحديث تاريخ الاستحقاق القادم في جدول العقود
DELIMITER //
CREATE TRIGGER update_contract_next_payment AFTER INSERT ON payments
FOR EACH ROW
BEGIN
    DECLARE contract_rent_type VARCHAR(20);
    
    -- الحصول على نوع الإيجار من العقد
    SELECT rent_type 
    INTO contract_rent_type
    FROM contracts
    WHERE contract_id = NEW.contract_id;
    
    -- تحديث تاريخ الاستحقاق القادم بناءً على تاريخ الدفع
    UPDATE contracts 
    SET next_payment_date = 
        CASE contract_rent_type
            WHEN 'يومي' THEN DATE_ADD(DATE(NEW.payment_date), INTERVAL 1 DAY)
            WHEN 'شهري' THEN DATE_ADD(DATE(NEW.payment_date), INTERVAL 1 MONTH)
            WHEN 'نصف سنوي' THEN DATE_ADD(DATE(NEW.payment_date), INTERVAL 6 MONTH)
            WHEN 'سنوي' THEN DATE_ADD(DATE(NEW.payment_date), INTERVAL 1 YEAR)
        END
    WHERE contract_id = NEW.contract_id;
END //
DELIMITER ;

-- Trigger لتعيين تاريخ الاستحقاق الأول عند إنشاء العقد
DELIMITER //
CREATE TRIGGER set_initial_payment_date BEFORE INSERT ON contracts
FOR EACH ROW
BEGIN
    SET NEW.next_payment_date = NEW.start_date;
END //
DELIMITER ;
