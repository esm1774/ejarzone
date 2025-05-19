<?php
require_once 'config/config.php';

// بدء الجلسة إذا لم تكن موجودة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
    <title>تسجيل حساب جديد - الصرح</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .register-container {
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
        .card-body {
            padding: 2rem;
        }
        .form-control {
            border-radius: 8px;
            padding: 0.75rem 2.5rem 0.75rem 2.5rem;
            border: 1px solid #ced4da;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.15);
        }
        .form-control.is-invalid {
            background-image: none;
            border-color: #dc3545;
        }
        .form-control.is-valid {
            background-image: none;
            border-color: #198754;
        }
        .validation-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            display: none;
            z-index: 4;
        }
        .validation-icon.valid {
            color: #198754;
            display: block;
        }
        .validation-icon.invalid {
            color: #dc3545;
            display: block;
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
        .requirement {
            margin-bottom: 0.25rem;
        }
        .requirement i {
            margin-left: 0.5rem;
        }
        .requirement.valid {
            color: #198754;
        }
        .requirement.invalid {
            color: #dc3545;
        }
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        .btn-primary {
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: 8px;
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #0056b3, #004094);
            transform: translateY(-1px);
        }
        .btn-primary:disabled {
            cursor: not-allowed;
            opacity: 0.8;
        }
        .input-group-text {
            background-color: transparent;
            border-right: none;
            cursor: pointer;
        }
        .form-control.password {
            border-right: 1px solid #ced4da;
            border-top-right-radius: 8px !important;
            border-bottom-right-radius: 8px !important;
        }
        .invalid-feedback {
            font-size: 0.85rem;
            margin-top: 0.5rem;
        }
        #alert-container .alert {
            display: none;
            animation: fadeIn 0.3s ease-in;
            border-radius: 8px;
            border: none;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        #alert-container .alert.show {
            display: block;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .login-link {
            color: #007bff;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .login-link:hover {
            color: #0056b3;
            text-decoration: underline;
        }
        .form-floating {
            position: relative;
            margin-bottom: 1rem;
        }
        .password-toggle {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
            z-index: 4;
        }
        .password-toggle:hover {
            color: #495057;
        }
        .form-icon {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            z-index: 4;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="card">
            <div class="card-header text-center">
                <h3><i class="bi bi-person-plus-fill me-2"></i>تسجيل حساب جديد</h3>
            </div>
            <div class="card-body">
                <div id="alert-container"></div>
                <form id="registerForm" method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="form-floating mb-3">
                        <div class="position-relative">
                            <i class="bi bi-person form-icon"></i>
                            <input type="text" class="form-control" id="username" name="username" placeholder="اسم المستخدم" required>
                            <i class="bi bi-check-circle-fill validation-icon"></i>
                        </div>
                        <div class="invalid-feedback"></div>
                        <div class="password-requirements" id="username-requirements">
                            <div class="requirement" data-requirement="length">
                                <i class="bi bi-circle"></i>
                                يجب أن يكون الطول بين 3 و 20 حرفاً
                            </div>
                            <div class="requirement" data-requirement="characters">
                                <i class="bi bi-circle"></i>
                                يجب أن يحتوي على حروف وأرقام فقط
                            </div>
                        </div>
                    </div>

                    <div class="form-floating mb-3">
                        <div class="position-relative">
                            <i class="bi bi-person-badge form-icon"></i>
                            <input type="text" class="form-control" id="full_name" name="full_name" placeholder="الاسم الكامل" required>
                            <i class="bi bi-check-circle-fill validation-icon"></i>
                        </div>
                        <div class="invalid-feedback"></div>
                    </div>

                    <div class="form-floating mb-3">
                        <div class="position-relative">
                            <i class="bi bi-envelope form-icon"></i>
                            <input type="email" class="form-control" id="email" name="email" placeholder="البريد الإلكتروني" required>
                            <i class="bi bi-check-circle-fill validation-icon"></i>
                        </div>
                        <div class="invalid-feedback"></div>
                    </div>

                    <div class="form-floating mb-3">
                        <div class="position-relative">
                            <i class="bi bi-lock form-icon"></i>
                            <input type="password" class="form-control" id="password" name="password" placeholder="كلمة المرور" required>
                            <i class="bi bi-check-circle-fill validation-icon"></i>
                            <i class="bi bi-eye-slash password-toggle" onclick="togglePassword('password')"></i>
                        </div>
                        <div class="invalid-feedback"></div>
                        <div class="password-requirements" id="password-requirements">
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
                                رمز خاص واحد على الأقل
                            </div>
                        </div>
                    </div>

                    <div class="form-floating mb-4">
                        <div class="position-relative">
                            <i class="bi bi-lock form-icon"></i>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="تأكيد كلمة المرور" required>
                            <i class="bi bi-check-circle-fill validation-icon"></i>
                            <i class="bi bi-eye-slash password-toggle" onclick="togglePassword('confirm_password')"></i>
                        </div>
                        <div class="invalid-feedback"></div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mb-3" id="submitBtn">
                        <i class="bi bi-person-plus me-2"></i>تسجيل
                    </button>

                    <div class="text-center">
                        <a href="login.php" class="login-link">
                            <i class="bi bi-box-arrow-in-left me-1"></i>
                            لديك حساب بالفعل؟ سجل دخول
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // التحقق من تكرار اسم المستخدم
        async function checkUsernameExists(username) {
            try {
                const response = await fetch('check_username.php?username=' + encodeURIComponent(username));
                const data = await response.json();
                return data.exists;
            } catch (error) {
                console.error('Error checking username:', error);
                return false;
            }
        }

        // التحقق من اسم المستخدم
        function validateUsername(username) {
            const lengthValid = username.length >= 3 && username.length <= 20;
            const charactersValid = /^[a-zA-Z0-9]+$/.test(username);
            
            updateRequirement('username', 'length', lengthValid);
            updateRequirement('username', 'characters', charactersValid);
            
            return lengthValid && charactersValid;
        }

        // التحقق من البريد الإلكتروني
        function validateEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        // التحقق من تكرار البريد الإلكتروني
        async function checkEmailExists(email) {
            try {
                const response = await fetch('check_email.php?email=' + encodeURIComponent(email));
                const data = await response.json();
                return data.exists;
            } catch (error) {
                console.error('خطأ في التحقق من البريد الإلكتروني:', error);
                return false;
            }
        }

        // التحقق من كلمة المرور
        function validatePassword(password) {
            const lengthValid = password.length >= 8;
            const uppercaseValid = /[A-Z]/.test(password);
            const lowercaseValid = /[a-z]/.test(password);
            const numberValid = /[0-9]/.test(password);
            const specialValid = /[^A-Za-z0-9]/.test(password);
            
            updateRequirement('password', 'length', lengthValid);
            updateRequirement('password', 'uppercase', uppercaseValid);
            updateRequirement('password', 'lowercase', lowercaseValid);
            updateRequirement('password', 'number', numberValid);
            updateRequirement('password', 'special', specialValid);
            
            return lengthValid && uppercaseValid && lowercaseValid && numberValid && specialValid;
        }

        // تحديث متطلبات الحقل
        function updateRequirement(field, requirement, isValid) {
            const requirementElement = document.querySelector(`#${field}-requirements .requirement[data-requirement="${requirement}"]`);
            if (requirementElement) {
                const icon = requirementElement.querySelector('i');
                requirementElement.classList.toggle('valid', isValid);
                requirementElement.classList.toggle('invalid', !isValid);
                icon.className = isValid ? 'bi bi-check-circle-fill' : 'bi bi-x-circle-fill';
            }
        }

        // تحديث حالة الحقل
        function updateFieldStatus(input, isValid, message = '') {
            const validationIcon = input.parentElement.querySelector('.validation-icon');
            const feedback = input.parentElement.nextElementSibling;
            
            input.classList.toggle('is-valid', isValid);
            input.classList.toggle('is-invalid', !isValid);
            
            if (validationIcon) {
                validationIcon.className = `bi ${isValid ? 'bi-check-circle-fill' : 'bi-x-circle-fill'} validation-icon ${isValid ? 'valid' : 'invalid'}`;
            }
            
            if (feedback) {
                feedback.textContent = message;
            }

            const requirements = document.getElementById(`${input.id}-requirements`);
            if (requirements) {
                requirements.classList.toggle('show', input === document.activeElement || !isValid);
            }
        }

        // إضافة مستمعي الأحداث
        document.addEventListener('DOMContentLoaded', function() {
            const username = document.getElementById('username');
            const email = document.getElementById('email');
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            let usernameCheckTimeout;
            let emailCheckTimeout;

            username.addEventListener('input', function() {
                const isValid = validateUsername(this.value);
                updateFieldStatus(this, isValid, isValid ? '' : 'اسم المستخدم غير صالح');
                
                // التحقق من تكرار اسم المستخدم
                clearTimeout(usernameCheckTimeout);
                if (isValid) {
                    usernameCheckTimeout = setTimeout(async () => {
                        const exists = await checkUsernameExists(this.value);
                        updateFieldStatus(this, !exists, exists ? 'اسم المستخدم مستخدم بالفعل' : '');
                    }, 500);
                }
            });

            username.addEventListener('focus', function() {
                document.getElementById('username-requirements').classList.add('show');
            });

            username.addEventListener('blur', function() {
                if (!this.value || this.classList.contains('is-invalid')) {
                    document.getElementById('username-requirements').classList.remove('show');
                }
            });

            email.addEventListener('input', async function() {
                const emailInput = this;
                const validationIcon = emailInput.nextElementSibling;
                const feedbackDiv = emailInput.parentElement.nextElementSibling;

                // التحقق من صحة تنسيق البريد الإلكتروني
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                const isValidFormat = emailRegex.test(emailInput.value);

                if (!isValidFormat) {
                    emailInput.classList.add('is-invalid');
                    emailInput.classList.remove('is-valid');
                    validationIcon.classList.add('bi-x-circle-fill', 'invalid');
                    validationIcon.classList.remove('bi-check-circle-fill', 'valid');
                    feedbackDiv.textContent = 'يرجى إدخال بريد إلكتروني صحيح';
                    return;
                }

                // التحقق من وجود البريد الإلكتروني في قاعدة البيانات
                const exists = await checkEmailExists(emailInput.value);
                if (exists) {
                    emailInput.classList.add('is-invalid');
                    emailInput.classList.remove('is-valid');
                    validationIcon.classList.add('bi-x-circle-fill', 'invalid');
                    validationIcon.classList.remove('bi-check-circle-fill', 'valid');
                    feedbackDiv.textContent = 'هذا البريد الإلكتروني مستخدم بالفعل';
                } else {
                    emailInput.classList.add('is-valid');
                    emailInput.classList.remove('is-invalid');
                    validationIcon.classList.add('bi-check-circle-fill', 'valid');
                    validationIcon.classList.remove('bi-x-circle-fill', 'invalid');
                    feedbackDiv.textContent = '';
                }
            });

            password.addEventListener('input', function() {
                const isValid = validatePassword(this.value);
                updateFieldStatus(this, isValid);
                
                // تحديث حالة تأكيد كلمة المرور
                if (confirmPassword.value) {
                    const confirmValid = this.value === confirmPassword.value;
                    updateFieldStatus(confirmPassword, confirmValid, confirmValid ? '' : 'كلمتا المرور غير متطابقتين');
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
        });

        document.getElementById('registerForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('submitBtn');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>جاري التسجيل...';
            
            try {
                const formData = new FormData(this);
                const response = await fetch('register_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error(`خطأ في الاستجابة: ${response.status}`);
                }
                
                const result = await response.json();
                console.log('Server Response:', result); // للتتبع
                
                if (result.success) {
                    // إخفاء أي رسائل خطأ سابقة
                    resetForm();
                    
                    // عرض رسالة النجاح
                    showAlert('success', result.message);
                    
                    // إعادة التوجيه بعد ثانيتين
                    setTimeout(() => {
                        window.location.href = result.redirect;
                    }, 2000);
                } else {
                    if (result.errors) {
                        // إعادة تعيين حالة النموذج أولاً
                        resetForm();
                        
                        // عرض أخطاء التحقق
                        Object.entries(result.errors).forEach(([field, message]) => {
                            const input = document.getElementById(field);
                            if (input) {
                                input.classList.add('is-invalid');
                                const feedback = input.parentElement.querySelector('.invalid-feedback');
                                if (feedback) {
                                    feedback.textContent = message;
                                }
                            } else {
                                // إذا كان الخطأ عاماً
                                showAlert('danger', message);
                            }
                        });
                        
                        // عرض رسالة عامة للأخطاء
                        showAlert('danger', 'يرجى تصحيح الأخطاء أدناه');
                    } else if (result.message) {
                        showAlert('danger', result.message);
                    } else {
                        showAlert('danger', 'حدث خطأ غير معروف. يرجى المحاولة مرة أخرى.');
                    }
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('danger', 'حدث خطأ أثناء معالجة الطلب. يرجى المحاولة مرة أخرى.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
        });

        function resetForm() {
            // إزالة جميع التنبيهات
            document.getElementById('alert-container').innerHTML = '';
            
            // إعادة تعيين حالة جميع الحقول
            const inputs = document.querySelectorAll('.form-control');
            inputs.forEach(input => {
                input.classList.remove('is-invalid', 'is-valid');
                const validationIcon = input.parentElement.querySelector('.validation-icon');
                if (validationIcon) {
                    validationIcon.className = 'bi bi-check-circle-fill validation-icon';
                }
                const feedback = input.parentElement.querySelector('.invalid-feedback');
                if (feedback) {
                    feedback.textContent = '';
                }
            });
            
            // إخفاء متطلبات كلمة المرور
            const requirements = document.querySelectorAll('.password-requirements');
            requirements.forEach(req => req.classList.remove('show'));
        }

        function showAlert(type, message) {
            const alertContainer = document.getElementById('alert-container');
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            
            // إزالة التنبيهات السابقة من نفس النوع
            const existingAlerts = alertContainer.querySelectorAll(`.alert-${type}`);
            existingAlerts.forEach(alert => alert.remove());
            
            // إضافة التنبيه الجديد
            alertContainer.appendChild(alertDiv);
            
            // تمرير إلى التنبيه
            alertDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    </script>
</body>
</html>
