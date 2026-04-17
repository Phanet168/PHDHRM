<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$e = Modules\HumanResource\Entities\Employee::where('official_id_10','1861400248')->with(['gender','profileExtra'])->first();
if (!$e) {
    echo "NOT_FOUND\n";
    exit;
}

echo "ID={$e->id}\n";
echo "GENDER_RAW={$e->gender}\n";
echo "GENDER_REL=" . (optional($e->gender)->gender_name ?? '') . "\n";
echo "NATIONALITY={$e->nationality}\n";
echo "CITIZENSHIP={$e->citizenship}\n";
echo "DOB={$e->date_of_birth}\n";

$controller = app(Modules\HumanResource\Http\Controllers\EmployeeController::class);
$ref = new ReflectionClass($controller);
$m = $ref->getMethod('employeeProfilePdfData');
$m->setAccessible(true);
$data = $m->invoke($controller, $e);
$profile = $data['profile'] ?? [];
foreach (['gender','citizenship','nationality','birth_place_full','present_address_full','full_name'] as $k) {
    echo $k . '=' . ($profile[$k] ?? '') . "\n";
}
