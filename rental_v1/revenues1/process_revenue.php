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
            if (!isset($_POST['type_id']) || !isset($_POST['amount']) || !isset($_POST['payment_date'])) {
                $_SESSION['error'] = "جميع الحقول المطلوبة يجب ملؤها";
                header("Location: " . $_SERVER['HTTP_REFERER']);
                exit();
            }

            $type_id = $_POST['type_id'];
            $amount = $_POST['amount'];
            $payment_date = $_POST['payment_date'];
            $description = $_POST['description'] ?? '';
            $contract_id = !empty($_POST['contract_id']) ? $_POST['contract_id'] : null;
            $payment_method = $_POST['payment_method'] ?? 'cash';

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

            $query = "INSERT INTO revenues (type_id, amount, payment_date, description, contract_id, receipt_number, payment_method) 
                     VALUES (:type_id, :amount, :payment_date, :description, :contract_id, :receipt_number, :payment_method)";
            $params = [
                'type_id' => $type_id,
                'amount' => $amount,
                'payment_date' => $payment_date,
                'description' => $description,
                'contract_id' => $contract_id,
                'receipt_number' => $receipt_number,
                'payment_method' => $payment_method
            ];

            $stmt = $db->prepare($query);
            $stmt->execute($params);

            echo json_encode(['success' => true, 'message' => 'تم إضافة الإيراد بنجاح']);
            break;

        case 'edit':
            // التحقق من البيانات المطلوبة
            if (!isset($_POST['type_id']) || !isset($_POST['amount']) || !isset($_POST['payment_date'])) {
                $_SESSION['error'] = "جميع الحقول المطلوبة يجب ملؤها";
                header("Location: " . $_SERVER['HTTP_REFERER']);
                exit();
            }

            $type_id = $_POST['type_id'];
            $amount = $_POST['amount'];
            $payment_date = $_POST['payment_date'];
            $description = $_POST['description'] ?? '';
            $contract_id = !empty($_POST['contract_id']) ? $_POST['contract_id'] : null;
            $payment_method = $_POST['payment_method'] ?? 'cash';

            // إذا كان هذا تحديث لإيراد موجود
            if (isset($_POST['revenue_id'])) {
                $revenue_id = $_POST['revenue_id'];
                $query = "UPDATE revenues 
                         SET type_id = :type_id, 
                             amount = :amount, 
                             payment_date = :payment_date, 
                             description = :description, 
                             contract_id = :contract_id,
                             payment_method = :payment_method
                         WHERE revenue_id = :revenue_id";
                $params = [
                    'type_id' => $type_id,
                    'amount' => $amount,
                    'payment_date' => $payment_date,
                    'description' => $description,
                    'contract_id' => $contract_id,
                    'revenue_id' => $revenue_id,
                    'payment_method' => $payment_method
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

        default:
            throw new Exception('العملية غير معروفة');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
