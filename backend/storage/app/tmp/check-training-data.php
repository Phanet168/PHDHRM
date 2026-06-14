<?php
require __DIR__ . '/../../../vendor/autoload.php';
$app = require __DIR__ . '/../../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$employee = Modules\HumanResource\Entities\Employee::with(['educationHistories','foreignLanguages'])->where('employee_id','023307')->first();
if (!$employee) { echo "NO_EMP\n"; exit; }
foreach ($employee->educationHistories as $i => $row) {
  echo 'EDU#' . ($i+1) . ' degree=' . ($row->degree_level ?? '') . ' major=' . ($row->major_subject ?? '') . ' place=' . ($row->institution_name ?? '') . ' note=' . ($row->note ?? '') . ' from=' . ($row->start_date ?? '') . ' to=' . ($row->end_date ?? '') . PHP_EOL;
}
foreach ($employee->foreignLanguages as $i => $row) {
  echo 'LANG#' . ($i+1) . ' lang=' . ($row->language_name ?? '') . ' place=' . ($row->institution_name ?? '') . ' result=' . ($row->result ?? '') . ' from=' . ($row->start_date ?? '') . ' to=' . ($row->end_date ?? '') . PHP_EOL;
}
