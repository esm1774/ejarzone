<?php
require_once "../config/config.php";
require_once "../includes/check_permission.php";
require_once "../config/database.php";

$database = new Database();
$db = $database->getConnection();

// إنشاء اتصال بقاعدة البيانات

// استعلام المصروفات مع أنواعها
try {
    // أولاً نتحقق من وجود الجداول
    $check_tables = $db->query("SHOW TABLES LIKE 'expense%'");
    $tables = $check_tables->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('expense_types', $tables) || !in_array('expenses', $tables)) {
        echo "
            <div class='alert alert-warning'>
                يجب إنشاء جداول المصروفات أولاً. 
                <a href='create_tables.sql' target='_blank'>اضغط هنا</a> 
                لعرض أوامر إنشاء الجداول.
            </div>
        ";
        exit();
    }

    // جلب معلومات الترتيب
    $sort_column = $_GET['sort'] ?? 'e.expense_date';
    $sort_direction = $_GET['direction'] ?? 'DESC';

    // التحقق من صحة عمود الترتيب
    $allowed_columns = [
        'e.expense_date' => 'تاريخ المصروف',
        'e.amount' => 'المبلغ',
        'e.description' => 'الوصف',
        't.type_name' => 'التصنيف',
        'e.payment_type' => 'طريقة الدفع',
        'e.payee_name' => 'المستلم',
        'e.receipt_number' => 'رقم الإيصال'
    ];

    if (!array_key_exists($sort_column, $allowed_columns)) {
        $sort_column = 'e.expense_date';
    }

    // التحقق من صحة اتجاه الترتيب
    $sort_direction = strtoupper($sort_direction) === 'DESC' ? 'DESC' : 'ASC';

    // استعلام المصروفات
    $query = "
        SELECT 
            t.type_name,
            e.expense_id,
            e.amount,
            e.expense_date,
            e.payee_name, 
            e.receipt_number,
            e.description,
            e.payment_type
        FROM 
            expense_types t
            INNER JOIN expenses e ON t.type_id = e.type_id
        ORDER BY 
            {$sort_column} {$sort_direction}
        LIMIT 100
    ";
    $expenses = $db->query($query);

    // استعلام أنواع المصروفات
    $types_query = "SELECT * FROM expense_types ORDER BY type_name";
    $types = $db->query($types_query);
    $types_edit = $db->query($types_query);
} catch(PDOException $e) {
    echo "خطأ في قاعدة البيانات: " . $e->getMessage();
    exit();
}
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<!-- المحتوى الرئيسي -->
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">إدارة المصروفات</h5>
                        <a href="add.php" class="btn btn-primary">
                            <i class="bi bi-plus-lg"></i> إضافة مصروف جديد
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
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
                                        <th>إجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $counter = 1; ?>
                                    <?php while ($expense = $expenses->fetch(PDO::FETCH_ASSOC)): ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td>
                                            <a href="view.php?id=<?php echo $expense['expense_id']; ?>" class="text-dark text-decoration-none">
                                                <?php echo date('Y-m-d', strtotime($expense['expense_date'])); ?>
                                            </a>
                                        </td>
                                        <td><?php echo number_format($expense['amount'], 2) . ' ريال'; ?></td>
                                        <td><?php echo htmlspecialchars($expense['description']) ?: '-'; ?></td>
                                        <td><?php echo htmlspecialchars($expense['type_name']); ?></td>
                                        <td><?php echo htmlspecialchars($expense['payment_type'])?:'-'; ?> </td>
                                        <td><?php echo htmlspecialchars($expense['payee_name']) ?: '-'; ?></td>
                                        <td><?php echo htmlspecialchars($expense['receipt_number']) ?: '-'; ?></td>
                                        <td>
                                            <div class='btn-group' role='group'>
                                                
                                                <?php if (hasPermission('view_expenses')): ?>
                                                    <a href="view.php?id=<?php echo $expense['expense_id']; ?>" class="btn btn-sm btn-info" title="عرض">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <?php if (hasPermission('edit_expenses')): ?>
                                                    <a href="edit.php?id=<?php echo $expense['expense_id']; ?>" class="btn btn-sm btn-primary" title="تعديل">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <?php if (hasPermission('delete_expenses')): ?>
                                                    <button type="button" class="btn btn-sm btn-danger" title="حذف" onclick="confirmDelete(<?php echo $expense['expense_id']; ?>)">
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
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // حذف المصروف
            $('.delete-expense').click(function() {
                if (confirm('هل أنت متأكد من حذف هذا المصروف؟')) {
                    var id = $(this).data('id');
                    $.ajax({
                        url: 'process_expense.php',
                        method: 'POST',
                        data: {
                            expense_id: id,
                            action: 'delete'
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert('حدث خطأ: ' + response.message);
                            }
                        }
                    });
                }
            });
        });
    </script>

<?php include '../includes/footer.php'; ?>