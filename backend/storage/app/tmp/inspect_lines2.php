<?php
$p = 'modules/HumanResource/Resources/views/employee/employee_pdf.blade.php';
$lines = file($p, FILE_IGNORE_NEW_LINES);
foreach ([441,442,443,444,445] as $i) {
  $line = $lines[$i] ?? '';
  echo ($i+1) . ': ' . $line . "\n";
}
