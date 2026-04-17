<?php
$base = dirname(__DIR__, 2);
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo 'org_unit_types_count=' . DB::table('org_unit_types')->count() . PHP_EOL;
echo 'departments_count=' . DB::table('departments')->count() . PHP_EOL;
