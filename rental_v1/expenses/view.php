<?php
require_once __DIR__ . '/../config/database.php';

// التحقق من تسجيل الدخول
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// التحقق من وجود معرف المصروف
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "معرف المصروف غير صحيح";
    header('Location: index.php');
    exit();
}

$expense_id = (int)$_GET['id'];

// إنشاء اتصال بقاعدة البيانات
$database = new Database();
$db = $database->getConnection();

try {
    // استعلام بيانات المصروف
    $query = "
        SELECT 
            e.*,
            t.type_name,
            t.description as type_description
        FROM 
            expenses e
            JOIN expense_types t ON e.type_id = t.type_id
        WHERE 
            e.expense_id = ?
    ";
    $stmt = $db->prepare($query);
    $stmt->execute([$expense_id]);
    $expense = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$expense) {
        $_SESSION['error'] = "المصروف غير موجود";
        header('Location: index.php');
        exit();
    }

    include '../includes/header.php';
?>

<!-- المحتوى الرئيسي -->
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">تفاصيل المصروف</h5>
                        <div>
                            <a href="edit.php?id=<?php echo $expense_id; ?>" class="btn btn-warning">
                                <i class="bi bi-pencil"></i> تعديل
                            </a>
                            <a href="delete.php?id=<?php echo $expense_id; ?>" class="btn btn-danger">
                                <i class="bi bi-trash"></i> حذف
                            </a>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-right"></i> عودة للقائمة
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tr>
                                    <th class="bg-light" style="width: 30%;">رقم المصروف</th>
                                    <td><?php echo $expense_id; ?></td>
                                </tr>
                                <tr>
                                    <th class="bg-light">نوع المصروف</th>
                                    <td>
                                        <?php echo htmlspecialchars($expense['type_name']); ?>
                                        <?php if ($expense['type_description']): ?>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($expense['type_description']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="bg-light">المبلغ</th>
                                    <td>
                                        <span class="text-primary fw-bold">
                                            <?php echo number_format($expense['amount'], 2); ?> ريال
                                        </span>
                                    </td>
                                </tr>
                                <th class="bg-light">طريقة الدفع</th>
                                    <td><?php echo $expense['payment_type'] == 'كاش' ? 'كاش' : ($expense['payment_type'] == 'تحويل' ? 'تحويل' : 'شبكة'); ?> </td>
                                </tr>
                                <tr>
                                    <th class="bg-light">التاريخ</th>
                                    <td><?php echo date('Y-m-d', strtotime($expense['expense_date'])); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tr>
                                    <th class="bg-light" style="width: 30%;">رقم الإيصال</th>
                                    <td><?php echo htmlspecialchars($expense['receipt_number']) ?: '-'; ?></td>
                                </tr>
                                <tr>
                                
                                <tr>
                                    <th class="bg-light">المُسلم</th>
                                    <td><?php echo htmlspecialchars($expense['payee_name']) ?: '-'; ?></td>
                                </tr>
                                <tr>
                                    <th class="bg-light">الوصف</th>
                                    <td><?php echo nl2br(htmlspecialchars($expense['description'])) ?: '-'; ?></td>
                                </tr>
                                <tr>
                                    <th class="bg-light">تاريخ الإنشاء</th>
                                    <td>
                                        <?php echo date('Y-m-d H:i', strtotime($expense['created_at'])); ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="text-end mt-3">
                    <a href="index.php" class="btn btn-secondary">عودة</a>
                    <a href="edit.php?id=<?php echo $expense['expense_id']; ?>" class="btn btn-primary">
                        <i class="bi bi-pencil"></i> تعديل
                    </a>
                    <a href="print_receipt.php?id=<?php echo $expense['expense_id']; ?>" 
                       class="btn btn-success" target="_blank">
                        <i class="bi bi-printer"></i> طباعة السند
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
    include '../includes/footer.php';
} catch(PDOException $e) {
    $_SESSION['error'] = "خطأ في قاعدة البيانات: " . $e->getMessage();
    header('Location: index.php');
    exit();
}
?>
