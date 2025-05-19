<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

// التحقق من تسجيل الدخول
checkAuth();

// التحقق من الصلاحيات
checkPermission('view_reports');

// دالة لجلب المستأجرين النشطين
function getActiveTenants() {
    $pdo = getDatabaseConnection();
    
    $query = "
        SELECT 
            t.tenant_id,
            t.full_name,
            t.phone,
            t.email,
            t.id_number,
            COUNT(DISTINCT c.contract_id) as active_contracts,
            GROUP_CONCAT(DISTINCT CONCAT(b.name, ' - ', u.unit_name)) as rented_units,
            COALESCE(SUM(p.amount), 0) as total_payments,
            COALESCE(
                (
                    SELECT SUM(amount)
                    FROM payments
                    WHERE contract_id IN (SELECT contract_id FROM contracts WHERE tenant_id = t.tenant_id)
                    AND payment_date >= DATE_SUB(CURRENT_DATE, INTERVAL 1 YEAR)
                ), 0
            ) as payments_last_year,
            COALESCE(
                (
                    SELECT COUNT(*)
                    FROM payments
                    WHERE contract_id IN (SELECT contract_id FROM contracts WHERE tenant_id = t.tenant_id)
                ), 0
            ) as payments_count
        FROM tenants t
        INNER JOIN contracts c ON t.tenant_id = c.tenant_id
        INNER JOIN units u ON c.unit_id = u.unit_id
        INNER JOIN buildings b ON u.building_id = b.id
        LEFT JOIN payments p ON c.contract_id = p.contract_id
        WHERE c.end_date >= CURRENT_DATE
        AND c.status = 'ساري'
        GROUP BY t.tenant_id
        ORDER BY t.full_name
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll();
}

// جلب البيانات
$active_tenants = getActiveTenants();

// حساب الإجماليات
$total_tenants = count($active_tenants);
$total_contracts = array_sum(array_column($active_tenants, 'active_contracts'));
$total_payments = array_sum(array_column($active_tenants, 'total_payments'));
$total_payments_last_year = array_sum(array_column($active_tenants, 'payments_last_year'));
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="main-content">

    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">تقرير المستأجرين النشطين</h5>
                    </div>
                    <div class="card-body">
                        <!-- بطاقات الإحصائيات -->
                        <div class="row mb-4">
                            <div class="col">
                                <div class="card bg-info text-white">
                                    <div class="card-body">
                                        <h6 class="card-title">عدد المستأجرين</h6>
                                        <h4 class="mb-0"><?php echo $total_tenants; ?></h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="card bg-primary text-white">
                                    <div class="card-body">
                                        <h6 class="card-title">العقود النشطة</h6>
                                        <h4 class="mb-0"><?php echo $total_contracts; ?></h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="card bg-success text-white">
                                    <div class="card-body">
                                        <h6 class="card-title">إجمالي المدفوعات</h6>
                                        <h4 class="mb-0"><?php echo number_format($total_payments, 2); ?> ريال</h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="card bg-warning text-white">
                                    <div class="card-body">
                                        <h6 class="card-title">مدفوعات آخر سنة</h6>
                                        <h4 class="mb-0"><?php echo number_format($total_payments_last_year, 2); ?> ريال</h4>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- جدول المستأجرين -->
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>الاسم الكامل</th>
                                        <th>رقم الهوية</th>
                                        <th>رقم الهاتف</th>
                                        <th>البريد الإلكتروني</th>
                                        <th>العقود النشطة</th>
                                        <th>الوحدات المستأجرة</th>
                                        <th>عدد المدفوعات</th>
                                        <th>إجمالي المدفوعات</th>
                                        <th>مدفوعات آخر سنة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $counter = 1;
                                    foreach ($active_tenants as $tenant): 
                                    ?>
                                        <tr>
                                            <td><?php echo $counter++; ?></td>
                                            <td><?php echo htmlspecialchars($tenant['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($tenant['id_number']); ?></td>
                                            <td>
                                                <a href="tel:<?php echo htmlspecialchars($tenant['phone']); ?>" class="btn btn-sm btn-info">
                                                    <i class="bi bi-telephone"></i>
                                                    <?php echo htmlspecialchars($tenant['phone']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($tenant['email']); ?></td>
                                            <td><?php echo $tenant['active_contracts']; ?></td>
                                            <td><?php echo htmlspecialchars($tenant['rented_units']); ?></td>
                                            <td><?php echo $tenant['payments_count']; ?></td>
                                            <td><?php echo number_format($tenant['total_payments'], 2); ?> ريال</td>
                                            <td><?php echo number_format($tenant['payments_last_year'], 2); ?> ريال</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
