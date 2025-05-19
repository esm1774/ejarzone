<?php
require_once 'config/config.php';
require_once 'includes/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['username'])) {
    echo json_encode(['error' => 'اسم المستخدم مطلوب']);
    exit;
}

$username = trim($_GET['username']);

try {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
    $stmt->execute(['username' => $username]);
    
    $exists = $stmt->fetchColumn() > 0;
    
    echo json_encode(['exists' => $exists]);
} catch (PDOException $e) {
    error_log("Error checking username: " . $e->getMessage());
    echo json_encode(['error' => 'حدث خطأ أثناء التحقق من اسم المستخدم']);
}
