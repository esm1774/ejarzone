<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/check_permission.php';

// التحقق من تسجيل الدخول
if (!isLoggedIn()) {
    redirect('../login.php');
    exit();
}

// جلب معلومات الترتيب
$sort_column = $_GET['sort'] ?? 'u.unit_name';
$sort_direction = $_GET['direction'] ?? 'ASC';

// التحقق من صحة عمود الترتيب
$allowed_columns = [
    'u.unit_name' => 'اسم الوحدة',
    'b.name' => 'المبنى',
    'u.floor' => 'الطابق',
    'u.status' => 'الحالة',
];

if (!array_key_exists($sort_column, $allowed_columns)) {
    $sort_column = 'u.unit_name';
}

// التحقق من صحة اتجاه الترتيب
$sort_direction = strtoupper($sort_direction) === 'DESC' ? 'DESC' : 'ASC';

// إنشاء اتصال بقاعدة البيانات
$database = new Database();
$db = $database->getConnection();

try {
    // جلب جميع الوحدات مع أسماء المباني
    $query = "SELECT u.*, b.name as building_name 
              FROM units u 
              LEFT JOIN buildings b ON u.building_id = b.id 
              ORDER BY {$sort_column} {$sort_direction}";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    addMessage('error', 'حدث خطأ أثناء جلب بيانات الوحدات: ' . $e->getMessage());
    $units = [];
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>
<div class="main-content">
    <div class="container-fluid py-4">
        <?php displayMessages(); ?>
        
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">قائمة الوحدات</h5>
                        <?php if (hasPermission('add_units')): ?>
                        <a href="add.php" class="btn btn-primary">
                            <i class="bi bi-plus-lg"></i> إضافة وحدة جديدة
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($units)): ?>
                            <div class="alert alert-info">
                                لا توجد وحدات مضافة حالياً
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
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
                                            <th>الإجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $counter = 1;
                                        foreach ($units as $unit): 
                                        ?>
                                            <tr>
                                                <td><?php echo $counter++; ?></td>
                                                <td><?php echo htmlspecialchars($unit['unit_name']); ?></td>
                                                <td><?php echo htmlspecialchars($unit['building_name']); ?></td>
                                                <td><?php echo htmlspecialchars($unit['floor']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $unit['status'] == 'شاغرة' ? 'success' : 'warning'; ?>">
                                                        <?php echo htmlspecialchars($unit['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <?php if (hasPermission('view_units')): ?>
                                                        <a href="view.php?id=<?php echo $unit['unit_id']; ?>" class="btn btn-sm btn-info" title="عرض التفاصيل">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <?php endif; ?>

                                                        <?php if (hasPermission('edit_units')): ?>
                                                        <a href="edit.php?id=<?php echo $unit['unit_id']; ?>" class="btn btn-sm btn-warning" title="تعديل">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <?php endif; ?>

                                                        <?php if (hasPermission('delete_units') && $unit['status'] == 'شاغرة'): ?>
                                                        <a href="delete.php?id=<?php echo $unit['unit_id']; ?>" 
                                                        class="btn btn-sm btn-danger" 
                                                        onclick="return confirm('هل أنت متأكد من حذف هذه الوحدة؟')" 
                                                        title="حذف">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
