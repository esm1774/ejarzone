<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

include_once "../config/database.php";
$database = new Database();
$db = $database->getConnection();

// التحقق من وجود معرف الوحدة
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$unit_id = $_GET['id'];

// جلب بيانات الوحدة
$query = "SELECT * FROM units WHERE unit_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$unit_id]);
$unit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$unit) {
    header("Location: index.php");
    exit();
}
?>

<?php include '../includes/header.php'; ?>

<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">تفاصيل الوحدة</h5>
            <div>
                <a href="edit.php?id=<?php echo $unit_id; ?>" class="btn btn-warning">
                    <i class="bi bi-pencil"></i>
                    تعديل
                </a>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-right"></i>
                    رجوع
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-bordered">
                        <tr>
                            <th class="bg-light" width="30%">اسم الوحدة</th>
                            <td><?php echo htmlspecialchars($unit['unit_name']); ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">المبنى</th>
                            <td><?php echo htmlspecialchars($unit['building']); ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">الطابق</th>
                            <td><?php echo htmlspecialchars($unit['floor']); ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">الحالة</th>
                            <td>
                                <span class="badge <?php echo ($unit['status'] == 'متاح') ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo htmlspecialchars($unit['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th class="bg-light">تاريخ الإضافة</th>
                            <td><?php echo date('Y-m-d', strtotime($unit['created_at'])); ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">الوصف</th>
                            <td><?php echo nl2br(htmlspecialchars($unit['description'])); ?></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <?php if (!empty($unit['images'])): ?>
                    <h5>صور الوحدة</h5>
                    <div class="row">
                        <?php
                        $images = explode(',', $unit['images']);
                        foreach ($images as $image):
                            if (!empty($image)):
                        ?>
                        <div class="col-md-6 mb-3">
                            <img src="../uploads/units/<?php echo $image; ?>" 
                                 class="img-fluid rounded" 
                                 alt="صورة الوحدة"
                                 style="width: 100%; height: 200px; object-fit: cover;">
                        </div>
                        <?php
                            endif;
                        endforeach;
                        ?>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">لا توجد صور متاحة للوحدة</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- عرض العقود المرتبطة بالوحدة -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="card-title">العقود المرتبطة بالوحدة</h5>
        </div>
        <div class="card-body">
            <?php
            $query = "SELECT c.*, t.full_name as tenant_name 
                    FROM contracts c 
                    JOIN tenants t ON c.tenant_id = t.tenant_id 
                    WHERE c.unit_id = ? 
                    ORDER BY c.created_at DESC";
            $stmt = $db->prepare($query);
            $stmt->execute([$unit_id]);
            $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <?php if ($contracts): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>رقم العقد</th>
                            <th>المستأجر</th>
                            <th>تاريخ البداية</th>
                            <th>تاريخ النهاية</th>
                            <th>نوع الإيجار</th>
                            <th>الحالة</th>
                            <th>المبلغ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contracts as $contract): ?>
                        <tr>
                            <td><?php echo $contract['contract_id']; ?></td>
                            <td><?php echo htmlspecialchars($contract['tenant_name']); ?></td>
                            <td><?php echo $contract['start_date']; ?></td>
                            <td><?php echo $contract['end_date']; ?></td>
                            <td><?php echo $contract['rent_type']; ?></td>
                            <td>
                                <span class="badge <?php echo ($contract['status'] == 'ساري') ? 'bg-success' : 'bg-secondary'; ?>">
                                    <?php echo $contract['status']; ?>
                                </span>
                            </td>
                            <td><?php echo number_format($contract['rent_amount'], 2); ?> ريال</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="alert alert-info">لا توجد عقود مرتبطة بهذه الوحدة</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
