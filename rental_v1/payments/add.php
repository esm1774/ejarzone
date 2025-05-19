<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

include '../includes/header.php';

// إنشاء اتصال قاعدة البيانات
$database = new Database();
$db = $database->getConnection();
?>

<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">تسجيل دفعة جديدة</h5>
        </div>
        <div class="card-body">
            <!-- نموذج البحث -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" id="search" class="form-control" placeholder="ابحث عن مستأجر...">
                        <button class="btn btn-primary" type="button" id="searchButton">
                            <i class="bi bi-search"></i> بحث
                        </button>
                    </div>
                </div>
            </div>

            <!-- نتائج البحث -->
            <div id="searchResults" class="mb-4"></div>

            <!-- نموذج الدفع -->
            <form id="paymentForm" style="display: none;" method="post">
                <input type="hidden" name="contract_id" id="contractId">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="amount" class="form-label">المبلغ</label>
                        <input type="number" step="0.01" class="form-control" id="amount" name="amount" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="payment_type" class="form-label">طريقة الدفع</label>
                        <select class="form-select" id="payment_type" name="payment_type" required>
                            <option value="">اختر طريقة الدفع</option>
                            <option value="كاش">كاش</option>
                            <option value="تحويل">تحويل</option>
                        </select>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="receipt_number" class="form-label">رقم الإيصال</label>
                        <input type="text" class="form-control" id="receipt_number" name="receipt_number" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="notes" class="form-label">ملاحظات</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                    </div>
                </div>

                <input type="hidden" name="received_by" value="<?php echo $_SESSION['user_id']; ?>">
                <button type="submit" class="btn btn-primary">تسجيل الدفعة</button>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    // معالجة تقديم النموذج
    $('#paymentForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        $.ajax({
            url: 'process_payment.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('تم تسجيل الدفعة بنجاح');
                    window.location.href = 'index.php';
                } else {
                    console.error('Server Error:', response);
                    alert(response.message || 'حدث خطأ أثناء تسجيل الدفعة');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                alert('حدث خطأ أثناء تسجيل الدفعة');
            }
        });
    });

    // معالجة زر البحث
    $('#searchButton').on('click', searchTenant);
});

function searchTenant() {
    const searchValue = $('#search').val();
    if (searchValue.trim() === '') {
        alert('الرجاء إدخال قيمة للبحث');
        return;
    }

    $.ajax({
        url: 'search_tenant.php',
        type: 'GET',
        data: { search: searchValue },
        success: function(response) {
            if (response.success) {
                let html = `
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>اسم المستأجر</th>
                                <th>رقم الهاتف</th>
                                <th>الوحدة</th>
                                <th>نوع الإيجار</th>
                                <th>قيمة الإيجار</th>
                                <th>تاريخ الاستحقاق</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

                response.data.forEach(function(result) {
                    html += `
                        <tr>
                            <td>${result.tenant_name}</td>
                            <td>${result.phone}</td>
                            <td>${result.unit_name}</td>
                            <td>${result.rent_type}</td>
                            <td>${result.rent_amount}</td>
                            <td>${result.next_payment_date}</td>
                            <td>
                                <button type="button" class="btn btn-primary btn-sm" onclick="selectContract(${result.contract_id}, ${result.rent_amount})">
                                    اختيار
                                </button>
                            </td>
                        </tr>
                    `;
                });

                html += '</tbody></table>';
                $('#searchResults').html(html);
            } else {
                $('#searchResults').html(`
                    <div class="alert alert-warning">
                        ${response.message}
                    </div>
                `);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error:', error);
            $('#searchResults').html(`
                <div class="alert alert-danger">
                    حدث خطأ أثناء البحث
                </div>
            `);
        }
    });
}

function selectContract(contractId, rentAmount) {
    $('#contractId').val(contractId);
    $('#amount').val(rentAmount);
    $('#paymentForm').show();
    
    // إنشاء رقم إيصال تلقائي
    const today = new Date();
    const receiptNumber = 'RCP-' + 
                         today.getFullYear() +
                         (today.getMonth() + 1).toString().padStart(2, '0') +
                         today.getDate().toString().padStart(2, '0') + '-' + 
                         Math.floor(Math.random() * 1000).toString().padStart(3, '0');
    $('#receipt_number').val(receiptNumber);
}
</script>
