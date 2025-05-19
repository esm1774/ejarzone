<?php
session_start();
require_once "../config/config.php";
require_once "../includes/functions.php";

// التحقق من تسجيل الدخول
if (!isLoggedIn()) {
    redirect('../login.php');
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    if (!isset($_GET['id'])) {
        addMessage('error', 'معرف العقد غير موجود');
        redirect('index.php');
        exit();
    }

    // جلب معلومات العقد قبل الحذف
    $query = "SELECT unit_id FROM contracts WHERE contract_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$_GET['id']]);
    $contract = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$contract) {
        addMessage('error', 'العقد غير موجود');
        redirect('index.php');
        exit();
    }

    // بدء المعاملة
    $db->beginTransaction();

    try {
        // حذف العقد
        $query = "DELETE FROM contracts WHERE contract_id = ?";
        $stmt = $db->prepare($query);
        if (!$stmt->execute([$_GET['id']])) {
            throw new Exception("فشل في حذف العقد");
        }

        // تحديث حالة الوحدة إلى شاغرة
        $query = "UPDATE units SET status = 'شاغرة' WHERE unit_id = ?";
        $stmt = $db->prepare($query);
        if (!$stmt->execute([$contract['unit_id']])) {
            throw new Exception("فشل في تحديث حالة الوحدة");
        }

        // تأكيد المعاملة
        $db->commit();
        
        addMessage('success', 'تم حذف العقد وتحديث حالة الوحدة بنجاح');
        redirect('index.php');
    } catch (Exception $e) {
        // التراجع عن المعاملة في حالة حدوث خطأ
        $db->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    addMessage('error', 'حدث خطأ: ' . $e->getMessage());
    redirect('index.php');
}
?>
