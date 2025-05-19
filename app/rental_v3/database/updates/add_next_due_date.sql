-- إضافة عمود تاريخ الاستحقاق القادم إلى جدول المدفوعات
ALTER TABLE payments ADD COLUMN next_due_date DATE AFTER payment_date;
