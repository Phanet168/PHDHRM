<?php
$s = "áž–áŸ’ážšáŸ‡";
echo iconv('Windows-1252','UTF-8//IGNORE',$s), PHP_EOL;
echo iconv('UTF-8','Windows-1252//IGNORE',$s), PHP_EOL;
