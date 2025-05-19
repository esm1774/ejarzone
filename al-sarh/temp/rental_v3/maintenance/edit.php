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
    $stmt = $pdo->prepare("
        SELECT m.*, 
               u.unit_name,
               u.floor,
               u.building_id,
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

    // جلب قائمة المباني
    $buildingsStmt = $pdo->query("SELECT id, name FROM buildings ORDER BY name");
    $buildings = $buildingsStmt->fetchAll();

    // جلب الوحدات للمبنى الحالي
    $unitsStmt = $pdo->prepare("
        SELECT u.unit_id, 
               CONCAT(u.unit_name, ' - الطابق ', u.floor) as unit_name
        FROM units u
        WHERE u.building_id = ?
        ORDER BY u.unit_name
    ");
    $unitsStmt->execute([$maintenance['building_id']]);
    $units = $unitsStmt->fetchAll();

} catch (PDOException $e) {
    die("خطأ في قاعدة البيانات: " . $e->getMessage());
}

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

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE maintenance 
                SET unit_id = ?, maintenance_date = ?, description = ?, status = ?
                WHERE maintenance_id = ?
            ");
            if ($stmt->execute([$unit_id, $maintenance_date, $description, $status, $maintenance_id])) {
                header("Location: index.php?success=true");
                exit;
            }
        } catch (PDOException $e) {
            $errors[] = "حدث خطأ أثناء تحديث بيانات الصيانة";
        }
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>
<div class="main-content">
    <div class="container-fluid py-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">تعديل الصيانة</h5>
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
                    <div class="mb-3">
                        <label for="building_id" class="form-label">المبنى</label>
                        <select class="form-select" id="building_id" name="building_id" required>
                            <option value="">اختر المبنى</option>
                            <?php foreach ($buildings as $building): ?>
                                <option value="<?php echo $building['id']; ?>" 
                                        <?php echo $building['id'] == $maintenance['building_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($building['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">
                            يرجى اختيار المبنى
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="unit_id" class="form-label">الوحدة</label>
                        <select class="form-select" id="unit_id" name="unit_id" required>
                            <option value="">اختر الوحدة</option>
                            <?php foreach ($units as $unit): ?>
                                <option value="<?php echo $unit['unit_id']; ?>" 
                                        <?php echo $unit['unit_id'] == $maintenance['unit_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($unit['unit_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">
                            يرجى اختيار الوحدة
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="maintenance_date" class="form-label">تاريخ الصيانة</label>
                        <input type="date" class="form-control" id="maintenance_date" name="maintenance_date" 
                            value="<?php echo htmlspecialchars($maintenance['maintenance_date']); ?>" required>
                        <div class="invalid-feedback">
                            يرجى تحديد تاريخ الصيانة
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">وصف المشكلة</label>
                        <textarea class="form-control" id="description" name="description" rows="3" required><?php echo htmlspecialchars($maintenance['description']); ?></textarea>
                        <div class="invalid-feedback">
                            يرجى إدخال وصف المشكلة
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="status" class="form-label">الحالة</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="">اختر الحالة</option>
                            <option value="قيد الانتظار" <?php echo $maintenance['status'] === 'قيد الانتظار' ? 'selected' : ''; ?>>قيد الانتظار</option>
                            <option value="جاري العمل" <?php echo $maintenance['status'] === 'جاري العمل' ? 'selected' : ''; ?>>جاري العمل</option>
                            <option value="مكتمل" <?php echo $maintenance['status'] === 'مكتمل' ? 'selected' : ''; ?>>مكتمل</option>
                            <option value="ملغي" <?php echo $maintenance['status'] === 'ملغي' ? 'selected' : ''; ?>>ملغي</option>
                        </select>
                        <div class="invalid-feedback">
                            يرجى تحديد حالة الصيانة
                        </div>
                    </div>

                    <div class="text-end">
                        <a href="index.php" class="btn btn-secondary">إلغاء</a>
                        <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
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
        unitSelect.innerHTML = '<option value="">اختر المبنى أولاً</option>';
        unitSelect.disabled = true;
    }
});
</script>

<?php include '../includes/footer.php'; ?>
