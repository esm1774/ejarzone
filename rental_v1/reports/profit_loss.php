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


// دالة لحساب إجمالي الإيرادات
function getTotalRevenue($start_date, $end_date) {
    $pdo = getDatabaseConnection();
    
    // حساب إجمالي الإيجارات من جدول payments
    $rent_query = "SELECT COALESCE(SUM(amount), 0) as total_rent 
                  FROM payments 
                  WHERE DATE(payment_date) BETWEEN ? AND ?";
    
    $stmt_rent = $pdo->prepare($rent_query);
    $stmt_rent->execute([$start_date, $end_date]);
    $total_rent = $stmt_rent->fetch(PDO::FETCH_ASSOC)['total_rent'];
    
    // حساب إجمالي الإيرادات الأخرى من جدول revenues
    $revenue_query = "SELECT COALESCE(SUM(amount), 0) as total_revenue 
                     FROM revenues 
                     WHERE DATE(payment_date) BETWEEN ? AND ?";
    
    $stmt_revenue = $pdo->prepare($revenue_query);
    $stmt_revenue->execute([$start_date, $end_date]);
    $total_revenue = $stmt_revenue->fetch(PDO::FETCH_ASSOC)['total_revenue'];
    
    return [
        'total_rent' => $total_rent,
        'total_revenue' => $total_revenue,
        'grand_total' => $total_rent + $total_revenue
    ];
}

// دالة لحساب إجمالي المصروفات
function getTotalExpenses($start_date, $end_date) {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare("
        SELECT 
            t.type_name,
            SUM(e.amount) as total_expense,
            COUNT(*) as count
        FROM expenses e
        JOIN expense_types t ON e.type_id = t.type_id
        WHERE DATE(e.expense_date) BETWEEN ? AND ?
        GROUP BY t.type_id, t.type_name
        ORDER BY t.type_name
    ");
    
    // للتأكد من الاستعلام
    error_log("Expense Query Dates: $start_date to $end_date");
    
    $stmt->execute([$start_date, $end_date]);
    $results = $stmt->fetchAll();
    
    // للتأكد من النتائج
    error_log("Expense Results: " . json_encode($results));
    
    return $results;
}

// حساب إجمالي عدد المدفوعات
$total_payments_query = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM payments WHERE payment_date BETWEEN '$start_date' AND '$end_date') +
        (SELECT COUNT(*) FROM revenues WHERE payment_date BETWEEN '$start_date' AND '$end_date') as total_count
")->fetchColumn();

// حساب إجمالي عدد المصروفات
$total_expenses_count = $pdo->query("SELECT COUNT(*) FROM expenses WHERE expense_date BETWEEN '$start_date' AND '$end_date'")->fetchColumn();

$revenues = getTotalRevenue($start_date, $end_date);
$expenses = getTotalExpenses($start_date, $end_date);

// حساب الإجماليات
$total_revenue = $revenues['grand_total'];
$total_expense = array_sum(array_column($expenses, 'total_expense'));
$net_profit = $total_revenue - $total_expense;

// للتأكد من البيانات
error_log("Date Range: $start_date to $end_date");
error_log("Total Payments: $total_payments_query");
error_log("Total Expenses: $total_expenses_count");
error_log("Revenues: " . json_encode($revenues));
error_log("Expenses: " . json_encode($expenses));

?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقرير الأرباح والخسائر - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container mt-4">
        <h2 class="mb-4">تقرير الأرباح والخسائر</h2>

        <!-- معلومات التصحيح -->
        <div class="alert alert-info">
            إجمالي عدد المدفوعات في الفترة المحددة: <?php echo number_format($total_payments_query); ?>
            <br>
            إجمالي عدد المصروفات في الفترة المحددة: <?php echo number_format($total_expenses_count); ?>
            <br>
            إجمالي المدفوعات: <?php echo number_format($total_revenue); ?> ريال
            <br>
            إجمالي المصروفات: <?php echo number_format($total_expense); ?> ريال
            <br>
            صافي الربح: <?php echo number_format($net_profit); ?> ريال
        </div>
        
        <!-- نموذج الفلترة -->
        <form class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label">من تاريخ</label>
                <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">إلى تاريخ</label>
                <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary d-block">عرض التقرير</button>
            </div>
        </form>

        <!-- ملخص التقرير -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">إجمالي الإيرادات</h5>
                        <h3 class="card-text"><?php echo number_format($total_revenue, 2); ?> ريال</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h5 class="card-title">إجمالي المصروفات</h5>
                        <h3 class="card-text"><?php echo number_format($total_expense, 2); ?> ريال</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card <?php echo $net_profit >= 0 ? 'bg-primary' : 'bg-warning'; ?> text-white">
                    <div class="card-body">
                        <h5 class="card-title">صافي الربح</h5>
                        <h3 class="card-text"><?php echo number_format($net_profit, 2); ?> ريال</h3>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($revenues)): ?>
        <!-- تفاصيل الإيرادات -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>تفاصيل الإيرادات</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>نوع الإيراد</th>
                                <th>المبلغ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>الإيجارات</td>
                                <td><?php echo number_format($revenues['total_rent'], 2); ?> ريال</td>
                            </tr>
                            <tr>
                                <td>الإيرادات الأخرى</td>
                                <td><?php echo number_format($revenues['total_revenue'], 2); ?> ريال</td>
                            </tr>
                            <tr>
                                <td>الإجمالي</td>
                                <td><?php echo number_format($revenues['grand_total'], 2); ?> ريال</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($expenses)): ?>
        <!-- تفاصيل المصروفات -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>تفاصيل المصروفات</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>نوع المصروف</th>
                                <th>عدد العمليات</th>
                                <th>المبلغ</th>
                                <th>النسبة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenses as $expense): ?>
                            <tr>
                                <td><?php echo $expense['type_name']; ?></td>
                                <td><?php echo $expense['count']; ?></td>
                                <td><?php echo number_format($expense['total_expense'], 2); ?> ريال</td>
                                <td><?php echo number_format(($expense['total_expense'] / $total_expense) * 100, 1); ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($revenues) || !empty($expenses)): ?>
        <!-- الرسم البياني -->
        <div class="card mb-4">
            <div class="card-body">
                <canvas id="profitLossChart"></canvas>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-warning">
            لا توجد بيانات للفترة المحددة
        </div>
        <?php endif; ?>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    
    <?php if (!empty($revenues) || !empty($expenses)): ?>
    <script>
    // إعداد الرسم البياني
    const ctx = document.getElementById('profitLossChart').getContext('2d');
    const chartData = {
        labels: ['الإيرادات والمصروفات'],
        datasets: [
            {
                label: 'الإيرادات',
                data: [<?php echo $total_revenue; ?>],
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            },
            {
                label: 'المصروفات',
                data: [<?php echo $total_expense; ?>],
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 1
            }
        ]
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
