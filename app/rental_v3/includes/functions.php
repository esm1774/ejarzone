<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// دالة لإضافة رسالة إلى جلسة المستخدم
function addMessage($type, $message) {
    if (!isset($_SESSION['messages'])) {
        $_SESSION['messages'] = [];
    }
    $_SESSION['messages'][] = [
        'type' => $type,
        'text' => $message
    ];
}

// دالة لعرض الرسائل المخزنة في الجلسة
function displayMessages() {
    if (isset($_SESSION['messages']) && !empty($_SESSION['messages'])) {
        foreach ($_SESSION['messages'] as $message) {
            $alertClass = 'alert-info';
            if ($message['type'] == 'success') {
                $alertClass = 'alert-success';
            } elseif ($message['type'] == 'error') {
                $alertClass = 'alert-danger';
            } elseif ($message['type'] == 'warning') {
                $alertClass = 'alert-warning';
            }
            
            echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show" role="alert">';
            echo htmlspecialchars($message['text']);
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            echo '</div>';
        }
        // مسح الرسائل بعد عرضها
        $_SESSION['messages'] = [];
    }
}

// دالة لتنظيف وتأمين المدخلات
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// دالة لتنسيق التاريخ
function formatDate($date, $format = 'Y-m-d') {
    return date($format, strtotime($date));
}

// دالة لتنسيق المبلغ
function formatAmount($amount, $decimals = 2) {
    return number_format($amount, $decimals, '.', ',');
}

/**
 * حساب تاريخ الاستحقاق القادم بناءً على تاريخ بداية العقد ورقم القسط
 * 
 * @param string $contract_start_date تاريخ بداية العقد
 * @param string $rent_type نوع الإيجار (يومي، أسبوعي، شهري، ربع سنوي، نصف سنوي، سنوي)
 * @param string $contract_end_date تاريخ نهاية العقد
 * @param int $installment_number رقم القسط الحالي
 * @return string|null تاريخ الاستحقاق القادم أو null إذا كان آخر قسط
 */
function calculateNextDueDate($contract_start_date, $rent_type, $contract_end_date, $installment_number) {
    // تحويل التواريخ إلى كائنات DateTime
    $start_date = new DateTime($contract_start_date);
    $end_date = new DateTime($contract_end_date);
    
    // حساب تاريخ القسط القادم مباشرة
    $next_due_date = clone $start_date;
    
    // حساب تاريخ القسط القادم بناءً على رقم القسط الحالي
    for ($i = 0; $i < $installment_number; $i++) {
        switch ($rent_type) {
            case 'يومي':
                $next_due_date->modify('+1 day');
                break;
            case 'أسبوعي':
                $next_due_date->modify('+1 week');
                break;
            case 'شهري':
                $next_due_date->modify('+1 month');
                break;
            case 'ربع سنوي':
                $next_due_date->modify('+3 months');
                break;
            case 'نصف سنوي':
                $next_due_date->modify('+6 months');
                break;
            case 'سنوي':
                $next_due_date->modify('+1 year');
                break;
            default:
                return null;
        }
    }

    // التحقق من أن تاريخ القسط القادم لا يتجاوز تاريخ نهاية العقد
    if ($next_due_date > $end_date) {
        return null;
    }

    return $next_due_date->format('Y-m-d');
}
