<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Report\Entities\ReportImportTemplate;
use Modules\Report\Services\His\HisTemplateSync;

/** Seeds the HC1 / HO2 import templates (later layout changes: php artisan report:sync-his-templates). */
return new class extends Migration
{
    public function up(): void
    {
        app(HisTemplateSync::class)->sync();
    }

    public function down(): void
    {
        ReportImportTemplate::whereIn('code', ['HC1', 'HO2'])->doesntHave('batches')->delete();
    }
};
