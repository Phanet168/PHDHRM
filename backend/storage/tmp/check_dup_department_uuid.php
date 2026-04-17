<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$dups = DB::table('departments')
    ->select('uuid', DB::raw('COUNT(*) as total'))
    ->whereNull('deleted_at')
    ->whereNotNull('uuid')
    ->where('uuid', '!=', '')
    ->groupBy('uuid')
    ->having('total', '>', 1)
    ->get();

if ($dups->isEmpty()) {
    echo "NO_DUP_UUID\n";
} else {
    foreach ($dups as $row) {
        echo $row->uuid . ' => ' . $row->total . PHP_EOL;
    }
}
