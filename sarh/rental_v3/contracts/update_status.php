<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

include_once "../config/database.php";
include_once "../includes/contract_functions.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['contract_id']) && isset($_POST['new_status'])) {
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        // تحديث حالة العقد باستخدام الدالة الجديدة
        if (updateContractStatus(
            $_POST['contract_id'],
            $_POST['new_status'],
            $_POST['notes'] ?? 'تم تغيير حالة العقد'
        )) {
            header("Location: view.php?id=" . $_POST['contract_id'] . "&success=1");
        } else {
            header("Location: view.php?id=" . $_POST['contract_id'] . "&error=1");
        }
    } catch (Exception $e) {
        header("Location: view.php?id=" . $_POST['contract_id'] . "&error=" . urlencode($e->getMessage()));
    }
    exit();
}

header("Location: index.php");
exit();
