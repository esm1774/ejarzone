<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/helpers.php';

// التحقق من الصلاحيات
if (!hasPermission('add_users')) {
    $_SESSION['error'] = "ليس لديك صلاحية للوصول إلى هذه الصفحة";
    header("Location: index.php");
    exit();
}



// إنشاء اتصال بقاعدة البيانات
$database = new Database();
$db = $database->getConnection();

// الحصول على قائمة الأدوار
$stmt = $db->query("SELECT role_id, role_name, description FROM roles ORDER BY role_name");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// الحصول على قائمة الصلاحيات المتاحة
$stmt = $db->query("SELECT permission_id, permission_name, description FROM permissions ORDER BY permission_name");
$permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!-- تضمين الهيدر والسايدبار -->

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<!-- المحتوى الرئيسي -->
<div class="main-content">
    <div class="container-fluid py-4">
        <div class="content-wrapper">
            <div class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1 class="m-0">إضافة مستخدم جديد</h1>
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
                                    <h3 class="card-title">بيانات المستخدم الجديد</h3>
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
                                        <input type="hidden" name="action" value="add">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="username" class="form-label">اسم المستخدم</label>
                                                    <input type="text" class="form-control" id="username" name="username" required>
                                                    <div class="invalid-feedback">يرجى إدخال اسم المستخدم</div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="full_name" class="form-label">الاسم الكامل</label>
                                                    <input type="text" class="form-control" id="full_name" name="full_name" required>
                                                    <div class="invalid-feedback">يرجى إدخال الاسم الكامل</div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="email" class="form-label">البريد الإلكتروني</label>
                                                    <input type="email" class="form-control" id="email" name="email" required>
                                                    <div class="invalid-feedback">يرجى إدخال بريد إلكتروني صحيح</div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="password" class="form-label">كلمة المرور</label>
                                                    <input type="password" class="form-control" id="password" name="password" required>
                                                    <div class="invalid-feedback">يرجى إدخال كلمة المرور</div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="role" class="form-label">الدور</label>
                                            <select name="role_id" id="role" class="form-control" required>
                                                <option value="">اختر الدور</option>
                                                <?php foreach ($roles as $role): ?>
                                                <option value="<?php echo $role['role_id']; ?>">
                                                    <?php echo htmlspecialchars($role['role_name'] . ' (' . ($role['description'] ?: $role['role_name']) . ')'); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
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
                                                            id="permission_<?php echo $permission['permission_id']; ?>">
                                                        <label class="form-check-label" for="permission_<?php echo $permission['permission_id']; ?>">
                                                            <?php echo htmlspecialchars($permission['permission_name'] . ' (' . ($permission['description'] ?: $permission['permission_name']) . ')'); ?>
                                                        </label>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>

                                        <div class="text-end">
                                            <a href="index.php" class="btn btn-secondary">إلغاء</a>
                                            <button type="submit" class="btn btn-primary">إضافة المستخدم</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
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
