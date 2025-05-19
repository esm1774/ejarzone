<?php
require_once '../config/config.php';
require_once '../includes/database.php';
require_once '../includes/helpers.php';

// التحقق من حالة الجلسة قبل بدئها
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// التحقق من تسجيل الدخول والصلاحيات
if (!isset($_SESSION['user_id']) || !hasPermission('manage_permissions')) {
    header('Location: ../login.php');
    exit();
}

// معالجة النموذج عند الإرسال
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_POST['user_id'];
    $permissionNames = isset($_POST['permission_names']) ? $_POST['permission_names'] : [];

    try {
        $db = getDatabaseConnection();
        
        // بدء معاملة قاعدة البيانات
        $db->beginTransaction();
        
        // حذف كل الصلاحيات الحالية للمستخدم
        $stmt = $db->prepare("DELETE FROM user_permissions WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // إضافة الصلاحيات الجديدة
        $errors = [];
        foreach ($permissionNames as $permissionName) {
            // جلب permission_id من جدول permissions
            $stmtPerm = $db->prepare("SELECT permission_id FROM permissions WHERE permission_name = ?");
            $stmtPerm->execute([$permissionName]);
            $permissionId = $stmtPerm->fetchColumn();
            
            if ($permissionId === false) {
                $errors[] = "الصلاحية '{$permissionName}' غير موجودة.";
                continue;
            }
            
            // إضافة الصلاحية للمستخدم
            $stmtInsert = $db->prepare("INSERT INTO user_permissions (user_id, permission_id) VALUES (?, ?)");
            if (!$stmtInsert->execute([$userId, $permissionId])) {
                $errors[] = "حدث خطأ أثناء إضافة الصلاحية '{$permissionName}'.";
            }
        }
        
        // إذا لم يكن هناك أخطاء، قم بتأكيد التغييرات
        if (empty($errors)) {
            $db->commit();
            $_SESSION['success'] = "تم تحديث صلاحيات المستخدم بنجاح.";
        } else {
            // إذا كانت هناك أخطاء، تراجع عن التغييرات
            $db->rollBack();
            $_SESSION['error'] = implode('<br>', $errors);
        }
    } catch (Exception $e) {
        // تراجع عن التغييرات في حالة حدوث خطأ غير متوقع
        $db->rollBack();
        $_SESSION['error'] = "حدث خطأ أثناء المعالجة: " . $e->getMessage();
    }
    
    // إعادة التوجيه إلى الصفحة
    header("Location: manage_user_permissions.php");
    exit();
}

// استرجاع قائمة المستخدمين والصلاحيات
$db = getDatabaseConnection();
$users = $db->query("SELECT user_id, full_name, email FROM users")->fetchAll(PDO::FETCH_ASSOC);
$permissions = $db->query("SELECT * FROM permissions")->fetchAll(PDO::FETCH_ASSOC);

// استرجاع صلاحيات المستخدم المحدد
$selectedUserId = isset($_GET['user_id']) ? $_GET['user_id'] : null;
$userPermissions = [];
if ($selectedUserId) {
    $stmt = $db->prepare("
        SELECT p.permission_name 
        FROM user_permissions up 
        JOIN permissions p ON up.permission_id = p.permission_id 
        WHERE up.user_id = ?
    ");
    $stmt->execute([$selectedUserId]);
    $userPermissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// تضمين الهيدر
$pageTitle = "إدارة صلاحيات المستخدمين";
require_once '../includes/header.php';
?>
    <?php require_once '../includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="content-wrapper">
    
    <div class="content-container">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">إدارة صلاحيات المستخدم</h3>
                        </div>
                        <div class="card-body">
                            <?php if (isset($_SESSION['success'])): ?>
                                <div class="alert alert-success">
                                    <?php 
                                    echo $_SESSION['success'];
                                    unset($_SESSION['success']);
                                    ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($_SESSION['error'])): ?>
                                <div class="alert alert-danger">
                                    <?php 
                                    echo $_SESSION['error'];
                                    unset($_SESSION['error']);
                                    ?>
                                </div>
                            <?php endif; ?>

                            <!-- نموذج اختيار المستخدم -->
                            <form method="get" class="mb-4">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="user_id">اختر المستخدم:</label>
                                            <select name="user_id" id="user_id" class="form-control select2" onchange="this.form.submit()">
                                                <option value="">-- اختر المستخدم --</option>
                                                <?php foreach ($users as $user): ?>
                                                    <option value="<?php echo $user['user_id']; ?>" <?php echo ($selectedUserId == $user['user_id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($user['full_name'] . ' (' . $user['email'] . ')'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </form>

                            <?php if ($selectedUserId): ?>
                                <!-- نموذج تحديث الصلاحيات -->
                                <form method="post">
                                    <input type="hidden" name="user_id" value="<?php echo $selectedUserId; ?>">
                                    
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label>الصلاحيات:</label>
                                                <div class="row">
                                                    <?php foreach ($permissions as $permission): ?>
                                                        <div class="col-md-4 mb-2">
                                                            <div class="form-check custom-checkbox">
                                                                <input type="checkbox" 
                                                                       class="form-check-input" 
                                                                       name="permission_names[]" 
                                                                       value="<?php echo $permission['permission_name']; ?>"
                                                                       id="perm_<?php echo $permission['permission_id']; ?>"
                                                                       <?php echo in_array($permission['permission_name'], $userPermissions) ? 'checked' : ''; ?>>
                                                                <label class="form-check-label" for="perm_<?php echo $permission['permission_id']; ?>">
                                                                    <?php echo $permission['description'] ?: $permission['permission_name']; ?>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-3">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-check-lg"></i>
                                            حفظ الصلاحيات
                                        </button>
                                        <a href="manage_user_permissions.php" class="btn btn-secondary">
                                            <i class="bi bi-x-lg"></i>
                                            إلغاء
                                        </a>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // تهيئة Select2
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%'
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
