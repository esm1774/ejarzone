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

    $query = "
        SELECT 
            b.name as building_name,
            u.unit_name,
            u.status,
            t.full_name AS tenant_name,
            c.start_date,
            c.end_date,
            c.rent_amount
        FROM units u 
        LEFT JOIN buildings b ON u.building_id = b.id
        LEFT JOIN (
            SELECT * FROM contracts 
            WHERE status = 'ساري'
            AND contract_id IN (
                SELECT MAX(contract_id)
                FROM contracts
                GROUP BY unit_id
            )
        ) c ON u.unit_id = c.unit_id 
        LEFT JOIN tenants t ON c.tenant_id = t.tenant_id 
        ORDER BY b.name, u.unit_name";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll();
}

// جلب بيانات إشغال الوحدات
$occupancy_data = getOccupancyData();

// حساب الإحصائيات
$total_units = count($occupancy_data);
$rented_units = count(array_filter($occupancy_data, function($row) { 
    return $row['status'] === 'مؤجرة';
}));
$vacant_units = count(array_filter($occupancy_data, function($row) { 
    return $row['status'] === 'شاغرة';
}));
$occupancy_rate = $total_units > 0 ? round(($rented_units / $total_units) * 100, 2) : 0;
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">تقرير إشغال الوحدات</h5>
                    </div>
                <div class="card-body">
                    <!-- كروت الإحصائيات -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <h6 class="card-title">إجمالي الوحدات</h6>
                                    <h4 class="mb-0"><?php echo $total_units; ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <h6 class="card-title">الوحدات المؤجرة</h6>
                                    <h4 class="mb-0"><?php echo $rented_units; ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <h6 class="card-title">الوحدات الشاغرة</h6>
                                    <h4 class="mb-0"><?php echo $vacant_units; ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h6 class="card-title">نسبة الإشغال</h6>
                                    <h4 class="mb-0"><?php echo $occupancy_rate; ?>%</h4>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- جدول إشغال الوحدات -->
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>المبنى</th>
                                    <th>الوحدة</th>
                                    <th>الحالة</th>
                                    <th>المستأجر</th>
                                    <th>قيمة الإيجار</th>
                                    <th>تاريخ البداية</th>
                                    <th>تاريخ النهاية</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $counter = 1;
                                foreach ($occupancy_data as $row): 
                                ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td><?php echo htmlspecialchars($row['building_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['unit_name']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $row['status'] === 'مؤجرة' ? 'success' : 'warning'; ?>">
                                                <?php echo htmlspecialchars($row['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $row['tenant_name'] ? htmlspecialchars($row['tenant_name']) : '-'; ?></td>
                                        <td><?php echo $row['rent_amount'] ? number_format($row['rent_amount'], 2) . ' ريال' : '-'; ?></td>
                                        <td><?php echo $row['start_date'] ?: '-'; ?></td>
                                        <td><?php echo $row['end_date'] ?: '-'; ?></td>
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

<?php include '../includes/footer.php'; ?>
