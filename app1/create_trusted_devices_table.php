<?php
require_once 'config/config.php';
require_once 'includes/database.php';

try {
    $pdo = getDatabaseConnection();
    
    // إنشاء جدول الأجهزة الموثوقة
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS trusted_devices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            device_identifier VARCHAR(255) NOT NULL,
            browser_info VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            last_used DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id),
            UNIQUE KEY unique_device (user_id, device_identifier)
        )
    ');
    
    echo 'تم إنشاء جدول الأجهزة الموثوقة بنجاح';
} catch(PDOException $e) {
    echo $e->getMessage();
}
