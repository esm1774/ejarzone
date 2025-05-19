<?php
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../config/database.php';

// إنشاء اتصال بقاعدة البيانات
$database = new Database();
$db = $database->getConnection();

// جلب أنواع الإيرادات
$types = $db->query("SELECT * FROM revenue_types ORDER BY type_name");

// جلب العقود النشطة
$contracts = $db->query("
    SELECT 
        c.contract_id,
        c.contract_id,
        t.full_name as tenant_name,
        u.unit_name
    FROM 
        contracts c
        JOIN tenants t ON c.tenant_id = t.tenant_id
        JOIN units u ON c.unit_id = u.unit_id
    WHERE 
        c.status = 'ساري'
    ORDER BY 
        c.contract_id
");
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
                                        <div class="mb-3">
                                            <label for="type_id" class="form-label">نوع الإيراد *</label>
                                            <select class="form-select" id="type_id" name="type_id" required>
                                                <option value="">اختر نوع الإيراد</option>
                                                <?php while ($type = $types->fetch()): ?>
                                                <option value="<?php echo $type['type_id']; ?>">
                                                    <?php echo htmlspecialchars($type['type_name']); ?>
                                                </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="amount" class="form-label">المبلغ *</label>
                                            <input type="number" step="0.01" class="form-control" id="amount" 
                                                   name="amount" required>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="payment_date" class="form-label">تاريخ الإيراد *</label>
                                            <input type="date" class="form-control" id="payment_date" 
                                                   name="payment_date" required>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="contract_id" class="form-label">رقم العقد</label>
                                            <select class="form-select" id="contract_id" name="contract_id">
                                                <option value="">اختر العقد (اختياري)</option>
                                                <?php while ($contract = $contracts->fetch()): ?>
                                                <option value="<?php echo $contract['contract_id']; ?>">
                                                    <?php echo htmlspecialchars($contract['contract_id'] . 
                                                          ' - ' . $contract['tenant_name'] . 
                                                          ' (' . $contract['unit_name'] . ')'); ?>
                                                </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label for="description" class="form-label">الوصف</label>
                                            <textarea class="form-control" id="description" name="description" 
                                                      rows="3"></textarea>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label for="payment_method" class="form-label">طريقة الدفع:</label>
                                            <select class="form-select" id="payment_method" name="payment_method" required>
                                                <option value="cash">نقداً</option>
                                                <option value="transfer">تحويل</option>
                                            </select>
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
        
        // التحقق من الحقول المطلوبة
        var requiredFields = ['type_id', 'amount', 'payment_date', 'payment_method'];
        var isValid = true;
        
        requiredFields.forEach(function(field) {
            if (!$('#' + field).val()) {
                isValid = false;
                $('#' + field).addClass('is-invalid');
            } else {
                $('#' + field).removeClass('is-invalid');
            }
        });
        
        if (!isValid) {
            alert('الرجاء ملء جميع الحقول المطلوبة');
            return;
        }
        
        $.ajax({
            url: 'process_revenue.php',
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if (response.success) {
                    window.location.href = 'index.php';
                } else {
                    alert('حدث خطأ: ' + response.message);
                }
            },
            error: function() {
                alert('حدث خطأ في الاتصال بالخادم');
            }
        });
    });

    // تحديث حالة حقل العقد بناءً على نوع الإيراد
    $('#type_id').on('change', function() {
        const selectedOption = $(this).find('option:selected');
        const contractField = $('#contract_id');
        
        if (selectedOption.text().includes('إيجار')) {
            contractField.prop('required', true);
            contractField.closest('.mb-3').show();
        } else {
            contractField.prop('required', false);
            contractField.closest('.mb-3').hide();
        }
    });

    // عند تحميل الصفحة
    $('#type_id').trigger('change');
});
</script>

<?php require_once '../includes/footer.php'; ?>
