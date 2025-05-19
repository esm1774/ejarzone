<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

// التحقق من تسجيل الدخول
checkAuth();

if (!isset($_GET['payment_id']) || !is_numeric($_GET['payment_id'])) {
    addMessage('error', 'معرف الدفعة غير صحيح');
    header('Location: index.php');
    exit;
}

$db = getDatabaseConnection();
$payment_id = $_GET['payment_id'];

try {
    // تفعيل عرض الأخطاء للتصحيح
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // جلب بيانات الدفعة والعقد والمستأجر
    $stmt = $db->prepare("
        SELECT 
            p.*,
            c.contract_id,
            c.start_date,
            c.end_date,
            c.rent_type,
            t.full_name as tenant_name,
            t.phone as tenant_phone,
            t.id_number as tenant_id_number,
            u.unit_name,
            b.name as building_name,
            us.full_name as created_by_name,
            pm.method_name as method_name
        FROM payments p
        JOIN contracts c ON p.contract_id = c.contract_id
        JOIN tenants t ON c.tenant_id = t.tenant_id
        JOIN units u ON c.unit_id = u.unit_id
        JOIN buildings b ON u.building_id = b.id
        JOIN users us ON p.created_by = us.user_id
        JOIN payment_methods pm ON p.method_id = pm.method_id
        WHERE p.payment_id = ?
    ");
    
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        die("لم يتم العثور على الدفعة رقم: " . htmlspecialchars($payment_id));
    }

} catch (PDOException $e) {
    die("حدث خطأ في قاعدة البيانات: " . $e->getMessage());
}

include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <div class="row align-items-center">
                        <div class="col">
                            <h3 class="mb-0">إيصال استلام دفعة إيجار</h3>
                        </div>
                        <div class="col-auto">
                            <a href="print_receipt.php?payment_id=<?php echo $payment_id; ?>" 
                               class="btn btn-light"
                               target="_blank">
                                <i class="bi bi-printer"></i>
                                طباعة
                            </a>
                            <a href="download_receipt.php?payment_id=<?php echo $payment_id; ?>&format=pdf" 
                               class="btn btn-light">
                                <i class="bi bi-file-pdf"></i>
                                PDF
                            </a>
                            <a href="view_installments.php?contract_id=<?php echo $payment['contract_id']; ?>" 
                               class="btn btn-light">
                                عودة
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <h4>إيصال رقم: <?php echo $payment['receipt_number']; ?></h4>
                        <p class="text-muted">تاريخ الدفع: <?php echo date('Y-m-d', strtotime($payment['payment_date'])); ?></p>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="text-primary mb-3">معلومات المستأجر</h5>
                            <table class="table table-sm">
                                <tr>
                                    <th width="35%">الاسم:</th>
                                    <td><?php echo htmlspecialchars($payment['tenant_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>رقم الهوية:</th>
                                    <td><?php echo htmlspecialchars($payment['tenant_id_number']); ?></td>
                                </tr>
                                <tr>
                                    <th>رقم الجوال:</th>
                                    <td><?php echo htmlspecialchars($payment['tenant_phone']); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h5 class="text-primary mb-3">معلومات العقار</h5>
                            <table class="table table-sm">
                                <tr>
                                    <th width="35%">المبنى:</th>
                                    <td><?php echo htmlspecialchars($payment['building_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>الوحدة:</th>
                                    <td><?php echo htmlspecialchars($payment['unit_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>نوع العقد:</th>
                                    <td><?php echo htmlspecialchars($payment['rent_type']); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-12">
                            <h5 class="text-primary mb-3">تفاصيل الدفعة</h5>
                            <table class="table table-bordered">
                                <tr>
                                    <th width="25%">رقم القسط:</th>
                                    <td><?php echo $payment['installment_number']; ?></td>
                                    <th width="25%">المبلغ:</th>
                                    <td><?php echo number_format($payment['amount'], 2); ?> ريال</td>
                                </tr>
                                <tr>
                                    <th>طريقة الدفع:</th>
                                    <td><?php echo htmlspecialchars($payment['method_name']); ?></td>
                                    <th>تاريخ الدفع:</th>
                                    <td><?php echo date('Y-m-d', strtotime($payment['payment_date'])); ?></td>
                                </tr>
                                <?php if (!empty($payment['notes'])): ?>
                                <tr>
                                    <th>ملاحظات:</th>
                                    <td colspan="3"><?php echo nl2br(htmlspecialchars($payment['notes'])); ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-md-6">
                            <p class="mb-0">تم الاستلام بواسطة: <?php echo htmlspecialchars($payment['created_by_name']); ?></p>
                            <p class="text-muted">تاريخ الإنشاء: <?php echo date('Y-m-d H:i', strtotime($payment['created_at'])); ?></p>
                        </div>
                        <div class="col-md-6 text-end">
                            <div style="border-top: 1px solid #dee2e6; padding-top: 10px;">
                                <p class="mb-0">التوقيع: ________________</p>
                                <p class="mb-0">الختم: ________________</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
