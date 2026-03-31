<?php
$pdo = new PDO("mysql:host=localhost;port=3307;dbname=PHDHRM;charset=utf8mb4","root","");
$tables = ['employees','employee_docs','employee_files','employee_work_histories','employee_pay_grade_histories','departments','positions','users'];
foreach ($tables as $t) {
  $stmt = $pdo->query("SELECT COUNT(*) AS c FROM `$t`");
  $c = (int)$stmt->fetch(PDO::FETCH_ASSOC)['c'];
  echo $t . '_rows=' . $c . PHP_EOL;
}
