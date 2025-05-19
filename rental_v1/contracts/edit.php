<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

include_once "../config/database.php";
include_once "../includes/contract_functions.php";  

$database = new Database();
$db = $database->getConnection();

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

// جلب بيانات العقد الحالي
$query = "SELECT * FROM contracts WHERE contract_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_GET['id']]);
$contract = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contract) {
    header("Location: index.php");
    exit();
}

// جلب قائمة الوحدات (المتاحة + الوحدة الحالية للعقد)
$query = "SELECT * FROM units WHERE status = 'متاح' OR unit_id = ? ORDER BY unit_name";
$stmt_units = $db->prepare($query);
$stmt_units->execute([$contract['unit_id']]);

// جلب قائمة المستأجرين النشطين
$query = "SELECT * FROM tenants WHERE status = 'نشط' OR tenant_id = ? ORDER BY full_name";
$stmt_tenants = $db->prepare($query);
$stmt_tenants->execute([$contract['tenant_id']]);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // التحقق من توفر الوحدة في الفترة المحددة (إذا تم تغيير الوحدة)
        if ($_POST['unit_id'] != $contract['unit_id']) {
            $query = "SELECT COUNT(*) FROM contracts 
                      WHERE unit_id = :unit_id 
                      AND status = 'ساري'
                      AND contract_id != :contract_id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(":unit_id", $_POST['unit_id']);
            $stmt->bindParam(":contract_id", $_GET['id']);
            $stmt->execute();
            
            if ($stmt->fetchColumn() > 0) {
                $error = "هذه الوحدة مؤجرة حالياً";
            }
        }

        if (!isset($error)) {
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

            // إذا تم تغيير حالة العقد، نقوم بتسجيل التغيير
            if ($_POST['status'] != $contract['status']) {
                logContractStatusChange(
                    $contract['contract_id'],
                    $contract['status'],
                    $_POST['status'],
                    'تم تغيير حالة العقد من خلال صفحة التعديل'
                );
            }

            // تحديث العقد
            $query = "UPDATE contracts SET 
                        unit_id = :unit_id,
                        tenant_id = :tenant_id,
                        rent_amount = :rent_amount,
                        start_date = :start_date,
                        next_payment_date = :next_payment_date,
                        rent_type = :rent_type,
                        status = :status
                     WHERE contract_id = :contract_id";
            
            $stmt = $db->prepare($query);
            
            $stmt->bindParam(":unit_id", $_POST['unit_id']);
            $stmt->bindParam(":tenant_id", $_POST['tenant_id']);
            $stmt->bindParam(":rent_amount", $_POST['rent_amount']);
            $stmt->bindParam(":start_date", $_POST['start_date']);
            $stmt->bindParam(":next_payment_date", $next_payment_date->format('Y-m-d'));
            $stmt->bindParam(":rent_type", $_POST['rent_type']);
            $stmt->bindParam(":status", $_POST['status']);
            $stmt->bindParam(":contract_id", $_GET['id']);
            
            if ($stmt->execute()) {
                // تحديث حالة الوحدة القديمة إلى متاح إذا تم تغيير الوحدة
                if ($_POST['unit_id'] != $contract['unit_id']) {
                    $update_old_unit = "UPDATE units SET status = 'متاح' WHERE unit_id = ?";
                    $stmt = $db->prepare($update_old_unit);
                    $stmt->execute([$contract['unit_id']]);
                }
                
                header("Location: view.php?id=" . $_GET['id']);
                exit();
            }
        }
    } catch(PDOException $e) {
        $error = "حدث خطأ أثناء تحديث العقد: " . $e->getMessage();
    }
}
?>


<?php include '../includes/header.php'; ?>

<!-- المحتوى الرئيسي -->
    <div class="container-fluid">
        <div class="row">
            <?php include_once "../includes/sidebar.php"; ?>

            <div class="col-md-10 p-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">تعديل العقد رقم <?php echo $contract['contract_id']; ?></h5>
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
                                        <?php while ($unit = $stmt_units->fetch(PDO::FETCH_ASSOC)): ?>
                                            <option value="<?php echo $unit['unit_id']; ?>" 
                                                    <?php echo ($unit['unit_id'] == $contract['unit_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($unit['unit_name'] . ' - ' . $unit['building'] . ' - الطابق ' . $unit['floor']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <div class="invalid-feedback">يرجى اختيار الوحدة</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="tenant_id" class="form-label">المستأجر</label>
                                    <select class="form-select" id="tenant_id" name="tenant_id" required>
                                        <?php while ($tenant = $stmt_tenants->fetch(PDO::FETCH_ASSOC)): ?>
                                            <option value="<?php echo $tenant['tenant_id']; ?>"
                                                    <?php echo ($tenant['tenant_id'] == $contract['tenant_id']) ? 'selected' : ''; ?>>
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
                                    <input type="date" class="form-control" id="start_date" name="start_date" 
                                           value="<?php echo $contract['start_date']; ?>" required>
                                    <div class="invalid-feedback">يرجى تحديد تاريخ بداية العقد</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="rent_type" class="form-label">نوع الإيجار</label>
                                    <select class="form-select" id="rent_type" name="rent_type" required>
                                        <option value="">اختر نوع الإيجار</option>
                                        <option value="يومي" <?php echo ($contract['rent_type'] == 'يومي') ? 'selected' : ''; ?>>يومي</option>
                                        <option value="شهري" <?php echo ($contract['rent_type'] == 'شهري') ? 'selected' : ''; ?>>شهري</option>
                                        <option value="نصف سنوي" <?php echo ($contract['rent_type'] == 'نصف سنوي') ? 'selected' : ''; ?>>نصف سنوي</option>
                                        <option value="سنوي" <?php echo ($contract['rent_type'] == 'سنوي') ? 'selected' : ''; ?>>سنوي</option>
                                    </select>
                                    <div class="invalid-feedback">يرجى اختيار نوع الإيجار</div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="rent_amount" class="form-label">مبلغ الإيجار</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" class="form-control" id="rent_amount" name="rent_amount" 
                                               value="<?php echo $contract['rent_amount']; ?>" required>
                                        <span class="input-group-text">ريال</span>
                                    </div>
                                    <div class="invalid-feedback">يرجى إدخال مبلغ الإيجار</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="status" class="form-label">حالة العقد</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="ساري" <?php echo ($contract['status'] == 'ساري') ? 'selected' : ''; ?>>ساري</option>
                                        <option value="منتهي" <?php echo ($contract['status'] == 'منتهي') ? 'selected' : ''; ?>>منتهي</option>
                                        <option value="ملغي" <?php echo ($contract['status'] == 'ملغي') ? 'selected' : ''; ?>>ملغي</option>
                                        <option value="مفسوخ" <?php echo ($contract['status'] == 'مفسوخ') ? 'selected' : ''; ?>>مفسوخ</option>
                                    </select>
                                    <div class="invalid-feedback">يرجى اختيار حالة العقد</div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                                <a href="index.php" class="btn btn-secondary">إلغاء</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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
    </script>
   <?php include '../includes/footer.php'; ?>
