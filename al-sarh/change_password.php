<?php
session_start();
require_once 'config/config.php';
require_once 'includes/database.php';

// التحقق من وجود جلسة مؤقتة
if (!isset($_SESSION['temp_user_id'])) {
    header("Location: login.php");
    exit();
}

$error = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $userId = $_SESSION['temp_user_id'];
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    if ($newPassword !== $confirmPassword) {
        $error = "كلمة المرور الجديدة غير متطابقة";
    } else {
        $pdo = getDatabaseConnection();
        
        // التحقق من المستخدم وكلمة المرور الحالية
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if ($user) {
            if ($user['force_password_change']) {
                // التحقق من كلمة المرور المؤقتة
                if ($currentPassword === $user['temp_password']) {
                    // تحديث كلمة المرور
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET password = ?,
                            force_password_change = FALSE,
                            temp_password = NULL,
                            is_active = TRUE
                        WHERE user_id = ?
                    ");
                    if ($stmt->execute([$hashedPassword, $userId])) {
                        $success = true;
                        // مسح الجلسة المؤقتة
                        unset($_SESSION['temp_user_id']);
                        header("Location: login.php?msg=password_changed");
                        exit();
                    } else {
                        $error = "حدث خطأ أثناء تحديث كلمة المرور";
                    }
                } else {
                    $error = "كلمة المرور المؤقتة غير صحيحة";
                }
            } else {
                // التحقق من كلمة المرور الحالية
                if (password_verify($currentPassword, $user['password'])) {
                    // تحديث كلمة المرور
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET password = ?
                        WHERE user_id = ?
                    ");
                    if ($stmt->execute([$hashedPassword, $userId])) {
                        $success = true;
                        // مسح الجلسة المؤقتة
                        unset($_SESSION['temp_user_id']);
                        header("Location: login.php?msg=password_changed");
                        exit();
                    } else {
                        $error = "حدث خطأ أثناء تحديث كلمة المرور";
                    }
                } else {
                    $error = "كلمة المرور الحالية غير صحيحة";
                }
            }
        } else {
            $error = "حدث خطأ غير متوقع";
        }
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تغيير كلمة المرور</title>
    <link rel="stylesheet" href="assets/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title text-center mb-4">تغيير كلمة المرور</h3>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">كلمة المرور الحالية</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">كلمة المرور الجديدة</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
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
    
    <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
