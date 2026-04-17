<?php
$s = 'ážšážŠáŸ’áž‹áž”áž¶áž›ážáŸážáŸ’ážážŸáŸ’áž‘áž¹áž„ážáŸ’ážšáŸ‚áž„';
$t = mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
$u = mb_convert_encoding($t, 'UTF-8', 'Windows-1252');
var_dump($s);
var_dump($t);
var_dump($u);
