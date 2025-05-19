<?php
require_once "../config/config.php";
require_once "../includes/check_permission.php";
require_once "../config/database.php";

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

    // جلب معلومات الدفعة والعقد
    $query = "SELECT p.*, c.rent_type, c.next_due_date
              FROM payments p
              JOIN contracts c ON p.contract_id = c.contract_id
              WHERE p.payment_id = :payment_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":payment_id", $payment_id);
    $stmt->execute();
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        throw new Exception("الدفعة غير موجودة");
    }

    // حساب تاريخ الاستحقاق الجديد
    $next_due_date = new DateTime($payment['next_due_date']);
    
    // تحديد المدة حسب نوع العقد
    switch ($payment['rent_type']) {
        case 'شهري':
            $next_due_date->modify('-1 month');
            break;
        case 'سنوي':
            $next_due_date->modify('-1 year');
            break;
        case 'نصف سنوي':
            $next_due_date->modify('-6 months');
            break;
        case 'ربع سنوي':
            $next_due_date->modify('-3 months');
            break;
        default:
            throw new Exception("نوع العقد غير معروف");
    }

    // تحديث تاريخ الاستحقاق في جدول العقود
    $update_contract = "UPDATE contracts 
                       SET next_due_date = :next_due_date
                       WHERE contract_id = :contract_id";
    $update_stmt = $db->prepare($update_contract);
    $new_due_date = $next_due_date->format('Y-m-d');
    $update_stmt->bindParam(":next_due_date", $new_due_date);
    $update_stmt->bindParam(":contract_id", $payment['contract_id']);
    $update_stmt->execute();

    // حذف الدفعة
    $delete_query = "DELETE FROM payments WHERE payment_id = :payment_id";
    $delete_stmt = $db->prepare($delete_query);
    $delete_stmt->bindParam(":payment_id", $payment_id);
    $delete_stmt->execute();

    // تأكيد المعاملة
    $db->commit();

    $_SESSION['success'] = "تم حذف الدفعة وتحديث تاريخ الاستحقاق بنجاح";
    header("Location: index.php");
    exit();

} catch (Exception $e) {
    // التراجع عن المعاملة في حالة حدوث خطأ
    $db->rollBack();
    $_SESSION['error'] = $e->getMessage();
    header("Location: index.php");
    exit();
}
?>
