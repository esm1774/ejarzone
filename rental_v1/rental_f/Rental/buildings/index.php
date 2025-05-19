<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

// التحقق من صلاحية عرض المباني
if (!hasPermission('view_buildings')) {
    addMessage('error', 'عذراً، ليس لديك صلاحية للوصول إلى هذه الصفحة');
    redirect('../index.php');
}

// إنشاء اتصال بقاعدة البيانات
$database = new Database();
$db = $database->getConnection();

try {
    // استعلام لجلب المباني مع عدد الوحدات المؤجرة وغير المؤجرة
    $query = "SELECT 
        b.*,
        COUNT(u.unit_id) as total_actual_units,
        SUM(CASE WHEN u.status = 'مؤجرة' THEN 1 ELSE 0 END) as rented_units,
        SUM(CASE WHEN u.status = 'شاغرة' THEN 1 ELSE 0 END) as vacant_units
    FROM buildings b
    LEFT JOIN units u ON b.id = u.building_id
    GROUP BY b.id
    ORDER BY b.name";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    addMessage('error', 'حدث خطأ أثناء جلب بيانات المباني: ' . $e->getMessage());
    $buildings = [];
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>
<div class="main-content">
<div class="container-fluid py-4">
    <?php displayMessages(); ?>
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h3 mb-0">إدارة المباني</h2>
    </div>

    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">قائمة المباني</h5>
            <a href="add.php" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> إضافة مبنى جديد
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>اسم المبنى</th>
                            <th>العنوان</th>
                            <th>عدد الطوابق</th>
                            <th>إجمالي الوحدات</th>
                            <th>الوحدات المؤجرة</th>
                            <th>الوحدات الشاغرة</th>
                            <th>المالك</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($buildings as $index => $building): ?>
                        <tr>
                            <td><?php echo $offset + $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($building['name']); ?></td>
                            <td><?php echo htmlspecialchars($building['address']); ?></td>
                            <td><?php echo $building['floors_count']; ?></td>
                            <td><?php echo $building['total_actual_units']; ?></td>
                            <td>
                                <span class="badge bg-success">
                                    <?php echo $building['rented_units']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-warning">
                                    <?php echo $building['vacant_units']; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($building['owner_name']); ?></td>
                            <td>
                                <div class="btn-group">
                                    <a href="view.php?id=<?php echo $building['id']; ?>" class="btn btn-sm btn-info" title="عرض التفاصيل">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="edit.php?id=<?php echo $building['id']; ?>" class="btn btn-sm btn-warning" title="تعديل">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="units.php?building_id=<?php echo $building['id']; ?>" class="btn btn-sm btn-primary" title="الوحدات">
                                        <i class="bi bi-building"></i>
                                    </a>
                                    <a href="dell.php?id=<?php echo $building['id']; ?>" 
                                       class="btn btn-sm btn-danger" 
                                       title="حذف"
                                       onclick="return confirm('هل أنت متأكد من حذف هذا المبنى؟');">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
