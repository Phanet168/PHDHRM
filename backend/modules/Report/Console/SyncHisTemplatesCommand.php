<?php

namespace Modules\Report\Console;

use Illuminate\Console\Command;
use Modules\Report\Services\His\HisTemplateSync;

class SyncHisTemplatesCommand extends Command
{
    protected $signature = 'report:sync-his-templates';

    protected $description = 'Write the HIS workbook layouts (HC1, HO2) into the import template tables';

    public function handle(HisTemplateSync $sync): int
    {
        foreach ($sync->sync() as $code => $blocks) {
            $this->line(sprintf('%-6s %d blocks', $code, $blocks));
        }

        return self::SUCCESS;
    }
}
