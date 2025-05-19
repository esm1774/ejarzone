<?php
// تنزيل وإعداد PHPMailer
$phpmailerDir = __DIR__ . '/vendor/phpmailer/phpmailer';
if (!file_exists($phpmailerDir)) {
    mkdir($phpmailerDir . '/src', 0777, true);
}

// تنزيل الملفات الأساسية
$files = [
    'Exception.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/Exception.php',
    'PHPMailer.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/PHPMailer.php',
    'SMTP.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/SMTP.php'
];

foreach ($files as $filename => $url) {
    $content = file_get_contents($url);
    if ($content !== false) {
        file_put_contents($phpmailerDir . '/src/' . $filename, $content);
        echo "تم تنزيل $filename بنجاح\n";
    } else {
        echo "فشل في تنزيل $filename\n";
    }
}

echo "تم الانتهاء من تنزيل PHPMailer\n";
