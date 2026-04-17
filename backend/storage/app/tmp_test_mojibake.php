<?php
$s = "áž€áŸ†áž–áž»áž„áž”áŸ†ážšáž¾áž€áž¶ážšáž„áž¶ážš";
$arr = [
  'orig' => $s,
  'utf8_encode' => @utf8_encode($s),
  'iconv' => @iconv('Windows-1252','UTF-8//IGNORE',$s),
  'mb_cp1252' => @mb_convert_encoding($s,'UTF-8','Windows-1252'),
  'mb_iso' => @mb_convert_encoding($s,'UTF-8','ISO-8859-1'),
];
foreach ($arr as $k => $v) {
  $has = preg_match('/\p{Khmer}/u', $v) ? 'KH' : 'NO';
  echo $k . ':' . $has . ':' . $v . PHP_EOL;
}
