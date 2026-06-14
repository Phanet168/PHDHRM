<?php
$zip = new ZipArchive();
$path = 'backend/storage/app/public/templates/civil-servant-biography-template-v4.docx';
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
$tables = $xp->query('//w:tbl');
foreach ($tables as $ti => $tbl) {
  $rows = $xp->query('./w:tr', $tbl);
  echo 'TABLE=' . ($ti+1) . ' ROWS=' . $rows->length . PHP_EOL;
  foreach ($rows as $ri => $tr) {
    $cells = $xp->query('./w:tc', $tr);
    $parts=[];
    foreach($cells as $ci => $tc){ $t=''; foreach($xp->query('.//w:t',$tc) as $tn){ $t .= $tn->textContent; } $parts[]='['.($ci+1).']'.trim($t); }
    echo 'ROW=' . ($ri+1) . ' ' . implode(' | ', $parts) . PHP_EOL;
  }
}
