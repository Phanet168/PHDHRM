<?php
foreach (['Ã¡Å¸Â ','ážšážŠáŸ’áž‹áž”áž¶áž›ážáŸážáŸ’ážážŸáŸ’áž‘áž¹áž„ážáŸ’ážšáŸ‚áž„'] as $s) {
  echo "INPUT: $s\n";
  $v=$s;
  for($i=1;$i<=3;$i++){
    $v=mb_convert_encoding($v,'Windows-1252','UTF-8');
    echo " pass$i: $v\n";
  }
  echo "---\n";
}
