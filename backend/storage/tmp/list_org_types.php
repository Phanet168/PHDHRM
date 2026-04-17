<?php
$base = dirname(__DIR__, 2);
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$rows = DB::table('org_unit_types')->orderBy('sort_order')->orderBy('id')->get(['id','code','name','name_km','sort_order','is_active']);
foreach($rows as $r){
  echo $r->id.' | '.$r->code.' | '.$r->name.' | '.$r->name_km.' | '.$r->sort_order.' | active='.$r->is_active.PHP_EOL;
}
