<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * التحقق من تسجيل دخول المستخدم
 */
function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . getUrl('login.php'));
        exit();
    }
}

/**
 * تسجيل خروج المستخدم
 */
function logout() {
    session_destroy();
    header('Location: ' . getUrl('login.php'));
    exit();
}

/**
 * التحقق من صلاحيات المستخدم
 */
function checkPermission($permission) {
    if (!isset($_SESSION['user_permissions']) || !in_array($permission, $_SESSION['user_permissions'])) {
        header('HTTP/1.1 403 Forbidden');
        die('غير مصرح لك بالوصول إلى هذه الصفحة');
    }
}

/**
 * الحصول على معرف المستخدم الحالي
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * الحصول على اسم المستخدم الحالي
 */
function getCurrentUserName() {
    return $_SESSION['user_name'] ?? 'زائر';
}
