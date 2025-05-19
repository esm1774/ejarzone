-- إنشاء جدول أنواع الإيرادات
CREATE TABLE IF NOT EXISTS revenue_types (
    type_id INT PRIMARY KEY AUTO_INCREMENT,
    type_name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- إدخال أنواع الإيرادات الافتراضية
INSERT INTO revenue_types (type_name, description) VALUES 
('عمولة', 'عمولة الوساطة العقارية'),
('رسوم عقد إلكتروني', 'رسوم توثيق العقد إلكترونياً'),
('سعي', 'رسوم السعي في العقار'),
('رسوم إدارية', 'رسوم إدارية متنوعة'),
('رسوم صيانة', 'رسوم خدمات الصيانة'),
('إيرادات أخرى', 'إيرادات متنوعة');

-- إنشاء جدول الإيرادات
CREATE TABLE IF NOT EXISTS revenues (
    revenue_id INT PRIMARY KEY AUTO_INCREMENT,
    receipt_number VARCHAR(20) UNIQUE,
    contract_id INT,
    type_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    description TEXT,
    payment_method ENUM('كاش', 'تحويل','شبكة') NOT NULL DEFAULT 'كاش',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (type_id) REFERENCES revenue_types(type_id),
    FOREIGN KEY (contract_id) REFERENCES contracts(contract_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
