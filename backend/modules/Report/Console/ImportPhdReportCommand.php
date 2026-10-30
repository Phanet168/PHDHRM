<?php

namespace Modules\Report\Console;

use Illuminate\Console\Command;
use Modules\Report\Http\Controllers\Health\SettingController;
use Modules\Report\Services\MasterDataImporter;

/**
 * php artisan report:import-phdreport                      → bundled PHDReport indicator list
 * php artisan report:import-phdreport path/to/export.json  → indicator list or Firestore collections export
 */
class ImportPhdReportCommand extends Command
{
    protected $signature = 'report:import-phdreport {file? : JSON file (indicator list or {departments, units, indicators, periods, reports, actions, import_mappings})}';

    protected $description = 'Import PHDReport (Firebase) master data and values into the health activity report tables';

    public function handle(MasterDataImporter $importer): int
    {
        $path = $this->argument('file') ?: module_path('Report', SettingController::BUNDLED_INDICATORS);
        if (!is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $counts = $importer->import(MasterDataImporter::decode(file_get_contents($path)));
        foreach ($counts as $collection => $n) {
            $this->line(sprintf('%-16s %d', $collection, $n));
        }
        $this->info('Done.');

        return self::SUCCESS;
    }
}
