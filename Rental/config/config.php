<?php
// تحديد المسار الأساسي للتطبيق
define('BASE_PATH', dirname(__DIR__));

// تحديد المسار الأساسي في URL
define('BASE_URL', '');

// دالة للحصول على المسار الكامل للملف
function getFullPath($path) {
    return BASE_PATH . '/Rental/' . ltrim($path, '/');
}

// دالة للحصول على URL الكامل
function getUrl($path) {
    return BASE_URL . '/Rental/' . ltrim($path, '/');
}

// تكوين قاعدة البيانات
define('DB_HOST', 'localhost');
define('DB_NAME', 'u402628076_sarh');
define('DB_USER', 'u402628076_admin');
define('DB_PASS', 'EsmM@1974');

// إعدادات البريد الإلكتروني
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-password');

// معلومات التطبيق
define('APP_NAME', 'نظام إدارة الشقق الفندقية');
define('APP_VERSION', '1.0.0');

// تهيئة session إذا لم تكن موجودة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
