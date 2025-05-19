<?php
session_start();
require_once "../config/config.php";
require_once "../includes/functions.php";

// التحقق من تسجيل الدخول
if (!isLoggedIn()) {
    redirect('../login.php');
    exit();
}

// التحقق من وجود معرف الوحدة
if (!isset($_GET['id'])) {
    addMessage('error', 'معرف الوحدة غير موجود');
    redirect('index.php');
    exit();
}

$unit_id = $_GET['id'];

try {
    $database = new Database();
    $db = $database->getConnection();

    // جلب بيانات الوحدة مع اسم المبنى
    $query = "SELECT u.*, b.name as building_name 
              FROM units u 
              LEFT JOIN buildings b ON u.building_id = b.id 
              WHERE u.unit_id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$unit_id]);
    $unit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$unit) {
        addMessage('error', 'الوحدة غير موجودة');
        redirect('index.php');
        exit();
    }

    // جلب العقد الحالي إن وجد
    $contractQuery = "SELECT c.*, t.full_name as tenant_name 
                     FROM contracts c 
                     LEFT JOIN tenants t ON c.tenant_id = t.tenant_id 
                     WHERE c.unit_id = ? AND c.end_date >= CURRENT_DATE 
                     ORDER BY c.start_date DESC LIMIT 1";
    $contractStmt = $db->prepare($contractQuery);
    $contractStmt->execute([$unit_id]);
    $contract = $contractStmt->fetch(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    addMessage('error', 'حدث خطأ أثناء جلب بيانات الوحدة: ' . $e->getMessage());
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
                    <h5 class="mb-0">تفاصيل الوحدة</h5>
                    <div>
                        <a href="edit.php?id=<?php echo $unit['unit_id']; ?>" class="btn btn-warning">
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
                                    <th>اسم الوحدة</th>
                                    <td><?php echo htmlspecialchars($unit['unit_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>المبنى</th>
                                    <td><?php echo htmlspecialchars($unit['building_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>الطابق</th>
                                    <td><?php echo htmlspecialchars($unit['floor']); ?></td>
                                </tr>
                                <tr>
                                    <th>الحالة</th>
                                    <td>
                                        <span class="badge bg-<?php echo $unit['status'] == 'شاغرة' ? 'success' : 'warning'; ?>">
                                            <?php echo htmlspecialchars($unit['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <?php if ($contract): ?>
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">معلومات العقد الحالي</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th>المستأجر</th>
                                            <td><?php echo htmlspecialchars($contract['tenant_name']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>تاريخ البداية</th>
                                            <td><?php echo date('Y-m-d', strtotime($contract['start_date'])); ?></td>
                                        </tr>
                                        <tr>
                                            <th>تاريخ النهاية</th>
                                            <td><?php echo date('Y-m-d', strtotime($contract['end_date'])); ?></td>
                                        </tr>
                                        <tr>
                                            <th>قيمة الإيجار</th>
                                            <td><?php echo number_format($contract['rent_amount'], 2); ?> ريال</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($unit['description'])): ?>
                    <div class="row mt-4">
                        <div class="col-12">
                            <h6>وصف الوحدة</h6>
                            <p><?php echo nl2br(htmlspecialchars($unit['description'])); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($unit['images'])): ?>
                    <div class="row mt-4">
                        <div class="col-12">
                            <h6>صور الوحدة</h6>
                            <div class="row">
                                <?php
                                $images = explode(',', $unit['images']);
                                foreach ($images as $image):
                                    if (!empty($image)):
                                ?>
                                <div class="col-md-3 mb-3">
                                    <img src="../uploads/units/<?php echo htmlspecialchars($image); ?>" 
                                         class="img-fluid rounded" 
                                         alt="صورة الوحدة">
                                </div>
                                <?php
                                    endif;
                                endforeach;
                                ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
