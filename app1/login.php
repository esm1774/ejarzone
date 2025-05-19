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

function getRemainingAttempts($pdo, $username) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as recent_attempts,
            MAX(attempt_time) as last_attempt
        FROM login_attempts 
        WHERE username = ? 
        AND is_success = 0 
        AND attempt_time > DATE_SUB(NOW(), INTERVAL 3 MINUTE)
    ");
    $stmt->execute([$username]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return 3 - $result['recent_attempts'];
}

function checkLoginAttempts($pdo, $username) {
    // التحقق من حالة الحساب
    $stmt = $pdo->prepare("SELECT is_active FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && !$user['is_active']) {
        return "هذا الحساب معطل. يرجى التواصل مع الدعم الفني لتفعيله.";
    }

    // التحقق من المحاولات الفاشلة في آخر 30 دقيقة
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_attempts,
            MAX(attempt_time) as last_attempt
        FROM login_attempts 
        WHERE username = ? 
        AND is_success = 0 
        AND attempt_time > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    ");
    $stmt->execute([$username]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // التحقق من المحاولات الفاشلة في آخر 3 دقائق
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as recent_attempts
        FROM login_attempts 
        WHERE username = ? 
        AND is_success = 0 
        AND attempt_time > DATE_SUB(NOW(), INTERVAL 3 MINUTE)
    ");
    $stmt->execute([$username]);
    $recent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // إذا كان هناك 6 محاولات فاشلة في آخر 30 دقيقة
    if ($result['total_attempts'] >= 6) {
        // تعطيل الحساب
        $stmt = $pdo->prepare("
            UPDATE users 
            SET 
                is_active = 0,
                deactivation_reason = 'تم تعطيل الحساب بسبب تجاوز الحد الأقصى من محاولات تسجيل الدخول',
                deactivation_date = NOW()
            WHERE username = ?
        ");
        $stmt->execute([$username]);
        
        return "تم تعطيل الحساب نهائياً بسبب تجاوز الحد الأقصى من محاولات تسجيل الدخول. يرجى التواصل مع الدعم الفني على الرقم XXXXXXXXX لتفعيل حسابك.";
    }
    
    // إذا كان هناك 3 محاولات فاشلة في آخر 3 دقائق
    if ($recent['recent_attempts'] >= 3) {
        // حساب الوقت المتبقي للمحاولة التالية
        $lastAttempt = strtotime($result['last_attempt']);
        $waitUntil = $lastAttempt + (3 * 60); // 3 دقائق
        $remainingSeconds = $waitUntil - time();
        
        if ($remainingSeconds > 0) {
            $remainingMinutes = ceil($remainingSeconds / 60);
            return "تم إيقاف تسجيل الدخول مؤقتاً. يرجى المحاولة بعد {$remainingMinutes} دقائق. (المحاولات المتبقية قبل تعطيل الحساب: " . (6 - $result['total_attempts']) . " محاولات)";
        }
    }
    
    return true;
}

function logLoginAttempt($pdo, $username, $isSuccess) {
    // تسجيل المحاولة
    $stmt = $pdo->prepare("
        INSERT INTO login_attempts (
            username, 
            is_success, 
            ip_address, 
            attempt_time,
            user_agent
        ) VALUES (
            ?, ?, ?, NOW(), ?
        )
    ");
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $stmt->execute([$username, $isSuccess ? 1 : 0, $ipAddress, $userAgent]);

    // إذا كانت محاولة ناجحة، نمسح المحاولات الفاشلة السابقة
    if ($isSuccess) {
        $stmt = $pdo->prepare("
            DELETE FROM login_attempts 
            WHERE username = ? 
            AND is_success = 0
        ");
        $stmt->execute([$username]);
    }
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
            $remainingAttempts = getRemainingAttempts($pdo, $username);
            $totalRemaining = isset($_SESSION['remaining_attempts']) ? $_SESSION['remaining_attempts'] : 6;
            $error = "اسم المستخدم أو كلمة المرور غير صحيحة. المحاولات المتبقية: " . $remainingAttempts . " محاولات (المحاولات المتبقية قبل تعطيل الحساب: " . $totalRemaining . " محاولات)";
            logLoginAttempt($pdo, $username, false);
        } else {
            // التحقق من حالة الحساب
            if (!$user['is_active']) {
                $error = "هذا الحساب معطل. يرجى التواصل مع الدعم الفني لتفعيله.";
                if ($user['deactivation_reason']) {
                    $error .= "<br>سبب التعطيل: " . htmlspecialchars($user['deactivation_reason']);
                }
                logLoginAttempt($pdo, $username, false);
            } else if (password_verify($password, $user['password'])) {
                // تحديث آخر تسجيل دخول وإعادة تعيين محاولات تسجيل الدخول
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
                
                header('Location: index.php');
                exit;
            } else {
                $remainingAttempts = getRemainingAttempts($pdo, $username);
                $totalRemaining = isset($_SESSION['remaining_attempts']) ? $_SESSION['remaining_attempts'] : 6;
                $error = "اسم المستخدم أو كلمة المرور غير صحيحة. المحاولات المتبقية: " . $remainingAttempts . " محاولات (المحاولات المتبقية قبل تعطيل الحساب: " . $totalRemaining . " محاولات)";
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
