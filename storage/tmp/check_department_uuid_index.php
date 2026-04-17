<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$index = DB::select("SHOW INDEX FROM departments WHERE Key_name='departments_uuid_unique'");
echo empty($index) ? 'NO_INDEX' : 'HAS_INDEX';
