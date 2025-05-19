-- إضافة الأعمدة الجديدة لجدول المستخدمين
ALTER TABLE users
ADD COLUMN deactivation_reason VARCHAR(255) NULL,
ADD COLUMN deactivation_date DATETIME NULL;
