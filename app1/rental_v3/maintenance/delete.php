<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

// التحقق من تسجيل الدخول
checkAuth();

// التحقق من الصلاحيات
checkPermission('dell_maintenance');

$pdo = getDatabaseConnection();

// التحقق من وجود معرف الصيانة
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$maintenance_id = $_GET['id'];

// جلب بيانات الصيانة للتأكد من وجودها
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

// معالجة حذف البيانات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("DELETE FROM maintenance WHERE maintenance_id = ?");
        $stmt->execute([$maintenance_id]);
        header("Location: index.php");
        exit;
    } catch (PDOException $e) {
        $error = "حدث خطأ أثناء حذف بيانات الصيانة";
    }
}

include '../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h2 class="mb-0">حذف بيانات الصيانة</h2>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <div class="alert alert-warning">
                        هل أنت متأكد من حذف بيانات الصيانة التالية؟
                    </div>

                    <div class="maintenance-details mb-4">
                        <p><strong>الوحدة:</strong> <?php echo htmlspecialchars($maintenance['unit_name']); ?></p>
                        <p><strong>تاريخ الصيانة:</strong> <?php echo htmlspecialchars($maintenance['maintenance_date']); ?></p>
                        <p><strong>وصف المشكلة:</strong> <?php echo htmlspecialchars($maintenance['description']); ?></p>
                        <p><strong>الحالة:</strong> <?php echo htmlspecialchars($maintenance['status']); ?></p>
                    </div>

                    <form method="POST" action="">
                        <div class="form-group">
                            <button type="submit" class="btn btn-danger">تأكيد الحذف</button>
                            <a href="index.php" class="btn btn-secondary">إلغاء</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
