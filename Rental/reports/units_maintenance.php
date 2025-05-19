<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

// التحقق من تسجيل الدخول
checkAuth();

// التحقق من الصلاحيات
checkPermission('view_reports');

// دالة لجلب بيانات صيانة الوحدات
function getMaintenanceData() {
    $pdo = getDatabaseConnection();

    $query = "
        SELECT 
            u.unit_name, 
            m.maintenance_date, 
            m.description, 
            m.status,
            CASE 
                WHEN m.status = 'pending' THEN 'قيد الانتظار'
                WHEN m.status = 'in_progress' THEN 'جاري العمل'
                WHEN m.status = 'completed' THEN 'مكتمل'
                ELSE m.status
            END as status_ar
        FROM units u 
        INNER JOIN maintenance m ON u.unit_id = m.unit_id
        WHERE m.maintenance_id IS NOT NULL
        ORDER BY m.maintenance_date DESC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll();
}

// جلب بيانات صيانة الوحدات
$maintenance_data = getMaintenanceData();
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="container mt-4">
    <h2 class="mb-4">تقرير صيانة الوحدات</h2>

    <?php if (empty($maintenance_data)): ?>
        <div class="alert alert-info">
            لا توجد سجلات صيانة حالياً
        </div>
    <?php else: ?>
        <!-- جدول صيانة الوحدات -->
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>اسم الوحدة</th>
                        <th>تاريخ الصيانة</th>
                        <th>وصف المشكلة</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($maintenance_data as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['unit_name']); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($row['maintenance_date'])); ?></td>
                            <td><?php echo htmlspecialchars($row['description']); ?></td>
                            <td><?php echo htmlspecialchars($row['status_ar']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
