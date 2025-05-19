<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

include_once "../config/database.php";
$database = new Database();
$db = $database->getConnection();

// التحقق من وجود معرف الوحدة
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$unit_id = $_GET['id'];

// جلب بيانات الوحدة
$query = "SELECT * FROM units WHERE unit_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$unit_id]);
$unit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$unit) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // التعامل مع الصور الجديدة
        $images = explode(',', $unit['images']);
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

        // تحديث بيانات الوحدة
        $query = "UPDATE units SET 
                  unit_name = :unit_name,
                  floor = :floor,
                  building = :building,
                  status = :status,
                  description = :description,
                  images = :images
                  WHERE unit_id = :unit_id";
        
        $stmt = $db->prepare($query);
        
        $stmt->bindParam(":unit_name", $_POST['unit_name']);
        $stmt->bindParam(":floor", $_POST['floor']);
        $stmt->bindParam(":building", $_POST['building']);
        $stmt->bindParam(":status", $_POST['status']);
        $stmt->bindParam(":description", $_POST['description']);
        $stmt->bindParam(":images", implode(',', $images));
        $stmt->bindParam(":unit_id", $unit_id);
        
        if ($stmt->execute()) {
            header("Location: index.php?success=2");
            exit();
        } else {
            $error = "حدث خطأ أثناء تحديث الوحدة";
        }
    } catch (PDOException $e) {
        $error = "خطأ في قاعدة البيانات: " . $e->getMessage();
    }
}
?>

<?php include '../includes/header.php'; ?>

<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">تعديل الوحدة</h5>
        </div>
        <div class="card-body">
            <form action="" method="post">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="unit_name" class="form-label">اسم الوحدة</label>
                        <input type="text" class="form-control" id="unit_name" name="unit_name" value="<?php echo htmlspecialchars($unit['unit_name']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="building" class="form-label">المبنى</label>
                        <input type="text" class="form-control" id="building" name="building" value="<?php echo htmlspecialchars($unit['building']); ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="floor" class="form-label">الطابق</label>
                        <select class="form-select" id="floor" name="floor" required>
                            <?php
                            $floors = ['الأرضي', 'الأول', 'الثاني', 'الثالث', 'الرابع'];
                            foreach ($floors as $floor) {
                                $selected = ($floor == $unit['floor']) ? 'selected' : '';
                                echo "<option value=\"$floor\" $selected>$floor</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="status" class="form-label">الحالة</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="متاح" <?php echo ($unit['status'] == 'متاح') ? 'selected' : ''; ?>>متاح</option>
                            <option value="غير متاح" <?php echo ($unit['status'] == 'غير متاح') ? 'selected' : ''; ?>>غير متاح</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">وصف الوحدة</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($unit['description']); ?></textarea>
                </div>

                <div class="mb-3">
                    <label for="images" class="form-label">إضافة صور جديدة</label>
                    <input type="file" class="form-control" id="images" name="images[]" multiple accept="image/*">
                </div>

                <?php if (!empty($unit['images'])): ?>
                <div class="mb-3">
                    <label class="form-label">الصور الحالية</label>
                    <div class="row">
                        <?php
                        $images = explode(',', $unit['images']);
                        foreach ($images as $image):
                            if (!empty($image)):
                        ?>
                        <div class="col-md-3 mb-2">
                            <img src="../uploads/units/<?php echo $image; ?>" class="img-thumbnail" alt="صورة الوحدة">
                        </div>
                        <?php
                            endif;
                        endforeach;
                        ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i>
                        حفظ التغييرات
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="bi bi-x-lg"></i>
                        إلغاء
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
