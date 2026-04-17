<?php
$base = dirname(__DIR__, 2) . '/storage/app/legacy_import/unicode';
$rows = json_decode(file_get_contents($base . '/workplace_old.json'), true);
if (!is_array($rows)) { echo "invalid json\n"; exit(1);} 

$byId = [];
foreach ($rows as $r) {
  $id = trim((string)($r['WorkPlaceID'] ?? ''));
  if ($id === '') continue;
  $byId[$id] = $r;
}

$typeCount = [];
$depthCount = [];
$edgeTypeCount = [];
foreach ($rows as $r) {
  $id = trim((string)($r['WorkPlaceID'] ?? ''));
  if ($id === '') continue;
  $type = (int)($r['PlaceTypeID'] ?? 0);
  $typeCount[$type] = ($typeCount[$type] ?? 0) + 1;

  $depth = count(explode('|', $id));
  $depthCount[$depth] = ($depthCount[$depth] ?? 0) + 1;

  $parts = explode('|', $id);
  if (count($parts) > 1) {
    array_pop($parts);
    $pid = implode('|', $parts);
    if (isset($byId[$pid])) {
      $ptype = (int)($byId[$pid]['PlaceTypeID'] ?? 0);
      $k = $ptype . '->' . $type;
      $edgeTypeCount[$k] = ($edgeTypeCount[$k] ?? 0) + 1;
    }
  }
}

ksort($typeCount);
ksort($depthCount);
arsort($edgeTypeCount);

echo "TYPE COUNTS\n";
foreach ($typeCount as $t=>$c) echo $t.": ".$c."\n";

echo "\nDEPTH COUNTS\n";
foreach ($depthCount as $d=>$c) echo $d.": ".$c."\n";

echo "\nTOP EDGES parentType->childType\n";
$i=0;
foreach ($edgeTypeCount as $k=>$c){
  echo $k.": ".$c."\n";
  if(++$i>=40) break;
}

$sample = [];
foreach ($rows as $r){
  $t=(int)($r['PlaceTypeID']??0);
  if(!isset($sample[$t])) $sample[$t]=[];
  if(count($sample[$t])<3) $sample[$t][]=[
    'id'=>trim((string)($r['WorkPlaceID']??'')),
    'en'=>trim((string)($r['WorkPlaceE']??'')),
    'km'=>trim((string)($r['WorkPlaceK']??'')),
  ];
}
ksort($sample);

echo "\nSAMPLES BY TYPE\n";
foreach($sample as $t=>$arr){
  echo "Type {$t}\n";
  foreach($arr as $s){
    echo " - {$s['id']} | {$s['en']} | {$s['km']}\n";
  }
}
