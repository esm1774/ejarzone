<?php
require_once '../includes/session.php';
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

// التحقق من تسجيل الدخول
checkAuth();

// التحقق من الصلاحيات
checkPermission('delete_contracts');

$db = getDatabaseConnection();

// التحقق من وجود معرف العقد
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    addMessage('error', 'معرف العقد غير صحيح');
    header('Location: index.php');
    exit;
}

$contract_id = $_GET['id'];

try {
    $db->beginTransaction();

    // جلب معرف الوحدة قبل حذف العقد
    $stmt = $db->prepare("SELECT unit_id FROM contracts WHERE contract_id = ?");
    $stmt->execute([$contract_id]);
    $contract = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$contract) {
        addMessage('error', 'العقد غير موجود');
        header('Location: index.php');
        exit;
    }

    // حذف العقد
    $stmt = $db->prepare("DELETE FROM contracts WHERE contract_id = ?");
    if ($stmt->execute([$contract_id])) {
        // تحديث حالة الوحدة إلى شاغرة
        $updateUnitStmt = $db->prepare("UPDATE units SET status = 'شاغرة' WHERE unit_id = ?");
        $updateUnitStmt->execute([$contract['unit_id']]);

        $db->commit();
        addMessage('success', 'تم حذف العقد بنجاح');
        header('Location: index.php?success=delete');
        exit;
    }
} catch (PDOException $e) {
    $db->rollBack();
    addMessage('error', 'حدث خطأ أثناء حذف العقد: ' . $e->getMessage());
    header('Location: index.php');
    exit;
}
?>
