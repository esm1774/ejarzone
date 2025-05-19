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

    $query = "SELECT u.unit_name, m.maintenance_date, m.description, m.status, m.maintenance_id 
              FROM units u 
              INNER JOIN maintenance m ON u.unit_id = m.unit_id";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll();
}

// جلب بيانات صيانة الوحدات
$maintenance_data = getMaintenanceData();
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

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
                                    <th>اسم الوحدة</th>
                                    <th>تاريخ الصيانة</th>
                                    <th>وصف المشكلة</th>
                                    <th>الحالة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($maintenance_data)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">لا توجد بيانات صيانة متاحة</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($maintenance_data as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['unit_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['maintenance_date']); ?></td>
                                            <td><?php echo htmlspecialchars($row['description']); ?></td>
                                            <td><?php echo htmlspecialchars($row['status']); ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="view.php?id=<?php echo $row['maintenance_id']; ?>" class="btn btn-info btn-sm">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="edit.php?id=<?php echo $row['maintenance_id']; ?>" class="btn btn-warning btn-sm">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="delete.php?id=<?php echo $row['maintenance_id']; ?>" class="btn btn-danger btn-sm">
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

<?php include '../includes/footer.php'; ?>
