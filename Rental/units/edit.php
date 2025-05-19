<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    addMessage('error', 'يرجى تسجيل الدخول أولاً');
    header('Location: ../login.php');
    exit;
}

// التحقق من الصلاحيات
if (!hasPermission('edit_units')) {
    addMessage('error', 'ليس لديك صلاحية تعديل الوحدات');
    header('Location: index.php');
    exit;
}

$pdo = getDatabaseConnection();

// التحقق من وجود معرف الوحدة
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    addMessage('error', 'معرف الوحدة غير صحيح');
    header('Location: index.php');
    exit;
}

$unit_id = $_GET['id'];

try {
    // جلب بيانات الوحدة
    $stmt = $pdo->prepare("
        SELECT u.*, b.name as building_name 
        FROM units u 
        JOIN buildings b ON u.building_id = b.id 
        WHERE u.unit_id = ?
    ");
    $stmt->execute([$unit_id]);
    $unit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$unit) {
        addMessage('error', 'الوحدة غير موجودة');
        header('Location: index.php');
        exit;
    }

    // جلب قائمة المباني
    $buildingsStmt = $pdo->query("SELECT id, name FROM buildings ORDER BY name");
    $buildings = $buildingsStmt->fetchAll();

    // التحقق من وجود عقد نشط للوحدة
    $contractStmt = $pdo->prepare("
        SELECT COUNT(*) FROM contracts 
        WHERE unit_id = ? AND status = 'نشط'
    ");
    $contractStmt->execute([$unit_id]);
    $hasActiveContract = $contractStmt->fetchColumn() > 0;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $unit_name = $_POST['unit_name'] ?? '';
        $floor = $_POST['floor'] ?? '';
        $building_id = $_POST['building_id'] ?? '';
        $status = $_POST['status'] ?? '';
        $description = $_POST['description'] ?? '';

        // التحقق من صحة البيانات
        $errors = [];
        if (empty($unit_name)) $errors[] = "يرجى إدخال اسم الوحدة";
        if (empty($floor)) $errors[] = "يرجى تحديد الطابق";
        if (empty($building_id)) $errors[] = "يرجى اختيار المبنى";
        if (empty($status)) $errors[] = "يرجى تحديد حالة الوحدة";

        // التعامل مع الصور الجديدة
        $images = !empty($unit['images']) ? explode(',', $unit['images']) : [];
        if (!empty($_FILES['images']['name'][0])) {
            $upload_dir = "../uploads/units/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                if (!empty($tmp_name)) {
                    $file_name = time() . '_' . $_FILES['images']['name'][$key];
                    $file_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($tmp_name, $file_path)) {
                        $images[] = $file_name;
                    } else {
                        $errors[] = "فشل في رفع الصورة: " . $_FILES['images']['name'][$key];
                    }
                }
            }
        }

        if (empty($errors)) {
            try {
                // تحديث بيانات الوحدة
                $stmt = $pdo->prepare("
                    UPDATE units 
                    SET unit_name = ?, 
                        floor = ?, 
                        building_id = ?, 
                        status = ?, 
                        description = ?, 
                        images = ?
                    WHERE unit_id = ?
                ");
                
                if ($stmt->execute([
                    $unit_name,
                    $floor,
                    $building_id,
                    $status,
                    $description,
                    implode(',', $images),
                    $unit_id
                ])) {
                    addMessage('success', 'تم تحديث الوحدة بنجاح');
                    header('Location: index.php');
                    exit;
                }
            } catch (PDOException $e) {
                $errors[] = "حدث خطأ أثناء تحديث الوحدة: " . $e->getMessage();
            }
        }
    }
} catch (PDOException $e) {
    addMessage('error', 'حدث خطأ في قاعدة البيانات: ' . $e->getMessage());
    header('Location: index.php');
    exit;
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="mb-0">تعديل الوحدة</h5>
                </div>
                <div class="col-auto">
                    <a href="index.php" class="btn btn-secondary btn-sm">
                        <i class="bi bi-arrow-right"></i>
                        عودة للقائمة
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="unit_name" class="form-label">اسم الوحدة</label>
                            <input type="text" class="form-control" id="unit_name" name="unit_name" 
                                   value="<?php echo htmlspecialchars($unit['unit_name']); ?>" required>
                            <div class="invalid-feedback">يرجى إدخال اسم الوحدة</div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="building_id" class="form-label">المبنى</label>
                            <select class="form-select" id="building_id" name="building_id" required>
                                <option value="">اختر المبنى</option>
                                <?php foreach ($buildings as $building): ?>
                                    <option value="<?php echo $building['id']; ?>" 
                                            <?php echo $building['id'] == $unit['building_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($building['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">يرجى اختيار المبنى</div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="floor" class="form-label">الطابق</label>
                            <select class="form-select" id="floor" name="floor" required>
                                <option value="">اختر الطابق</option>
                                <?php
                                $floors = ['الأرضي', 'الأول', 'الثاني', 'الثالث', 'الرابع'];
                                foreach ($floors as $floor):
                                ?>
                                    <option value="<?php echo $floor; ?>" 
                                            <?php echo $floor === $unit['floor'] ? 'selected' : ''; ?>>
                                        <?php echo $floor; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">يرجى تحديد الطابق</div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="status" class="form-label">الحالة</label>
                            <select class="form-select" id="status" name="status" required 
                                    <?php echo $hasActiveContract ? 'disabled' : ''; ?>>
                                <option value="شاغرة" <?php echo $unit['status'] === 'شاغرة' ? 'selected' : ''; ?>>شاغرة</option>
                                <option value="مؤجرة" <?php echo $unit['status'] === 'مؤجرة' ? 'selected' : ''; ?>>مؤجرة</option>
                            </select>
                            <?php if ($hasActiveContract): ?>
                                <small class="text-muted">لا يمكن تغيير الحالة لوجود عقد نشط</small>
                            <?php endif; ?>
                            <div class="invalid-feedback">يرجى تحديد حالة الوحدة</div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">وصف الوحدة</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($unit['description']); ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">الصور الحالية</label>
                    <div class="row">
                        <?php 
                        if (!empty($unit['images'])):
                            $images = explode(',', $unit['images']);
                            foreach ($images as $image):
                                if (!empty($image)):
                        ?>
                        <div class="col-md-3 mb-3">
                            <div class="position-relative">
                                <img src="../uploads/units/<?php echo htmlspecialchars($image); ?>" 
                                     class="img-fluid rounded" alt="صورة الوحدة">
                                <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 m-2"
                                        onclick="deleteImage(this, '<?php echo htmlspecialchars($image); ?>')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                        <?php 
                                endif;
                            endforeach;
                        else:
                        ?>
                        <div class="col-12">
                            <p class="text-muted">لا توجد صور حالية</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="new_images" class="form-label">إضافة صور جديدة</label>
                    <input type="file" class="form-control" id="new_images" name="images[]" 
                           accept="image/*" multiple>
                    <small class="text-muted">يمكنك اختيار أكثر من صورة</small>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function deleteImage(button, imageName) {
    if (confirm('هل أنت متأكد من حذف هذه الصورة؟')) {
        // إرسال طلب حذف الصورة
        fetch('delete_image.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                unit_id: <?php echo $unit_id; ?>,
                image_name: imageName
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // إزالة الصورة من العرض
                button.closest('.col-md-3').remove();
            } else {
                alert('حدث خطأ أثناء حذف الصورة');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('حدث خطأ أثناء حذف الصورة');
        });
    }
}

// Form validation
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms)
        .forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                form.classList.add('was-validated')
            }, false)
        })
})()
</script>

<?php include '../includes/footer.php'; ?>
