<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::find(1);
if (!$user) { echo "USER_NOT_FOUND\n"; exit; }

$permNames = ['read_department','create_department','update_department','delete_department'];
foreach ($permNames as $perm) {
    echo $perm . ':' . ($user->can($perm) ? 'YES' : 'NO') . PHP_EOL;
}

echo "ROLES:" . PHP_EOL;
foreach ($user->roles as $role) {
    echo '- '.$role->name.PHP_EOL;
}
