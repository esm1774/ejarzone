<?php
require_once __DIR__ . '/../config/database.php';

// التحقق من تسجيل الدخول
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// التحقق من وجود معرف المصروف
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "معرف المصروف غير صحيح";
    header('Location: index.php');
    exit();
}

$expense_id = (int)$_GET['id'];

// إنشاء اتصال بقاعدة البيانات
$database = new Database();
$db = $database->getConnection();

try {
    // التحقق من وجود المصروف
    $check_query = "SELECT expense_id, expense_date, amount FROM expenses WHERE expense_id = ?";
    $stmt = $db->prepare($check_query);
    $stmt->execute([$expense_id]);
    $expense = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$expense) {
        $_SESSION['error'] = "المصروف غير موجود";
        header('Location: index.php');
        exit();
    }

    // عرض صفحة تأكيد الحذف
    include '../includes/header.php';
?>

<!-- المحتوى الرئيسي -->
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">حذف المصروف</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-danger">
                        <h5>هل أنت متأكد من حذف هذا المصروف؟</h5>
                        <p>
                            <strong>التاريخ:</strong> <?php echo date('Y-m-d', strtotime($expense['expense_date'])); ?><br>
                            <strong>المبلغ:</strong> <?php echo number_format($expense['amount'], 2); ?> ريال
                        </p>
                        <p class="mb-0">لا يمكن التراجع عن هذه العملية بعد تنفيذها.</p>
                    </div>

                    <form id="deleteExpenseForm">
                        <input type="hidden" name="expense_id" value="<?php echo $expense_id; ?>">
                        <input type="hidden" name="action" value="delete">
                        
                        <button type="submit" class="btn btn-danger" id="deleteBtn">
                            <i class="bi bi-trash"></i> تأكيد الحذف
                        </button>
                        <a href="view.php?id=<?php echo $expense_id; ?>" class="btn btn-secondary">
                            <i class="bi bi-x-lg"></i> إلغاء
                        </a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    $('#deleteExpenseForm').on('submit', function(e) {
        e.preventDefault();
        
        if (!confirm('هل أنت متأكد من حذف هذا المصروف؟ لا يمكن التراجع عن هذه العملية.')) {
            return;
        }

        // تعطيل زر الحذف
        $('#deleteBtn').prop('disabled', true).html('<i class="bi bi-hourglass"></i> جاري الحذف...');
        
        $.ajax({
            url: 'process_expense.php',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('تم حذف المصروف بنجاح');
                    window.location.href = 'index.php';
                } else {
                    alert('حدث خطأ: ' + (response.message || 'خطأ غير معروف'));
                    // إعادة تفعيل زر الحذف
                    $('#deleteBtn').prop('disabled', false).html('<i class="bi bi-trash"></i> تأكيد الحذف');
                }
            },
            error: function(xhr, status, error) {
                alert('حدث خطأ في الاتصال بالخادم: ' + error);
                // إعادة تفعيل زر الحذف
                $('#deleteBtn').prop('disabled', false).html('<i class="bi bi-trash"></i> تأكيد الحذف');
            }
        });
    });
});
</script>

<?php 
    include '../includes/footer.php';
} catch(PDOException $e) {
    $_SESSION['error'] = "خطأ في قاعدة البيانات: " . $e->getMessage();
    header('Location: index.php');
    exit();
}
?>
