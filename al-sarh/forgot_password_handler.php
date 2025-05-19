<?php
// منع أي مخرجات قبل JSON
ob_start();

try {
    // تكوين تسجيل الأخطاء
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/error.log');

    // تسجيل معلومات التشخيص
    error_log("=== بدء تنفيذ forgot_password_handler.php ===");
    error_log("المسار الحالي: " . __DIR__);
    error_log("المسار الكامل للملف: " . __FILE__);
    error_log("POST data: " . print_r($_POST, true));

    // تضمين الملفات المطلوبة
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/includes/database.php';
    require_once __DIR__ . '/includes/Validator.php';
    require_once __DIR__ . '/includes/Mailer.php';
    require_once __DIR__ . '/includes/Logger.php';

    // بدء الجلسة
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // التحقق من البيانات
    if (!isset($_POST['email']) || empty($_POST['email'])) {
        throw new Exception('البريد الإلكتروني مطلوب');
    }

    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('البريد الإلكتروني غير صالح');
    }

    // الاتصال بقاعدة البيانات
    $db = getDatabaseConnection();
    
    // إضافة الأعمدة المطلوبة إذا لم تكن موجودة
    try {
        error_log("التحقق من وجود الأعمدة المطلوبة في جدول users");
        
        $db->exec("
            ALTER TABLE users 
            ADD COLUMN IF NOT EXISTS reset_token VARCHAR(255) NULL,
            ADD COLUMN IF NOT EXISTS reset_token_expiry DATETIME NULL
        ");
        
        error_log("تم التحقق من/إضافة الأعمدة المطلوبة بنجاح");
    } catch (PDOException $e) {
        error_log("خطأ في إضافة الأعمدة: " . $e->getMessage());
        throw new Exception("حدث خطأ في تحديث قاعدة البيانات");
    }
    
    // التحقق من وجود البريد في قاعدة البيانات
    error_log("جاري التحقق من البريد الإلكتروني: " . $email);
    $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if (!$stmt->fetch()) {
        throw new Exception('البريد الإلكتروني غير مسجل في النظام');
    }

    // إنشاء رمز إعادة تعيين كلمة المرور
    $token = bin2hex(random_bytes(32));
    $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // حفظ الرمز في قاعدة البيانات
    error_log("جاري حفظ رمز إعادة التعيين للبريد: " . $email);
    $stmt = $db->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE email = ?");
    $stmt->execute([$token, $expiry, $email]);

    // إنشاء رابط إعادة تعيين كلمة المرور
    $resetLink = rtrim(APP_URL, '/') . '/reset_password.php?token=' . urlencode($token);
    
    // إرسال البريد الإلكتروني
    error_log("جاري إرسال بريد إعادة تعيين كلمة المرور");
    $mailer = Mailer::getInstance();
    $mailer->sendPasswordReset($email, $resetLink);

    // تنظيف أي مخرجات سابقة
    ob_clean();
    
    // إرسال الاستجابة
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'message' => 'تم إرسال رابط إعادة تعيين كلمة المرور إلى بريدك الإلكتروني'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("خطأ في forgot_password_handler.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // تنظيف أي مخرجات سابقة
    ob_clean();
    
    // إرسال رسالة الخطأ
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
