<?php
require __DIR__ . '/../../../vendor/autoload.php';
$app = require __DIR__ . '/../../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$employee = Modules\HumanResource\Entities\Employee::with(['department','position','gender','marital_status','employee_type','profileExtra','familyMembers','educationHistories','foreignLanguages'])->where('employee_id','023307')->firstOrFail();
$controller = app(Modules\HumanResource\Http\Controllers\EmployeeController::class);
$ref = new ReflectionClass($controller);
$call = function($name, ...$args) use ($controller, $ref) { $m = $ref->getMethod($name); $m->setAccessible(true); return $m->invokeArgs($controller, $args); };
$viewData = $call('civilServantBiographyPdfData', $employee);
$sections = $call('buildCivilServantBiographyWordTrainingSections', $viewData);
print_r($sections);
