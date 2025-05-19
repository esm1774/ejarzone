-- إضافة عمود updated_at إلى جدول payments
ALTER TABLE payments 
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
