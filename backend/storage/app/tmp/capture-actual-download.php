<?php
require __DIR__ . '/../../../vendor/autoload.php';
$app = require __DIR__ . '/../../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$controller = app(Modules\HumanResource\Http\Controllers\EmployeeController::class);
$response = $controller->downloadCivilServantBiography(Modules\HumanResource\Entities\Employee::where('employee_id','023307')->value('id'));
$out = 'backend/storage/app/tmp/actual-download-023307.docx';
ob_start();
$response->sendContent();
$content = ob_get_clean();
file_put_contents($out, $content);
echo 'SIZE=' . filesize($out) . PHP_EOL;
echo 'TYPE=' . get_class($response) . PHP_EOL;
?>
