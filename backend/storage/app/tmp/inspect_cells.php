<?php
require 'c:/xampp/htdocs/PHDHRM/vendor/autoload.php';
$sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load('d:/Download/retirement_list_2026_2026 (23).xlsx')->getActiveSheet();
$cells = ['F18','F19','A20','F20','A21','A24','F25'];
foreach ($cells as $cell) {
    $v=(string)$sheet->getCell($cell)->getFormattedValue();
    $font=$sheet->getStyle($cell)->getFont();
    $al=$sheet->getStyle($cell)->getAlignment();
    echo $cell.' = '.$v.' | font='.$font->getName().' size='.$font->getSize().' bold='.($font->getBold()?1:0).' | h='.$al->getHorizontal().' v='.$al->getVertical().PHP_EOL;
}
