<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$row = DB::table('departments')
    ->selectRaw("COUNT(*) AS total, SUM(CASE WHEN uuid IS NULL OR uuid = '' THEN 1 ELSE 0 END) AS empty_uuid")
    ->whereNull('deleted_at')
    ->first();

print_r($row);
