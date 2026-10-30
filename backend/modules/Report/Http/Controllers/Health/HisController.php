<?php

namespace Modules\Report\Http\Controllers\Health;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Report\Entities\ReportImportBatch;
use Modules\Report\Entities\ReportImportTemplate;
use Modules\Report\Entities\ReportFacility;
use Modules\Report\Services\His\HisImportService;
use Modules\Report\Services\PeriodCode;
use Throwable;

/** ទិន្នន័យ HIS — import whole HC1 / HO2 workbooks into the disaggregated fact table. */
class HisController extends HealthController
{
    private const UPLOAD_DIR = 'report-his';

    public function index(Request $request)
    {
        $preview = null;
        if ($request->filled('token') && ($stash = Cache::get($this->key($request, (string) $request->input('token'))))) {
            $preview = $stash + ['token' => $request->input('token')];
        }
        $p = $preview ? PeriodCode::parse($preview['period']) : $this->periodFromRequest($request);

        return view('report::health.his', [
            'templates' => ReportImportTemplate::withCount(['blocks' => fn ($q) => $q->where('active', true)])->orderBy('code')->get(),
            'facilities' => ReportFacility::healthFacilities()->get(),
            'batches' => ReportImportBatch::with(['template', 'facility', 'period', 'creator:id,full_name'])->latest()->paginate(15)->withQueryString(),
            'p' => $p,
            'years' => $this->years(),
            'preview' => $preview,
        ]);
    }

    public function preview(Request $request, HisImportService $import)
    {
        $data = $request->validate([
            'template_id' => ['required', 'integer', 'exists:report_import_templates,id'],
            'facility_id' => ['required', 'integer', 'exists:departments,id'],
            'file' => $this->excelRule(),
        ], ['file.required' => 'សូមជ្រើសឯកសារ HIS (.xls / .xlsx)'], ['file' => 'ឯកសារ HIS']);
        $template = ReportImportTemplate::findOrFail($data['template_id']);
        $p = $this->periodFromRequest($request);

        $token = (string) Str::uuid();
        $file = $request->file('file');
        $path = $file->storeAs(self::UPLOAD_DIR, $token . '.' . strtolower($file->getClientOriginalExtension()));
        try {
            $result = $import->preview(Storage::path($path), $template);
        } catch (Throwable $e) {
            Storage::delete($path);
            report($e);

            return redirect()->route('report.health.his')->withErrors(['file' => 'អាន Excel បរាជ័យ: ' . $e->getMessage()]);
        }

        Cache::put($this->key($request, $token), $result + [
            'template_id' => $template->id,
            'template' => $template->code,
            'facility_id' => (int) $data['facility_id'],
            'facility' => ReportFacility::find($data['facility_id'])?->department_name,
            'period' => $p['code'],
            'path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'duplicate' => ReportImportBatch::where('file_hash', hash_file('sha256', Storage::path($path)))->with(['facility', 'period'])->first()?->only(['id', 'file_name', 'created_at']),
        ], now()->addHour());

        return redirect()->route('report.health.his', ['token' => $token]);
    }

    public function commit(Request $request, HisImportService $import)
    {
        $request->validate(['token' => ['required', 'uuid']]);
        $stash = Cache::get($this->key($request, $request->input('token')));
        if (!$stash || !Storage::exists($stash['path'])) {
            return redirect()->route('report.health.his')->withErrors(['file' => 'Preview ផុតកំណត់ សូម upload ម្តងទៀត']);
        }
        $period = $this->period($stash['period']);
        if ($period->locked) {
            return redirect()->route('report.health.his')->withErrors(['file' => 'រយៈពេលនេះត្រូវបានចាក់សោ']);
        }

        $batch = $import->commit(Storage::path($stash['path']), $stash['file_name'], ReportImportTemplate::findOrFail($stash['template_id']),
            $stash['facility_id'], $period, $request->user());
        Storage::delete($stash['path']);
        Cache::forget($this->key($request, $request->input('token')));

        return redirect()->route('report.health.his')->with('success', sprintf(
            'បាននាំចូល %s តម្លៃ ពី %s សម្រាប់ %s • %s',
            PeriodCode::kh(number_format($batch->facts_count)), $stash['template'], $stash['facility'], $period->label
        ));
    }

    public function destroy(ReportImportBatch $batch)
    {
        $label = "{$batch->file_name} ({$batch->period?->label})";
        $batch->delete(); // facts cascade

        return back()->with('success', 'បានលុបការនាំចូល ' . $label);
    }

    private function key(Request $request, string $token): string
    {
        return 'report-his:' . $request->user()->id . ':' . $token;
    }
}
