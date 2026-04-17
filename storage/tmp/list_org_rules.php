<?php
$base = dirname(__DIR__, 2);
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$rows = DB::table('org_unit_type_rules as r')
  ->join('org_unit_types as p','p.id','=','r.parent_type_id')
  ->join('org_unit_types as c','c.id','=','r.child_type_id')
  ->orderBy('p.sort_order')->orderBy('c.sort_order')
  ->get(['p.code as parent','c.code as child']);
foreach($rows as $r){ echo $r->parent.' -> '.$r->child.PHP_EOL; }
echo 'count='.count($rows).PHP_EOL;
