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
        SELECT DISTINCT
            t.tenant_id,
            t.full_name,
            t.phone,
            t.email,
            t.id_number,
            COUNT(DISTINCT c.contract_id) as active_contracts,
            GROUP_CONCAT(DISTINCT u.unit_name) as rented_units
        FROM tenants t
        INNER JOIN contracts c ON t.tenant_id = c.tenant_id
        INNER JOIN units u ON c.unit_id = u.unit_id
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

?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="container mt-4">
    <h2 class="mb-4">تقرير المستأجرين النشطين</h2>
    
    <?php if (empty($active_tenants)): ?>
        <div class="alert alert-info">
            لا يوجد مستأجرين نشطين حالياً
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>الاسم الكامل</th>
                        <th>رقم الهوية</th>
                        <th>رقم الهاتف</th>
                        <th>البريد الإلكتروني</th>
                        <th>عدد العقود النشطة</th>
                        <th>الوحدات المستأجرة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($active_tenants as $tenant): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($tenant['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($tenant['id_number']); ?></td>
                            <td><?php echo htmlspecialchars($tenant['phone']); ?></td>
                            <td><?php echo htmlspecialchars($tenant['email']); ?></td>
                            <td><?php echo $tenant['active_contracts']; ?></td>
                            <td><?php echo htmlspecialchars($tenant['rented_units']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
