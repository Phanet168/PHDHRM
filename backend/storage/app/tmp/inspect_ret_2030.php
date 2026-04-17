<?php
require 'c:/xampp/htdocs/PHDHRM/vendor/autoload.php';
$path = 'd:/Download/retirement_list_2026_2030 (3).xlsx';
$sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();

echo "HighestRow: " . $sheet->getHighestRow() . PHP_EOL;
echo "Merges:" . PHP_EOL;
foreach ($sheet->getMergeCells() as $m) echo $m.PHP_EOL;

echo "\nDrawings:" . PHP_EOL;
foreach ($sheet->getDrawingCollection() as $d) {
    echo get_class($d) . ' | coords=' . $d->getCoordinates() . ' | width=' . $d->getWidth() . ' | height=' . $d->getHeight() . ' | offsetX=' . $d->getOffsetX() . ' | offsetY=' . $d->getOffsetY() . PHP_EOL;
}

echo "\nSection-like rows (A contains I./II./III):" . PHP_EOL;
for ($r=1; $r<=$sheet->getHighestRow(); $r++) {
    $a = trim((string)$sheet->getCell("A$r")->getFormattedValue());
    if (preg_match('/^(I|II|III|IV|V|VI|VII|VIII|IX|X)\./', $a)) {
        $font = $sheet->getStyle("A$r")->getFont();
        $al = $sheet->getStyle("A$r")->getAlignment();
        echo "Row $r => $a | font={$font->getName()} size={$font->getSize()} bold=".($font->getBold()?1:0)." | h={$al->getHorizontal()} v={$al->getVertical()} | rowH=".$sheet->getRowDimension($r)->getRowHeight().PHP_EOL;
    }
}
