<?php
require_once __DIR__ . '/../config/database.php';

// التحقق من تسجيل الدخول
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// إنشاء اتصال بقاعدة البيانات
$database = new Database();
$db = $database->getConnection();

// استعلام أنواع المصروفات
try {
    $types = $db->query("SELECT * FROM expense_types ORDER BY type_name");
} catch(PDOException $e) {
    echo "خطأ في قاعدة البيانات: " . $e->getMessage();
    exit();
}

include '../includes/header.php';
?>

<!-- المحتوى الرئيسي -->
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">إضافة مصروف جديد</h5>
                </div>
                <div class="card-body">
                    <form id="addExpenseForm" class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">التاريخ</label>
                            <input type="date" name="expense_date" class="form-control" required 
                                   value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">نوع المصروف</label>
                            <select name="type_id" class="form-select" required>
                                <option value="">اختر نوع المصروف</option>
                                <?php while ($type = $types->fetch(PDO::FETCH_ASSOC)): ?>
                                    <option value="<?php echo $type['type_id']; ?>">
                                        <?php echo htmlspecialchars($type['type_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">المبلغ</label>
                            <div class="input-group">
                                <input type="number" name="amount" class="form-control" step="0.01" required>
                                <span class="input-group-text">ريال</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label">طريقة الدفع</label>
                            <select name="payment_type" class="form-select" required>
                                <option value="">اختر طريقة الدفع</option>
                                <option value="cash">كاش</option>
                                <option value="transfer">تحويل</option>
                                <option value="network">شبكة</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">اسم المستفيد</label>
                            <input type="text" name="payee_name" class="form-control" required>
                        </div>

                        <!-- <div class="col-md-6">
                            <label class="form-label">رقم الإيصال</label>
                            <input type="text" name="receipt_number" class="form-control" 
                                   placeholder="اختياري">
                        </div> -->

                        <div class="col-6">
                            <label class="form-label">البيان</label>
                            <textarea name="description" class="form-control" rows="3" 
                                      placeholder="تفاصيل إضافية عن المصروف (اختياري)"></textarea>
                        </div>

                        

                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> حفظ المصروف
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

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    $('#addExpenseForm').on('submit', function(e) {
        e.preventDefault();
        
        // تعطيل زر الحفظ لمنع الإرسال المتكرر
        var submitButton = $(this).find('button[type="submit"]');
        submitButton.prop('disabled', true);
        
        $.ajax({
            url: 'process_expense.php',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('تم إضافة المصروف بنجاح');
                    window.location.href = 'index.php';
                } else {
                    alert('حدث خطأ: ' + (response.message || 'خطأ غير معروف'));
                    submitButton.prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                console.error('XHR Status:', status);
                console.error('Error:', error);
                console.error('Response:', xhr.responseText);
                
                // محاولة تحليل الاستجابة كـ JSON إذا كانت موجودة
                try {
                    var jsonResponse = JSON.parse(xhr.responseText);
                    if (jsonResponse && jsonResponse.success) {
                        alert('تم إضافة المصروف بنجاح');
                        window.location.href = 'index.php';
                        return;
                    }
                } catch (e) {
                    console.error('Failed to parse JSON:', e);
                }
                
                alert('حدث خطأ في الاتصال بالخادم. يرجى المحاولة مرة أخرى.');
                submitButton.prop('disabled', false);
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
