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
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'revenue';

// للتأكد من البيانات
$debug_query = $pdo->query("SELECT COUNT(*) as total FROM payments");
$total_payments = $debug_query->fetch()['total'];

// دالة لجلب الإيرادات
// دالة لجلب الإيرادات
function getRevenues($start_date, $end_date) {
    $pdo = getDatabaseConnection();
    
    // للتأكد من الاستعلام
    error_log("Revenue Query Dates: $start_date to $end_date");
    
    // جلب الإيجارات من جدول payments
    $rent_query = $pdo->prepare("
        SELECT 
            DATE_FORMAT(payment_date, '%Y-%m') as month,
            SUM(amount) as total_amount,
            COUNT(payment_id) as count,
            'إيجارات' as payment_type
        FROM payments
        WHERE DATE(payment_date) BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
    ");
    
    // جلب الإيرادات الأخرى من جدول revenues مع نوع الإيراد
    $revenue_query = $pdo->prepare("
        SELECT 
            DATE_FORMAT(r.payment_date, '%Y-%m') as month,
            SUM(r.amount) as total_amount,
            COUNT(r.revenue_id) as count,
            rt.type_name as payment_type
        FROM revenues r
        JOIN revenue_types rt ON r.type_id = rt.type_id
        WHERE DATE(r.payment_date) BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(r.payment_date, '%Y-%m'), rt.type_id, rt.type_name
        ORDER BY r.payment_date
    ");
    
    $rent_query->execute([$start_date, $end_date]);
    $revenue_query->execute([$start_date, $end_date]);
    
    $rent_results = $rent_query->fetchAll();
    $revenue_results = $revenue_query->fetchAll();
    
    // للتأكد من النتائج
    error_log("Rent Results: " . json_encode($rent_results));
    error_log("Revenue Results: " . json_encode($revenue_results));
    
    // دمج النتائج
    $results = array_merge($rent_results, $revenue_results);
    
    return $results;
}

// دالة لجلب المصروفات
function getTotalExpenses($start_date, $end_date) {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare("
        SELECT
            DATE_FORMAT(e.expense_date, '%Y-%m') as month,
            t.type_name,
            SUM(e.amount) as total_expense,
            COUNT(*) as count
        FROM expenses e
        JOIN expense_types t ON e.type_id = t.type_id
        WHERE DATE(e.expense_date) BETWEEN ? AND ?
        GROUP BY t.type_id, t.type_name, DATE_FORMAT(e.expense_date, '%Y-%m')
        ORDER BY e.expense_date, t.type_name
    ");
    $stmt->execute([$start_date, $end_date]);
    $results = $stmt->fetchAll();
    
    // للتأكد من النتائج
    error_log("Expense Results: " . json_encode($results));
    
    return $results;
}

// جلب البيانات حسب نوع التقرير
$data = [];
if ($report_type == 'revenue') {
    $data = getRevenues($start_date, $end_date);
} elseif ($report_type == 'expense') {
    $data = getTotalExpenses($start_date, $end_date);
}

// للتأكد من البيانات النهائية
error_log("Final Data: " . json_encode($data));
error_log("Total Payments in Database: " . $total_payments);
error_log("Current Report Type: " . $report_type);
error_log("Date Range: " . $start_date . " to " . $end_date);

?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>التقارير المالية - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container mt-4">
        <h2 class="mb-4">التقارير المالية</h2>
        
                <!-- معلومات التصحيح -->
                <?php if(!empty($data)): ?>
                <div class="alert alert-info">
                    <?php 
                    if ($report_type == "revenue") {
                        $total_operations = array_sum(array_column($data, 'count'));
                        echo "إجمالي عدد المدفوعات في الفترة المحددة: " . number_format($total_operations);
                    } else {
                        $total_expenses = array_sum(array_column($data, 'count'));
                        echo "إجمالي عدد المصروفات في الفترة المحددة: " . number_format($total_expenses);
                    }
                    ?>
                    <br>
                    الفترة: من <?php echo $start_date; ?> إلى <?php echo $end_date; ?>
                    <br>
                    نوع التقرير: <?php echo $report_type == "revenue" ? "الإيرادات" : "المصروفات"; ?>
                </div>
                <?php endif; ?>
        
        <!-- نموذج الفلترة -->
        <form class="row g-3 mb-4">
            <div class="col-md-3">
                <label class="form-label">من تاريخ</label>
                <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">إلى تاريخ</label>
                <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">نوع التقرير</label>
                <select name="report_type" class="form-select">
                    <option value="revenue" <?php echo $report_type == 'revenue' ? 'selected' : ''; ?>>الإيرادات</option>
                    <option value="expense" <?php echo $report_type == 'expense' ? 'selected' : ''; ?>>المصروفات</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary d-block">عرض التقرير</button>
            </div>
        </form>

        <?php if (empty($data)): ?>
        <div class="alert alert-warning">
            لا توجد بيانات للفترة المحددة
        </div>
        <?php else: ?>
        <!-- عرض الرسم البياني -->
        <div class="card mb-4">
            <div class="card-body">
                <canvas id="financialChart"></canvas>
            </div>
        </div>

        <!-- عرض البيانات في جدول -->
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>الشهر</th>
                        <th>النوع</th>
                        <th>المبلغ</th>
                    </tr>
                </thead>
                <tbody>
                    
                    <?php foreach ($data as $row): ?>
                    <tr>
                        <?php if ($report_type == 'revenue'): ?>
                            <td><?php echo $row['month']; ?></td>
                            <td><?php echo $row['payment_type']; ?></td>
                            <td><?php echo number_format($row['total_amount'], 2); ?> ريال</td>
                        <?php else: ?>
                            <td><?php echo $row['month']; ?></td>
                            <td><?php echo $row['type_name']; ?></td>
                            <td><?php echo number_format($row['total_expense'], 2); ?> ريال</td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    
    <?php if (!empty($data)): ?>
    <script>
    // إعداد الرسم البياني
    const ctx = document.getElementById('financialChart').getContext('2d');
    const data = <?php echo json_encode($data); ?>;
    
    const chartData = {
        labels: data.map(item => item.month),
        datasets: [{
            label: '<?php echo $report_type == "revenue" ? "الإيرادات" : "المصروفات"; ?>',
            data: data.map(item => item.total_amount),
            backgroundColor: '<?php echo $report_type == "revenue" ? "rgba(75, 192, 192, 0.2)" : "rgba(255, 99, 132, 0.2)"; ?>',
            borderColor: '<?php echo $report_type == "revenue" ? "rgba(75, 192, 192, 1)" : "rgba(255, 99, 132, 1)"; ?>',
            borderWidth: 1
        }]
    };

    new Chart(ctx, {
        type: 'bar',
        data: chartData,
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    </script>
    <?php endif; ?>
</body>
</html>
