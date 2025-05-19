<?php
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../config/database.php';

// التحقق من وجود معرف الإيراد
if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

// إنشاء اتصال بقاعدة البيانات
$database = new Database();
$db = $database->getConnection();

// جلب بيانات الإيراد
$query = "
    SELECT 
        r.*,
        rt.type_name,
        COALESCE(c.contract_id, '-') as contract_id,
        COALESCE(t.full_name, '-') as full_name,
        COALESCE(u.unit_name, '-') as unit_name
    FROM 
        revenues r
        JOIN revenue_types rt ON r.type_id = rt.type_id
        LEFT JOIN contracts c ON r.contract_id = c.contract_id
        LEFT JOIN tenants t ON c.tenant_id = t.tenant_id
        LEFT JOIN units u ON c.unit_id = u.unit_id
    WHERE 
        r.revenue_id = :revenue_id
";

$stmt = $db->prepare($query);
$stmt->execute(['revenue_id' => $_GET['id']]);
$revenue = $stmt->fetch(PDO::FETCH_ASSOC);

// إذا لم يتم العثور على الإيراد
if (!$revenue) {
    header('Location: index.php');
    exit;
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">تفاصيل الإيراد</h1>
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
                                <h3 class="card-title">معلومات الإيراد #<?php echo $revenue['revenue_id']; ?></h3>
                                <div class="text-end mt-3">
                                    <a href="index.php" class="btn btn-secondary">عودة</a>
                                    <a href="edit.php?id=<?php echo $revenue['revenue_id']; ?>" class="btn btn-primary">
                                        <i class="bi bi-pencil"></i> تعديل
                                    </a>
                                    <a href="print_receipt.php?id=<?php echo $revenue['revenue_id']; ?>" 
                                       class="btn btn-success" target="_blank">
                                        <i class="bi bi-printer"></i> طباعة الإيصال
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th style="width: 200px">نوع الإيراد</th>
                                            <td><?php echo htmlspecialchars($revenue['type_name']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>المبلغ</th>
                                            <td><?php echo number_format($revenue['amount'], 2); ?> ريال</td>
                                        </tr>
                                        <tr>
                                            <th>تاريخ الإيراد</th>
                                            <td><?php echo date('Y-m-d', strtotime($revenue['payment_date'])); ?></td>
                                        </tr>
                                        <tr>
                                            <th>رقم الإيصال</th>
                                            <td><?php echo htmlspecialchars($revenue['receipt_number']) ?: '-'; ?></td>
                                        </tr>
                                        <tr>
                                            <th>طريقة الدفع</th>
                                            <td><?php echo $revenue['payment_method'] == 'cash' ? 'نقداً' : 'تحويل'; ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th style="width: 200px">رقم العقد</th>
                                            <td><?php echo $revenue['contract_id']; ?></td>
                                        </tr>
                                        <tr>
                                            <th>اسم المستأجر</th>
                                            <td><?php echo $revenue['full_name']; ?></td>
                                        </tr>
                                        <tr>
                                            <th>الوحدة</th>
                                            <td><?php echo $revenue['unit_name']; ?></td>
                                        </tr>
                                        <tr>
                                            <th>الوصف</th>
                                            <td><?php echo htmlspecialchars($revenue['description']) ?: '-'; ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
