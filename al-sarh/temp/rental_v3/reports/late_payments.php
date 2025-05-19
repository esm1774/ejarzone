<?php
require_once '../config/config.php';
require_once '../includes/check_permission.php';
require_once '../config/database.php';

// التحقق من تسجيل الدخول
if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('view_reports')) {
    $_SESSION['error'] = "ليس لديك صلاحية لعرض التقارير";
    header('Location: ../index.php');
    exit();
}

// جلب معلومات الترتيب
$sort_column = $_GET['sort'] ?? 'days_late';
$sort_direction = $_GET['direction'] ?? 'DESC';

// التحقق من صحة عمود الترتيب
$allowed_columns = [
    'days_late' => 'عدد أيام التأخير',
    't.full_name' => 'اسم المستأجر',
    'u.unit_name' => 'الوحدة',
    'c.rent_amount' => 'قيمة الإيجار',
    'next_due_date' => 'تاريخ الاستحقاق',
    'late_amount' => 'قيمة المتأخرات'
];

if (!array_key_exists($sort_column, $allowed_columns)) {
    $sort_column = 'days_late';
}

// التحقق من صحة اتجاه الترتيب
$sort_direction = strtoupper($sort_direction) === 'DESC' ? 'DESC' : 'ASC';

// استلام المعايير
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "
        SELECT 
            c.contract_id,
            t.full_name,
            t.phone as tenant_mobile,
            b.name as building_name,
            u.unit_name,
            c.rent_amount,
            c.rent_type,
            COALESCE(p.next_due_date, 
                CASE c.rent_type
                    WHEN 'شهري' THEN DATE_ADD(c.start_date, INTERVAL 1 MONTH)
                    WHEN 'ربع سنوي' THEN DATE_ADD(c.start_date, INTERVAL 3 MONTH)
                    WHEN 'نصف سنوي' THEN DATE_ADD(c.start_date, INTERVAL 6 MONTH)
                    WHEN 'سنوي' THEN DATE_ADD(c.start_date, INTERVAL 1 YEAR)
                    ELSE c.start_date
                END
            ) as next_due_date,
            DATEDIFF(CURDATE(), 
                COALESCE(p.next_due_date, 
                    CASE c.rent_type
                        WHEN 'شهري' THEN DATE_ADD(c.start_date, INTERVAL 1 MONTH)
                        WHEN 'ربع سنوي' THEN DATE_ADD(c.start_date, INTERVAL 3 MONTH)
                        WHEN 'نصف سنوي' THEN DATE_ADD(c.start_date, INTERVAL 6 MONTH)
                        WHEN 'سنوي' THEN DATE_ADD(c.start_date, INTERVAL 1 YEAR)
                        ELSE c.start_date
                    END
                )
            ) as days_late,
            CASE 
                WHEN DATEDIFF(CURDATE(), 
                    COALESCE(p.next_due_date, 
                        CASE c.rent_type
                            WHEN 'شهري' THEN DATE_ADD(c.start_date, INTERVAL 1 MONTH)
                            WHEN 'ربع سنوي' THEN DATE_ADD(c.start_date, INTERVAL 3 MONTH)
                            WHEN 'نصف سنوي' THEN DATE_ADD(c.start_date, INTERVAL 6 MONTH)
                            WHEN 'سنوي' THEN DATE_ADD(c.start_date, INTERVAL 1 YEAR)
                            ELSE c.start_date
                        END
                    )
                ) <= 0 THEN 0
                ELSE ROUND(c.rent_amount * (
                    DATEDIFF(CURDATE(), 
                        COALESCE(p.next_due_date, 
                            CASE c.rent_type
                                WHEN 'شهري' THEN DATE_ADD(c.start_date, INTERVAL 1 MONTH)
                                WHEN 'ربع سنوي' THEN DATE_ADD(c.start_date, INTERVAL 3 MONTH)
                                WHEN 'نصف سنوي' THEN DATE_ADD(c.start_date, INTERVAL 6 MONTH)
                                WHEN 'سنوي' THEN DATE_ADD(c.start_date, INTERVAL 1 YEAR)
                                ELSE c.start_date
                            END
                        )
                    ) / 30.0
                ), 2)
            END as late_amount
        FROM contracts c 
        JOIN tenants t ON c.tenant_id = t.tenant_id 
        JOIN units u ON c.unit_id = u.unit_id 
        JOIN buildings b ON u.building_id = b.id
        LEFT JOIN (
            SELECT contract_id, next_due_date
            FROM payments
            WHERE payment_id IN (
                SELECT MAX(payment_id)
                FROM payments
                GROUP BY contract_id
            )
        ) p ON c.contract_id = p.contract_id
        WHERE c.status = 'ساري'
        AND COALESCE(p.next_due_date, 
            CASE c.rent_type
                WHEN 'شهري' THEN DATE_ADD(c.start_date, INTERVAL 1 MONTH)
                WHEN 'ربع سنوي' THEN DATE_ADD(c.start_date, INTERVAL 3 MONTH)
                WHEN 'نصف سنوي' THEN DATE_ADD(c.start_date, INTERVAL 6 MONTH)
                WHEN 'سنوي' THEN DATE_ADD(c.start_date, INTERVAL 1 YEAR)
                ELSE c.start_date
            END
        ) < CURDATE()
        AND COALESCE(p.next_due_date, 
            CASE c.rent_type
                WHEN 'شهري' THEN DATE_ADD(c.start_date, INTERVAL 1 MONTH)
                WHEN 'ربع سنوي' THEN DATE_ADD(c.start_date, INTERVAL 3 MONTH)
                WHEN 'نصف سنوي' THEN DATE_ADD(c.start_date, INTERVAL 6 MONTH)
                WHEN 'سنوي' THEN DATE_ADD(c.start_date, INTERVAL 1 YEAR)
                ELSE c.start_date
            END
        ) BETWEEN :start_date AND :end_date
        ORDER BY {$sort_column} {$sort_direction}";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    
    $late_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // حساب إجماليات
    $total_late = count($late_payments);
    $total_amount = array_sum(array_column($late_payments, 'rent_amount'));
    $total_late_amount = array_sum(array_column($late_payments, 'late_amount'));

} catch(PDOException $e) {
    $_SESSION['error'] = "حدث خطأ أثناء جلب البيانات: " . $e->getMessage();
    $late_payments = [];
    $total_late = 0;
    $total_amount = 0;
    $total_late_amount = 0;
}
?>

<?php include '../includes/header.php'; ?>
<div class="main-content">
<div class="container-fluid py-4">
    <?php include '../includes/alerts.php'; ?>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">تقرير المدفوعات المتأخرة</h5>
                </div>
                <div class="card-body">
                    <!-- معلومات التقرير -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="alert alert-info">
                                <strong>إجمالي المتأخرات:</strong>
                                <br>
                                عدد العقود: <?php echo number_format($total_late); ?>
                                <br>
                                إجمالي الإيجارات: <?php echo number_format($total_amount, 2); ?> ريال
                                <br>
                                قيمة المتأخرات: <?php echo number_format($total_late_amount, 2); ?> ريال
                            </div>
                        </div>
                        <!-- نموذج الفلترة -->
                        <div class="col-md-8">
                            <form class="row g-3">
                                <div class="col-md-4">
                                    <label for="start_date" class="form-label">من تاريخ</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" 
                                           value="<?php echo htmlspecialchars($start_date); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="end_date" class="form-label">إلى تاريخ</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" 
                                           value="<?php echo htmlspecialchars($end_date); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary d-block w-100">
                                        <i class="bi bi-filter"></i>
                                        تصفية
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- جدول المدفوعات المتأخرة -->
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <?php foreach ($allowed_columns as $column => $title): ?>
                                        <th>
                                            <a href="?sort=<?php echo $column; ?>&direction=<?php echo ($sort_column === $column && $sort_direction === 'ASC') ? 'DESC' : 'ASC'; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" 
                                               class="text-dark text-decoration-none">
                                                <?php echo $title; ?>
                                                <?php if ($sort_column === $column): ?>
                                                    <i class="bi bi-arrow-<?php echo $sort_direction === 'ASC' ? 'up' : 'down'; ?>"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                    <?php endforeach; ?>
                                    <th>نوع الإيجار</th>
                                    <th>معلومات الاتصال</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($late_payments)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center">لا توجد مدفوعات متأخرة</td>
                                    </tr>
                                <?php else: ?>
                                    <?php $counter = 1; ?>
                                    <?php foreach ($late_payments as $payment): ?>
                                        <tr>
                                            <td><?php echo $counter++; ?></td>
                                            <td><?php echo $payment['days_late']; ?> يوم</td>
                                            <td><?php echo htmlspecialchars($payment['full_name']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($payment['building_name']); ?> - 
                                                <?php echo htmlspecialchars($payment['unit_name']); ?>
                                            </td>
                                            <td><?php echo number_format($payment['rent_amount'], 2); ?> ريال</td>
                                            <td><?php echo date('Y/m/d', strtotime($payment['next_due_date'])); ?></td>
                                            <td><?php echo number_format($payment['late_amount'], 2); ?> ريال</td>
                                            <td><?php echo htmlspecialchars($payment['rent_type']); ?></td>
                                            <td>
                                                <a href="tel:<?php echo htmlspecialchars($payment['tenant_mobile']); ?>" 
                                                   class="btn btn-sm btn-info">
                                                    <i class="bi bi-telephone"></i>
                                                    <?php echo htmlspecialchars($payment['tenant_mobile']); ?>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
<?php include '../includes/footer.php'; ?>
