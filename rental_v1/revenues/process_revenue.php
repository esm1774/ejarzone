<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// إنشاء اتصال بقاعدة البيانات
$database = new Database();
$db = $database->getConnection();

try {
    // التحقق من وجود العملية المطلوبة
    if (!isset($_POST['action'])) {
        throw new Exception('لم يتم تحديد العملية المطلوبة');
    }

    $action = $_POST['action'];

    switch ($action) {
        case 'add':
            // التحقق من البيانات المطلوبة
            if (!isset($_POST['type_id']) || !isset($_POST['amount']) || !isset($_POST['payment_date']) || !isset($_POST['received_by'])) {
                $_SESSION['error'] = "جميع الحقول المطلوبة يجب ملؤها";
                header("Location: " . $_SERVER['HTTP_REFERER']);
                exit();
            }

            $type_id = $_POST['type_id'];
            $amount = $_POST['amount'];
            $payment_date = $_POST['payment_date'];
            $description = $_POST['description'] ?? '';
            $received_by = $_POST['received_by'];
            $payment_type = $_POST['payment_type'] ?? 'cash';

            // إنشاء رقم إيصال فريد
            $year = date('Y');
            $query = "SELECT MAX(CAST(SUBSTRING_INDEX(receipt_number, '-', -1) AS UNSIGNED)) as max_number 
                     FROM revenues 
                     WHERE receipt_number LIKE :year";
            $stmt = $db->prepare($query);
            $stmt->execute(['year' => $year . '-%']);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $next_number = ($result['max_number'] ?? 0) + 1;
            $receipt_number = $year . '-' . str_pad($next_number, 4, '0', STR_PAD_LEFT);

            $query = "INSERT INTO revenues (type_id, amount, payment_date, description, receipt_number, received_by, payment_type) 
                     VALUES (:type_id, :amount, :payment_date, :description, :receipt_number, :received_by, :payment_type)";
            $params = [
                'type_id' => $type_id,
                'amount' => $amount,
                'payment_date' => $payment_date,
                'description' => $description,
                'receipt_number' => $receipt_number,
                'received_by' => $received_by,
                'payment_type' => $payment_type
            ];

            $stmt = $db->prepare($query);
            $stmt->execute($params);

            echo json_encode(['success' => true, 'message' => 'تم إضافة الإيراد بنجاح']);
            break;

        case 'edit':
            // التحقق من البيانات المطلوبة
            if (!isset($_POST['type_id']) || !isset($_POST['amount']) || !isset($_POST['payment_date']) || !isset($_POST['revenue_id']) || !isset($_POST['received_by'])) {
                $_SESSION['error'] = "جميع الحقول المطلوبة يجب ملؤها";
                header("Location: " . $_SERVER['HTTP_REFERER']);
                exit();
            }

            $type_id = $_POST['type_id'];
            $amount = $_POST['amount'];
            $payment_date = $_POST['payment_date'];
            $description = $_POST['description'] ?? '';
            $received_by = $_POST['received_by'];
            $payment_type = $_POST['payment_type'] ?? 'cash';

            // إذا كان هذا تحديث لإيراد موجود
            if (isset($_POST['revenue_id'])) {
                $revenue_id = $_POST['revenue_id'];
                $query = "UPDATE revenues 
                         SET type_id = :type_id, 
                             amount = :amount, 
                             payment_date = :payment_date, 
                             description = :description, 
                             received_by = :received_by,
                             payment_type = :payment_type
                         WHERE revenue_id = :revenue_id";
                $params = [
                    'type_id' => $type_id,
                    'amount' => $amount,
                    'payment_date' => $payment_date,
                    'description' => $description,
                    'revenue_id' => $revenue_id,
                    'received_by' => $received_by,
                    'payment_type' => $payment_type
                ];

                $stmt = $db->prepare($query);
                $stmt->execute($params);

                echo json_encode(['success' => true, 'message' => 'تم تحديث الإيراد بنجاح']);
            } else {
                throw new Exception('معرف الإيراد غير موجود');
            }
            break;

        case 'delete':
            // التحقق من وجود معرف الإيراد
            if (!isset($_POST['revenue_id'])) {
                throw new Exception('معرف الإيراد غير موجود');
            }

            // حذف الإيراد
            $query = "DELETE FROM revenues WHERE revenue_id = :revenue_id";
            $stmt = $db->prepare($query);
            $stmt->execute(['revenue_id' => $_POST['revenue_id']]);

            echo json_encode(['success' => true, 'message' => 'تم حذف الإيراد بنجاح']);
            break;

        case 'add_type':
            // التحقق من البيانات المطلوبة
            if (!isset($_POST['type_name'])) {
                throw new Exception('اسم النوع مطلوب');
            }
            $type_name = $_POST['type_name'];
            $description = $_POST['description'] ?? '';

            $query = "INSERT INTO revenue_types (type_name, description) VALUES (:type_name, :description)";
            $stmt = $db->prepare($query);
            $stmt->execute(['type_name' => $type_name, 'description' => $description]);

            echo json_encode(['success' => true, 'message' => 'تم إضافة نوع الإيراد بنجاح']);
            break;

        case 'edit_type':
            // التحقق من البيانات المطلوبة
            if (!isset($_POST['type_id']) || !isset($_POST['type_name'])) {
                throw new Exception('معرف النوع واسم النوع مطلوبان');
            }
            $type_id = $_POST['type_id'];
            $type_name = $_POST['type_name'];
            $description = $_POST['description'] ?? '';

            $query = "UPDATE revenue_types SET type_name = :type_name, description = :description WHERE type_id = :type_id";
            $stmt = $db->prepare($query);
            $stmt->execute(['type_name' => $type_name, 'description' => $description, 'type_id' => $type_id]);

            echo json_encode(['success' => true, 'message' => 'تم تعديل نوع الإيراد بنجاح']);
            break;

        case 'delete_type':
            // التحقق من وجود معرف النوع
            if (!isset($_POST['type_id'])) {
                throw new Exception('معرف النوع مطلوب');
            }
            $type_id = $_POST['type_id'];

            $query = "DELETE FROM revenue_types WHERE type_id = :type_id";
            $stmt = $db->prepare($query);
            $stmt->execute(['type_id' => $type_id]);

            echo json_encode(['success' => true, 'message' => 'تم حذف نوع الإيراد بنجاح']);
            break;

        default:
            throw new Exception('العملية غير معروفة');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
