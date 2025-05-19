<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

// التحقق من تسجيل الدخول
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالوصول']);
    exit;
}

// إنشاء اتصال قاعدة البيانات
$database = new Database();
$db = $database->getConnection();

// التحقق من وجود قيمة البحث
if (!isset($_GET['search']) || empty($_GET['search'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'الرجاء إدخال قيمة للبحث']);
    exit;
}

try {
    $search = '%' . $_GET['search'] . '%';
    
    $sql = "SELECT 
                t.tenant_id,
                t.full_name as tenant_name,
                t.phone,
                u.unit_name,
                c.contract_id,
                c.rent_type,
                c.rent_amount,
                c.next_payment_date
            FROM tenants t
            INNER JOIN contracts c ON t.tenant_id = c.tenant_id
            INNER JOIN units u ON c.unit_id = u.unit_id
            WHERE (t.full_name LIKE :search1 OR t.phone LIKE :search2)
            AND c.status = 'ساري'
            ORDER BY c.next_payment_date ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':search1', $search);
    $stmt->bindParam(':search2', $search);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($results) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $results
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'لم يتم العثور على نتائج'
        ]);
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ في قاعدة البيانات: ' . $e->getMessage()
    ]);
}
