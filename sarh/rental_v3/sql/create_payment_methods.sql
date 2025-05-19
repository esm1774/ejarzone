-- إنشاء جدول طرق الدفع
CREATE TABLE IF NOT EXISTS payment_methods (
    method_id INT PRIMARY KEY AUTO_INCREMENT,
    method_name VARCHAR(50) NOT NULL UNIQUE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إضافة طرق الدفع الأساسية
INSERT INTO payment_methods (method_name) VALUES 
('تحويل بنكي'),
('شيك'),
('نقدي'),
('شبكة'),
('اخرى');
ON DUPLICATE KEY UPDATE method_name = VALUES(method_name);

-- إضافة عمود للإشارة إلى طريقة الدفع في جدول المدفوعات
ALTER TABLE payments 
ADD COLUMN method_id INT,
ADD FOREIGN KEY (method_id) REFERENCES payment_methods(method_id);

-- تحديث البيانات الموجودة
UPDATE payments p
JOIN payment_methods pm ON p.payment_type = pm.method_name
SET p.method_id = pm.method_id
WHERE p.payment_type IS NOT NULL;
