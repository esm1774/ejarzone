<?php
session_start();
require_once "../config/config.php";
require_once "../includes/functions.php";

// التحقق من تسجيل الدخول
if (!isLoggedIn()) {
    redirect('../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// جلب قائمة المباني
$buildingsQuery = "SELECT id, name FROM buildings ORDER BY name";
$buildingsStmt = $db->prepare($buildingsQuery);
$buildingsStmt->execute();
$buildings = $buildingsStmt->fetchAll(PDO::FETCH_ASSOC);

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
        $query = "INSERT INTO units (unit_name, floor, building_id, status, description, images) 
                  VALUES (:unit_name, :floor, :building_id, :status, :description, :images)";
        
        $stmt = $db->prepare($query);
        
        // ربط القيم
        $stmt->bindParam(":unit_name", $_POST['unit_name']);
        $stmt->bindParam(":floor", $_POST['floor']);
        $stmt->bindParam(":building_id", $_POST['building_id']);
        $stmt->bindParam(":status", $_POST['status']);
        $stmt->bindParam(":description", $_POST['description']);
        $stmt->bindParam(":images", implode(',', $images));
        
        if ($stmt->execute()) {
            addMessage('success', 'تم إضافة الوحدة بنجاح');
            header("Location: index.php?success=2"); 
            exit();
        } else {
            throw new Exception("فشل في إضافة الوحدة");
        }
    } catch (PDOException $e) {
        $error = "خطأ في قاعدة البيانات: " . $e->getMessage();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">إضافة وحدة جديدة</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="unit_name" class="form-label">اسم الوحدة</label>
                                <input type="text" class="form-control" id="unit_name" name="unit_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="building_id" class="form-label">المبنى</label>
                                <select class="form-select" id="building_id" name="building_id" required>
                                    <option value="">اختر المبنى</option>
                                    <?php foreach ($buildings as $building): ?>
                                        <option value="<?php echo $building['id']; ?>">
                                            <?php echo htmlspecialchars($building['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
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
                                    <option value="شاغرة">شاغرة</option>
                                    <option value="مؤجرة">مؤجرة</option>
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

                        <div class="text-end mt-4">
                            <a href="index.php" class="btn btn-secondary">إلغاء</a>
                            <button type="submit" class="btn btn-primary">حفظ</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
