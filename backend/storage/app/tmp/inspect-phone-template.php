<?php
$zip = new ZipArchive();
$path = 'backend/storage/app/public/templates/civil-servant-biography-template-v4.docx';
if ($zip->open($path) !== true) { echo "ZIP_FAIL\n"; exit; }
$name = null;
for ($i=0; $i < $zip->numFiles; $i++) {
  $n=$zip->getNameIndex($i);
  if (is_string($n) && str_replace('\\', '/', $n) === 'word/document.xml') { $name=$n; break; }
}
$xml = $zip->getFromName($name);
$zip->close();
$pos = strpos($xml, '${phone}');
echo 'PHONE_POS=' . ($pos === false ? 'NO' : $pos) . PHP_EOL;
if ($pos !== false) { echo substr($xml, max(0,$pos-500), 1200); }
