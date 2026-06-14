<?php
require __DIR__ . '/../../../vendor/autoload.php';
$app = require __DIR__ . '/../../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$controller = app(Modules\HumanResource\Http\Controllers\EmployeeController::class);
$ref = new ReflectionClass($controller);
$method = $ref->getMethod('buildCivilServantBiographyWordTrainingSections');
$method->setAccessible(true);
$viewMethod = $ref->getMethod('civilServantBiographyPdfData');
$viewMethod->setAccessible(true);
$employee = Modules\HumanResource\Entities\Employee::with(['department','position','gender','marital_status','employee_type','profileExtra','familyMembers','educationHistories','foreignLanguages'])->where('employee_id','023307')->firstOrFail();
$viewData = $viewMethod->invoke($controller, $employee);
$sections = $method->invoke($controller, $viewData);
echo json_encode($sections['general'][0], JSON_UNESCAPED_UNICODE).PHP_EOL;
echo json_encode($sections['professional'][0], JSON_UNESCAPED_UNICODE).PHP_EOL;
?>
