<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

// التحقق من صلاحية إضافة مبنى
if (!hasPermission('add_building')) {
    addMessage('error', 'عذراً، ليس لديك صلاحية لإضافة مبنى جديد');
    redirect('index.php');
}

// معالجة النموذج عند إرساله
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        // تحضير الاستعلام
        $query = "INSERT INTO buildings (
            name, address, floors_count, total_units, 
            construction_year, owner_name, owner_phone, 
            owner_email, notes, created_at
        ) VALUES (
            :name, :address, :floors_count, :total_units,
            :construction_year, :owner_name, :owner_phone,
            :owner_email, :notes, NOW()
        )";
        
        $stmt = $db->prepare($query);
        
        // ربط القيم
        $stmt->bindParam(':name', $_POST['name']);
        $stmt->bindParam(':address', $_POST['address']);
        $stmt->bindParam(':floors_count', $_POST['floors_count']);
        $stmt->bindParam(':total_units', $_POST['total_units']);
        $stmt->bindParam(':construction_year', $_POST['construction_year']);
        $stmt->bindParam(':owner_name', $_POST['owner_name']);
        $stmt->bindParam(':owner_phone', $_POST['owner_phone']);
        $stmt->bindParam(':owner_email', $_POST['owner_email']);
        $stmt->bindParam(':notes', $_POST['notes']);
        
        if ($stmt->execute()) {
            addMessage('success', 'تم إضافة المبنى بنجاح');
            header("Location: index.php?success=2"); 
            exit();
        } else {
            throw new Exception("فشل في إضافة المبنى");
        }
    } catch (Exception $e) {
        addMessage('error', 'حدث خطأ أثناء إضافة المبنى: ' . $e->getMessage());
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="container-fluid py-4">
    <?php displayMessages(); ?>
    <div class="row justify-content-center">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h3 class="card-title mb-4">إضافة مبنى جديد</h3>
                    
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">اسم المبنى <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="address" class="form-label">العنوان <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="address" name="address" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="floors_count" class="form-label">عدد الطوابق <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="floors_count" name="floors_count" required min="1">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="total_units" class="form-label">إجمالي عدد الوحدات <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="total_units" name="total_units" required min="1">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="construction_year" class="form-label">سنة الإنشاء</label>
                                <input type="number" class="form-control" id="construction_year" name="construction_year" min="1900" max="<?php echo date('Y'); ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="owner_name" class="form-label">اسم المالك</label>
                                <input type="text" class="form-control" id="owner_name" name="owner_name">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="owner_phone" class="form-label">هاتف المالك</label>
                                <input type="tel" class="form-control" id="owner_phone" name="owner_phone">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="owner_email" class="form-label">البريد الإلكتروني للمالك</label>
                                <input type="email" class="form-control" id="owner_email" name="owner_email">
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="notes" class="form-label">ملاحظات</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                            </div>
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
