<?php
$line = trim($argv[1]);
$decoded = iconv('UTF-8','Windows-1252//IGNORE',$line);
echo "DEC: $decoded\n";
