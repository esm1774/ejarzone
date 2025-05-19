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
    $stmt = $pdo->prepare("
        SELECT m.*, 
               u.unit_name,
               u.floor,
               b.name as building_name
        FROM maintenance m 
        JOIN units u ON m.unit_id = u.unit_id 
        JOIN buildings b ON u.building_id = b.id
        WHERE m.maintenance_id = ?
    ");
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

<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="mb-0">تفاصيل الصيانة</h5>
                </div>
                <div class="col-auto">
                    <a href="index.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-right ml-1"></i>
                        عودة للقائمة
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label fw-bold">المبنى:</label>
                        <p class="mb-0"><?php echo htmlspecialchars($maintenance['building_name']); ?></p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label fw-bold">الطابق:</label>
                        <p class="mb-0"><?php echo htmlspecialchars($maintenance['floor']); ?></p>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label fw-bold">الوحدة:</label>
                        <p class="mb-0"><?php echo htmlspecialchars($maintenance['unit_name']); ?></p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label fw-bold">تاريخ الصيانة:</label>
                        <p class="mb-0"><?php echo htmlspecialchars($maintenance['maintenance_date']); ?></p>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">وصف المشكلة:</label>
                <p class="mb-0"><?php echo nl2br(htmlspecialchars($maintenance['description'])); ?></p>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">الحالة:</label>
                <p class="mb-0">
                    <?php
                    $statusClass = '';
                    switch($maintenance['status']) {
                        case 'قيد الانتظار':
                            $statusClass = 'text-warning';
                            break;
                        case 'جاري العمل':
                            $statusClass = 'text-primary';
                            break;
                        case 'مكتمل':
                            $statusClass = 'text-success';
                            break;
                        case 'ملغي':
                            $statusClass = 'text-danger';
                            break;
                    }
                    ?>
                    <span class="<?php echo $statusClass; ?>">
                        <?php echo htmlspecialchars($maintenance['status']); ?>
                    </span>
                </p>
            </div>

            <div class="mt-4">
                <a href="edit.php?id=<?php echo $maintenance_id; ?>" class="btn btn-warning btn-sm">
                    <i class="fas fa-edit ml-1"></i>
                    تعديل
                </a>
                <a href="#" onclick="confirmDelete(<?php echo $maintenance_id; ?>)" class="btn btn-danger btn-sm">
                    <i class="fas fa-trash ml-1"></i>
                    حذف
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(id) {
    if (confirm('هل أنت متأكد من حذف هذا السجل؟')) {
        window.location.href = 'delete.php?id=' + id;
    }
}
</script>

<?php include '../includes/footer.php'; ?>
