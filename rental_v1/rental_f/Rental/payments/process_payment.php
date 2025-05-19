<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالدخول']);
    exit();
}

// التحقق من وجود البيانات المطلوبة
if (!isset($_POST['contract_id'], $_POST['amount'], $_POST['payment_type'], $_POST['receipt_number'])) {
    $missing_fields = [];
    if (!isset($_POST['contract_id'])) $missing_fields[] = 'contract_id';
    if (!isset($_POST['amount'])) $missing_fields[] = 'amount';
    if (!isset($_POST['payment_type'])) $missing_fields[] = 'payment_type';
    if (!isset($_POST['receipt_number'])) $missing_fields[] = 'receipt_number';
    
    echo json_encode([
        'success' => false, 
        'message' => 'جميع الحقول مطلوبة',
        'missing_fields' => $missing_fields,
        'posted_data' => $_POST
    ]);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // بدء المعاملة
    $db->beginTransaction();
    
    // إدخال بيانات الدفعة مع التاريخ الفعلي
    $sql = "INSERT INTO payments (
        contract_id, 
        amount, 
        payment_type,
        receipt_number, 
        received_by,
        notes,
        payment_date
    ) VALUES (
        :contract_id,
        :amount,
        :payment_type,
        :receipt_number,
        :received_by,
        :notes,
        NOW()
    )";
    
    $stmt = $db->prepare($sql);
    
    $params = [
        ':contract_id' => $_POST['contract_id'],
        ':amount' => $_POST['amount'],
        ':payment_type' => $_POST['payment_type'],
        ':receipt_number' => $_POST['receipt_number'],
        ':received_by' => $_SESSION['user_id'],
        ':notes' => $_POST['notes'] ?? null
    ];
    
    // للتشخيص
    error_log("SQL Query: " . $sql);
    error_log("Parameters: " . print_r($params, true));
    
    $stmt->execute($params);
    
    // إتمام المعاملة
    $db->commit();
    
    echo json_encode(['success' => true, 'message' => 'تم تسجيل الدفعة بنجاح']);
    
} catch (PDOException $e) {
    // التراجع عن المعاملة في حالة حدوث خطأ
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log("Database Error: " . $e->getMessage());
    error_log("Error Code: " . $e->getCode());
    error_log("SQL State: " . $e->errorInfo[0]);
    
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء تسجيل الدفعة',
        'debug_info' => [
            'error_message' => $e->getMessage(),
            'error_code' => $e->getCode(),
            'sql_state' => $e->errorInfo[0] ?? null
        ]
    ]);
}
