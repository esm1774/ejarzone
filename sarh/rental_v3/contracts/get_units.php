<?php
require_once '../includes/session.php';
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

// التحقق من تسجيل الدخول
checkAuth();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['building_id']) || !is_numeric($_GET['building_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'معرف المبنى غير صحيح']);
    exit;
}

$building_id = $_GET['building_id'];

try {
    $pdo = getDatabaseConnection();
    
    // جلب الوحدات الشاغرة للمبنى المحدد
    $stmt = $pdo->prepare("
        SELECT unit_id, 
               CONCAT(unit_name, ' - الطابق ', floor) as unit_name
        FROM units 
        WHERE building_id = ? AND status = 'شاغرة'
        ORDER BY floor, unit_name
    ");
    
    $stmt->execute([$building_id]);
    $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($units);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'حدث خطأ في قاعدة البيانات']);
}
?>
