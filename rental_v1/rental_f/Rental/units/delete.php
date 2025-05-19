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
    $unit_id = $_GET['id'];

    // التحقق من وجود عقود مرتبطة بالوحدة
    $query = "SELECT COUNT(*) FROM contracts WHERE unit_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$unit_id]);
    $contracts_count = $stmt->fetchColumn();

    if ($contracts_count > 0) {
        header("Location: index.php?error=1");
        exit();
    }

    // حذف الصور المرتبطة بالوحدة
    $query = "SELECT images FROM units WHERE unit_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$unit_id]);
    $unit = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($unit && !empty($unit['images'])) {
        $images = explode(',', $unit['images']);
        foreach ($images as $image) {
            if (!empty($image)) {
                $image_path = "../uploads/units/" . $image;
                if (file_exists($image_path)) {
                    unlink($image_path);
                }
            }
        }
    }

    // حذف الوحدة
    $query = "DELETE FROM units WHERE unit_id = ?";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([$unit_id])) {
        header("Location: index.php?success=3");
    } else {
        header("Location: index.php?error=2");
    }
} else {
    header("Location: index.php");
}
exit();
?>
