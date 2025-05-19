<?php
require_once 'config/config.php';
require_once 'includes/database.php';

try {
    $pdo = getDatabaseConnection();
    
    // Get roles table data
    $stmt = $pdo->query("SELECT * FROM roles");
    echo "Available Roles:\n";
    echo str_repeat("-", 50) . "\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
        echo str_repeat("-", 50) . "\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
