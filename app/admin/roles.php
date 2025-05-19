<?php
session_start();
require_once '../config/config.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';

// التحقق من تسجيل الدخول وصلاحيات المستخدم
if (!isAuthenticated() || !hasPermission('manage_roles')) {
    header('Location: ../login.php');
    exit;
}

// إضافة دور جديد
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_role'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $permissions = isset($_POST['permissions']) ? $_POST['permissions'] : [];

    try {
        $pdo->beginTransaction();

        // إضافة الدور
        $stmt = $pdo->prepare("INSERT INTO roles (name, description) VALUES (?, ?)");
        $stmt->execute([$name, $description]);
        $roleId = $pdo->lastInsertId();

        // إضافة الصلاحيات للدور
        if (!empty($permissions)) {
            $stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            foreach ($permissions as $permissionId) {
                $stmt->execute([$roleId, $permissionId]);
            }
        }

        $pdo->commit();
        $_SESSION['success'] = "تم إضافة الدور بنجاح";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "حدث خطأ أثناء إضافة الدور";
    }
    
    header('Location: roles.php');
    exit;
}

// تحديث دور
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_role'])) {
    $roleId = $_POST['role_id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $permissions = isset($_POST['permissions']) ? $_POST['permissions'] : [];

    try {
        $pdo->beginTransaction();

        // تحديث الدور
        $stmt = $pdo->prepare("UPDATE roles SET name = ?, description = ? WHERE id = ?");
        $stmt->execute([$name, $description, $roleId]);

        // حذف الصلاحيات القديمة
        $stmt = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $stmt->execute([$roleId]);

        // إضافة الصلاحيات الجديدة
        if (!empty($permissions)) {
            $stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            foreach ($permissions as $permissionId) {
                $stmt->execute([$roleId, $permissionId]);
            }
        }

        $pdo->commit();
        $_SESSION['success'] = "تم تحديث الدور بنجاح";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "حدث خطأ أثناء تحديث الدور";
    }
    
    header('Location: roles.php');
    exit;
}

// حذف دور
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_role'])) {
    $roleId = $_POST['role_id'];

    try {
        $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
        $stmt->execute([$roleId]);
        $_SESSION['success'] = "تم حذف الدور بنجاح";
    } catch (PDOException $e) {
        $_SESSION['error'] = "حدث خطأ أثناء حذف الدور";
    }
    
    header('Location: roles.php');
    exit;
}

// جلب جميع الأدوار
$stmt = $pdo->query("SELECT * FROM roles ORDER BY name");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب جميع الصلاحيات
$stmt = $pdo->query("SELECT * FROM permissions ORDER BY name");
$permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب صلاحيات كل دور
$rolePermissions = [];
$stmt = $pdo->query("SELECT role_id, permission_id FROM role_permissions");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (!isset($rolePermissions[$row['role_id']])) {
        $rolePermissions[$row['role_id']] = [];
    }
    $rolePermissions[$row['role_id']][] = $row['permission_id'];
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الأدوار والصلاحيات - الصرح</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/custom.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container mt-5">
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

        <div class="row">
            <!-- قائمة الأدوار -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">الأدوار</h5>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                            <i class="bi bi-plus-lg"></i> إضافة دور جديد
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>الدور</th>
                                        <th>الوصف</th>
                                        <th>الصلاحيات</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($roles as $role): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($role['name']); ?></td>
                                        <td><?php echo htmlspecialchars($role['description']); ?></td>
                                        <td>
                                            <?php
                                            if (isset($rolePermissions[$role['id']])) {
                                                $rolePerms = array_filter($permissions, function($p) use ($rolePermissions, $role) {
                                                    return in_array($p['id'], $rolePermissions[$role['id']]);
                                                });
                                                echo implode(', ', array_map(function($p) {
                                                    return $p['name'];
                                                }, $rolePerms));
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-primary btn-sm" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editRoleModal"
                                                    data-role-id="<?php echo $role['id']; ?>"
                                                    data-role-name="<?php echo htmlspecialchars($role['name']); ?>"
                                                    data-role-description="<?php echo htmlspecialchars($role['description']); ?>"
                                                    data-role-permissions="<?php echo isset($rolePermissions[$role['id']]) ? implode(',', $rolePermissions[$role['id']]) : ''; ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteRoleModal"
                                                    data-role-id="<?php echo $role['id']; ?>"
                                                    data-role-name="<?php echo htmlspecialchars($role['name']); ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- قائمة الصلاحيات -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">الصلاحيات المتاحة</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <?php foreach ($permissions as $permission): ?>
                            <div class="list-group-item">
                                <h6 class="mb-1"><?php echo htmlspecialchars($permission['name']); ?></h6>
                                <small class="text-muted"><?php echo htmlspecialchars($permission['description']); ?></small>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal إضافة دور -->
    <div class="modal fade" id="addRoleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">إضافة دور جديد</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">اسم الدور</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">الوصف</label>
                            <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">الصلاحيات</label>
                            <div class="row">
                                <?php foreach ($permissions as $permission): ?>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               name="permissions[]" 
                                               value="<?php echo $permission['id']; ?>" 
                                               id="perm_<?php echo $permission['id']; ?>">
                                        <label class="form-check-label" for="perm_<?php echo $permission['id']; ?>">
                                            <?php echo htmlspecialchars($permission['name']); ?>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="add_role" class="btn btn-primary">إضافة</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal تعديل دور -->
    <div class="modal fade" id="editRoleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="role_id" id="edit_role_id">
                    <div class="modal-header">
                        <h5 class="modal-title">تعديل دور</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">اسم الدور</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">الوصف</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">الصلاحيات</label>
                            <div class="row">
                                <?php foreach ($permissions as $permission): ?>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input edit-permission" type="checkbox" 
                                               name="permissions[]" 
                                               value="<?php echo $permission['id']; ?>" 
                                               id="edit_perm_<?php echo $permission['id']; ?>">
                                        <label class="form-check-label" for="edit_perm_<?php echo $permission['id']; ?>">
                                            <?php echo htmlspecialchars($permission['name']); ?>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="update_role" class="btn btn-primary">حفظ التغييرات</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal حذف دور -->
    <div class="modal fade" id="deleteRoleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="role_id" id="delete_role_id">
                    <div class="modal-header">
                        <h5 class="modal-title">حذف دور</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>هل أنت متأكد من حذف الدور: <span id="delete_role_name"></span>؟</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="delete_role" class="btn btn-danger">حذف</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // تحديث بيانات modal التعديل
        document.getElementById('editRoleModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const roleId = button.getAttribute('data-role-id');
            const roleName = button.getAttribute('data-role-name');
            const roleDescription = button.getAttribute('data-role-description');
            const rolePermissions = button.getAttribute('data-role-permissions').split(',');

            document.getElementById('edit_role_id').value = roleId;
            document.getElementById('edit_name').value = roleName;
            document.getElementById('edit_description').value = roleDescription;

            // إعادة تعيين جميع checkboxes
            document.querySelectorAll('.edit-permission').forEach(checkbox => {
                checkbox.checked = rolePermissions.includes(checkbox.value);
            });
        });

        // تحديث بيانات modal الحذف
        document.getElementById('deleteRoleModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const roleId = button.getAttribute('data-role-id');
            const roleName = button.getAttribute('data-role-name');

            document.getElementById('delete_role_id').value = roleId;
            document.getElementById('delete_role_name').textContent = roleName;
        });
    </script>
</body>
</html>
