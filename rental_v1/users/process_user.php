<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/helpers.php';

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "طريقة الطلب غير صحيحة";
    header("Location: index.php");
    exit();
}

// التحقق من CSRF token
if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    $_SESSION['error'] = "رمز CSRF غير صالح";
    header("Location: index.php");
    exit();
}

// إنشاء اتصال بقاعدة البيانات
$database = new Database();
$db = $database->getConnection();

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'add':
            // التحقق من الصلاحيات
            if (!hasPermission('add_users')) {
                throw new Exception("ليس لديك صلاحية لإضافة مستخدمين");
            }

            // التحقق من البيانات المطلوبة
            $requiredFields = ['username', 'full_name', 'email', 'password', 'role'];
            foreach ($requiredFields as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception("جميع الحقول مطلوبة");
                }
            }

            // التحقق من عدم وجود اسم المستخدم أو البريد الإلكتروني
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$_POST['username'], $_POST['email']]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("اسم المستخدم أو البريد الإلكتروني مستخدم بالفعل");
            }

            // إضافة المستخدم
            $db->beginTransaction();

            $stmt = $db->prepare("INSERT INTO users (username, full_name, email, password, role, created_at) 
                                 VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $_POST['username'],
                $_POST['full_name'],
                $_POST['email'],
                password_hash($_POST['password'], PASSWORD_DEFAULT),
                $_POST['role']
            ]);

            $userId = $db->lastInsertId();

            // إضافة صلاحيات المستخدم
            if (!empty($_POST['permissions'])) {
                $stmt = $db->prepare("INSERT INTO user_permissions (user_id, permission_id) VALUES (?, ?)");
                foreach ($_POST['permissions'] as $permissionId) {
                    $stmt->execute([$userId, $permissionId]);
                }
            }

            $db->commit();
            $_SESSION['success'] = "تم إضافة المستخدم بنجاح";
            break;

        case 'edit':
            // التحقق من الصلاحيات
            if (!hasPermission('edit_users')) {
                throw new Exception("ليس لديك صلاحية لتعديل المستخدمين");
            }

            // التحقق من وجود معرف المستخدم
            if (!isset($_POST['user_id']) || !is_numeric($_POST['user_id'])) {
                throw new Exception("معرف المستخدم غير صحيح");
            }

            $userId = (int)$_POST['user_id'];

            // التحقق من وجود المستخدم
            $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            if (!$user) {
                throw new Exception("المستخدم غير موجود");
            }

            // التحقق من البيانات المطلوبة
            $requiredFields = ['username', 'full_name', 'email', 'role'];
            foreach ($requiredFields as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception("جميع الحقول مطلوبة");
                }
            }

            // التحقق من عدم وجود اسم المستخدم أو البريد الإلكتروني لمستخدم آخر
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND user_id != ?");
            $stmt->execute([$_POST['username'], $_POST['email'], $userId]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("اسم المستخدم أو البريد الإلكتروني مستخدم بالفعل");
            }

            // تحديث بيانات المستخدم
            $db->beginTransaction();

            $sql = "UPDATE users SET username = ?, full_name = ?, email = ?, role = ?";
            $params = [
                $_POST['username'],
                $_POST['full_name'],
                $_POST['email'],
                $_POST['role']
            ];

            // تحديث كلمة المرور إذا تم تقديمها
            if (!empty($_POST['password'])) {
                $sql .= ", password = ?";
                $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
            }

            $sql .= " WHERE user_id = ?";
            $params[] = $userId;

            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            // تحديث صلاحيات المستخدم
            $stmt = $db->prepare("DELETE FROM user_permissions WHERE user_id = ?");
            $stmt->execute([$userId]);

            if (!empty($_POST['permissions'])) {
                $stmt = $db->prepare("INSERT INTO user_permissions (user_id, permission_id) VALUES (?, ?)");
                foreach ($_POST['permissions'] as $permissionId) {
                    $stmt->execute([$userId, $permissionId]);
                }
            }

            $db->commit();
            $_SESSION['success'] = "تم تحديث بيانات المستخدم بنجاح";
            break;

        case 'delete':
            // التحقق من الصلاحيات
            if (!hasPermission('delete_users')) {
                throw new Exception("ليس لديك صلاحية لحذف المستخدمين");
            }

            // التحقق من وجود معرف المستخدم
            if (!isset($_POST['user_id']) || !is_numeric($_POST['user_id'])) {
                throw new Exception("معرف المستخدم غير صحيح");
            }

            $userId = (int)$_POST['user_id'];

            // التحقق من عدم حذف المستخدم لنفسه
            if ($userId === $_SESSION['user_id']) {
                throw new Exception("لا يمكنك حذف حسابك الخاص");
            }

            // حذف المستخدم وصلاحياته
            $db->beginTransaction();

            $stmt = $db->prepare("DELETE FROM user_permissions WHERE user_id = ?");
            $stmt->execute([$userId]);

            $stmt = $db->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);

            $db->commit();
            $_SESSION['success'] = "تم حذف المستخدم بنجاح";
            break;

        default:
            throw new Exception("إجراء غير صحيح");
    }
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $_SESSION['error'] = $e->getMessage();
    
    // تسجيل الخطأ
    logError('خطأ في معالجة المستخدم: ' . $e->getMessage(), [
        'action' => $action,
        'user_id' => $userId ?? null,
        'post_data' => $_POST
    ]);
}

// إعادة التوجيه حسب الإجراء
if ($action === 'add' || $action === 'edit') {
    header("Location: " . ($_POST['user_id'] ?? false ? "edit.php?id=" . $_POST['user_id'] : "index.php"));
} else {
    header("Location: index.php");
}
exit();
