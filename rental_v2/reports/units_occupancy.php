<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

// التحقق من تسجيل الدخول
checkAuth();

// التحقق من الصلاحيات
checkPermission('view_reports');

// دالة لجلب بيانات إشغال الوحدات
function getOccupancyData() {
    $pdo = getDatabaseConnection();

    $query = "SELECT u.unit_name, t.full_name AS tenant_name, c.start_date, c.end_date 
              FROM units u 
              LEFT JOIN contracts c ON u.unit_id = c.unit_id 
              LEFT JOIN tenants t ON c.tenant_id = t.tenant_id 
              WHERE c.status = 'ساري'";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll();
}

// جلب بيانات إشغال الوحدات
$occupancy_data = getOccupancyData();

// عرض النموذج لتصفية التاريخ
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="container mt-4">
    <h2 class="mb-4">تقرير إشغال الوحدات</h2>

    <!-- كروت الإحصائيات -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">عدد الوحدات المشغولة</h6>
                    <h4 class="mb-0"><?php echo count(array_filter($occupancy_data, function($row) { return !empty($row['tenant_name']); })); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6 class="card-title">عدد الوحدات غير المشغولة</h6>
                    <h4 class="mb-0"><?php echo count(array_filter($occupancy_data, function($row) { return empty($row['tenant_name']); })); ?></h4>
                </div>
            </div>
        </div>
    </div>

    <!-- جدول إشغال الوحدات -->
    <table class="table table-striped">
        <thead>
            <tr>
                <th>اسم الوحدة</th>
                <th>اسم المستأجر</th>
                <th>تاريخ البداية</th>
                <th>تاريخ النهاية</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($occupancy_data as $row): ?>
                <tr>
                    <td><?php echo $row['unit_name']; ?></td>
                    <td><?php echo $row['tenant_name'] ?: 'غير مشغولة'; ?></td>
                    <td><?php echo $row['start_date']; ?></td>
                    <td><?php echo $row['end_date']; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
