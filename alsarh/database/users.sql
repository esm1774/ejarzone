-- تعطيل فحص المفاتيح الخارجية مؤقتاً
SET FOREIGN_KEY_CHECKS=0;

-- حذف العلاقات المرتبطة بجدول users
ALTER TABLE `user_permissions` DROP FOREIGN KEY IF EXISTS `user_permissions_user_id_foreign`;
ALTER TABLE `contract_logs` DROP FOREIGN KEY IF EXISTS `contract_logs_created_by_foreign`;
ALTER TABLE `contract_logs` DROP FOREIGN KEY IF EXISTS `contract_logs_updated_by_foreign`;

-- حذف الجدول إذا كان موجوداً
DROP TABLE IF EXISTS `users`;

-- إعادة تفعيل فحص المفاتيح الخارجية
SET FOREIGN_KEY_CHECKS=1;

-- إنشاء جدول المستخدمين
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','manager','user') NOT NULL DEFAULT 'user',
  `avatar` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إضافة مستخدم افتراضي (كلمة المرور: 123456)
INSERT INTO `users` (`full_name`, `email`, `password`, `phone`, `role`) VALUES
('مدير النظام', 'admin@example.com', '$2y$10$7875AyLEZMatq2bxmCW10u8i8qXyUnYdMN1ZqRjwkcg7W3dxpEu.u', '0500000000', 'admin');

-- إعادة إنشاء العلاقات
ALTER TABLE `user_permissions`
ADD CONSTRAINT `user_permissions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

ALTER TABLE `contract_logs`
ADD CONSTRAINT `contract_logs_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
ADD CONSTRAINT `contract_logs_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`);