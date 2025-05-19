-- إضافة عمود last_payment_id إلى جدول contracts
ALTER TABLE contracts ADD COLUMN last_payment_id INT;

-- إضافة foreign key يربط مع جدول payments
ALTER TABLE contracts
ADD CONSTRAINT fk_last_payment
FOREIGN KEY (last_payment_id)
REFERENCES payments(payment_id)
ON DELETE SET NULL;
