<?php
require_once "../config/config.php";
require_once "../includes/check_permission.php";
require_once "../config/database.php";

// التحقق من تسجيل الدخول
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// التحقق من وجود معرف المصروف
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$expense_id = (int)$_GET['id'];

// إنشاء اتصال بقاعدة البيانات
$database = new Database();
$db = $database->getConnection();

try {
    // استعلام بيانات المصروف
    $query = "
        SELECT 
            e.*,
            t.type_name,
            pm.method_name as payment_method_name
        FROM 
            expenses e
            JOIN expense_types t ON e.type_id = t.type_id
            LEFT JOIN payment_methods pm ON e.method_id = pm.method_id
        WHERE 
            e.expense_id = ?
    ";
    $stmt = $db->prepare($query);
    $stmt->execute([$expense_id]);
    $expense = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$expense) {
        $_SESSION['error'] = "المصروف غير موجود";
        header('Location: index.php');
        exit();
    }

    // استعلام أنواع المصروفات
    $types = $db->query("SELECT * FROM expense_types ORDER BY type_name");
    
    // استعلام طرق الدفع
    $payment_methods = $db->query("SELECT * FROM payment_methods ORDER BY method_name");
} catch(PDOException $e) {
    $_SESSION['error'] = "خطأ في قاعدة البيانات: " . $e->getMessage();
    header('Location: index.php');
    exit();
}
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">
<!-- المحتوى الرئيسي -->
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">تعديل المصروف</h5>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-right"></i> عودة للقائمة
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="editExpenseForm" class="row g-3">
                            <input type="hidden" name="expense_id" value="<?php echo $expense_id; ?>">
                            <input type="hidden" name="action" value="edit">

                            
                            <div class="col-md-6">
                                <label class="form-label">التاريخ</label>
                                <input type="date" name="expense_date" class="form-control" 
                                    value="<?php echo $expense['expense_date']; ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">رقم الإيصال</label>
                                <input type="text" name="receipt_number" class="form-control" 
                                    value="<?php echo htmlspecialchars($expense['receipt_number']); ?>" 
                                    placeholder="اختياري">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">نوع المصروف</label>
                                <select name="type_id" class="form-select" required>
                                    <?php while ($type = $types->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo $type['type_id']; ?>" 
                                                <?php echo ($type['type_id'] == $expense['type_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($type['type_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">المبلغ</label>
                                <div class="input-group">
                                    <input type="number" name="amount" class="form-control" 
                                        value="<?php echo $expense['amount']; ?>" 
                                        step="0.01" required>
                                    <span class="input-group-text">ريال</span>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">طريقة الدفع</label>
                                <select name="payment_method" class="form-select" required>
                                    <option value="">اختر طريقة الدفع</option>
                                    <?php while ($method = $payment_methods->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo $method['method_name']; ?>" 
                                            <?php echo ($expense['payment_method_name'] == $method['method_name']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($method['method_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">اسم المستلم</label>
                                <input type="text" name="payee_name" class="form-control" 
                                    value="<?php echo htmlspecialchars($expense['payee_name']); ?>" 
                                    placeholder="اختياري">
                            </div>

                            <div class="col-12">
                                <label class="form-label">البيان</label>
                                <textarea name="description" class="form-control" rows="3" 
                                        placeholder="تفاصيل إضافية عن المصروف (اختياري)"><?php echo htmlspecialchars($expense['description']); ?></textarea>
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> حفظ التغييرات
                                </button>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="bi bi-x-lg"></i> إلغاء
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    $('#editExpenseForm').on('submit', function(e) {
        e.preventDefault();
        
        // تعطيل زر الحفظ
        $('button[type="submit"]').prop('disabled', true);
        
        $.ajax({
            url: 'process_expense.php',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('تم تعديل المصروف بنجاح');
                    window.location.href = 'index.php';
                } else {
                    alert('حدث خطأ: ' + (response.message || 'خطأ غير معروف'));
                    // إعادة تفعيل زر الحفظ
                    $('button[type="submit"]').prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                alert('حدث خطأ في الاتصال بالخادم: ' + error);
                // إعادة تفعيل زر الحفظ
                $('button[type="submit"]').prop('disabled', false);
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
