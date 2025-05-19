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
