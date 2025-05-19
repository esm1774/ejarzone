<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

include_once "../config/database.php";
$database = new Database();
$db = $database->getConnection();

// جلب قائمة الوحدات المتاحة
$query = "SELECT * FROM units WHERE status = 'متاح' ORDER BY unit_name";
$stmt_units = $db->prepare($query);
$stmt_units->execute();

// جلب قائمة المستأجرين النشطين
$query = "SELECT * FROM tenants WHERE status = 'نشط' ORDER BY full_name";
$stmt_tenants = $db->prepare($query);
$stmt_tenants->execute();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // التحقق من توفر الوحدة
        $query = "SELECT COUNT(*) FROM contracts 
                  WHERE unit_id = :unit_id 
                  AND status = 'ساري'";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":unit_id", $_POST['unit_id']);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            $error = "هذه الوحدة مؤجرة حالياً";
        } else {
            // تحديد تاريخ استحقاق الإيجار بناءً على نوع الإيجار
            $start_date = new DateTime($_POST['start_date']);
            $next_payment_date = clone $start_date;
            
            switch($_POST['rent_type']) {
                case 'يومي':
                    $next_payment_date->modify('+1 day');
                    break;
                case 'شهري':
                    $next_payment_date->modify('+1 month');
                    break;
                case 'نصف سنوي':
                    $next_payment_date->modify('+6 months');
                    break;
                case 'سنوي':
                    $next_payment_date->modify('+1 year');
                    break;
            }

            // إضافة العقد
            $query = "INSERT INTO contracts (unit_id, tenant_id, rent_amount, start_date, end_date, next_payment_date, rent_type, status) 
                      VALUES (:unit_id, :tenant_id, :rent_amount, :start_date, :end_date, :next_payment_date, :rent_type, 'ساري')";
            
            $stmt = $db->prepare($query);

            // حساب تاريخ نهاية العقد
            $end_date = new DateTime($_POST['end_date']);
            
            $stmt->bindParam(":unit_id", $_POST['unit_id']);
            $stmt->bindParam(":tenant_id", $_POST['tenant_id']);
            $stmt->bindParam(":rent_amount", $_POST['rent_amount']);
            $stmt->bindParam(":start_date", $_POST['start_date']);
            $stmt->bindParam(":end_date", $_POST['end_date']);
            $stmt->bindParam(":next_payment_date", $_POST['next_payment_date']);
            $stmt->bindParam(":rent_type", $_POST['rent_type']);
            
            if ($stmt->execute()) {
                // تحديث حالة الوحدة إلى غير متاح
                $query = "UPDATE units SET status = 'غير متاح' WHERE unit_id = :unit_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":unit_id", $_POST['unit_id']);
                $stmt->execute();

                header("Location: index.php?success=1");
                exit();
            } else {
                $error = "حدث خطأ أثناء إضافة العقد";
            }
        }
    } catch (PDOException $e) {
        $error = "خطأ في قاعدة البيانات: " . $e->getMessage();
    }
}
?>

<?php include '../includes/header.php'; ?>

<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">إضافة عقد جديد</h5>
        </div>
        <div class="card-body">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" class="needs-validation" novalidate>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="unit_id" class="form-label">الوحدة</label>
                        <select class="form-select" id="unit_id" name="unit_id" required>
                            <option value="">اختر الوحدة</option>
                            <?php while ($unit = $stmt_units->fetch(PDO::FETCH_ASSOC)): ?>
                                <option value="<?php echo $unit['unit_id']; ?>">
                                    <?php echo htmlspecialchars($unit['unit_name'] . ' - ' . $unit['building'] . ' - الطابق ' . $unit['floor']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <div class="invalid-feedback">يرجى اختيار الوحدة</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="tenant_id" class="form-label">المستأجر</label>
                        <select class="form-select" id="tenant_id" name="tenant_id" required>
                            <option value="">اختر المستأجر</option>
                            <?php while ($tenant = $stmt_tenants->fetch(PDO::FETCH_ASSOC)): ?>
                                <option value="<?php echo $tenant['tenant_id']; ?>">
                                    <?php echo htmlspecialchars($tenant['full_name'] . ' - ' . $tenant['phone']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <div class="invalid-feedback">يرجى اختيار المستأجر</div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="start_date" class="form-label">تاريخ بداية العقد</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" required>
                        <div class="invalid-feedback">يرجى تحديد تاريخ بداية العقد</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="end_date" class="form-label">تاريخ نهاية العقد</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" required>
                        <div class="invalid-feedback">يرجى تحديد تاريخ نهاية العقد</div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="next_payment_date" class="form-label">تاريخ استحقاق الإيجار الأول</label>
                        <input type="date" class="form-control" id="next_payment_date" name="next_payment_date" required>
                        <div class="invalid-feedback">يرجى تحديد تاريخ استحقاق الإيجار الأول</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="rent_type" class="form-label">نوع الإيجار</label>
                        <select class="form-select" id="rent_type" name="rent_type" required>
                            <option value="">اختر نوع الإيجار</option>
                            <option value="يومي">يومي</option>
                            <option value="شهري">شهري</option>
                            <option value="نصف سنوي">نصف سنوي</option>
                            <option value="سنوي">سنوي</option>
                        </select>
                        <div class="invalid-feedback">يرجى اختيار نوع الإيجار</div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="rent_amount" class="form-label">مبلغ الإيجار</label>
                        <div class="input-group">
                            <input type="number" step="0.01" class="form-control" id="rent_amount" name="rent_amount" required>
                            <span class="input-group-text">ريال</span>
                        </div>
                        <div class="invalid-feedback">يرجى إدخال مبلغ الإيجار</div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">حفظ العقد</button>
            </form>
        </div>
    </div>
</div>

<script>
// تفعيل التحقق من صحة النموذج
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

// تحديث تاريخ استحقاق الإيجار تلقائياً عند تغيير تاريخ بداية العقد
document.getElementById('start_date').addEventListener('change', function() {
    document.getElementById('next_payment_date').value = this.value;
    updateEndDate(); // تحديث تاريخ نهاية العقد عند تغيير تاريخ البداية
});

// تحديث تاريخ نهاية العقد تلقائياً عند تغيير نوع الإيجار
document.getElementById('rent_type').addEventListener('change', function() {
    updateEndDate();
});

// دالة لتحديث تاريخ نهاية العقد
function updateEndDate() {
    const startDate = document.getElementById('start_date').value;
    const rentType = document.getElementById('rent_type').value;
    
    if (startDate && rentType) {
        const endDate = new Date(startDate);
        
        switch(rentType) {
            case 'يومي':
                endDate.setDate(endDate.getDate() + 1);
                break;
            case 'شهري':
                endDate.setFullYear(endDate.getFullYear() + 1); // عقد سنوي يتجدد شهرياً
                break;
            case 'نصف سنوي':
                endDate.setMonth(endDate.getMonth() + 6);
                break;
            case 'سنوي':
                endDate.setFullYear(endDate.getFullYear() + 1);
                break;
        }
        
        // تنسيق التاريخ بالشكل YYYY-MM-DD
        const formattedDate = endDate.toISOString().split('T')[0];
        document.getElementById('end_date').value = formattedDate;
    }
}
</script>

<?php include '../includes/footer.php'; ?>
