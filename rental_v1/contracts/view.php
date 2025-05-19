<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

include_once "../config/database.php";
$database = new Database();
$db = $database->getConnection();

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

// جلب بيانات العقد مع معلومات الوحدة والمستأجر
$query = "SELECT c.*, u.unit_name, u.building, u.floor, 
          t.full_name as tenant_name, t.phone, t.email, t.id_number 
          FROM contracts c 
          JOIN units u ON c.unit_id = u.unit_id 
          JOIN tenants t ON c.tenant_id = t.tenant_id 
          WHERE c.contract_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_GET['id']]);
$contract = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contract) {
    header("Location: index.php");
    exit();
}

// حساب الأيام المتبقية حتى موعد الدفع القادم
$today = new DateTime();
$next_payment = new DateTime($contract['next_payment_date']);
$interval = $today->diff($next_payment);
$days_until_payment = $interval->invert ? 0 : $interval->days;
?>


<?php include '../includes/header.php'; ?>

<!-- المحتوى الرئيسي -->
    <div class="container-fluid">
        <div class="row">
            <?php include_once "../includes/sidebar.php"; ?>

            <div class="col-md-10 p-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">تفاصيل العقد رقم <?php echo $contract['contract_id']; ?></h5>
                        <div>
                            <a href="edit.php?id=<?php echo $contract['contract_id']; ?>" class="btn btn-warning">
                                <i class="bi bi-pencil"></i> تعديل
                            </a>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-right"></i> عودة
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- معلومات العقد -->
                            <div class="col-md-6">
                                <h6 class="border-bottom pb-2 mb-3">معلومات العقد</h6>
                                <dl class="row">
                                    <dt class="col-sm-4">حالة العقد</dt>
                                    <dd class="col-sm-8">
                                        <span class="badge <?php echo ($contract['status'] == 'ساري') ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo $contract['status']; ?>
                                        </span>
                                    </dd>

                                    <dt class="col-sm-4">نوع الإيجار</dt>
                                    <dd class="col-sm-8"><?php echo $contract['rent_type']; ?></dd>

                                    <dt class="col-sm-4">مبلغ الإيجار</dt>
                                    <dd class="col-sm-8"><?php echo number_format($contract['rent_amount'], 2); ?> ريال</dd>

                                    <dt class="col-sm-4">تاريخ البداية</dt>
                                    <dd class="col-sm-8"><?php echo $contract['start_date']; ?></dd>

                                    <dt class="col-sm-4">تاريخ الاستحقاق القادم</dt>
                                    <dd class="col-sm-8">
                                        <?php echo $contract['next_payment_date']; ?>
                                        <?php if ($days_until_payment > 0): ?>
                                            <small class="text-muted">(متبقي <?php echo $days_until_payment; ?> يوم)</small>
                                        <?php else: ?>
                                            <small class="text-danger">(متأخر)</small>
                                        <?php endif; ?>
                                    </dd>
                                </dl>
                            </div>

                            <!-- معلومات الوحدة -->
                            <div class="col-md-6">
                                <h6 class="border-bottom pb-2 mb-3">معلومات الوحدة</h6>
                                <dl class="row">
                                    <dt class="col-sm-4">اسم الوحدة</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($contract['unit_name']); ?></dd>

                                    <dt class="col-sm-4">المبنى</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($contract['building']); ?></dd>

                                    <dt class="col-sm-4">الطابق</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($contract['floor']); ?></dd>
                                </dl>

                                <h6 class="border-bottom pb-2 mb-3 mt-4">معلومات المستأجر</h6>
                                <dl class="row">
                                    <dt class="col-sm-4">اسم المستأجر</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($contract['tenant_name']); ?></dd>

                                    <dt class="col-sm-4">رقم الهوية</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($contract['id_number']); ?></dd>

                                    <dt class="col-sm-4">رقم الهاتف</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($contract['phone']); ?></dd>

                                    <dt class="col-sm-4">البريد الإلكتروني</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($contract['email']); ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php include '../includes/footer.php'; ?>
