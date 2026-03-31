<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $d = Modules\HumanResource\Entities\Department::create([
        'department_name' => 'TEST-ORG-CHECK',
        'unit_type_id' => 1,
        'is_active' => 1,
    ]);
    echo "CREATED ID={$d->id} UUID={$d->uuid}\n";
    $d->delete();
} catch (Throwable $e) {
    echo get_class($e) . ': ' . $e->getMessage() . "\n";
}
