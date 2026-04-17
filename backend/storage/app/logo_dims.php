<?php
$files=['public/assets/logo.png','public/assets/logo2.png','public/assets/hrm-nrw-logo.png','public/assets/HRM1.png','public/assets/logo/logo/Logo​.png'];
foreach($files as $f){
  if(!is_file($f)){echo $f." => missing\n";continue;}
  $s=@getimagesize($f);
  echo $f.' => '.($s?$s[0].'x'.$s[1]:'unknown')."\n";
}
