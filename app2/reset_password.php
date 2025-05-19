<?php
require_once 'config/config.php';
require_once 'includes/database.php';

// بدء الجلسة إذا لم تكن موجودة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// التحقق من وجود الرمز
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
if (empty($token)) {
    $_SESSION['error'] = 'رمز إعادة تعيين كلمة المرور مفقود';
    header('Location: forgot_password.php');
    exit;
}

// التحقق من صلاحية الرمز في قاعدة البيانات
try {
    $db = getDatabaseConnection();
    $stmt = $db->prepare("
        SELECT user_id, reset_token_expiry 
        FROM users 
        WHERE reset_token = ? 
        AND reset_token_expiry > NOW() 
        AND reset_token IS NOT NULL
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        // تسجيل الخطأ للتشخيص
        error_log("محاولة استخدام رمز غير صالح: " . $token);
        $_SESSION['error'] = 'رابط إعادة تعيين كلمة المرور غير صالح أو منتهي الصلاحية';
        header('Location: forgot_password.php');
        exit;
    }
    
    // حفظ معرف المستخدم في الجلسة للاستخدام لاحقاً
    $_SESSION['reset_user_id'] = $user['user_id'];
    
} catch (Exception $e) {
    error_log("خطأ في التحقق من رمز إعادة تعيين كلمة المرور: " . $e->getMessage());
    $_SESSION['error'] = 'حدث خطأ أثناء التحقق من صلاحية الرابط';
    header('Location: forgot_password.php');
    exit;
}

// إنشاء CSRF token إذا لم يكن موجوداً
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعادة تعيين كلمة المرور - الصرح</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body>
    <div class="reset-password-container">
        <div class="card">
            <div class="card-header text-center">
                <h3><i class="bi bi-shield-lock-fill me-2"></i>إعادة تعيين كلمة المرور</h3>
            </div>
            <div class="card-body">
                <div id="alert-container"></div>
                <form id="resetPasswordForm" method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="form-group">
                        <label for="password" class="form-label mb-2">كلمة المرور الجديدة</label>
                        <div class="input-group">
                            <i class="bi bi-lock form-icon"></i>
                            <input type="password" 
                                   class="form-control" 
                                   id="password" 
                                   name="password" 
                                   required>
                            <i class="bi bi-eye-slash password-toggle" 
                               onclick="togglePassword('password')" 
                               title="إظهار/إخفاء كلمة المرور"></i>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="password-requirements mt-2">
                            <div class="requirement" data-requirement="length">
                                <i class="bi bi-circle"></i>
                                8 أحرف على الأقل
                            </div>
                            <div class="requirement" data-requirement="uppercase">
                                <i class="bi bi-circle"></i>
                                حرف كبير واحد على الأقل
                            </div>
                            <div class="requirement" data-requirement="lowercase">
                                <i class="bi bi-circle"></i>
                                حرف صغير واحد على الأقل
                            </div>
                            <div class="requirement" data-requirement="number">
                                <i class="bi bi-circle"></i>
                                رقم واحد على الأقل
                            </div>
                            <div class="requirement" data-requirement="special">
                                <i class="bi bi-circle"></i>
                                رمز خاص واحد على الأقل (!@#$%^&*(),.?":{}|<>)
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password" class="form-label mb-2">تأكيد كلمة المرور</label>
                        <div class="input-group">
                            <i class="bi bi-lock form-icon"></i>
                            <input type="password" 
                                   class="form-control" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   required>
                            <i class="bi bi-eye-slash password-toggle" 
                               onclick="togglePassword('confirm_password')" 
                               title="إظهار/إخفاء كلمة المرور"></i>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mb-3" id="submitBtn">
                        <i class="bi bi-check2-circle me-2"></i>تغيير كلمة المرور
                    </button>

                    <div class="text-center">
                        <a href="login.php" class="back-link">
                            <i class="bi bi-arrow-right me-1"></i>
                            العودة إلى تسجيل الدخول
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            const form = document.getElementById('resetPasswordForm');

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
                const icon = parent.querySelector('.validation-icon');

                field.classList.remove('is-valid', 'is-invalid');
                field.classList.add(isValid ? 'is-valid' : 'is-invalid');

                if (icon) {
                    icon.classList.remove('bi-check-circle-fill', 'bi-x-circle-fill');
                    icon.classList.add(isValid ? 'bi-check-circle-fill' : 'bi-x-circle-fill');
                }

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

            password.addEventListener('input', function() {
                const isValid = validatePassword(this.value);
                updateFieldStatus(this, isValid);
                
                if (confirmPassword.value) {
                    const isMatch = confirmPassword.value === this.value;
                    updateFieldStatus(confirmPassword, isMatch, isMatch ? '' : 'كلمتا المرور غير متطابقتين');
                }
            });

            password.addEventListener('focus', function() {
                document.querySelector('.password-requirements').classList.add('show');
            });

            confirmPassword.addEventListener('input', function() {
                const isValid = this.value === password.value;
                updateFieldStatus(this, isValid, isValid ? '' : 'كلمتا المرور غير متطابقتين');
            });

            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const submitBtn = document.getElementById('submitBtn');
                const originalBtnText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>جاري المعالجة...';
                
                try {
                    const formData = new FormData(this);
                    const response = await fetch('reset_password_handler.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        showAlert('success', result.message);
                        setTimeout(() => {
                            window.location.href = 'login.php';
                        }, 2000);
                    } else {
                        showAlert('danger', result.message || 'حدث خطأ أثناء إعادة تعيين كلمة المرور');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnText;
                    }
                } catch (error) {
                    showAlert('danger', 'حدث خطأ أثناء معالجة الطلب');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                }
            });

            function showAlert(type, message) {
                const alertContainer = document.getElementById('alert-container');
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
                alertDiv.innerHTML = `
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                alertContainer.innerHTML = '';
                alertContainer.appendChild(alertDiv);
            }

            // عرض رسائل الخطأ من PHP
            const errorMessage = document.querySelector('.alert-danger');
            if (errorMessage) {
                setTimeout(() => {
                    errorMessage.remove();
                }, 5000);
            }
        });
    </script>
</body>
</html>
