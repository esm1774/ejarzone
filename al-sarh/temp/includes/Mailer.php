<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';

class Mailer {
    private static $instance = null;
    private $mailer;

    private function __construct() {
        try {
            $this->mailer = new PHPMailer(true);
            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->Encoding = 'base64';
            
            // إعداد SMTP
            $this->mailer->isSMTP();
            $this->mailer->SMTPDebug = SMTP_DEBUG;
            $this->mailer->Host = SMTP_HOST;
            $this->mailer->Port = SMTP_PORT;
            $this->mailer->SMTPAuth = SMTP_AUTH;
            $this->mailer->Username = SMTP_USER;
            $this->mailer->Password = SMTP_PASS;
            
            // تحديد نوع التشفير
            if (defined('SMTP_ENCRYPTION')) {
                if (SMTP_ENCRYPTION === 'ssl') {
                    $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                } elseif (SMTP_ENCRYPTION === 'tls') {
                    $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                }
            }
            
            // إعداد المرسل
            $this->mailer->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            
            error_log("تم تهيئة PHPMailer بنجاح مع الإعدادات التالية:");
            error_log("Host: " . SMTP_HOST);
            error_log("Port: " . SMTP_PORT);
            error_log("Username: " . SMTP_USER);
            error_log("From Email: " . SMTP_FROM_EMAIL);
            error_log("Encryption: " . (defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'none'));
        } catch (Exception $e) {
            error_log("خطأ في تهيئة PHPMailer: " . $e->getMessage());
            throw new Exception("فشل في تهيئة نظام البريد: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function sendPasswordReset($to, $resetLink) {
        try {
            error_log("محاولة إرسال بريد إعادة تعيين كلمة المرور إلى: " . $to);
            
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to);
            
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'إعادة تعيين كلمة المرور';
            
            // قالب HTML للبريد الإلكتروني
            $this->mailer->Body = $this->getPasswordResetTemplate($resetLink);
            $this->mailer->AltBody = strip_tags(str_replace('<br>', "\n", $this->mailer->Body));
            
            $result = $this->mailer->send();
            error_log("تم إرسال البريد بنجاح");
            return $result;
            
        } catch (Exception $e) {
            error_log("خطأ في إرسال البريد: " . $e->getMessage());
            error_log("Mailer Error: " . $this->mailer->ErrorInfo);
            throw new Exception('فشل في إرسال بريد إعادة تعيين كلمة المرور: ' . $e->getMessage());
        }
    }

    private function getPasswordResetTemplate($resetLink) {
        return '
        <div dir="rtl" style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
            <h2 style="color: #333;">إعادة تعيين كلمة المرور</h2>
            <p>لقد تلقينا طلباً لإعادة تعيين كلمة المرور الخاصة بحسابك.</p>
            <p>إذا لم تقم بطلب إعادة تعيين كلمة المرور، يمكنك تجاهل هذا البريد الإلكتروني.</p>
            <p>لإعادة تعيين كلمة المرور، يرجى النقر على الرابط أدناه:</p>
            <p style="margin: 20px 0;">
                <a href="' . htmlspecialchars($resetLink) . '" 
                   style="background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
                    إعادة تعيين كلمة المرور
                </a>
            </p>
            <p>أو نسخ ولصق الرابط التالي في متصفحك:</p>
            <p style="background-color: #f5f5f5; padding: 10px; word-break: break-all;">
                ' . htmlspecialchars($resetLink) . '
            </p>
            <p style="color: #666; font-size: 0.9em;">
                هذا الرابط صالح لمدة 24 ساعة فقط.<br>
                إذا انتهت صلاحية الرابط، يمكنك طلب رابط جديد من صفحة نسيت كلمة المرور.
            </p>
        </div>';
    }

    // منع نسخ الكائن
    private function __clone() {}

    // منع إعادة إنشاء الكائن من خلال unserialization
    public function __wakeup() {}
}
