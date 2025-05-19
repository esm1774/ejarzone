<?php

/**
 * التحقق من CSRF token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * التحقق من صحة التاريخ
 */
function validateDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * تنسيق المبلغ
 */
function formatAmount($amount) {
    return number_format($amount, 2) . ' ريال';
}

/**
 * تسجيل الأخطاء
 */
function logError($error, $context = []) {
    $logFile = __DIR__ . '/../logs/error.log';
    $logDir = dirname($logFile);
    
    // إنشاء مجلد السجلات إذا لم يكن موجوداً
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    $logMessage = "[$timestamp] $error $contextStr\n";
    error_log($logMessage, 3, $logFile);
}

/**
 * تحويل التاريخ إلى التنسيق العربي
 */
function formatDateArabic($date) {
    $months = [
        'January' => 'يناير',
        'February' => 'فبراير',
        'March' => 'مارس',
        'April' => 'أبريل',
        'May' => 'مايو',
        'June' => 'يونيو',
        'July' => 'يوليو',
        'August' => 'أغسطس',
        'September' => 'سبتمبر',
        'October' => 'أكتوبر',
        'November' => 'نوفمبر',
        'December' => 'ديسمبر'
    ];
    
    $d = new DateTime($date);
    $month = $months[$d->format('F')];
    return $d->format('d') . ' ' . $month . ' ' . $d->format('Y');
}
