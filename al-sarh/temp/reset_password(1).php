<?php
require_once 'config/config.php';
require_once 'includes/database.php';

// بدء الجلسة إذا لم تكن موجودة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// التحقق من وجود الرمز
$token = $_GET['token'] ?? '';
if (empty($token)) {
    header('Location: login.php');
    exit;
}

// التحقق من صلاحية الرمز في قاعدة البيانات
try {
    $db = getDatabaseConnection();
    $stmt = $db->prepare("SELECT user_id FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()");
    $stmt->execute([$token]);
    
    if (!$stmt->fetch()) {
        $_SESSION['error'] = 'رابط إعادة تعيين كلمة المرور غير صالح أو منتهي الصلاحية';
        header('Location: forgot_password.php');
        exit;
    }
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .reset-password-container {
            max-width: 500px;
            width: 100%;
            margin: 2rem auto;
            padding: 0 15px;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .card-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem;
        }
        .card-header h3 {
            margin: 0;
            font-size: 1.5rem;
        }
        .password-requirements {
            display: none;
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.5rem;
        }
        .password-requirements.show {
            display: block;
        }
        .password-toggle {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
        }
        .validation-icon {
            position: absolute;
            left: 40px;
            top: 50%;
            transform: translateY(-50%);
            display: none;
            color: #198754;
        }
        .is-valid ~ .validation-icon {
            display: block;
            color: #198754;
        }
        .is-invalid ~ .validation-icon {
            display: block;
            color: #dc3545;
        }
    </style>
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
                    
                    <div class="form-floating mb-3">
                        <div class="position-relative">
                            <i class="bi bi-lock form-icon"></i>
                            <input type="password" 
                                   class="form-control" 
                                   id="password" 
                                   name="password" 
                                   placeholder="كلمة المرور الجديدة" 
                                   autocomplete="new-password"
                                   required>
                            <i class="bi bi-check-circle-fill validation-icon"></i>
                            <i class="bi bi-eye-slash password-toggle" onclick="togglePassword('password')"></i>
                            <div class="invalid-feedback"></div>
                        </div>
                        <label for="password">كلمة المرور الجديدة</label>
                        <div id="password-requirements" class="password-requirements">
                            <small>يجب أن تحتوي كلمة المرور على:</small>
                            <ul class="mb-0">
                                <li id="length-check">8 أحرف على الأقل</li>
                                <li id="uppercase-check">حرف كبير واحد على الأقل</li>
                                <li id="lowercase-check">حرف صغير واحد على الأقل</li>
                                <li id="number-check">رقم واحد على الأقل</li>
                                <li id="special-check">رمز خاص واحد على الأقل</li>
                            </ul>
                        </div>
                    </div>

                    <div class="form-floating mb-4">
                        <div class="position-relative">
                            <i class="bi bi-lock form-icon"></i>
                            <input type="password" 
                                   class="form-control" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   placeholder="تأكيد كلمة المرور الجديدة" 
                                   autocomplete="new-password"
                                   required>
                            <i class="bi bi-check-circle-fill validation-icon"></i>
                            <i class="bi bi-eye-slash password-toggle" onclick="togglePassword('confirm_password')"></i>
                            <div class="invalid-feedback"></div>
                        </div>
                        <label for="confirm_password">تأكيد كلمة المرور الجديدة</label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mb-3" id="submitBtn">
                        <i class="bi bi-check-circle me-2"></i>تغيير كلمة المرور
                    </button>

                    <div class="text-center">
                        <a href="login.php" class="text-decoration-none">
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

            function showAlert(type, message) {
                const alertContainer = document.getElementById('alert-container');
                const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
                alertContainer.innerHTML = `
                    <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
            }

            function updateFieldStatus(field, isValid, message = '') {
                field.classList.remove('is-valid', 'is-invalid');
                field.classList.add(isValid ? 'is-valid' : 'is-invalid');
                
                const feedback = field.parentElement.querySelector('.invalid-feedback');
                if (feedback) {
                    feedback.textContent = message;
                }
            }

            function updateRequirement(id, isValid) {
                const element = document.getElementById(id);
                if (element) {
                    element.style.color = isValid ? '#198754' : '#6c757d';
                    element.innerHTML = isValid ? 
                        `<i class="bi bi-check-circle-fill me-1"></i>${element.textContent}` :
                        element.textContent.replace(/<i.*?>/i, '');
                }
            }

            function validatePassword(value) {
                const hasUpperCase = /[A-Z]/.test(value);
                const hasLowerCase = /[a-z]/.test(value);
                const hasNumbers = /\d/.test(value);
                const hasSpecialChar = /[!@#$%^&*(),.?":{}|<>]/.test(value);
                const hasValidLength = value.length >= 8;

                updateRequirement('length-check', hasValidLength);
                updateRequirement('uppercase-check', hasUpperCase);
                updateRequirement('lowercase-check', hasLowerCase);
                updateRequirement('number-check', hasNumbers);
                updateRequirement('special-check', hasSpecialChar);

                return hasUpperCase && hasLowerCase && hasNumbers && hasSpecialChar && hasValidLength;
            }

            password.addEventListener('input', function() {
                const isValid = validatePassword(this.value);
                updateFieldStatus(this, isValid, isValid ? '' : 'كلمة المرور لا تستوفي المتطلبات');
                
                // التحقق من تطابق كلمتي المرور
                if (confirmPassword.value) {
                    const isMatch = confirmPassword.value === this.value;
                    updateFieldStatus(confirmPassword, isMatch, isMatch ? '' : 'كلمتا المرور غير متطابقتين');
                }
            });

            password.addEventListener('focus', function() {
                document.getElementById('password-requirements').classList.add('show');
            });

            password.addEventListener('blur', function() {
                document.getElementById('password-requirements').classList.remove('show');
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
                        // إعادة التوجيه بعد ثانيتين
                        setTimeout(() => {
                            window.location.href = 'login.php';
                        }, 2000);
                    } else {
                        showAlert('danger', result.message);
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnText;
                    }
                } catch (error) {
                    showAlert('danger', 'حدث خطأ أثناء معالجة الطلب');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                }
            });
        });

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
    </script>
</body>
</html>
