<?php
require_once 'config/config.php';
require_once 'includes/database.php';

try {
    $pdo = getDatabaseConnection();
    
    // Get table structure
    $stmt = $pdo->query("DESCRIBE users");
    echo "Table Structure:\n";
    echo str_repeat("-", 50) . "\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Field: {$row['Field']}\n";
        echo "Type: {$row['Type']}\n";
        echo "Null: {$row['Null']}\n";
        echo "Key: {$row['Key']}\n";
        echo "Default: {$row['Default']}\n";
        echo "Extra: {$row['Extra']}\n";
        echo str_repeat("-", 50) . "\n";
    }
    
    // Get sample data
    $stmt = $pdo->query("SELECT * FROM users LIMIT 5");
    echo "\nSample Data:\n";
    echo str_repeat("-", 50) . "\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
        echo str_repeat("-", 50) . "\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
