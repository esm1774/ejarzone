<?php
require_once "../config/config.php";
require_once "../includes/check_permission.php";
require_once "../config/database.php";
require_once "../includes/functions.php";

// التحقق من تسجيل الدخول والصلاحيات
if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

if (!hasPermission('delete_payments')) {
    $_SESSION['error'] = "ليس لديك صلاحية لحذف المدفوعات";
    header("Location: index.php");
    exit();
}

// التحقق من وجود معرف الدفعة
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "معرف الدفعة غير صحيح";
    header("Location: index.php");
    exit();
}

$payment_id = $_GET['id'];

// إنشاء اتصال قاعدة البيانات
$database = new Database();
$db = $database->getConnection();

try {
    // بدء المعاملة
    $db->beginTransaction();

    // جلب معلومات الدفعة والعقد وآخر تاريخ استحقاق
    $query = "SELECT p.*, c.rent_type, c.start_date, c.end_date,
              (
                  SELECT p2.payment_id
                  FROM payments p2
                  WHERE p2.contract_id = p.contract_id
                  AND p2.payment_id < p.payment_id
                  ORDER BY p2.payment_id DESC
                  LIMIT 1
              ) as previous_payment_id,
              (
                  SELECT p2.next_due_date
                  FROM payments p2
                  WHERE p2.contract_id = p.contract_id
                  AND p2.payment_id < p.payment_id
                  ORDER BY p2.payment_id DESC
                  LIMIT 1
              ) as previous_next_due_date
              FROM payments p
              JOIN contracts c ON p.contract_id = c.contract_id
              WHERE p.payment_id = :payment_id";
    
    $stmt = $db->prepare($query);
    $stmt->execute(['payment_id' => $payment_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        throw new Exception('الدفعة غير موجودة');
    }

    // تحديد تاريخ الاستحقاق القادم
    $next_due_date = null;
    if ($payment['previous_payment_id']) {
        // إذا كانت هناك دفعة سابقة، استخدم تاريخ الاستحقاق القادم منها
        $next_due_date = $payment['previous_next_due_date'];
    } else {
        // إذا لم تكن هناك دفعة سابقة، احسب تاريخ الاستحقاق القادم من بداية العقد
        $next_due_date = calculateNextDueDate(
            $payment['start_date'],
            $payment['rent_type'],
            $payment['end_date'],
            1
        );
    }

    // تحديث last_payment_id في جدول العقود
    $updateContractStmt = $db->prepare("
        UPDATE contracts 
        SET last_payment_id = :previous_payment_id
        WHERE contract_id = :contract_id 
        AND last_payment_id = :current_payment_id
    ");
    
    $updateContractStmt->execute([
        'previous_payment_id' => $payment['previous_payment_id'],
        'contract_id' => $payment['contract_id'],
        'current_payment_id' => $payment_id
    ]);

    // حذف الدفعة
    $deleteStmt = $db->prepare("DELETE FROM payments WHERE payment_id = :payment_id");
    $deleteStmt->execute(['payment_id' => $payment_id]);

    $db->commit();
    $_SESSION['success'] = 'تم حذف الدفعة بنجاح';
    header("Location: index.php");
    exit();

} catch (Exception $e) {
    $db->rollBack();
    $_SESSION['error'] = 'حدث خطأ أثناء حذف الدفعة: ' . $e->getMessage();
    header("Location: index.php");
    exit();
}
