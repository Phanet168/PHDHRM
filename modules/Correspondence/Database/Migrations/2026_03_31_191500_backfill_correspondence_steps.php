<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Correspondence\Entities\CorrespondenceLetter;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('correspondence_letters') || !Schema::hasColumn('correspondence_letters', 'current_step')) {
            return;
        }

        DB::table('correspondence_letters')
            ->whereNull('current_step')
            ->where('letter_type', CorrespondenceLetter::TYPE_INCOMING)
            ->update(['current_step' => CorrespondenceLetter::STEP_INCOMING_RECEIVED]);

        DB::table('correspondence_letters')
            ->whereNull('current_step')
            ->where('letter_type', CorrespondenceLetter::TYPE_OUTGOING)
            ->update(['current_step' => CorrespondenceLetter::STEP_OUTGOING_DRAFT]);
    }

    public function down(): void
    {
        // no-op
    }
};

