<?php
require_once 'config/config.php';
require_once 'includes/Logger.php';

$logger = Logger::getInstance();

try {
    // عرض آخر 10 سجلات
    echo "Last 10 Log Entries:\n";
    echo str_repeat("-", 50) . "\n";
    
    // افتراض أن السجلات تُحفظ في مجلد logs
    $log_file = __DIR__ . '/logs/app.log';
    if (file_exists($log_file)) {
        $logs = file($log_file);
        $logs = array_reverse($logs);
        $logs = array_slice($logs, 0, 10);
        
        foreach ($logs as $log) {
            echo $log . "\n";
        }
    } else {
        echo "Log file not found at: " . $log_file;
    }
    
} catch (Exception $e) {
    echo "Error reading logs: " . $e->getMessage();
}
