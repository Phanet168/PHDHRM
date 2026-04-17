<?php
require 'c:/xampp/htdocs/PHDHRM/vendor/autoload.php';
$sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load('c:/xampp/htdocs/PHDHRM/storage/app/tmp/ret_test_2030.xlsx')->getActiveSheet();

foreach ($sheet->getDrawingCollection() as $d) {
    echo 'Drawing width=' . $d->getWidth() . ' height=' . $d->getHeight() . ' coord=' . $d->getCoordinates() . PHP_EOL;
}

for ($r=1;$r<=$sheet->getHighestRow();$r++) {
    $a = trim((string)$sheet->getCell("A$r")->getFormattedValue());
    if (preg_match('/^(I|II|III|IV|V)\./',$a)) {
        $font=$sheet->getStyle("A$r")->getFont();
        $al=$sheet->getStyle("A$r")->getAlignment();
        echo "Row $r: $a | font={$font->getName()} size={$font->getSize()} h={$al->getHorizontal()}\n";
    }
}
