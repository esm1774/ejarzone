-- إضافة عمود method_id إلى جدول revenues
ALTER TABLE revenues ADD COLUMN method_id INT;

-- إضافة Foreign Key
ALTER TABLE revenues 
ADD CONSTRAINT fk_revenues_payment_methods 
FOREIGN KEY (method_id) REFERENCES payment_methods(method_id);

-- تحديث البيانات الموجودة
UPDATE revenues r
SET method_id = (
    SELECT method_id 
    FROM payment_methods pm 
    WHERE pm.method_name = r.payment_type 
    LIMIT 1
);

-- جعل العمود إجبارياً بعد تحديث البيانات
ALTER TABLE revenues MODIFY method_id INT NOT NULL;

-- حذف العمود القديم payment_type
ALTER TABLE revenues DROP COLUMN payment_type;
