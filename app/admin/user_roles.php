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

// تحديث أدوار المستخدم
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user_roles'])) {
    $userId = $_POST['user_id'];
    $roles = isset($_POST['roles']) ? $_POST['roles'] : [];

    try {
        $pdo->beginTransaction();

        // حذف الأدوار القديمة
        $stmt = $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?");
        $stmt->execute([$userId]);

        // إضافة الأدوار الجديدة
        if (!empty($roles)) {
            $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
            foreach ($roles as $roleId) {
                $stmt->execute([$userId, $roleId]);
            }
        }

        $pdo->commit();
        $_SESSION['success'] = "تم تحديث أدوار المستخدم بنجاح";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "حدث خطأ أثناء تحديث أدوار المستخدم";
    }
    
    header('Location: user_roles.php');
    exit;
}

// جلب جميع المستخدمين
$stmt = $pdo->query("
    SELECT id, username, email, full_name, 
           CASE WHEN is_active = 1 THEN 'نشط' ELSE 'غير نشط' END as status
    FROM users 
    ORDER BY username
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب جميع الأدوار
$stmt = $pdo->query("SELECT * FROM roles ORDER BY name");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب أدوار كل مستخدم
$userRoles = [];
$stmt = $pdo->query("SELECT user_id, role_id FROM user_roles");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (!isset($userRoles[$row['user_id']])) {
        $userRoles[$row['user_id']] = [];
    }
    $userRoles[$row['user_id']][] = $row['role_id'];
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة أدوار المستخدمين - الصرح</title>
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

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">إدارة أدوار المستخدمين</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>اسم المستخدم</th>
                                <th>الاسم الكامل</th>
                                <th>البريد الإلكتروني</th>
                                <th>الحالة</th>
                                <th>الأدوار الحالية</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo $user['status']; ?></td>
                                <td>
                                    <?php
                                    if (isset($userRoles[$user['id']])) {
                                        $userRoleNames = array_filter($roles, function($r) use ($userRoles, $user) {
                                            return in_array($r['id'], $userRoles[$user['id']]);
                                        });
                                        echo implode(', ', array_map(function($r) {
                                            return $r['name'];
                                        }, $userRoleNames));
                                    }
                                    ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-primary btn-sm" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editUserRolesModal"
                                            data-user-id="<?php echo $user['id']; ?>"
                                            data-user-name="<?php echo htmlspecialchars($user['username']); ?>"
                                            data-user-roles="<?php echo isset($userRoles[$user['id']]) ? implode(',', $userRoles[$user['id']]) : ''; ?>">
                                        <i class="bi bi-pencil"></i> تعديل الأدوار
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

    <!-- Modal تعديل أدوار المستخدم -->
    <div class="modal fade" id="editUserRolesModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="modal-header">
                        <h5 class="modal-title">تعديل أدوار المستخدم: <span id="edit_user_name"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">الأدوار</label>
                            <div class="row">
                                <?php foreach ($roles as $role): ?>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input edit-role" type="checkbox" 
                                               name="roles[]" 
                                               value="<?php echo $role['id']; ?>" 
                                               id="role_<?php echo $role['id']; ?>">
                                        <label class="form-check-label" for="role_<?php echo $role['id']; ?>">
                                            <?php echo htmlspecialchars($role['name']); ?>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="update_user_roles" class="btn btn-primary">حفظ التغييرات</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // تحديث بيانات modal تعديل الأدوار
        document.getElementById('editUserRolesModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const userName = button.getAttribute('data-user-name');
            const userRoles = button.getAttribute('data-user-roles').split(',');

            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_user_name').textContent = userName;

            // إعادة تعيين جميع checkboxes
            document.querySelectorAll('.edit-role').forEach(checkbox => {
                checkbox.checked = userRoles.includes(checkbox.value);
            });
        });
    </script>
</body>
</html>
