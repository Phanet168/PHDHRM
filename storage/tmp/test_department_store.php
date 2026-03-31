<?php
$base = dirname(__DIR__, 2);
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\HumanResource\Http\Controllers\DepartmentController;
use Modules\HumanResource\Support\OrgUnitRuleService;
use Modules\HumanResource\Entities\OrgUnitType;

$typeId = (int) OrgUnitType::query()->where('is_active', true)->value('id');
if ($typeId <= 0) {
    echo "no-unit-type\n";
    exit(1);
}

DB::beginTransaction();
try {
    $request = Request::create('/hr/departments', 'POST', [
        'department_name' => 'TMP_TEST_CREATE',
        'unit_type_id' => $typeId,
        'location_code' => 'TMP-TEST-' . time(),
        'is_active' => 1,
    ]);

    $controller = app(DepartmentController::class);
    $response = $controller->store($request, app(OrgUnitRuleService::class));

    echo get_class($response) . PHP_EOL;
    echo method_exists($response, 'getStatusCode') ? $response->getStatusCode() . PHP_EOL : "no-status\n";

    DB::rollBack();
    echo "ok\n";
} catch (Throwable $e) {
    DB::rollBack();
    echo "error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
