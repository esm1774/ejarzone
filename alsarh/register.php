<?php
require_once 'config/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

// تفعيل عرض الأخطاء
error_reporting(E_ALL);
ini_set('display_errors', 1);

// بدء الجلسة إذا لم تكن موجودة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// إنشاء CSRF token إذا لم يكن موجوداً
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// دالة لتنظيف المدخلات
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = null;
    $transaction_started = false;
    
    try {
        // التحقق من CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('خطأ في التحقق من صحة النموذج');
        }

        // التحقق من البيانات المطلوبة
        $required_fields = ['username', 'password', 'confirm_password', 'full_name', 'email'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
                throw new Exception("الحقل {$field} مطلوب");
            }
        }

        // تنظيف وتحقق من المدخلات
        $username = sanitizeInput($_POST['username']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $full_name = sanitizeInput($_POST['full_name']);
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

        // طباعة البيانات للتحقق
        error_log("Username: " . $username);
        error_log("Full Name: " . $full_name);
        error_log("Email: " . $email);

        // التحقق من طول اسم المستخدم
        if (strlen($username) < 3 || strlen($username) > 20) {
            throw new Exception('يجب أن يكون طول اسم المستخدم بين 3 و 20 حرفاً');
        }

        // التحقق من صحة البريد الإلكتروني
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('البريد الإلكتروني غير صالح');
        }

        // التحقق من طول الاسم الكامل
        if (strlen($full_name) < 2 || strlen($full_name) > 50) {
            throw new Exception('يجب أن يكون طول الاسم الكامل بين 2 و 50 حرفاً');
        }

        // التحقق من تطابق كلمتي المرور
        if ($password !== $confirm_password) {
            throw new Exception('كلمتا المرور غير متطابقتين');
        }

        // التحقق من قوة كلمة المرور
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
            throw new Exception('كلمة المرور يجب أن تحتوي على 8 حروف على الأقل، وتتضمن حروف كبيرة وصغيرة، وأرقام، ورموز');
        }

        // الحصول على اتصال قاعدة البيانات
        $pdo = getDatabaseConnection();
        error_log("تم الاتصال بقاعدة البيانات بنجاح");
        
        // بدء المعاملة
        $pdo->beginTransaction();
        $transaction_started = true;
        error_log("تم بدء المعاملة");

        // التحقق من عدم وجود اسم مستخدم مكرر
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('اسم المستخدم موجود بالفعل');
        }
        error_log("تم التحقق من اسم المستخدم");

        // التحقق من عدم وجود بريد إلكتروني مكرر
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('البريد الإلكتروني موجود بالفعل');
        }
        error_log("تم التحقق من البريد الإلكتروني");

        // تشفير كلمة المرور
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        error_log("تم تشفير كلمة المرور");

        // إدخال المستخدم الجديد في قاعدة البيانات
        $query = "INSERT INTO users (username, password, full_name, email, role, status, created_at) 
                 VALUES (?, ?, ?, ?, 'مستخدم', 'نشط', NOW())";
        error_log("SQL Query: " . $query);
        
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([$username, $password_hash, $full_name, $email]);
        
        if (!$result) {
            $error = $stmt->errorInfo();
            error_log("Database Error: " . print_r($error, true));
            throw new Exception('فشل في تسجيل المستخدم: ' . $error[2]);
        }
        error_log("تم إدخال المستخدم بنجاح");

        // تأكيد المعاملة
        $pdo->commit();
        $transaction_started = false;
        error_log("تم تأكيد المعاملة");

        // إضافة رسالة نجاح وتوجيه المستخدم
        addMessage('success', 'تم تسجيل المستخدم بنجاح');
        header('Location: login.php');
        exit;

    } catch (Exception $e) {
        error_log("Error: " . $e->getMessage());
        if ($transaction_started && $pdo !== null) {
            try {
                $pdo->rollBack();
            } catch (PDOException $rollback_error) {
                error_log("Rollback Error: " . $rollback_error->getMessage());
            }
        }
        addMessage('error', $e->getMessage());
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل مستخدم جديد</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Tajawal', sans-serif;
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background-color: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 500px;
        }
        .form-group {
            position: relative;
            margin-bottom: 1.5rem;
        }
        .password-toggle {
            position: absolute;
            left: 10px;
            top: 35px;
            cursor: pointer;
        }
        .password-requirements {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 0.5rem;
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
        }
        .password-requirements ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .password-requirements li {
            margin: 5px 0;
            padding-right: 25px;
            position: relative;
        }
        .password-requirements li:before {
            content: '';
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            background-size: contain;
            background-repeat: no-repeat;
        }
        .password-requirements li.valid:before {
            content: '✓';
            color: #28a745;
        }
        .password-requirements li.invalid:before {
            content: '✗';
            color: #dc3545;
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            padding: 0.5rem 2rem;
            font-weight: bold;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        .login-link {
            text-align: center;
            margin-top: 1rem;
        }
        .invalid-feedback {
            font-size: 0.85rem;
        }
        /* تعديل Bootstrap RTL */
        .form-control {
            text-align: right;
        }
        .invalid-feedback {
            text-align: right;
        }
    </style>
</head>
<body>
<div class="container">
    <h2 class="text-center mb-4">تسجيل مستخدم جديد</h2>
    <form method="POST" action="register.php" id="registerForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        
        <div class="form-group">
            <label for="username">اسم المستخدم</label>
            <input type="text" class="form-control" id="username" name="username" required 
                   minlength="3" maxlength="20" pattern="[A-Za-z0-9_]+"
                   title="يجب أن يحتوي على أحرف وأرقام وشرطة سفلية فقط">
            <div class="invalid-feedback">يجب أن يكون اسم المستخدم بين 3 و 20 حرفاً</div>
        </div>

        <div class="form-group">
            <label for="password">كلمة المرور</label>
            <input type="password" class="form-control" id="password" name="password" required>
            <i class="fas fa-eye password-toggle" onclick="togglePassword('password')"></i>
            <div class="password-requirements">
                <ul>
                    <li id="length" class="invalid">8 حروف على الأقل</li>
                    <li id="lowercase" class="invalid">حرف صغير واحد على الأقل</li>
                    <li id="uppercase" class="invalid">حرف كبير واحد على الأقل</li>
                    <li id="number" class="invalid">رقم واحد على الأقل</li>
                    <li id="special" class="invalid">رمز خاص واحد على الأقل (@$!%*?&)</li>
                </ul>
            </div>
        </div>

        <div class="form-group">
            <label for="confirm_password">تأكيد كلمة المرور</label>
            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
            <i class="fas fa-eye password-toggle" onclick="togglePassword('confirm_password')"></i>
        </div>

        <div class="form-group">
            <label for="full_name">الاسم الكامل</label>
            <input type="text" class="form-control" id="full_name" name="full_name" required
                   minlength="2" maxlength="50">
            <div class="invalid-feedback">يجب أن يكون الاسم الكامل بين 2 و 50 حرفاً</div>
        </div>

        <div class="form-group">
            <label for="email">البريد الإلكتروني</label>
            <input type="email" class="form-control" id="email" name="email" required>
            <div class="invalid-feedback">يرجى إدخال بريد إلكتروني صحيح</div>
        </div>

        <button type="submit" class="btn btn-primary w-100">تسجيل</button>
        
        <div class="login-link">
            لديك حساب بالفعل؟ <a href="login.php">تسجيل الدخول</a>
        </div>
    </form>
</div>

<script>
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = input.nextElementSibling;
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    
    // التحقق من طول كلمة المرور
    document.getElementById('length').className = 
        password.length >= 8 ? 'valid' : 'invalid';

    // التحقق من وجود حرف صغير
    document.getElementById('lowercase').className = 
        /[a-z]/.test(password) ? 'valid' : 'invalid';

    // التحقق من وجود حرف كبير
    document.getElementById('uppercase').className = 
        /[A-Z]/.test(password) ? 'valid' : 'invalid';

    // التحقق من وجود رقم
    document.getElementById('number').className = 
        /\d/.test(password) ? 'valid' : 'invalid';

    // التحقق من وجود رمز خاص
    document.getElementById('special').className = 
        /[@$!%*?&]/.test(password) ? 'valid' : 'invalid';
});

document.getElementById('registerForm').addEventListener('submit', function(event) {
    let isValid = true;
    
    // التحقق من اسم المستخدم
    const username = document.getElementById('username');
    if (username.value.length < 3 || username.value.length > 20) {
        username.classList.add('is-invalid');
        isValid = false;
    } else {
        username.classList.remove('is-invalid');
    }
    
    // التحقق من كلمة المرور
    const password = document.getElementById('password');
    const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
    if (!passwordRegex.test(password.value)) {
        password.classList.add('is-invalid');
        isValid = false;
    } else {
        password.classList.remove('is-invalid');
    }
    
    // التحقق من تطابق كلمة المرور
    const confirmPassword = document.getElementById('confirm_password');
    if (password.value !== confirmPassword.value) {
        confirmPassword.classList.add('is-invalid');
        isValid = false;
    } else {
        confirmPassword.classList.remove('is-invalid');
    }
    
    // التحقق من الاسم الكامل
    const fullName = document.getElementById('full_name');
    if (fullName.value.length < 2 || fullName.value.length > 50) {
        fullName.classList.add('is-invalid');
        isValid = false;
    } else {
        fullName.classList.remove('is-invalid');
    }
    
    // التحقق من البريد الإلكتروني
    const email = document.getElementById('email');
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email.value)) {
        email.classList.add('is-invalid');
        isValid = false;
    } else {
        email.classList.remove('is-invalid');
    }
    
    if (!isValid) {
        event.preventDefault();
    }
});
</script>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
