<?php

namespace Modules\Report\Http\Controllers\Health;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Report\Entities\ReportImportMapping;
use Modules\Report\Entities\ReportValue;
use Modules\Report\Services\ExcelImportService;
use Modules\Report\Services\HealthReportData;
use Modules\Report\Services\PeriodCode;
use Modules\Report\Services\WorkbookReader;
use Modules\Report\Services\XlsxExport;
use Throwable;

class ImportController extends HealthController
{
    private const UPLOAD_DIR = 'report-health-import';

    /** Upload form; with ?token= it also shows the stashed preview (so refreshing the page is safe). */
    public function index(Request $request)
    {
        $stash = $request->filled('token') ? Cache::get($this->cacheKey($request, (string) $request->input('token'))) : null;
        if (!$stash) {
            return $this->page($request, $this->periodFromRequest($request));
        }

        $p = PeriodCode::parse($stash['period']);

        // Values already stored for this period, so the preview shows what the import would replace.
        $existing = ReportValue::query()
            ->whereHas('period', fn ($q) => $q->where('code', $p['code']))
            ->whereIn('indicator_id', array_filter(array_column($stash['rows'], 'indicator_id')))
            ->pluck('value', 'indicator_id');
        $rows = array_map(fn ($r) => $r + ['current' => $existing[$r['indicator_id']] ?? null], $stash['rows']);

        return $this->page($request, $p, [
            'token' => $request->input('token'),
            'mode' => $stash['mode'],
            'rows' => $rows,
            'warnings' => $stash['warnings'] ?? [],
            'viewer' => $this->viewer($request, $stash['files'] ?? []),
        ]);
    }

    /** One sheet of an uploaded workbook (?src=HC|HOSP|Summary&sheet=…) so users can see the file and pick cells for mappings. */
    private function viewer(Request $request, array $files): ?array
    {
        $files = array_filter($files, fn ($path) => Storage::exists($path));
        if (!$files) {
            return null;
        }
        $src = array_key_exists($request->input('src'), $files) ? $request->input('src') : array_key_first($files);
        try {
            $reader = WorkbookReader::open(Storage::path($files[$src]));

            return ['sources' => array_keys($files), 'src' => $src, 'sheets' => $reader->sheetNames()] + $reader->grid($request->input('sheet'));
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Keep the uploaded workbooks next to the preview for an hour (sheet viewer); older uploads are pruned. */
    private function keepUploads(Request $request, string $token): array
    {
        foreach (Storage::files(self::UPLOAD_DIR) as $old) {
            if (Storage::lastModified($old) < now()->subHours(2)->getTimestamp()) {
                Storage::delete($old);
            }
        }
        $kept = [];
        foreach (['HC' => 'file_hc', 'HOSP' => 'file_hosp', 'Summary' => 'file_summary'] as $label => $field) {
            if ($request->hasFile($field)) {
                $file = $request->file($field);
                $kept[$label] = $file->storeAs(self::UPLOAD_DIR, "{$token}_{$label}." . strtolower($file->getClientOriginalExtension()));
            }
        }

        return $kept;
    }

    public function preview(Request $request, ExcelImportService $import)
    {
        // Follow the files actually sent: only a summary file means summary mode, whatever tab was active.
        $hasMappingFile = $request->hasFile('file_hc') || $request->hasFile('file_hosp');
        $mode = $request->hasFile('file_summary') && !$hasMappingFile ? 'summary' : ($hasMappingFile ? 'mapping' : $request->input('mode'));
        $request->merge(['mode' => $mode]);
        $mapping = $mode === 'mapping';

        $request->validate([
            'mode' => ['required', 'in:mapping,summary'],
            'file_hc' => array_merge($this->excelRule(false), $mapping ? ['required_without:file_hosp'] : []),
            'file_hosp' => $this->excelRule(false),
            'file_summary' => $this->excelRule(!$mapping),
        ], [
            'file_hc.required_without' => 'សូមជ្រើសឯកសារ HC ឬ HOSP យ៉ាងហោចណាស់មួយ',
            'file_summary.required' => 'សូមជ្រើសឯកសារ Summary Excel',
            'mode.required' => 'សូមជ្រើសរបៀបនាំចូល',
            'max' => ':attribute ធំជាង ១០MB',
            'uploaded' => ':attribute upload មិនបានសម្រេច (ទំហំធំពេក ឬការតភ្ជាប់ដាច់)',
        ], ['file_hc' => 'ឯកសារ HC', 'file_hosp' => 'ឯកសារ HOSP', 'file_summary' => 'ឯកសារ Summary']);

        $p = $this->periodFromRequest($request);
        try {
            $result = $mapping
                ? $import->previewMapping($request->file('file_hc')?->getRealPath(), $request->file('file_hosp')?->getRealPath())
                : $import->previewSummary($request->file('file_summary')->getRealPath());
        } catch (InvalidArgumentException $e) {
            return redirect()->route('report.health.import')->withInput()->withErrors(['file' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('report.health.import')->withInput()->withErrors(['file' => 'អាន Excel បរាជ័យ: ' . $e->getMessage()]);
        }

        $token = (string) Str::uuid();
        Cache::put($this->cacheKey($request, $token), [
            'period' => $p['code'],
            'mode' => $request->input('mode'),
            'rows' => $result['rows'],
            'warnings' => $result['warnings'],
            'files' => $this->keepUploads($request, $token),
        ], now()->addHour());

        // Post/Redirect/Get: the preview lives at a GET URL, so refresh / back never re-POSTs.
        return redirect()->route('report.health.import', ['token' => $token]);
    }

    public function commit(Request $request, ExcelImportService $import)
    {
        $request->validate(['token' => ['required', 'uuid']]);
        $stash = Cache::get($this->cacheKey($request, $request->input('token')));
        if (!$stash) {
            return redirect()->route('report.health.import')->withErrors(['file' => 'Preview ផុតកំណត់ សូមផ្ទៀងផ្ទាត់ម្តងទៀត']);
        }

        $period = $this->period($stash['period']);
        if ($period->locked) {
            return redirect()->route('report.health.import')->withErrors(['file' => 'រយៈពេលនេះត្រូវបានចាក់សោ']);
        }

        $result = $import->commit($stash['rows'], $period, $request->user(), $this->access);
        Cache::forget($this->cacheKey($request, $request->input('token')));
        Storage::delete(array_values($stash['files'] ?? []));

        $message = 'នាំចូលបាន ' . PeriodCode::kh($result['saved']) . ' សូចនាករ សម្រាប់ ' . $period->label
            . ($result['skipped'] ? ' • រំលង ' . PeriodCode::kh($result['skipped']) : '');

        return redirect()->route('report.health.entry', ['period' => $period->code])
            ->with('success', $message)
            ->with('import_errors', array_slice($result['errors'], 0, 10));
    }

    /** Blank summary-mode template listing the indicators the user can import. */
    public function template(Request $request, HealthReportData $data)
    {
        $editable = $this->access->editableUnitIds($request->user());
        $rows = $data->rows(null, $editable === null ? null : ($editable ?: null));

        return XlsxExport::download(
            'phd-report-template.xlsx',
            'Summary',
            \Modules\Report\Services\ExcelImportService::SUMMARY_COLUMNS,
            $rows->map(fn ($r) => [$r['code'], $r['name'], $r['unit'], null, null, null, null])->all(),
            [14, 70, 30, 12, 12, 12, 12]
        );
    }

    public function exportPreview(Request $request)
    {
        $stash = Cache::get($this->cacheKey($request, (string) $request->input('token')));
        abort_unless($stash, 404);

        return XlsxExport::download(
            "preview_{$stash['period']}.xlsx",
            'Preview',
            ['ID', 'សូចនាករ', 'ផ្នែក', 'HC', 'HOSP', 'សរុប', 'ចំណាំ'],
            array_map(fn ($r) => [$r['code'], $r['name'], $r['unit'], $r['hc'], $r['hosp'], $r['total'], implode('; ', $r['notes'])], $stash['rows']),
            [12, 70, 30, 12, 12, 12, 30]
        );
    }

    private function page(Request $request, array $p, array $preview = [])
    {
        return view('report::health.import', [
            'p' => $p,
            'years' => $this->years(),
            'preview' => $preview,
            'mappingCount' => ReportImportMapping::where('active', true)->count(),
        ]);
    }

    private function cacheKey(Request $request, string $token): string
    {
        return 'report-health-import:' . $request->user()->id . ':' . $token;
    }
}
