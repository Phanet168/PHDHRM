<?php
$base = dirname(__DIR__, 2);
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = DB::getDatabaseName();
$rows = DB::select("SELECT INDEX_NAME, NON_UNIQUE, COLUMN_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'departments' ORDER BY INDEX_NAME, SEQ_IN_INDEX", [$db]);
foreach($rows as $r){ echo $r->INDEX_NAME.' | '.$r->NON_UNIQUE.' | '.$r->COLUMN_NAME.PHP_EOL; }
