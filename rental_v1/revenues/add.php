<?php
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../config/database.php';

// إنشاء اتصال بقاعدة البيانات
$database = new Database();
$db = $database->getConnection();

// جلب أنواع الإيرادات
$types = $db->query("SELECT * FROM revenue_types ORDER BY type_name");
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">إضافة إيراد جديد</h1>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <form id="addRevenueForm">
                                <input type="hidden" name="action" value="add">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="type_id" class="form-label">نوع الإيراد <span class="text-danger">*</span></label>
                                            <select class="form-control" id="type_id" name="type_id" required>
                                                <option value="">اختر نوع الإيراد</option>
                                                <?php while($type = $types->fetch(PDO::FETCH_ASSOC)): ?>
                                                    <option value="<?php echo $type['type_id']; ?>">
                                                        <?php echo htmlspecialchars($type['type_name']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="amount" class="form-label">المبلغ <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control" id="amount" name="amount" step="0.01" required>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="payment_date" class="form-label">تاريخ الدفع <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" id="payment_date" name="payment_date" required>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="payment_type" class="form-label">طريقة الدفع <span class="text-danger">*</span></label>
                                            <select class="form-control" id="payment_type" name="payment_type" required>
                                                <option value="كاش">كاش</option>
                                                <option value="تحويل">تحويل</option>
                                                <option value="شبكة">شبكة</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="received_by" class="form-label">المُسلم <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="received_by" name="received_by" required>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="description" class="form-label">الوصف</label>
                                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-end">
                                    <a href="index.php" class="btn btn-secondary">إلغاء</a>
                                    <button type="submit" class="btn btn-primary">حفظ</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#addRevenueForm').submit(function(e) {
        e.preventDefault();
        
        $.ajax({
            url: 'process_revenue.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    window.location.href = 'index.php';
                } else {
                    alert(response.message);
                }
            },
            error: function() {
                alert('حدث خطأ في الاتصال بالخادم');
            }
        });
    });

    // تعيين تاريخ اليوم كقيمة افتراضية
    document.getElementById('payment_date').valueAsDate = new Date();
});</script>

<?php require_once '../includes/footer.php'; ?>
