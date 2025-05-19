<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

include_once "../config/database.php";
$database = new Database();
$db = $database->getConnection();

// استعلام لجلب جميع المستأجرين
$query = "SELECT * FROM tenants ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
?>

<?php include '../includes/header.php'; ?>

<!-- المحتوى الرئيسي -->
<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
                    <h2>إدارة المستأجرين</h2>
                    <a href="add.php" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i>
                        إضافة مستأجر جديد
                    </a>
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
                               echo "<td>
                                    
                                    <div class='btn-group' role='group'>
                                        <a href='view.php?id=" . $row['tenant_id'] . "' class='btn btn-sm btn-info' title='عرض'> <i class='bi bi-eye'></i></a>
                                        <a href='edit.php?id=" . $row['tenant_id'] . "' class='btn btn-sm btn-warning ' title='تعديل'><i class='bi bi-pencil'></i></a>
                                        <a href='delete.php?id=" . $row['tenant_id'] . "' class='btn btn-sm btn-danger ' onclick='return confirm(\"هل أنت متأكد من حذف هذا المستأجر؟\")' title='حذف'><i class='bi bi-trash'></i>
                                        </a>
                                        </div>
                                    </td>";
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


<?php include '../includes/footer.php'; ?>
