<?php
$base = dirname(__DIR__, 2);
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$svc = app(Modules\HumanResource\Support\OrgUnitRuleService::class);
$map = $svc->allowedParentTypeIdsByChildType();
$types = DB::table('org_unit_types')->pluck('code','id')->toArray();
foreach($map as $childId=>$parents){
  $child = $types[$childId] ?? $childId;
  $parentCodes = array_map(function($id) use ($types){ return $types[$id] ?? (string)$id; }, $parents);
  echo $child.' <- ['.implode(',',$parentCodes).']'.PHP_EOL;
}
