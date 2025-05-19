<?php
// بدء الجلسة فقط إذا لم تكن موجودة
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/config.php';
require_once 'includes/database.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// إنشاء اتصال قاعدة البيانات
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );
} catch (PDOException $e) {
    die("خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage());
}

$error = '';
$success = '';

// معالجة نموذج تغيير كلمة المرور
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // التحقق من صحة المدخلات
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = "جميع الحقول مطلوبة";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "كلمة المرور الجديدة وتأكيدها غير متطابقين";
    } elseif (strlen($newPassword) < 8) {
        $error = "يجب أن تكون كلمة المرور الجديدة 8 أحرف على الأقل";
    } else {
        // التحقق من كلمة المرور الحالية
        $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($currentPassword, $user['password'])) {
            $error = "كلمة المرور الحالية غير صحيحة";
        } else {
            // تحديث كلمة المرور
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ?, force_password_change = 0 WHERE user_id = ?");
            
            if ($stmt->execute([$hashedPassword, $_SESSION['user_id']])) {
                // إعادة تعيين علامة تغيير كلمة المرور في الجلسة
                unset($_SESSION['force_password_change']);
                
                // تسجيل الخروج
                session_unset();
                session_destroy();
                
                // تعيين رسالة نجاح
                session_start();
                $_SESSION['success_message'] = "تم تغيير كلمة المرور بنجاح. يرجى تسجيل الدخول باستخدام كلمة المرور الجديدة.";
                
                // إعادة التوجيه إلى صفحة تسجيل الدخول
                header('Location: login.php');
                exit;
            } else {
                $error = "حدث خطأ أثناء تحديث كلمة المرور";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تغيير كلمة المرور - الصرح</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center">تغيير كلمة المرور</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="change_password.php">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">كلمة المرور الحالية</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">كلمة المرور الجديدة</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <div class="form-text">يجب أن تكون كلمة المرور 8 أحرف على الأقل</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">تأكيد كلمة المرور الجديدة</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">تغيير كلمة المرور</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            const form = document.getElementById('changePasswordForm');

            function togglePassword(inputId) {
                const input = document.getElementById(inputId);
                const icon = input.parentElement.querySelector('.password-toggle');
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                } else {
                    input.type = 'password';
                    icon.classList.remove('bi-eye');
                    icon.classList.add('bi-eye-slash');
                }
            }

            function updateFieldStatus(field, isValid, message = '') {
                const parent = field.parentElement;
                const feedback = parent.querySelector('.invalid-feedback');
                
                field.classList.remove('is-valid', 'is-invalid');
                field.classList.add(isValid ? 'is-valid' : 'is-invalid');
                
                if (feedback) {
                    feedback.textContent = message;
                }
            }

            function updateRequirement(requirement, isValid) {
                const element = document.querySelector(`.requirement[data-requirement="${requirement}"]`);
                if (element) {
                    const icon = element.querySelector('i');
                    icon.classList.remove('bi-circle', 'bi-check-circle-fill', 'bi-x-circle-fill');
                    icon.classList.add(isValid ? 'bi-check-circle-fill' : 'bi-x-circle-fill');
                    element.classList.remove('valid', 'invalid');
                    element.classList.add(isValid ? 'valid' : 'invalid');
                }
            }

            function validatePassword(value) {
                const hasUpperCase = /[A-Z]/.test(value);
                const hasLowerCase = /[a-z]/.test(value);
                const hasNumbers = /\d/.test(value);
                const hasSpecialChar = /[!@#$%^&*(),.?":{}|<>]/.test(value);
                const hasValidLength = value.length >= 8;

                updateRequirement('length', hasValidLength);
                updateRequirement('uppercase', hasUpperCase);
                updateRequirement('lowercase', hasLowerCase);
                updateRequirement('number', hasNumbers);
                updateRequirement('special', hasSpecialChar);

                return hasUpperCase && hasLowerCase && hasNumbers && hasValidLength && hasSpecialChar;
            }

            newPassword.addEventListener('input', function() {
                const isValid = validatePassword(this.value);
                updateFieldStatus(this, isValid);
                
                if (confirmPassword.value) {
                    const isMatch = confirmPassword.value === this.value;
                    updateFieldStatus(confirmPassword, isMatch, isMatch ? '' : 'كلمتا المرور غير متطابقتين');
                }
            });

            newPassword.addEventListener('focus', function() {
                document.querySelector('.password-requirements').classList.add('show');
            });

            confirmPassword.addEventListener('input', function() {
                const isValid = this.value === newPassword.value;
                updateFieldStatus(this, isValid, isValid ? '' : 'كلمتا المرور غير متطابقتين');
            });

            // إخفاء رسائل النجاح بعد 5 ثواني
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                setTimeout(() => {
                    successAlert.remove();
                }, 5000);
            }
        });
    </script>
</body>
</html>
