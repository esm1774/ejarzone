<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../config/config.php";

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: " . getUrl('login.php'));
    exit();
}

// التحقق من الصلاحية
if (isset($requiredPermission) && !hasPermission($requiredPermission)) {
    $_SESSION['error'] = "ليس لديك صلاحية للوصول إلى هذه الصفحة";
    header("Location: " . getUrl('index.php'));
    exit();
}
