<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
$base = dirname(__DIR__, 2);
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo 'empty=' . Illuminate\Support\Facades\DB::table('departments')->whereNull('deleted_at')->where('location_code', '')->count() . PHP_EOL;
echo 'null=' . Illuminate\Support\Facades\DB::table('departments')->whereNull('deleted_at')->whereNull('location_code')->count() . PHP_EOL;
echo 'nonnull=' . Illuminate\Support\Facades\DB::table('departments')->whereNull('deleted_at')->whereNotNull('location_code')->count() . PHP_EOL;
