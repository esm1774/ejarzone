<?php
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../config/database.php';

// إنشاء اتصال بقاعدة البيانات
$database = new Database();
$db = $database->getConnection();

// التحقق من وجود معرف الإيراد
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$revenue_id = $_GET['id'];

// جلب بيانات الإيراد
$query = "
    SELECT 
        r.*,
        rt.type_name
    FROM 
        revenues r
        JOIN revenue_types rt ON r.type_id = rt.type_id
    WHERE 
        r.revenue_id = :revenue_id
";

$stmt = $db->prepare($query);
$stmt->execute(['revenue_id' => $revenue_id]);
$revenue = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$revenue) {
    header('Location: index.php');
    exit;
}

// جلب أنواع الإيرادات
$types = $db->query("SELECT * FROM revenue_types ORDER BY type_name");

// جلب العقود النشطة
$contracts = $db->query("
    SELECT 
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
                    <h1 class="m-0">تعديل الإيراد</h1>
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
                            <form id="editRevenueForm">
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="revenue_id" value="<?php echo $revenue_id; ?>">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="type_id" class="form-label">نوع الإيراد *</label>
                                            <select class="form-select" id="type_id" name="type_id" required>
                                                <option value="">اختر نوع الإيراد</option>
                                                <?php while ($type = $types->fetch()): ?>
                                                <option value="<?php echo $type['type_id']; ?>" 
                                                    <?php echo ($type['type_id'] == $revenue['type_id']) ? 'selected' : ''; ?>>
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
                                                   name="amount" required value="<?php echo $revenue['amount']; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="payment_date" class="form-label">تاريخ الإيراد *</label>
                                            <input type="date" class="form-control" id="payment_date" 
                                                   name="payment_date" required 
                                                   value="<?php echo date('Y-m-d', strtotime($revenue['payment_date'])); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="receipt_number" class="form-label">رقم الإيصال</label>
                                            <input type="text" class="form-control" id="receipt_number" 
                                                   name="receipt_number" readonly
                                                   value="<?php echo htmlspecialchars($revenue['receipt_number']); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="contract_id" class="form-label">رقم العقد</label>
                                            <select class="form-select" id="contract_id" name="contract_id">
                                                <option value="">اختر العقد (اختياري)</option>
                                                <?php while ($contract = $contracts->fetch()): ?>
                                                <option value="<?php echo $contract['contract_id']; ?>"
                                                    <?php echo ($contract['contract_id'] == $revenue['contract_id']) ? 'selected' : ''; ?>>
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
                                                      rows="3"><?php echo htmlspecialchars($revenue['description']); ?></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-end">
                                    <a href="index.php" class="btn btn-secondary">إلغاء</a>
                                    <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
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
    $('#editRevenueForm').submit(function(e) {
        e.preventDefault();
        
        // التحقق من الحقول المطلوبة
        var requiredFields = ['type_id', 'amount', 'payment_date'];
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
