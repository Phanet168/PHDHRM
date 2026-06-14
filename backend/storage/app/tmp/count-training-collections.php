<?php
require __DIR__ . '/../../../vendor/autoload.php';
$app = require __DIR__ . '/../../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$employee = Modules\HumanResource\Entities\Employee::with(['educationHistories','foreignLanguages'])->where('employee_id','023307')->firstOrFail();
$controller = app(Modules\HumanResource\Http\Controllers\EmployeeController::class);
$ref = new ReflectionClass($controller);
$m = $ref->getMethod('civilServantBiographyPdfData');
$m->setAccessible(true);
$data = $m->invoke($controller, $employee);
echo 'ACADEMIC=' . count(data_get($data,'academic_infos',[])) . PHP_EOL;
echo 'EDU=' . count(data_get($data,'education_histories',[])) . PHP_EOL;
echo 'LANG=' . count(data_get($data,'foreign_languages',[])) . PHP_EOL;
