<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

include_once "../config/database.php";
$database = new Database();
$pdo = $database->getConnection();

// استعلام لجلب جميع الوحدات
$query = "SELECT * FROM units ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute();
?>

<?php include '../includes/header.php'; ?>

<!-- المحتوى الرئيسي -->
<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">إدارة الوحدات</h5>
            <a href="add.php" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i>
                إضافة وحدة جديدة
            </a>
        </div>
        <div class="card-body">
            <!-- جدول الوحدات -->
            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>اسم الوحدة</th>
                            <th>المبنى</th>
                            <th>الطابق</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // استعلام قاعدة البيانات
                        $sql = "SELECT * FROM units ORDER BY unit_name";
                        $stmt = $pdo->query($sql);
                        
                        $counter = 1;
                        while ($row = $stmt->fetch()) {
                            echo "<tr>";
                            echo "<td>" . $counter . "</td>";
                            $counter++;
                            echo "<td>{$row['unit_name']}</td>";
                            echo "<td>{$row['building']}</td>";
                            echo "<td>{$row['floor']}</td>";
                            echo "<td>";
                            if ($row['status'] == 'متاح') {
                                echo '<span class="badge bg-success">متاح</span>';
                            } else {
                                echo '<span class="badge bg-danger">غير متاح</span>';
                            }
                            echo "</td>";

                            echo "<td>";
                                echo "<div class='btn-group' role='group'>";
                                    echo "<a href='view.php?id={$row['unit_id']}' class='btn btn-sm btn-info' title='عرض'><i class='bi bi-eye'></i></a>";
                                    echo "<a href='edit.php?id={$row['unit_id']}' class='btn btn-sm btn-warning' title='تعديل'><i class='bi bi-pencil'></i></a>";
                                    echo "<button onclick='confirmDelete({$row['unit_id']})' class='btn btn-sm btn-danger' title='حذف'><i class='bi bi-trash'></i></button>";
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

<!-- سكريبت تأكيد الحذف -->
<script>
function confirmDelete(id) {
    if (confirm('هل أنت متأكد من حذف هذه الوحدة؟')) {
        window.location.href = 'delete.php?id=' + id;
    }
}
</script>

<?php include '../includes/footer.php'; ?>
