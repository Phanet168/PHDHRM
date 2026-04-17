<?php
$pdo = new PDO("mysql:host=localhost;port=3307;dbname=PHDHRM;charset=utf8mb4","root","");
$tables = ['employees','employee_docs','employee_work_histories','departments'];
foreach ($tables as $t) {
  echo "\n[$t]\n";
  $stmt = $pdo->query("SHOW INDEX FROM `$t`");
  while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo ($r['Key_name'] ?? '') . ' | ' . ($r['Column_name'] ?? '') . ' | non_unique=' . ($r['Non_unique'] ?? '') . ' | seq=' . ($r['Seq_in_index'] ?? '') . PHP_EOL;
  }
}
