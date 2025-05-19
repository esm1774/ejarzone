<?php
require_once 'config/config.php';
require_once 'includes/database.php';

try {
    $pdo = getDatabaseConnection();
    
    // إضافة الأعمدة الجديدة إذا لم تكن موجودة
    $pdo->exec('
        ALTER TABLE users 
        ADD COLUMN IF NOT EXISTS otp_code VARCHAR(4) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS otp_expiry DATETIME DEFAULT NULL
    ');
    
    echo 'تم تحديث جدول المستخدمين بنجاح';
} catch(PDOException $e) {
    echo $e->getMessage();
}
