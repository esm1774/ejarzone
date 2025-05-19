<?php
require_once '../config/database.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('معرف المصروف غير صحيح');
}

$expense_id = $_GET['id'];
$database = new Database();
$db = $database->getConnection();

$query = "
    SELECT 
        e.expense_id,
        e.receipt_number,
        e.amount,
        e.expense_date,
        e.payee_name,
        e.description,
        e.payment_type,
        et.type_name
    FROM 
        expenses e
        JOIN expense_types et ON e.type_id = et.type_id
    WHERE 
        e.expense_id = :expense_id
";

$stmt = $db->prepare($query);
$stmt->execute(['expense_id' => $expense_id]);
$expense = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$expense) {
    die('المصروف غير موجود');
}

function numberToArabicWords($number) {
    // ... (نفس الدالة الموجودة في إيصال الإيرادات)
}

$amount_in_words = numberToArabicWords($expense['amount']) . " ريال فقط لا غير";
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سند صرف #<?php echo $expense['receipt_number']; ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="../assets/css/receipt.css">
    <style>
        @page {
            margin: 0;
            size: A4 portrait;
        }
        @media print {
            html, body {
                width: 210mm;
                height: 297mm;
                margin: 0;
                padding: 0;
            }
            .receipt-container {
                margin: 0;
                border: initial;
                border-radius: initial;
                width: initial;
                min-height: initial;
                box-shadow: initial;
                background: initial;
                page-break-after: always;
                -webkit-print-color-adjust: exact;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
    <script>
        window.onload = function() {
            document.querySelector('.print-buttons button').onclick = function() {
                var printContents = document.querySelector('.receipt-container').innerHTML;
                var originalContents = document.body.innerHTML;
                
                document.body.innerHTML = printContents;
                
                window.print();
                
                document.body.innerHTML = originalContents;
                
                return false;
            };
        };
    </script>
</head>
<body>
    <div class="print-buttons no-print">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="bi bi-printer"></i> طباعة
        </button>
        <button onclick="window.close()" class="btn btn-secondary">
            <i class="bi bi-x"></i> إغلاق
        </button>
    </div>

    <div class="receipt-container">
        <div class="header-section">
            <div class="company-info-arabic">
                <div class="company-name">شقق الصرح المخدومة</div>
                <div class="company-details">سجل تجاري: 1011158236</div>
                <div class="company-details">جــوال: 0530309594</div>
            </div>
            <div class="company-info">
                <div class="company-name">Al Sarh serviced Apartments</div>
                <div class="company-details">C.R. 1011158236</div>
                <div class="company-details">Mob. 0530309594</div>
            </div>
        </div>

        <div class="receipt-title">
            سند صرف
            <div class="receipt-title-english">Payment Voucher</div>
        </div>

        <div class="receipt-info">
            <div class="receipt-number">
                <span>رقم:</span>
                <span class="number"><?php echo $expense['receipt_number']; ?></span>
                <br>
                <span>التاريخ:</span>
                <span class="date"><?php echo date('Y/m/d', strtotime($expense['expense_date'])); ?></span>
            </div>
            <div class="amount-box">
                المبلغ Amount
                <div class="amount"><?php echo number_format($expense['amount'], 0); ?></div>
            </div>
        </div>

        <div class="receipt-details">
            <div class="label"> اصرف للسيد:</div>
            <div class="value"><?php echo htmlspecialchars($expense['payee_name']); ?></div>
            <div class="value"></div>
            <div class="label" style="text-align: left;">Pay to :</div>

            <div class="label">مبلغ وقدره: </div>
            <div class="value"><?php echo $amount_in_words; ?></div>
            <div class="value"></div>
            <div class="label" style="text-align: left;">Amount SR :</div>

            <div class="label"> وذلك مقابل:</div>
            <div class="value"><?php echo htmlspecialchars($expense['description']); ?></div>
            <div class="value"></div>
            <div class="label" style="text-align: left;">payment against :</div>

            <div class="label">طريقة الدفع:</div>
            <div class="value"><?php echo $expense['payment_type']; ?></div>
            <div class="value"></div>
            <div class="label" style="text-align: left;">payment method :</div>

        
        </div>

        <div class="accountant-section">
            <div class="recipient">
                <div class="label"> المُستلم:</div>
                <div class="value"><?php echo htmlspecialchars($expense['payee_name']); ?></div>
            </div>
            <div class="stamp-section">
                <img src="../assets/img/stamp.png" alt="Company Stamp" class="stamp-image">
            </div>
            <div class="accountant">
                <div class="label">المُسلم:</div>
                <div class="value"><?php echo $_SESSION['full_name'] ; ?></div>
            </div>
        </div>
    </div>
</body>
</html>
