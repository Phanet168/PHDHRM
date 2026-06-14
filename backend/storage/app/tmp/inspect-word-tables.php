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
libxml_use_internal_errors(true);
$doc = new DOMDocument();
$doc->loadXML($xml);
$xp = new DOMXPath($doc);
$xp->registerNamespace('w','http://purl.oclc.org/ooxml/wordprocessingml/main');
$tables = $xp->query('//w:tbl');
echo 'TABLES=' . $tables->length . PHP_EOL;
foreach ($tables as $ti => $tbl) {
  echo 'TABLE#' . ($ti+1) . PHP_EOL;
  $rows = $xp->query('./w:tr', $tbl);
  foreach ($rows as $ri => $tr) {
    $cells = $xp->query('./w:tc', $tr);
    $texts = [];
    foreach ($cells as $ci => $tc) {
      $t='';
      foreach ($xp->query('.//w:t', $tc) as $tn) { $t .= $tn->textContent; }
      $texts[] = '[' . ($ci+1) . ']' . trim(preg_replace('/\s+/u',' ', $t));
    }
    echo '  ROW#' . ($ri+1) . ' CELLS=' . $cells->length . ' ' . implode(' | ', $texts) . PHP_EOL;
  }
}
