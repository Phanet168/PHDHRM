<?php
$zip = new ZipArchive();
$path = 'backend/storage/app/tmp/test-table-write.docx';
$zip->open($path);
$name = null;
for ($i=0; $i<$zip->numFiles; $i++) {
  $n=$zip->getNameIndex($i);
  if (is_string($n) && str_replace('\\', '/', $n) === 'word/document.xml') { $name=$n; break; }
}
$xml = $zip->getFromName($name); $zip->close();
$pos = strpos($xml,'TEST');
echo $pos . PHP_EOL;
echo substr($xml, max(0,$pos-300), 900);
