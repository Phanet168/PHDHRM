<?php
$path='backend/storage/app/tmp/protected-download-023307.docx';
$zip=new ZipArchive();
$status=$zip->open($path);
var_dump($status);
if($status!==true){ exit; }
var_dump($zip->numFiles);
for($i=0;$i<min($zip->numFiles,5);$i++){ var_dump($zip->getNameIndex($i)); }
$zip->close();
?>
