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

// التحقق من وجود معرف العقد
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    addMessage('error', 'معرف العقد غير صحيح');
    header('Location: index.php');
    exit;
}

$contract_id = $_GET['id'];

try {
    // جلب بيانات العقد
    $stmt = $pdo->prepare("
        SELECT c.*, 
               u.unit_name,
               u.floor,
               b.name as building_name,
               t.full_name as tenant_name,
               t.phone as tenant_phone,
               t.email as tenant_email
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
                    <h5 class="mb-0">تفاصيل العقد</h5>
                </div>
                <div class="col-auto">
                    <?php if (hasPermission('edit_contracts')): ?>
                    <a href="edit.php?id=<?php echo $contract_id; ?>" class="btn btn-warning btn-sm">
                        <i class="bi bi-pencil ml-1"></i>
                        تعديل العقد
                    </a>
                    <?php endif; ?>
                    <a href="index.php" class="btn btn-secondary btn-sm">
                        <i class="bi bi-arrow-right ml-1"></i>
                        عودة للقائمة
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-muted mb-3">معلومات العقد</h6>
                    <table class="table table-sm">
                        <tr>
                            <th width="35%">رقم العقد</th>
                            <td><?php echo $contract['contract_id']; ?></td>
                        </tr>
                        <tr>
                            <th>المبنى</th>
                            <td><?php echo htmlspecialchars($contract['building_name']); ?></td>
                        </tr>
                        <tr>
                            <th>الوحدة</th>
                            <td>
                                <?php echo htmlspecialchars($contract['unit_name']); ?> 
                                - الطابق <?php echo htmlspecialchars($contract['floor']); ?>
                            </td>
                        </tr>
                        <tr>
                            <th>قيمة الإيجار</th>
                            <td><?php echo number_format($contract['rent_amount'], 2); ?> ريال</td>
                        </tr>
                        <tr>
                            <th>تاريخ البداية</th>
                            <td><?php echo htmlspecialchars($contract['start_date']); ?></td>
                        </tr>
                        <tr>
                            <th>تاريخ النهاية</th>
                            <td><?php echo htmlspecialchars($contract['end_date']); ?></td>
                        </tr>
                        <tr>
                            <th>الحالة</th>
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
                        </tr>
                    </table>
                </div>

                <div class="col-md-6">
                    <h6 class="text-muted mb-3">معلومات المستأجر</h6>
                    <table class="table table-sm">
                        <tr>
                            <th width="35%">اسم المستأجر</th>
                            <td><?php echo htmlspecialchars($contract['tenant_name']); ?></td>
                        </tr>
                        <tr>
                            <th>رقم الهاتف</th>
                            <td>
                                <a href="tel:<?php echo $contract['tenant_phone']; ?>">
                                    <?php echo htmlspecialchars($contract['tenant_phone']); ?>
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <th>البريد الإلكتروني</th>
                            <td>
                                <a href="mailto:<?php echo $contract['tenant_email']; ?>">
                                    <?php echo htmlspecialchars($contract['tenant_email']); ?>
                                </a>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
