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
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Cairo', sans-serif !important;
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
            font-weight: 600;
        }
        .card-body {
            padding: 2rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }
        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
            color: #495057;
        }
        .form-control {
            height: calc(3rem + 2px);
            padding: 0.75rem 3rem 0.75rem 1rem;
            font-size: 1rem;
            border-radius: 8px;
            border: 1px solid #ced4da;
            transition: all 0.2s ease-in-out;
        }
        .form-control:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.15);
        }
        .input-group {
            position: relative;
        }
        .input-icon {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            z-index: 4;
        }
        .password-toggle {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
            z-index: 4;
            transition: color 0.2s;
        }
        .password-toggle:hover {
            color: #007bff;
        }
        .btn-primary {
            height: 3rem;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.2s;
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            font-size: 1rem;
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            background: linear-gradient(135deg, #0056b3, #004094);
        }
        .alert {
            border-radius: 8px;
            font-weight: 500;
        }
        .password-requirements {
            margin-top: 0.5rem;
            font-size: 0.875rem;
            color: #6c757d;
        }
        .requirement {
            margin-bottom: 0.25rem;
        }
        .requirement i {
            margin-left: 0.5rem;
            width: 16px;
            text-align: center;
        }
        .requirement.valid {
            color: #198754;
        }
        .requirement.invalid {
            color: #dc3545;
        }
        .back-link {
            color: #6c757d;
            text-decoration: none;
            transition: color 0.2s;
            font-weight: 500;
        }
        .back-link:hover {
            color: #007bff;
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
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php 
                            echo $_SESSION['error'];
                            unset($_SESSION['error']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form id="resetPasswordForm" method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="form-group">
                        <label for="password" class="form-label">كلمة المرور الجديدة</label>
                        <div class="input-group">
                            <i class="bi bi-lock input-icon"></i>
                            <input type="password" 
                                   class="form-control" 
                                   id="password" 
                                   name="password" 
                                   required>
                            <i class="bi bi-eye-slash password-toggle" 
                               onclick="togglePassword('password')" 
                               title="إظهار/إخفاء كلمة المرور"></i>
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
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password" class="form-label">تأكيد كلمة المرور</label>
                        <div class="input-group">
                            <i class="bi bi-lock input-icon"></i>
                            <input type="password" 
                                   class="form-control" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   required>
                            <i class="bi bi-eye-slash password-toggle" 
                               onclick="togglePassword('confirm_password')" 
                               title="إظهار/إخفاء كلمة المرور"></i>
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

            function updateRequirement(requirement, isValid) {
                const element = document.querySelector(`.requirement[data-requirement="${requirement}"]`);
                if (element) {
                    element.classList.remove('valid', 'invalid');
                    element.classList.add(isValid ? 'valid' : 'invalid');
                }
            }

            function validatePassword(value) {
                const hasUpperCase = /[A-Z]/.test(value);
                const hasLowerCase = /[a-z]/.test(value);
                const hasNumbers = /\d/.test(value);
                const hasValidLength = value.length >= 8;

                updateRequirement('length', hasValidLength);
                updateRequirement('uppercase', hasUpperCase);
                updateRequirement('lowercase', hasLowerCase);
                updateRequirement('number', hasNumbers);

                return hasUpperCase && hasLowerCase && hasNumbers && hasValidLength;
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
