<?php
require_once 'database.php';

/**
 * تسجيل تغيير حالة العقد
 */
function logContractStatusChange($contract_id, $old_status, $new_status, $notes = '') {
    $pdo = getDatabaseConnection();
    
    $query = "INSERT INTO contract_logs (contract_id, old_status, new_status, user_id, notes) 
              VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($query);
    return $stmt->execute([
        $contract_id,
        $old_status,
        $new_status,
        $_SESSION['user_id'] ?? null,
        $notes
    ]);
}

/**
 * تحديث حالة العقد مع تسجيل التغيير
 */
function updateContractStatus($contract_id, $new_status, $notes = '') {
    $pdo = getDatabaseConnection();
    
    // جلب الحالة القديمة
    $stmt = $pdo->prepare("SELECT status FROM contracts WHERE contract_id = ?");
    $stmt->execute([$contract_id]);
    $old_status = $stmt->fetchColumn();
    
    // تحديث حالة العقد
    $update_query = "UPDATE contracts SET status = ? WHERE contract_id = ?";
    $update_stmt = $pdo->prepare($update_query);
    $success = $update_stmt->execute([$new_status, $contract_id]);
    
    if ($success) {
        // تسجيل التغيير
        logContractStatusChange($contract_id, $old_status, $new_status, $notes);
    }
    
    return $success;
}

/**
 * جلب آخر تغيير لحالة العقد
 */
function getLastContractStatusChange($contract_id) {
    $pdo = getDatabaseConnection();
    
    $query = "SELECT * FROM contract_logs 
              WHERE contract_id = ? 
              ORDER BY log_date DESC 
              LIMIT 1";
              
    $stmt = $pdo->prepare($query);
    $stmt->execute([$contract_id]);
    return $stmt->fetch();
}
