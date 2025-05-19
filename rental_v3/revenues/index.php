<?php
require_once "../config/config.php";
require_once "../includes/check_permission.php";
require_once "../config/database.php";

$database = new Database();
$db = $database->getConnection();

// جلب معلومات الترتيب
$sort_column = $_GET['sort'] ?? 'r.payment_date';
$sort_direction = $_GET['direction'] ?? 'DESC';

// التحقق من صحة عمود الترتيب
$allowed_columns = [
    'r.payment_date' => 'التاريخ',
    'r.amount' => 'المبلغ',
    'r.description' => 'الوصف',
    'r.received_by' => 'المُسلم',
    'r.receipt_number' => 'رقم الإيصال',
    'pm.method_name' => 'طريقة الدفع',
    'rt.type_name' => 'النوع'
];

if (!array_key_exists($sort_column, $allowed_columns)) {
    $sort_column = 'r.payment_date';
}

// التحقق من صحة اتجاه الترتيب
$sort_direction = strtoupper($sort_direction) === 'DESC' ? 'DESC' : 'ASC';

// استعلام لجلب الإيرادات مع أنواعها
$query = "SELECT r.revenue_id, r.type_id, r.amount, r.payment_date, r.receipt_number, r.received_by, pm.method_name as payment_type, r.description, rt.type_name 
          FROM revenues r 
          JOIN revenue_types rt ON r.type_id = rt.type_id
          LEFT JOIN payment_methods pm ON r.method_id = pm.method_id
          ORDER BY {$sort_column} {$sort_direction}";
$revenues = $db->query($query);
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<!-- المحتوى الرئيسي -->
<div class="container-fluid py-4">
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">إدارة الإيرادات الأخرى</h1>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h3 class="card-title">قائمة الإيرادات</h3>
                                <div>
                                    <a href="types.php" class="btn btn-secondary">
                                        <i class="bi bi-list-ul"></i> أنواع الإيرادات
                                    </a>
                                    <a href="add.php" class="btn btn-primary">
                                        <i class="bi bi-plus-lg"></i> إضافة إيراد جديد
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <?php foreach ($allowed_columns as $column => $title): ?>
                                                <th>
                                                    <a href="?sort=<?php echo $column; ?>&direction=<?php echo ($sort_column === $column && $sort_direction === 'ASC') ? 'DESC' : 'ASC'; ?>" 
                                                       class="text-dark text-decoration-none">
                                                        <?php echo $title; ?>
                                                        <?php if ($sort_column === $column): ?>
                                                            <i class="bi bi-arrow-<?php echo $sort_direction === 'ASC' ? 'up' : 'down'; ?>"></i>
                                                        <?php endif; ?>
                                                    </a>
                                                </th>
                                            <?php endforeach; ?>
                                            <th>الإجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $counter = 1; ?>
                                        <?php while ($revenue = $revenues->fetch(PDO::FETCH_ASSOC)): ?>
                                        <tr>
                                            <td><?php echo $counter++; ?></td>
                                            <td><?php echo date('Y-m-d', strtotime($revenue['payment_date'])); ?></td>
                                            <td><?php echo number_format($revenue['amount'], 2); ?> ريال</td>
                                            <td><?php echo htmlspecialchars($revenue['description']) ?: '-'; ?></td>
                                            <td><?php echo htmlspecialchars($revenue['received_by']); ?></td>
                                            <td><?php echo htmlspecialchars($revenue['receipt_number']) ?: '-'; ?></td>
                                            <td><?php echo htmlspecialchars($revenue['payment_type']) ?: '-'; ?></td>
                                            <td><?php echo htmlspecialchars($revenue['type_name']); ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <?php if (hasPermission('view_revenues')): ?>
                                                        <a href="view.php?id=<?php echo $revenue['revenue_id']; ?>" class="btn btn-sm btn-info" title="عرض">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (hasPermission('edit_revenues')): ?>
                                                        <a href="edit.php?id=<?php echo $revenue['revenue_id']; ?>" class="btn btn-sm btn-primary" title="تعديل">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (hasPermission('delete_revenues')): ?>
                                                        <form method="post" action="process_revenue.php" class="d-inline delete-form" onsubmit="return confirm('هل أنت متأكد من حذف هذا الإيراد؟');">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="revenue_id" value="<?php echo $revenue['revenue_id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger" title="حذف">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (hasPermission('print_revenues')): ?>
                                                        <a href="print_receipt.php?id=<?php echo $revenue['revenue_id']; ?>" class="btn btn-sm btn-secondary" title="طباعة">
                                                            <i class="bi bi-printer"></i>
                                                        </a>
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
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>