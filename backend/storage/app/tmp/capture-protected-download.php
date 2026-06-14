<?php
require __DIR__ . '/../../../vendor/autoload.php';
$app = require __DIR__ . '/../../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$controller = app(Modules\HumanResource\Http\Controllers\EmployeeController::class);
$ref = new ReflectionClass($controller);
$viewMethod = $ref->getMethod('civilServantBiographyPdfData');
$viewMethod->setAccessible(true);
$downloadMethod = $ref->getMethod('downloadCivilServantBiographyWordTemplate');
$downloadMethod->setAccessible(true);
$employee = Modules\HumanResource\Entities\Employee::with(['department','position','gender','marital_status','employee_type','profileExtra','familyMembers','educationHistories','foreignLanguages'])->where('employee_id','023307')->firstOrFail();
$viewData = $viewMethod->invoke($controller, $employee);
$response = $downloadMethod->invoke($controller, $viewData, 'civil-servant-biography-test.docx');
$out = 'backend/storage/app/tmp/protected-download-023307.docx';
ob_start();
$response->sendContent();
$content = ob_get_clean();
file_put_contents($out, $content);
echo 'SIZE=' . filesize($out) . PHP_EOL;
echo 'TYPE=' . get_class($response) . PHP_EOL;
?>
