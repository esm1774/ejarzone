<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/check_permission.php';
require_once dirname(__DIR__) . '/config/database.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

// إنشاء اتصال قاعدة البيانات
$database = new Database();
$db = $database->getConnection();

$search_term = $_GET['search'] ?? '';
$contracts = [];

if ($search_term) {
    try {
        $query = "SELECT 
                    c.contract_id,
                    t.full_name as tenant_name,
                    t.phone as tenant_mobile,
                    b.name as building_name,
                    u.unit_name,
                    u.floor,
                    c.start_date,
                    c.end_date,
                    c.rent_amount,
                    c.rent_type
                FROM contracts c
                JOIN tenants t ON c.tenant_id = t.tenant_id
                JOIN units u ON c.unit_id = u.unit_id
                JOIN buildings b ON u.building_id = b.id
                WHERE (t.full_name LIKE :search_name OR t.phone LIKE :search_phone)
                AND c.status = 'ساري'
                ORDER BY t.full_name ASC";
        
        $stmt = $db->prepare($query);
        $search_param = "%{$search_term}%";
        $stmt->bindParam(':search_name', $search_param);
        $stmt->bindParam(':search_phone', $search_param);
        $stmt->execute();
        $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $_SESSION['error'] = 'حدث خطأ أثناء البحث: ' . $e->getMessage();
    }
}
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">
    <div class="container-fluid py-4">
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']); 
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">البحث عن المستأجر</h5>
            </div>
            <div class="card-body">
                <form method="get" class="mb-4">
                    <div class="row">
                        <div class="col-md-8">
                            <input type="text" name="search" class="form-control" 
                                placeholder="ابحث باسم المستأجر أو رقم الجوال" 
                                value="<?php echo htmlspecialchars($search_term); ?>">
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search"></i>
                                بحث
                            </button>
                        </div>
                    </div>
                </form>

                <?php if ($search_term): ?>
                    <?php if (empty($contracts)): ?>
                        <div class="alert alert-info">
                            لا توجد نتائج للبحث
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>المستأجر</th>
                                        <th>رقم الجوال</th>
                                        <th>المبنى</th>
                                        <th>الوحدة</th>
                                        <th>قيمة الإيجار</th>
                                        <th>نوع الإيجار</th>
                                        <th>تاريخ البداية</th>
                                        <th>تاريخ النهاية</th>
                                        <th>إجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $counter = 1; ?>
                                    <?php foreach ($contracts as $contract): ?>
                                        <tr>
                                            <td><?php echo $counter++; ?></td>
                                            <td><?php echo htmlspecialchars($contract['tenant_name']); ?></td>
                                            <td><?php echo htmlspecialchars($contract['tenant_mobile']); ?></td>
                                            <td><?php echo htmlspecialchars($contract['building_name']); ?></td>
                                            <td><?php echo htmlspecialchars($contract['unit_name']) . ' - الطابق ' . htmlspecialchars($contract['floor']); ?></td>
                                            <td><?php echo number_format($contract['rent_amount'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($contract['rent_type']); ?></td>
                                            <td><?php echo date('Y/m/d', strtotime($contract['start_date'])); ?></td>
                                            <td><?php echo date('Y/m/d', strtotime($contract['end_date'])); ?></td>
                                            <td>
                                                <a href="view_installments.php?contract_id=<?php echo $contract['contract_id']; ?>" 
                                                class="btn btn-sm btn-primary">
                                                    <i class="bi bi-eye"></i>
                                                    عرض الأقساط
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
