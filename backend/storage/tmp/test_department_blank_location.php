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
DB::beginTransaction();
try {
    $controller = app(DepartmentController::class);
    $service = app(OrgUnitRuleService::class);

    $r1 = Request::create('/hr/departments', 'POST', [
        'department_name' => 'TMP_EMPTY_A',
        'unit_type_id' => $typeId,
        'location_code' => '',
        'is_active' => 1,
    ]);
    $controller->store($r1, $service);

    $r2 = Request::create('/hr/departments', 'POST', [
        'department_name' => 'TMP_EMPTY_B',
        'unit_type_id' => $typeId,
        'location_code' => '',
        'is_active' => 1,
    ]);
    $controller->store($r2, $service);

    echo "ok\n";
    DB::rollBack();
} catch (Throwable $e) {
    DB::rollBack();
    echo "error: " . $e->getMessage() . "\n";
    exit(1);
}
