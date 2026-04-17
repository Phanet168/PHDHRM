<?php
$p = 'modules/HumanResource/Resources/views/employee/employee_pdf.blade.php';
$lines = file($p, FILE_IGNORE_NEW_LINES);
$idx = 441; // zero-based
$line = $lines[$idx] ?? '';
echo "RAW: $line\n";
$decoded = @iconv('UTF-8','Windows-1252//IGNORE',$line);
echo "DEC: $decoded\n";
