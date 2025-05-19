<?php
require_once 'config/config.php';
require_once 'includes/database.php';

try {
    $pdo = getDatabaseConnection();
    
    // إضافة أعمدة جديدة لجدول المستخدمين
    $pdo->exec("
        ALTER TABLE users 
        ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE,
        ADD COLUMN IF NOT EXISTS force_password_change BOOLEAN DEFAULT FALSE,
        ADD COLUMN IF NOT EXISTS temp_password VARCHAR(255) DEFAULT NULL
    ");
    
    // إنشاء جدول محاولات تسجيل الدخول
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            ip_address VARCHAR(45) NOT NULL,
            attempt_time DATETIME NOT NULL,
            is_success BOOLEAN DEFAULT FALSE,
            FOREIGN KEY (user_id) REFERENCES users(user_id)
        )
    ");
    
    echo "تم تحديث قاعدة البيانات بنجاح\n";
} catch(PDOException $e) {
    echo $e->getMessage();
}
