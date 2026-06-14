<?php
$zip = new ZipArchive();
$path = 'backend/storage/app/tmp/debug-civil-servant-023307.docx';
if ($zip->open($path) !== true) { echo "ZIP_FAIL\n"; exit; }
$name = null;
for ($i=0; $i < $zip->numFiles; $i++) {
  $n=$zip->getNameIndex($i);
  if (is_string($n) && str_replace('\\', '/', $n) === 'word/document.xml') { $name=$n; break; }
}
$xml = $zip->getFromName($name);
$zip->close();
foreach (['${phone}','០៩៧២០៩៧៨៨៨','0972097888'] as $needle) {
    echo $needle . '=' . (strpos($xml, $needle) !== false ? 'YES' : 'NO') . PHP_EOL;
}
