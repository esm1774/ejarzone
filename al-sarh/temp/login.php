<?php
session_start();
require_once 'config/config.php';
require_once 'includes/database.php';
require_once 'includes/Mailer.php';

function generateDeviceIdentifier() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    return hash('sha256', $userAgent . $ipAddress);
}

function isTrustedDevice($pdo, $userId, $deviceIdentifier) {
    $stmt = $pdo->prepare("
        SELECT id FROM trusted_devices 
        WHERE user_id = ? AND device_identifier = ?
    ");
    $stmt->execute([$userId, $deviceIdentifier]);
    return $stmt->fetch() !== false;
}

function updateTrustedDevice($pdo, $userId, $deviceIdentifier) {
    $browserInfo = $_SERVER['HTTP_USER_AGENT'];
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    
    $stmt = $pdo->prepare("
        INSERT INTO trusted_devices 
            (user_id, device_identifier, browser_info, ip_address, last_used) 
        VALUES 
            (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
            last_used = NOW(),
            browser_info = VALUES(browser_info),
            ip_address = VALUES(ip_address)
    ");
    return $stmt->execute([$userId, $deviceIdentifier, $browserInfo, $ipAddress]);
}

function checkLoginAttempts($pdo, $username = null) {
    $timeWindow = date('Y-m-d H:i:s', strtotime('-15 minutes'));
    
    // البحث عن محاولات تسجيل الدخول الفاشلة للمستخدم
    $sql = "SELECT COUNT(*) as attempts, MIN(la.attempt_time) as first_attempt 
            FROM login_attempts la 
            JOIN users u ON la.user_id = u.user_id 
            WHERE la.attempt_time > ? 
            AND la.is_success = FALSE";
    $params = [$timeWindow];
    
    if ($username) {
        $sql .= " AND u.username = ?";
        $params[] = $username;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['attempts'] > 0) {
        $firstAttempt = strtotime($result['first_attempt']);
        $lockoutEnd = $firstAttempt + (15 * 60); // 15 دقيقة بالثواني
        $currentTime = time();
        
        $remainingTime = max(0, $lockoutEnd - $currentTime);
        
        // التحقق مما إذا كانت المحاولات تجاوزت 3 وانتهت فترة الانتظار
        $waitingPeriodExpired = ($remainingTime <= 0 && $result['attempts'] >= 3);
        
        return [
            'attempts' => $result['attempts'],
            'remaining_seconds' => $remainingTime,
            'first_attempt' => $result['first_attempt'],
            'waiting_period_expired' => $waitingPeriodExpired
        ];
    }
    
    return [
        'attempts' => 0, 
        'remaining_seconds' => 0, 
        'first_attempt' => null,
        'waiting_period_expired' => false
    ];
}

function recordLoginAttempt($pdo, $userId, $isSuccess) {
    $stmt = $pdo->prepare("
        INSERT INTO login_attempts (user_id, ip_address, attempt_time, is_success)
        VALUES (?, ?, NOW(), ?)
    ");
    return $stmt->execute([$userId, $_SERVER['REMOTE_ADDR'], $isSuccess]);
}

function deactivateAccount($pdo, $userId) {
    $stmt = $pdo->prepare("
        UPDATE users 
        SET is_active = FALSE 
        WHERE user_id = ?
    ");
    return $stmt->execute([$userId]);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $error = null;

    $pdo = getDatabaseConnection();
    
    // التحقق من عدد محاولات تسجيل الدخول الفاشلة للمستخدم
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user) {
        $loginStatus = checkLoginAttempts($pdo, $username);
        if ($loginStatus['attempts'] >= 3 && $loginStatus['remaining_seconds'] > 0) {
            // لا يزال في فترة الانتظار
            $minutes = ceil($loginStatus['remaining_seconds'] / 60);
            $seconds = $loginStatus['remaining_seconds'] % 60;
            $error = "تم تجاوز الحد المسموح به من محاولات تسجيل الدخول. يرجى المحاولة بعد {$minutes} دقيقة و {$seconds} ثانية";
        } else {
            // التحقق من بيانات المستخدم
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user) {
                // التحقق من حالة الحساب
                if (!$user['is_active']) {
                    $error = "هذا الحساب غير نشط. يرجى التواصل مع فريق الدعم لتفعيله.";
                } else if ($user['force_password_change']) {
                    $_SESSION['temp_user_id'] = $user['user_id'];
                    header("Location: change_password.php");
                    exit();
                } else if (password_verify($password, $user['password'])) {
                    // تسجيل محاولة تسجيل الدخول الناجحة
                    recordLoginAttempt($pdo, $user['user_id'], true);
                    
                    $deviceIdentifier = generateDeviceIdentifier();
                    
                    if (isTrustedDevice($pdo, $user['user_id'], $deviceIdentifier)) {
                        // تسجيل الدخول مباشرة
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['user_name'] = $user['full_name'];
                        $_SESSION['user_role'] = $user['role'];
                        
                        updateTrustedDevice($pdo, $user['user_id'], $deviceIdentifier);
                        
                        // جلب صلاحيات المستخدم
                        $stmt = $pdo->prepare("
                            SELECT p.permission_name 
                            FROM permissions p 
                            JOIN user_permissions up ON p.permission_id = up.permission_id 
                            WHERE up.user_id = ?
                        ");
                        $stmt->execute([$user['user_id']]);
                        $_SESSION['user_permissions'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        header("Location: index.php");
                        exit();
                    } else {
                        // إنشاء رمز OTP للتحقق
                        $otp = sprintf("%04d", rand(0, 9999));
                        $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                        
                        $stmt = $pdo->prepare("
                            UPDATE users 
                            SET otp_code = ?, 
                                otp_expiry = ? 
                            WHERE user_id = ?
                        ");
                        $stmt->execute([$otp, $expiry, $user['user_id']]);
                        
                        try {
                            $mailer = Mailer::getInstance();
                            $emailBody = "
                                <h2>رمز التحقق من تسجيل الدخول</h2>
                                <p>مرحباً {$user['full_name']},</p>
                                <p>لقد تم تسجيل محاولة دخول من جهاز جديد إلى حسابك.</p>
                                <p>رمز التحقق الخاص بك هو: <strong>{$otp}</strong></p>
                                <p>هذا الرمز صالح لمدة 15 دقيقة فقط.</p>
                                <p>إذا لم تقم بطلب هذا الرمز، يرجى تجاهل هذا البريد الإلكتروني وتغيير كلمة المرور الخاصة بك.</p>
                            ";
                            
                            $mailer->send(
                                $user['email'],
                                $user['full_name'],
                                'رمز التحقق من تسجيل الدخول',
                                $emailBody
                            );
                            
                            $_SESSION['temp_user_id'] = $user['user_id'];
                            $_SESSION['temp_device_id'] = $deviceIdentifier;
                            
                            header("Location: verify_otp.php");
                            exit();
                        } catch (Exception $e) {
                            $error = "حدث خطأ في إرسال رمز التحقق. يرجى المحاولة مرة أخرى.";
                            error_log("خطأ في إرسال رمز التحقق: " . $e->getMessage());
                        }
                    }
                } else {
                    // تسجيل محاولة تسجيل الدخول الفاشلة
                    recordLoginAttempt($pdo, $user['user_id'], false);
                    
                    // التحقق من عدد المحاولات الفاشلة للمستخدم
                    $userLoginStatus = checkLoginAttempts($pdo, $username);
                    
                    if ($userLoginStatus['waiting_period_expired']) {
                        // تعطيل الحساب لأن المستخدم فشل في تسجيل الدخول بعد فترة الانتظار
                        deactivateAccount($pdo, $user['user_id']);
                        $error = "تم تعطيل حسابك بسبب استمرار محاولات تسجيل الدخول الفاشلة بعد فترة الانتظار. يرجى التواصل مع فريق الدعم.";
                    } else if ($userLoginStatus['attempts'] >= 3) {
                        // في فترة الانتظار
                        $minutes = ceil($userLoginStatus['remaining_seconds'] / 60);
                        $seconds = $userLoginStatus['remaining_seconds'] % 60;
                        $error = "تم تجاوز الحد المسموح به من المحاولات. يرجى الانتظار {$minutes} دقيقة و {$seconds} ثانية قبل المحاولة مرة أخرى.";
                        $error .= "<br>تحذير: سيتم تعطيل حسابك إذا فشلت في تسجيل الدخول بعد انتهاء فترة الانتظار.";
                    } else {
                        $remainingAttempts = 3 - $userLoginStatus['attempts'];
                        $error = "اسم المستخدم أو كلمة المرور غير صحيحة. المحاولات المتبقية: {$remainingAttempts}";
                    }
                }
            } else {
                $error = "اسم المستخدم أو كلمة المرور غير صحيحة";
            }
        }
    } else {
        $error = "اسم المستخدم أو كلمة المرور غير صحيحة";
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - الصرح</title>
    
    <!-- Bootstrap RTL CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <!-- Cairo Font -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body>
        <div class="container">
        <div class="row justify-content-center">
    <!--<div class="auth-container">-->
    <!--    <div class="auth-header">-->
            <img src="assets/images/logo.png" alt="الصرح" class="img-fluid">
            <h1>تسجيل الدخول</h1>
            <p>مرحباً بك في نظام إدارة الشقق الفندقية</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form action="login_handler.php" method="post" novalidate>
            <div class="form-group">
                <label for="email" class="form-label">البريد الإلكتروني</label>
                <div class="input-group">
                    <i class="bi bi-envelope form-icon"></i>
                    <input type="email" 
                           class="form-control" 
                           id="email" 
                           name="email" 
                           required>
                    <div class="invalid-feedback">
                        يرجى إدخال بريد إلكتروني صحيح
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">كلمة المرور</label>
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
                    <div class="invalid-feedback">
                        يرجى إدخال كلمة المرور
                    </div>
                </div>
            </div>

            <div class="form-group">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">تذكرني</label>
                    </div>
                    <a href="forgot_password.php" class="text-decoration-none">نسيت كلمة المرور؟</a>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 mb-3">
                <i class="bi bi-box-arrow-in-left me-2"></i>تسجيل الدخول
            </button>

            <div class="text-center">
                ليس لديك حساب؟ 
                <a href="register.php" class="text-decoration-none">إنشاء حساب جديد</a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling;
            
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

        // التحقق من صحة النموذج
        const form = document.querySelector('form');
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });

        // إخفاء رسائل التنبيه بعد 5 ثواني
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.remove();
            }, 5000);
        });
    </script>
</body>
</html>
