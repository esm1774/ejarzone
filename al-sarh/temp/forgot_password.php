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
    <title>نسيت كلمة المرور - الصرح</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body>
    <div class="forgot-password-container">
        <div class="card">
            <div class="card-header text-center">
                <h3><i class="bi bi-key-fill me-2"></i>استعادة كلمة المرور</h3>
            </div>
            <div class="card-body p-4">
                <div id="alertContainer"></div>
                <form id="forgotPasswordForm" method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="form-group">
                        <label for="email">البريد الإلكتروني</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <i class="bi bi-envelope"></i>
                            </div>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   required>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mb-3" id="submitBtn">
                        <i class="bi bi-send me-2"></i>إرسال رابط الاستعادة
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
        document.getElementById('forgotPasswordForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('submitBtn');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>جاري الإرسال...';
            
            try {
                const formData = new FormData(this);
                console.log('Form data:', Object.fromEntries(formData));
                console.log('Sending request to:', '<?php echo rtrim(APP_URL, '/'); ?>/forgot_password_handler.php');
                
                const response = await fetch('<?php echo rtrim(APP_URL, '/'); ?>/forgot_password_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                console.log('Response status:', response.status);
                console.log('Response headers:', Object.fromEntries(response.headers));
                
                const responseText = await response.text();
                console.log('Raw response:', responseText);
                
                let result;
                try {
                    result = JSON.parse(responseText);
                    console.log('Parsed response:', result);
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    throw new Error('فشل في تحليل استجابة الخادم: ' + responseText.substring(0, 1000));
                }
                
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                
                if (result.success) {
                    showAlert('success', result.message);
                    this.reset();
                } else {
                    if (result.errors) {
                        Object.entries(result.errors).forEach(([field, message]) => {
                            const input = document.getElementById(field);
                            if (input) {
                                input.classList.add('is-invalid');
                                const feedback = input.parentElement.querySelector('.invalid-feedback');
                                if (feedback) {
                                    feedback.textContent = message;
                                }
                            }
                        });
                    } else if (result.message) {
                        showAlert('danger', result.message);
                    }
                }
            } catch (error) {
                console.error('Error details:', error);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                showAlert('danger', 'حدث خطأ أثناء معالجة الطلب: ' + error.message);
            }
        });

        function showAlert(type, message) {
            const alertContainer = document.getElementById('alertContainer');
            const alertElement = document.createElement('div');
            alertElement.className = `alert alert-${type} alert-dismissible fade show`;
            alertElement.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            alertContainer.innerHTML = '';
            alertContainer.appendChild(alertElement);
            
            // تلقائياً إخفاء التنبيه بعد 5 ثواني
            setTimeout(() => {
                alertElement.classList.remove('show');
                setTimeout(() => alertElement.remove(), 150);
            }, 5000);
        }
    </script>
</body>
</html>
