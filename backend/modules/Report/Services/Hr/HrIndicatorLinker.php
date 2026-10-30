<?php

namespace Modules\Report\Services\Hr;

use Illuminate\Support\Collection;
use Modules\Report\Entities\ReportHrProfession;
use Modules\Report\Entities\ReportIndicator;

/**
 * Links the HR indicators to the staff snapshot by reading their names, which follow a fixed pattern:
 * "<profession>(ប្រុស|ស្រី)", "<profession>(កិច្ចសន្យាកិច្ចព្រមព្រៀង) ស្រី", "ចំនួនបុគ្គលិក​សរុបនៅមណ្ឌលសុខភាព ប្រុស",
 * "ចូលនិវត្តន៍ ស្រី" … Indicators that are already linked (or don't match) are left alone.
 */
class HrIndicatorLinker
{
    private const EVENT_NAMES = [
        'បញ្ចូលក្របខ័ណ្ឌ' => 'enter_framework',
        'ចូលនិវត្តន៍' => 'retire',
        'លុបចេញពីក្របខ័ណ្ឌ' => 'remove',
        'មរណភាព' => 'death',
        'មន្ត្រីទទួលមរណភាព' => 'death',
        'ផ្ទេរចេញក្រៅខេត្ត' => 'transfer_out_province',
        'ផ្ទេរចេញក្រៅក្រសួង' => 'transfer_out_ministry',
        'ផ្ទេរចូល' => 'transfer_in',
        'ទំនេរគ្មានបៀវត្ស' => 'lwop',
        'តែងតាំងឡើងថ្នាក់' => 'promotion',
    ];

    private const WORKPLACE_NAMES = ['ទីចាត់ការមន្ទីរ' => 'phd', 'ស្រុកប្រតិបត្តិ' => 'od', 'មន្ទីរពេទ្យ' => 'hospital', 'មណ្ឌលសុខភាព' => 'hc'];

    /** Indicator kinds left for manual entry (payroll work, decorations: not in the staff records). */
    private const SKIP_TYPES = ['ផ្នែកការងារបៀវត្ស'];

    /**
     * @param  bool  $apply  false = only report what would be linked
     * @return Collection<int, array{indicator: ReportIndicator, link: array}>
     */
    public function run(bool $apply = true): Collection
    {
        $professions = ReportHrProfession::get(['id', 'name'])->mapWithKeys(fn ($p) => [self::norm($p->name) => $p->id]);

        $candidates = ReportIndicator::whereNull('hrm_measure')
            ->whereHas('unit', fn ($q) => $q->where('name', 'like', '%បុគ្គលិក%'))
            ->where(fn ($q) => $q->whereNull('indicator_type')->orWhereNotIn('indicator_type', self::SKIP_TYPES))
            ->get();

        $out = collect();
        foreach ($candidates as $indicator) {
            $link = self::parse($indicator->name, $professions);
            if (!$link) {
                continue;
            }
            if ($apply) {
                $indicator->update($link);
            }
            $out->push(['indicator' => $indicator, 'link' => $link]);
        }

        return $out;
    }

    /** HRM link read from an indicator name, or null when the name is not one of the known patterns. */
    public static function parse(string $name, Collection $professions): ?array
    {
        $n = self::norm($name);
        $sex = str_contains($n, 'ប្រុស') ? 'M' : (str_contains($n, 'ស្រី') ? 'F' : null);
        $employment = match (true) {
            str_contains($n, 'កិច្ចសន្យាកិច្ចព្រមព្រៀង') => 'contract_agreement',
            str_contains($n, 'កិច្ចសន្យា') => 'contract',
            str_contains($n, 'កិច្ចព្រមព្រៀង') => 'agreement',
            default => null,
        };
        $link = ['hrm_measure' => null, 'hrm_sex' => $sex, 'hrm_profession_id' => null, 'hrm_employment' => $employment, 'hrm_workplace' => null];

        foreach (self::EVENT_NAMES as $label => $event) {
            if (str_starts_with($n, self::norm($label))) {
                return ['hrm_measure' => $event] + $link;
            }
        }

        if (str_starts_with($n, 'ចំនួនបុគ្គ')) {
            foreach (self::WORKPLACE_NAMES as $label => $code) {
                if (str_contains($n, self::norm($label))) {
                    $link['hrm_workplace'] = $code;
                }
            }

            return ['hrm_measure' => 'headcount'] + $link;
        }

        // "<profession>" + optional "(…)" / sex suffix; the plain lists count framework staff
        $base = self::norm(preg_replace('/\(.*$|\s+(ប្រុស|ស្រី)$/u', '', $name));
        if (isset($professions[$base])) {
            return ['hrm_measure' => 'headcount', 'hrm_profession_id' => $professions[$base], 'hrm_employment' => $employment ?? 'civil'] + $link;
        }

        return null;
    }

    /** Spelling-insensitive form: no spaces / zero-width marks, one way of writing the subscript ta. */
    public static function norm(string $s): string
    {
        $s = preg_replace('/[\s\x{200B}\x{200C}\x{200D}\x{FEFF}]+/u', '', $s);

        // ្រ្ត and ្ត្រ (មន្រ្តី / មន្ត្រី) are the same word typed in two orders
        return str_replace(['្ដ', '្រ្ត', 'ក្របខណ្ឌ', 'មរណៈ'], ['្ត', '្ត្រ', 'ក្របខ័ណ្ឌ', 'មរណ'], $s);
    }
}
