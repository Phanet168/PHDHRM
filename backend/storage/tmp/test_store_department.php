<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
$base = dirname(__DIR__, 2);
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use Modules\HumanResource\Http\Controllers\DepartmentController;
use Modules\HumanResource\Support\OrgUnitRuleService;
use Modules\HumanResource\Entities\Department;

$root = Department::withoutGlobalScopes()->whereNull('deleted_at')->whereNull('parent_id')->first();
if (!$root) { echo "no root\n"; exit(1);} 
$req = Request::create('/hr/departments', 'POST', [
    'department_name' => 'TEST UNIT CLI',
    'unit_type_id' => 2,
    'parent_id' => $root->id,
    'sort_order' => 99,
    'location_code' => 'TEST-LOC-' . time(),
    'is_active' => 1,
]);

try {
    $controller = new DepartmentController();
    $resp = $controller->store($req, new OrgUnitRuleService());
    echo get_class($resp) . "\n";
    echo "ok\n";
} catch (Throwable $e) {
    echo "ERR: " . get_class($e) . " :: " . $e->getMessage() . "\n";
}
