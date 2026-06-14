<?php
$zip = new ZipArchive();
$path = 'backend/storage/app/public/templates/civil-servant-biography-template-v4.docx';
if ($zip->open($path) !== true) { echo "ZIP_FAIL\n"; exit; }
$name = null;
for ($i=0; $i<$zip->numFiles; $i++) {
  $n=$zip->getNameIndex($i);
  if (is_string($n) && str_replace('\\', '/', $n) === 'word/document.xml') { $name=$n; break; }
}
$xml = $zip->getFromName($name); $zip->close();
$needle = 'វគ្គ';
$pos = strpos($xml, $needle);
if ($pos === false) { echo "NOT_FOUND\n"; exit; }
file_put_contents('backend/storage/app/tmp/training-section.xml', substr($xml, max(0,$pos-1000), 12000));
echo "WROTE\n";
