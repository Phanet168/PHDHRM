<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data fix after the PHDReport import:
 * - indicators parked in "មិនបានកំណត់ផ្នែក" whose type names exactly one unit move into that unit (with their
 *   values), so e.g. all private-sector indicators are entered in one place;
 * - 638 was imported as a second "មន្ទីរសម្រាកព្យាបាល": its code sits in the "(គ្មានច្បាប់)" block
 *   (638 646 651 653 654 659 660 663), so it gets that name.
 */
return new class extends Migration
{
    public function up(): void
    {
        $parked = DB::table('report_units')->where('name', 'មិនបានកំណត់ផ្នែក')->pluck('id');
        foreach (DB::table('report_indicators')->whereIn('unit_id', $parked)->whereNotNull('indicator_type')->get(['id', 'indicator_type']) as $i) {
            $units = DB::table('report_units')->where('name', $i->indicator_type)->whereNotIn('id', $parked)->get(['id', 'department_id']);
            if ($units->count() !== 1) {
                continue;
            }
            DB::table('report_indicators')->where('id', $i->id)->update(['unit_id' => $units[0]->id, 'department_id' => $units[0]->department_id, 'updated_at' => now()]);
            DB::table('report_values')->where('indicator_id', $i->id)->update(['unit_id' => $units[0]->id]);
        }

        DB::table('report_indicators')->where('code', '638')->where('name', 'មន្ទីរសម្រាកព្យាបាល')
            ->update(['name' => 'មន្ទីរសម្រាកព្យាបាល(គ្មានច្បាប់)', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // a placement fix: nothing to undo automatically (indicators can be moved in Settings)
    }
};
