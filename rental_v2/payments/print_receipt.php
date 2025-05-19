<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

// التحقق من تسجيل الدخول
checkAuth();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('معرف الدفعة غير صحيح');
}

$payment_id = $_GET['id'];
$db = getDatabaseConnection();

try {
    $query = "
        SELECT 
            p.payment_id,
            p.receipt_number,
            p.amount,
            p.payment_date,
            pm.method_name as payment_type,
            t.full_name as tenant_name,
            CONCAT('إيجار شقة ', u.unit_name, ' إلى تاريخ ', DATE_FORMAT(c.next_due_date, '%Y/%m/%d')) as payment_description
        FROM 
            payments p
            JOIN contracts c ON p.contract_id = c.contract_id
            JOIN units u ON c.unit_id = u.unit_id
            JOIN tenants t ON c.tenant_id = t.tenant_id
            JOIN payment_methods pm ON p.method_id = pm.method_id
        WHERE 
            p.payment_id = :payment_id
    ";

    $stmt = $db->prepare($query);
    $stmt->execute(['payment_id' => $payment_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        die('الدفعة غير موجودة');
    }

    // تحويل المبلغ إلى كلمات
    function numberToArabicWords($number) {
        $number = (int)$number;
        $arr = array(
            "صفر", "واحد", "اثنان", "ثلاثة", "أربعة", "خمسة", "ستة", "سبعة", "ثمانية", "تسعة",
            "عشرة", "أحد عشر", "اثنا عشر", "ثلاثة عشر", "أربعة عشر", "خمسة عشر", "ستة عشر", "سبعة عشر", "ثمانية عشر", "تسعة عشر",
            "عشرون", "ثلاثون", "أربعون", "خمسون", "ستون", "سبعون", "ثمانون", "تسعون"
        );
        
        if ($number == 0) return $arr[0];
        
        $result = "";
        
        if ($number >= 1000) {
            $thousands = floor($number / 1000);
            if ($thousands == 1) {
                $result .= "ألف ";
            } elseif ($thousands == 2) {
                $result .= "ألفان ";
            } elseif ($thousands <= 10) {
                $result .= $arr[$thousands] . " آلاف ";
            } else {
                $result .= numberToArabicWords($thousands) . " ألف ";
            }
            $number %= 1000;
        }
        
        if ($number >= 100) {
            $hundreds = floor($number / 100);
            if ($hundreds == 1) {
                $result .= "مائة ";
            } elseif ($hundreds == 2) {
                $result .= "مائتان ";
            } else {
                $result .= $arr[$hundreds] . "مائة ";
            }
            $number %= 100;
        }
        
        if ($number > 0) {
            if ($number <= 19) {
                $result .= $arr[$number] . " ";
            } else {
                $ones = $number % 10;
                $tens = floor($number / 10) + 18;
                if ($ones > 0) {
                    $result .= $arr[$ones] . " و";
                }
                $result .= $arr[$tens] . " ";
            }
        }
        
        return trim($result);
    }

    $amount_in_words = numberToArabicWords($payment['amount']) . " ريال فقط لا غير";
} catch (PDOException $e) {
    die('حدث خطأ أثناء جلب بيانات الدفعة: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سند قبض #<?php echo $payment['receipt_number']; ?></title>
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
            سند قبض
            <div class="receipt-title-english">Receipt Voucher</div>
        </div>

        <div class="receipt-info">
            <div class="receipt-number">
                <span>رقم:</span>
                <span class="number"><?php echo $payment['receipt_number']; ?></span>
                <br>
                <span>التاريخ:</span>
                <span class="date"><?php echo date('Y/m/d', strtotime($payment['payment_date'])); ?></span>
            </div>
            <div class="amount-box">
                المبلغ Amount
                <div class="amount"><?php echo number_format($payment['amount'], 0); ?></div>
            </div>
        </div>

        <div class="receipt-details">
            <div class="label">استلمنا من السيد:</div>
            <div class="value"><?php echo htmlspecialchars($payment['tenant_name']); ?></div>
            <div class="value"></div>

            <div class="label">مبلغ وقدره:</div>
            <div class="value"><?php echo $amount_in_words; ?></div>
            <div class="value"></div>
            <div class="label" style="text-align: left;">Amount SR :</div>

            <div class="label">وذلك عن:</div>
            <div class="value"><?php echo htmlspecialchars($payment['payment_description']); ?></div>
            <div class="value"></div>
            <div class="label" style="text-align: left;">payment against :</div>


            <div class="label">كاش أو تحويل:</div>
            <div class="value"><?php echo $payment['payment_type']; ?></div>
            <div class="value"></div>
            <div class="label" style="text-align: left;">Cash OR Trans :</div>
        </div>

        <div class="accountant-section">
            <div class="recipient">
                <div class="label">المُسلم:</div>
                <div class="value"><?php echo htmlspecialchars($payment['tenant_name']); ?></div>
            </div>
            <div class="stamp-section">
                <img src="../assets/img/stamp.png" alt="Company Stamp" class="stamp-image">
                
            </div>
            
            <div class="accountant">
                <div class="label">المستلم:</div>
                <div class="value"><?php echo isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'المحاسب'; ?></div>
            </div>
        </div>
    </div>
</body>
</html>
