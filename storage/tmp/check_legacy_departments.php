<?php
$base = dirname(__DIR__, 2);
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$count = DB::table('departments')->where('location_code', 'like', 'LEGACY-WP-%')->count();
echo "legacy_departments_count={$count}\n";
$rows = DB::table('departments')
    ->where('location_code', 'like', 'LEGACY-WP-%')
    ->orderBy('sort_order')
    ->orderBy('department_name')
    ->limit(8)
    ->get(['id','department_name','location_code','parent_id']);
foreach ($rows as $r) {
    echo $r->id . ' | ' . $r->department_name . ' | ' . $r->location_code . ' | parent=' . ($r->parent_id ?? 'null') . "\n";
}
