<?php
$file='public/storage/application/1773483856logo.png';
if (is_file($file)) {
  $s=@getimagesize($file);
  echo $file.' => '.($s?$s[0].'x'.$s[1]:'unknown').PHP_EOL;
} else {
  echo 'missing'.PHP_EOL;
}
