<?php
copy('backend/storage/app/public/templates/civil-servant-biography-template-v4.docx', 'backend/storage/app/tmp/test-table-write.docx');
$zip = new ZipArchive();
$path = 'backend/storage/app/tmp/test-table-write.docx';
$zip->open($path);
$name = null;
for ($i=0; $i<$zip->numFiles; $i++) {
  $n=$zip->getNameIndex($i);
  if (is_string($n) && str_replace('\\', '/', $n) === 'word/document.xml') { $name=$n; break; }
}
$xml = $zip->getFromName($name);
$dom = new DOMDocument();
$dom->loadXML($xml);
$xp = new DOMXPath($dom);
$xp->registerNamespace('w','http://purl.oclc.org/ooxml/wordprocessingml/main');
$tbl = $xp->query('//w:tbl')->item(0);
$rows = $xp->query('./w:tr',$tbl);
$tr = $rows->item(2);
$cells = $xp->query('./w:tc',$tr);
$tc = $cells->item(1);
$texts = $xp->query('.//w:t',$tc);
$first = true;
foreach ($texts as $t) { $t->nodeValue = $first ? 'TEST' : ''; $first = false; }
$zip->addFromString($name, $dom->saveXML());
$zip->close();
echo "DONE\n";
