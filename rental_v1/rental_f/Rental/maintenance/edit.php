<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

// التحقق من تسجيل الدخول
checkAuth();

// التحقق من الصلاحيات
checkPermission('edit_maintenance');

$pdo = getDatabaseConnection();

// التحقق من وجود معرف الصيانة
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$maintenance_id = $_GET['id'];

// جلب بيانات الصيانة
try {
    $stmt = $pdo->prepare("SELECT * FROM maintenance WHERE maintenance_id = ?");
    $stmt->execute([$maintenance_id]);
    $maintenance = $stmt->fetch();

    if (!$maintenance) {
        header("Location: index.php");
        exit;
    }
} catch (PDOException $e) {
    die("خطأ في قاعدة البيانات: " . $e->getMessage());
}

// جلب قائمة الوحدات
$stmt = $pdo->query("SELECT unit_id, unit_name FROM units");
$units = $stmt->fetchAll();

// معالجة تحديث البيانات
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

    // إذا لم تكن هناك أخطاء، قم بتحديث السجل
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE maintenance SET unit_id = ?, maintenance_date = ?, description = ?, status = ? WHERE maintenance_id = ?");
            $stmt->execute([$unit_id, $maintenance_date, $description, $status, $maintenance_id]);
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            $errors[] = "حدث خطأ أثناء تحديث بيانات الصيانة";
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
                    <h2 class="mb-0">تعديل بيانات الصيانة</h2>
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
                                    <option value="<?php echo $unit['unit_id']; ?>" <?php echo ($maintenance['unit_id'] == $unit['unit_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($unit['unit_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group mb-3">
                            <label for="maintenance_date">تاريخ الصيانة</label>
                            <input type="date" name="maintenance_date" id="maintenance_date" class="form-control" 
                                   value="<?php echo htmlspecialchars($maintenance['maintenance_date']); ?>" required>
                        </div>

                        <div class="form-group mb-3">
                            <label for="description">وصف المشكلة</label>
                            <textarea name="description" id="description" class="form-control" rows="3" required><?php echo htmlspecialchars($maintenance['description']); ?></textarea>
                        </div>

                        <div class="form-group mb-3">
                            <label for="status">الحالة</label>
                            <select name="status" id="status" class="form-control" required>
                                <option value="">اختر الحالة</option>
                                <option value="pending" <?php echo ($maintenance['status'] == 'pending') ? 'selected' : ''; ?>>قيد الانتظار</option>
                                <option value="in_progress" <?php echo ($maintenance['status'] == 'in_progress') ? 'selected' : ''; ?>>جاري العمل</option>
                                <option value="completed" <?php echo ($maintenance['status'] == 'completed') ? 'selected' : ''; ?>>مكتمل</option>
                                <option value="cancelled" <?php echo ($maintenance['status'] == 'cancelled') ? 'selected' : ''; ?>>ملغي</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                            <a href="index.php" class="btn btn-secondary">إلغاء</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
