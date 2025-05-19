<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once "../config/config.php";
require_once "../includes/check_permission.php";
require_once "../config/database.php";

$database = new Database();
$db = $database->getConnection();

// استعلام لجلب جميع المستأجرين
$query = "SELECT * FROM tenants ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<!-- المحتوى الرئيسي -->
<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2>إدارة المستأجرين</h2>
            <?php if (hasPermission('add_tenants')): ?>
            <a href="add.php" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i>
                إضافة مستأجر جديد
            </a>
            <?php endif; ?>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php
                switch ($_GET['success']) {
                    case 1:
                        echo "تم إضافة المستأجر بنجاح";
                        break;
                    case 2:
                        echo "تم تحديث بيانات المستأجر بنجاح";
                        break;
                    case 3:
                        echo "تم حذف المستأجر بنجاح";
                        break;
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php
                switch ($_GET['error']) {
                    case 1:
                        echo "لا يمكن حذف المستأجر لوجود عقود مرتبطة به";
                        break;
                    case 2:
                        echo "حدث خطأ أثناء حذف المستأجر";
                        break;
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <div class="card-body">
        <!-- جدول المستأجرين -->
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الاسم الكامل</th>
                        <th>الجنسية</th>
                        <th>رقم الهوية</th>
                        <th>الهاتف</th>
                        <th>البريد الإلكتروني</th>
                        <th>الحالة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $counter = 1;
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        echo "<tr>";
                        echo "<td>" . $counter++ . "</td>";
                        echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['nationality']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['id_number']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                        echo "<td><span class='badge " . ($row['status'] == 'نشط' ? 'bg-success' : 'bg-danger') . "'>" 
                            . htmlspecialchars($row['status']) . "</span></td>";
                        echo "<td>";
                        echo "<div class='btn-group' role='group'>";
                        
                        if (hasPermission('view_tenants')) {
                            echo "<a href='view.php?id=" . $row['tenant_id'] . "' class='btn btn-sm btn-info' title='عرض'>";
                            echo "<i class='bi bi-eye'></i></a>";
                        }
                        
                        if (hasPermission('edit_tenants')) {
                            echo "<a href='edit.php?id=" . $row['tenant_id'] . "' class='btn btn-sm btn-primary' title='تعديل'>";
                            echo "<i class='bi bi-pencil'></i></a>";
                        }
                        
                        if (hasPermission('delete_tenants')) {
                            echo "<button type='button' class='btn btn-sm btn-danger' title='حذف' ";
                            echo "onclick='confirmDelete(" . $row['tenant_id'] . ")'>";
                            echo "<i class='bi bi-trash'></i></button>";
                        }
                        
                        echo "</div>";
                        echo "</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
function confirmDelete(id) {
    if (confirm('هل أنت متأكد من حذف هذا المستأجر؟')) {
        window.location.href = 'delete.php?id=' + id;
    }
}
</script>

<?php include '../includes/footer.php'; ?>
