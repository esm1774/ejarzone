<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// التحقق من تسجيل الدخول
if (!isLoggedIn()) {
    redirect('login.php');
    exit();
}

// تفعيل عرض الأخطاء للتطوير
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// التحقق من وجود معرف المبنى في الرابط
if (!isset($_GET['building_id']) || !is_numeric($_GET['building_id'])) {
    $_SESSION['error'] = 'معرف المبنى غير صالح';
    redirect('buildings/index.php');
    exit();
}

$buildingId = (int)$_GET['building_id'];

// إنشاء اتصال بقاعدة البيانات
$database = new Database();
$db = $database->getConnection();

// التحقق من وجود المبنى
$stmt = $db->prepare("SELECT * FROM buildings WHERE id = ?");
$stmt->execute([$buildingId]);
$building = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$building) {
    $_SESSION['error'] = 'المبنى غير موجود';
    redirect('buildings/index.php');
    exit();
}

try {
    // جلب الوحدات المرتبطة بالمبنى
    $stmt = $db->prepare("
        SELECT 
            u.unit_id as unit_id,
            u.unit_name,
            COALESCE(t.full_name, 'غير مؤجرة') as tenant_name,
            COALESCE(c.rent_amount, NULL) as current_rent,
            COALESCE(c.status, 'شاغرة') as status
        FROM units u
        LEFT JOIN buildings b ON u.building_id = b.id
        LEFT JOIN (
            SELECT c.* 
            FROM contracts c
            INNER JOIN (
                SELECT unit_id, MAX(end_date) as max_end_date
                FROM contracts
                WHERE end_date >= CURRENT_DATE
                GROUP BY unit_id
            ) latest ON c.unit_id = latest.unit_id AND c.end_date = latest.max_end_date
        ) c ON u.unit_id = c.unit_id
        LEFT JOIN tenants t ON c.tenant_id = t.tenant_id
        WHERE u.building_id = ?
        ORDER BY u.unit_name
    ");
    
    if (!$stmt->execute([$buildingId])) {
        throw new PDOException("فشل تنفيذ الاستعلام: " . implode(" ", $stmt->errorInfo()));
    }
    
    $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($units === false) {
        throw new PDOException("فشل جلب البيانات");
    }
    
} catch (PDOException $e) {
    error_log("Database Error in units.php: " . $e->getMessage());
    $_SESSION['error'] = 'حدث خطأ أثناء جلب بيانات الوحدات: ' . $e->getMessage();
    $units = [];
}

// تضمين الهيدر والسايدبار
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<!-- المحتوى الرئيسي -->
 <div class="main-content">
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">الوحدات في <?php echo htmlspecialchars($building['name']); ?></h5>
                    <a href="<?php echo getUrl('units/add.php?building_id=' . $buildingId); ?>" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> إضافة وحدة جديدة
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($units)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            لا توجد وحدات مضافة لهذا المبنى
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>رقم الوحدة</th>
                                        <th>الإيجار الشهري</th>
                                        <th>الحالة</th>
                                        <th>المستأجر</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($units as $unit): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($unit['unit_name']); ?></td>
                                            <td><?php echo $unit['current_rent'] !== NULL ? number_format($unit['current_rent'], 2) . ' ريال' : ''; ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $unit['status'] === 'مؤجرة' ? 'success' : 'warning'; ?>">
                                                    <?php echo $unit['status']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($unit['tenant_name']); ?></td>
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
<script>
// حذف الوحدة
$(document).ready(function() {
    $('.delete-unit').click(function() {
        const unitId = $(this).data('id');
        
        if (confirm('هل أنت متأكد من حذف هذه الوحدة؟')) {
            $.ajax({
                url: '../ajax/delete_unit.php',
                type: 'POST',
                data: { unit_id: unitId },
                success: function(response) {
                    const data = JSON.parse(response);
                    if (data.success) {
                        // إعادة تحميل الصفحة
                        location.reload();
                    } else {
                        alert(data.message || 'حدث خطأ أثناء حذف الوحدة');
                    }
                },
                error: function() {
                    alert('حدث خطأ في الاتصال بالخادم');
                }
            });
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
