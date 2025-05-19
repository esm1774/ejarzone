<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

include_once "../config/database.php";
$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // التحقق من تحميل الصور
        $images = [];
        if (!empty($_FILES['images']['name'][0])) {
            $upload_dir = "../uploads/units/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                $file_name = time() . '_' . $_FILES['images']['name'][$key];
                $file_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($tmp_name, $file_path)) {
                    $images[] = $file_name;
                }
            }
        }

        // إعداد الاستعلام
        $query = "INSERT INTO units (unit_name, floor, building, status, description, images) 
                  VALUES (:unit_name, :floor, :building, :status, :description, :images)";
        
        $stmt = $db->prepare($query);
        
        // ربط القيم
        $stmt->bindParam(":unit_name", $_POST['unit_name']);
        $stmt->bindParam(":floor", $_POST['floor']);
        $stmt->bindParam(":building", $_POST['building']);
        $stmt->bindParam(":status", $_POST['status']);
        $stmt->bindParam(":description", $_POST['description']);
        $stmt->bindParam(":images", implode(',', $images));
        
        if ($stmt->execute()) {
            header("Location: index.php?success=1");
            exit();
        } else {
            $error = "حدث خطأ أثناء إضافة الوحدة";
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
                        <h5 class="card-title">إضافة وحدة جديدة</h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="unit_name" class="form-label">اسم الوحدة</label>
                                    <input type="text" class="form-control" id="unit_name" name="unit_name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="building" class="form-label">المبنى</label>
                                    <input type="text" class="form-control" id="building" name="building" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="floor" class="form-label">الطابق</label>
                                    <select class="form-select" id="floor" name="floor" required>
                                        <option value="الأرضي">الأرضي</option>
                                        <option value="الأول">الأول</option>
                                        <option value="الثاني">الثاني</option>
                                        <option value="الثالث">الثالث</option>
                                        <option value="الرابع">الرابع</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="status" class="form-label">الحالة</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="متاح">متاح</option>
                                        <option value="غير متاح">غير متاح</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">وصف الوحدة</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="images" class="form-label">صور الوحدة</label>
                                <input type="file" class="form-control" id="images" name="images[]" multiple accept="image/*">
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

    <?php include '../includes/footer.php'; ?>
