<?php
require_once '../includes/session.php';
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

// التحقق من تسجيل الدخول
checkAuth();

// التحقق من الصلاحيات
checkPermission('view_reports');



// جلب معلومات الترتيب
$sort_column = $_GET['sort'] ?? 'm.maintenance_date';
$sort_direction = $_GET['direction'] ?? 'DESC';

// التحقق من صحة عمود الترتيب
$allowed_columns = [
    'm.maintenance_date' => 'تاريخ الصيانة',
    'b.name' => 'المبنى',
    'u.unit_name' => 'الوحدة',
    'm.description' => 'الوصف',
    'm.status' => 'الحالة'
];

if (!array_key_exists($sort_column, $allowed_columns)) {
    $sort_column = 'm.maintenance_date';
}

// التحقق من صحة اتجاه الترتيب
$sort_direction = strtoupper($sort_direction) === 'DESC' ? 'DESC' : 'ASC';

// دالة لجلب بيانات صيانة الوحدات
function getMaintenanceData() {
    global $sort_column, $sort_direction;
    $pdo = getDatabaseConnection();

    $query = "
        SELECT 
            CONCAT( u.unit_name, ' - الطابق ', u.floor) as unit_full_name,
            m.maintenance_date,
            b.name as building_name,
            m.description,
            m.status,
            m.maintenance_id 
        FROM maintenance m
        JOIN units u ON u.unit_id = m.unit_id
        JOIN buildings b ON u.building_id = b.id
        ORDER BY {$sort_column} {$sort_direction}
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
<div class="main-content">
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="row align-items-center">
                            <div class="col">
                                <h2 class="mb-0">إدارة الصيانة</h2>
                            </div>
                            <div class="col-auto">
                                <a href="add.php" class="btn btn-primary">
                                    <i class="bi bi-plus-lg"></i> إضافة صيانة جديدة
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <?php foreach ($allowed_columns as $column => $title): ?>
                                            <th>
                                                <a href="?sort=<?php echo $column; ?>&direction=<?php echo ($sort_column === $column && $sort_direction === 'ASC') ? 'DESC' : 'ASC'; ?>" 
                                                class="text-dark text-decoration-none">
                                                    <?php echo $title; ?>
                                                    <?php if ($sort_column === $column): ?>
                                                        <i class="bi bi-arrow-<?php echo $sort_direction === 'ASC' ? 'up' : 'down'; ?>"></i>
                                                    <?php endif; ?>
                                                </a>
                                            </th>
                                        <?php endforeach; ?>
                                        <th>إجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($maintenance_data)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center">لا توجد بيانات صيانة متاحة</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($maintenance_data as $index => $maintenance): ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td><?php echo date('Y-m-d', strtotime($maintenance['maintenance_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($maintenance['building_name']); ?></td>
                                                <td><?php echo htmlspecialchars($maintenance['unit_full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($maintenance['description']); ?></td>
                                                <td>
                                                    <?php
                                                    $statusClass = '';
                                                    switch ($maintenance['status']) {
                                                        case 'قيد الانتظار':
                                                            $statusClass = 'warning';
                                                            break;
                                                        case 'جاري العمل':
                                                            $statusClass = 'info';
                                                            break;
                                                        case 'مكتمل':
                                                            $statusClass = 'success';
                                                            break;
                                                        case 'ملغي':
                                                            $statusClass = 'danger';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge bg-<?php echo $statusClass; ?>">
                                                        <?php echo htmlspecialchars($maintenance['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="view.php?id=<?php echo $maintenance['maintenance_id']; ?>" class="btn btn-info btn-sm">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <a href="edit.php?id=<?php echo $maintenance['maintenance_id']; ?>" class="btn btn-warning btn-sm">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <a href="delete.php?id=<?php echo $maintenance['maintenance_id']; ?>" class="btn btn-danger btn-sm">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
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
