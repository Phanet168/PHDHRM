<?php
$base = dirname(__DIR__, 2);
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$rows = json_decode(file_get_contents($base . '/storage/app/legacy_import/unicode/workplace_old.json'), true);
$legacyByCode = [];
foreach($rows as $r){
  $id = trim((string)($r['WorkPlaceID'] ?? ''));
  if($id==='') continue;
  $code = 'LEGACY-WP-' . str_replace('|','-',$id);
  $legacyByCode[$code] = $r;
}

$deps = DB::table('departments as d')
  ->leftJoin('org_unit_types as t','t.id','=','d.unit_type_id')
  ->where('d.location_code','like','LEGACY-WP-%')
  ->get(['d.id','d.location_code','d.department_name','d.parent_id','t.code as unit_code']);

$counts=[];
$mismatch=[];
foreach($deps as $d){
  $r = $legacyByCode[$d->location_code] ?? null;
  $pt = $r ? (int)($r['PlaceTypeID'] ?? 0) : -1;
  $k = $pt.'->'.($d->unit_code ?: 'null');
  $counts[$k]=($counts[$k]??0)+1;
}
arsort($counts);
foreach($counts as $k=>$c){ echo $k.' : '.$c.PHP_EOL; }
