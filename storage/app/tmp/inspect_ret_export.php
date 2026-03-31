<?php
$root = dirname(__DIR__, 3);
require $root . '/vendor/autoload.php';

$path = $root . '/storage/app/tmp/ret_test.xlsx';
$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
$sheet = $spreadsheet->getActiveSheet();

echo "HighestRow: " . $sheet->getHighestRow() . PHP_EOL;
echo "HighestCol: " . $sheet->getHighestColumn() . PHP_EOL;

echo "MERGES:" . PHP_EOL;
foreach ($sheet->getMergeCells() as $m) {
    echo $m . PHP_EOL;
}

echo PHP_EOL . "Footer candidates F rows with values:" . PHP_EOL;
for ($r = max(1, $sheet->getHighestRow()-20); $r <= $sheet->getHighestRow(); $r++) {
    $f = (string)$sheet->getCell("F$r")->getFormattedValue();
    $g = (string)$sheet->getCell("G$r")->getFormattedValue();
    $h = (string)$sheet->getCell("H$r")->getFormattedValue();
    if (trim($f.$g.$h) !== '') {
        $font = $sheet->getStyle("F$r")->getFont();
        echo "Row $r | F='$f' | G='$g' | H='$h' | Font=" . $font->getName() . " Size=" . $font->getSize() . PHP_EOL;
    }
}
