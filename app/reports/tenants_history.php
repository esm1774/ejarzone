<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

// التحقق من تسجيل الدخول
checkAuth();

// التحقق من الصلاحيات
checkPermission('view_reports');

// التحقق من الصلاحيات
// if (!hasPermission('view_reports')) {
//     addMessage('error', 'ليس لديك صلاحية عرض التقارير');
//     header('Location: index.php');
//     exit;
// }

// دالة لجلب سجل المستأجرين
function getTenantsHistory() {
    $pdo = getDatabaseConnection();
    
    $query = "
        SELECT 
            t.tenant_id,
            t.full_name,
            t.phone,
            t.email,
            t.id_number,
            b.name as building_name,
            u.unit_name,
            c.contract_id,
            c.start_date,
            c.end_date,
            c.status,
            CASE 
                WHEN c.status = 'ساري' THEN 'نشط'
                WHEN c.status = 'منتهي' THEN 'منتهي'
                WHEN c.status = 'ملغي' THEN 'ملغي'
                ELSE c.status
            END as status_ar,
            c.rent_amount,
            COALESCE(p.total_payments, 0) as total_payments,
            COALESCE(p.payments_count, 0) as payments_count,
            COALESCE(p.last_payment_date, '-') as last_payment_date,
            COALESCE(p.next_payment_date, '-') as next_payment_date
        FROM tenants t
        INNER JOIN contracts c ON t.tenant_id = c.tenant_id
        INNER JOIN units u ON c.unit_id = u.unit_id
        INNER JOIN buildings b ON u.building_id = b.id
        LEFT JOIN (
            SELECT 
                contract_id,
                SUM(amount) as total_payments,
                COUNT(*) as payments_count,
                MAX(payment_date) as last_payment_date,
                MAX(next_due_date) as next_payment_date
            FROM payments
            GROUP BY contract_id
        ) p ON c.contract_id = p.contract_id
        ORDER BY t.full_name, c.start_date DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll();
}

// جلب البيانات
$tenants_history = getTenantsHistory();

// تجميع البيانات حسب المستأجر
$grouped_history = [];
foreach ($tenants_history as $record) {
    $tenant_id = $record['tenant_id'];
    if (!isset($grouped_history[$tenant_id])) {
        $grouped_history[$tenant_id] = [
            'tenant_info' => [
                'full_name' => $record['full_name'],
                'phone' => $record['phone'],
                'email' => $record['email'],
                'id_number' => $record['id_number']
            ],
            'contracts' => [],
            'total_payments' => 0,
            'total_contracts' => 0
        ];
    }
    $grouped_history[$tenant_id]['contracts'][] = [
        'building_name' => $record['building_name'],
        'unit_name' => $record['unit_name'],
        'start_date' => $record['start_date'],
        'end_date' => $record['end_date'],
        'status' => $record['status_ar'],
        'rent_amount' => $record['rent_amount'],
        'total_payments' => $record['total_payments'],
        'payments_count' => $record['payments_count'],
        'last_payment_date' => $record['last_payment_date'],
        'next_payment_date' => $record['next_payment_date']
    ];
    $grouped_history[$tenant_id]['total_payments'] += $record['total_payments'];
    $grouped_history[$tenant_id]['total_contracts']++;
}

// حساب الإجماليات
$total_tenants = count($grouped_history);
$total_contracts = array_sum(array_column($grouped_history, 'total_contracts'));
$total_payments = array_sum(array_column($grouped_history, 'total_payments'));
?>

<?php include '../includes/header.php'; ?>
<div class="main-content">
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">سجل المستأجرين</h5>
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
                                    <h6 class="card-title">عدد العقود</h6>
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
                    </div>

                    <!-- سجل المستأجرين -->
                    <?php foreach ($grouped_history as $tenant_id => $data): ?>
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <h5 class="mb-0">
                                            <?php echo htmlspecialchars($data['tenant_info']['full_name']); ?>
                                            <small class="text-muted">
                                                (<?php echo htmlspecialchars($data['tenant_info']['id_number']); ?>)
                                            </small>
                                        </h5>
                                    </div>
                                    <div class="col-md-6 text-md-end">
                                        <a href="tel:<?php echo htmlspecialchars($data['tenant_info']['phone']); ?>" class="btn btn-sm btn-info">
                                            <i class="bi bi-telephone"></i>
                                            <?php echo htmlspecialchars($data['tenant_info']['phone']); ?>
                                        </a>
                                        <?php if ($data['tenant_info']['email']): ?>
                                            <a href="mailto:<?php echo htmlspecialchars($data['tenant_info']['email']); ?>" class="btn btn-sm btn-secondary ms-2">
                                                <i class="bi bi-envelope"></i>
                                                <?php echo htmlspecialchars($data['tenant_info']['email']); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h6 class="card-title">عدد العقود</h6>
                                                <h4 class="mb-0"><?php echo $data['total_contracts']; ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h6 class="card-title">إجمالي المدفوعات</h6>
                                                <h4 class="mb-0"><?php echo number_format($data['total_payments'], 2); ?> ريال</h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>المبنى - الوحدة</th>
                                                <th>تاريخ البداية</th>
                                                <th>تاريخ النهاية</th>
                                                <th>قيمة الإيجار</th>
                                                <th>الحالة</th>
                                                <th>عدد الدفعات</th>
                                                <th>إجمالي المدفوع</th>
                                                <th>آخر دفعة</th>
                                                <th>موعد الدفعة القادمة</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $counter = 1;
                                            foreach ($data['contracts'] as $contract): 
                                            ?>
                                                <tr>
                                                    <td><?php echo $counter++; ?></td>
                                                    <td>
                                                        <?php echo htmlspecialchars($contract['building_name']); ?> - 
                                                        <?php echo htmlspecialchars($contract['unit_name']); ?>
                                                    </td>
                                                    <td><?php echo $contract['start_date']; ?></td>
                                                    <td><?php echo $contract['end_date']; ?></td>
                                                    <td><?php echo number_format($contract['rent_amount'], 2); ?> ريال</td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $contract['status'] === 'نشط' ? 'success' : ($contract['status'] === 'منتهي' ? 'warning' : 'secondary'); ?>">
                                                            <?php echo $contract['status']; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $contract['payments_count']; ?></td>
                                                    <td><?php echo number_format($contract['total_payments'], 2); ?> ريال</td>
                                                    <td><?php echo $contract['last_payment_date']; ?></td>
                                                    <td><?php echo $contract['next_payment_date']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
<?php include '../includes/footer.php'; ?>
