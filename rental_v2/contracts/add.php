<?php
require_once '../includes/session.php';
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

// التحقق من تسجيل الدخول
checkAuth();

$errors = [];
$db = getDatabaseConnection();

// جلب قائمة المباني
$buildingsQuery = "SELECT * FROM buildings ORDER BY name";
$buildingsStmt = $db->query($buildingsQuery);
$buildings = $buildingsStmt->fetchAll(PDO::FETCH_ASSOC);

// جلب قائمة المستأجرين
$tenantsQuery = "SELECT * FROM tenants ORDER BY full_name";
$tenantsStmt = $db->query($tenantsQuery);
$tenants = $tenantsStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $building_id = $_POST['building_id'] ?? '';
    $unit_id = $_POST['unit_id'] ?? '';
    $tenant_id = $_POST['tenant_id'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $rent_amount = $_POST['rent_amount'] ?? '';
    $rent_type = $_POST['rent_type'] ?? '';
    $notes = $_POST['notes'] ?? '';

    // التحقق من البيانات
    if (empty($building_id)) {
        $errors[] = "يرجى اختيار المبنى";
    }
    if (empty($unit_id)) {
        $errors[] = "يرجى اختيار الوحدة";
    }
    if (empty($tenant_id)) {
        $errors[] = "يرجى اختيار المستأجر";
    }
    if (empty($start_date)) {
        $errors[] = "يرجى تحديد تاريخ بداية العقد";
    }
    if (empty($end_date)) {
        $errors[] = "يرجى تحديد تاريخ نهاية العقد";
    }
    if (empty($rent_amount)) {
        $errors[] = "يرجى إدخال مبلغ الإيجار";
    }
    if (empty($rent_type)) {
        $errors[] = "يرجى اختيار نوع الإيجار";
    }

    if (empty($errors)) {
        try {
            // إضافة العقد الجديد
            $query = "INSERT INTO contracts (unit_id, tenant_id, start_date, end_date, rent_amount, rent_type, notes, status) 
                      VALUES (:unit_id, :tenant_id, :start_date, :end_date, :rent_amount, :rent_type, :notes, 'ساري')";
            
            $stmt = $db->prepare($query);
            
            $stmt->execute([
                ':unit_id' => $unit_id,
                ':tenant_id' => $tenant_id,
                ':start_date' => $start_date,
                ':end_date' => $end_date,
                ':rent_amount' => $rent_amount,
                ':rent_type' => $rent_type,
                ':notes' => $notes
            ]);

            // تحديث حالة الوحدة إلى مؤجرة
            $updateUnitQuery = "UPDATE units SET status = 'مؤجرة' WHERE unit_id = :unit_id";
            $updateUnitStmt = $db->prepare($updateUnitQuery);
            $updateUnitStmt->execute([':unit_id' => $unit_id]);

            addMessage('success', 'تم إضافة العقد بنجاح');
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'حدث خطأ أثناء إضافة العقد: ' . $e->getMessage();
        }
    }
}

include '../includes/header.php';
?>

<!-- إضافة jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="mb-0">إضافة عقد جديد</h5>
                </div>
                <div class="col text-end">
                    <a href="index.php" class="btn btn-secondary">عودة</a>
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

            <form method="POST" class="needs-validation" novalidate>
                <div class="row">
                    <div class="col-md-6">
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
                            <div class="invalid-feedback">يرجى اختيار المبنى</div>
                        </div>

                        <div class="mb-3">
                            <label for="unit_id" class="form-label">الوحدة</label>
                            <select class="form-select" id="unit_id" name="unit_id" required disabled>
                                <option value="">اختر الوحدة</option>
                            </select>
                            <div class="invalid-feedback">يرجى اختيار الوحدة</div>
                        </div>

                        <div class="mb-3">
                            <label for="tenant_id" class="form-label">المستأجر</label>
                            <select class="form-select" id="tenant_id" name="tenant_id" required>
                                <option value="">اختر المستأجر</option>
                                <?php foreach ($tenants as $tenant): ?>
                                    <option value="<?php echo $tenant['tenant_id']; ?>">
                                        <?php echo htmlspecialchars($tenant['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">يرجى اختيار المستأجر</div>
                        </div>

                        <div class="mb-3">
                            <label for="start_date" class="form-label">تاريخ بداية العقد</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" required>
                            <div class="invalid-feedback">يرجى تحديد تاريخ بداية العقد</div>
                        </div>

                        <div class="mb-3">
                            <label for="end_date" class="form-label">تاريخ نهاية العقد</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" required>
                            <div class="invalid-feedback">يرجى تحديد تاريخ نهاية العقد</div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="rent_amount" class="form-label">مبلغ الإيجار</label>
                            <input type="number" class="form-control" id="rent_amount" name="rent_amount" required>
                            <div class="invalid-feedback">يرجى إدخال مبلغ الإيجار</div>
                        </div>

                        <div class="mb-3">
                            <label for="rent_type" class="form-label">نوع الإيجار</label>
                            <select class="form-select" id="rent_type" name="rent_type" required>
                                <option value="">اختر نوع الإيجار</option>
                                <option value="يومي">يومي</option>
                                <option value="أسبوعي">أسبوعي</option>
                                <option value="شهري">شهري</option>
                                <option value="نصف سنوي">نصف سنوي</option>
                                <option value="سنوي">سنوي</option>
                            </select>
                            <div class="invalid-feedback">يرجى اختيار نوع الإيجار</div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">ملاحظات</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary">إضافة العقد</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // عند تغيير المبنى
    $('#building_id').change(function() {
        var buildingId = $(this).val();
        var unitSelect = $('#unit_id');
        
        // إعادة تعيين قائمة الوحدات
        unitSelect.html('<option value="">اختر الوحدة</option>');
        
        if (buildingId) {
            // تفعيل قائمة الوحدات
            unitSelect.prop('disabled', false);
            
            // جلب الوحدات الشاغرة للمبنى المحدد
            $.ajax({
                url: 'get_available_units.php',
                type: 'POST',
                data: { building_id: buildingId },
                success: function(response) {
                    try {
                        var units = JSON.parse(response);
                        console.log('Units received:', units);
                        units.forEach(function(unit) {
                            unitSelect.append(
                                $('<option></option>')
                                    .val(unit.unit_id)
                                    .text(unit.unit_name)
                            );
                        });
                        unitSelect.prop('disabled', false);
                    } catch (e) {
                        console.error('Error parsing units:', e);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error fetching units:', error);
                }
            });
        } else {
            // تعطيل قائمة الوحدات
            unitSelect.prop('disabled', true);
        }
    });

    // عند تغيير الوحدة
    $('#unit_id').change(function() {
        console.log('Selected unit value:', $(this).val());
    });
});
</script>

<?php include '../includes/footer.php'; ?>
