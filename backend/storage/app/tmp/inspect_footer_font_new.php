<?php
require 'c:/xampp/htdocs/PHDHRM/vendor/autoload.php';
$sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load('c:/xampp/htdocs/PHDHRM/storage/app/tmp/ret_test_2030.xlsx')->getActiveSheet();
for ($r=max(1,$sheet->getHighestRow()-20); $r<=$sheet->getHighestRow(); $r++) {
    $f=trim((string)$sheet->getCell("F$r")->getFormattedValue());
    if ($f !== '') {
        $font=$sheet->getStyle("F$r")->getFont();
        echo "Row $r | F=$f | font={$font->getName()} size={$font->getSize()}\n";
    }
}
