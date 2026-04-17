<?php
error_reporting(E_ALL);
ini_set('display_errors','1');
$root = 'c:/xampp/htdocs/PHDHRM';
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$employees = \Modules\HumanResource\Entities\Employee::query()->take(40)->get();
foreach ($employees as $e) {
    $dob = $e->date_of_birth ?: '1966-01-01';
    $ret = \Carbon\Carbon::parse($dob)->addYears(60);
    $e->setAttribute('retirement_date', $ret->toDateString());
}

$export = new \Modules\HumanResource\Exports\RetirementListExport($employees, 2026, 2030, []);
$result = \Maatwebsite\Excel\Facades\Excel::store($export, 'tmp/ret_test_2030.xlsx', 'local');
var_dump($result);
echo storage_path('app/tmp/ret_test_2030.xlsx').PHP_EOL;
