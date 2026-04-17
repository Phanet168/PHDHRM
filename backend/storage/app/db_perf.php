<?php
$pdo = new PDO("mysql:host=localhost;port=3307;dbname=PHDHRM;charset=utf8mb4", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
function ms() { return microtime(true); }
$start = ms();
$c = (int)$pdo->query("SELECT COUNT(*) c FROM employees WHERE is_active=1")->fetch(PDO::FETCH_ASSOC)['c'];
$dur1 = (ms()-$start)*1000;

$sql = "SELECT employees.id FROM employees
LEFT JOIN positions as p_order ON employees.position_id = p_order.id
LEFT JOIN departments as d_main ON employees.department_id = d_main.id
LEFT JOIN departments as d_sub ON employees.sub_department_id = d_sub.id
WHERE employees.is_active = 1
ORDER BY
CASE WHEN COALESCE(NULLIF(d_sub.location_code, ''), NULLIF(d_main.location_code, '')) LIKE 'LEGACY-WP-%' THEN 0 ELSE 1 END ASC,
CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(COALESCE(NULLIF(d_sub.location_code, ''), NULLIF(d_main.location_code, '')), 'LEGACY-WP-', ''), '-', 3), '-', -1) AS UNSIGNED) ASC,
CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(COALESCE(NULLIF(d_sub.location_code, ''), NULLIF(d_main.location_code, '')), 'LEGACY-WP-', ''), '-', 4), '-', -1) AS UNSIGNED) ASC,
CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(COALESCE(NULLIF(d_sub.location_code, ''), NULLIF(d_main.location_code, '')), 'LEGACY-WP-', ''), '-', 5), '-', -1) AS UNSIGNED) ASC,
COALESCE(d_main.sort_order, 0) ASC,
COALESCE(NULLIF(d_main.department_name, ''), '') ASC,
COALESCE(d_sub.sort_order, 0) ASC,
COALESCE(NULLIF(d_sub.department_name, ''), '') ASC,
CASE WHEN p_order.position_rank IS NULL THEN 1 ELSE 0 END ASC,
p_order.position_rank ASC,
COALESCE(NULLIF(p_order.position_name_km, ''), NULLIF(p_order.position_name, ''), '') ASC,
COALESCE(NULLIF(employees.last_name, ''), '') ASC,
COALESCE(NULLIF(employees.first_name, ''), '') ASC,
employees.id ASC";
$start = ms();
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
$dur2 = (ms()-$start)*1000;

echo "active_count={$c}; simple_count_ms=" . round($dur1,2) . PHP_EOL;
echo "ordered_rows=" . count($rows) . "; ordered_query_ms=" . round($dur2,2) . PHP_EOL;
