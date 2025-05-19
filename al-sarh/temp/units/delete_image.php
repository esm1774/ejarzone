<?php
require_once '../includes/session.php';
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

// التحقق من تسجيل الدخول
checkAuth();

// التحقق من الصلاحيات
checkPermission('edit_units');

// التحقق من أن الطلب هو POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// قراءة البيانات المرسلة
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['unit_id']) || !isset($data['image_name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

$unit_id = $data['unit_id'];
$image_name = $data['image_name'];

try {
    $pdo = getDatabaseConnection();

    // جلب بيانات الوحدة
    $stmt = $pdo->prepare("SELECT images FROM units WHERE unit_id = ?");
    $stmt->execute([$unit_id]);
    $unit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$unit) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Unit not found']);
        exit;
    }

    // تحديث قائمة الصور
    $images = explode(',', $unit['images']);
    $key = array_search($image_name, $images);
    
    if ($key !== false) {
        // حذف الصورة من المصفوفة
        unset($images[$key]);
        
        // حذف الملف من المجلد
        $file_path = "../uploads/units/" . $image_name;
        if (file_exists($file_path)) {
            unlink($file_path);
        }

        // تحديث قاعدة البيانات
        $stmt = $pdo->prepare("UPDATE units SET images = ? WHERE unit_id = ?");
        if ($stmt->execute([implode(',', $images), $unit_id])) {
            echo json_encode(['success' => true]);
            exit;
        }
    }

    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Image not found']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
