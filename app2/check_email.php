<?php
require_once 'config/config.php';
require_once 'includes/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['email'])) {
    echo json_encode(['error' => 'البريد الإلكتروني مطلوب']);
    exit;
}

$email = filter_var($_GET['email'], FILTER_SANITIZE_EMAIL);

try {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    
    $exists = $stmt->fetchColumn() > 0;
    
    echo json_encode(['exists' => $exists]);
} catch (PDOException $e) {
    error_log("Error checking email: " . $e->getMessage());
    echo json_encode(['error' => 'حدث خطأ أثناء التحقق من البريد الإلكتروني']);
}
