<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once "../config/config.php";
require_once "../includes/check_permission.php";
require_once "../config/database.php";

$database = new Database();
$db = $database->getConnection();

// استعلام لجلب جميع العقود مع معلومات الوحدة والمستأجر والمبنى
$query = "SELECT c.*, u.unit_name, t.full_name as tenant_name, b.name as building_name 
          FROM contracts c 
          JOIN units u ON c.unit_id = u.unit_id 
          JOIN tenants t ON c.tenant_id = t.tenant_id 
          JOIN buildings b ON c.building_id = b.id
          ORDER BY c.contract_id DESC";
$stmt = $db->prepare($query);
$stmt->execute();
?>


<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<!-- المحتوى الرئيسي -->
<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
                    <h2>إدارة العقود</h2>
                    <a href="add.php" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> إضافة عقد جديد
                    </a>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php
                        switch ($_GET['success']) {
                            case 1:
                                echo "تم إضافة العقد بنجاح";
                                break;
                            case 2:
                                echo "تم تحديث العقد بنجاح";
                                break;
                            case 3:
                                echo "تم حذف العقد بنجاح";
                                break;
                        }
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php
                        switch ($_GET['error']) {
                            case 1:
                                echo "الوحدة مؤجرة في هذه الفترة";
                                break;
                            case 2:
                                echo "حدث خطأ أثناء معالجة العقد";
                                break;
                        }
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- جدول العقود -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المبنى</th>
                                <th>الوحدة</th>
                                <th>المستأجر</th>
                                <!-- <th>نوع الإيجار</th>
                                <th>تاريخ البداية</th> -->
                                <th>تاريخ الاستحقاق</th>
                                <th>المبلغ</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): 
                                // حساب الأيام المتبقية حتى موعد الدفع القادم
                                $today = new DateTime();
                                $next_payment = new DateTime($row['next_payment_date']);
                                $interval = $today->diff($next_payment);
                                $days_until_payment = $interval->invert ? -$interval->days : $interval->days;
                            ?>
                            <tr>
                                <td><?php echo $row['contract_id']; ?></td>
                                <td><?php echo htmlspecialchars($row['building_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['unit_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['tenant_name']); ?></td>
                                <!-- <td><?php echo $row['rent_type']; ?></td>
                                <td><?php echo $row['start_date']; ?></td> -->
                                <td>
                                    <?php echo $row['next_payment_date']; ?>
                                    <?php if ($row['status'] == 'ساري'): ?>
                                        <?php if ($days_until_payment > 0): ?>
                                            <small class="text-muted">(متبقي <?php echo $days_until_payment; ?> يوم)</small>
                                        <?php else: ?>
                                            <small class="text-danger">(متأخر <?php echo abs($days_until_payment); ?> يوم)</small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($row['rent_amount'], 2); ?> ريال</td>
                                <td>
                                    <span class="badge <?php echo ($row['status'] == 'ساري') ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo $row['status']; ?>
                                    </span>
                                </td>
                                <td>
                                <div class='btn-group' role='group'>
                                    <?php if (hasPermission('view_contracts')): ?>
                                        <a href="view.php?id=<?php echo $row['contract_id']; ?>" class="btn btn-sm btn-info" title="عرض">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (hasPermission('edit_contracts')): ?>
                                        <a href="edit.php?id=<?php echo $row['contract_id']; ?>" class="btn btn-sm btn-primary" title="تعديل">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (hasPermission('delete_contracts')): ?>
                                        <button type="button" class="btn btn-sm btn-danger" title="حذف" 
                                               onclick="confirmDelete(<?php echo $row['contract_id']; ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<?php include '../includes/footer.php'; ?>