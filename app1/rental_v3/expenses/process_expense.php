<?php
// تعيين headers مناسبة
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/../config/database.php';

// التحقق من تسجيل الدخول
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'يجب تسجيل الدخول']);
    exit();
}

// التحقق من وجود POST data
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير صحيحة']);
    exit();
}

// إنشاء اتصال بقاعدة البيانات
$database = new Database();
$db = $database->getConnection();

try {
    // حذف مصروف
    if (isset($_POST['action']) && $_POST['action'] == 'delete') {
        $stmt = $db->prepare("DELETE FROM expenses WHERE expense_id = ?");
        $success = $stmt->execute([$_POST['expense_id']]);
        echo json_encode(['success' => $success]);
        exit();
    }

    // تعديل مصروف
    if (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $stmt = $db->prepare("
            UPDATE expenses 
            SET type_id = ?, 
                amount = ?, 
                expense_date = ?, 
                receipt_number = ?, 
                payment_type = ?,
                payee_name = ?,
                description = ?
            WHERE expense_id = ?
        ");
        
        $success = $stmt->execute([
            $_POST['type_id'],
            $_POST['amount'],
            $_POST['expense_date'],
            $_POST['receipt_number'],
            $_POST['payment_type'],
            $_POST['payee_name'],
            $_POST['description'],
            $_POST['expense_id']
        ]);
        
        echo json_encode(['success' => $success]);
        exit();
    }

    // إضافة مصروف جديد
    if (!isset($_POST['type_id']) || !isset($_POST['amount']) || !isset($_POST['expense_date'])) {
        $_SESSION['error'] = "جميع الحقول المطلوبة يجب ملؤها";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }

    $type_id = $_POST['type_id'];
    $amount = $_POST['amount'];
    $expense_date = $_POST['expense_date'];
    $description = $_POST['description'] ?? '';
    $payment_type = $_POST['payment_type'] ?? 'كاش';
    $payee_name = $_POST['payee_name'] ?? '';

    // التحقق من صحة البيانات
    $type_id = filter_var($type_id, FILTER_VALIDATE_INT);
    $amount = filter_var($amount, FILTER_VALIDATE_FLOAT);
    $expense_date = date('Y-m-d', strtotime($expense_date));

    if ($type_id === false || $amount === false || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'البيانات المدخلة غير صحيحة']);
        exit();
    }

    // التحقق من وجود نوع المصروف
    $check_type = $db->prepare("SELECT type_id FROM expense_types WHERE type_id = ?");
    $check_type->execute([$type_id]);
    if (!$check_type->fetch()) {
        echo json_encode(['success' => false, 'message' => 'نوع المصروف غير موجود']);
        exit();
    }

    // إنشاء رقم إيصال فريد
    $year = date('Y');
    $query = "SELECT MAX(CAST(SUBSTRING_INDEX(receipt_number, '-', -1) AS UNSIGNED)) as max_number 
             FROM expenses 
             WHERE receipt_number LIKE :year";
    $stmt = $db->prepare($query);
    $stmt->execute(['year' => $year . '-%']);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $next_number = ($result['max_number'] ?? 0) + 1;
    $receipt_number = $year . '-' . str_pad($next_number, 4, '0', STR_PAD_LEFT);

    $query = "INSERT INTO expenses (type_id, amount, expense_date, description, receipt_number, payment_type, payee_name) 
             VALUES (:type_id, :amount, :expense_date, :description, :receipt_number, :payment_type, :payee_name)";
    $params = [
        'type_id' => $type_id,
        'amount' => $amount,
        'expense_date' => $expense_date,
        'description' => $description,
        'receipt_number' => $receipt_number,
        'payment_type' => $payment_type,
        'payee_name' => $payee_name
    ];

    $stmt = $db->prepare($query);
    $success = $stmt->execute($params);

    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'تم إضافة المصروف بنجاح',
            'expense_id' => $db->lastInsertId()
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'فشل في إضافة المصروف']);
    }

} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
