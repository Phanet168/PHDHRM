<?php
$files = [
 'modules/HumanResource/Resources/views/employee/employee_pdf.blade.php',
 'modules/HumanResource/Resources/views/employee/employee_detail_pdf.blade.php',
];
foreach($files as $f){
 $c=file_get_contents($f);
 $count=0;
 if(preg_match_all('/[ÃÂÅáâ]/u',$c,$m)){$count=count($m[0]);}
 echo $f.' markers='.$count."\n";
}
