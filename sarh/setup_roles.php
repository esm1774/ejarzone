<?php
require_once 'config/config.php';
require_once 'includes/database.php';

try {
    $pdo = getDatabaseConnection();
    
    // عرض الأدوار الحالية
    echo "Current Roles:\n";
    echo str_repeat("-", 50) . "\n";
    $stmt = $pdo->query("SELECT * FROM roles");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
    
    // التحقق من وجود الدور 4
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE role_id = 4");
    $stmt->execute();
    $exists = $stmt->fetchColumn();
    
    if (!$exists) {
        // إضافة الدور إذا لم يكن موجوداً
        $stmt = $pdo->prepare("
            INSERT INTO roles (role_id, role_name, description) 
            VALUES (4, 'مستخدم عادي', 'مستخدم عادي في النظام')
        ");
        $stmt->execute();
        echo "\nRole added successfully!\n";
    } else {
        echo "\nRole 4 already exists.\n";
    }
    
    // عرض الأدوار بعد التحديث
    echo "\nUpdated Roles:\n";
    echo str_repeat("-", 50) . "\n";
    $stmt = $pdo->query("SELECT * FROM roles");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
