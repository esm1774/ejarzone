-- تحديث جدول المستخدمين
ALTER TABLE users
ADD COLUMN IF NOT EXISTS deactivation_reason VARCHAR(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS deactivation_date DATETIME DEFAULT NULL;

-- تحديث جدول محاولات تسجيل الدخول
ALTER TABLE login_attempts
ADD COLUMN IF NOT EXISTS username VARCHAR(50) NOT NULL,
ADD COLUMN IF NOT EXISTS attempt_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS user_agent VARCHAR(255) DEFAULT NULL;

-- إضافة مؤشر على username و attempt_time
ALTER TABLE login_attempts 
ADD INDEX IF NOT EXISTS idx_username_attempt (username, attempt_time, is_success);
