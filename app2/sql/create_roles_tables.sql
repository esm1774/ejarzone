-- إنشاء جدول الأدوار
CREATE TABLE IF NOT EXISTS roles (
    role_id INT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إنشاء جدول صلاحيات الأدوار
CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT,
    permission_id INT,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(permission_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إضافة الأدوار الافتراضية
INSERT INTO roles (role_name, description) VALUES
('admin', 'مدير النظام - كافة الصلاحيات'),
('manager', 'مدير - صلاحيات إدارية محدودة'),
('user', 'مستخدم - صلاحيات أساسية');

-- تحديث جدول المستخدمين لربطه بجدول الأدوار
ALTER TABLE users 
MODIFY COLUMN role VARCHAR(50),
ADD CONSTRAINT fk_users_roles 
FOREIGN KEY (role) REFERENCES roles(role_name) ON UPDATE CASCADE;
