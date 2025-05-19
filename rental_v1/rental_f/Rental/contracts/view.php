<?php
session_start();
require_once "../config/config.php";
require_once "../includes/functions.php";

// التحقق من تسجيل الدخول
if (!isLoggedIn()) {
    redirect('../login.php');
    exit();
}

// التحقق من وجود معرف العقد
if (!isset($_GET['id'])) {
    addMessage('error', 'معرف العقد غير موجود');
    redirect('index.php');
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // جلب بيانات العقد مع معلومات الوحدة والمستأجر والمبنى
    $query = "SELECT c.*, u.unit_name, u.floor, t.*, b.name as building_name
              FROM contracts c
              LEFT JOIN units u ON c.unit_id = u.unit_id
              LEFT JOIN tenants t ON c.tenant_id = t.tenant_id
              LEFT JOIN buildings b ON c.building_id = b.id
              WHERE c.contract_id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$_GET['id']]);
    $contract = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$contract) {
        addMessage('error', 'العقد غير موجود');
        redirect('index.php');
        exit();
    }

} catch(PDOException $e) {
    addMessage('error', 'حدث خطأ أثناء جلب بيانات العقد: ' . $e->getMessage());
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
                    <h5 class="mb-0">تفاصيل العقد</h5>
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
                        <div class="col-md-6">
                            <h6 class="mb-3">معلومات العقد</h6>
                            <table class="table table-bordered">
                                <tr>
                                    <th>رقم العقد</th>
                                    <td><?php echo $contract['contract_id']; ?></td>
                                </tr>
                                <tr>
                                    <th>المبنى</th>
                                    <td><?php echo htmlspecialchars($contract['building_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>الوحدة</th>
                                    <td><?php echo htmlspecialchars($contract['unit_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>الطابق</th>
                                    <td><?php echo htmlspecialchars($contract['floor']); ?></td>
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
                                <tr>
                                    <th>الحالة</th>
                                    <td>
                                        <?php
                                        $today = new DateTime();
                                        $end_date = new DateTime($contract['end_date']);
                                        if ($end_date < $today) {
                                            echo '<span class="badge bg-danger">منتهي</span>';
                                        } else {
                                            echo '<span class="badge bg-success">ساري</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="mb-3">معلومات المستأجر</h6>
                            <table class="table table-bordered">
                                <tr>
                                    <th>الاسم الكامل</th>
                                    <td><?php echo htmlspecialchars($contract['full_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>رقم الهوية</th>
                                    <td><?php echo htmlspecialchars($contract['id_number']); ?></td>
                                </tr>
                                <tr>
                                    <th>رقم الجوال</th>
                                    <td><?php echo htmlspecialchars($contract['phone']); ?></td>
                                </tr>
                                <tr>
                                    <th>البريد الإلكتروني</th>
                                    <td><?php echo htmlspecialchars($contract['email']); ?></td>
                                </tr>
                                <tr>
                                    <th>العنوان</th>
                                    <td><?php echo htmlspecialchars($contract['address']); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <?php if (!empty($contract['notes'])): ?>
                    <div class="row mt-4">
                        <div class="col-12">
                            <h6>ملاحظات</h6>
                            <p><?php echo nl2br(htmlspecialchars($contract['notes'])); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
