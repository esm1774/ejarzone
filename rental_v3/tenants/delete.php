<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

include_once "../config/database.php";
$database = new Database();
$db = $database->getConnection();

if (isset($_GET['id'])) {
    $tenant_id = $_GET['id'];

    // التحقق من وجود عقود مرتبطة بالمستأجر
    $query = "SELECT COUNT(*) FROM contracts WHERE tenant_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$tenant_id]);
    $contracts_count = $stmt->fetchColumn();

    if ($contracts_count > 0) {
        header("Location: index.php?error=1");
        exit();
    }

    // حذف المستأجر
    $query = "DELETE FROM tenants WHERE tenant_id = ?";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([$tenant_id])) {
        header("Location: index.php?success=3");
    } else {
        header("Location: index.php?error=2");
    }
} else {
    header("Location: index.php");
}
exit();
?>
