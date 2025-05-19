<?php
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../config/database.php';

// إنشاء اتصال بقاعدة البيانات
$database = new Database();
$db = $database->getConnection();

// استعلام لجلب الإيرادات مع أنواعها
$query = "
    SELECT 
        r.*,
        rt.type_name,
        COALESCE(c.contract_id, '-') as contract_id
    FROM 
        revenues r
        JOIN revenue_types rt ON r.type_id = rt.type_id
        LEFT JOIN contracts c ON r.contract_id = c.contract_id
    ORDER BY 
        r.payment_date DESC,
        r.revenue_id DESC
";
$revenues = $db->query($query);
?>

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
                                            <th>النوع</th>
                                            <th>المبلغ</th>
                                            <th>التاريخ</th>
                                            <th>رقم الإيصال</th>
                                            <th>رقم العقد</th>
                                            <th>طريقة الدفع</th>
                                            <th>الوصف</th>
                                            <th>الإجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($revenue = $revenues->fetch(PDO::FETCH_ASSOC)): ?>
                                        <tr>
                                            <td><?php echo $revenue['revenue_id']; ?></td>
                                            <td><?php echo htmlspecialchars($revenue['type_name']); ?></td>
                                            <td><?php echo number_format($revenue['amount'], 2); ?> ريال</td>
                                            <td><?php echo date('Y-m-d', strtotime($revenue['payment_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($revenue['receipt_number']) ?: '-'; ?></td>
                                            <td><?php echo $revenue['contract_id']; ?></td>
                                            <td><?php echo $revenue['payment_method'] == 'cash' ? 'نقداً' : 'تحويل'; ?></td>
                                            <td><?php echo htmlspecialchars($revenue['description']) ?: '-'; ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="view.php?id=<?php echo $revenue['revenue_id']; ?>" 
                                                       class="btn btn-sm btn-info">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="edit.php?id=<?php echo $revenue['revenue_id']; ?>" 
                                                       class="btn btn-sm btn-warning">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-danger delete-revenue" 
                                                            data-id="<?php echo $revenue['revenue_id']; ?>">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
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

<!-- Modal حذف الإيراد -->
<div class="modal fade" id="deleteRevenueModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تأكيد الحذف</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                هل أنت متأكد من حذف هذا الإيراد؟
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">حذف</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    let revenueIdToDelete = null;
    
    // عند النقر على زر الحذف
    $('.delete-revenue').click(function() {
        revenueIdToDelete = $(this).data('id');
        $('#deleteRevenueModal').modal('show');
    });
    
    // تأكيد الحذف
    $('#confirmDelete').click(function() {
        if (revenueIdToDelete) {
            $.ajax({
                url: 'process_revenue.php',
                type: 'POST',
                data: {
                    action: 'delete',
                    revenue_id: revenueIdToDelete
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('حدث خطأ أثناء الحذف: ' + response.message);
                    }
                },
                error: function() {
                    alert('حدث خطأ في الاتصال بالخادم');
                }
            });
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
