<?php
require_once "../config/config.php";
require_once "../includes/check_permission.php";
require_once "../config/database.php";

// التحقق من تسجيل الدخول
if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

// التحقق من وجود معرف الدفعة
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "معرف الدفعة غير صحيح";
    header("Location: index.php");
    exit();
}

$payment_id = $_GET['id'];

// إنشاء اتصال قاعدة البيانات
$database = new Database();
$db = $database->getConnection();

try {
    // استعلام تفاصيل الدفعة
    $query = "SELECT p.*, t.full_name as tenant_name, c.contract_id, c.next_due_date,
              u.unit_name, pm.method_name as payment_type
              FROM payments p 
              LEFT JOIN contracts c ON p.contract_id = c.contract_id
              LEFT JOIN tenants t ON c.tenant_id = t.tenant_id
              LEFT JOIN units u ON c.unit_id = u.unit_id
              LEFT JOIN payment_methods pm ON p.method_id = pm.method_id
              WHERE p.payment_id = :payment_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":payment_id", $payment_id);
    $stmt->execute();
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        throw new Exception("الدفعة غير موجودة");
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header("Location: index.php");
    exit();
}
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<!-- المحتوى الرئيسي -->
<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">تفاصيل الدفعة</h5>
            <div class="text-end mt-4">
                <?php if (hasPermission('edit_payments')): ?>
                <a href="edit.php?id=<?php echo $payment_id; ?>" class="btn btn-primary">
                    <i class="bi bi-pencil"></i> تعديل
                </a>
                <?php endif; ?>

                <a href="print_receipt.php?id=<?php echo $payment_id; ?>" class="btn btn-success" target="_blank">
                    <i class="bi bi-printer"></i> طباعة سند القبض
                </a>

                <?php if (hasPermission('delete_payments')): ?>
                <a href="delete.php?id=<?php echo $payment_id; ?>" class="btn btn-danger" 
                   onclick="return confirm('هل أنت متأكد من حذف هذه الدفعة؟');">
                    <i class="bi bi-trash"></i> حذف
                </a>
                <?php endif; ?>

                <a href="index.php" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-right"></i> عودة للقائمة
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-bordered">
                        <tr>
                            <th>رقم العقد</th>
                            <td><?php echo htmlspecialchars($payment['contract_id'] ?? 'غير متوفر'); ?></td>
                        </tr>
                        <tr>
                            <th>اسم المستأجر</th>
                            <td><?php echo htmlspecialchars($payment['tenant_name'] ?? 'غير متوفر'); ?></td>
                        </tr>
                        <tr>
                            <th>اسم الوحدة</th>
                            <td><?php echo htmlspecialchars($payment['unit_name'] ?? 'غير متوفر'); ?></td>
                        </tr>
                        <tr>
                            <th>المبلغ</th>
                            <td><?php echo number_format($payment['amount'], 2); ?> ريال</td>
                        </tr>
                        <tr>
                            <th>تاريخ الدفع</th>
                            <td><?php echo date('Y-m-d', strtotime($payment['payment_date'])); ?></td>
                        </tr>
                        <tr>
                            <th>تاريخ الاستحقاق القادم</th>
                            <td><?php echo $payment['next_due_date'] ? date('Y-m-d', strtotime($payment['next_due_date'])) : 'غير متوفر'; ?></td>
                        </tr>
                        <tr>
                            <th>طريقة الدفع</th>
                            <td><?php echo htmlspecialchars($payment['payment_type'] ?? 'غير متوفر'); ?></td>
                        </tr>
                        <tr>
                            <th>رقم الإيصال</th>
                            <td><?php echo htmlspecialchars($payment['receipt_number']); ?></td>
                        </tr>
                        <tr>
                            <th>ملاحظات</th>
                            <td><?php echo nl2br(htmlspecialchars($payment['notes'] ?? '')); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>