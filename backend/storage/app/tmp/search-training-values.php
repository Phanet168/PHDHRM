<?php
$zip = new ZipArchive();
$path = 'backend/storage/app/tmp/debug-civil-servant-023307.docx';
$zip->open($path);
$name = null;
for ($i=0; $i<$zip->numFiles; $i++) { $n=$zip->getNameIndex($i); if (is_string($n) && str_replace('\\', '/', $n) === 'word/document.xml') { $name=$n; break; } }
$xml = $zip->getFromName($name); $zip->close();
foreach (['កម្ពុជា','វិទ្យាស្ថានពហុបច្ចេកទេសព្រះកុសមៈ','០១/០១/២០១០','អង់គ្លេស','២៥/១២/២០២២'] as $needle) {
  echo $needle . '=' . (strpos($xml, $needle) !== false ? 'YES' : 'NO') . PHP_EOL;
}
