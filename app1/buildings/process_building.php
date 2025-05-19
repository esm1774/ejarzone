<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

// إنشاء اتصال بقاعدة البيانات
$database = new Database();
$db = $database->getConnection();

// التحقق من نوع العملية
$action = isset($_POST['action']) ? $_POST['action'] : '';

try {
    switch ($action) {
        case 'add':
            // التحقق من صلاحية إضافة مبنى
            if (!hasPermission('add_buildings')) {
                throw new Exception('عذراً، ليس لديك صلاحية لإضافة مبنى جديد');
            }

            // التحقق من البيانات المطلوبة
            $required_fields = ['name', 'address', 'floors_count', 'total_units'];
            foreach ($required_fields as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception("حقل " . $field . " مطلوب");
                }
            }

            // إدخال البيانات في قاعدة البيانات
            $query = "INSERT INTO buildings (
                name, address, floors_count, total_units, 
                construction_year, owner_name, owner_phone, 
                owner_email, notes
            ) VALUES (
                :name, :address, :floors_count, :total_units,
                :construction_year, :owner_name, :owner_phone,
                :owner_email, :notes
            )";

            $stmt = $db->prepare($query);
            
            // تنظيف وتأمين البيانات المدخلة
            $stmt->bindValue(':name', sanitize($_POST['name']));
            $stmt->bindValue(':address', sanitize($_POST['address']));
            $stmt->bindValue(':floors_count', (int)$_POST['floors_count']);
            $stmt->bindValue(':total_units', (int)$_POST['total_units']);
            $stmt->bindValue(':construction_year', !empty($_POST['construction_year']) ? (int)$_POST['construction_year'] : null);
            $stmt->bindValue(':owner_name', !empty($_POST['owner_name']) ? sanitize($_POST['owner_name']) : null);
            $stmt->bindValue(':owner_phone', !empty($_POST['owner_phone']) ? sanitize($_POST['owner_phone']) : null);
            $stmt->bindValue(':owner_email', !empty($_POST['owner_email']) ? sanitize($_POST['owner_email']) : null);
            $stmt->bindValue(':notes', !empty($_POST['notes']) ? sanitize($_POST['notes']) : null);

            if ($stmt->execute()) {
                $building_id = $db->lastInsertId();
                addMessage('success', 'تم إضافة المبنى بنجاح');
            } else {
                throw new Exception('حدث خطأ أثناء إضافة المبنى');
            }
            break;

        case 'edit':
            // التحقق من صلاحية تعديل مبنى
            if (!hasPermission('edit_buildings')) {
                throw new Exception('عذراً، ليس لديك صلاحية لتعديل المبنى');
            }

            // التحقق من وجود معرف المبنى
            if (empty($_POST['id'])) {
                throw new Exception('معرف المبنى غير موجود');
            }

            // التحقق من البيانات المطلوبة
            $required_fields = ['name', 'address', 'floors_count', 'total_units'];
            foreach ($required_fields as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception("حقل " . $field . " مطلوب");
                }
            }

            // تحديث البيانات في قاعدة البيانات
            $query = "UPDATE buildings SET 
                name = :name,
                address = :address,
                floors_count = :floors_count,
                total_units = :total_units,
                construction_year = :construction_year,
                owner_name = :owner_name,
                owner_phone = :owner_phone,
                owner_email = :owner_email,
                notes = :notes
            WHERE id = :id";

            $stmt = $db->prepare($query);
            
            // تنظيف وتأمين البيانات المدخلة
            $stmt->bindValue(':id', (int)$_POST['id']);
            $stmt->bindValue(':name', sanitize($_POST['name']));
            $stmt->bindValue(':address', sanitize($_POST['address']));
            $stmt->bindValue(':floors_count', (int)$_POST['floors_count']);
            $stmt->bindValue(':total_units', (int)$_POST['total_units']);
            $stmt->bindValue(':construction_year', !empty($_POST['construction_year']) ? (int)$_POST['construction_year'] : null);
            $stmt->bindValue(':owner_name', !empty($_POST['owner_name']) ? sanitize($_POST['owner_name']) : null);
            $stmt->bindValue(':owner_phone', !empty($_POST['owner_phone']) ? sanitize($_POST['owner_phone']) : null);
            $stmt->bindValue(':owner_email', !empty($_POST['owner_email']) ? sanitize($_POST['owner_email']) : null);
            $stmt->bindValue(':notes', !empty($_POST['notes']) ? sanitize($_POST['notes']) : null);

            if ($stmt->execute()) {
                addMessage('success', 'تم تحديث المبنى بنجاح');
            } else {
                throw new Exception('حدث خطأ أثناء تحديث المبنى');
            }
            break;

        case 'delete':
            // التحقق من صلاحية حذف مبنى
            if (!hasPermission('delete_buildings')) {
                throw new Exception('عذراً، ليس لديك صلاحية لحذف المبنى');
            }

            // التحقق من وجود معرف المبنى
            if (empty($_POST['id'])) {
                throw new Exception('معرف المبنى غير موجود');
            }

            // التحقق من عدم وجود وحدات مرتبطة بالمبنى
            $check_units = "SELECT COUNT(*) FROM units WHERE building_id = :building_id";
            $check_stmt = $db->prepare($check_units);
            $check_stmt->bindValue(':building_id', (int)$_POST['id']);
            $check_stmt->execute();
            
            if ($check_stmt->fetchColumn() > 0) {
                throw new Exception('لا يمكن حذف المبنى لوجود وحدات مرتبطة به');
            }

            // حذف المبنى
            $query = "DELETE FROM buildings WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindValue(':id', (int)$_POST['id']);

            if ($stmt->execute()) {
                addMessage('success', 'تم حذف المبنى بنجاح');
            } else {
                throw new Exception('حدث خطأ أثناء حذف المبنى');
            }
            break;

        default:
            throw new Exception('عملية غير صالحة');
    }

} catch (Exception $e) {
    addMessage('error', $e->getMessage());
}

// إعادة التوجيه إلى الصفحة السابقة
if (isset($_SERVER['HTTP_REFERER'])) {
    redirect($_SERVER['HTTP_REFERER']);
} else {
    redirect('index.php');
}
