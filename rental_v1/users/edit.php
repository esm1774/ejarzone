<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/helpers.php';

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

// تضمين الهيدر والسايدبار
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// إنشاء اتصال بقاعدة البيانات
$database = new Database();
$db = $database->getConnection();

// الحصول على بيانات المستخدم
$stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $_SESSION['error'] = "المستخدم غير موجود";
    header("Location: index.php");
    exit();
}

// الحصول على صلاحيات المستخدم الحالية
$stmt = $db->prepare("SELECT permission_id FROM user_permissions WHERE user_id = ?");
$stmt->execute([$userId]);
$userPermissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

// الحصول على قائمة الصلاحيات المتاحة
$stmt = $db->query("SELECT * FROM permissions ORDER BY permission_name");
$permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">تعديل المستخدم</h1>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
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

                            <form action="process_user.php" method="POST" class="needs-validation" novalidate>
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
                                            <select class="form-select" id="role" name="role" required>
                                                <option value="">اختر الدور...</option>
                                                <option value="مدير" <?php echo $user['role'] === 'مدير' ? 'selected' : ''; ?>>مدير</option>
                                                <option value="مشرف" <?php echo $user['role'] === 'مشرف' ? 'selected' : ''; ?>>مشرف</option>
                                                <option value="مستخدم" <?php echo $user['role'] === 'مستخدم' ? 'selected' : ''; ?>>مستخدم</option>
                                            </select>
                                            <div class="invalid-feedback">يرجى اختيار الدور</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">الصلاحيات</label>
                                    <div class="row">
                                        <?php foreach ($permissions as $permission): ?>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="permissions[]" 
                                                       value="<?php echo $permission['permission_id']; ?>" 
                                                       id="permission_<?php echo $permission['permission_id']; ?>"
                                                       <?php echo in_array($permission['permission_id'], $userPermissions) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" 
                                                       for="permission_<?php echo $permission['permission_id']; ?>">
                                                    <?php echo htmlspecialchars($permission['permission_name']); ?>
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
