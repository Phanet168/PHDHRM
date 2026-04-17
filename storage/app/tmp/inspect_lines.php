<?php
$p = 'modules/HumanResource/Resources/views/employee/employee_detail_pdf.blade.php';
$lines = file($p, FILE_IGNORE_NEW_LINES);
foreach ([435,439,440,441,442,447,450] as $i) {
  $line = $lines[$i] ?? '';
  echo ($i+1) . ': ' . $line . "\n";
}
