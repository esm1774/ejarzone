<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

// التحقق من تسجيل الدخول
checkAuth();

// التحقق من وجود معرف المبنى
if (!isset($_GET['building_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'معرف المبنى مطلوب']);
    exit;
}

$building_id = $_GET['building_id'];

try {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare("
        SELECT u.unit_id, 
               CONCAT(u.unit_name, ' - الطابق ', u.floor) as unit_name
        FROM units u
        WHERE u.building_id = ?
        ORDER BY u.unit_name
    ");
    $stmt->execute([$building_id]);
    $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إرجاع النتائج بتنسيق JSON
    header('Content-Type: application/json');
    echo json_encode($units);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'حدث خطأ في قاعدة البيانات']);
}
