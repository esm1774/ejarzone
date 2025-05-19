<?php
require_once "../config/config.php";
require_once "../config/database.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        // التحقق من نوع العملية
        if (isset($_POST['action'])) {
            $action = $_POST['action'];
            
            // إضافة إيراد جديد
            if ($action === 'add') {
                // التحقق من البيانات المطلوبة
                if (!isset($_POST['type_id'], $_POST['amount'], $_POST['payment_date'], $_POST['method_id'])) {
                    throw new Exception("جميع الحقول المطلوبة يجب ملؤها");
                }

                $type_id = $_POST['type_id'];
                $amount = $_POST['amount'];
                $payment_date = $_POST['payment_date'];
                $description = $_POST['description'] ?? '';
                $received_by = $_POST['received_by'] ?? '';
                $method_id = $_POST['method_id'];

                // إنشاء رقم إيصال فريد
                $year = date('Y');
                $month = date('m');
                $query = "SELECT MAX(CAST(SUBSTRING_INDEX(receipt_number, '-', -1) AS UNSIGNED)) as max_number 
                         FROM revenues 
                         WHERE receipt_number LIKE :year_month";
                $stmt = $db->prepare($query);
                $stmt->execute(['year_month' => $year . $month . '-%']);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $next_number = ($result['max_number'] ?? 0) + 1;
                $receipt_number = $year . $month . '-' . str_pad($next_number, 4, '0', STR_PAD_LEFT);

                // إدخال البيانات
                $query = "INSERT INTO revenues (type_id, amount, payment_date, description, receipt_number, received_by, method_id) 
                         VALUES (:type_id, :amount, :payment_date, :description, :receipt_number, :received_by, :method_id)";
                
                $stmt = $db->prepare($query);
                $params = [
                    'type_id' => $type_id,
                    'amount' => $amount,
                    'payment_date' => $payment_date,
                    'description' => $description,
                    'receipt_number' => $receipt_number,
                    'received_by' => $received_by,
                    'method_id' => $method_id
                ];

                if ($stmt->execute($params)) {
                    $_SESSION['success'] = "تم إضافة الإيراد بنجاح";
                    header("Location: index.php");
                    exit();
                } else {
                    throw new Exception("حدث خطأ أثناء إضافة الإيراد");
                }
            }
            
            // تحديث إيراد
            elseif ($action === 'edit') {
                if (!isset($_POST['revenue_id'], $_POST['type_id'], $_POST['amount'], $_POST['payment_date'], $_POST['method_id'])) {
                    throw new Exception("جميع الحقول المطلوبة يجب ملؤها");
                }

                $revenue_id = $_POST['revenue_id'];
                $type_id = $_POST['type_id'];
                $amount = $_POST['amount'];
                $payment_date = $_POST['payment_date'];
                $description = $_POST['description'] ?? '';
                $received_by = $_POST['received_by'] ?? '';
                $method_id = $_POST['method_id'];

                // الحصول على رقم الإيصال الحالي
                $query = "SELECT receipt_number FROM revenues WHERE revenue_id = :revenue_id";
                $stmt = $db->prepare($query);
                $stmt->execute(['revenue_id' => $revenue_id]);
                $current_receipt = $stmt->fetch(PDO::FETCH_ASSOC);
                $receipt_number = $current_receipt['receipt_number'];

                $query = "UPDATE revenues 
                         SET type_id = :type_id,
                             amount = :amount,
                             payment_date = :payment_date,
                             description = :description,
                             received_by = :received_by,
                             method_id = :method_id,
                             updated_at = NOW()
                         WHERE revenue_id = :revenue_id";

                $stmt = $db->prepare($query);
                $params = [
                    'revenue_id' => $revenue_id,
                    'type_id' => $type_id,
                    'amount' => $amount,
                    'payment_date' => $payment_date,
                    'description' => $description,
                    'received_by' => $received_by,
                    'method_id' => $method_id
                ];

                if ($stmt->execute($params)) {
                    $_SESSION['success'] = "تم تحديث الإيراد بنجاح";
                    header("Location: index.php");
                    exit();
                } else {
                    throw new Exception("حدث خطأ أثناء تحديث الإيراد");
                }
            }
            
            // حذف إيراد
            elseif ($action === 'delete') {
                if (!isset($_POST['revenue_id'])) {
                    throw new Exception("معرف الإيراد غير موجود");
                }

                $revenue_id = $_POST['revenue_id'];

                // التحقق من وجود الإيراد
                $query = "SELECT revenue_id FROM revenues WHERE revenue_id = :revenue_id";
                $stmt = $db->prepare($query);
                $stmt->execute(['revenue_id' => $revenue_id]);
                
                if (!$stmt->fetch()) {
                    throw new Exception("الإيراد غير موجود");
                }

                // حذف الإيراد
                $query = "DELETE FROM revenues WHERE revenue_id = :revenue_id";
                $stmt = $db->prepare($query);
                
                if ($stmt->execute(['revenue_id' => $revenue_id])) {
                    $_SESSION['success'] = "تم حذف الإيراد بنجاح";
                } else {
                    throw new Exception("حدث خطأ أثناء حذف الإيراد");
                }
                
                header("Location: index.php");
                exit();
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "حدث خطأ: " . $e->getMessage();
        header("Location: index.php");
        exit();
    }
}

header("Location: index.php");
exit();
?>
