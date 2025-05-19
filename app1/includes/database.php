<?php
require_once dirname(__DIR__) . '/config/config.php';

function getDatabaseConnection() {
    try {
        error_log("محاولة الاتصال بقاعدة البيانات");
        error_log("Host: " . DB_HOST);
        error_log("Database: " . DB_NAME);
        error_log("User: " . DB_USER);
        
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        error_log("تم الاتصال بقاعدة البيانات بنجاح");
        return $pdo;
        
    } catch (PDOException $e) {
        error_log("خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        throw new Exception("فشل الاتصال بقاعدة البيانات: " . $e->getMessage());
    }
}
