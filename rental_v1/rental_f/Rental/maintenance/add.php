<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

// التحقق من تسجيل الدخول
checkAuth();

// التحقق من الصلاحيات
checkPermission('add_maintenance');

// جلب قائمة الوحدات
$pdo = getDatabaseConnection();
$stmt = $pdo->query("SELECT unit_id, unit_name FROM units");
$units = $stmt->fetchAll();

// معالجة النموذج عند الإرسال
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $unit_id = $_POST['unit_id'] ?? '';
    $maintenance_date = $_POST['maintenance_date'] ?? '';
    $description = $_POST['description'] ?? '';
    $status = $_POST['status'] ?? '';

    // التحقق من صحة البيانات
    $errors = [];
    if (empty($unit_id)) {
        $errors[] = "يرجى اختيار الوحدة";
    }
    if (empty($maintenance_date)) {
        $errors[] = "يرجى تحديد تاريخ الصيانة";
    }
    if (empty($description)) {
        $errors[] = "يرجى إدخال وصف المشكلة";
    }
    if (empty($status)) {
        $errors[] = "يرجى تحديد حالة الصيانة";
    }

    // إذا لم تكن هناك أخطاء، قم بإضافة السجل
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO maintenance (unit_id, maintenance_date, description, status) VALUES (?, ?, ?, ?)");
            $stmt->execute([$unit_id, $maintenance_date, $description, $status]);
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            $errors[] = "حدث خطأ أثناء إضافة بيانات الصيانة";
        }
    }
}
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h2 class="mb-0">إضافة صيانة جديدة</h2>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="form-group mb-3">
                            <label for="unit_id">الوحدة</label>
                            <select name="unit_id" id="unit_id" class="form-control" required>
                                <option value="">اختر الوحدة</option>
                                <?php foreach ($units as $unit): ?>
                                    <option value="<?php echo $unit['unit_id']; ?>">
                                        <?php echo htmlspecialchars($unit['unit_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group mb-3">
                            <label for="maintenance_date">تاريخ الصيانة</label>
                            <input type="date" name="maintenance_date" id="maintenance_date" class="form-control" required>
                        </div>

                        <div class="form-group mb-3">
                            <label for="description">وصف المشكلة</label>
                            <textarea name="description" id="description" class="form-control" rows="3" required></textarea>
                        </div>

                        <div class="form-group mb-3">
                            <label for="status">الحالة</label>
                            <select name="status" id="status" class="form-control" required>
                                <option value="">اختر الحالة</option>
                                <option value="pending">قيد الانتظار</option>
                                <option value="in_progress">جاري العمل</option>
                                <option value="completed">مكتمل</option>
                                <option value="cancelled">ملغي</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">حفظ</button>
                            <a href="index.php" class="btn btn-secondary">إلغاء</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
