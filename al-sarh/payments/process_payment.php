<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

// التحقق من تسجيل الدخول
checkAuth();

$db = getDatabaseConnection();

// التحقق من وجود المعرفات المطلوبة
if (!isset($_GET['contract_id']) || !isset($_GET['installment'])) {
    addMessage('error', 'بيانات غير مكتملة');
    header('Location: index.php');
    exit;
}

$contract_id = $_GET['contract_id'];
$installment_number = $_GET['installment'];

try {
    // جلب بيانات طرق الدفع
    $methodStmt = $db->prepare("SELECT method_id, method_name FROM payment_methods WHERE is_active = TRUE ORDER BY method_name");
    $methodStmt->execute();
    $paymentMethods = $methodStmt->fetchAll(PDO::FETCH_ASSOC);

    // جلب بيانات العقد
    $stmt = $db->prepare("
        SELECT c.*, t.full_name as tenant_name 
        FROM contracts c 
        JOIN tenants t ON c.tenant_id = t.tenant_id 
        WHERE c.contract_id = ?
    ");
    $stmt->execute([$contract_id]);
    $contract = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$contract) {
        addMessage('error', 'العقد غير موجود');
        header('Location: index.php');
        exit;
    }

    // التحقق من عدم وجود دفعة مسجلة لهذا القسط
    $checkStmt = $db->prepare("
        SELECT payment_id 
        FROM payments 
        WHERE contract_id = ? AND installment_number = ?
    ");
    $checkStmt->execute([$contract_id, $installment_number]);
    if ($checkStmt->fetch()) {
        addMessage('error', 'تم تسجيل دفعة لهذا القسط مسبقاً');
        header("Location: view_installments.php?contract_id=$contract_id");
        exit;
    }

} catch (PDOException $e) {
    addMessage('error', 'حدث خطأ في قاعدة البيانات: ' . $e->getMessage());
    header('Location: index.php');
    exit;
}

include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">تسجيل دفعة جديدة</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="save_payment.php">
                        <input type="hidden" name="contract_id" value="<?php echo $contract_id; ?>">
                        <input type="hidden" name="installment_number" value="<?php echo $installment_number; ?>">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">المستأجر</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($contract['tenant_name']); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">رقم القسط</label>
                                <input type="text" class="form-control" value="<?php echo $installment_number; ?>" readonly>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">المبلغ</label>
                                <input type="text" class="form-control" value="<?php echo number_format($contract['rent_amount'], 2); ?> ريال" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">تاريخ الدفع</label>
                                <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">طريقة الدفع</label>
                                <select name="method_id" class="form-select" required>
                                    <option value="">اختر طريقة الدفع</option>
                                    <?php foreach ($paymentMethods as $method): ?>
                                        <option value="<?php echo htmlspecialchars($method['method_id']); ?>">
                                            <?php echo htmlspecialchars($method['method_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ملاحظات</label>
                                <textarea name="notes" class="form-control" rows="2"></textarea>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i>
                                    حفظ الدفعة
                                </button>
                                <a href="view_installments.php?contract_id=<?php echo $contract_id; ?>" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i>
                                    إلغاء
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
