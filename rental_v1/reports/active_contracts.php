<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

// التحقق من تسجيل الدخول
checkAuth();

// التحقق من الصلاحيات
checkPermission('view_reports');

// الحصول على اتصال قاعدة البيانات
$pdo = getDatabaseConnection();

// استلام المعايير
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// دالة لجلب العقود النشطة
function getActiveContracts($start_date, $end_date) {
    $pdo = getDatabaseConnection();
    
    $query = "
        SELECT 
            c.contract_id,
            t.full_name as tenant_name,
            u.unit_name,
            c.rent_amount,
            c.start_date,
            c.end_date,
            c.next_due_date,
            DATEDIFF(c.end_date, CURDATE()) as remaining_days,
            (
                SELECT COUNT(*) 
                FROM payments p 
                WHERE p.contract_id = c.contract_id
            ) as payments_count,
            (
                SELECT COALESCE(SUM(amount), 0)
                FROM payments p 
                WHERE p.contract_id = c.contract_id
            ) as total_paid
        FROM contracts c 
        JOIN tenants t ON c.tenant_id = t.tenant_id 
        JOIN units u ON c.unit_id = u.unit_id 
        WHERE c.status = 'ساري'
        AND (
            (c.start_date BETWEEN ? AND ?) OR
            (c.end_date BETWEEN ? AND ?) OR
            (c.start_date <= ? AND c.end_date >= ?)
        )
        ORDER BY c.end_date ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$start_date, $end_date, $start_date, $end_date, $start_date, $end_date]);
    return $stmt->fetchAll();
}

// جلب العقود النشطة
$active_contracts = getActiveContracts($start_date, $end_date);

// حساب إجماليات
$total_contracts = count($active_contracts);
$total_rent = array_sum(array_column($active_contracts, 'rent_amount'));
$total_paid = array_sum(array_column($active_contracts, 'total_paid'));
$total_remaining = $total_rent - $total_paid;

?>

    <?php include '../includes/header.php'; ?>
    
    <div class="container mt-4">
        <h2 class="mb-4">تقرير العقود النشطة</h2>
        
        <!-- معلومات التقرير -->
        <div class="alert alert-info">
            إجمالي عدد العقود النشطة: <?php echo number_format($total_contracts); ?>
            <br>
            إجمالي قيمة العقود: <?php echo number_format($total_rent, 2); ?> ريال
            <br>
            إجمالي المدفوعات: <?php echo number_format($total_paid, 2); ?> ريال
            <br>
            إجمالي المتبقي: <?php echo number_format($total_remaining, 2); ?> ريال
        </div>
        
        <!-- نموذج الفلترة -->
        <form class="row g-3 mb-4">
            <div class="col-md-4">
                <label for="start_date" class="form-label">من تاريخ</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
            </div>
            <div class="col-md-4">
                <label for="end_date" class="form-label">إلى تاريخ</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary d-block">تصفية</button>
            </div>
        </form>

        <!-- جدول العقود النشطة -->
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>رقم العقد</th>
                        <th>اسم المستأجر</th>
                        <th>الوحدة</th>
                        <th>قيمة الإيجار</th>
                        <th>تاريخ البداية</th>
                        <th>تاريخ النهاية</th>
                        <th>تاريخ الاستحقاق القادم</th>
                        <th>الأيام المتبقية</th>
                        <th>عدد الدفعات</th>
                        <th>إجمالي المدفوع</th>
                        <th>المتبقي</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($active_contracts as $contract): ?>
                    <tr>
                        <td><?php echo $contract['contract_id']; ?></td>
                        <td><?php echo $contract['tenant_name']; ?></td>
                        <td><?php echo $contract['unit_name']; ?></td>
                        <td><?php echo number_format($contract['rent_amount'], 2); ?> ريال</td>
                        <td><?php echo $contract['start_date']; ?></td>
                        <td><?php echo $contract['end_date']; ?></td>
                        <td><?php echo $contract['next_due_date']; ?></td>
                        <td><?php echo $contract['remaining_days']; ?> يوم</td>
                        <td><?php echo $contract['payments_count']; ?></td>
                        <td><?php echo number_format($contract['total_paid'], 2); ?> ريال</td>
                        <td><?php echo number_format($contract['rent_amount'] - $contract['total_paid'], 2); ?> ريال</td>
                        <td>
                            <a href="../contracts/view.php?id=<?php echo $contract['contract_id']; ?>" class="btn btn-sm btn-primary">عرض العقد</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <?php include '../includes/footer.php'; ?>