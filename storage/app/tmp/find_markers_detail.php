<?php
$f='modules/HumanResource/Resources/views/employee/employee_detail_pdf.blade.php';
$lines=file($f, FILE_IGNORE_NEW_LINES);
foreach($lines as $i=>$line){
 if(preg_match('/[ÃÂÅáâ]/u',$line)){
   echo ($i+1).": $line\n";
 }
}
