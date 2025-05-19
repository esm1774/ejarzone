<?php
require_once "../config/config.php";
require_once "../includes/check_permission.php";
require_once "../config/database.php";

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // إعداد الاستعلام
        $query = "INSERT INTO tenants (full_name, nationality, id_number, phone, email, status) 
                  VALUES (:full_name, :nationality, :id_number, :phone, :email, :status)";
        
        $stmt = $db->prepare($query);
        
        // ربط القيم
        $stmt->bindParam(":full_name", $_POST['full_name']);
        $stmt->bindParam(":nationality", $_POST['nationality']);
        $stmt->bindParam(":id_number", $_POST['id_number']);
        $stmt->bindParam(":phone", $_POST['phone']);
        $stmt->bindParam(":email", $_POST['email']);
        $stmt->bindParam(":status", $_POST['status']);
        
        if ($stmt->execute()) {
            header("Location: index.php?success=1");
            exit();
        } else {
            $error = "حدث خطأ أثناء إضافة المستأجر";
        }
    } catch (PDOException $e) {
        $error = "خطأ في قاعدة البيانات: " . $e->getMessage();
    }
}
?>



<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
            <!-- المحتوى الرئيسي -->
            <div class="col-md-10 p-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">إضافة مستأجر جديد</h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="full_name" class="form-label">الاسم الكامل</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" required>
                                    <div class="invalid-feedback">
                                        يرجى إدخال الاسم الكامل
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="nationality" class="form-label">الجنسية</label>
                                    <input type="text" class="form-control" id="nationality" name="nationality" required>
                                    <div class="invalid-feedback">
                                        يرجى إدخال الجنسية
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="id_number" class="form-label">رقم الهوية</label>
                                    <input type="text" class="form-control" id="id_number" name="id_number" required>
                                    <div class="invalid-feedback">
                                        يرجى إدخال رقم الهوية
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">رقم الهاتف</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" required>
                                    <div class="invalid-feedback">
                                        يرجى إدخال رقم الهاتف
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">البريد الإلكتروني</label>
                                    <input type="email" class="form-control" id="email" name="email">
                                    <div class="invalid-feedback">
                                        يرجى إدخال بريد إلكتروني صحيح
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="status" class="form-label">الحالة</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="نشط">نشط</option>
                                        <option value="غير نشط">غير نشط</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <button type="submit" class="btn btn-primary">حفظ</button>
                                <a href="index.php" class="btn btn-secondary">إلغاء</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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
    <?php include '../includes/footer.php'; ?>
