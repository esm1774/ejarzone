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

function checkLoginAttempts($pdo, $email) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as attempts 
        FROM login_attempts 
        WHERE email = ? 
        AND is_success = 0 
        AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt->execute([$email]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['attempts'] >= 5) {
        return "تم تجاوز الحد الأقصى لمحاولات تسجيل الدخول. يرجى المحاولة مرة أخرى بعد 15 دقيقة.";
    }
    
    return true;
}

function logLoginAttempt($pdo, $email, $isSuccess) {
    $stmt = $pdo->prepare("
        INSERT INTO login_attempts (email, is_success, ip_address) 
        VALUES (?, ?, ?)
    ");
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $stmt->execute([$email, $isSuccess ? 1 : 0, $ipAddress]);
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
    
    // التحقق من محاولات تسجيل الدخول
    $checkAttempts = checkLoginAttempts($pdo, $username);
    if ($checkAttempts !== true) {
        $error = $checkAttempts;
    } else {
        // البحث عن المستخدم
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = "اسم المستخدم أو كلمة المرور غير صحيحة";
            logLoginAttempt($pdo, $username, false);
        } else {
            // التحقق من حالة الحساب
            if (!$user['is_active']) {
                $error = "هذا الحساب غير نشط. يرجى التواصل مع فريق الدعم لتفعيله.";
                logLoginAttempt($pdo, $username, false);
            } else if (password_verify($password, $user['password'])) {
                // تحديث آخر تسجيل دخول
                $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                $stmt->execute([$user['user_id']]);
                
                // تسجيل محاولة تسجيل الدخول الناجحة
                logLoginAttempt($pdo, $username, true);
                
                // تخزين معلومات المستخدم في الجلسة
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                
                // التحقق مما إذا كان المستخدم يحتاج إلى تغيير كلمة المرور
                if ($user['force_password_change']) {
                    $_SESSION['force_password_change'] = true;
                    header('Location: change_password.php');
                    exit;
                }
                
                // توجيه المستخدم إلى الصفحة الرئيسية
                header('Location: index.php');
                exit;
            } else {
                $error = "اسم المستخدم أو كلمة المرور غير صحيحة";
                logLoginAttempt($pdo, $username, false);
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
    <title>تسجيل الدخول - الصرح</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Cairo', sans-serif;
        }
        .card {
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .btn-primary {
            background-color: #2c3e50;
            border-color: #2c3e50;
        }
        .btn-primary:hover {
            background-color: #34495e;
            border-color: #34495e;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center">تسجيل الدخول</h3>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['success_message'])): ?>
                            <div class="alert alert-success">
                                <?php 
                                    echo $_SESSION['success_message'];
                                    unset($_SESSION['success_message']);
                                ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger">
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label">اسم المستخدم</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">كلمة المرور</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 mb-3" id="submitBtn">
                                <i class="bi bi-box-arrow-in-right me-2"></i>تسجيل الدخول
                            </button>
                            
                            <div class="text-center">
                                <div class="mb-2">
                                    <a href="register.php" class="text-decoration-none">
                                        <i class="bi bi-person-plus me-1"></i>
                                        تسجيل مستخدم جديد
                                    </a>
                                </div>
                                <div>
                                    <a href="forgot_password.php" class="text-decoration-none">
                                        <i class="bi bi-key me-1"></i>
                                        نسيت كلمة المرور؟
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
