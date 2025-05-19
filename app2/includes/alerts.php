<?php
/**
 * عرض رسائل النظام للمستخدم
 */

if (!function_exists('showAlerts')) {
    function showAlerts() {
        if (isset($_SESSION['messages']) && !empty($_SESSION['messages'])) {
            foreach ($_SESSION['messages'] as $type => $messages) {
                foreach ($messages as $message) {
                    $alertClass = '';
                    $icon = '';
                    
                    switch ($type) {
                        case 'success':
                            $alertClass = 'alert-success';
                            $icon = 'bi-check-circle-fill';
                            break;
                        case 'error':
                            $alertClass = 'alert-danger';
                            $icon = 'bi-exclamation-triangle-fill';
                            break;
                        case 'warning':
                            $alertClass = 'alert-warning';
                            $icon = 'bi-exclamation-circle-fill';
                            break;
                        case 'info':
                            $alertClass = 'alert-info';
                            $icon = 'bi-info-circle-fill';
                            break;
                    }
                    
                    echo "<div class='alert {$alertClass} alert-dismissible fade show' role='alert'>";
                    echo "<i class='bi {$icon} me-2'></i>";
                    echo htmlspecialchars($message);
                    echo "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>";
                    echo "</div>";
                }
            }
            // مسح الرسائل بعد عرضها
            unset($_SESSION['messages']);
        }
    }
}

if (!function_exists('addMessage')) {
    function addMessage($type, $message) {
        if (!isset($_SESSION['messages'])) {
            $_SESSION['messages'] = [];
        }
        if (!isset($_SESSION['messages'][$type])) {
            $_SESSION['messages'][$type] = [];
        }
        $_SESSION['messages'][$type][] = $message;
    }
}
