<?php
$base = dirname(__DIR__, 2);
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$rows = Illuminate\Support\Facades\DB::table('org_unit_types')->select('id','code','name','name_km','is_active','sort_order')->orderBy('sort_order')->orderBy('id')->get();
foreach ($rows as $r) { echo $r->id.'|'.$r->code.'|'.$r->is_active.'|'.$r->name.'|'.$r->name_km.PHP_EOL; }
