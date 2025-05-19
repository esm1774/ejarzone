<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

// التحقق من تسجيل الدخول
checkAuth();

// التحقق من الصلاحيات
checkPermission('view_reports');

// التحقق من البيانات في قاعدة البيانات
$pdo = getDatabaseConnection();

// التحقق من جدول العقود
$contracts_check = $pdo->query("SELECT contract_id, status, start_date, end_date FROM contracts WHERE status != 'ساري'")->fetchAll();
error_log("=== العقود غير السارية ===");
error_log(print_r($contracts_check, true));

// التحقق من جدول سجلات العقود
$logs_check = $pdo->query("SELECT * FROM contract_logs ORDER BY log_date DESC")->fetchAll();
error_log("=== سجلات العقود ===");
error_log(print_r($logs_check, true));

// الحصول على التاريخ الحالي
$current_date = date('Y-m-d');
$default_start_date = date('Y-m-d', strtotime('-1 month'));
$default_end_date = date('Y-m-d', strtotime('+1 month'));

// استلام المعايير من النموذج
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : $default_start_date;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : $default_end_date;
$status = isset($_GET['status']) ? $_GET['status'] : 'all';

// دالة لجلب العقود المنتهية
function getExpiredContracts($start_date, $end_date, $status) {
    $pdo = getDatabaseConnection();
    
    error_log("=== معايير البحث ===");
    error_log("تاريخ البداية: " . $start_date);
    error_log("تاريخ النهاية: " . $end_date);
    error_log("الحالة: " . $status);
    
    // 1. التحقق من العقود غير السارية
    $basic_query = "SELECT c.*, t.full_name, u.unit_name 
                    FROM contracts c
                    JOIN tenants t ON c.tenant_id = t.tenant_id
                    JOIN units u ON c.unit_id = u.unit_id
                    WHERE c.status != 'ساري'";
    $basic_results = $pdo->query($basic_query)->fetchAll(PDO::FETCH_ASSOC);
    error_log("=== العقود غير السارية ===");
    error_log(print_r($basic_results, true));
    
    // 2. التحقق من السجلات
    $logs_query = "SELECT * FROM contract_logs WHERE contract_id IN (SELECT contract_id FROM contracts WHERE status != 'ساري')";
    $logs_results = $pdo->query($logs_query)->fetchAll(PDO::FETCH_ASSOC);
    error_log("=== سجلات العقود ===");
    error_log(print_r($logs_results, true));
    
    // الاستعلام الرئيسي المبسط
    $query = "
        SELECT 
            c.contract_id,
            t.full_name as tenant_name,
            u.unit_name,
            c.rent_amount,
            c.start_date,
            c.end_date,
            c.status,
            cl.log_date as actual_end_date,
            DATEDIFF(COALESCE(cl.log_date, c.end_date), c.start_date) as contract_duration,
            (SELECT COUNT(*) FROM payments p WHERE p.contract_id = c.contract_id) as payments_count,
            (SELECT COALESCE(SUM(amount), 0) FROM payments p WHERE p.contract_id = c.contract_id) as total_paid,
            (SELECT payment_date FROM payments p 
             WHERE p.contract_id = c.contract_id 
             ORDER BY payment_date DESC LIMIT 1) as last_payment_date
        FROM contracts c 
        JOIN tenants t ON c.tenant_id = t.tenant_id 
        JOIN units u ON c.unit_id = u.unit_id 
        LEFT JOIN (
            SELECT contract_id, MAX(log_date) as log_date
            FROM contract_logs
            GROUP BY contract_id
        ) cl ON c.contract_id = cl.contract_id
        WHERE c.status != 'ساري'
        AND (? = 'all' OR c.status = ?)
        AND (
            c.end_date BETWEEN ? AND ?
            OR cl.log_date BETWEEN ? AND ?
        )
        ORDER BY COALESCE(cl.log_date, c.end_date) DESC";
    
    error_log("=== الاستعلام النهائي ===");
    error_log($query);
    
    try {
        $stmt = $pdo->prepare($query);
        $params = [$status, $status, $start_date, $end_date, $start_date, $end_date];
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("=== معاملات الاستعلام ===");
        error_log(print_r($params, true));
        error_log("=== نتائج الاستعلام ===");
        error_log(print_r($results, true));
        
        return $results;
    } catch (PDOException $e) {
        error_log("=== خطأ في الاستعلام ===");
        error_log($e->getMessage());
        return [];
    }
}

// حالات العقود المنتهية
$statuses = [
    'all' => 'الكل',
    'منتهي' => 'منتهي',
    'مفسوخ' => 'مفسوخ',
    'ملغي' => 'ملغي'
];

// جلب العقود المنتهية
$contracts = getExpiredContracts($start_date, $end_date, $status);

// حساب الإجماليات
$total_contracts = count($contracts);
$total_rent = array_sum(array_column($contracts, 'rent_amount'));
$total_paid = array_sum(array_column($contracts, 'total_paid'));
$total_remaining = $total_rent - $total_paid;

?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">تقرير العقود المنتهية</h5>
                        
                        <!-- زر طباعة التقرير -->
                        <button class="btn btn-primary" onclick="window.print()">
                            <i class="fas fa-print"></i> طباعة التقرير
                        </button>
                    </div>
                    <div class="card-body">
                        <!-- نموذج الفلترة -->
                        <form method="get" class="mb-4">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="start_date">من تاريخ</label>
                                        <input type="date" class="form-control" id="start_date" name="start_date" 
                                               value="<?php echo $start_date; ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="end_date">إلى تاريخ</label>
                                        <input type="date" class="form-control" id="end_date" name="end_date" 
                                               value="<?php echo $end_date; ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="status">حالة العقد</label>
                                        <select class="form-control" id="status" name="status">
                                            <?php foreach ($statuses as $key => $value): ?>
                                                <option value="<?php echo $key; ?>" <?php echo $status == $key ? 'selected' : ''; ?>>
                                                    <?php echo $value; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <button type="submit" class="btn btn-primary d-block w-100">تطبيق الفلتر</button>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <?php if (empty($contracts)): ?>
                            <div class="alert alert-info">لا توجد عقود منتهية في الفترة المحددة</div>
                        <?php else: ?>
                            <!-- عرض الإحصائيات -->
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="card bg-primary text-white">
                                        <div class="card-body">
                                            <h6 class="card-title">عدد العقود</h6>
                                            <h4 class="mb-0"><?php echo $total_contracts; ?></h4>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-success text-white">
                                        <div class="card-body">
                                            <h6 class="card-title">إجمالي الإيجارات</h6>
                                            <h4 class="mb-0"><?php echo number_format($total_rent, 2); ?> ريال</h4>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-info text-white">
                                        <div class="card-body">
                                            <h6 class="card-title">إجمالي المدفوع</h6>
                                            <h4 class="mb-0"><?php echo number_format($total_paid, 2); ?> ريال</h4>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-warning text-white">
                                        <div class="card-body">
                                            <h6 class="card-title">إجمالي المتبقي</h6>
                                            <h4 class="mb-0"><?php echo number_format($total_remaining, 2); ?> ريال</h4>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- جدول العقود -->
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead >
                                        <tr>
                                            <th>رقم العقد</th>
                                            <th>المستأجر</th>
                                            <th>الوحدة</th>
                                            <th>قيمة الإيجار</th>
                                            <th>تاريخ البداية</th>
                                            <th>تاريخ النهاية</th>
                                            <th>تاريخ الإنتهاء الفعلي</th>
                                            <th>مدة العقد (يوم)</th>
                                            <th>حالة العقد</th>
                                            <th>عدد الدفعات</th>
                                            <th>إجمالي المدفوع</th>
                                            <th>تاريخ آخر دفعة</th>
                                            <th>المتبقي</th>
                                            <th>الإجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($contracts as $contract): ?>
                                            <tr>
                                                <td><?php echo $contract['contract_id']; ?></td>
                                                <td><?php echo $contract['tenant_name']; ?></td>
                                                <td><?php echo $contract['unit_name']; ?></td>
                                                <td><?php echo number_format($contract['rent_amount'], 2); ?> ريال</td>
                                                <td><?php echo $contract['start_date']; ?></td>
                                                <td><?php echo $contract['end_date']; ?></td>
                                                <td><?php echo $contract['actual_end_date'] ?? 'لا يوجد'; ?></td>
                                                <td><?php echo $contract['contract_duration']; ?></td>
                                                <td><?php echo $contract['status']; ?></td>
                                                <td><?php echo $contract['payments_count']; ?></td>
                                                <td><?php echo number_format($contract['total_paid'], 2); ?> ريال</td>
                                                <td><?php echo $contract['last_payment_date'] ?? 'لا يوجد'; ?></td>
                                                <td><?php echo number_format($contract['rent_amount'] - $contract['total_paid'], 2); ?> ريال</td>
                                                <td>
                                                    <a href="../contracts/view.php?id=<?php echo $contract['contract_id']; ?>" 
                                                       class="btn btn-sm btn-info">
                                                        عرض
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
