<?php
require_once 'config/config.php';
require_once 'includes/database.php';

try {
    $pdo = getDatabaseConnection();
    
    // Get table structure
    $stmt = $pdo->query("SHOW CREATE TABLE users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Table Creation SQL:\n";
    echo str_repeat("-", 50) . "\n";
    print_r($result);
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
