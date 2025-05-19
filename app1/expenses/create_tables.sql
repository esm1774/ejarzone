-- إنشاء جدول أنواع المصروفات
CREATE TABLE IF NOT EXISTS expense_types (
    type_id INT PRIMARY KEY AUTO_INCREMENT,
    type_name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- إنشاء جدول المصروفات
CREATE TABLE IF NOT EXISTS expenses (
    expense_id INT PRIMARY KEY AUTO_INCREMENT,
    receipt_number VARCHAR(20) UNIQUE,
    type_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payee_name VARCHAR(255) NOT NULL,
    payment_date DATE NOT NULL,
    description TEXT,
    payment_method ENUM('كاش', 'تحويل', 'شبكة') NOT NULL DEFAULT 'كاش',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (type_id) REFERENCES expense_types(type_id)
);

-- إدخال بعض أنواع المصروفات الأساسية
INSERT INTO expense_types (type_name, description) VALUES
('صيانة', 'مصاريف صيانة وإصلاح الوحدات'),
('كهرباء', 'فواتير الكهرباء'),
('مياه', 'فواتير المياه'),
('نظافة', 'مصاريف النظافة والتعقيم'),
('أمن', 'مصاريف الأمن والحراسة'),
('أخرى', 'مصروفات متنوعة أخرى');
