<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

// التحقق من تسجيل الدخول
checkAuth();

// التحقق من الصلاحيات
checkPermission('add_maintenance');

// جلب قائمة المباني من جدول الوحدات
$pdo = getDatabaseConnection();
$buildingsStmt = $pdo->query("
    SELECT DISTINCT b.id, b.name 
    FROM units u 
    JOIN buildings b ON u.building_id = b.id 
    ORDER BY b.name
");
$buildings = $buildingsStmt->fetchAll();

// معالجة النموذج عند الإرسال
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $unit_id = $_POST['unit_id'] ?? '';
    $building_id = $_POST['building_id'] ?? '';
    $maintenance_date = $_POST['maintenance_date'] ?? '';
    $description = $_POST['description'] ?? '';
    $status = $_POST['status'] ?? '';

    // التحقق من صحة البيانات
    $errors = [];
    if (empty($unit_id)) {
        $errors[] = "يرجى اختيار الوحدة";
    }
    if (empty($building_id)) {
        $errors[] = "يرجى اختيار المبنى";
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
            // التحقق من وجود الوحدة في المبنى المحدد
            $checkStmt = $pdo->prepare("SELECT unit_id FROM units WHERE unit_id = ? AND building_id = ?");
            $checkStmt->execute([$unit_id, $building_id]);
            
            if (!$checkStmt->fetch()) {
                $errors[] = "الوحدة المحددة غير موجودة في هذا المبنى";
            } else {
                $stmt = $pdo->prepare("INSERT INTO maintenance (unit_id, maintenance_date, description, status) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$unit_id, $maintenance_date, $description, $status])) {
                    header("Location: index.php");
                    exit;
                } else {
                    $errors[] = "حدث خطأ أثناء إضافة بيانات الصيانة. الرجاء المحاولة مرة أخرى";
                }
            }
        } catch (PDOException $e) {
            // تسجيل الخطأ للمطور
            error_log($e->getMessage());
            $errors[] = "حدث خطأ في قاعدة البيانات. الرجاء التحقق من صحة البيانات المدخلة";
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

                    <form method="POST" class="needs-validation" novalidate>
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo $error; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="building_id" class="form-label">المبنى</label>
                            <select class="form-select" id="building_id" name="building_id" required>
                                <option value="">اختر المبنى</option>
                                <?php foreach ($buildings as $building): ?>
                                    <option value="<?php echo $building['id']; ?>">
                                        <?php echo htmlspecialchars($building['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">
                                يرجى اختيار المبنى
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="unit_id">الوحدة</label>
                            <select name="unit_id" id="unit_id" class="form-select" required disabled>
                                <option value="">اختر المبنى أولاً</option>
                            </select>
                            <div class="invalid-feedback">
                                يرجى اختيار الوحدة
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="maintenance_date">تاريخ الصيانة</label>
                            <input type="date" name="maintenance_date" id="maintenance_date" class="form-control" required>
                            <div class="invalid-feedback">
                                يرجى تحديد تاريخ الصيانة
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description">وصف المشكلة</label>
                            <textarea name="description" id="description" class="form-control" rows="3" required></textarea>
                            <div class="invalid-feedback">
                                يرجى إدخال وصف المشكلة
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="status">الحالة</label>
                            <select name="status" id="status" class="form-control" required>
                                <option value="">اختر الحالة</option>
                                <option value="قيد الانتظار">قيد الانتظار</option>
                                <option value="جاري العمل">جاري العمل</option>
                                <option value="مكتمل">مكتمل</option>
                                <option value="ملغي">ملغي</option>
                            </select>
                            <div class="invalid-feedback">
                                يرجى تحديد حالة الصيانة
                            </div>
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

<script>
document.getElementById('building_id').addEventListener('change', function() {
    const buildingId = this.value;
    const unitSelect = document.getElementById('unit_id');
    
    // تعطيل حقل الوحدات أثناء التحميل
    unitSelect.disabled = true;
    unitSelect.innerHTML = '<option value="">جاري التحميل...</option>';
    
    if (buildingId) {
        // إرسال طلب AJAX لجلب الوحدات
        fetch(`get_units.php?building_id=${buildingId}`)
            .then(response => response.json())
            .then(units => {
                unitSelect.innerHTML = '<option value="">اختر الوحدة</option>';
                units.forEach(unit => {
                    const option = document.createElement('option');
                    option.value = unit.unit_id;
                    option.textContent = unit.unit_name;  
                    unitSelect.appendChild(option);
                });
                unitSelect.disabled = false;
            })
            .catch(error => {
                console.error('Error:', error);
                unitSelect.innerHTML = '<option value="">حدث خطأ في تحميل الوحدات</option>';
            });
    } else {
        // إذا لم يتم اختيار مبنى، قم بتفريغ قائمة الوحدات وتعطيلها
        unitSelect.innerHTML = '<option value="">اختر المبنى أولاً</option>';
        unitSelect.disabled = true;
    }
});
</script>
