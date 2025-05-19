<?php
require_once '../includes/session.php';
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

// التحقق من تسجيل الدخول
checkAuth();

// التحقق من الصلاحيات
checkPermission('edit_contracts');

$pdo = getDatabaseConnection();

// التحقق من وجود معرف العقد
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    addMessage('error', 'معرف العقد غير صحيح');
    header('Location: index.php');
    exit;
}

$contract_id = $_GET['id'];

try {
    // جلب بيانات العقد الحالي
    $stmt = $pdo->prepare("
        SELECT c.*, 
               u.unit_name,
               u.floor,
               u.building_id,
               b.name as building_name,
               t.full_name as tenant_name
        FROM contracts c
        JOIN units u ON c.unit_id = u.unit_id
        JOIN buildings b ON u.building_id = b.id
        JOIN tenants t ON c.tenant_id = t.tenant_id
        WHERE c.contract_id = ?
    ");
    $stmt->execute([$contract_id]);
    $contract = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$contract) {
        addMessage('error', 'العقد غير موجود');
        header('Location: index.php');
        exit;
    }

    // جلب قائمة الوحدات
    $unitsStmt = $pdo->prepare("
        SELECT u.unit_id, 
               CONCAT(u.unit_name, ' - الطابق ', u.floor) as unit_name
        FROM units u
        WHERE u.status = 'شاغرة' OR u.unit_id = ?
        ORDER BY u.unit_name
    ");
    $unitsStmt->execute([$contract['unit_id']]);
    $units = $unitsStmt->fetchAll();

    // جلب قائمة المستأجرين
    $tenantsStmt = $pdo->query("SELECT tenant_id, full_name FROM tenants ORDER BY full_name");
    $tenants = $tenantsStmt->fetchAll();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $unit_id = $_POST['unit_id'] ?? '';
        $tenant_id = $_POST['tenant_id'] ?? '';
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $rent_amount = $_POST['rent_amount'] ?? '';
        $status = $_POST['status'] ?? '';

        // التحقق من صحة البيانات
        $errors = [];
        if (empty($unit_id)) $errors[] = "يرجى اختيار الوحدة";
        if (empty($tenant_id)) $errors[] = "يرجى اختيار المستأجر";
        if (empty($start_date)) $errors[] = "يرجى تحديد تاريخ بداية العقد";
        if (empty($end_date)) $errors[] = "يرجى تحديد تاريخ نهاية العقد";
        if (empty($rent_amount)) $errors[] = "يرجى تحديد قيمة الإيجار";
        if (empty($status)) $errors[] = "يرجى تحديد حالة العقد";

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE contracts 
                    SET unit_id = ?, tenant_id = ?, start_date = ?, 
                        end_date = ?, rent_amount = ?, status = ?
                    WHERE contract_id = ?
                ");
                
                if ($stmt->execute([
                    $unit_id, $tenant_id, $start_date, 
                    $end_date, $rent_amount, $status, 
                    $contract_id
                ])) {
                    // تحديث حالة الوحدة
                    if ($status === 'نشط') {
                        $updateUnitStmt = $pdo->prepare("UPDATE units SET status = 'مؤجرة' WHERE unit_id = ?");
                        $updateUnitStmt->execute([$unit_id]);
                    } else {
                        $updateUnitStmt = $pdo->prepare("UPDATE units SET status = 'شاغرة' WHERE unit_id = ?");
                        $updateUnitStmt->execute([$unit_id]);
                    }

                    addMessage('success', 'تم تحديث العقد بنجاح');
                    header('Location: index.php?success=edit');
                    exit;
                }
            } catch (PDOException $e) {
                $errors[] = "حدث خطأ أثناء تحديث العقد: " . $e->getMessage();
            }
        }
    }
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
                    <h5 class="mb-0">تعديل العقد</h5>
                </div>
                <div class="col-auto">
                    <a href="index.php" class="btn btn-secondary btn-sm">
                        <i class="bi bi-arrow-right ml-1"></i>
                        عودة للقائمة
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" class="needs-validation" novalidate>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="unit_id" class="form-label">الوحدة</label>
                            <select class="form-select" id="unit_id" name="unit_id" required>
                                <option value="">اختر الوحدة</option>
                                <?php foreach ($units as $unit): ?>
                                    <option value="<?php echo $unit['unit_id']; ?>" 
                                            <?php echo $unit['unit_id'] == $contract['unit_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($unit['unit_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">يرجى اختيار الوحدة</div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="tenant_id" class="form-label">المستأجر</label>
                            <select class="form-select" id="tenant_id" name="tenant_id" required>
                                <option value="">اختر المستأجر</option>
                                <?php foreach ($tenants as $tenant): ?>
                                    <option value="<?php echo $tenant['tenant_id']; ?>" 
                                            <?php echo $tenant['tenant_id'] == $contract['tenant_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tenant['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">يرجى اختيار المستأجر</div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="rent_amount" class="form-label">قيمة الإيجار</label>
                            <input type="number" class="form-control" id="rent_amount" name="rent_amount" 
                                   value="<?php echo htmlspecialchars($contract['rent_amount']); ?>" required>
                            <div class="invalid-feedback">يرجى تحديد قيمة الإيجار</div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="start_date" class="form-label">تاريخ بداية العقد</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                   value="<?php echo htmlspecialchars($contract['start_date']); ?>" required>
                            <div class="invalid-feedback">يرجى تحديد تاريخ بداية العقد</div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="end_date" class="form-label">تاريخ نهاية العقد</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                   value="<?php echo htmlspecialchars($contract['end_date']); ?>" required>
                            <div class="invalid-feedback">يرجى تحديد تاريخ نهاية العقد</div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="status" class="form-label">حالة العقد</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="">اختر الحالة</option>
                                <option value="نشط" <?php echo $contract['status'] === 'نشط' ? 'selected' : ''; ?>>نشط</option>
                                <option value="منتهي" <?php echo $contract['status'] === 'منتهي' ? 'selected' : ''; ?>>منتهي</option>
                                <option value="ملغي" <?php echo $contract['status'] === 'ملغي' ? 'selected' : ''; ?>>ملغي</option>
                            </select>
                            <div class="invalid-feedback">يرجى تحديد حالة العقد</div>
                        </div>
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
