<?php
session_start();
require_once "../config/config.php";
require_once "../includes/functions.php";

// التحقق من تسجيل الدخول
if (!isLoggedIn()) {
    redirect('../login.php');
    exit();
}

// التحقق من وجود معرف المبنى
if (!isset($_GET['id'])) {
    addMessage('error', 'معرف المبنى غير موجود');
    redirect('index.php');
    exit();
}

$building_id = $_GET['id'];

try {
    $database = new Database();
    $db = $database->getConnection();

    // جلب بيانات المبنى مع إحصائيات الوحدات
    $query = "SELECT 
        b.*,
        COUNT(u.unit_id) as total_units,
        SUM(CASE WHEN u.status = 'مؤجرة' THEN 1 ELSE 0 END) as rented_units,
        SUM(CASE WHEN u.status = 'شاغرة' THEN 1 ELSE 0 END) as vacant_units,
        SUM(CASE WHEN c.contract_id IS NOT NULL AND c.end_date >= CURRENT_DATE THEN c.rent_amount ELSE 0 END) as total_rent
    FROM buildings b
    LEFT JOIN units u ON b.id = u.building_id
    LEFT JOIN contracts c ON u.unit_id = c.unit_id AND c.end_date >= CURRENT_DATE
    WHERE b.id = ?
    GROUP BY b.id";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$building_id]);
    $building = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$building) {
        addMessage('error', 'المبنى غير موجود');
        redirect('index.php');
        exit();
    }

    // جلب الوحدات المرتبطة بالمبنى
    $unitsQuery = "SELECT u.*, 
                          COALESCE(c.tenant_id, 0) as has_contract,
                          t.full_name as tenant_name,
                          c.start_date,
                          c.end_date,
                          c.rent_amount
                   FROM units u
                   LEFT JOIN contracts c ON u.unit_id = c.unit_id AND c.end_date >= CURRENT_DATE
                   LEFT JOIN tenants t ON c.tenant_id = t.tenant_id
                   WHERE u.building_id = ?
                   ORDER BY u.unit_name";
    $unitsStmt = $db->prepare($unitsQuery);
    $unitsStmt->execute([$building_id]);
    $units = $unitsStmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    addMessage('error', 'حدث خطأ أثناء جلب بيانات المبنى: ' . $e->getMessage());
    redirect('index.php');
    exit();
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="container-fluid py-4">
    <?php displayMessages(); ?>
    
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">تفاصيل المبنى</h5>
                    <div>
                        <a href="edit.php?id=<?php echo $building['id']; ?>" class="btn btn-warning">
                            <i class="bi bi-pencil"></i> تعديل
                        </a>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-right"></i> عودة
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tr>
                                    <th>اسم المبنى</th>
                                    <td><?php echo htmlspecialchars($building['name']); ?></td>
                                </tr>
                                <tr>
                                    <th>العنوان</th>
                                    <td><?php echo htmlspecialchars($building['address']); ?></td>
                                </tr>
                                <tr>
                                    <th>عدد الطوابق</th>
                                    <td><?php echo $building['floors_count']; ?></td>
                                </tr>
                                <tr>
                                    <th>عدد الوحدات</th>
                                    <td><?php echo $building['total_units']; ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card bg-success text-white">
                                        <div class="card-body">
                                            <h6 class="card-title">الوحدات الشاغرة</h6>
                                            <h2 class="mb-0"><?php echo $building['vacant_units']; ?></h2>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card bg-warning text-white">
                                        <div class="card-body">
                                            <h6 class="card-title">الوحدات المؤجرة</h6>
                                            <h2 class="mb-0"><?php echo $building['rented_units']; ?></h2>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 mt-3">
                                    <div class="card bg-info text-white">
                                        <div class="card-body">
                                            <h6 class="card-title">إجمالي الإيجارات الشهرية</h6>
                                            <h2 class="mb-0"><?php echo number_format($building['total_rent'], 2); ?> ريال</h2>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- قائمة الوحدات -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <h5 class="mb-3">الوحدات في المبنى</h5>
                            <?php if (empty($units)): ?>
                                <div class="alert alert-info">لا توجد وحدات مضافة لهذا المبنى</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>اسم الوحدة</th>
                                                <th>الطابق</th>
                                                <th>الحالة</th>
                                                <th>المستأجر</th>
                                                <th>تاريخ بداية العقد</th>
                                                <th>تاريخ نهاية العقد</th>
                                                <th>قيمة الإيجار</th>
                                                <th>الإجراءات</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($units as $index => $unit): ?>
                                                <tr>
                                                    <td><?php echo $index + 1; ?></td>
                                                    <td><?php echo htmlspecialchars($unit['unit_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($unit['floor']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $unit['status'] == 'شاغرة' ? 'success' : 'warning'; ?>">
                                                            <?php echo htmlspecialchars($unit['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $unit['has_contract'] ? htmlspecialchars($unit['tenant_name']) : '-'; ?></td>
                                                    <td><?php echo $unit['has_contract'] ? date('Y-m-d', strtotime($unit['start_date'])) : '-'; ?></td>
                                                    <td><?php echo $unit['has_contract'] ? date('Y-m-d', strtotime($unit['end_date'])) : '-'; ?></td>
                                                    <td><?php echo $unit['has_contract'] ? number_format($unit['rent_amount'], 2) . ' ريال' : '-'; ?></td>
                                                    <td>
                                                        <div class="btn-group">
                                                            <a href="../units/view.php?id=<?php echo $unit['unit_id']; ?>" 
                                                               class="btn btn-sm btn-info" 
                                                               title="عرض التفاصيل">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                            <a href="../units/edit.php?id=<?php echo $unit['unit_id']; ?>" 
                                                               class="btn btn-sm btn-warning" 
                                                               title="تعديل">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                            <?php if ($unit['status'] == 'شاغرة'): ?>
                                                                <a href="../contracts/add.php?unit_id=<?php echo $unit['unit_id']; ?>" 
                                                                   class="btn btn-sm btn-success" 
                                                                   title="إضافة عقد">
                                                                    <i class="bi bi-plus-lg"></i>
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
</div>

<?php include '../includes/footer.php'; ?>
