<?php
$base = dirname(__DIR__, 2);
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$svc = app(Modules\HumanResource\Support\OrgUnitRuleService::class);
$allowedMap = $svc->allowedParentTypeIdsByChildType();
$types = DB::table('org_unit_types')->pluck('code','id')->toArray();
$rows = DB::table('departments as d')->leftJoin('departments as p','p.id','=','d.parent_id')->get(['d.id as cid','d.department_name as cname','d.unit_type_id as ctype','d.location_code as ccode','p.id as pid','p.unit_type_id as ptype']);
$viol=[];
foreach($rows as $r){
  if(!$r->pid) continue;
  $ctype=(int)$r->ctype; $ptype=(int)$r->ptype;
  $allowed = $allowedMap[$ctype] ?? [];
  if(!in_array($ptype, $allowed, true)){
    $viol[]=$r;
  }
}
echo 'violations='.count($viol).PHP_EOL;
foreach(array_slice($viol,0,30) as $v){
 echo ($types[(int)$v->ptype]??$v->ptype).' -> '.($types[(int)$v->ctype]??$v->ctype).' | '.$v->ccode.' | '.$v->cname.PHP_EOL;
}
