<?php
require_once "../config/config.php";
require_once "../includes/check_permission.php";
require_once "../config/database.php";

// التحقق من تسجيل الدخول والصلاحيات
if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

if (!hasPermission('add_revenues')) {
    $_SESSION['error'] = "ليس لديك صلاحية لإضافة إيرادات";
    header("Location: index.php");
    exit();
}

// إنشاء اتصال بقاعدة البيانات
$database = new Database();
$db = $database->getConnection();

// جلب أنواع الإيرادات
$types = $db->query("SELECT * FROM revenue_types ORDER BY type_name");

// جلب طرق الدفع
$payment_methods = $db->query("SELECT * FROM payment_methods WHERE is_active = 1");

$page_title = "إضافة إيراد جديد";
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">إضافة إيراد جديد</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="process_revenue.php" class="needs-validation" novalidate>
                            <input type="hidden" name="action" value="add">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="type_id" class="form-label">نوع الإيراد <span class="text-danger">*</span></label>
                                        <select class="form-control" id="type_id" name="type_id" required>
                                            <option value="">اختر نوع الإيراد</option>
                                            <?php while($type = $types->fetch(PDO::FETCH_ASSOC)): ?>
                                                <option value="<?php echo $type['type_id']; ?>">
                                                    <?php echo htmlspecialchars($type['type_name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                        <div class="invalid-feedback">يرجى اختيار نوع الإيراد</div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="amount" class="form-label">المبلغ <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="amount" name="amount" step="0.01" required>
                                        <div class="invalid-feedback">يرجى إدخال المبلغ</div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="payment_date" class="form-label">تاريخ الدفع <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="payment_date" name="payment_date" required>
                                        <div class="invalid-feedback">يرجى إدخال تاريخ الدفع</div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="method_id" class="form-label">طريقة الدفع <span class="text-danger">*</span></label>
                                        <select class="form-control" id="method_id" name="method_id" required>
                                            <option value="">اختر طريقة الدفع</option>
                                            <?php while ($method = $payment_methods->fetch()): ?>
                                                <option value="<?php echo $method['method_id']; ?>">
                                                    <?php echo htmlspecialchars($method['method_name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                        <div class="invalid-feedback">يرجى اختيار طريقة الدفع</div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="received_by" class="form-label">المُسلم <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="received_by" name="received_by" required>
                                        <div class="invalid-feedback">يرجى إدخال اسم المُسلم</div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="description" class="form-label">الوصف</label>
                                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="text-end mt-3">
                                <a href="index.php" class="btn btn-outline-primary">
                                    <i class="bi bi-arrow-right"></i> عودة للقائمة
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg"></i> حفظ
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include "../includes/footer.php"; ?>
