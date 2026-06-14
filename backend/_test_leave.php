<?php
define('LARAVEL_START', microtime(true));
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    $user = App\Models\User::first();
    echo "User: " . ($user?->email ?? 'none') . "\n";
    $service = app(Modules\HumanResource\Support\OrgHierarchyAccessService::class);
    $result = $service->isSystemAdmin($user);
    echo "isSystemAdmin: " . ($result ? 'true' : 'false') . "\n";
    $types = Modules\HumanResource\Entities\LeaveType::all();
    echo "LeaveTypes: " . $types->count() . "\n";
    $emps = Modules\HumanResource\Entities\Employee::where('is_active', 1)->get();
    echo "Employees: " . $emps->count() . "\n";
    echo "OK\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
