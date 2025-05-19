<?php
require_once '../includes/session.php';
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

// التحقق من تسجيل الدخول
checkAuth();

// التحقق من الصلاحيات
checkPermission('view_contracts');

$pdo = getDatabaseConnection();

// استعلام لجلب جميع العقود مع معلومات الوحدة والمستأجر والمبنى
try {
    $query = "
        SELECT c.*, 
               u.unit_name, 
               u.floor,
               t.full_name as tenant_name, 
               b.name as building_name 
        FROM contracts c 
        JOIN units u ON c.unit_id = u.unit_id 
        JOIN tenants t ON c.tenant_id = t.tenant_id 
        JOIN buildings b ON u.building_id = b.id
        ORDER BY c.contract_id DESC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $contracts = $stmt->fetchAll();
} catch (PDOException $e) {
    die("خطأ في قاعدة البيانات: " . $e->getMessage());
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="mb-0">إدارة العقود</h5>
                </div>
                <div class="col-auto">
                    <?php if (hasPermission('add_contracts')): ?>
                    <a href="add.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg ml-1"></i>
                        إضافة عقد جديد
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
                <?php
                switch ($_GET['success']) {
                    case 'add':
                        echo "تم إضافة العقد بنجاح";
                        break;
                    case 'edit':
                        echo "تم تحديث العقد بنجاح";
                        break;
                    case 'delete':
                        echo "تم حذف العقد بنجاح";
                        break;
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>المبنى</th>
                            <th>الوحدة</th>
                            <th>المستأجر</th>
                            <th>تاريخ البداية</th>
                            <th>تاريخ النهاية</th>
                            <th>قيمة الإيجار</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contracts as $contract): ?>
                            <tr>
                                <td><?php echo $contract['contract_id']; ?></td>
                                <td><?php echo htmlspecialchars($contract['building_name']); ?></td>
                                <td><?php echo htmlspecialchars($contract['unit_name']) . ' - الطابق ' . htmlspecialchars($contract['floor']); ?></td>
                                <td><?php echo htmlspecialchars($contract['tenant_name']); ?></td>
                                <td><?php echo htmlspecialchars($contract['start_date']); ?></td>
                                <td><?php echo htmlspecialchars($contract['end_date']); ?></td>
                                <td><?php echo number_format($contract['rent_amount'], 2); ?></td>
                                <td>
                                    <?php
                                    $statusClass = '';
                                    switch($contract['status']) {
                                        case 'نشط':
                                            $statusClass = 'text-success';
                                            break;
                                        case 'منتهي':
                                            $statusClass = 'text-danger';
                                            break;
                                        case 'ملغي':
                                            $statusClass = 'text-secondary';
                                            break;
                                    }
                                    ?>
                                    <span class="<?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars($contract['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <?php if (hasPermission('view_contracts')): ?>
                                        <a href="view.php?id=<?php echo $contract['contract_id']; ?>" 
                                           class="btn btn-info btn-sm" title="عرض التفاصيل">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php endif; ?>

                                        <?php if (hasPermission('edit_contracts')): ?>
                                        <a href="edit.php?id=<?php echo $contract['contract_id']; ?>" 
                                           class="btn btn-warning btn-sm" title="تعديل">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php endif; ?>

                                        <?php if (hasPermission('delete_contracts')): ?>
                                        <a href="#" onclick="confirmDelete(<?php echo $contract['contract_id']; ?>)"
                                           class="btn btn-danger btn-sm" title="حذف">
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
        </div>
    </div>
</div>

<script>
function confirmDelete(id) {
    if (confirm('هل أنت متأكد من حذف هذا العقد؟')) {
        window.location.href = 'delete.php?id=' + id;
    }
}
</script>

<?php include '../includes/footer.php'; ?>