<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

// تفعيل عرض الأخطاء
error_reporting(E_ALL);
ini_set('display_errors', 1);

// التحقق من تسجيل الدخول
checkAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    addMessage('error', 'طريقة طلب غير صحيحة');
    header('Location: index.php');
    exit;
}

// التحقق من البيانات المطلوبة
if (!isset($_POST['contract_id'], $_POST['installment_number'], $_POST['payment_date'], $_POST['method_id'])) {
    addMessage('error', 'جميع الحقول مطلوبة');
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

$db = getDatabaseConnection();

try {
    // جلب بيانات العقد
    $stmt = $db->prepare("SELECT rent_amount, rent_type, start_date, end_date FROM contracts WHERE contract_id = ?");
    $stmt->execute([$_POST['contract_id']]);
    $contract = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$contract) {
        addMessage('error', 'العقد غير موجود');
        header('Location: index.php');
        exit;
    }

    // التحقق من عدم وجود دفعة مسجلة لهذا القسط
    $checkStmt = $db->prepare("SELECT payment_id FROM payments WHERE contract_id = ? AND installment_number = ?");
    $checkStmt->execute([$_POST['contract_id'], $_POST['installment_number']]);
    if ($checkStmt->fetch()) {
        addMessage('error', 'تم تسجيل دفعة لهذا القسط مسبقاً');
        header('Location: view_installments.php?contract_id=' . $_POST['contract_id']);
        exit;
    }

    // إنشاء رقم إيصال فريد
    $receipt_number = date('Ymd') . sprintf('%04d', rand(1, 9999));

    // حساب تاريخ الاستحقاق القادم
    $next_due_date = calculateNextDueDate(
        $contract['start_date'],
        $contract['rent_type'],
        $contract['end_date'],
        $_POST['installment_number']
    );
    
    $db->beginTransaction();

    // إدخال الدفعة الجديدة
    $insertStmt = $db->prepare("
        INSERT INTO payments (
            contract_id, 
            installment_number,
            payment_date, 
            amount, 
            method_id,
            receipt_number,
            notes,
            created_by,
            next_due_date,
            created_at
        ) VALUES (
            :contract_id,
            :installment_number,
            :payment_date,
            :amount,
            :method_id,
            :receipt_number,
            :notes,
            :created_by,
            :next_due_date,
            NOW()
        )
    ");

    $insertStmt->execute([
        'contract_id' => $_POST['contract_id'],
        'installment_number' => $_POST['installment_number'],
        'payment_date' => $_POST['payment_date'],
        'amount' => $contract['rent_amount'],
        'method_id' => $_POST['method_id'],
        'receipt_number' => $receipt_number,
        'notes' => $_POST['notes'] ?? null,
        'created_by' => $_SESSION['user_id'],
        'next_due_date' => $next_due_date
    ]);

    // الحصول على معرف الدفعة المدخلة
    $payment_id = $db->lastInsertId();

    // تحديث last_payment_id في جدول العقود
    $updateContractStmt = $db->prepare("
        UPDATE contracts 
        SET last_payment_id = :payment_id
        WHERE contract_id = :contract_id
    ");

    $updateContractStmt->execute([
        'payment_id' => $payment_id,
        'contract_id' => $_POST['contract_id']
    ]);

    $db->commit();

    addMessage('success', 'تم تسجيل الدفعة بنجاح. رقم الإيصال: ' . $receipt_number);
    header("Location: view_receipt.php?payment_id=$payment_id");
    exit;

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("خطأ في حفظ الدفعة: " . $e->getMessage());
    addMessage('error', 'حدث خطأ أثناء حفظ الدفعة');
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}