<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Role;
use App\Models\User;

$targetPerms = ['read_department','create_department','update_department','delete_department'];

echo "ROLES\n";
foreach (Role::all() as $role) {
    echo "ROLE: {$role->name}\n";
    foreach ($targetPerms as $perm) {
        $has = $role->hasPermissionTo($perm) ? 'YES' : 'NO';
        echo "  {$perm}: {$has}\n";
    }
}

echo "\nUSERS\n";
foreach (User::with('roles')->get(['id','name','email']) as $u) {
    $roleNames = $u->roles->pluck('name')->implode(',');
    echo "USER {$u->id} {$u->name} [{$roleNames}]\n";
    foreach ($targetPerms as $perm) {
        $has = $u->can($perm) ? 'YES' : 'NO';
        echo "  {$perm}: {$has}\n";
    }
}
