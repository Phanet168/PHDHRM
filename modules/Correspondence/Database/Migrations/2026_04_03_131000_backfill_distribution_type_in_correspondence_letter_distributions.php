<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('correspondence_letter_distributions')) {
            return;
        }

        if (!Schema::hasColumn('correspondence_letter_distributions', 'distribution_type')) {
            return;
        }

        DB::table('correspondence_letter_distributions')
            ->whereNull('distribution_type')
            ->orWhere('distribution_type', '')
            ->update(['distribution_type' => 'to']);
    }

    public function down(): void
    {
        // no-op
    }
};
