-- إضافة العمود الجديد إلى جدول العقود
ALTER TABLE contracts ADD COLUMN next_due_date DATE NOT NULL DEFAULT (CURRENT_DATE);

-- تحديث قيم next_due_date الحالية لتكون مساوية لتاريخ بداية العقد
UPDATE contracts SET next_due_date = start_date;

-- حذف Triggers القديمة إذا كانت موجودة
DROP TRIGGER IF EXISTS update_next_due_date;
DROP TRIGGER IF EXISTS set_initial_due_date;

-- إنشاء Trigger لتحديث تاريخ الاستحقاق القادم بعد كل دفعة
DELIMITER //
CREATE TRIGGER update_next_due_date AFTER INSERT ON payments
FOR EACH ROW
BEGIN
    DECLARE contract_rent_type VARCHAR(20);
    
    -- الحصول على نوع الإيجار من العقد
    SELECT rent_type INTO contract_rent_type
    FROM contracts
    WHERE contract_id = NEW.contract_id;
    
    -- تحديث تاريخ الاستحقاق القادم بناءً على نوع الإيجار
    UPDATE contracts 
    SET next_due_date = 
        CASE contract_rent_type
            WHEN 'يومي' THEN DATE_ADD(NEW.payment_date, INTERVAL 1 DAY)
            WHEN 'شهري' THEN DATE_ADD(NEW.payment_date, INTERVAL 1 MONTH)
            WHEN 'نصف سنوي' THEN DATE_ADD(NEW.payment_date, INTERVAL 6 MONTH)
            WHEN 'سنوي' THEN DATE_ADD(NEW.payment_date, INTERVAL 1 YEAR)
        END
    WHERE contract_id = NEW.contract_id;
END //
DELIMITER ;

-- إنشاء Trigger لتعيين تاريخ الاستحقاق الأول عند إنشاء العقد
DELIMITER //
CREATE TRIGGER set_initial_due_date BEFORE INSERT ON contracts
FOR EACH ROW
BEGIN
    SET NEW.next_due_date = NEW.start_date;
END //
DELIMITER ;
