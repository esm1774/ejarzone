<?php
require_once "../config/config.php";
require_once "../includes/check_permission.php";
require_once "../config/database.php";

// التحقق من تسجيل الدخول والصلاحيات
if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

if (!hasPermission('edit_revenues')) {
    $_SESSION['error'] = "ليس لديك صلاحية لتعديل الإيرادات";
    header("Location: index.php");
    exit();
}

// إنشاء اتصال بقاعدة البيانات
$database = new Database();
$db = $database->getConnection();

// التحقق من وجود معرف الإيراد
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$revenue_id = $_GET['id'];

// جلب بيانات الإيراد
$query = "
    SELECT 
        r.*,
        rt.type_name,
        pm.method_name
    FROM 
        revenues r
        JOIN revenue_types rt ON r.type_id = rt.type_id
        LEFT JOIN payment_methods pm ON r.method_id = pm.method_id
    WHERE 
        r.revenue_id = :revenue_id
";

$stmt = $db->prepare($query);
$stmt->execute(['revenue_id' => $revenue_id]);
$revenue = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$revenue) {
    $_SESSION['error'] = "الإيراد غير موجود";
    header('Location: index.php');
    exit;
}

// جلب أنواع الإيرادات
$types = $db->query("SELECT * FROM revenue_types ORDER BY type_name");

// جلب طرق الدفع
$payment_methods = $db->query("SELECT * FROM payment_methods WHERE is_active = 1");

$page_title = "تعديل الإيراد";
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">تعديل الإيراد</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="process_revenue.php" class="needs-validation" novalidate>
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="revenue_id" value="<?php echo $revenue_id; ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="receipt_number" class="form-label">رقم الإيصال</label>
                                        <input type="text" class="form-control" id="receipt_number" 
                                            value="<?php echo htmlspecialchars($revenue['receipt_number']); ?>" readonly>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="payment_date" class="form-label">تاريخ الإيراد <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="payment_date" 
                                            name="payment_date" required 
                                            value="<?php echo date('Y-m-d', strtotime($revenue['payment_date'])); ?>">
                                        <div class="invalid-feedback">يرجى إدخال تاريخ الإيراد</div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="type_id" class="form-label">نوع الإيراد <span class="text-danger">*</span></label>
                                        <select class="form-select" id="type_id" name="type_id" required>
                                            <option value="">اختر نوع الإيراد</option>
                                            <?php while ($type = $types->fetch()): ?>
                                            <option value="<?php echo $type['type_id']; ?>" 
                                                <?php echo ($type['type_id'] == $revenue['type_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($type['type_name']); ?>
                                            </option>
                                            <?php endwhile; ?>
                                        </select>
                                        <div class="invalid-feedback">يرجى اختيار نوع الإيراد</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="amount" class="form-label">المبلغ <span class="text-danger">*</span></label>
                                        <input type="number" step="0.01" class="form-control" id="amount" 
                                            name="amount" required value="<?php echo $revenue['amount']; ?>">
                                        <div class="invalid-feedback">يرجى إدخال المبلغ</div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="method_id" class="form-label">طريقة الدفع <span class="text-danger">*</span></label>
                                        <select class="form-control" id="method_id" name="method_id" required>
                                            <option value="">اختر طريقة الدفع</option>
                                            <?php while ($method = $payment_methods->fetch()): ?>
                                            <option value="<?php echo $method['method_id']; ?>" 
                                                <?php echo ($method['method_id'] == $revenue['method_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($method['method_name']); ?>
                                            </option>
                                            <?php endwhile; ?>
                                        </select>
                                        <div class="invalid-feedback">يرجى اختيار طريقة الدفع</div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="received_by" class="form-label">المُسلم <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="received_by" name="received_by" 
                                            required value="<?php echo htmlspecialchars($revenue['received_by']); ?>">
                                        <div class="invalid-feedback">يرجى إدخال اسم المُسلم</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label for="description" class="form-label">الوصف</label>
                                        <textarea class="form-control" id="description" name="description" 
                                                rows="3"><?php echo htmlspecialchars($revenue['description']); ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="text-end mt-3">
                                <a href="index.php" class="btn btn-outline-primary">
                                    <i class="bi bi-arrow-right"></i> عودة للقائمة
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg"></i> حفظ التغييرات
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
// تفعيل التحقق من صحة النموذج
(function() {
    'use strict';
    var forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();
</script>

<?php include "../includes/footer.php"; ?>
