<?php
$base = dirname(__DIR__, 2);
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$rows = json_decode(file_get_contents($base . '/storage/app/legacy_import/unicode/workplace_old.json'), true);
$legacyByCode = [];
foreach($rows as $r){
  $id=trim((string)($r['WorkPlaceID']??'')); if($id==='') continue;
  $legacyByCode['LEGACY-WP-'.str_replace('|','-',$id)] = $r;
}

$deps = DB::table('departments as d')
  ->leftJoin('org_unit_types as t','t.id','=','d.unit_type_id')
  ->where('d.location_code','like','LEGACY-WP-%')
  ->where('t.code','bureau')
  ->get(['d.id','d.location_code','d.department_name']);

foreach($deps as $d){
  $r=$legacyByCode[$d->location_code]??null;
  $pt=(int)($r['PlaceTypeID']??0);
  if($pt===17){
    echo $d->id.' | '.$d->location_code.' | '.$d->department_name.' | '.$r['WorkPlaceE']."\n";
  }
}
