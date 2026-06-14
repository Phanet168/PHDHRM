<?php
copy('backend/storage/app/tmp/debug-civil-servant-023307.docx', 'backend/storage/app/tmp/debug-civil-servant-023307-copy.docx');
require __DIR__ . '/../../../vendor/autoload.php';
$app = require __DIR__ . '/../../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$controller = app(Modules\HumanResource\Http\Controllers\EmployeeController::class);
$ref = new ReflectionClass($controller);
$method = $ref->getMethod('updateCivilServantBiographyWordTrainingTable');
$method->setAccessible(true);
$viewMethod = $ref->getMethod('civilServantBiographyPdfData');
$viewMethod->setAccessible(true);
$employee = Modules\HumanResource\Entities\Employee::with(['department','position','gender','marital_status','employee_type','profileExtra','familyMembers','educationHistories','foreignLanguages'])->where('employee_id','023307')->firstOrFail();
$viewData = $viewMethod->invoke($controller, $employee);
$method->invoke($controller, 'backend/storage/app/tmp/debug-civil-servant-023307-copy.docx', $viewData);
$zip = new ZipArchive();
$zip->open('backend/storage/app/tmp/debug-civil-servant-023307-copy.docx');
$name = null;
for ($i=0; $i<$zip->numFiles; $i++) { $n=$zip->getNameIndex($i); if (is_string($n) && str_replace('\\', '/', $n) === 'word/document.xml') { $name=$n; break; } }
$xml = $zip->getFromName($name); $zip->close();
foreach (['កម្ពុជា','វិទ្យាស្ថានពហុបច្ចេកទេសព្រះកុសមៈ','០១/០១/២០១០','អង់គ្លេស','២៥/១២/២០២២'] as $needle) {
  echo $needle . '=' . (strpos($xml, $needle) !== false ? 'YES' : 'NO') . PHP_EOL;
}
