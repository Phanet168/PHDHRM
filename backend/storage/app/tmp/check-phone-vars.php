<?php
require __DIR__ . '/../../../vendor/autoload.php';
$app = require __DIR__ . '/../../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$employee = Modules\HumanResource\Entities\Employee::with([
    'department','position','gender','marital_status','employee_type','profileExtra','familyMembers','educationHistories','foreignLanguages'
])->where('employee_id', '023307')->firstOrFail();
$controller = app(Modules\HumanResource\Http\Controllers\EmployeeController::class);
$ref = new ReflectionClass($controller);
$call = function($name, ...$args) use ($controller, $ref) { $m=$ref->getMethod($name); $m->setAccessible(true); return $m->invokeArgs($controller,$args); };
$viewData = $call('civilServantBiographyPdfData', $employee);
$profile = data_get($viewData, 'profile', []);
$vars = $call('employeeProfileWordTemplateVariables', $profile);
echo 'PROFILE_PHONE=' . (string) data_get($profile, 'phone', '') . PHP_EOL;
echo 'VAR_PHONE=' . (string) data_get($vars, 'phone', '') . PHP_EOL;
