<?php
$zip=new ZipArchive();
$path='backend/storage/app/tmp/actual-download-023307.docx';
if(!$zip->open($path)){ echo 'OPEN_FAIL'; exit; }
$name=null;
for($i=0;$i<$zip->numFiles;$i++){ $n=$zip->getNameIndex($i); if(is_string($n) && str_replace('\\','/',$n)==='word/document.xml'){ $name=$n; break; } }
$xml=$zip->getFromName($name); $zip->close();
$doc=new DOMDocument(); $doc->loadXML($xml);
$xp=new DOMXPath($doc); $xp->registerNamespace('w','http://purl.oclc.org/ooxml/wordprocessingml/main');
$tbl=$xp->query('//w:tbl')->item(0); $rows=$xp->query('./w:tr',$tbl);
foreach([3,5] as $rowNo){
  $tr=$rows->item($rowNo-1); $cells=$xp->query('./w:tc',$tr); $vals=[];
  foreach($cells as $i=>$tc){ $t=''; foreach($xp->query('.//w:t',$tc) as $tn){ $t.=$tn->textContent; } $vals[]=$t; }
  echo 'ROW'.$rowNo.'='.json_encode($vals, JSON_UNESCAPED_UNICODE).PHP_EOL;
}
?>
