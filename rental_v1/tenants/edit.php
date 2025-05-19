<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

include_once "../config/database.php";
$database = new Database();
$db = $database->getConnection();

// التحقق من وجود معرف المستأجر
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$tenant_id = $_GET['id'];

// جلب بيانات المستأجر
$query = "SELECT * FROM tenants WHERE tenant_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$tenant_id]);
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tenant) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // تحديث بيانات المستأجر
        $query = "UPDATE tenants SET 
                  full_name = :full_name,
                  nationality = :nationality,
                  id_number = :id_number,
                  phone = :phone,
                  email = :email,
                  status = :status
                  WHERE tenant_id = :tenant_id";
        
        $stmt = $db->prepare($query);
        
        $stmt->bindParam(":full_name", $_POST['full_name']);
        $stmt->bindParam(":nationality", $_POST['nationality']);
        $stmt->bindParam(":id_number", $_POST['id_number']);
        $stmt->bindParam(":phone", $_POST['phone']);
        $stmt->bindParam(":email", $_POST['email']);
        $stmt->bindParam(":status", $_POST['status']);
        $stmt->bindParam(":tenant_id", $tenant_id);
        
        if ($stmt->execute()) {
            header("Location: index.php?success=2");
            exit();
        } else {
            $error = "حدث خطأ أثناء تحديث بيانات المستأجر";
        }
    } catch (PDOException $e) {
        $error = "خطأ في قاعدة البيانات: " . $e->getMessage();
    }
}
?>


<?php include '../includes/header.php'; ?>

<!-- المحتوى الرئيسي -->
            <div class="col-md-10 p-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">تعديل بيانات المستأجر</h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="full_name" class="form-label">الاسم الكامل</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" 
                                           value="<?php echo htmlspecialchars($tenant['full_name']); ?>" required>
                                    <div class="invalid-feedback">
                                        يرجى إدخال الاسم الكامل
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="nationality" class="form-label">الجنسية</label>
                                    <input type="text" class="form-control" id="nationality" name="nationality" 
                                           value="<?php echo htmlspecialchars($tenant['nationality']); ?>" required>
                                    <div class="invalid-feedback">
                                        يرجى إدخال الجنسية
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="id_number" class="form-label">رقم الهوية</label>
                                    <input type="text" class="form-control" id="id_number" name="id_number" 
                                           value="<?php echo htmlspecialchars($tenant['id_number']); ?>" required>
                                    <div class="invalid-feedback">
                                        يرجى إدخال رقم الهوية
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">رقم الهاتف</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($tenant['phone']); ?>" required>
                                    <div class="invalid-feedback">
                                        يرجى إدخال رقم الهاتف
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">البريد الإلكتروني</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($tenant['email']); ?>">
                                    <div class="invalid-feedback">
                                        يرجى إدخال بريد إلكتروني صحيح
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="status" class="form-label">الحالة</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="نشط" <?php echo ($tenant['status'] == 'نشط') ? 'selected' : ''; ?>>نشط</option>
                                        <option value="غير نشط" <?php echo ($tenant['status'] == 'غير نشط') ? 'selected' : ''; ?>>غير نشط</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
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
