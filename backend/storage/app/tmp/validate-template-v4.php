<?php
libxml_use_internal_errors(true);
$zip = new ZipArchive();
$path = 'backend/storage/app/public/templates/civil-servant-biography-template-v4.docx';
if ($zip->open($path) !== true) { echo "ZIP_FAIL\n"; exit; }
$name = null;
for ($i = 0; $i < $zip->numFiles; $i++) {
    $n = $zip->getNameIndex($i);
    if (is_string($n) && str_replace('\\', '/', $n) === 'word/document.xml') { $name = $n; break; }
}
if ($name === null) { echo "ENTRY_FAIL\n"; exit; }
$xml = $zip->getFromName($name);
$zip->close();
$doc = new DOMDocument();
$ok = $doc->loadXML($xml);
echo $ok ? "TEMPLATE_OK\n" : "TEMPLATE_BAD\n";
