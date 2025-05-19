<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

// تفعيل عرض الأخطاء للتأكد من عملية الحفظ
error_reporting(E_ALL);
ini_set('display_errors', 1);

// التحقق من تسجيل الدخول
checkAuth();

// التحقق من وجود معرف المستخدم في الجلسة
if (!isset($_SESSION['user_id'])) {
    addMessage('error', 'الرجاء تسجيل الدخول مرة أخرى');
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    addMessage('error', 'طريقة طلب غير صحيحة');
    header('Location: index.php');
    exit;
}

// التحقق من البيانات المطلوبة
$required_fields = ['contract_id', 'installment_number', 'payment_date', 'method_id'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    $fields_str = implode(', ', $missing_fields);
    addMessage('error', 'الحقول التالية مطلوبة: ' . $fields_str);
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

// التحقق من صحة التاريخ
if (!validateDate($_POST['payment_date'])) {
    addMessage('error', 'تاريخ الدفع غير صالح');
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

$db = getDatabaseConnection();

try {
    // جلب بيانات العقد
    $stmt = $db->prepare("SELECT rent_amount FROM contracts WHERE contract_id = ?");
    $stmt->execute([$_POST['contract_id']]);
    $contract = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$contract) {
        addMessage('error', 'العقد غير موجود');
        header('Location: index.php');
        exit;
    }

    // التحقق من عدم وجود دفعة مسجلة لهذا القسط
    $checkStmt = $db->prepare("
        SELECT payment_id 
        FROM payments 
        WHERE contract_id = ? AND installment_number = ?
    ");
    $checkStmt->execute([$_POST['contract_id'], $_POST['installment_number']]);
    if ($checkStmt->fetch()) {
        addMessage('error', 'تم تسجيل دفعة لهذا القسط مسبقاً');
        header('Location: view_installments.php?contract_id=' . $_POST['contract_id']);
        exit;
    }

    // التحقق من صحة طريقة الدفع
    $methodStmt = $db->prepare("SELECT method_id, method_name FROM payment_methods WHERE method_id = ? AND is_active = TRUE");
    $methodStmt->execute([$_POST['method_id']]);
    $paymentMethod = $methodStmt->fetch(PDO::FETCH_ASSOC);
    if (!$paymentMethod) {
        addMessage('error', 'طريقة الدفع غير صالحة');
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }

    $db->beginTransaction();

    // إنشاء رقم إيصال فريد
    $receipt_number = date('Ymd') . sprintf('%04d', rand(1, 9999));

    // التحقق من عدم تكرار رقم الإيصال
    do {
        $checkReceiptStmt = $db->prepare("SELECT payment_id FROM payments WHERE receipt_number = ?");
        $checkReceiptStmt->execute([$receipt_number]);
        if (!$checkReceiptStmt->fetch()) {
            break;
        }
        $receipt_number = date('Ymd') . sprintf('%04d', rand(1, 9999));
    } while (true);

    // إدخال بيانات الدفعة
    $stmt = $db->prepare("
        INSERT INTO payments (
            contract_id,
            installment_number,
            amount,
            payment_date,
            method_id,
            receipt_number,
            notes,
            created_by
        ) VALUES (
            :contract_id,
            :installment_number,
            :amount,
            :payment_date,
            :method_id,
            :receipt_number,
            :notes,
            :created_by
        )
    ");

    $params = [
        ':contract_id' => $_POST['contract_id'],
        ':installment_number' => $_POST['installment_number'],
        ':amount' => $contract['rent_amount'],
        ':payment_date' => $_POST['payment_date'],
        ':method_id' => $_POST['method_id'],
        ':receipt_number' => $receipt_number,
        ':notes' => $_POST['notes'] ?? null,
        ':created_by' => $_SESSION['user_id']
    ];

    $stmt->execute($params);
    $payment_id = $db->lastInsertId();

    // التحقق من نجاح عملية الإدخال
    $verifyStmt = $db->prepare("SELECT payment_id FROM payments WHERE payment_id = ?");
    $verifyStmt->execute([$payment_id]);
    if (!$verifyStmt->fetch()) {
        throw new Exception('فشل في التحقق من إدخال الدفعة');
    }

    $db->commit();

    // إضافة رسالة نجاح
    addMessage('success', sprintf('تم تسجيل الدفعة بنجاح. رقم الإيصال: %s، طريقة الدفع: %s', 
        $receipt_number, 
        $paymentMethod['method_name']
    ));
    
    // التوجيه إلى صفحة عرض الإيصال
    header("Location: view_receipt.php?payment_id=$payment_id");
    exit;

} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("خطأ في حفظ الدفعة: " . $e->getMessage());
    addMessage('error', 'حدث خطأ أثناء حفظ الدفعة: ' . $e->getMessage());
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("خطأ عام: " . $e->getMessage());
    addMessage('error', $e->getMessage());
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

// دالة للتحقق من صحة التاريخ
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}
