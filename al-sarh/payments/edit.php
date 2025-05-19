<?php
require_once "../config/config.php";
require_once "../includes/check_permission.php";
require_once "../config/database.php";

// التحقق من تسجيل الدخول
if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('edit_payments')) {
    $_SESSION['error'] = "ليس لديك صلاحية لتعديل المدفوعات";
    header("Location: index.php");
    exit();
}

// التحقق من وجود معرف الدفعة
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "معرف الدفعة غير صحيح";
    header("Location: index.php");
    exit();
}

$payment_id = $_GET['id'];

// إنشاء اتصال قاعدة البيانات
$database = new Database();
$db = $database->getConnection();

try {
    // جلب تفاصيل الدفعة
    $query = "SELECT p.*, c.contract_id, t.full_name as tenant_name,
              pm.method_name, pm.method_id
              FROM payments p 
              LEFT JOIN contracts c ON p.contract_id = c.contract_id
              LEFT JOIN tenants t ON c.tenant_id = t.tenant_id
              LEFT JOIN payment_methods pm ON p.method_id = pm.method_id
              WHERE p.payment_id = :payment_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":payment_id", $payment_id);
    $stmt->execute();
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        throw new Exception("الدفعة غير موجودة");
    }

    // جلب قائمة طرق الدفع المتاحة
    $payment_methods_query = "SELECT method_id, method_name FROM payment_methods WHERE is_active = 1 ORDER BY method_name";
    $payment_methods_stmt = $db->prepare($payment_methods_query);
    $payment_methods_stmt->execute();
    $payment_methods = $payment_methods_stmt->fetchAll(PDO::FETCH_ASSOC);

    // معالجة تحديث البيانات
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // التحقق من البيانات المدخلة
        if (empty($_POST['amount']) || empty($_POST['payment_date']) || 
            empty($_POST['method_id']) || empty($_POST['receipt_number'])) {
            throw new Exception("جميع الحقول المطلوبة يجب تعبئتها");
        }

        $query = "UPDATE payments SET 
                  amount = :amount,
                  payment_date = :payment_date,
                  method_id = :method_id,
                  receipt_number = :receipt_number,
                  notes = :notes
                  WHERE payment_id = :payment_id";
        
        $stmt = $db->prepare($query);
        
        $stmt->bindParam(":amount", $_POST['amount']);
        $stmt->bindParam(":payment_date", $_POST['payment_date']);
        $stmt->bindParam(":method_id", $_POST['method_id']);
        $stmt->bindParam(":receipt_number", $_POST['receipt_number']);
        $stmt->bindParam(":notes", $_POST['notes']);
        $stmt->bindParam(":payment_id", $payment_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "تم تحديث بيانات الدفعة بنجاح";
            header("Location: view.php?id=" . $payment_id);
            exit();
        } else {
            throw new Exception("حدث خطأ أثناء تحديث البيانات");
        }
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header("Location: index.php");
    exit();
}
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<!-- المحتوى الرئيسي -->
<div class="main-content">
    <div class="container-fluid py-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">تعديل بيانات الدفعة</h5>
            </div>
            <div class="card-body">
                <form method="post" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">رقم العقد</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($payment['contract_id'] ?? 'غير متوفر'); ?>" readonly>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">اسم المستأجر</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($payment['tenant_name'] ?? 'غير متوفر'); ?>" readonly>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">المبلغ</label>
                                <input type="number" step="0.01" name="amount" class="form-control" value="<?php echo htmlspecialchars($payment['amount']); ?>" required>
                                <div class="invalid-feedback">يرجى إدخال المبلغ</div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">تاريخ الدفع</label>
                                <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d', strtotime($payment['payment_date'])); ?>" required>
                                <div class="invalid-feedback">يرجى اختيار تاريخ الدفع</div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">طريقة الدفع</label>
                                <select name="method_id" class="form-control" required>
                                    <option value="">اختر طريقة الدفع</option>
                                    <?php foreach ($payment_methods as $method): ?>
                                    <option value="<?php echo $method['method_id']; ?>" 
                                            <?php echo $payment['method_id'] == $method['method_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($method['method_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">يرجى اختيار طريقة الدفع</div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">رقم الإيصال</label>
                                <input type="text" name="receipt_number" class="form-control" value="<?php echo htmlspecialchars($payment['receipt_number']); ?>" required>
                                <div class="invalid-feedback">يرجى إدخال رقم الإيصال</div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">ملاحظات</label>
                                <textarea name="notes" class="form-control" rows="3"><?php echo htmlspecialchars($payment['notes'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                                <a href="view.php?id=<?php echo $payment_id; ?>" class="btn btn-outline-primary">إلغاء</a>
                            </div>
                        </div>
                    </div>
                </form>
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

<?php include '../includes/footer.php'; ?>
