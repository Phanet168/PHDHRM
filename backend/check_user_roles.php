<?php
require 'vendor/autoload.php';

$pdo = new PDO('mysql:host=localhost;dbname=phdhrm', 'root', '');
$stmt = $pdo->query("SELECT uor.id, uor.user_id, uor.system_role_id, sr.code, d.department_name FROM user_org_roles uor LEFT JOIN system_roles sr ON uor.system_role_id = sr.id LEFT JOIN departments d ON uor.department_id = d.id WHERE uor.user_id = 25");

echo "User 25 (phanet@gmail.com) org roles:\n";
echo "=====================================\n";
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($rows)) {
    echo "No org roles assigned.\n";
} else {
    foreach ($rows as $row) {
        echo "Role: {$row['code']} | Department: {$row['department_name']}\n";
    }
}
