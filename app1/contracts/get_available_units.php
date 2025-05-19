<?php
require_once '../includes/session.php';
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

// التحقق من تسجيل الدخول
checkAuth();

if (!isset($_POST['building_id'])) {
    echo json_encode([]);
    exit;
}

$db = getDatabaseConnection();
$building_id = $_POST['building_id'];

try {
    // جلب الوحدات الشاغرة للمبنى المحدد
    $query = "SELECT u.unit_id, u.unit_name, u.status 
              FROM units u 
              WHERE u.building_id = :building_id 
              AND u.status = 'شاغرة' 
              ORDER BY u.unit_name";
    $stmt = $db->prepare($query);
    $stmt->execute([':building_id' => $building_id]);
    $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($units);
} catch (PDOException $e) {
    echo json_encode([]);
}
