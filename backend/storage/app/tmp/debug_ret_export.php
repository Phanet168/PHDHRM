<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
$root = dirname(__DIR__, 3);
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$employees = \Modules\HumanResource\Entities\Employee::query()->take(8)->get();
$export = new \Modules\HumanResource\Exports\RetirementListExport($employees, 2026, 2026, []);
$result = \Maatwebsite\Excel\Facades\Excel::store($export, 'tmp/ret_test.xlsx', 'local');
var_dump($result);
echo storage_path('app/tmp/ret_test.xlsx') . PHP_EOL;
