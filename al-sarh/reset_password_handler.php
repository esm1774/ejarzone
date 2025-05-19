<?php
// منع عرض أخطاء PHP للمستخدم
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

require_once 'config/config.php';
require_once 'includes/database.php';

// بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// التأكد من أن الطلب POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

try {
    // التحقق من CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        throw new Exception('رمز CSRF غير صالح');
    }

    // التحقق من وجود البيانات المطلوبة
    if (!isset($_POST['token'], $_POST['password'], $_POST['confirm_password'])) {
        throw new Exception('جميع الحقول مطلوبة');
    }

    $token = $_POST['token'];
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    // التحقق من تطابق كلمتي المرور
    if ($password !== $confirmPassword) {
        throw new Exception('كلمتا المرور غير متطابقتين');
    }

    // التحقق من قوة كلمة المرور
    if (strlen($password) < 8) {
        throw new Exception('يجب أن تكون كلمة المرور 8 أحرف على الأقل');
    }
    if (!preg_match('/[A-Z]/', $password)) {
        throw new Exception('يجب أن تحتوي كلمة المرور على حرف كبير واحد على الأقل');
    }
    if (!preg_match('/[a-z]/', $password)) {
        throw new Exception('يجب أن تحتوي كلمة المرور على حرف صغير واحد على الأقل');
    }
    if (!preg_match('/[0-9]/', $password)) {
        throw new Exception('يجب أن تحتوي كلمة المرور على رقم واحد على الأقل');
    }
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        throw new Exception('يجب أن تحتوي كلمة المرور على رمز خاص واحد على الأقل');
    }

    // الاتصال بقاعدة البيانات
    $db = getDatabaseConnection();

    // التحقق من صلاحية الرمز
    $stmt = $db->prepare("SELECT user_id FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('رابط إعادة تعيين كلمة المرور غير صالح أو منتهي الصلاحية');
    }

    // تحديث كلمة المرور
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("
        UPDATE users 
        SET password = ?, 
            reset_token = NULL, 
            reset_token_expiry = NULL
        WHERE user_id = ?
    ");
    
    if (!$stmt->execute([$hashedPassword, $user['user_id']])) {
        throw new Exception('فشل في تحديث كلمة المرور');
    }

    // إرسال الاستجابة
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'message' => 'تم تحديث كلمة المرور بنجاح'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("خطأ في reset_password_handler.php: " . $e->getMessage());
    
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
