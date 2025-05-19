<?php
// تحديد المسار الأساسي للتطبيق
define('BASE_PATH', dirname(__DIR__));

// تحديد المسار الأساسي في URL
define('BASE_URL', '');

// دالة للحصول على المسار الكامل للملف
function getFullPath($path) {
    return BASE_PATH . '/sarh/' . ltrim($path, '/');
}

// دالة للحصول على URL الكامل
function getUrl($path) {
    return BASE_URL . '/sarh/' . ltrim($path, '/');
}

// تكوين قاعدة البيانات
define('DB_HOST', 'localhost');
define('DB_NAME', 'u402628076_sarh');
define('DB_USER', 'u402628076_admin');
define('DB_PASS', 'EsmM@1974');

// إعدادات SMTP
define('SMTP_HOST', 'smtp.titan.email');
define('SMTP_PORT', 587);
define('SMTP_USER', 'support@ejarzone.com');
define('SMTP_PASS', 'EsmM@1974'); // يجب تغيير هذا إلى كلمة المرور الحقيقية
define('SMTP_FROM_EMAIL', 'support@ejarzone.com'); // تم تغييره ليتطابق مع SMTP_USER
define('SMTP_FROM_NAME', 'الصرح - خدمة العملاء');
define('SMTP_ENCRYPTION', 'tls');
define('SMTP_AUTH', true);
define('SMTP_DEBUG', 0); // تغيير من 2 إلى 0 لإيقاف رسائل التصحيح


// // إعدادات SMTP لـ Gmail
// define('SMTP_HOST', 'smtp.gmail.com');
// define('SMTP_PORT', 587);
// define('SMTP_USER', 'esm1774@gmail.com');
// define('SMTP_PASS', 'EsmM@1974'); // يجب تغيير هذا إلى كلمة مرور التطبيق
// define('SMTP_FROM_EMAIL', 'esm1774@gmail.com');
// define('SMTP_FROM_NAME', 'الصرح');
// define('SMTP_ENCRYPTION', 'tls');
// define('SMTP_AUTH', true);
// define('SMTP_DEBUG', 2); // زيادة مستوى التصحيح لمعرفة المزيد من التفاصيل

// معلومات التطبيق
define('APP_NAME', 'الصرح');
define('APP_URL', 'https://ejarzone.com/al-sarh');
define('APP_VERSION', '1.0.0');
define('APP_TIMEZONE', 'Asia/Riyadh');

// تهيئة session إذا لم تكن موجودة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تعيين المنطقة الزمنية
date_default_timezone_set(APP_TIMEZONE);

// إنشاء اتصال قاعدة البيانات
require_once __DIR__ . '/database.php';
$database = new Database();
$GLOBALS['db'] = $database->getConnection();

// دالة للتحقق من تسجيل الدخول
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// دالة للتحقق من الصلاحيات
function hasPermission($permissionName) {
    // التحقق من تسجيل الدخول
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // التحقق من وجود الصلاحيات في الجلسة
    if (!isset($_SESSION['permissions'])) {
        try {
            // جلب صلاحيات الدور
            $stmt = $GLOBALS['db']->prepare("
                SELECT DISTINCT p.permission_name 
                FROM permissions p 
                INNER JOIN role_permissions rp ON p.permission_id = rp.permission_id
                INNER JOIN users u ON u.role_id = rp.role_id
                WHERE u.user_id = ?
                UNION
                SELECT DISTINCT p.permission_name 
                FROM permissions p 
                INNER JOIN user_permissions up ON p.permission_id = up.permission_id 
                WHERE up.user_id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
            $_SESSION['permissions'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            logError('خطأ في جلب الصلاحيات: ' . $e->getMessage(), [
                'user_id' => $_SESSION['user_id']
            ]);
            return false;
        }
    }
    
    return in_array($permissionName, $_SESSION['permissions']);
}

// دالة التوجيه
function redirect($path) {
    header("Location: " . getUrl($path));
    exit();
}

// دالة لعرض رسالة خطأ
function showError($message) {
    $_SESSION['error'] = $message;
}

// دالة لعرض رسالة نجاح
function showSuccess($message) {
    $_SESSION['success'] = $message;
}

// دالة للحصول على الرسائل
function getMessages() {
    $messages = [
        'error' => $_SESSION['error'] ?? null,
        'success' => $_SESSION['success'] ?? null
    ];
    
    unset($_SESSION['error'], $_SESSION['success']);
    return $messages;
}
