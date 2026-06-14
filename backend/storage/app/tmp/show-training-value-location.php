<?php
$zip = new ZipArchive();
$path = 'backend/storage/app/tmp/debug-civil-servant-023307-copy.docx';
$zip->open($path);
$name = null;
for ($i=0; $i<$zip->numFiles; $i++) { $n=$zip->getNameIndex($i); if (is_string($n) && str_replace('\\', '/', $n) === 'word/document.xml') { $name=$n; break; } }
$xml = $zip->getFromName($name); $zip->close();
$pos = strpos($xml, 'កម្ពុជា');
echo 'POS=' . ($pos === false ? 'NO' : $pos) . PHP_EOL;
if ($pos !== false) { echo substr($xml, max(0,$pos-300), 1200); }
