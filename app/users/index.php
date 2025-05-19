<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/helpers.php';

// التحقق من الصلاحيات
if (!hasPermission('view_users')) {
    $_SESSION['error'] = "ليس لديك صلاحية للوصول إلى هذه الصفحة";
    header("Location: ../index.php");
    exit();
}

// جلب معلومات الترتيب
$sort_column = $_GET['sort'] ?? 'u.username';
$sort_direction = $_GET['direction'] ?? 'ASC';

// التحقق من صحة عمود الترتيب
$allowed_columns = [
    'u.username' => 'اسم المستخدم',
    'u.email' => 'البريد الإلكتروني',
    'u.full_name' => 'الاسم الكامل',
    'r.role_name' => 'الدور',
    'u.created_at' => 'تاريخ الإنشاء'
];

if (!array_key_exists($sort_column, $allowed_columns)) {
    $sort_column = 'u.username';
}

// التحقق من صحة اتجاه الترتيب
$sort_direction = strtoupper($sort_direction) === 'DESC' ? 'DESC' : 'ASC';

// إنشاء اتصال بقاعدة البيانات
$database = new Database();
$db = $database->getConnection();

// معالجة البحث والفلترة
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// استعلام قاعدة البيانات
$query = "SELECT u.*, GROUP_CONCAT(COALESCE(p.description, p.permission_name)) as permission_descriptions,
          GROUP_CONCAT(p.permission_name) as permission_names 
          
          FROM users u 
          
          LEFT JOIN roles r ON u.role_id = r.role_id 
          LEFT JOIN user_permissions up ON u.user_id = up.user_id 
          LEFT JOIN permissions p ON up.permission_id = p.permission_id";

$params = [];
$where = [];

if (!empty($search)) {
    $where[] = "(u.username LIKE :search OR u.full_name LIKE :search OR u.email LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($where)) {
    $query .= " WHERE " . implode(' AND ', $where);
}

$query .= " GROUP BY u.user_id ORDER BY {$sort_column} {$sort_direction} LIMIT :offset, :perPage";
$params[':offset'] = $offset;
$params[':perPage'] = $perPage;

// تنفيذ الاستعلام
$stmt = $db->prepare($query);
foreach ($params as $key => &$value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// حساب إجمالي عدد المستخدمين للترقيم
$countQuery = "SELECT COUNT(*) FROM users u";
if (!empty($where)) {
    $countQuery .= " WHERE " . implode(' AND ', $where);
}
$stmt = $db->prepare($countQuery);
$stmt->execute(array_filter($params, function($key) {
    return $key !== ':offset' && $key !== ':perPage';
}, ARRAY_FILTER_USE_KEY));
$totalUsers = $stmt->fetchColumn();
$totalPages = ceil($totalUsers / $perPage);

// الحصول على قائمة الصلاحيات المتاحة
$stmt = $db->query("SELECT * FROM permissions ORDER BY permission_name");
$availablePermissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="card-title">إدارة المستخدمين</h3>
                                <?php if (hasPermission('add_users')): ?>
                                <a href="add.php" class="btn btn-primary">
                                    <i class="bi bi-plus-lg"></i> إضافة مستخدم جديد
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- نموذج البحث -->
                            <form method="GET" class="mb-4">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <input type="text" name="search" class="form-control" 
                                                   placeholder="البحث عن مستخدم..." 
                                                   value="<?php echo htmlspecialchars($search); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-search"></i> بحث
                                        </button>
                                        <?php if (!empty($search)): ?>
                                        <a href="index.php" class="btn btn-secondary">
                                            <i class="bi bi-x-circle"></i> إلغاء البحث
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </form>

                            <!-- عرض الرسائل -->
                            <?php
                            $messages = getMessages();
                            if (!empty($messages['error'])): ?>
                                <div class="alert alert-danger"><?php echo $messages['error']; ?></div>
                            <?php endif; ?>
                            <?php if (!empty($messages['success'])): ?>
                                <div class="alert alert-success"><?php echo $messages['success']; ?></div>
                            <?php endif; ?>

                            <!-- جدول المستخدمين -->
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <?php foreach ($allowed_columns as $column => $title): ?>
                                                <th>
                                                    <a href="?sort=<?php echo $column; ?>&direction=<?php echo ($sort_column === $column && $sort_direction === 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>" 
                                                       class="text-dark text-decoration-none">
                                                        <?php echo $title; ?>
                                                        <?php if ($sort_column === $column): ?>
                                                            <i class="bi bi-arrow-<?php echo $sort_direction === 'ASC' ? 'up' : 'down'; ?>"></i>
                                                        <?php endif; ?>
                                                    </a>
                                                </th>
                                            <?php endforeach; ?>
                                            <th>الحالة</th>
                                            <th>تاريخ الإنشاء</th>
                                            <th>الإجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $index => $user): ?>
                                        <tr>
                                            <td><?php echo $offset + $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['role_id']); ?></td>
                                            <td>
                                                <?php 
                                                $descriptions = explode(',', $user['permission_descriptions']);
                                                foreach ($descriptions as $description) {
                                                    if (!empty($description)) {
                                                        echo '<span class="badge bg-info me-1">' . htmlspecialchars($description) . '</span>';
                                                    }
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php if ($user['is_active']): ?>
                                                    <span class="badge bg-success">نشط</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">غير نشط</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo formatDateArabic($user['created_at']); ?></td>
                                            <td>
                                                <?php if (hasPermission('edit_users')): ?>
                                                <a href="edit.php?id=<?php echo $user['user_id']; ?>" 
                                                   class="btn btn-sm btn-primary">
                                                    <i class="bi bi-pencil"></i> تعديل
                                                </a>
                                                <?php endif; ?>
                                                
                                                <?php if (hasPermission('delete_users') && $user['user_id'] != $_SESSION['user_id']): ?>
                                                <button type="button" class="btn btn-sm btn-danger" 
                                                        onclick="confirmDelete(<?php echo $user['user_id']; ?>)">
                                                    <i class="bi bi-trash"></i> حذف
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($users)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center">لا يوجد مستخدمين</td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- الترقيم -->
                            <?php if ($totalPages > 1): ?>
                            <div class="d-flex justify-content-center mt-4">
                                <nav aria-label="Page navigation">
                                    <ul class="pagination">
                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                        <?php endfor; ?>
                                    </ul>
                                </nav>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal تأكيد الحذف -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تأكيد الحذف</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                هل أنت متأكد من حذف هذا المستخدم؟
            </div>
            <div class="modal-footer">
                <form action="process_user.php" method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-danger">تأكيد الحذف</button>
                </form>
            </div>
        </div>
    </div>
</div>
</div>
<script>
function confirmDelete(userId) {
    document.getElementById('deleteUserId').value = userId;
    new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
}
</script>

<?php require_once '../includes/footer.php'; ?>
