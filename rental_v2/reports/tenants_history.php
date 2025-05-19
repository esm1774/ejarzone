<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

// التحقق من تسجيل الدخول
checkAuth();

// التحقق من الصلاحيات
checkPermission('view_reports');

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
            u.unit_name,
            c.start_date,
            c.end_date,
            c.status,
            CASE 
                WHEN c.status = 'active' THEN 'نشط'
                WHEN c.status = 'expired' THEN 'منتهي'
                WHEN c.status = 'terminated' THEN 'ملغي'
                ELSE c.status
            END as status_ar,
            c.rent_amount
        FROM tenants t
        INNER JOIN contracts c ON t.tenant_id = c.tenant_id
        INNER JOIN units u ON c.unit_id = u.unit_id
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
            'contracts' => []
        ];
    }
    $grouped_history[$tenant_id]['contracts'][] = [
        'unit_name' => $record['unit_name'],
        'start_date' => $record['start_date'],
        'end_date' => $record['end_date'],
        'status' => $record['status_ar'],
        'rent_amount' => $record['rent_amount']
    ];
}
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="container mt-4">
    <h2 class="mb-4">سجل المستأجرين</h2>
    
    <?php if (empty($tenants_history)): ?>
        <div class="alert alert-info">
            لا يوجد سجل للمستأجرين
        </div>
    <?php else: ?>
        <?php foreach ($grouped_history as $tenant_id => $data): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <?php echo htmlspecialchars($data['tenant_info']['full_name']); ?>
                        <small class="text-muted">
                            (<?php echo htmlspecialchars($data['tenant_info']['id_number']); ?>)
                        </small>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>رقم الهاتف:</strong> 
                            <?php echo htmlspecialchars($data['tenant_info']['phone']); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>البريد الإلكتروني:</strong> 
                            <?php echo htmlspecialchars($data['tenant_info']['email']); ?>
                        </div>
                    </div>
                    
                    <h6>سجل العقود:</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>الوحدة</th>
                                    <th>تاريخ البداية</th>
                                    <th>تاريخ النهاية</th>
                                    <th>الحالة</th>
                                    <th>قيمة الإيجار</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['contracts'] as $contract): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($contract['unit_name']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($contract['start_date'])); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($contract['end_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($contract['status']); ?></td>
                                        <td><?php echo number_format($contract['rent_amount'], 2); ?> ريال</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
