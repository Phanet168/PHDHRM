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
$doc = new DOMDocument();
$doc->loadXML($xml);
$xp = new DOMXPath($doc);
$xp->registerNamespace('w','http://purl.oclc.org/ooxml/wordprocessingml/main');
$tbl = $xp->query('//w:tbl')->item(0);
$rows = $xp->query('./w:tr',$tbl);
$tr = $rows->item(2);
$cells = $xp->query('./w:tc',$tr);
foreach ($cells as $i => $tc) {
  $text=''; foreach ($xp->query('.//w:t',$tc) as $tn) { $text .= $tn->textContent; }
  echo '['.($i+1).']' . trim($text) . PHP_EOL;
}
