<?php
require_once 'config/config.php';

// التحقق من تسجيل الدخول
if (!isLoggedIn()) {
    redirect('login.php');
}

// الحصول على بيانات المستخدم
$userId = $_SESSION['user_id'];
$stmt = $GLOBALS['db']->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// معالجة تحديث البيانات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    $errors = [];
    
    // التحقق من البيانات
    if (empty($full_name)) {
        $errors[] = 'الرجاء إدخال الاسم';
    }
    if (empty($email)) {
        $errors[] = 'الرجاء إدخال البريد الإلكتروني';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'البريد الإلكتروني غير صالح';
    }
    
    // التحقق من تغيير كلمة المرور
    if (!empty($currentPassword)) {
        if (!password_verify($currentPassword, $user['password'])) {
            $errors[] = 'كلمة المرور الحالية غير صحيحة';
        } elseif (empty($newPassword)) {
            $errors[] = 'الرجاء إدخال كلمة المرور الجديدة';
        } elseif ($newPassword !== $confirmPassword) {
            $errors[] = 'كلمة المرور الجديدة غير متطابقة';
        }
    }
    
    // معالجة الصورة الشخصية
    $avatar = $user['avatar'];
    if (!empty($_FILES['avatar']['name'])) {
        $allowedTypes = ['image/jpeg', 'image/png'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['avatar']['type'], $allowedTypes)) {
            $errors[] = 'نوع الملف غير مدعوم. الرجاء استخدام صور JPEG أو PNG';
        } elseif ($_FILES['avatar']['size'] > $maxSize) {
            $errors[] = 'حجم الصورة كبير جداً. الحد الأقصى هو 5 ميجابايت';
        } else {
            $extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $newFileName = uniqid() . '.' . $extension;
            $uploadPath = 'uploads/avatars/' . $newFileName;
            
            if (!is_dir('uploads/avatars')) {
                mkdir('uploads/avatars', 0777, true);
            }
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadPath)) {
                // حذف الصورة القديمة
                if ($avatar && file_exists($avatar)) {
                    unlink($avatar);
                }
                $avatar = $uploadPath;
            } else {
                $errors[] = 'حدث خطأ أثناء رفع الصورة';
            }
        }
    }
    
    // حفظ التغييرات
    if (empty($errors)) {
        try {
            $GLOBALS['db']->beginTransaction();
            
            // تحديث البيانات الأساسية
            $sql = "UPDATE users SET full_name = ?, email = ?, phone = ?, avatar = ? WHERE user_id = ?";
            $stmt = $GLOBALS['db']->prepare($sql);
            $stmt->execute([$full_name, $email, $phone, $avatar, $userId]);
            
            // تحديث كلمة المرور إذا تم تغييرها
            if (!empty($newPassword)) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET password = ? WHERE user_id = ?";
                $stmt = $GLOBALS['db']->prepare($sql);
                $stmt->execute([$hashedPassword, $userId]);
            }
            
            $GLOBALS['db']->commit();
            showSuccess('تم تحديث البيانات بنجاح');
            
            // تحديث اسم المستخدم في الجلسة
            $_SESSION['full_name'] = $full_name;
            
            // إعادة تحميل بيانات المستخدم
            $stmt = $GLOBALS['db']->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $GLOBALS['db']->rollBack();
            showError('حدث خطأ أثناء تحديث البيانات');
        }
    } else {
        foreach ($errors as $error) {
            showError($error);
        }
    }
}

// عرض الصفحة
include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="main-content">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h4 class="card-title mb-4">الملف الشخصي</h4>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <!-- الصورة الشخصية -->
                            <div class="text-center mb-4">
                                <div class="avatar-wrapper mb-3">
                                    <?php if ($user['avatar']): ?>
                                        <img src="<?php echo $user['avatar']; ?>" alt="الصورة الشخصية" class="rounded-circle" style="width: 150px; height: 150px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="avatar-placeholder rounded-circle d-flex align-items-center justify-content-center" style="width: 150px; height: 150px; background-color: var(--primary-color); color: white; font-size: 48px;">
                                            <?php echo substr($user['full_name'], 0, 1); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="mb-3">
                                    <input type="file" class="form-control" id="avatar" name="avatar" accept="image/jpeg,image/png">
                                    <div class="form-text">اختر صورة بصيغة JPEG أو PNG (الحد الأقصى: 5 ميجابايت)</div>
                                </div>
                            </div>

                            <!-- البيانات الأساسية -->
                            <div class="mb-3">
                                <label for="full_name" class="form-label">الاسم</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">البريد الإلكتروني</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="phone" class="form-label">رقم الهاتف</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
                            </div>
                            
                            <hr class="my-4">
                            
                            <!-- تغيير كلمة المرور -->
                            <h5 class="mb-3">تغيير كلمة المرور</h5>
                            
                            <div class="mb-3">
                                <label for="current_password" class="form-label">كلمة المرور الحالية</label>
                                <input type="password" class="form-control" id="current_password" name="current_password">
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">كلمة المرور الجديدة</label>
                                <input type="password" class="form-control" id="new_password" name="new_password">
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">تأكيد كلمة المرور الجديدة</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
.avatar-wrapper {
    display: inline-block;
    position: relative;
}
.avatar-wrapper img,
.avatar-placeholder {
    box-shadow: 0 0 20px rgba(0,0,0,0.1);
}
</style>

<?php include 'includes/footer.php'; ?>