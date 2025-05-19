<?php
session_start();
require_once 'config/config.php';
require_once 'includes/database.php';

// إذا لم يكن هناك جلسة تحقق مؤقتة، إعادة التوجيه إلى صفحة تسجيل الدخول
if (!isset($_SESSION['temp_user_id']) || !isset($_SESSION['temp_device_id'])) {
    header("Location: login.php");
    exit();
}

$error = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $otp = $_POST['otp'];
    $user_id = $_SESSION['temp_user_id'];
    $device_id = $_SESSION['temp_device_id'];

    $pdo = getDatabaseConnection();
    
    // التحقق من صحة رمز OTP
    $stmt = $pdo->prepare("
        SELECT * FROM users 
        WHERE user_id = ? AND otp_code = ? AND otp_expiry > NOW()
    ");
    $stmt->execute([$user_id, $otp]);
    $user = $stmt->fetch();

    if ($user) {
        // إضافة الجهاز إلى قائمة الأجهزة الموثوقة
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
        $stmt->execute([$user_id, $device_id, $browserInfo, $ipAddress]);

        // مسح رمز OTP
        $stmt = $pdo->prepare("
            UPDATE users 
            SET otp_code = NULL, 
                otp_expiry = NULL 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);

        // تخزين بيانات المستخدم في الجلسة
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_role'] = $user['role'];

        // جلب صلاحيات المستخدم
        $stmt = $pdo->prepare("
            SELECT p.permission_name 
            FROM permissions p 
            JOIN user_permissions up ON p.permission_id = up.permission_id 
            WHERE up.user_id = ?
        ");
        $stmt->execute([$user['user_id']]);
        $_SESSION['user_permissions'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // مسح الجلسة المؤقتة
        unset($_SESSION['temp_user_id']);
        unset($_SESSION['temp_device_id']);
        
        header("Location: index.php");
        exit();
    } else {
        $error = "رمز التحقق غير صحيح أو منتهي الصلاحية";
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>التحقق من الرمز - نظام إدارة الشقق الفندقية</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .otp-form {
            background-color: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        .otp-input {
            letter-spacing: 0.5em;
            text-align: center;
            font-size: 1.5em;
        }
    </style>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="otp-form">
                    <h2 class="text-center mb-4">التحقق من الرمز</h2>
                    <p class="text-center mb-4">تم إرسال رمز التحقق إلى بريدك الإلكتروني</p>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label for="otp" class="form-label">رمز التحقق</label>
                            <input type="text" 
                                   class="form-control otp-input" 
                                   id="otp" 
                                   name="otp" 
                                   maxlength="4" 
                                   pattern="\d{4}"
                                   inputmode="numeric"
                                   autocomplete="one-time-code"
                                   required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mb-3">
                            تحقق من الرمز
                        </button>
                        <div class="text-center">
                            <a href="login.php" class="text-decoration-none">
                                العودة لصفحة تسجيل الدخول
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // تحويل المدخلات إلى أرقام فقط
        document.getElementById('otp').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>
</html>
