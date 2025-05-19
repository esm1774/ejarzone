<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

// التحقق من تسجيل الدخول
checkAuth();

// التحقق من الصلاحيات
checkPermission('view_reports');

// الحصول على اتصال قاعدة البيانات
$pdo = getDatabaseConnection();

// استلام المعايير
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// دالة لجلب المدفوعات المتأخرة
function getLatePayments($start_date, $end_date) {
    $pdo = getDatabaseConnection();
    
    $query = "
        SELECT 
            c.contract_id,
            t.full_name,
            u.unit_name,
            c.rent_amount,
            c.next_due_date,
            DATEDIFF(CURDATE(), c.next_due_date) as days_late,
            CASE 
                WHEN DATEDIFF(CURDATE(), c.next_due_date) <= 0 THEN 0
                ELSE c.rent_amount * (DATEDIFF(CURDATE(), c.next_due_date) / 30.0)
            END as late_amount
        FROM contracts c 
        JOIN tenants t ON c.tenant_id = t.tenant_id 
        JOIN units u ON c.unit_id = u.unit_id 
        WHERE c.next_due_date < CURDATE()
        AND c.next_due_date BETWEEN ? AND ?
        AND c.status = 'ساري'
        ORDER BY c.next_due_date ASC";
    
    error_log("SQL Query: " . $query);
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$start_date, $end_date]);
    $results = $stmt->fetchAll();
    
    error_log("عدد النتائج: " . count($results));
    
    return $results;
}

// جلب المدفوعات المتأخرة
$late_payments = getLatePayments($start_date, $end_date);

// حساب إجماليات
$total_late = count($late_payments);
$total_amount = array_sum(array_column($late_payments, 'rent_amount'));
$total_late_amount = array_sum(array_column($late_payments, 'late_amount'));

?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقرير المدفوعات المتأخرة - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container mt-4">
        <h2 class="mb-4">تقرير المدفوعات المتأخرة</h2>
        
        <!-- معلومات التقرير -->
        <div class="alert alert-info">
            إجمالي عدد المدفوعات المتأخرة: <?php echo number_format($total_late); ?>
            <br>
            إجمالي المبالغ المتأخرة: <?php echo number_format($total_amount, 2); ?> ريال
            <br>
            إجمالي قيمة المتأخرات: <?php echo number_format($total_late_amount, 2); ?> ريال
        </div>
        
        <!-- نموذج الفلترة -->
        <form class="row g-3 mb-4">
            <div class="col-md-4">
                <label for="start_date" class="form-label">من تاريخ</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
            </div>
            <div class="col-md-4">
                <label for="end_date" class="form-label">إلى تاريخ</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary d-block">تصفية</button>
            </div>
        </form>

        <!-- جدول المدفوعات المتأخرة -->
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>رقم العقد</th>
                        <th>اسم المستأجر</th>
                        <th>رقم الوحدة</th>
                        <th>مبلغ الإيجار</th>
                        <th>يوم الدفع</th>
                        <th>عدد أيام التأخير</th>
                        <th>قيمة المتأخرات</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($late_payments as $payment): ?>
                    <tr>
                        <td><?php echo $payment['contract_id']; ?></td>
                        <td><?php echo $payment['full_name']; ?></td>
                        <td><?php echo $payment['unit_name']; ?></td>
                        <td><?php echo number_format($payment['rent_amount'], 2); ?> ريال</td>
                        <td><?php echo $payment['next_due_date']; ?></td>
                        <td><?php echo $payment['days_late']; ?> يوم</td>
                        <td><?php echo number_format($payment['late_amount'], 2); ?> ريال</td>
                        <td>
                            <a href="../contracts/view.php?id=<?php echo $payment['contract_id']; ?>" class="btn btn-sm btn-primary">عرض العقد</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
