<?php
require_once '../config/config.php';
require_once '../includes/database.php';
require_once '../includes/helpers.php';

// التحقق من حالة الجلسة قبل بدئها
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// التحقق من تسجيل الدخول والصلاحيات
if (!isset($_SESSION['user_id']) || !hasPermission('manage_roles')) {
    header('Location: ../login.php');
    exit();
}

$db = getDatabaseConnection();

// معالجة إضافة/تعديل الدور
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            $db->beginTransaction();
            
            if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
                $roleName = trim($_POST['role_name']);
                $description = trim($_POST['description']);
                $permissions = isset($_POST['permissions']) ? $_POST['permissions'] : [];
                
                // التحقق من صحة البيانات
                if (empty($roleName)) {
                    throw new Exception('اسم الدور مطلوب');
                }
                
                if ($_POST['action'] === 'add') {
                    // إضافة دور جديد
                    $stmt = $db->prepare("INSERT INTO roles (role_name, description) VALUES (?, ?)");
                    $stmt->execute([$roleName, $description]);
                    $roleId = $db->lastInsertId();
                } else {
                    // تحديث دور موجود
                    $roleId = $_POST['role_id'];
                    $stmt = $db->prepare("UPDATE roles SET role_name = ?, description = ? WHERE role_id = ?");
                    $stmt->execute([$roleName, $description, $roleId]);
                    
                    // حذف الصلاحيات القديمة
                    $stmt = $db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
                    $stmt->execute([$roleId]);
                }
                
                // إضافة الصلاحيات الجديدة
                if (!empty($permissions)) {
                    $stmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                    foreach ($permissions as $permissionId) {
                        $stmt->execute([$roleId, $permissionId]);
                    }
                }
                
                $db->commit();
                $_SESSION['success'] = $_POST['action'] === 'add' ? 'تم إضافة الدور بنجاح' : 'تم تحديث الدور بنجاح';
            }
            
            elseif ($_POST['action'] === 'delete' && isset($_POST['role_id'])) {
                $roleId = $_POST['role_id'];
                
                // التحقق من عدم وجود مستخدمين مرتبطين بهذا الدور
                $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role_id = ?");
                $stmt->execute([$roleId]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('لا يمكن حذف هذا الدور لأنه مرتبط بمستخدمين');
                }
                
                // حذف الدور
                $stmt = $db->prepare("DELETE FROM roles WHERE role_id = ?");
                $stmt->execute([$roleId]);
                
                $db->commit();
                $_SESSION['success'] = 'تم حذف الدور بنجاح';
            }
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = $e->getMessage();
        }
    }
    
    header('Location: manage_roles.php');
    exit();
}

// جلب قائمة الأدوار
$roles = $db->query("SELECT * FROM roles ORDER BY role_name")->fetchAll(PDO::FETCH_ASSOC);

// جلب قائمة الصلاحيات
$permissions = $db->query("SELECT * FROM permissions ORDER BY permission_name")->fetchAll(PDO::FETCH_ASSOC);

// جلب صلاحيات الدور المحدد
$selectedRoleId = isset($_GET['role_id']) ? $_GET['role_id'] : null;
$rolePermissions = [];
if ($selectedRoleId) {
    $stmt = $db->prepare("
        SELECT permission_id 
        FROM role_permissions 
        WHERE role_id = ?
    ");
    $stmt->execute([$selectedRoleId]);
    $rolePermissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// تضمين الهيدر
$pageTitle = "إدارة الأدوار والصلاحيات";
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
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title">إدارة الأدوار</h3>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#roleModal">
                                <i class="bi bi-plus-lg"></i>
                                إضافة دور جديد
                            </button>
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

                            <!-- جدول الأدوار -->
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>اسم الدور</th>
                                            <th>الوصف</th>
                                            <th>عدد الصلاحيات</th>
                                            <th>تاريخ الإنشاء</th>
                                            <th>آخر تحديث</th>
                                            <th>الإجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($roles as $role): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($role['role_name']); ?></td>
                                                <td><?php echo htmlspecialchars($role['description']); ?></td>
                                                <td>
                                                    <?php
                                                    $stmt = $db->prepare("SELECT COUNT(*) FROM role_permissions WHERE role_id = ?");
                                                    $stmt->execute([$role['role_id']]);
                                                    echo $stmt->fetchColumn();
                                                    ?>
                                                </td>
                                                <td><?php echo isset($role['created_at']) ? date('Y-m-d H:i', strtotime($role['created_at'])) : 'غير متوفر'; ?></td>
                                                <td><?php echo isset($role['updated_at']) ? date('Y-m-d H:i', strtotime($role['updated_at'])) : 'غير متوفر'; ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-info edit-role" 
                                                            data-role-id="<?php echo $role['role_id']; ?>"
                                                            data-role-name="<?php echo htmlspecialchars($role['role_name']); ?>"
                                                            data-description="<?php echo htmlspecialchars($role['description']); ?>">
                                                        <i class="bi bi-pencil"></i>
                                                        تعديل
                                                    </button>
                                                    <?php if ($role['role_name'] !== 'admin'): ?>
                                                        <button type="button" class="btn btn-sm btn-danger delete-role"
                                                                data-role-id="<?php echo $role['role_id']; ?>"
                                                                data-role-name="<?php echo htmlspecialchars($role['role_name']); ?>">
                                                            <i class="bi bi-trash"></i>
                                                            حذف
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal إضافة/تعديل دور -->
<div class="modal fade" id="roleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="roleForm" method="post">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="role_id" value="">
                
                <div class="modal-header">
                    <h5 class="modal-title">إضافة دور جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="role_name" class="form-label">اسم الدور</label>
                        <input type="text" class="form-control" id="role_name" name="role_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">الوصف</label>
                        <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">الصلاحيات</label>
                        <div class="row">
                            <?php foreach ($permissions as $permission): ?>
                                <div class="col-md-4 mb-2">
                                    <div class="form-check custom-checkbox">
                                        <input type="checkbox" 
                                               class="form-check-input" 
                                               name="permissions[]" 
                                               value="<?php echo $permission['permission_id']; ?>"
                                               id="perm_<?php echo $permission['permission_id']; ?>">
                                        <label class="form-check-label" for="perm_<?php echo $permission['permission_id']; ?>">
                                            <?php echo $permission['description'] ?: $permission['permission_name']; ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">حفظ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal تأكيد الحذف -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="role_id" id="delete_role_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">تأكيد الحذف</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <p>هل أنت متأكد من حذف الدور: <strong id="delete_role_name"></strong>؟</p>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-danger">حذف</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // تهيئة النوافذ المنبثقة
    const roleModal = document.getElementById('roleModal');
    const deleteModal = document.getElementById('deleteModal');
    
    // معالجة زر التعديل
    $('.edit-role').click(function(e) {
        e.preventDefault();
        const roleId = $(this).data('role-id');
        const roleName = $(this).data('role-name');
        const description = $(this).data('description');
        
        // تعيين قيم النموذج
        $('#roleForm input[name="action"]').val('edit');
        $('#roleForm input[name="role_id"]').val(roleId);
        $('#roleForm input[name="role_name"]').val(roleName);
        $('#roleForm textarea[name="description"]').val(description);
        $('#roleModal .modal-title').text('تعديل الدور');
        
        // جلب صلاحيات الدور
        $.get('get_role_permissions.php', { role_id: roleId })
            .done(function(permissions) {
                // إعادة تعيين جميع مربعات الاختيار أولاً
                $('input[name="permissions[]"]').prop('checked', false);
                
                // تحديد الصلاحيات المرتبطة بالدور
                if (Array.isArray(permissions)) {
                    permissions.forEach(function(permissionId) {
                        $(`#perm_${permissionId}`).prop('checked', true);
                    });
                }
                
                // فتح النافذة المنبثقة
                new bootstrap.Modal(roleModal).show();
            })
            .fail(function(jqXHR, textStatus, errorThrown) {
                alert('حدث خطأ أثناء جلب صلاحيات الدور');
            });
    });
    
    // معالجة زر الحذف
    $('.delete-role').click(function(e) {
        e.preventDefault();
        const roleId = $(this).data('role-id');
        const roleName = $(this).data('role-name');
        
        $('#delete_role_id').val(roleId);
        $('#delete_role_name').text(roleName);
        
        new bootstrap.Modal(deleteModal).show();
    });
    
    // إعادة تعيين النموذج عند فتح نافذة إضافة دور جديد
    $(document).on('click', '[data-bs-target="#roleModal"]:not(.edit-role)', function() {
        $('#roleForm')[0].reset();
        $('#roleForm input[name="action"]').val('add');
        $('#roleForm input[name="role_id"]').val('');
        $('#roleModal .modal-title').text('إضافة دور جديد');
        $('input[name="permissions[]"]').prop('checked', false);
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
