<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$rows = DB::table('departments')
    ->select('id','department_name','uuid','parent_id','unit_type_id','location_code','deleted_at')
    ->whereNull('deleted_at')
    ->where(function ($q) {
        $q->whereNull('uuid')->orWhere('uuid', '');
    })
    ->get();

foreach ($rows as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
