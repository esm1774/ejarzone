<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (isset($_GET['role_id'])) {
    $roleId = (int)$_GET['role_id'];

    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
    $stmt->execute([$roleId]);
    $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode(['permissions' => $permissions]);
} else {
    echo json_encode(['permissions' => []]);
}
