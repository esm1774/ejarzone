<?php
require_once "../config/config.php";
require_once "../includes/check_permission.php";
require_once "../config/database.php";

// التحقق من تسجيل الدخول والصلاحيات
if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

if (!hasPermission('add_payments')) {
    $_SESSION['error'] = "ليس لديك صلاحية لإضافة دفعة جديدة";
    header("Location: index.php");
    exit();
}

// إنشاء اتصال قاعدة البيانات
$database = new Database();
$db = $database->getConnection();

try {
    // جلب آخر رقم إيصال
    $last_receipt_query = "SELECT receipt_number FROM payments 
                          WHERE receipt_number REGEXP '^[0-9]+$'
                          ORDER BY CAST(receipt_number AS UNSIGNED) DESC 
                          LIMIT 1";
    $last_receipt_stmt = $db->prepare($last_receipt_query);
    $last_receipt_stmt->execute();
    $last_receipt = $last_receipt_stmt->fetch(PDO::FETCH_ASSOC);
    
    // إنشاء رقم الإيصال التالي
    $next_receipt_number = $last_receipt ? (intval($last_receipt['receipt_number']) + 1) : 1000;

    // جلب قائمة العقود النشطة مع قيمة الإيجار
    $contracts_query = "SELECT c.contract_id, t.full_name as tenant_name, u.unit_name, c.rent_amount
                       FROM contracts c
                       JOIN tenants t ON c.tenant_id = t.tenant_id
                       JOIN units u ON c.unit_id = u.unit_id
                       WHERE c.status = 'ساري'
                       ORDER BY t.full_name";
    $contracts_stmt = $db->prepare($contracts_query);
    $contracts_stmt->execute();
    $contracts = $contracts_stmt->fetchAll(PDO::FETCH_ASSOC);

    // جلب طرق الدفع المتاحة
    $payment_methods_query = "SELECT method_id, method_name 
                            FROM payment_methods 
                            WHERE is_active = 1 
                            ORDER BY method_name";
    $payment_methods_stmt = $db->prepare($payment_methods_query);
    $payment_methods_stmt->execute();
    $payment_methods = $payment_methods_stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // التحقق من البيانات المدخلة
        if (empty($_POST['contract_id']) || 
            empty($_POST['payment_date']) || empty($_POST['method_id']) || 
            empty($_POST['receipt_number'])) {
            throw new Exception("جميع الحقول المطلوبة يجب تعبئتها");
        }

        // جلب قيمة الإيجار من العقد
        $rent_query = "SELECT rent_amount FROM contracts WHERE contract_id = :contract_id";
        $rent_stmt = $db->prepare($rent_query);
        $rent_stmt->bindParam(":contract_id", $_POST['contract_id']);
        $rent_stmt->execute();
        $rent_data = $rent_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$rent_data) {
            throw new Exception("لم يتم العثور على العقد");
        }

        // إضافة الدفعة الجديدة
        $query = "INSERT INTO payments 
                 (contract_id, amount, payment_date, method_id, receipt_number, notes) 
                 VALUES 
                 (:contract_id, :amount, :payment_date, :method_id, :receipt_number, :notes)";
        
        $stmt = $db->prepare($query);
        
        $stmt->bindParam(":contract_id", $_POST['contract_id']);
        $stmt->bindParam(":amount", $rent_data['rent_amount']);
        $stmt->bindParam(":payment_date", $_POST['payment_date']);
        $stmt->bindParam(":method_id", $_POST['method_id']);
        $stmt->bindParam(":receipt_number", $_POST['receipt_number']);
        $stmt->bindParam(":notes", $_POST['notes']);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "تم إضافة الدفعة بنجاح";
            header("Location: index.php");
            exit();
        } else {
            throw new Exception("حدث خطأ أثناء إضافة الدفعة");
        }
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
}
?>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/sidebar.php'; ?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">إضافة إيجار جديد</h5>
                </div>
                <div class="card-body">
                    <form method="post" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">العقد</label>
                                    <select name="contract_id" id="contract_select" class="form-control" required>
                                        <option value="">اختر العقد</option>
                                        <?php foreach ($contracts as $contract): ?>
                                        <option value="<?php echo $contract['contract_id']; ?>" 
                                                data-rent="<?php echo $contract['rent_amount']; ?>">
                                            <?php echo htmlspecialchars($contract['tenant_name'] . ' - ' . $contract['unit_name'] . 
                                                     ' (' . number_format($contract['rent_amount'], 2) . ' ريال)'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">يرجى اختيار العقد</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">قيمة الإيجار</label>
                                    <input type="text" id="rent_amount" class="form-control" readonly>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">تاريخ الدفع</label>
                                    <input type="date" name="payment_date" class="form-control" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                    <div class="invalid-feedback">يرجى اختيار تاريخ الدفع</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">طريقة الدفع</label>
                                    <select name="method_id" class="form-control" required>
                                        <option value="">اختر طريقة الدفع</option>
                                        <?php foreach ($payment_methods as $method): ?>
                                        <option value="<?php echo $method['method_id']; ?>">
                                            <?php echo htmlspecialchars($method['method_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">يرجى اختيار طريقة الدفع</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">رقم الإيصال</label>
                                    <input type="text" name="receipt_number" id="receipt_number" 
                                           class="form-control" readonly
                                           value="<?php echo $next_receipt_number; ?>">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">ملاحظات</label>
                                    <textarea name="notes" class="form-control" rows="3"></textarea>
                                </div>

                                <div class="mb-3">
                                    <button type="submit" class="btn btn-primary">حفظ</button>
                                    <a href="index.php" class="btn btn-outline-primary">إلغاء</a>
                                </div>
                            </div>
                        </div>
                    </form>
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

    // عرض قيمة الإيجار عند اختيار العقد
    document.getElementById('contract_select').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const rentAmount = selectedOption.getAttribute('data-rent');
        const rentField = document.getElementById('rent_amount');
        
        if (rentAmount) {
            rentField.value = new Intl.NumberFormat('ar-SA', { 
                style: 'currency', 
                currency: 'SAR' 
            }).format(rentAmount);

            // تحديث رقم الإيصال عند اختيار العقد
            const receiptField = document.getElementById('receipt_number');
            const contractId = this.value;
            const currentDate = new Date();
            const year = currentDate.getFullYear();
            const month = String(currentDate.getMonth() + 1).padStart(2, '0');
            
            // تنسيق رقم الإيصال: السنة-الشهر-رقم العقد-الرقم التسلسلي
            receiptField.value = `${year}${month}-${contractId}-${receiptField.value}`;
        } else {
            rentField.value = '';
        }
    });
})();
</script>

<?php include '../includes/footer.php'; ?>
