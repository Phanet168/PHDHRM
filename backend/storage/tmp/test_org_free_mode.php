<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = app(Modules\HumanResource\Support\OrgUnitRuleService::class);
$map = $service->allowedParentTypeIdsByChildType();
$typeIds = array_keys($map);
$counts = [];
foreach ($map as $childTypeId => $parentIds) {
    $counts[] = count($parentIds);
}
echo 'child_type_count=' . count($typeIds) . PHP_EOL;
echo 'min_parent_options=' . min($counts) . PHP_EOL;

echo 'test validate & create' . PHP_EOL;

$parent = Modules\HumanResource\Entities\Department::whereNull('deleted_at')->whereNotNull('unit_type_id')->first();
if (!$parent) {
    echo 'no_parent_found';
    exit;
}

try {
    $service->validateParentRule(8, (int)$parent->id, null); // health_post under any parent in free mode

    $d = Modules\HumanResource\Entities\Department::create([
        'department_name' => 'TEST-FREE-SUB',
        'unit_type_id' => 8,
        'parent_id' => (int)$parent->id,
        'is_active' => 1,
        'location_code' => 'TEST-FREE-SUB-' . time(),
    ]);

    echo 'CREATED:' . $d->id . ':' . $d->uuid . ':PARENT=' . $d->parent_id . PHP_EOL;
    $d->delete();
} catch (Throwable $e) {
    echo get_class($e) . ' => ' . $e->getMessage() . PHP_EOL;
}
