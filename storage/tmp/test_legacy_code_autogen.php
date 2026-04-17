<?php
$base = dirname(__DIR__, 2);
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\HumanResource\Http\Controllers\DepartmentController;
use Modules\HumanResource\Support\OrgUnitRuleService;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\OrgUnitType;

$parent = Department::query()->where('location_code', 'LEGACY-WP-1-2-19')->first();
$typeId = (int) OrgUnitType::query()->where('is_active', true)->value('id');
if (!$parent || $typeId <= 0) {
    echo "missing parent/type\n";
    exit(1);
}

DB::beginTransaction();
try {
    $req = Request::create('/hr/departments', 'POST', [
        'department_name' => 'TMP_CHILD_AUTO_CODE',
        'unit_type_id' => $typeId,
        'parent_id' => (int) $parent->id,
        'location_code' => '',
        'is_active' => 1,
    ]);

    $controller = app(DepartmentController::class);
    $controller->store($req, app(OrgUnitRuleService::class));

    $created = Department::query()->where('department_name', 'TMP_CHILD_AUTO_CODE')->latest('id')->first();
    echo ($created ? $created->location_code : 'not-found') . PHP_EOL;

    DB::rollBack();
} catch (Throwable $e) {
    DB::rollBack();
    echo 'error: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
