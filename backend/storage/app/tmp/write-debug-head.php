<?php
$zip = new ZipArchive();
$path = 'backend/storage/app/tmp/debug-civil-servant-023307.docx';
if ($zip->open($path) !== true) { echo "ZIPFAIL\n"; exit; }
$name = null;
for ($i = 0; $i < $zip->numFiles; $i++) {
    $n = $zip->getNameIndex($i);
    if (is_string($n) && str_replace('\\', '/', $n) === 'word/document.xml') { $name = $n; break; }
}
$xml = $zip->getFromName($name);
$zip->close();
file_put_contents('backend/storage/app/tmp/debug-xml-head.txt', substr($xml, 0, 2000));
echo "WROTE\n";
