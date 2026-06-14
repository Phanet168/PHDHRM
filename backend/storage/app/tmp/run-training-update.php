<?php
require __DIR__ . '/../../../vendor/autoload.php';
$app = require __DIR__ . '/../../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$controller = app(Modules\HumanResource\Http\Controllers\EmployeeController::class);
$ref = new ReflectionClass($controller);
$method = $ref->getMethod('updateCivilServantBiographyWordTrainingTable');
$method->setAccessible(true);
$employee = Modules\HumanResource\Entities\Employee::with(['department','position','gender','marital_status','employee_type','profileExtra','familyMembers','educationHistories','foreignLanguages'])->where('employee_id','023307')->firstOrFail();
$viewMethod = $ref->getMethod('civilServantBiographyPdfData');
$viewMethod->setAccessible(true);
$viewData = $viewMethod->invoke($controller, $employee);
$path = 'backend/storage/app/tmp/debug-civil-servant-023307.docx';
$result = $method->invoke($controller, $path, $viewData);
echo 'RESULT=' . ($result ? '1' : '0') . PHP_EOL;
