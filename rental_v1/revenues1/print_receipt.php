<?php
require_once '../config/database.php';

// التحقق من وجود معرف الإيراد
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('معرف الإيراد غير صحيح');
}

$revenue_id = $_GET['id'];

// إنشاء اتصال بقاعدة البيانات
$database = new Database();
$db = $database->getConnection();

// جلب بيانات الإيراد
$query = "
    SELECT 
        r.*,
        rt.type_name,
        COALESCE(c.contract_id, '') as contract_id,
        COALESCE(t.full_name, '') as tenant_name,
        COALESCE(u.unit_name, '') as unit_name
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
$stmt->execute(['revenue_id' => $revenue_id]);
$revenue = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$revenue) {
    die('الإيراد غير موجود');
}

// دالة لتحويل الأرقام إلى كلمات بالعربية
function numberToArabicWords($number) {
    $ones = [
        0 => "صفر", 1 => "واحد", 2 => "اثنان", 3 => "ثلاثة", 4 => "أربعة", 
        5 => "خمسة", 6 => "ستة", 7 => "سبعة", 8 => "ثمانية", 9 => "تسعة"
    ];
    
    $tens = [
        1 => "عشر", 2 => "عشرون", 3 => "ثلاثون", 4 => "أربعون", 
        5 => "خمسون", 6 => "ستون", 7 => "سبعون", 8 => "ثمانون", 9 => "تسعون"
    ];
    
    $hundreds = [
        1 => "مائة", 2 => "مئتان", 3 => "ثلاثمائة", 4 => "أربعمائة", 
        5 => "خمسمائة", 6 => "ستمائة", 7 => "سبعمائة", 8 => "ثمانمائة", 9 => "تسعمائة"
    ];
    
    $thousands = [
        1 => "ألف", 2 => "ألفان", 3 => "ثلاثة آلاف", 4 => "أربعة آلاف", 
        5 => "خمسة آلاف", 6 => "ستة آلاف", 7 => "سبعة آلاف", 8 => "ثمانية آلاف", 9 => "تسعة آلاف"
    ];

    $number = (int)$number;
    if ($number == 0) return "صفر";
    
    $words = "";
    
    // الآلاف
    if ($number >= 1000) {
        $words .= $thousands[floor($number/1000)] . " ";
        $number = $number % 1000;
    }
    
    // المئات
    if ($number >= 100) {
        $words .= $hundreds[floor($number/100)] . " ";
        $number = $number % 100;
    }
    
    // العشرات والآحاد
    if ($number > 0) {
        if ($number < 10) {
            $words .= $ones[$number];
        } else if ($number == 10) {
            $words .= "عشرة";
        } else if ($number < 20) {
            $words .= $ones[$number-10] . " عشر";
        } else {
            if ($number % 10 != 0) {
                $words .= $ones[$number % 10] . " و";
            }
            $words .= $tens[floor($number/10)];
        }
    }
    
    return $words;
}

// تحويل المبلغ إلى كلمات
$amount_in_words = numberToArabicWords($revenue['amount']) . " ريال فقط لا غير";
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سند قبض #<?php echo $revenue['receipt_number']; ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="../assets/css/receipt.css">
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
                <span class="number"><?php echo $revenue['receipt_number']; ?></span>
                <br>
                <span>التاريخ:</span>
                <span class="date"><?php echo date('Y/m/d', strtotime($revenue['payment_date'])); ?></span>
            </div>
            <div class="amount-box">
                المبلغ Amount Received
                <div class="amount"><?php echo number_format($revenue['amount'], 0); ?></div>
            </div>
        </div>

        <div class="receipt-details">
            <div class="label">استلمنا من السيد:</div>
            <div class="value"><?php echo htmlspecialchars($revenue['tenant_name']); ?></div>
            <div class="value"></div>
            <div class="label" style="text-align: left;">Received From :</div>

            <div class="label">مبلغ وقدره:</div>
            <div class="value"><?php echo $amount_in_words; ?></div>
            <div class="value"></div>
            <div class="label" style="text-align: left;">Amount SR :</div>

            <div class="label">وذلك عن:</div>
            <div class="value"><?php echo htmlspecialchars($revenue['type_name']); ?></div>
            <div class="value"></div>
            <div class="label" style="text-align: left;">payment against :</div>

            <div class="label">طريقة الدفع:</div>
            <div class="value"><?php echo $revenue['payment_method']; ?></div>
             <div class="value"></div>
           <div class="label" style="text-align: left;">payment method :</div>

            <div class="label">كاش أو تحويل:</div>
            <div class="value"><?php echo $revenue['payment_method'] == 'cash' ? 'نقداً' : 'تحويل'; ?></div>
            <div class="value"></div>
            <div class="label" style="text-align: left;">Cash OR Trans :</div>
        </div>

        <div class="accountant-section">
            <div class="accountant">
                <div class="label">المحاسب:</div>
                <div class="value">طارق النشار</div>
            </div>
            <div class="stamp-section">
                <img src="../assets/img/stamp.png" alt="Company Stamp" class="stamp-image">
            </div>
            <div class="recipient">
                <div class="label">المستلم:</div>
                <div class="value"><?php echo htmlspecialchars($revenue['tenant_name']); ?></div>
            </div>
        </div>
    </div>
</body>
</html>
