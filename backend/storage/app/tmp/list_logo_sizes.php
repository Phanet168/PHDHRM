<?php
$files = glob('c:/xampp/htdocs/PHDHRM/public/**/*logo*.*', GLOB_BRACE);
$extra = [
'c:/xampp/htdocs/PHDHRM/public/assets/HRM.png',
'c:/xampp/htdocs/PHDHRM/public/assets/hrm-nrw-logo.png',
'c:/xampp/htdocs/PHDHRM/public/assets/HRM1.png',
'c:/xampp/htdocs/PHDHRM/public/assets/HRM2.png',
];
$all = array_unique(array_merge($files ?: [], $extra));
sort($all);
foreach ($all as $p) {
  if (!is_file($p)) continue;
  $s=@getimagesize($p);
  echo str_replace('c:/xampp/htdocs/PHDHRM/','',$p).' | '.($s[0]??'?').'x'.($s[1]??'?').PHP_EOL;
}
