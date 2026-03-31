<?php
$s = 'ážšážŠáŸ’áž‹áž”áž¶áž›ážáŸážáŸ’ážážŸáŸ’áž‘áž¹áž„ážáŸ’ážšáŸ‚áž„';
$t = mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
$u = mb_convert_encoding($t, 'UTF-8', 'Windows-1252');
echo "S=$s\n";
echo "T=$t\n";
echo "U=$u\n";
