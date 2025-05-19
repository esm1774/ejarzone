-- إضافة صلاحيات المدفوعات
INSERT INTO permissions (permission_name, description) VALUES
('view_payments', 'عرض المدفوعات'),
('add_payments', 'إضافة مدفوعات جديدة'),
('edit_payments', 'تعديل المدفوعات'),
('delete_payments', 'حذف المدفوعات'),
('print_payments', 'طباعة إيصالات المدفوعات');

-- إضافة الصلاحيات للمدير (افتراض أن دور المدير له role_id = 1)
INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, permission_id
FROM permissions
WHERE permission_name IN (
    'view_payments',
    'add_payments',
    'edit_payments',
    'delete_payments',
    'print_payments'
);
