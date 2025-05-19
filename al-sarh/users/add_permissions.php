<?php
require_once '../config/database.php';
require_once '../includes/helpers.php';

// معالجة النموذج عند الإرسال
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $groupId = $_POST['group_id'];
    $permissionNames = $_POST['permission_names']; // الحصول على جميع الصلاحيات المختارة

    addPermissions($db, $groupId, $permissionNames);
}

// إضافة جميع الصلاحيات لمدير النظام
$adminGroupId = 1; // تأكد من أن $adminGroupId هو معرف مجموعة مدير النظام
if ($groupId == $adminGroupId) {
    $allPermissions = $db->query("SELECT permission_name FROM permissions")->fetchAll(PDO::FETCH_COLUMN);
    addPermissions($db, $groupId, $allPermissions);
}


// دالة لإضافة الصلاحيات
function addPermissions($db, $groupId, $permissionNames) {
    // تحقق من أن القائمة تحتوي على صلاحيات
    if (empty($permissionNames) || !is_array($permissionNames)) {
        $_SESSION['error'] = "يجب اختيار صلاحيات لإضافتها.";
        header("Location: add_permissions.php");
        exit();
    }

    // قائمة لتجميع الأخطاء
    $errors = [];

    try {
        // بدء معاملة قاعدة البيانات
        $db->beginTransaction();

        foreach ($permissionNames as $permissionName) {
            // جلب permission_id من جدول permissions
            $stmtPerm = $db->prepare("SELECT permission_id FROM permissions WHERE permission_name = ?");
            $stmtPerm->execute([$permissionName]);
            $permissionId = $stmtPerm->fetchColumn();

            if ($permissionId === false) {
                // الصلاحية غير موجودة
                $errors[] = "الصلاحية '{$permissionName}' غير موجودة.";
                continue; // الانتقال للصلاحية التالية
            }

            // التحقق من أن الصلاحية غير مضافة مسبقًا للمجموعة
            $stmtCheck = $db->prepare("SELECT COUNT(*) FROM group_permissions WHERE group_id = ? AND permission_id = ?");
            $stmtCheck->execute([$groupId, $permissionId]);
            if ($stmtCheck->fetchColumn() > 0) {
                $errors[] = "الصلاحية '{$permissionName}' مضافة بالفعل.";
                continue; // الانتقال للصلاحية التالية
            }

            // إدخال الصلاحية في جدول group_permissions
            $stmtInsert = $db->prepare("INSERT INTO group_permissions (group_id, permission_id) VALUES (?, ?)");
            if (!$stmtInsert->execute([$groupId, $permissionId])) {
                $errors[] = "حدث خطأ أثناء إضافة الصلاحية '{$permissionName}'.";
            }
        }

        // إذا لم يكن هناك أخطاء، قم بتأكيد التغييرات
        if (empty($errors)) {
            $db->commit();
            $_SESSION['success'] = "تمت إضافة الصلاحيات بنجاح.";
        } else {
            // إذا كانت هناك أخطاء، تراجع عن التغييرات
            $db->rollBack();
            $_SESSION['error'] = implode('<br>', $errors);
        }
    } catch (Exception $e) {
        // تراجع عن التغييرات في حالة حدوث خطأ غير متوقع
        $db->rollBack();
        $_SESSION['error'] = "حدث خطأ أثناء المعالجة: " . $e->getMessage();
    }

    // إعادة التوجيه إلى الصفحة
    header("Location: add_permissions.php");
    exit();
}

// استرجاع المجموعات والصلاحيات لعرضها في النموذج
$groups = $db->query("SELECT * FROM groups")->fetchAll(PDO::FETCH_ASSOC);
$permissions = $db->query("SELECT * FROM permissions")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>إضافة صلاحيات للمجموعات</title>
    <style>
        /* (CSS styles as before) */
    </style>
</head>
<body>
    <h1>إضافة صلاحيات للمجموعات</h1>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="message success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="message error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <form method="POST" action="add_permissions.php">
        <label for="group_id">اختر المجموعة:</label>
        <select name="group_id" id="group_id" required>
            <?php foreach ($groups as $group): ?>
                <option value="<?php echo $group['group_id']; ?>"><?php echo $group['group_name']; ?></option>
            <?php endforeach; ?>
        </select>

        <label>اختر الصلاحيات:</label>
        <div>
            <?php foreach ($permissions as $permission): ?>
                <input type="checkbox" name="permission_names[]" value="<?php echo $permission['permission_name']; ?>" id="<?php echo $permission['permission_name']; ?>">
                <label for="<?php echo $permission['permission_name']; ?>"><?php echo $permission['permission_name']; ?></label><br>
            <?php endforeach; ?>
        </div>

        <button type="button" onclick="selectAll()">اختر الكل</button>
        <button type="button" onclick="deselectAll()">إلغاء الاختيار</button>
        <button type="submit">إضافة الصلاحيات</button>
        <button type="button" onclick="addAllPermissions()">إضافة جميع الصلاحيات لمدير النظام</button>
    </form>

    <script>
        function selectAll() {
            const checkboxes = document.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(checkbox => checkbox.checked = true);
        }

        function deselectAll() {
            const checkboxes = document.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(checkbox => checkbox.checked = false);
        }

        function addAllPermissions() {
            const checkboxes = document.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(checkbox => checkbox.checked = true);
            document.querySelector('form').submit();
        }
    </script>
</body>
</html>