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
    try {
        // جلب معلومات العقد قبل الحذف
        $query = "SELECT unit_id FROM contracts WHERE contract_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$_GET['id']]);
        $contract = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($contract) {
            // حذف العقد
            $query = "DELETE FROM contracts WHERE contract_id = ?";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([$_GET['id']])) {
                // تحديث حالة الوحدة إلى متاح
                $query = "UPDATE units SET status = 'متاح' WHERE unit_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$contract['unit_id']]);

                header("Location: index.php?success=3");
                exit();
            }
        }
    } catch (PDOException $e) {
        header("Location: index.php?error=2");
        exit();
    }
}

header("Location: index.php");
exit();
?>
