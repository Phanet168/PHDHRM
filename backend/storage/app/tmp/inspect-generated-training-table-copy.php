<?php
$zip = new ZipArchive();
$path = 'backend/storage/app/tmp/debug-civil-servant-023307-copy.docx';
$zip->open($path);
$name = null;
for ($i=0; $i<$zip->numFiles; $i++) { $n=$zip->getNameIndex($i); if (is_string($n) && str_replace('\\', '/', $n) === 'word/document.xml') { $name=$n; break; } }
$xml = $zip->getFromName($name); $zip->close();
$doc = new DOMDocument();
$doc->loadXML($xml);
$xp = new DOMXPath($doc);
$xp->registerNamespace('w','http://purl.oclc.org/ooxml/wordprocessingml/main');
$tbl = $xp->query('//w:tbl')->item(0);
$rows = $xp->query('./w:tr', $tbl);
foreach ([3,5,6,7,9,10,11,13,14] as $rowNo) {
  $tr = $rows->item($rowNo-1);
  $cells = $xp->query('./w:tc', $tr);
  $parts = [];
  foreach ($cells as $i => $tc) {
    $t=''; foreach ($xp->query('.//w:t', $tc) as $tn) { $t .= $tn->textContent; }
    $parts[]='['.($i+1).']'.trim(preg_replace('/\s+/u',' ', $t));
  }
  echo 'ROW#' . $rowNo . ' ' . implode(' | ', $parts) . PHP_EOL;
}
