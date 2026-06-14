<?php
require __DIR__ . '/../../../vendor/autoload.php';
$app = require __DIR__ . '/../../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$employee = Modules\HumanResource\Entities\Employee::with('profileExtra')->where('employee_id', '023307')->first();
if (!$employee) { echo "NO_EMP\n"; exit; }
echo 'EMP_PHONE=' . (string) ($employee->phone ?? '') . PHP_EOL;
echo 'EMP_HOME_PHONE=' . (string) ($employee->home_phone ?? '') . PHP_EOL;
echo 'EMP_CELL_PHONE=' . (string) ($employee->cell_phone ?? '') . PHP_EOL;
echo 'EXTRA_CONTACT=' . (string) data_get($employee, 'profileExtra.institution_contact_no', '') . PHP_EOL;
