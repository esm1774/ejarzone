-- حذف جميع الـ Triggers القديمة
DROP TRIGGER IF EXISTS update_contract_next_payment;
DROP TRIGGER IF EXISTS update_next_payment;
DROP TRIGGER IF EXISTS after_payment_insert;
DROP TRIGGER IF EXISTS update_next_due_date;
DROP TRIGGER IF EXISTS set_initial_due_date;

-- إنشاء Trigger لتحديث تاريخ الاستحقاق القادم
DELIMITER //
CREATE TRIGGER update_contract_next_payment AFTER INSERT ON payments
FOR EACH ROW
BEGIN
    DECLARE contract_rent_type VARCHAR(20);
    DECLARE current_due_date DATE;
    DECLARE debug_message TEXT;
    
    -- الحصول على نوع الإيجار وتاريخ الاستحقاق الحالي من العقد
    SELECT rent_type, next_due_date 
    INTO contract_rent_type, current_due_date
    FROM contracts
    WHERE contract_id = NEW.contract_id;
    
    -- تسجيل معلومات التشخيص
    SET debug_message = CONCAT('Trigger executing for payment_id: ', NEW.payment_id, 
                             ', contract_id: ', NEW.contract_id,
                             ', payment_date: ', NEW.payment_date,
                             ', current_due_date: ', current_due_date,
                             ', rent_type: ', contract_rent_type);
    
    INSERT INTO debug_log (message) VALUES (debug_message);
    
    -- تحديث تاريخ الاستحقاق القادم بناءً على تاريخ الاستحقاق الحالي
    UPDATE contracts 
    SET next_due_date = 
        CASE contract_rent_type
            WHEN 'يومي' THEN DATE_ADD(current_due_date, INTERVAL 1 DAY)
            WHEN 'شهري' THEN DATE_ADD(current_due_date, INTERVAL 1 MONTH)
            WHEN 'نصف سنوي' THEN DATE_ADD(current_due_date, INTERVAL 6 MONTH)
            WHEN 'سنوي' THEN DATE_ADD(current_due_date, INTERVAL 1 YEAR)
        END
    WHERE contract_id = NEW.contract_id;
    
    -- تحديث تاريخ الدفع القادم
    UPDATE contracts 
    SET next_payment_date = 
        CASE contract_rent_type
            WHEN 'يومي' THEN DATE_ADD(current_due_date, INTERVAL 1 DAY)
            WHEN 'شهري' THEN DATE_ADD(current_due_date, INTERVAL 1 MONTH)
            WHEN 'نصف سنوي' THEN DATE_ADD(current_due_date, INTERVAL 6 MONTH)
            WHEN 'سنوي' THEN DATE_ADD(current_due_date, INTERVAL 1 YEAR)
        END
    WHERE contract_id = NEW.contract_id;
    
    -- تسجيل تاريخ الاستحقاق الجديد
    SET debug_message = CONCAT('New next_due_date set to: ', 
                             (SELECT next_due_date FROM contracts WHERE contract_id = NEW.contract_id));
    INSERT INTO debug_log (message) VALUES (debug_message);
END //
DELIMITER ;

-- إنشاء جدول لسجلات التشخيص إذا لم يكن موجوداً
CREATE TABLE IF NOT EXISTS debug_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
