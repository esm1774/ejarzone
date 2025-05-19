<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/config.php';
require_once 'includes/Logger.php';
require_once 'includes/Mailer.php';

try {
    $mailer = Mailer::getInstance();
    echo "تم إنشاء كائن Mailer بنجاح\n";
    
    // محاولة إرسال بريد اختباري
    $result = $mailer->sendPasswordReset('test@example.com', 'http://localhost/test');
    if ($result) {
        echo "تم إرسال البريد بنجاح\n";
    } else {
        echo "فشل إرسال البريد\n";
    }
} catch (Exception $e) {
    echo "حدث خطأ: " . $e->getMessage() . "\n";
    echo "التتبع: " . $e->getTraceAsString() . "\n";
}
