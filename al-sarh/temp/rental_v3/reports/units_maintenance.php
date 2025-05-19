<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

// التحقق من تسجيل الدخول
checkAuth();

// التحقق من الصلاحيات
checkPermission('view_reports');

// دالة لجلب بيانات الصيانة
function getMaintenanceData() {
    $pdo = getDatabaseConnection();

    $query = "
        SELECT 
            m.maintenance_id,
            b.name as building_name,
            u.unit_name, 
            m.description, 
            m.status,
            CASE 
                WHEN m.status = 'قيد الانتظار' THEN 'قيد الانتظار'
                WHEN m.status = 'جاري العمل' THEN 'جاري العمل'
                WHEN m.status = 'مكتمل' THEN 'مكتمل'
                WHEN m.status = 'ملغي' THEN 'ملغي'
                ELSE m.status
            END as status_ar
        FROM units u 
        JOIN buildings b ON u.building_id = b.id
        JOIN maintenance m ON u.unit_id = m.unit_id
        ORDER BY m.maintenance_id DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll();
}

// جلب بيانات الصيانة
$maintenance_data = getMaintenanceData();

// حساب عدد الطلبات لكل حالة
$status_counts = [
    'قيد الانتظار' => 0,
    'جاري العمل' => 0,
    'مكتمل' => 0,
    'ملغي' => 0
];

foreach ($maintenance_data as $request) {
    if (isset($status_counts[$request['status']])) {
        $status_counts[$request['status']]++;
    }
}

// حساب إجمالي الطلبات
$total_requests = count($maintenance_data);
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">تقرير طلبات الصيانة</h5>
                    </div>
                <div class="card-body">
                    <!-- بطاقات الإحصائيات -->
                    <div class="row mb-4">
                        <div class="col">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <h6 class="card-title">إجمالي الطلبات</h6>
                                    <h4 class="mb-0"><?php echo $total_requests; ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <h6 class="card-title">قيد الانتظار</h6>
                                    <h4 class="mb-0"><?php echo $status_counts['قيد الانتظار']; ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <h6 class="card-title">جاري العمل</h6>
                                    <h4 class="mb-0"><?php echo $status_counts['جاري العمل']; ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h6 class="card-title">مكتمل</h6>
                                    <h4 class="mb-0"><?php echo $status_counts['مكتمل']; ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="card bg-secondary text-white">
                                <div class="card-body">
                                    <h6 class="card-title">ملغي</h6>
                                    <h4 class="mb-0"><?php echo $status_counts['ملغي']; ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- جدول طلبات الصيانة -->
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>المبنى</th>
                                    <th>الوحدة</th>
                                    <th>الوصف</th>
                                    <th>الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $counter = 1;
                                foreach ($maintenance_data as $request): 
                                    $status_class = '';
                                    switch ($request['status']) {
                                        case 'قيد الانتظار':
                                            $status_class = 'bg-warning';
                                            break;
                                        case 'جاري العمل':
                                            $status_class = 'bg-primary';
                                            break;
                                        case 'مكتمل':
                                            $status_class = 'bg-success';
                                            break;
                                        case 'ملغي':
                                            $status_class = 'bg-secondary';
                                            break;
                                    }
                                ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td><?php echo htmlspecialchars($request['building_name']); ?></td>
                                        <td><?php echo htmlspecialchars($request['unit_name']); ?></td>
                                        <td><?php echo htmlspecialchars($request['description']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $status_class; ?>">
                                                <?php echo htmlspecialchars($request['status_ar']); ?>
                                            </span>
                                        </td>
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
