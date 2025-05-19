<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

// التحقق من تسجيل الدخول
checkAuth();

// التحقق من الصلاحيات
checkPermission('view_maintenance');

$pdo = getDatabaseConnection();

// التحقق من وجود معرف الصيانة
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$maintenance_id = $_GET['id'];

// جلب بيانات الصيانة
try {
    $stmt = $pdo->prepare("SELECT m.*, u.unit_name FROM maintenance m JOIN units u ON m.unit_id = u.unit_id WHERE m.maintenance_id = ?");
    $stmt->execute([$maintenance_id]);
    $maintenance = $stmt->fetch();

    if (!$maintenance) {
        header("Location: index.php");
        exit;
    }
} catch (PDOException $e) {
    die("خطأ في قاعدة البيانات: " . $e->getMessage());
}
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h2 class="mb-0">تفاصيل الصيانة</h2>
                        </div>
                        <div class="col-auto">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-right"></i> عودة للقائمة
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label"><strong>الوحدة:</strong></label>
                                <p class="form-control-static"><?php echo htmlspecialchars($maintenance['unit_name']); ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label"><strong>تاريخ الصيانة:</strong></label>
                                <p class="form-control-static"><?php echo htmlspecialchars($maintenance['maintenance_date']); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label"><strong>وصف المشكلة:</strong></label>
                        <p class="form-control-static"><?php echo nl2br(htmlspecialchars($maintenance['description'])); ?></p>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label"><strong>الحالة:</strong></label>
                        <p class="form-control-static">
                            <?php
                            $status_labels = [
                                'pending' => 'قيد الانتظار',
                                'in_progress' => 'جاري العمل',
                                'completed' => 'مكتمل',
                                'cancelled' => 'ملغي'
                            ];
                            echo $status_labels[$maintenance['status']] ?? $maintenance['status'];
                            ?>
                        </p>
                    </div>

                    <div class="mt-4">
                        <a href="edit.php?id=<?php echo $maintenance_id; ?>" class="btn btn-warning">
                            <i class="fas fa-edit"></i> تعديل
                        </a>
                        <a href="delete.php?id=<?php echo $maintenance_id; ?>" class="btn btn-danger">
                            <i class="fas fa-trash"></i> حذف
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
