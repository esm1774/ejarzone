<?php
require_once '../includes/session.php';
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

// التحقق من تسجيل الدخول
checkAuth();

$db = getDatabaseConnection();

// التحقق من وجود معرف العقد
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    addMessage('error', 'معرف العقد غير صحيح');
    header('Location: index.php');
    exit;
}

$contract_id = $_GET['id'];

try {
    // جلب بيانات العقد والمستأجر والوحدة
    $stmt = $db->prepare("
        SELECT 
            c.*,
            t.full_name as tenant_name,
            t.phone as tenant_phone,
            t.email as tenant_email,
            t.id_number as tenant_id_number,
            u.unit_name,
            b.name as building_name
        FROM contracts c
        JOIN tenants t ON c.tenant_id = t.tenant_id
        JOIN units u ON c.unit_id = u.unit_id
        JOIN buildings b ON u.building_id = b.id
        WHERE c.contract_id = ?
    ");
    
    $stmt->execute([$contract_id]);
    $contract = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$contract) {
        addMessage('error', 'العقد غير موجود');
        header('Location: index.php');
        exit;
    }

    // حساب عدد الدفعات بناءً على نوع العقد
    $startDate = new DateTime($contract['start_date']);
    $endDate = new DateTime($contract['end_date']);
    $interval = $startDate->diff($endDate);
    
    // حساب عدد الأيام الكلية للعقد
    $totalDays = $interval->days;
    
    $numberOfInstallments = 0;
    switch ($contract['rent_type']) {
        case 'يومي':
            $numberOfInstallments = $totalDays; // عدد الأيام
            break;
        case 'أسبوعي':
            $numberOfInstallments = ceil($totalDays / 7); // عدد الأسابيع
            break;
        case 'شهري':
            $numberOfInstallments = ceil($totalDays / 30)-1; // عدد الأشهر
            break;
        case 'ربع سنوي':
            $numberOfInstallments = ceil($totalDays / 91.25)-1; // تقريباً كل 3 شهور
            break;
        case 'نصف سنوي':
            $numberOfInstallments = ceil($totalDays / 182.5)-1; // تقريباً كل 6 شهور
            break;
        case 'سنوي':
            $numberOfInstallments = ceil($totalDays / 365)-1; // عدد السنوات
            break;
    }

    // جلب الدفعات المسجلة
    $paymentsStmt = $db->prepare("
        SELECT 
            p.*,
            CONCAT(u.first_name, ' ', u.last_name) as received_by_name
        FROM payments p
        LEFT JOIN users u ON p.created_by = u.id
        WHERE p.contract_id = ?
        ORDER BY p.installment_number ASC
    ");
    $paymentsStmt->execute([$contract_id]);
    $payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);

    // تنظيم الدفعات في مصفوفة
    $paymentsMap = [];
    foreach ($payments as $payment) {
        $paymentsMap[$payment['installment_number']] = $payment;
    }

} catch (PDOException $e) {
    addMessage('error', 'حدث خطأ في قاعدة البيانات: ' . $e->getMessage());
    header('Location: index.php');
    exit;
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>
<div class="main-content">
    <div class="container-fluid py-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <div class="row align-items-center">
                    <div class="col">
                        <h3 class="mb-0">تفاصيل العقد</h3>
                    </div>
                    <div class="col text-end">
                        <a href="edit.php?id=<?php echo $contract_id; ?>" class="btn btn-light">تعديل</a>
                        <a href="index.php" class="btn btn-light">عودة</a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!-- معلومات المستأجر -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5 class="text-primary mb-3">معلومات المستأجر</h5>
                        <table class="table table-sm">
                            <tr>
                                <th width="30%">الاسم:</th>
                                <td><?php echo htmlspecialchars($contract['tenant_name']); ?></td>
                            </tr>
                            <tr>
                                <th>رقم الهوية:</th>
                                <td><?php echo htmlspecialchars($contract['tenant_id_number']); ?></td>
                            </tr>
                            <tr>
                                <th>رقم الجوال:</th>
                                <td><?php echo htmlspecialchars($contract['tenant_phone']); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h5 class="text-primary mb-3">معلومات العقد</h5>
                        <table class="table table-sm">
                            <tr>
                                <th width="30%">رقم العقد:</th>
                                <td><?php echo htmlspecialchars($contract['contract_id']); ?></td>
                            </tr>
                            <tr>
                                <th>المبنى:</th>
                                <td><?php echo htmlspecialchars($contract['building_name']); ?></td>
                            </tr>
                            <tr>
                                <th>الوحدة:</th>
                                <td><?php echo htmlspecialchars($contract['unit_name']); ?></td>
                            </tr>
                            <tr>
                                <th>نوع العقد:</th>
                                <td><?php echo htmlspecialchars($contract['rent_type']); ?></td>
                            </tr>
                            <tr>
                                <th>قيمة الإيجار:</th>
                                <td><?php echo number_format($contract['rent_amount'], 2); ?> ريال</td>
                            </tr>
                            <tr>
                                <th>تاريخ البداية:</th>
                                <td><?php echo htmlspecialchars($contract['start_date']); ?></td>
                            </tr>
                            <tr>
                                <th>تاريخ النهاية:</th>
                                <td><?php echo htmlspecialchars($contract['end_date']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- جدول الدفعات -->
                <h5 class="text-primary mb-3">الدفعات المستحقة</h5>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>رقم القسط</th>
                                <th>تاريخ الاستحقاق</th>
                                <th>المبلغ</th>
                                <th>حالة الدفع</th>
                                <th>تاريخ الدفع</th>
                                <th>طريقة الدفع</th>
                                <th>المستلم</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $today = new DateTime();
                            for ($i = 1; $i <= $numberOfInstallments; $i++):
                                // حساب تاريخ استحقاق الدفعة
                                $dueDate = new DateTime($contract['start_date']);
                                switch ($contract['rent_type']) {
                                    case 'يومي':
                                        $dueDate->modify('+' . ($i-1) . ' day');
                                        break;
                                    case 'أسبوعي':
                                        $dueDate->modify('+' . ($i-1) . ' week');
                                        break;
                                    case 'شهري':
                                        $dueDate->modify('+' . ($i-1) . ' month');
                                        break;
                                    case 'ربع سنوي':
                                        $dueDate->modify('+' . ($i-1)*3 . ' month');
                                        break;
                                    case 'نصف سنوي':
                                        $dueDate->modify('+' . ($i-1)*6 . ' month');
                                        break;
                                    case 'سنوي':
                                        $dueDate->modify('+' . ($i-1) . ' year');
                                        break;
                                }

                                $payment = $paymentsMap[$i] ?? null;
                                $status = 'مستحق';
                                $statusClass = 'bg-warning text-dark';
                                
                                if ($payment) {
                                    $status = 'مدفوع';
                                    $statusClass = 'bg-success text-white';
                                } else {
                                    // مقارنة الشهر والسنة
                                    $currentMonth = $today->format('Y-m');
                                    $dueMonth = $dueDate->format('Y-m');
                                    
                                    if ($dueMonth > $currentMonth) {
                                        $status = 'مستقبلية';
                                        $statusClass = 'bg-info text-white';
                                    } elseif ($dueMonth < $currentMonth) {
                                        $status = 'متأخر';
                                        $statusClass = 'bg-danger text-white';
                                    } else {
                                        $status = 'مستحق';
                                        $statusClass = 'bg-warning text-dark';
                                    }
                                }
                            ?>
                            <tr>
                                <td><?php echo $i; ?></td>
                                <td><?php echo $dueDate->format('Y-m-d'); ?></td>
                                <td><?php echo number_format($contract['rent_amount'], 2); ?> ريال</td>
                                <td><span class="badge <?php echo $statusClass; ?>"><?php echo $status; ?></span></td>
                                <td><?php echo $payment ? date('Y-m-d', strtotime($payment['payment_date'])) : '-'; ?></td>
                                <td><?php echo $payment ? htmlspecialchars($payment['payment_method']) : '-'; ?></td>
                                <td><?php echo $payment ? htmlspecialchars($payment['received_by_name']) : '-'; ?></td>
                                <td>
                                    <div class="btn-group">
                                        <?php if (!$payment): ?>
                                            <a href="../payments/process_payment.php?contract_id=<?php echo $contract_id; ?>&installment=<?php echo $i; ?>" 
                                            class="btn btn-sm btn-success">
                                                <i class="bi bi-cash-coin"></i>
                                                دفع
                                            </a>
                                        <?php else: ?>
                                            <a href="../payments/view_receipt.php?payment_id=<?php echo $payment['payment_id']; ?>" 
                                            class="btn btn-sm btn-info"
                                            data-bs-toggle="tooltip"
                                            title="عرض الإيصال">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="../payments/print_receipt.php?payment_id=<?php echo $payment['payment_id']; ?>" 
                                            class="btn btn-sm btn-primary"
                                            target="_blank"
                                            data-bs-toggle="tooltip"
                                            title="طباعة الإيصال">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>

                <!-- ملخص الدفعات -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h5 class="card-title">ملخص الدفعات</h5>
                                <table class="table table-sm">
                                    <tr>
                                        <th>إجمالي عدد الدفعات:</th>
                                        <td><?php echo $numberOfInstallments; ?></td>
                                    </tr>
                                    <tr>
                                        <th>الدفعات المسددة:</th>
                                        <td><?php echo count($payments); ?></td>
                                    </tr>
                                    <tr>
                                        <th>الدفعات المتبقية:</th>
                                        <td><?php echo $numberOfInstallments - count($payments); ?></td>
                                    </tr>
                                    <tr>
                                        <th>إجمالي المبلغ:</th>
                                        <td><?php echo number_format($contract['rent_amount'] * $numberOfInstallments, 2); ?> ريال</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
