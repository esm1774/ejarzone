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

// حساب إجمالي الإيرادات والأرباح
$stats['total_revenues'] = $stats['total_rent_payments'] + $stats['total_other_revenues'];
$stats['net_profit'] = $stats['total_revenues'] - $stats['total_expenses'];

// استعلامات البيانات
try {
    // آخر الإيرادات
    $latest_revenues = $db->query("
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
    ");

    // الإيجارات المستحقة
    $expiring_contracts = $db->query("
        SELECT 
            c.*, 
            t.full_name as tenant_name, 
            u.unit_name,
            COALESCE(p.next_due_date, 
                CASE c.rent_type
                    WHEN 'شهري' THEN DATE_ADD(c.start_date, INTERVAL 1 MONTH)
                    WHEN 'ربع سنوي' THEN DATE_ADD(c.start_date, INTERVAL 3 MONTH)
                    WHEN 'نصف سنوي' THEN DATE_ADD(c.start_date, INTERVAL 6 MONTH)
                    WHEN 'سنوي' THEN DATE_ADD(c.start_date, INTERVAL 1 YEAR)
                    ELSE c.start_date
                END
            ) as next_due_date,
            DATEDIFF(
                COALESCE(p.next_due_date, 
                    CASE c.rent_type
                        WHEN 'شهري' THEN DATE_ADD(c.start_date, INTERVAL 1 MONTH)
                        WHEN 'ربع سنوي' THEN DATE_ADD(c.start_date, INTERVAL 3 MONTH)
                        WHEN 'نصف سنوي' THEN DATE_ADD(c.start_date, INTERVAL 6 MONTH)
                        WHEN 'سنوي' THEN DATE_ADD(c.start_date, INTERVAL 1 YEAR)
                        ELSE c.start_date
                    END
                ), 
                CURDATE()
            ) as days_until_due
        FROM contracts c 
        JOIN tenants t ON c.tenant_id = t.tenant_id 
        JOIN units u ON c.unit_id = u.unit_id
        LEFT JOIN (
            SELECT contract_id, next_due_date
            FROM payments
            WHERE payment_id IN (
                SELECT MAX(payment_id)
                FROM payments
                GROUP BY contract_id
            )
        ) p ON c.contract_id = p.contract_id
        WHERE c.status = 'ساري'
        AND (
            p.next_due_date IS NULL 
            OR 
            p.next_due_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        )
        ORDER BY days_until_due ASC
        LIMIT 5
    ");

    // آخر المصروفات
    $expenses = $db->query("
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
    ");

    // الوحدات الشاغرة
    $vacant_units = $db->query("
        SELECT units.*, buildings.name as building_name 
        FROM units 
        LEFT JOIN buildings ON units.building_id = buildings.id
        WHERE unit_id NOT IN (
            SELECT unit_id FROM contracts WHERE status = 'ساري'
        )
        LIMIT 5
    ");
} catch(PDOException $e) {
    $error = "خطأ في قاعدة البيانات: " . htmlspecialchars($e->getMessage());
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
    <link rel="stylesheet" href="assets/css/sidebar.css">
</head>
<body>
    <!-- زر تبديل القائمة الجانبية -->
    <button class="btn btn-primary sidebar-toggle d-md-none">
        <i class="bi bi-list"></i>
    </button>

    <div class="wrapper">
        <!-- القائمة الجانبية -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <h3 class="text-white p-3">لوحة التحكم</h3>
            </div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="buildings/index.php">
                        <i class="bi bi-building"></i>
                        <span>إدارة المباني</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="units/index.php">
                        <i class="bi bi-house-door"></i>
                        <span>إدارة الوحدات</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="maintenance/index.php">
                        <i class="bi bi-tools"></i>
                        <span>إدارة الصيانة</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="tenants/index.php">
                        <i class="bi bi-people"></i>
                        <span>إدارة المستأجرين</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="contracts/index.php">
                        <i class="bi bi-file-text"></i>
                        <span>إدارة العقود</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="payments/index.php">
                        <i class="bi bi-cash-coin"></i>
                        <span>إدارة الإيجارات</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="revenues/index.php">
                        <i class="bi bi-cash-stack"></i>
                        <span>إدارة الإيرادات</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="expenses/index.php">
                        <i class="bi bi-receipt"></i>
                        <span>إدارة المصروفات</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reports/index.php">
                        <i class="bi bi-graph-up"></i>
                        <span>التقارير</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="users/index.php">
                        <i class="bi bi-person-gear"></i>
                        <span>إدارة المستخدمين</span>
                    </a>
                </li>
                <li class="nav-item mt-3">
                    <a class="nav-link" href="logout.php">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>تسجيل الخروج</span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- المحتوى الرئيسي -->
        <main class="main-content1">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- بطاقات الإحصائيات -->
            <div class="row g-4 mb-4">
                <!-- الوحدات -->
                <div class="col-md-3">
                    <div class="card stat-card h-100 shadow-sm border-0" style="background: linear-gradient(45deg, #4e73df 0%, #6f42c1 100%);">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0 text-white">الوحدات</h6>
                                    <h2 class="mt-2 mb-0 text-white"><?php echo $stats['total_units']; ?></h2>
                                    <small class="text-white-50">شاغرة: <?php echo $stats['vacant_units']; ?></small>
                                </div>
                                <i class="bi bi-building fs-1 text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- المستأجرين -->
                <div class="col-md-3">
                    <div class="card stat-card h-100 shadow-sm border-0" style="background: linear-gradient(45deg, #36b9cc 0%, #2c9faf 100%);">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0 text-white">المستأجرين</h6>
                                    <h2 class="mt-2 mb-0 text-white"><?php echo $stats['total_tenants']; ?></h2>
                                    <small class="text-white-50">عقود نشطة: <?php echo $stats['active_contracts']; ?></small>
                                </div>
                                <i class="bi bi-people fs-1 text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- الإيرادات -->
                <div class="col-md-3">
                    <div class="card stat-card h-100 shadow-sm border-0" style="background: linear-gradient(45deg, #1cc88a 0%, #20c997 100%);">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0 text-white">إجمالي الإيرادات</h6>
                                    <h2 class="mt-2 mb-0 text-white"><?php echo number_format($stats['total_revenues'], 2); ?></h2>
                                    <small class="text-white-50">
                                        إيجارات: <?php echo number_format($stats['total_rent_payments'], 2); ?>
                                        <br>
                                        إيرادات أخرى: <?php echo number_format($stats['total_other_revenues'], 2); ?>
                                    </small>
                                </div>
                                <i class="bi bi-currency-dollar fs-1 text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- المصروفات -->
                <div class="col-md-3">
                    <div class="card stat-card h-100 shadow-sm border-0" style="background: linear-gradient(45deg, #e74a3b 0%, #dc3545 100%);">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0 text-white">إجمالي المصروفات</h6>
                                    <h2 class="mt-2 mb-0 text-white"><?php echo number_format($stats['total_expenses'], 2); ?></h2>
                                    <small class="text-white-50">عدد المصروفات: <?php echo $stats['total_expenses_count']; ?></small>
                                </div>
                                <i class="bi bi-cash-stack fs-1 text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- صافي الأرباح -->
                <div class="col-md-3">
                    <div class="card stat-card h-100 shadow-sm border-0" 
                         style="background: linear-gradient(45deg, <?php echo $stats['net_profit'] >= 0 ? '#1cc88a 0%, #20c997' : '#e74a3b 0%, #dc3545'; ?> 100%);">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0 text-white">صافي الأرباح</h6>
                                    <h2 class="mt-2 mb-0 text-white"><?php echo number_format($stats['net_profit'], 2); ?></h2>
                                    <small class="text-white-50">
                                        <?php echo $stats['net_profit'] >= 0 ? 'أرباح' : 'خسائر'; ?>
                                    </small>
                                </div>
                                <i class="bi bi-graph-up fs-1 text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- الجداول -->
            <div class="row g-4">
                <!-- آخر الإيرادات -->
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">آخر الإيرادات</h5>
                            <div>
                                <a href="payments/index.php" class="btn btn-sm btn-primary">الإيجارات</a>
                                <a href="revenues/index.php" class="btn btn-sm btn-primary">الإيرادات الأخرى</a>
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
                                            <td><?php echo number_format($revenue['amount'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($revenue['description']); ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- الإيجارات المستحقة -->
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">الإيجارات المستحقة</h5>
                            <a href="contracts/index.php" class="btn btn-sm btn-primary">عرض الكل</a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>المستأجر</th>
                                            <th>الوحدة</th>
                                            <th>تاريخ الاستحقاق</th>
                                            <th>الحالة</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($contract = $expiring_contracts->fetch()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($contract['tenant_name']); ?></td>
                                            <td><?php echo htmlspecialchars($contract['unit_name']); ?></td>
                                            <td><?php echo date('Y-m-d', strtotime($contract['next_due_date'])); ?></td>
                                            <td>
                                                <?php
                                                $days = $contract['days_until_due'];
                                                if ($days < 0) {
                                                    echo "<span class='badge bg-danger'>متأخر " . abs($days) . " يوم</span>";
                                                } elseif ($days == 0) {
                                                    echo "<span class='badge bg-danger'>يستحق اليوم</span>";
                                                } elseif ($days <= 7) {
                                                    echo "<span class='badge bg-warning'>يستحق خلال " . $days . " يوم</span>";
                                                } else {
                                                    echo "<span class='badge bg-info'>يستحق خلال " . $days . " يوم</span>";
                                                }
                                                ?>
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
                            <a href="expenses/index.php" class="btn btn-sm btn-primary">عرض الكل</a>
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
                                            <td><?php echo number_format($expense['amount'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($expense['description']); ?></td>
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
                            <a href="units/index.php" class="btn btn-sm btn-primary">عرض الكل</a>
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
                                                <strong>المبنى:</strong> <?php echo htmlspecialchars($unit['building_name']); ?>
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
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/sidebar.js"></script>
</body>
</html>
