<?php
session_start();
require_once "../config/config.php";
require_once "../includes/functions.php";

// التحقق من تسجيل الدخول
if (!isLoggedIn()) {
    redirect('../login.php');
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // جلب قائمة الوحدات الشاغرة
    $query = "SELECT u.*, b.name as building_name 
              FROM units u
              LEFT JOIN buildings b ON u.building_id = b.id
              WHERE u.status = 'شاغرة'
              ORDER BY u.unit_name";
    $stmt_units = $db->prepare($query);
    $stmt_units->execute();

    // جلب قائمة المستأجرين النشطين
    $query = "SELECT * FROM tenants WHERE status = 'نشط' ORDER BY full_name";
    $stmt_tenants = $db->prepare($query);
    $stmt_tenants->execute();

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // التحقق من توفر الوحدة
        $query = "SELECT COUNT(*) FROM contracts 
                  WHERE unit_id = :unit_id 
                  AND status = 'ساري'
                  AND ((start_date BETWEEN :start_date AND :end_date)
                       OR (end_date BETWEEN :start_date AND :end_date)
                       OR (start_date <= :start_date AND end_date >= :end_date))";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":unit_id", $_POST['unit_id']);
        $stmt->bindParam(":start_date", $_POST['start_date']);
        $stmt->bindParam(":end_date", $_POST['end_date']);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("الوحدة مؤجرة في هذه الفترة");
        }

        // إضافة العقد الجديد
        $query = "INSERT INTO contracts (unit_id, tenant_id, start_date, end_date, rent_amount, rent_type, notes) 
                  VALUES (:unit_id, :tenant_id, :start_date, :end_date, :rent_amount, :rent_type, :notes)";
        
        $stmt = $db->prepare($query);
        
        $stmt->bindParam(":unit_id", $_POST['unit_id']);
        $stmt->bindParam(":tenant_id", $_POST['tenant_id']);
        $stmt->bindParam(":start_date", $_POST['start_date']);
        $stmt->bindParam(":end_date", $_POST['end_date']);
        $stmt->bindParam(":rent_amount", $_POST['rent_amount']);
        $stmt->bindParam(":rent_type", $_POST['rent_type']);
        $stmt->bindParam(":notes", $_POST['notes']);
        
        if ($stmt->execute()) {
            // تحديث حالة الوحدة إلى مؤجرة
            $update_unit = "UPDATE units SET status = 'مؤجرة' WHERE unit_id = ?";
            $stmt = $db->prepare($update_unit);
            $stmt->execute([$_POST['unit_id']]);

            addMessage('success', 'تم إضافة العقد بنجاح');
            redirect('index.php');
            exit();
        } else {
            throw new Exception("فشل في إضافة العقد");
        }
    }
} catch (Exception $e) {
    $error = 'خطأ في قاعدة البيانات: ' . $e->getMessage();
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">إضافة عقد جديد</h5>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-right"></i> عودة
                    </a>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="unit_id" class="form-label">الوحدة</label>
                                <select class="form-select" id="unit_id" name="unit_id" required>
                                    <option value="">اختر الوحدة</option>
                                    <?php while ($unit = $stmt_units->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo $unit['unit_id']; ?>">
                                            <?php echo htmlspecialchars($unit['unit_name'] . ' - ' . $unit['building_name'] . ' - الطابق ' . $unit['floor']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="tenant_id" class="form-label">المستأجر</label>
                                <select class="form-select" id="tenant_id" name="tenant_id" required>
                                    <option value="">اختر المستأجر</option>
                                    <?php while ($tenant = $stmt_tenants->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo $tenant['tenant_id']; ?>">
                                            <?php echo htmlspecialchars($tenant['full_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="start_date" class="form-label">تاريخ البداية</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="end_date" class="form-label">تاريخ النهاية</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="rent_amount" class="form-label">قيمة الإيجار</label>
                                <input type="number" step="0.01" class="form-control" id="rent_amount" name="rent_amount" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="rent_type" class="form-label">نوع الإيجار</label>
                                <select class="form-select" id="rent_type" name="rent_type" required>
                                    <option value="">اختر نوع الإيجار</option>
                                    <option value="شهري">شهري</option>
                                    <option value="سنوي">سنوي</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">ملاحظات</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>

                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary">إضافة العقد</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
