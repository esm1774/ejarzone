<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

include_once "../config/database.php";
$database = new Database();
$db = $database->getConnection();

// التحقق من وجود معرف المستأجر
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$tenant_id = $_GET['id'];

// جلب بيانات المستأجر
$query = "SELECT * FROM tenants WHERE tenant_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$tenant_id]);
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tenant) {
    header("Location: index.php");
    exit();
}
?>


<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<!-- المحتوى الرئيسي -->
            <div class="col-md-10 p-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">تفاصيل المستأجر</h5>
                        <div>
                            <a href="edit.php?id=<?php echo $tenant_id; ?>" class="btn btn-warning btn-sm">
                                <i class="bi bi-pencil"></i> تعديل
                            </a>
                            <a href="index.php" class="btn btn-secondary btn-sm">
                                <i class="bi bi-arrow-right"></i> عودة
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-bordered">
                                    <tr>
                                        <th style="width: 30%">الاسم الكامل</th>
                                        <td><?php echo htmlspecialchars($tenant['full_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>الجنسية</th>
                                        <td><?php echo htmlspecialchars($tenant['nationality']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>رقم الهوية</th>
                                        <td><?php echo htmlspecialchars($tenant['id_number']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>رقم الهاتف</th>
                                        <td><?php echo htmlspecialchars($tenant['phone']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>البريد الإلكتروني</th>
                                        <td><?php echo htmlspecialchars($tenant['email']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>الحالة</th>
                                        <td>
                                            <span class="badge <?php echo ($tenant['status'] == 'نشط') ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo htmlspecialchars($tenant['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>تاريخ الإضافة</th>
                                        <td><?php echo date('Y-m-d', strtotime($tenant['created_at'])); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- عرض العقود المرتبطة بالمستأجر -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title">العقود المرتبطة بالمستأجر</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $query = "SELECT c.*, u.unit_name 
                                FROM contracts c 
                                JOIN units u ON c.unit_id = u.unit_id 
                                WHERE c.tenant_id = ? 
                                ORDER BY c.created_at DESC";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$tenant_id]);
                        $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>

                        <?php if ($contracts): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>رقم العقد</th>
                                        <th>الوحدة</th>
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
                                        <td><?php echo htmlspecialchars($contract['unit_name']); ?></td>
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
                        <div class="alert alert-info">لا توجد عقود مرتبطة بهذا المستأجر</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php include '../includes/footer.php'; ?>
