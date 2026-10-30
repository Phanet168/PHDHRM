<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Report\Services\CodeGenerator;

/**
 * Private-sector indicators: every facility type gets all five figures (ចំនួន, បើកថ្មី, បន្តសុពលភាព,
 * គ្មានច្បាប់, លុបចោល) so the data-entry grid has no gaps.
 * - 739 sits in the "(បើកថ្មី)" block (738 ឱសថស្ថាន, 739, 740 ឱសថស្ថានរង (ខ), 741 អគារលក់ឱសថបូរាណ) where
 *   ឱសថស្ថានរង (ក) is missing, so it is named "ឱសថស្ថានរង (ក)(បើកថ្មី)";
 * - the missing ones are added, settings copied from a sibling of the same row.
 */
return new class extends Migration
{
    private const UNIT = 'ការគ្រប់គ្រងផ្នែកឯកជនក្នុងវិស័យសុខាភិបាល';

    /** new name => code of the indicator whose settings it copies */
    private const ADD = [
        'បន្ទប់ពិគ្រោះព្យាបាលជំងឺ(លុបចោល)' => '621',
        'ឱសថស្ថាន(បន្តសុពលភាព)' => '726',
        'ឱសថស្ថានរង (ក)' => '726',
        'ឱសថស្ថានរង (ក)(បន្តសុពលភាព)' => '726',
        'ឱសថស្ថានរង (ក)(គ្មានច្បាប់)' => '726',
        'ឱសថស្ថានរង (ខ)(បន្តសុពលភាព)' => '728',
        'អគារលក់ឱសថបូរាណ(បន្តសុពលភាព)' => '729',
    ];

    public function up(): void
    {
        $unit = DB::table('report_units')->where('name', self::UNIT)->first();
        if (!$unit) {
            return;
        }
        DB::table('report_indicators')->where('code', '739')->where('unit_id', $unit->id)->where('name', 'ឱសថស្ថាន (បន្តសុពលភាព)')
            ->update(['name' => 'ឱសថស្ថានរង (ក)(បើកថ្មី)', 'updated_at' => now()]);

        $key = fn ($s) => preg_replace('/[\s\x{200B}]+/u', '', str_replace('សុពុលភាព', 'សុពលភាព', $s));
        $existing = DB::table('report_indicators')->where('unit_id', $unit->id)->pluck('name')->map($key)->flip();
        foreach (self::ADD as $name => $like) {
            if (isset($existing[$key($name)])) {
                continue;
            }
            $tpl = DB::table('report_indicators')->where('code', $like)->first();
            DB::table('report_indicators')->insert([
                'code' => CodeGenerator::next('report_indicators'),
                'name' => $name,
                'indicator_type' => $tpl->indicator_type ?? self::UNIT,
                'direction' => $tpl->direction ?? 'up',
                'aggregation' => $tpl->aggregation ?? 'none',
                'department_id' => $unit->department_id,
                'unit_id' => $unit->id,
                'owner_user_id' => $tpl->owner_user_id ?? null,
                'sort_order' => $tpl->sort_order ?? 0,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $unit = DB::table('report_units')->where('name', self::UNIT)->value('id');
        DB::table('report_indicators')->where('unit_id', $unit)->whereIn('name', array_keys(self::ADD))
            ->whereNotExists(fn ($q) => $q->from('report_values')->whereColumn('report_values.indicator_id', 'report_indicators.id'))
            ->delete();
        DB::table('report_indicators')->where('code', '739')->where('name', 'ឱសថស្ថានរង (ក)(បើកថ្មី)')->update(['name' => 'ឱសថស្ថាន (បន្តសុពលភាព)']);
    }
};
