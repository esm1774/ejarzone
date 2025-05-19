<?php
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../config/database.php';

// إنشاء اتصال بقاعدة البيانات
$database = new Database();
$db = $database->getConnection();

// جلب أنواع الإيرادات
$types = $db->query("SELECT * FROM revenue_types ORDER BY type_name");
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">أنواع الإيرادات</h1>
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
                            <div class="d-flex justify-content-between align-items-center">
                                <h3 class="card-title">قائمة أنواع الإيرادات</h3>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" 
                                        data-bs-target="#addTypeModal">
                                    <i class="bi bi-plus-lg"></i> إضافة نوع جديد
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>النوع</th>
                                            <th>الوصف</th>
                                            <th>الإجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($type = $types->fetch(PDO::FETCH_ASSOC)): ?>
                                        <tr>
                                            <td><?php echo $type['type_id']; ?></td>
                                            <td><?php echo htmlspecialchars($type['type_name']); ?></td>
                                            <td><?php echo htmlspecialchars($type['description']) ?: '-'; ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" 
                                                            class="btn btn-sm btn-warning edit-type"
                                                            data-id="<?php echo $type['type_id']; ?>"
                                                            data-name="<?php echo htmlspecialchars($type['type_name']); ?>"
                                                            data-description="<?php echo htmlspecialchars($type['description']); ?>">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-danger delete-type"
                                                            data-id="<?php echo $type['type_id']; ?>">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
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

<!-- Modal إضافة نوع جديد -->
<div class="modal fade" id="addTypeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="addTypeForm">
                <input type="hidden" name="action" value="add_type">
                
                <div class="modal-header">
                    <h5 class="modal-title">إضافة نوع إيراد جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="type_name" class="form-label">اسم النوع *</label>
                        <input type="text" class="form-control" id="type_name" name="type_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">الوصف</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
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

<!-- Modal تعديل النوع -->
<div class="modal fade" id="editTypeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editTypeForm">
                <input type="hidden" name="action" value="edit_type">
                <input type="hidden" name="type_id" id="edit_type_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">تعديل نوع الإيراد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_type_name" class="form-label">اسم النوع *</label>
                        <input type="text" class="form-control" id="edit_type_name" name="type_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">الوصف</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal حذف النوع -->
<div class="modal fade" id="deleteTypeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تأكيد الحذف</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                هل أنت متأكد من حذف هذا النوع؟
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">حذف</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // إضافة نوع جديد
    $('#addTypeForm').submit(function(e) {
        e.preventDefault();
        
        $.ajax({
            url: 'process_revenue.php',
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('حدث خطأ: ' + response.message);
                }
            },
            error: function() {
                alert('حدث خطأ في الاتصال بالخادم');
            }
        });
    });

    // تعديل النوع
    $('.edit-type').click(function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        var description = $(this).data('description');

        $('#edit_type_id').val(id);
        $('#edit_type_name').val(name);
        $('#edit_description').val(description);

        $('#editTypeModal').modal('show');
    });

    $('#editTypeForm').submit(function(e) {
        e.preventDefault();
        
        $.ajax({
            url: 'process_revenue.php',
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('حدث خطأ: ' + response.message);
                }
            },
            error: function() {
                alert('حدث خطأ في الاتصال بالخادم');
            }
        });
    });

    // حذف النوع
    let typeIdToDelete = null;
    
    $('.delete-type').click(function() {
        typeIdToDelete = $(this).data('id');
        $('#deleteTypeModal').modal('show');
    });
    
    $('#confirmDelete').click(function() {
        if (typeIdToDelete) {
            $.ajax({
                url: 'process_revenue.php',
                type: 'POST',
                data: {
                    action: 'delete_type',
                    type_id: typeIdToDelete
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('حدث خطأ: ' + response.message);
                    }
                },
                error: function() {
                    alert('حدث خطأ في الاتصال بالخادم');
                }
            });
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
