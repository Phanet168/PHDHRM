<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
require __DIR__ . '/../../../vendor/autoload.php';
$app = require __DIR__ . '/../../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$employee = Modules\HumanResource\Entities\Employee::with([
    'department','position','gender','marital_status','employee_type','profileExtra','familyMembers','educationHistories','foreignLanguages'
])->where('employee_id', '023307')->firstOrFail();

$controller = app(Modules\HumanResource\Http\Controllers\EmployeeController::class);
$ref = new ReflectionClass($controller);
$call = function($name, ...$args) use ($controller, $ref) {
    $m = $ref->getMethod($name);
    $m->setAccessible(true);
    return $m->invokeArgs($controller, $args);
};

$viewData = $call('civilServantBiographyPdfData', $employee);
$profile = data_get($viewData, 'profile', []);
$vars = $call('employeeProfileWordTemplateVariables', $profile);
$template = $call('resolvePreferredCivilServantBiographyWordTemplatePath', ['full_name']);
$out = __DIR__ . '/debug-civil-servant-023307.docx';
$ok = $call('fillWordTemplateDocx', $template, $out, $vars);
$photo = $call('prepareWordTemplateProfilePhoto', $viewData, __DIR__, 'debug023307');
$replaced = false;
if ($photo) {
    $replaced = $call('replaceWordTemplatePlaceholderImage', $out, $photo, 'word/media/employee_photo_placeholder.png');
}

echo 'TEMPLATE=' . $template . PHP_EOL;
echo 'OUT=' . $out . PHP_EOL;
echo 'FILL=' . ($ok ? '1' : '0') . PHP_EOL;
echo 'PHOTO=' . ($photo ?: 'NONE') . PHP_EOL;
echo 'PHOTO_REPLACED=' . ($replaced ? '1' : '0') . PHP_EOL;
echo 'PRESENT_ADDRESS=' . ($profile['present_address'] ?? '') . PHP_EOL;
echo 'PRESENT_ADDRESS_PREFIX=' . ($profile['present_address_prefix'] ?? '') . PHP_EOL;
echo 'VAR_current_address_prefix=' . ($vars['current_address_prefix'] ?? '') . PHP_EOL;
echo 'VAR_current_village=' . ($vars['current_village'] ?? '') . PHP_EOL;
