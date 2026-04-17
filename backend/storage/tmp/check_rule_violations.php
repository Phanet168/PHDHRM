<?php
$base = dirname(__DIR__, 2);
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$rules = DB::table('org_unit_type_rules')->get(['parent_type_id','child_type_id']);
$allowed=[];
foreach($rules as $r){ $allowed[(int)$r->child_type_id][(int)$r->parent_type_id]=true; }
$types = DB::table('org_unit_types')->pluck('code','id')->toArray();
$rows = DB::table('departments as d')->leftJoin('departments as p','p.id','=','d.parent_id')->get(['d.id as cid','d.department_name as cname','d.unit_type_id as ctype','d.location_code as ccode','p.id as pid','p.department_name as pname','p.unit_type_id as ptype']);
$viol=[];
foreach($rows as $r){
  $ctype=(int)$r->ctype; $pid=$r->pid? (int)$r->pid : 0; if(!$pid) continue;
  $ptype=(int)$r->ptype;
  if(!isset($allowed[$ctype][$ptype])){
    $viol[]=$r;
  }
}
echo 'violations='.count($viol).PHP_EOL;
foreach(array_slice($viol,0,40) as $v){
 echo ($types[(int)$v->ptype]??$v->ptype).' -> '.($types[(int)$v->ctype]??$v->ctype).' | '.$v->ccode.' | '.$v->cname.PHP_EOL;
}
