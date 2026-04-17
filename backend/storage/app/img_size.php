<?php
$files = [
    'public/assets/HRM1.png',
    'public/assets/HRM2.png',
    'public/storage/applications/logo.png',
    'public/storage/logo.png'
];
foreach ($files as $f) {
  if (is_file($f)) {
    $s = @getimagesize($f);
    echo $f . ' => ' . ($s ? ($s[0].'x'.$s[1]) : 'unknown') . PHP_EOL;
  }
}
