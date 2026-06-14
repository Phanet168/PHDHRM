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
preg_match_all('/\$\{[^}]+\}/', $xml, $m);
$tokens = array_values(array_unique($m[0]));
sort($tokens);
foreach ($tokens as $token) {
  if (stripos($token, 'education') !== false || stripos($token, 'academic') !== false || stripos($token, 'training') !== false || stripos($token, 'foreign') !== false || stripos($token, 'certificate') !== false || stripos($token, 'study') !== false || stripos($token, 'from') !== false || stripos($token, 'to') !== false || stripos($token, 'place') !== false) {
    echo $token . PHP_EOL;
  }
}
