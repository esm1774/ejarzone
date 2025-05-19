<?php
require_once '../config/config.php';
require_once '../includes/Logger.php';

try {
    $pdo = getDatabaseConnection();
    $logger = Logger::getInstance();

    // إنشاء جدول password_resets
    $sql = "
    CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        token VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        used BOOLEAN DEFAULT FALSE,
        INDEX email_index (email),
        INDEX token_index (token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql);
    $logger->info('تم إنشاء جدول password_resets بنجاح');
    echo "تم إنشاء جدول password_resets بنجاح\n";

} catch (PDOException $e) {
    $logger->error('خطأ في إنشاء جدول password_resets: {message}', ['message' => $e->getMessage()]);
    echo "خطأ: " . $e->getMessage() . "\n";
}
