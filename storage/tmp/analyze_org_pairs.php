<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$typeById = DB::table('org_unit_types')->pluck('code', 'id')->toArray();
$rules = DB::table('org_unit_type_rules')->get(['parent_type_id','child_type_id']);
$allowed = [];
foreach ($rules as $r) {
    $allowed[(int)$r->parent_type_id . ':' . (int)$r->child_type_id] = true;
}

$rows = DB::table('departments as c')
    ->join('departments as p', 'p.id', '=', 'c.parent_id')
    ->whereNull('c.deleted_at')
    ->whereNull('p.deleted_at')
    ->select('p.unit_type_id as parent_type_id', 'c.unit_type_id as child_type_id', DB::raw('COUNT(*) as total'))
    ->groupBy('p.unit_type_id','c.unit_type_id')
    ->orderBy('total','desc')
    ->get();

foreach ($rows as $row) {
    $pk = (int)$row->parent_type_id;
    $ck = (int)$row->child_type_id;
    $pair = $pk . ':' . $ck;
    $isAllowed = isset($allowed[$pair]) ? 'OK' : 'MISSING_RULE';
    $parentCode = $typeById[$pk] ?? ('#'.$pk);
    $childCode = $typeById[$ck] ?? ('#'.$ck);
    echo sprintf("%s -> %s : %d [%s]", $parentCode, $childCode, (int)$row->total, $isAllowed) . PHP_EOL;
}
