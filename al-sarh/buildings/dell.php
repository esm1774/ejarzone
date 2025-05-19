<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

// التحقق من صلاحية حذف مبنى
if (!hasPermission('delete_building')) {
    addMessage('error', 'عذراً، ليس لديك صلاحية لحذف المبنى');
    redirect('index.php');
}

// التحقق من وجود معرف المبنى
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    addMessage('error', 'معرف المبنى غير صحيح');
    redirect('index.php');
}

$building_id = (int)$_GET['id'];

// إنشاء اتصال بقاعدة البيانات
$database = new Database();
$db = $database->getConnection();

try {
    // التحقق من وجود وحدات مرتبطة بالمبنى
    $query = "SELECT COUNT(*) as unit_count FROM units WHERE building_id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $building_id);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['unit_count'] > 0) {
        addMessage('error', 'لا يمكن حذف المبنى لأنه يحتوي على وحدات مرتبطة به');
        redirect('index.php');
    }
    
    // حذف المبنى
    $query = "DELETE FROM buildings WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $building_id);
    
    if ($stmt->execute()) {
        addMessage('success', 'تم حذف المبنى بنجاح');
        header("Location: index.php?success=2"); 
        exit();
    } else {
        addMessage('error', 'حدث خطأ أثناء حذف المبنى');
    }
} catch(PDOException $e) {
    addMessage('error', 'حدث خطأ في قاعدة البيانات: ' . $e->getMessage());
}

redirect('index.php');
