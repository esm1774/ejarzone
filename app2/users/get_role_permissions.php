<?php
require_once '../config/config.php';
require_once '../includes/database.php';

// التحقق من حالة الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// التحقق من تسجيل الدخول والصلاحيات
if (!isset($_SESSION['user_id']) || !hasPermission('manage_roles')) {
    http_response_code(403);
    exit('Forbidden');
}

// التحقق من وجود معرف الدور
if (!isset($_GET['role_id'])) {
    http_response_code(400);
    exit('Bad Request');
}

try {
    $db = getDatabaseConnection();
    
    // جلب صلاحيات الدور
    $stmt = $db->prepare("
        SELECT permission_id 
        FROM role_permissions 
        WHERE role_id = ?
    ");
    $stmt->execute([$_GET['role_id']]);
    $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // إرجاع النتيجة بتنسيق JSON
    header('Content-Type: application/json');
    echo json_encode($permissions);
    
} catch (Exception $e) {
    http_response_code(500);
    exit('Internal Server Error');
}
