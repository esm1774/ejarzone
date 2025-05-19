<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// إنشاء اتصال قاعدة البيانات
$database = new Database();
$db = $database->getConnection();

// الإحصائيات
$stats = [
    'total_units' => $db->query("SELECT COUNT(*) FROM units")->fetchColumn(),
    'vacant_units' => $db->query("SELECT COUNT(*) FROM units WHERE status = 'شاغرة'")->fetchColumn(),
    'total_tenants' => $db->query("SELECT COUNT(*) FROM tenants")->fetchColumn(),
    'active_contracts' => $db->query("SELECT COUNT(*) FROM contracts WHERE status = 'ساري'")->fetchColumn(),
    'total_rent_payments' => $db->query("SELECT COALESCE(SUM(amount), 0) FROM payments")->fetchColumn(),
    'total_other_revenues' => $db->query("SELECT COALESCE(SUM(amount), 0) FROM revenues")->fetchColumn(),
    'total_expenses' => $db->query("SELECT COALESCE(SUM(amount), 0) FROM expenses")->fetchColumn(),
    'total_expenses_count' => $db->query("SELECT COUNT(*) FROM expenses")->fetchColumn(),
];

// حساب إجمالي الإيرادات (الإيجارات + الإيرادات الأخرى)
$stats['total_revenues'] = $stats['total_rent_payments'] + $stats['total_other_revenues'];

// حساب صافي الأرباح (الإيرادات - المصروفات)
$stats['net_profit'] = $stats['total_revenues'] - $stats['total_expenses'];

// آخر الإيرادات (تشمل الإيجارات والإيرادات الأخرى)
$latest_revenues_query = "
    (SELECT 
        'إيجار' COLLATE utf8mb4_general_ci as source,
        p.payment_date as date,
        p.amount,
        CONCAT('إيجار - ', t.full_name, ' - ', u.unit_name) COLLATE utf8mb4_general_ci as description
    FROM 
        payments p
        JOIN contracts c ON p.contract_id = c.contract_id
        JOIN tenants t ON c.tenant_id = t.tenant_id
        JOIN units u ON c.unit_id = u.unit_id
    ORDER BY p.payment_date DESC
    LIMIT 5)
    UNION ALL
    (SELECT 
        rt.type_name COLLATE utf8mb4_general_ci as source,
        r.payment_date as date,
        r.amount,
        COALESCE(r.description, '') COLLATE utf8mb4_general_ci as description
    FROM 
        revenues r
        JOIN revenue_types rt ON r.type_id = rt.type_id
    ORDER BY r.payment_date DESC
    LIMIT 5)
    ORDER BY date DESC
    LIMIT 5
";
$latest_revenues = $db->query($latest_revenues_query);

try {
    // تحديث الإحصائيات
    // $stats['units'] = $db->query("SELECT COUNT(*) as count FROM units")->fetch()['count'];
    // $stats['tenants'] = $db->query("SELECT COUNT(*) as count FROM tenants")->fetch()['count'];
    // $stats['active_contracts'] = $db->query("SELECT COUNT(*) as count FROM contracts WHERE status = 'ساري'")->fetch()['count'];
    // $stats['total_payments'] = $db->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments")->fetch()['total'];
    // $stats['total_expenses'] = $db->query("SELECT COALESCE(SUM(amount), 0) as total FROM expenses")->fetch()['total'];

    // آخر المدفوعات
    // $latest_payments = $db->query("
    //     SELECT p.*, t.full_name as tenant_name, u.unit_name 
    //     FROM payments p 
    //     JOIN contracts c ON p.contract_id = c.contract_id 
    //     JOIN tenants t ON c.tenant_id = t.tenant_id 
    //     JOIN units u ON c.unit_id = u.unit_id 
    //     ORDER BY p.payment_date DESC 
    //     LIMIT 5
    // ");

    // الإيجارات المستحقة والمتأخرة
    $expiring_contracts = $db->query("
        SELECT 
            c.*, 
            t.full_name as tenant_name, 
            u.unit_name,
            DATEDIFF(c.next_due_date, CURDATE()) as days_until_due
        FROM contracts c 
        JOIN tenants t ON c.tenant_id = t.tenant_id 
        JOIN units u ON c.unit_id = u.unit_id 
        WHERE (
            -- الإيجارات المستحقة خلال 5 أيام
            (c.next_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 5 DAY))
            OR
            -- الإيجارات المتأخرة
            (c.next_due_date < CURDATE())
        )
        AND c.status = 'ساري'
        ORDER BY c.next_due_date ASC 
        LIMIT 5
    ");

    // استعلام المصروفات
    $query = "
        SELECT 
            e.expense_id,
            e.amount,
            e.expense_date,
            e.description,
            t.type_name
        FROM 
            expenses e
            JOIN expense_types t ON e.type_id = t.type_id
        ORDER BY 
            e.expense_date DESC,
            e.expense_id DESC
        LIMIT 5
    ";
    $expenses = $db->query($query);

    // الوحدات الشاغرة
    $vacant_units = $db->query("
        SELECT * FROM units 
        WHERE unit_id NOT IN (
            SELECT unit_id FROM contracts WHERE status = 'ساري'
        )
        LIMIT 5
    ");
} catch(PDOException $e) {
    echo "<div class='alert alert-danger'>خطأ في قاعدة البيانات: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الشقق الفندقية</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
        }
        .nav-link {
            color: white;
        }
        .nav-link:hover {
            background-color: #495057;
        }
    </style>
</head>
<body>
<div class="content">

    <div class="container-fluid">
        <div class="row">
            <!-- القائمة الجانبية -->
            <div class="col-md-2 sidebar p-3">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <div class="sidebar-title text-white mb-2">
                            <i class="bi bi-grid-fill me-2"></i>
                            <span>لوحة التحكم</span>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="buildings/index.php">
                            <i class="bi bi-building"></i> إدارة المباني
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="units/index.php">
                            <i class="bi bi-building"></i> إدارة الوحدات
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="maintenance/index.php">
                            <i class="bi bi-tools"></i> إدارة الصيانة
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="tenants/index.php">
                            <i class="bi bi-people"></i> إدارة المستأجرين
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contracts/index.php">
                            <i class="bi bi-file-text"></i> إدارة العقود
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="payments/index.php">
                            <i class="bi bi-cash-coin"></i> إدارة الإيجارات
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="revenues/index.php">
                            <i class="bi bi-cash-stack"></i> إدارة الإيرادات
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="expenses/index.php">
                            <i class="bi bi-cash-stack"></i> إدارة المصروفات
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports/index.php">
                            <i class="bi bi-graph-up"></i> التقارير
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users/index.php">
                            <i class="bi bi-people"></i> إدارة المستخدمين   
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="bi bi-box-arrow-right"></i> تسجيل الخروج
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- المحتوى الرئيسي -->
            <!-- <div class="col-md-10 p-4"> -->
                <div class="container-fluid py-4">
                    <!-- بطاقات الإحصائيات -->
                    <div class="row g-4 mb-4">
                        <div class="col-md-3">
                            <div class="card h-100 shadow-sm border-0" style="background: linear-gradient(45deg, #4e73df 0%, #6f42c1 100%);">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="card-title mb-0 text-white">الوحدات</h6>
                                            <h2 class="mt-2 mb-0 text-white"><?php echo $stats['total_units']; ?></h2>
                                            <small class="text-white-50">
                                                شاغرة: <?php echo $stats['vacant_units']; ?>
                                            </small>
                                        </div>
                                        <i class="bi bi-building fs-1 text-white-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card h-100 shadow-sm border-0" style="background: linear-gradient(45deg, #36b9cc 0%, #2c9faf 100%);">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="card-title mb-0 text-white">المستأجرين</h6>
                                            <h2 class="mt-2 mb-0 text-white"><?php echo $stats['total_tenants']; ?></h2>
                                            <small class="text-white-50">
                                                عقود نشطة: <?php echo $stats['active_contracts']; ?>
                                            </small>
                                        </div>
                                        <i class="bi bi-people fs-1 text-white-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card h-100 shadow-sm border-0" style="background: linear-gradient(45deg, #1cc88a 0%, #20c997 100%);">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="card-title mb-0 text-white">إجمالي الإيرادات</h6>
                                            <h2 class="mt-2 mb-0 text-white"><?php echo number_format($stats['total_revenues'], 2); ?> ريال</h2>
                                            <small class="text-white-50">
                                                إيجارات: <?php echo number_format($stats['total_rent_payments'], 2); ?> ريال
                                                <br>
                                                إيرادات أخرى: <?php echo number_format($stats['total_other_revenues'], 2); ?> ريال
                                            </small>
                                        </div>
                                        <i class="bi bi-currency-dollar fs-1 text-white-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card h-100 shadow-sm border-0" style="background: linear-gradient(45deg, #e74a3b 0%, #dc3545 100%);">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="card-title mb-0 text-white">إجمالي المصروفات</h6>
                                            <h2 class="mt-2 mb-0 text-white"><?php echo number_format($stats['total_expenses'], 2); ?> ريال</h2>
                                            <small class="text-white-50">
                                                عدد المصروفات: <?php echo $stats['total_expenses_count'] ?? 0; ?>
                                            </small>
                                        </div>
                                        <i class="bi bi-cash-stack fs-1 text-white-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card h-100 shadow-sm border-0" style="background: linear-gradient(45deg, <?php echo $stats['net_profit'] >= 0 ? '#1cc88a 0%, #20c997' : '#e74a3b 0%, #dc3545'; ?> 100%);">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="card-title mb-0 text-white">صافي الأرباح</h6>
                                            <h2 class="mt-2 mb-0 text-white"><?php echo number_format($stats['net_profit'], 2); ?> ريال</h2>
                                            <small class="text-white-50">
                                                <?php if ($stats['net_profit'] >= 0): ?>
                                                    أرباح
                                                <?php else: ?>
                                                    خسائر
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <i class="bi bi-graph-up fs-1 text-white-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <!-- آخر الإيرادات -->
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">آخر الإيرادات</h5>
                                    <div>
                                        <a href="payments/index.php" class="btn btn-sm btn-primary">
                                            الإيجارات
                                        </a>
                                        <a href="revenues/index.php" class="btn btn-sm btn-primary">
                                            الإيرادات الأخرى
                                        </a>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>التاريخ</th>
                                                    <th>النوع</th>
                                                    <th>المبلغ</th>
                                                    <th>التفاصيل</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($revenue = $latest_revenues->fetch()): ?>
                                                <tr>
                                                    <td><?php echo date('Y-m-d', strtotime($revenue['date'])); ?></td>
                                                    <td><?php echo htmlspecialchars($revenue['source']); ?></td>
                                                    <td><?php echo number_format($revenue['amount'], 2); ?> ريال</td>
                                                    <td><?php echo htmlspecialchars($revenue['description']) ?: '-'; ?></td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- الإيجارات المستحقة والمتأخرة -->
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">الإيجارات المستحقة والمتأخرة</h5>
                                    <a href="contracts/index.php" class="btn btn-sm btn-primary">
                                        عرض الكل
                                    </a>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>المستأجر</th>
                                                    <th>رقم الوحدة</th>
                                                    <th>تاريخ الاستحقاق</th>
                                                    <th>الحالة</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($contract = $expiring_contracts->fetch(PDO::FETCH_ASSOC)): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($contract['tenant_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($contract['unit_name']); ?></td>
                                                    <td><?php echo date('Y-m-d', strtotime($contract['next_due_date'])); ?></td>
                                                    <td>
                                                        <?php if ($contract['days_until_due'] < 0): ?>
                                                            <span class="badge bg-danger">
                                                                متأخر <?php echo abs($contract['days_until_due']); ?> يوم
                                                            </span>
                                                        <?php elseif ($contract['days_until_due'] == 0  ): ?>
                                                            <span class="badge bg-danger">
                                                                يستحق اليوم 
                                                            </span>
                                                        <?php elseif ($contract['days_until_due'] > 0 && $contract['days_until_due'] <= 2): ?>
                                                            <span class="badge bg-danger">
                                                                يستحق خلال <?php echo $contract['days_until_due']; ?> يوم
                                                            </span>
                                                        <?php elseif ($contract['days_until_due'] > 2 && $contract['days_until_due'] <= 4): ?>
                                                            <span class="badge bg-warning">
                                                                يستحق خلال <?php echo $contract['days_until_due']; ?> يوم
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-info">
                                                                يستحق خلال <?php echo $contract['days_until_due']; ?> يوم
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- آخر المصروفات -->
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">آخر المصروفات</h5>
                                    <a href="expenses/index.php" class="btn btn-sm btn-primary">
                                        عرض الكل
                                    </a>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>التاريخ</th>
                                                    <th>النوع</th>
                                                    <th>المبلغ</th>
                                                    <th>الوصف</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($expense = $expenses->fetch()): ?>
                                                <tr>
                                                    <td><?php echo date('Y-m-d', strtotime($expense['expense_date'])); ?></td>
                                                    <td><?php echo htmlspecialchars($expense['type_name']); ?></td>
                                                    <td><?php echo number_format($expense['amount'], 2); ?> ريال</td>
                                                    <td><?php echo htmlspecialchars($expense['description']) ?: '-'; ?></td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- الوحدات الشاغرة -->
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">الوحدات الشاغرة</h5>
                                    <a href="units/index.php" class="btn btn-sm btn-primary">
                                        عرض الكل
                                    </a>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <?php while ($unit = $vacant_units->fetch()): ?>
                                        <div class="col-md-4">
                                            <div class="card h-100 border-0 shadow-sm">
                                                <div class="card-body">
                                                    <h5 class="card-title">وحدة رقم <?php echo htmlspecialchars($unit['unit_name']); ?></h5>
                                                    <p class="card-text">
                                                        <strong>الدور:</strong> <?php echo htmlspecialchars($unit['floor']); ?><br>
                                                        <strong>المبنى:</strong> <?php echo htmlspecialchars($unit['building']); ?>
                                                    </p>
                                                    <a href="contracts/add.php?unit_id=<?php echo $unit['unit_id']; ?>" class="btn btn-primary">
                                                        إضافة عقد جديد
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endwhile; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <!-- </div> -->
        </div>
    </div>
</div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
