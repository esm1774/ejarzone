-- التحقق من وجود العمود email وحذفه إذا كان موجوداً
SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'login_attempts'
    AND COLUMN_NAME = 'email'
);

SET @sqlstmt := IF(
    @exists > 0,
    'ALTER TABLE login_attempts DROP COLUMN email',
    'SELECT "Column email does not exist"'
);

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- إضافة العمود username إذا لم يكن موجوداً
SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'login_attempts'
    AND COLUMN_NAME = 'username'
);

SET @sqlstmt := IF(
    @exists = 0,
    'ALTER TABLE login_attempts ADD COLUMN username VARCHAR(50) NOT NULL',
    'SELECT "Column username already exists"'
);

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- إضافة العمود attempt_time إذا لم يكن موجوداً
SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'login_attempts'
    AND COLUMN_NAME = 'attempt_time'
);

SET @sqlstmt := IF(
    @exists = 0,
    'ALTER TABLE login_attempts ADD COLUMN attempt_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
    'SELECT "Column attempt_time already exists"'
);

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- إضافة العمود user_agent إذا لم يكن موجوداً
SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'login_attempts'
    AND COLUMN_NAME = 'user_agent'
);

SET @sqlstmt := IF(
    @exists = 0,
    'ALTER TABLE login_attempts ADD COLUMN user_agent TEXT NULL',
    'SELECT "Column user_agent already exists"'
);

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- إضافة مؤشر على username و attempt_time للأداء الأفضل
ALTER TABLE login_attempts ADD INDEX idx_username_attempt (username, attempt_time, is_success);

-- حذف السجلات القديمة
DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 30 DAY);
