<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/helpers.php';

// دالة لإنشاء كلمة مرور عشوائية تلبي متطلبات التعقيد
function generateRandomPassword($length = 8) {
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $numbers = '0123456789';
    $special = '!@#$%^&*(),.?":{}|<>';
    
    // التأكد من وجود حرف من كل نوع على الأقل
    $password = [
        $uppercase[random_int(0, strlen($uppercase) - 1)], // حرف كبير
        $lowercase[random_int(0, strlen($lowercase) - 1)], // حرف صغير
        $numbers[random_int(0, strlen($numbers) - 1)],     // رقم
        $special[random_int(0, strlen($special) - 1)]      // رمز خاص
    ];
    
    // إكمال باقي الأحرف بشكل عشوائي
    $all = $uppercase . $lowercase . $numbers . $special;
    for ($i = count($password); $i < $length; $i++) {
        $password[] = $all[random_int(0, strlen($all) - 1)];
    }
    
    // خلط الأحرف
    shuffle($password);
    
    return implode('', $password);
}

// التحقق من الصلاحيات
if (!hasPermission('edit_users')) {
    $_SESSION['error'] = "ليس لديك صلاحية للوصول إلى هذه الصفحة";
    header("Location: index.php");
    exit();
}

// التحقق من وجود معرف المستخدم
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "معرف المستخدم غير صحيح";
    header("Location: index.php");
    exit();
}

$userId = (int)$_GET['id'];

// إنشاء اتصال بقاعدة البيانات
$database = new Database();
$db = $database->getConnection();

// الحصول على قائمة الأدوار
$stmt = $db->query("SELECT * FROM roles ORDER BY role_name");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// الحصول على بيانات المستخدم مع الدور
$stmt = $db->prepare("
    SELECT u.*, r.role_name, r.role_id 
    FROM users u 
    LEFT JOIN roles r ON u.role_id = r.role_id 
    WHERE u.user_id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $_SESSION['error'] = "المستخدم غير موجود";
    header("Location: index.php");
    exit();
}

// جلب صلاحيات الدور
$stmt = $db->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
$stmt->execute([$user['role_id']]);
$rolePermissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

// الحصول على صلاحيات المستخدم الحالية
$stmt = $db->prepare("SELECT permission_id FROM user_permissions WHERE user_id = ?");
$stmt->execute([$userId]);
$userPermissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

// دمج صلاحيات الدور مع صلاحيات المستخدم
$combinedPermissions = array_unique(array_merge($userPermissions, $rolePermissions));

// الحصول على قائمة الصلاحيات المتاحة
$stmt = $db->query("SELECT permission_id, permission_name, description FROM permissions ORDER BY permission_name");
$permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// معالجة النموذج عند الإرسال
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    error_log(print_r($_POST, true)); // تسجيل البيانات المرسلة
    $fullName = $_POST['full_name'];
    $email = $_POST['email'];
    $role = $_POST['role_id'];
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $newPassword = $_POST['password'];
    $forcePasswordChange = isset($_POST['force_password_change']) ? 1 : 0;
    $tempPassword = null;
    $selectedPermissions = isset($_POST['permissions']) ? $_POST['permissions'] : [];

    try {
        $db->beginTransaction();

        // جلب حالة المستخدم الحالية
        $stmt = $db->prepare("SELECT is_active FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $currentStatus = $stmt->fetchColumn();

        // إذا تم تفعيل الحساب من حالة غير نشط
        if ($isActive && !$currentStatus) {
            $forcePasswordChange = 1; // إجبار تغيير كلمة المرور
            // إنشاء كلمة مرور مؤقتة
            $tempPassword = generateRandomPassword();
            $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("
                UPDATE users 
                SET 
                    full_name = ?, 
                    email = ?, 
                    role_id = ?, 
                    is_active = ?,
                    password = ?,
                    force_password_change = TRUE,
                    temp_password = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$fullName, $email, $role, $isActive, $hashedPassword, $tempPassword, $userId]);

            // إرسال بريد إلكتروني للمستخدم بكلمة المرور المؤقتة
            require_once '../includes/Mailer.php';
            $mailer = Mailer::getInstance();
            $emailBody = "
                <h2>إعادة تفعيل حسابك</h2>
                <p>مرحباً {$fullName},</p>
                <p>تم إعادة تفعيل حسابك في النظام.</p>
                <p>كلمة المرور المؤقتة الخاصة بك هي:</p>
                <p> <strong>{$tempPassword}</strong></p>
                <p>سيتم توجيهك لتغيير كلمة المرور الخاصة بك عند تسجيل الدخول.</p>
            ";
            
            try {
                $mailer->send(
                    $email,
                    $fullName,
                    'إعادة تفعيل الحساب',
                    $emailBody
                );
            } catch (Exception $e) {
                error_log("خطأ في إرسال البريد الإلكتروني: " . $e->getMessage());
                // نستمر في العملية حتى لو فشل إرسال البريد
            }
        } else {
            // تحديث عادي للبيانات
            if ($newPassword) {
                // تحديث مع كلمة المرور
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $db->prepare("
                    UPDATE users 
                    SET 
                        full_name = ?, 
                        email = ?, 
                        role_id = ?, 
                        is_active = ?,
                        password = ?,
                        force_password_change = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$fullName, $email, $role, $isActive, $hashedPassword, $forcePasswordChange, $userId]);
            } else {
                // تحديث بدون كلمة المرور
                $stmt = $db->prepare("
                    UPDATE users 
                    SET 
                        full_name = ?, 
                        email = ?, 
                        role_id = ?,
                        is_active = ?,
                        force_password_change = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$fullName, $email, $role, $isActive, $forcePasswordChange, $userId]);
            }
        }

        // حذف الصلاحيات القديمة
        $stmt = $db->prepare("DELETE FROM user_permissions WHERE user_id = ?");
        $stmt->execute([$userId]);

        // إضافة الصلاحيات الجديدة
        if (!empty($selectedPermissions)) {
            $stmt = $db->prepare("INSERT INTO user_permissions (user_id, permission_id) VALUES (?, ?)");
            foreach ($selectedPermissions as $permissionId) {
                $stmt->execute([$userId, $permissionId]);
            }
        }

        $db->commit();
        $_SESSION['success'] = "تم تحديث بيانات المستخدم والصلاحيات بنجاح.";
        
        // رسالة نجاح مخصصة
        if ($isActive && !$currentStatus && $tempPassword) {
            $_SESSION['success'] = "تم تفعيل الحساب وإرسال كلمة المرور المؤقتة إلى البريد الإلكتروني للمستخدم.";
        }
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "حدث خطأ أثناء تحديث بيانات المستخدم: " . $e->getMessage();
    }
    header("Location: edit.php?id=$userId");
    exit();
}

?>
<!-- تضمين الهيدر والسايدبار -->

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<!-- المحتوى الرئيسي -->
<div class="main-content">
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">تعديل بيانات المستخدم</h3>
                    </div>
                        <div class="card-body">
                            <!-- عرض الرسائل -->
                            <?php
                            $messages = getMessages();
                            if (!empty($messages['error'])): ?>
                                <div class="alert alert-danger"><?php echo $messages['error']; ?></div>
                            <?php endif; ?>
                            <?php if (!empty($messages['success'])): ?>
                                <div class="alert alert-success"><?php echo $messages['success']; ?></div>
                            <?php endif; ?>

                            <form action="" method="POST" class="needs-validation" novalidate>
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="username" class="form-label">اسم المستخدم</label>
                                            <input type="text" class="form-control" id="username" name="username" 
                                                   value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                            <div class="invalid-feedback">يرجى إدخال اسم المستخدم</div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="full_name" class="form-label">الاسم الكامل</label>
                                            <input type="text" class="form-control" id="full_name" name="full_name" 
                                                   value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                            <div class="invalid-feedback">يرجى إدخال الاسم الكامل</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="email" class="form-label">البريد الإلكتروني</label>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                            <div class="invalid-feedback">يرجى إدخال بريد إلكتروني صحيح</div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="password" class="form-label">كلمة المرور (اتركها فارغة إذا لم ترد تغييرها)</label>
                                            <input type="password" class="form-control" id="password" name="password">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="role" class="form-label">الدور</label>
                                            <select class="form-select" id="role" name="role_id" required>
                                                <option value="">اختر الدور</option>
                                                <?php foreach ($roles as $role): ?>
                                                    <option value="<?php echo $role['role_id']; ?>" 
                                                            <?php echo ($user['role_id'] == $role['role_id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($role['role_name']); ?>
                                                        <?php if (!empty($role['description'])): ?>
                                                            - <?php echo htmlspecialchars($role['description']); ?>
                                                        <?php endif; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="invalid-feedback">يرجى اختيار الدور</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" <?php echo $user['is_active'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="is_active">حساب نشط</label>
                                            </div>
                                            <?php if (!$user['is_active']): ?>
                                                <small class="text-muted">
                                                    عند تفعيل الحساب، سيتم إنشاء كلمة مرور مؤقتة وإرسالها إلى البريد الإلكتروني للمستخدم.
                                                    سيُطلب من المستخدم تغيير كلمة المرور عند أول تسجيل دخول.
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">الصلاحيات</label>
                                    <div class="row">
                                        <?php foreach ($permissions as $permission): ?>
                                        <div class="col-md-4 mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="permissions[]" 
                                                       value="<?php echo $permission['permission_id']; ?>"
                                                       id="permission_<?php echo $permission['permission_id']; ?>"
                                                       <?php echo in_array($permission['permission_id'], $combinedPermissions) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="permission_<?php echo $permission['permission_id']; ?>">
                                                    <?php echo htmlspecialchars($permission['description'] ?: $permission['permission_name']); ?>
                                                </label>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="text-end">
                                    <a href="index.php" class="btn btn-secondary">إلغاء</a>
                                    <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function fetchRolePermissions(roleId) {
    fetch(`fetch_permissions.php?role_id=${roleId}`)
        .then(response => response.json())
        .then(data => {
            const permissionsCheckboxes = document.querySelectorAll('input[name="permissions[]"]');
            permissionsCheckboxes.forEach(checkbox => {
                checkbox.checked = data.permissions.includes(parseInt(checkbox.value));
            });
        })
        .catch(error => console.error('Error fetching permissions:', error));
}

document.getElementById('role').addEventListener('change', function() {
    const selectedRoleId = this.value;
    fetchRolePermissions(selectedRoleId);
});
</script>

<script>
// تفعيل التحقق من صحة النموذج
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms)
        .forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                form.classList.add('was-validated')
            }, false)
        })
})()
</script>

<?php require_once '../includes/footer.php'; ?>
