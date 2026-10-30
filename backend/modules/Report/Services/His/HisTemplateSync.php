<?php

namespace Modules\Report\Services\His;

use Illuminate\Support\Facades\DB;
use Modules\Report\Entities\ReportImportBlock;
use Modules\Report\Entities\ReportImportTemplate;

/** Writes HisTemplates (code) into report_import_templates / report_import_blocks. Safe to re-run. */
class HisTemplateSync
{
    /** @return array<string,int> blocks per template */
    public function sync(): array
    {
        $counts = [];
        DB::transaction(function () use (&$counts) {
            foreach (HisTemplates::all() as $code => $def) {
                $template = ReportImportTemplate::updateOrCreate(['code' => $code], [
                    'name' => $def['name'],
                    'facility_type' => $def['facility_type'],
                ]);
                $keep = [];
                foreach ($def['blocks'] as $i => $block) {
                    ReportImportBlock::updateOrCreate(
                        ['template_id' => $template->id, 'code' => $block['code']],
                        $block + ['sort_order' => $i, 'active' => true]
                    );
                    $keep[] = $block['code'];
                }
                // blocks removed from the code definition are switched off, not deleted (old batches stay valid)
                ReportImportBlock::where('template_id', $template->id)->whereNotIn('code', $keep)->update(['active' => false]);
                $counts[$code] = count($keep);
            }
        });

        return $counts;
    }
}
