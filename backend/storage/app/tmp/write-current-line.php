<?php
$zip = new ZipArchive();
$path = 'backend/storage/app/tmp/debug-civil-servant-023307.docx';
$zip->open($path);
$name = null;
for ($i = 0; $i < $zip->numFiles; $i++) {
    $n = $zip->getNameIndex($i);
    if (is_string($n) && str_replace('\\', '/', $n) === 'word/document.xml') { $name = $n; break; }
}
$xml = $zip->getFromName($name);
$zip->close();
$needle = 'ផ្ទះលេខ';
$pos = strpos($xml, $needle);
if ($pos === false) { echo "NOT_FOUND\n"; exit; }
file_put_contents('backend/storage/app/tmp/debug-current-line.txt', substr($xml, max(0, $pos - 200), 4500));
echo "WROTE_LINE\n";
