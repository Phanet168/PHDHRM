@extends('report::health.layout')
@php
    use Modules\Report\Services\PeriodCode;
    $kh = fn ($v) => PeriodCode::kh($v);
    $mode = old('mode', $preview['mode'] ?? 'mapping');
@endphp

@section('rpt_title', 'Import Excel')

@push('rpt_css')
    <style>
        .rpt-step { display: grid; place-items: center; width: 36px; height: 36px; border-radius: 999px; background: var(--rpt-e600); color: #fff; font-weight: 700; flex-shrink: 0; }
        .rpt-step-sm { width: 28px; height: 28px; background: var(--rpt-s100); color: var(--rpt-s900); font-size: 12px; font-weight: 600; }
        .rpt-drop { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 220px; border: 2px dashed var(--rpt-s200); border-radius: 16px; background: var(--rpt-s50); padding: 28px; text-align: center; cursor: pointer; transition: border-color .15s, background .15s; height: 100%; }
        .rpt-drop:hover, .rpt-drop.over { border-color: var(--rpt-e400); background: rgba(236,253,245,.4); }
        .rpt-drop input { display: none; }
        .rpt-drop-icon { display: grid; place-items: center; width: 56px; height: 56px; border-radius: 16px; background: var(--rpt-e100); color: var(--rpt-e700); margin-bottom: 16px; }
        .rpt-drop-title { font-weight: 600; word-break: break-all; }
        .rpt-drop-tag { display: inline-block; margin-bottom: 10px; font-size: 11px; font-weight: 700; letter-spacing: .06em; color: var(--rpt-e700); }
        .rpt-drop.has-file { border-style: solid; border-color: var(--rpt-e500); background: var(--rpt-e50); }
        .rpt-sheet td { padding: 4px 8px; border: 1px solid var(--rpt-s100); white-space: nowrap; max-width: 260px; overflow: hidden; text-overflow: ellipsis; cursor: pointer; }
        .rpt-sheet td.num { text-align: right; font-weight: 600; color: var(--rpt-e700); }
        .rpt-sheet td:hover { background: var(--rpt-e50) !important; outline: 1px solid var(--rpt-e500); }
        .rpt-sheet td.picked { background: var(--rpt-e100) !important; outline: 2px solid var(--rpt-e600); }
        .rpt-sheet .rpt-sheet-row, .rpt-sheet thead th:first-child { position: sticky; left: 0; background: var(--rpt-s50); color: var(--rpt-s400); font-weight: 500; padding: 4px 8px; text-align: right; }
    </style>
@endpush

@section('rpt')
    <section class="rpt-title">
        <div>
            <div class="rpt-eyebrow">នាំចូលទិន្នន័យ</div>
            <h1>Import Excel</h1>
            <p>បញ្ចូលឯកសារ Excel ដើម្បីផ្ទៀងផ្ទាត់ និងនាំចូលទិន្នន័យជាក្រុម</p>
        </div>
        <a class="rpt-btn rpt-btn-outline" style="font-weight:500" href="{{ route('report.health.import.template') }}">ទាញយក Template</a>
    </section>

    <div class="row g-4">
        <div class="col-xl-8">
            <form class="rpt-card rpt-card-pad" method="POST" action="{{ route('report.health.import.preview') }}" enctype="multipart/form-data" id="frmImport">
                @csrf
                <div class="d-flex align-items-center gap-3 mb-4">
                    <span class="rpt-step">១</span>
                    <div>
                        <h2>ជ្រើសរើសឯកសារ</h2>
                        <div class="rpt-sub">គាំទ្រ .xlsx / .xls ទំហំអតិបរមា ១០MB</div>
                    </div>
                    <div class="rpt-seg ms-auto" id="modeSeg">
                        <button type="button" data-mode="mapping" class="{{ $mode === 'mapping' ? 'active' : '' }}">Mapping HC + HOSP</button>
                        <button type="button" data-mode="summary" class="{{ $mode === 'summary' ? 'active' : '' }}">Summary Excel</button>
                    </div>
                </div>
                <input type="hidden" name="mode" id="inpMode" value="{{ $mode }}">

                <div class="row g-3" data-mode-panel="mapping" @if ($mode !== 'mapping') hidden @endif>
                    @foreach (['file_hc' => ['HC', 'មណ្ឌលសុខភាព (HC)'], 'file_hosp' => ['HOSP', 'មន្ទីរពេទ្យ (HOSP)']] as $name => [$tag, $label])
                        <div class="col-md-6">
                            <label class="rpt-drop">
                                <input type="file" name="{{ $name }}" accept=".xlsx,.xls">
                                <span class="rpt-drop-icon">@include('report::health._icon', ['name' => 'arrow'])</span>
                                <span class="rpt-drop-tag">{{ $tag }}</span>
                                <span class="rpt-drop-title">អូសឯកសារ {{ $label }} មកទីនេះ ឬចុចដើម្បីជ្រើសរើស</span>
                                <span class="rpt-sub mt-2">Excel workbook (.xlsx, .xls)</span>
                            </label>
                        </div>
                    @endforeach
                    <div class="col-12 rpt-sub">
                        តម្លៃត្រូវបានអានតាម Mapping ({{ $kh($mappingCount) }} សូចនាករសកម្ម)។
                        @can('read_health_report_settings')
                            <a class="rpt-link" href="{{ route('report.health.settings', 'mappings') }}">កែ Mapping</a>
                        @endcan
                    </div>
                </div>

                <div data-mode-panel="summary" @if ($mode !== 'summary') hidden @endif>
                    <label class="rpt-drop">
                        <input type="file" name="file_summary" accept=".xlsx,.xls">
                        <span class="rpt-drop-icon">@include('report::health._icon', ['name' => 'arrow'])</span>
                        <span class="rpt-drop-title">អូសឯកសារមកទីនេះ ឬចុចដើម្បីជ្រើសរើស</span>
                        <span class="rpt-sub mt-2">ជួរឈរ: indicator_id, name, hc, hosp, total, target</span>
                    </label>
                </div>

                <div class="row g-3 mt-2">
                    <div class="col-sm-4">
                        <label class="rpt-label">ឆ្នាំ</label>
                        <select class="rpt-input" name="year">
                            @foreach ($years as $y)
                                <option value="{{ $y }}" @selected($y == $p['year'])>{{ $kh($y) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-4">
                        <label class="rpt-label">រយៈពេល</label>
                        <select class="rpt-input" name="tag" id="selTag">
                            @foreach (PeriodCode::TYPES as $type => $typeLabel)
                                <optgroup label="{{ $typeLabel }}">
                                    @foreach (PeriodCode::tagsFor($type) as $tag)
                                        <option value="{{ $tag }}" data-type="{{ $type }}" @selected($tag === $p['tag'])>{{ PeriodCode::tagLabel($tag) }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <input type="hidden" name="type" id="inpType" value="{{ $p['type'] }}">
                    </div>
                    <div class="col-sm-4 d-flex align-items-end">
                        <button class="rpt-btn rpt-btn-primary w-100" type="submit">ផ្ទៀងផ្ទាត់ទិន្នន័យ</button>
                    </div>
                </div>
            </form>
        </div>

        <aside class="col-xl-4 d-flex flex-column gap-4">
            <div class="rpt-card" style="padding:20px">
                <h3>ដំណើរការនាំចូល</h3>
                @foreach (['ទាញយក Template ឬកំណត់ Mapping', 'បំពេញទិន្នន័យតាមជួរឈរ', 'Upload និងផ្ទៀងផ្ទាត់', 'ពិនិត្យ រួចបញ្ជាក់នាំចូល'] as $i => $step)
                    <div class="d-flex gap-3 mt-3"><span class="rpt-step rpt-step-sm">{{ $kh($i + 1) }}</span><span style="padding-top:4px;color:var(--rpt-s600)">{{ $step }}</span></div>
                @endforeach
            </div>
            <div class="rpt-note">
                <span style="color:var(--rpt-a600)">@include('report::health._icon', ['name' => 'alert'])</span>
                <div>
                    <h3>ចំណាំសំខាន់</h3>
                    <p>កុំកែឈ្មោះជួរឈរ ឬ Indicator ID ក្នុង Template។ តម្លៃដែលមានរួចសម្រាប់រយៈពេលដដែល នឹងត្រូវជំនួសដោយតម្លៃថ្មីពេលបញ្ជាក់នាំចូល (គោលដៅមិនប៉ះពាល់ទេ លុះត្រាតែមានជួរឈរ target)។</p>
                </div>
            </div>
        </aside>
    </div>

    @if (!empty($preview))
        @php($ok = collect($preview['rows'])->filter(fn ($r) => $r['indicator_id'] && ($r['total'] !== null || $r['hc'] !== null || $r['hosp'] !== null || $r['target'] !== null))->count())
        <div class="rpt-card mt-4" id="previewCard">
            <div class="rpt-toolbar">
                <div class="d-flex align-items-center gap-3 me-auto">
                    <span class="rpt-step">២</span>
                    <div>
                        <h2>ពិនិត្យ រួចបញ្ជាក់នាំចូល</h2>
                        <div class="rpt-sub">{{ PeriodCode::label($p['code']) }} • {{ $preview['mode'] === 'mapping' ? 'Mapping HC + HOSP' : 'Summary Excel' }} • {{ $kh(count($preview['rows'])) }} ជួរ</div>
                    </div>
                </div>
                <a class="rpt-btn rpt-btn-outline" href="{{ route('report.health.import.preview-export', ['token' => $preview['token']]) }}">Export Excel</a>
                <button type="button" class="rpt-btn rpt-btn-outline" onclick="window.print()">PDF</button>
                <form method="POST" action="{{ route('report.health.import.commit') }}" onsubmit="return confirm('បញ្ជាក់នាំចូល {{ $kh($ok) }} សូចនាករ សម្រាប់ {{ PeriodCode::label($p['code']) }}?')">
                    @csrf
                    <input type="hidden" name="token" value="{{ $preview['token'] }}">
                    <button class="rpt-btn rpt-btn-dark" type="submit" @disabled(!$ok)>បញ្ជាក់នាំចូល ({{ $kh($ok) }})</button>
                </form>
            </div>
            @if ($preview['warnings'])
                <div class="rpt-note" style="border-radius:0;border-width:0 0 1px">
                    <div>
                        <h3>ការព្រមាន {{ $kh(count($preview['warnings'])) }}</h3>
                        <p>{!! collect($preview['warnings'])->take(12)->map(fn ($w) => e($w))->implode('<br>') !!}{{ count($preview['warnings']) > 12 ? ' …' : '' }}</p>
                    </div>
                </div>
            @endif
            <div class="rpt-table-wrap" style="max-height:60vh;overflow-y:auto">
                <table class="rpt-table">
                    <thead><tr><th>ID</th><th>សូចនាករ</th><th>ផ្នែក</th><th class="text-end">HC</th><th class="text-end">HOSP</th><th class="text-end">សរុបថ្មី</th><th class="text-end">តម្លៃបច្ចុប្បន្ន</th><th>ស្ថានភាព</th></tr></thead>
                    <tbody>
                        @forelse ($preview['rows'] as $r)
                            <tr>
                                <td class="rpt-id" title="{{ $r['code'] }}">{{ \Modules\Report\Entities\ReportIndicator::shortCode($r['code']) }}</td>
                                <td class="rpt-name">{{ $r['name'] }}</td>
                                <td style="color:var(--rpt-s500)">{{ $r['unit'] }}</td>
                                <td class="text-end">{{ PeriodCode::khNumber($r['hc']) }}</td>
                                <td class="text-end">{{ PeriodCode::khNumber($r['hosp']) }}</td>
                                @php($current = $r['current'] ?? null)
                                @php($changes = $current !== null && $r['total'] !== null && abs($current - $r['total']) > 0.00001)
                                <td class="text-end" style="font-weight:600">{{ PeriodCode::khNumber($r['total']) }}</td>
                                <td class="text-end" style="{{ $changes ? 'color:var(--rpt-a700);font-weight:600' : 'color:var(--rpt-s400)' }}">{{ PeriodCode::khNumber($current) }}</td>
                                <td>
                                    @if (!$r['indicator_id'])
                                        <span class="rpt-badge rpt-badge-danger">ID មិនមាន</span>
                                    @elseif ($r['notes'])
                                        <span class="rpt-badge rpt-badge-warn" title="{{ implode('; ', $r['notes']) }}">ពិនិត្យ</span>
                                    @elseif ($r['total'] === null && $r['hc'] === null && $r['hosp'] === null)
                                        <span class="rpt-badge rpt-badge-muted" title="ជួរនេះនឹងត្រូវរំលង តម្លៃចាស់មិនប៉ះពាល់">គ្មានតម្លៃ • រំលង</span>
                                    @elseif ($changes)
                                        <span class="rpt-badge rpt-badge-warn">នឹងជំនួស</span>
                                    @elseif ($current === null)
                                        <span class="rpt-badge rpt-badge-ok">ថ្មី</span>
                                    @else
                                        <span class="rpt-badge rpt-badge-muted">ដូចចាស់</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="rpt-empty">
                                @if ($preview['mode'] === 'mapping' && !$mappingCount)
                                    <div style="font-weight:600;color:var(--rpt-a700)">មិនទាន់មាន Import Mapping សកម្ម — ទិន្នន័យក្នុងឯកសារមិនអាចភ្ជាប់ទៅសូចនាករបានទេ</div>
                                    <div class="mt-1">ឯកសារត្រូវបានអានរួចហើយ (មើលខាងក្រោម)។ សូមកំណត់ Sheet/Cell សម្រាប់សូចនាករនីមួយៗនៅ
                                        <a class="rpt-link" href="{{ route('report.health.settings', 'mappings') }}">ការកំណត់ › Import Mapping</a>
                                        ឬប្រើរបៀប <b>Summary Excel</b> ជាមួយ Template។</div>
                                @else
                                    គ្មានជួរ — សូមពិនិត្យ Mapping ឬ header «indicator_id»
                                @endif
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if (!empty($preview['viewer']))
            @php($v = $preview['viewer'])
            <div class="rpt-card mt-4" id="fileViewer">
                <form method="GET" class="rpt-toolbar">
                    <input type="hidden" name="token" value="{{ $preview['token'] }}">
                    <div class="me-auto">
                        <h2>ទិន្នន័យក្នុងឯកសារ</h2>
                        <div class="rpt-sub">ចុចលើ cell ដើម្បីចម្លងទីតាំង (ឧ. <code>{{ $v['src'] === 'HOSP' ? 'HOSP' : 'HC' }}("{{ $v['sheet'] }}!D6")</code>) ទៅប្រើក្នុង Mapping</div>
                    </div>
                    @if (count($v['sources']) > 1)
                        <div class="rpt-seg">
                            @foreach ($v['sources'] as $s)
                                <a href="{{ route('report.health.import', ['token' => $preview['token'], 'src' => $s]) }}#fileViewer" class="{{ $s === $v['src'] ? 'active' : '' }}">{{ $s }}</a>
                            @endforeach
                        </div>
                    @endif
                    <input type="hidden" name="src" value="{{ $v['src'] }}">
                    <select class="rpt-select" name="sheet" onchange="this.form.action = '#fileViewer'; this.form.submit()" aria-label="Sheet">
                        @foreach ($v['sheets'] as $s)
                            <option value="{{ $s }}" @selected($s === $v['sheet'])>{{ $s }}</option>
                        @endforeach
                    </select>
                    <span class="rpt-sub" id="cellRef" style="min-width:160px"></span>
                </form>
                <div class="rpt-table-wrap" style="max-height:70vh;overflow:auto">
                    <table class="rpt-table rpt-sheet" style="min-width:0;font-size:12px" data-src="{{ $v['src'] === 'HOSP' ? 'HOSP' : 'HC' }}" data-sheet="{{ $v['sheet'] }}">
                        <thead><tr><th style="left:0;z-index:2"></th>@foreach ($v['cols'] as $c)<th class="text-center">{{ $c }}</th>@endforeach</tr></thead>
                        <tbody>
                            @forelse ($v['rows'] as $rowNum => $cells)
                                <tr>
                                    <th class="rpt-sheet-row">{{ $rowNum }}</th>
                                    @foreach ($v['cols'] as $c)
                                        @php($val = $cells[$c] ?? null)
                                        <td data-ref="{{ $c }}{{ $rowNum }}" class="{{ $val !== null && is_numeric($val) ? 'num' : '' }}">{{ $val !== null ? \Illuminate\Support\Str::limit($val, 60) : '' }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr><td colspan="{{ count($v['cols']) + 1 }}" class="rpt-empty">Sheet នេះទទេ</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($v['more'])
                    <div class="rpt-foot">បង្ហាញតែ ៣០០ ជួរ × ៦០ ជួរឈរដំបូង</div>
                @endif
            </div>
        @endif
    @endif
@endsection

@push('rpt_js')
    <script>
        (function () {
            const form = document.getElementById('frmImport');
            document.getElementById('modeSeg').addEventListener('click', (e) => {
                const btn = e.target.closest('button[data-mode]');
                if (!btn) return;
                document.getElementById('inpMode').value = btn.dataset.mode;
                document.querySelectorAll('#modeSeg button').forEach((b) => b.classList.toggle('active', b === btn));
                form.querySelectorAll('[data-mode-panel]').forEach((p) => { p.hidden = p.dataset.modePanel !== btn.dataset.mode; });
            });
            document.getElementById('selTag').addEventListener('change', (e) => {
                document.getElementById('inpType').value = e.target.selectedOptions[0].dataset.type;
            });
            form.querySelectorAll('.rpt-drop').forEach((drop) => {
                const input = drop.querySelector('input[type=file]');
                const title = drop.querySelector('.rpt-drop-title');
                const sub = drop.querySelector('.rpt-sub');
                const show = () => {
                    const f = input.files[0];
                    drop.classList.toggle('has-file', !!f);
                    if (f) { title.textContent = f.name; sub.textContent = 'ឯកសាររួចរាល់សម្រាប់ផ្ទៀងផ្ទាត់'; }
                };
                input.addEventListener('change', show);
                ['dragenter', 'dragover'].forEach((ev) => drop.addEventListener(ev, (e) => { e.preventDefault(); drop.classList.add('over'); }));
                ['dragleave', 'drop'].forEach((ev) => drop.addEventListener(ev, (e) => { e.preventDefault(); drop.classList.remove('over'); }));
                drop.addEventListener('drop', (e) => { if (e.dataTransfer.files.length) { input.files = e.dataTransfer.files; show(); } });
            });
            // Check the chosen files match the active mode before uploading.
            form.addEventListener('submit', (e) => {
                const has = (n) => form.querySelector(`input[name="${n}"]`).files.length > 0;
                const mode = document.getElementById('inpMode').value;
                let msg = '';
                if (mode === 'mapping' && !has('file_hc') && !has('file_hosp')) {
                    msg = has('file_summary') ? '' : 'សូមជ្រើសឯកសារ HC ឬ HOSP យ៉ាងហោចណាស់មួយ';
                } else if (mode === 'summary' && !has('file_summary') && !has('file_hc') && !has('file_hosp')) {
                    msg = 'សូមជ្រើសឯកសារ Summary Excel';
                }
                if (msg) {
                    e.preventDefault();
                    alert(msg);
                    return;
                }
                form.querySelector('button[type=submit]').disabled = true;
                form.querySelector('button[type=submit]').textContent = 'កំពុងអាន Excel…';
            });
            // Sheet viewer: click a cell to copy its mapping reference.
            const sheet = document.querySelector('.rpt-sheet');
            sheet?.addEventListener('click', (e) => {
                const td = e.target.closest('td[data-ref]');
                if (!td) return;
                const ref = `${sheet.dataset.src}("${sheet.dataset.sheet}!${td.dataset.ref}")`;
                sheet.querySelectorAll('td.picked').forEach((c) => c.classList.remove('picked'));
                td.classList.add('picked');
                navigator.clipboard?.writeText(ref).catch(() => {});
                document.getElementById('cellRef').textContent = `${td.dataset.ref} = ${td.textContent || '—'} • បានចម្លង ${ref}`;
            });
            if (!location.hash) document.getElementById('previewCard')?.scrollIntoView({ behavior: 'smooth' });
        })();
    </script>
@endpush
