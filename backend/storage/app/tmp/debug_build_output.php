<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
require __DIR__ . '/../../../vendor/autoload.php';
$app = require __DIR__ . '/../../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$employee = Modules\HumanResource\Entities\Employee::query()->with([
    'department','position','gender','marital_status','employee_type','profileExtra','familyMembers','educationHistories','foreignLanguages'
])->first();
$controller = app(Modules\HumanResource\Http\Controllers\EmployeeController::class);

$mView = new ReflectionMethod($controller, 'civilServantBiographyPdfData');
$mView->setAccessible(true);
$viewData = $mView->invoke($controller, $employee);

$mVars = new ReflectionMethod($controller, 'employeeProfileWordTemplateVariables');
$mVars->setAccessible(true);
$placeholders = $mVars->invoke($controller, (array) data_get($viewData, 'profile', []));

$mTpl = new ReflectionMethod($controller, 'resolvePreferredCivilServantBiographyWordTemplatePath');
$mTpl->setAccessible(true);
$template = $mTpl->invoke($controller, ['full_name']);

$out = storage_path('app/tmp/final_bio_debug.docx');
@unlink($out);

$mFill = new ReflectionMethod($controller, 'fillWordTemplateDocx');
$mFill->setAccessible(true);
$filled = $mFill->invoke($controller, $template, $out, $placeholders);

$mPhoto = new ReflectionMethod($controller, 'prepareWordTemplateProfilePhoto');
$mPhoto->setAccessible(true);
$photo = $mPhoto->invoke($controller, $viewData, storage_path('app/tmp/employee-profile-docx'), 'debugfinal');

$replaced = null;
if ($photo) {
    $mReplace = new ReflectionMethod($controller, 'replaceWordTemplatePlaceholderImage');
    $mReplace->setAccessible(true);
    $replaced = $mReplace->invoke($controller, $out, $photo, 'word/media/employee_photo_placeholder.png');
}

echo json_encode([
    'template' => $template,
    'out' => $out,
    'filled' => $filled,
    'photo' => $photo,
    'replaced' => $replaced,
    'out_exists' => file_exists($out),
    'out_size' => file_exists($out) ? filesize($out) : null,
    'sample' => [
        'full_name' => $placeholders['full_name'] ?? null,
        'official_id_10' => $placeholders['official_id_10'] ?? null,
        'employee_id' => $placeholders['employee_id'] ?? null,
    ],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
