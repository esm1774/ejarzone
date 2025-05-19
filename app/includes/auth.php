<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';

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
 * التحقق من صلاحيات المستخدم مع إظهار رسالة خطأ
 */
function checkPermission($permission) {
    if (!hasPermission($permission)) {
        addMessage('error', 'غير مصرح لك بالوصول إلى هذه الصفحة');
        header('Location: index.php');
        exit();
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
