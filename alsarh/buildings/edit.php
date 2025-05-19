<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

// التحقق من صلاحية تعديل مبنى
if (!hasPermission('edit_building')) {
    addMessage('error', 'عذراً، ليس لديك صلاحية لتعديل بيانات المبنى');
    redirect('index.php');
}

// إنشاء اتصال بقاعدة البيانات
$database = new Database();
$db = $database->getConnection();

// التحقق من وجود معرف المبنى
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    addMessage('error', 'معرف المبنى غير صحيح');
    redirect('index.php');
}

$building_id = (int)$_GET['id'];

// جلب بيانات المبنى
try {
    $query = "SELECT * FROM buildings WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $building_id);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        addMessage('error', 'المبنى غير موجود');
        redirect('index.php');
    }
    
    $building = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    addMessage('error', 'حدث خطأ أثناء جلب بيانات المبنى: ' . $e->getMessage());
    redirect('index.php');
}

// معالجة تحديث البيانات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $query = "UPDATE buildings SET 
            name = :name,
            address = :address,
            floors_count = :floors_count,
            total_units = :total_units,
            construction_year = :construction_year,
            owner_name = :owner_name,
            owner_phone = :owner_phone,
            owner_email = :owner_email,
            notes = :notes,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = :id";

        $stmt = $db->prepare($query);
        
        // تنظيف وتأمين البيانات المدخلة
        $stmt->bindValue(':name', sanitize($_POST['name']));
        $stmt->bindValue(':address', sanitize($_POST['address']));
        $stmt->bindValue(':floors_count', (int)$_POST['floors_count']);
        $stmt->bindValue(':total_units', (int)$_POST['total_units']);
        $stmt->bindValue(':construction_year', !empty($_POST['construction_year']) ? $_POST['construction_year'] : null);
        $stmt->bindValue(':owner_name', !empty($_POST['owner_name']) ? sanitize($_POST['owner_name']) : null);
        $stmt->bindValue(':owner_phone', !empty($_POST['owner_phone']) ? sanitize($_POST['owner_phone']) : null);
        $stmt->bindValue(':owner_email', !empty($_POST['owner_email']) ? sanitize($_POST['owner_email']) : null);
        $stmt->bindValue(':notes', !empty($_POST['notes']) ? sanitize($_POST['notes']) : null);
        $stmt->bindValue(':id', $building_id);

        if ($stmt->execute()) {
            addMessage('success', 'تم تحديث بيانات المبنى بنجاح');
            header("Location: index.php?success=2"); 
            exit();
        } else {
            addMessage('error', 'حدث خطأ أثناء تحديث بيانات المبنى');
        }
    } catch(PDOException $e) {
        addMessage('error', 'حدث خطأ في قاعدة البيانات: ' . $e->getMessage());
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>
<div class="main-content">
<div class="container-fluid py-4">
    <?php displayMessages(); ?>
    
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h3 class="card-title mb-4">تعديل بيانات المبنى</h3>
                    
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">اسم المبنى</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($building['name']); ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="floors_count" class="form-label">عدد الطوابق</label>
                                <input type="number" class="form-control" id="floors_count" name="floors_count" 
                                       value="<?php echo $building['floors_count']; ?>" required min="1">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="total_units" class="form-label">إجمالي عدد الوحدات</label>
                                <input type="number" class="form-control" id="total_units" name="total_units" 
                                       value="<?php echo $building['total_units']; ?>" required min="1">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="construction_year" class="form-label">سنة الإنشاء</label>
                                <input type="number" class="form-control" id="construction_year" name="construction_year" 
                                       value="<?php echo $building['construction_year']; ?>"
                                       min="1900" max="<?php echo date('Y'); ?>">
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="address" class="form-label">العنوان</label>
                                <textarea class="form-control" id="address" name="address" required rows="2"><?php 
                                    echo htmlspecialchars($building['address']); 
                                ?></textarea>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="owner_name" class="form-label">اسم المالك</label>
                                <input type="text" class="form-control" id="owner_name" name="owner_name"
                                       value="<?php echo htmlspecialchars($building['owner_name']); ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="owner_phone" class="form-label">هاتف المالك</label>
                                <input type="tel" class="form-control" id="owner_phone" name="owner_phone"
                                       value="<?php echo htmlspecialchars($building['owner_phone']); ?>">
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label for="owner_email" class="form-label">البريد الإلكتروني للمالك</label>
                                <input type="email" class="form-control" id="owner_email" name="owner_email"
                                       value="<?php echo htmlspecialchars($building['owner_email']); ?>">
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="notes" class="form-label">ملاحظات</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"><?php 
                                    echo htmlspecialchars($building['notes']); 
                                ?></textarea>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                            <a href="index.php" class="btn btn-secondary">إلغاء</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
<?php include '../includes/footer.php'; ?>
