<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

include '../includes/header.php';

// إنشاء اتصال قاعدة البيانات
$database = new Database();
$db = $database->getConnection();

try {
    // أولاً، نتحقق من وجود أي مدفوعات
    $checkQuery = "SELECT COUNT(*) as count FROM payments";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute();
    $count = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($count > 0) {
        // إذا وجدت مدفوعات، نجلب البيانات بشكل تدريجي
        $query = "
            SELECT 
                p.*,
                c.contract_id,
                t.full_name as tenant_name,
                u.unit_name,
                c.next_due_date
            FROM 
                payments p
                LEFT JOIN contracts c ON p.contract_id = c.contract_id
                LEFT JOIN tenants t ON c.tenant_id = t.tenant_id
                LEFT JOIN units u ON c.unit_id = u.unit_id
            ORDER BY 
                p.payment_date DESC
            LIMIT 50
        ";
        
        error_log("Executing query: " . $query);
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Found " . count($payments) . " payments");
        
        if (empty($payments)) {
            error_log("Payments found but join query returned no results");
            $_SESSION['error'] = 'تم العثور على مدفوعات ولكن هناك مشكلة في عرضها. يرجى التواصل مع مدير النظام.';
        }
    } else {
        error_log("No payments found in the database");
        $payments = [];
    }
} catch(PDOException $e) {
    error_log("Database Error in index.php: " . $e->getMessage());
    error_log("SQL State: " . $e->errorInfo[0]);
    error_log("Error Code: " . $e->errorInfo[1]);
    error_log("Error Message: " . $e->errorInfo[2]);
    
    $_SESSION['error'] = 'حدث خطأ أثناء جلب المدفوعات: ' . $e->getMessage();
    $payments = [];
}

?>

<!-- المحتوى الرئيسي -->
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h3 mb-0">سجل الإيجارات</h2>
        <a href="add.php" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i>
            تسجيل إيجار جديدة
        </a>
    </div>

    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']); 
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($payments)): ?>
        <div class="alert alert-info">
            لا توجد إيجارات مسجلة بعد
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>رقم الإيصال</th>
                                <th>المستأجر</th>
                                <th>الوحدة</th>
                                <th>المبلغ</th>
                                <th>طريقة الدفع</th>
                                <th>تاريخ الدفع</th>
                                <th>تاريخ الاستحقاق القادم</th>
                                <th>ملاحظات</th>
                                <th>إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($payment['receipt_number']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['tenant_name'] ?? 'غير معروف'); ?></td>
                                    <td><?php echo htmlspecialchars($payment['unit_name'] ?? 'غير معروف'); ?></td>
                                    <td><?php echo number_format($payment['amount'], 2); ?></td>
                                    <td>
                                        <span class="badge <?php echo $payment['payment_type'] === 'كاش' ? 'bg-success' : 'bg-primary'; ?>">
                                            <?php echo htmlspecialchars($payment['payment_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($payment['payment_date'])); ?></td>
                                    <td><?php echo $payment['next_due_date'] ? date('Y-m-d', strtotime($payment['next_due_date'])) : 'غير محدد'; ?></td>
                                    <td>
                                        <?php if (!empty($payment['notes'])): ?>
                                            <button type="button" class="btn btn-sm btn-link" 
                                                    data-bs-toggle="tooltip" 
                                                    title="<?php echo htmlspecialchars($payment['notes']); ?>">
                                                <i class="bi bi-info-circle"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="print_receipt.php?id=<?php echo $payment['payment_id']; ?>" 
                                               class="btn btn-sm btn-success" target="_blank">
                                                <i class="bi bi-printer"></i> طباعة السند
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
