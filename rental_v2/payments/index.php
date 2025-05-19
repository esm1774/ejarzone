<?php
require_once "../config/config.php";
require_once "../includes/check_permission.php";
require_once "../config/database.php";

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}


// إنشاء اتصال قاعدة البيانات
$database = new Database();
$db = $database->getConnection();

try {
    // أولاً، نتحقق من وجود أي مدفوعات
    $checkQuery = "SELECT COUNT(*) as count FROM payments";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute();
    $result = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($result['count'] == 0) {
        // لا توجد مدفوعات
        $payments = [];
    } else {
        // جلب جميع المدفوعات مع معلومات المستأجر والعقد وطريقة الدفع
        $query = "SELECT p.*, t.full_name as tenant_name, c.contract_id, u.unit_name, c.next_due_date,
                 pm.method_name as payment_type
                 FROM payments p 
                 LEFT JOIN contracts c ON p.contract_id = c.contract_id
                 LEFT JOIN tenants t ON c.tenant_id = t.tenant_id
                 LEFT JOIN units u ON c.unit_id = u.unit_id
                 LEFT JOIN payment_methods pm ON p.method_id = pm.method_id
                 ORDER BY p.payment_date DESC
                 LIMIT 50";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<!-- المحتوى الرئيسي -->
<div class="container-fluid py-4">

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
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">إدارة المدفوعات</h5>
                <?php if (hasPermission('add_payments')): ?>
                <a href="add.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i>
                    إضافةإيجار جديد
                </a>
                <?php endif; ?>
            </div>
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
                                    <td><?php echo htmlspecialchars($payment['payment_type'] ?? 'غير محدد'); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($payment['payment_date'])); ?></td>
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
                                        <div class="btn-group" role="group">
                                            <?php if (hasPermission('view_payments')): ?>
                                            <a href="view.php?id=<?php echo $payment['payment_id']; ?>" 
                                               class="btn btn-sm btn-info" title="عرض">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php endif; ?>

                                            <?php if (hasPermission('edit_payments')): ?>
                                            <a href="edit.php?id=<?php echo $payment['payment_id']; ?>" 
                                               class="btn btn-sm btn-primary" title="تعديل">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <?php endif; ?>

                                            <?php if (hasPermission('delete_payments')): ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-danger" 
                                                    onclick="confirmDelete(<?php echo $payment['payment_id']; ?>)"
                                                    title="حذف">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                            <?php endif; ?>

                                            <?php if (hasPermission('print_payments')): ?>
                                            <a href="print_receipt.php?id=<?php echo $payment['payment_id']; ?>" 
                                               class="btn btn-sm btn-secondary" 
                                               target="_blank"
                                               title="طباعة">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                            <?php endif; ?>
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

<script>
function confirmDelete(id) {
    if (confirm('هل أنت متأكد من حذف هذه الدفعة؟')) {
        window.location.href = 'delete.php?id=' + id;
    }
}
</script>

<?php include '../includes/footer.php'; ?>
