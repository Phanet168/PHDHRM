<?php
$file='modules/HumanResource/Resources/views/employee/pay-promotion/index.blade.php';
$s=file_get_contents($file);
if(!preg_match('/áŸ.{0,120}/u',$s,$m)){echo "no\n"; exit;}
$orig=$m[0];
$try1=@iconv('UTF-8','Windows-1252//IGNORE',$orig);
$try2=@mb_convert_encoding($orig,'Windows-1252','UTF-8');
$check=function($label,$v){
  $kh=preg_match('/\p{Khmer}/u',$v)?'kh':'no';
  $moj=preg_match('/(áž|áŸ|Ã|Â|â|Å)/u',$v)?'moj':'ok';
  echo $label." len=".strlen($v)." kh=".$kh." moj=".$moj."\n";
};
$check('orig',$orig);
$check('try1',$try1?:'');
$check('try2',$try2?:'');
