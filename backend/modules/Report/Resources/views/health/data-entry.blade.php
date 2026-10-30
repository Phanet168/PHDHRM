@extends('report::health.layout')
@php
    use Modules\Report\Services\PeriodCode;
    $kh = fn ($v) => PeriodCode::kh($v);
    $num = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.');
    $filled = $rows->whereNotNull('value')->count();
    $partial = $rows->where('status', 'partial')->count();
    // Latest issue per indicator, preloaded for the modal.
    $issueData = $rows->filter(fn ($r) => $r['actions']->isNotEmpty())->mapWithKeys(function ($r) {
        $a = $r['actions']->first();

        return [$r['id'] => $a->only(['id', 'issue_text', 'action_text', 'action_owner', 'action_status', 'resolution_text']) + [
            'due' => $a->action_due?->format('Y-m-d'),
            'res_date' => $a->resolution_date?->format('Y-m-d'),
        ]];
    });
    $unit ??= null; // set when entering one unit (the usual way); null = the all-chapters view
    $exportQuery = ['period' => $p['code']] + ($unit ? ['unit' => $unit['id']] : []);
    $unitUrl = fn ($u) => route('report.health.entry', ['unit' => $u['id'], 'period' => $p['code']]);
    // units whose indicators are "<item>(<variant>)" are entered as one grid (item × variant) unless the list is asked for
    $layout = request('layout') === 'list' ? 'list' : 'grid';
    $matrices = $rows->groupBy('unit_id')->map(fn ($unitRows) => \Modules\Report\Services\IndicatorMatrix::build($unitRows->values()))->filter();
@endphp

@section('rpt_title', 'បញ្ចូលទិន្នន័យ')

@push('rpt_css')
    <style>
        .rpt-matrix-wrap { border: 1px solid var(--rpt-s200); border-radius: 12px; }
        .rpt-matrix { width: 100%; min-width: 560px; border-collapse: separate; border-spacing: 0; }
        .rpt-matrix th, .rpt-matrix td { padding: 6px 8px; border-bottom: 1px solid var(--rpt-s100); text-align: center; vertical-align: top; }
        /* the grid's own column names stay visible under the page's table header while scrolling a long grid */
        .rpt-matrix thead th { background: var(--rpt-s100); color: var(--rpt-s700); font-size: 12px; font-weight: 600; white-space: nowrap; position: sticky; top: var(--rpt-head, 44px); z-index: 2; }
        .rpt-mcell [data-issue] { visibility: hidden; }
        .rpt-mcell:hover [data-issue], .rpt-mcell:focus-within [data-issue], .rpt-mcell [data-issue].has-issue { visibility: visible; }
        .rpt-matrix .rpt-mlabel { text-align: left; font-weight: 500; min-width: 220px; max-width: 340px; position: sticky; left: 0; z-index: 1; background: #fff; }
        .rpt-matrix thead .rpt-mlabel { background: var(--rpt-s100); z-index: 3; }
        #tblEntry > thead th { z-index: 4; }
        .rpt-matrix tfoot .rpt-mlabel { background: var(--rpt-s50); }
        .rpt-matrix tbody tr:hover > * { background: var(--rpt-s50); }
        .rpt-matrix tfoot td, .rpt-matrix tfoot th { background: var(--rpt-s50); font-weight: 600; border-bottom: 0; }
        .rpt-matrix tfoot td { text-align: right; padding-right: 16px; }
        .rpt-mcell .rpt-cell-input { width: 100%; min-width: 76px; padding: 6px 8px; text-align: right; }
        .rpt-mcell .rpt-mvalue { display: block; text-align: right; padding: 6px 8px; }
        .rpt-mcell:focus-within { background: var(--rpt-e50, #ecfdf5); }
        .rpt-mfoot { display: flex; justify-content: space-between; align-items: center; gap: 4px; margin-top: 3px; font-size: 11px; min-height: 16px; }
        .rpt-mfoot [data-status] .rpt-badge { font-size: 0; width: 8px; height: 8px; padding: 0; border-radius: 50%; display: inline-block; vertical-align: middle; }
        .rpt-mnone { color: var(--rpt-s300); background: var(--rpt-s50); vertical-align: middle !important; }
        @media (max-width: 767.98px) {
            .rpt-matrix-wrap { overflow-x: auto; }
            .rpt-matrix thead th { position: static; }
            .rpt-cards .rpt-matrix { display: table; min-width: 520px; }
            .rpt-cards .rpt-matrix thead { display: table-header-group; }
            .rpt-cards .rpt-matrix tbody { display: table-row-group; }
            .rpt-cards .rpt-matrix tfoot { display: table-footer-group; }
            .rpt-cards .rpt-matrix tr { display: table-row; padding: 0; border: 0; }
            .rpt-cards .rpt-matrix td, .rpt-cards .rpt-matrix th { display: table-cell; width: auto !important; padding: 6px 8px !important; border-bottom: 1px solid var(--rpt-s100) !important; }
            .rpt-cards .rpt-matrix .rpt-mlabel { min-width: 150px; }
            .rpt-cards tr.rpt-matrix-row > td { padding: 8px !important; }
        }
    </style>
@endpush

@section('rpt')
    <section class="rpt-title">
        @if ($unit)
            <div>
                <div class="rpt-eyebrow"><a href="{{ route('report.health.entry', ['period' => $p['code']]) }}" style="color:inherit">← ម៉ឺនុយផ្នែក</a> • {{ $unit['department'] }}</div>
                <h1>{{ $unit['name'] }}</h1>
                <p>{{ $periodLabel }} • {{ $kh($unit['done']) }}/{{ $kh($unit['total']) }} សូចនាករមានតម្លៃ ({{ $kh($unit['percent']) }}%)
                    @if ($unit['of'] > 1) • ផ្នែក {{ $kh($unit['position']) }}/{{ $kh($unit['of']) }}@endif</p>
            </div>
        @else
            <div>
                <div class="rpt-eyebrow"><a href="{{ route('report.health.entry', ['period' => $p['code']]) }}" style="color:inherit">← ម៉ឺនុយផ្នែក</a> • គ្រប់គ្រងទិន្នន័យ</div>
                <h1>បញ្ចូលទិន្នន័យរបាយការណ៍</h1>
                <p>ទិដ្ឋភាពរួមតាមជំពូក • {{ $periodLabel }}</p>
            </div>
        @endif
        <div class="rpt-actions">
            @if ($unit && $unit['prev'])<a class="rpt-btn rpt-btn-outline" href="{{ $unitUrl($unit['prev']) }}" title="{{ $unit['prev']['name'] }}">← ផ្នែកមុន</a>@endif
            @if ($unit && $unit['next'])<a class="rpt-btn rpt-btn-outline" href="{{ $unitUrl($unit['next']) }}" title="{{ $unit['next']['name'] }}">ផ្នែកបន្ទាប់ →</a>@endif
            <a class="rpt-btn rpt-btn-outline" id="btnExportXlsx" href="{{ route('report.health.entry.export', $exportQuery) }}">Export Excel</a>
            <a class="rpt-btn rpt-btn-outline" id="btnExportPdf" target="_blank" href="{{ route('report.health.entry.print', $exportQuery) }}">PDF</a>
        </div>
    </section>

    @if ($locked)
        <div class="rpt-alert rpt-alert-err">{{ $periodLabel }} ត្រូវបានចាក់សោ — មិនអាចកែប្រែទិន្នន័យបានទេ។</div>
    @endif

    <div class="rpt-card">
        <form method="GET" class="rpt-toolbar" id="periodForm">
            <div class="rpt-search">
                @include('report::health._icon', ['name' => 'search'])
                <input class="rpt-input" id="inpSearch" type="search" placeholder="ស្វែងរកសូចនាករ..." autocomplete="off">
            </div>
            <select class="rpt-select" name="year" aria-label="ឆ្នាំ">
                @foreach ($years as $y)
                    <option value="{{ $y }}" @selected($y == $p['year'])>ឆ្នាំ {{ $kh($y) }}</option>
                @endforeach
            </select>
            <select class="rpt-select" name="tag" aria-label="រយៈពេល">
                @foreach (PeriodCode::TYPES as $type => $typeLabel)
                    <optgroup label="{{ $typeLabel }}">
                        @foreach (PeriodCode::tagsFor($type) as $tag)
                            <option value="{{ $tag }}" @selected($tag === $p['tag'])>{{ $type === 'month' ? 'ខែ' : '' }}{{ PeriodCode::tagLabel($tag) }} {{ isset($tagCounts[$tag]) ? '(' . $kh($tagCounts[$tag]) . ')' : '—' }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            <input type="hidden" name="type" id="inpType" value="{{ $p['type'] }}">
            @if ($layout === 'list')<input type="hidden" name="layout" value="list">@endif
            @if ($unit)
                {{-- one unit: the chapter / unit pickers are fixed (kept for the page script and exports) --}}
                <input type="hidden" name="unit" value="{{ $unit['id'] }}">
                <select id="selDept" hidden><option value="all" selected></option></select>
                <select id="selUnit" hidden><option value="{{ $unit['id'] }}" selected></option></select>
            @else
                <select class="rpt-select" id="selDept" name="department" aria-label="ជំពូក" onchange="this.form.requestSubmit()">
                    <option value="all" @selected($department === 'all')>ជំពូកទាំងអស់ ({{ $kh($totalAll) }})</option>
                    @foreach ($departments as $d)
                        @if (isset($deptCounts[$d->id]))
                            <option value="{{ $d->id }}" @selected((string) $department === (string) $d->id)>{{ $d->name }} ({{ $kh($deptCounts[$d->id]) }})</option>
                        @endif
                    @endforeach
                </select>
                <select class="rpt-select" id="selUnit" aria-label="ផ្នែក">
                    <option value="">ផ្នែកទាំងអស់</option>
                    @foreach ($departments as $d)
                        @foreach ($d->units as $u)
                            <option value="{{ $u->id }}" data-dept="{{ $d->id }}">{{ $u->name }}</option>
                        @endforeach
                    @endforeach
                </select>
            @endif
            <button class="rpt-btn rpt-btn-primary" type="submit">អនុវត្ត</button>
        </form>

        <div class="rpt-statbar">
            <div class="d-flex flex-wrap gap-3 align-items-center">
                <span><b id="cntTotal">{{ $kh($rows->count()) }}</b> សូចនាករ</span>
                <span style="color:var(--rpt-e700)">● <span id="cntFilled">{{ $kh($filled) }}</span> បានបញ្ចូល</span>
                <span style="color:var(--rpt-a700)">● {{ $kh($partial) }} មិនទាន់គ្រប់</span>
                @if ($canSave)
                    <button type="button" class="rpt-link" id="btnCopyPrev">ចម្លងពីរយៈពេលមុន</button>
                @endif
                <span id="statusLine" style="color:var(--rpt-s500)"></span>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-3">
                @if ($matrices->isNotEmpty())
                    <div class="rpt-seg" title="ផ្នែកដែលសូចនាករមានទម្រង់ «ប្រភេទ (បើកថ្មី / បន្តសុពលភាព …)» ឬ «(ប្រុស / ស្រី)» បង្ហាញជាតារាង">
                        <a href="{{ request()->fullUrlWithQuery(['layout' => null]) }}" class="{{ $layout === 'grid' ? 'active' : '' }}">តារាង</a>
                        <a href="{{ request()->fullUrlWithQuery(['layout' => 'list']) }}" class="{{ $layout === 'list' ? 'active' : '' }}">បញ្ជី</a>
                    </div>
                @endif
                <label class="d-flex align-items-center gap-2 mb-0"><input type="checkbox" id="onlyEmpty"> បង្ហាញតែមិនទាន់បញ្ចូល</label>
            </div>
        </div>

        <div class="rpt-table-wrap" style="max-height: 70vh; overflow-y: auto">
            @php($gridOnly = $unit && $layout === 'grid' && ($g = $matrices->get($unit['id'])) && $g['extra']->isEmpty())
            <table class="rpt-table rpt-cards" id="tblEntry">
                {{-- a unit shown only as a grid has its own column names: the list header would be noise --}}
                <thead @if ($gridOnly) hidden @endif>
                    <tr>
                        <th style="width:80px">ID</th>
                        <th style="min-width:380px">សូចនាករ</th>
                        <th style="width:140px">តម្លៃ</th>
                        <th style="width:140px">ផែនការ</th>
                        <th style="width:130px">ស្ថានភាព</th>
                        <th style="width:110px">សកម្មភាព</th>
                    </tr>
                </thead>
                <tbody>
                    @php($dn = 0)
                    @forelse ($rows->groupBy('department_id') as $deptRows)
                        @php($dn++)
                        @unless ($unit)<tr class="rpt-group-dept rpt-toggle" data-dept-row="{{ $deptRows[0]['department_id'] }}" title="ចុចដើម្បីបិទ/បើក"><td colspan="6">{{ $kh($dn) }}. {{ $deptRows[0]['department'] }} <span class="rpt-sub" style="display:inline">({{ $kh($deptRows->count()) }})</span></td></tr>@endunless
                        @php($un = 0)
                        @foreach ($deptRows->groupBy('unit_id') as $unitRows)
                            @php($un++)
                            @unless ($unit)<tr class="rpt-group-unit rpt-toggle" data-unit-row="{{ $unitRows[0]['unit_id'] }}" data-dept="{{ $unitRows[0]['department_id'] }}" title="ចុចដើម្បីបិទ/បើក"><td colspan="6">{{ $kh($dn) }}.{{ $kh($un) }} {{ $unitRows[0]['unit'] }} <span class="rpt-sub" style="display:inline">({{ $kh($unitRows->count()) }})</span></td></tr>@endunless
                            @php($m = $layout === 'grid' ? $matrices->get($unitRows[0]['unit_id']) : null)
                            @if ($m)
                                @include('report::health._entry-matrix', ['m' => $m, 'first' => $unitRows[0]])
                                @foreach ($m['extra'] as $r)
                                    @include('report::health._entry-row')
                                @endforeach
                            @else
                                @foreach ($unitRows as $r)
                                    @include('report::health._entry-row')
                                @endforeach
                            @endif
                        @endforeach
                    @empty
                        <tr><td colspan="6" class="rpt-empty">គ្មានសូចនាករ — សូមកំណត់សូចនាករនៅ «ការកំណត់» ឬស្នើសិទ្ធិផ្នែកពីអ្នកគ្រប់គ្រង។</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="rpt-foot">
            <span>បង្ហាញ <span id="cntShown">{{ $kh($rows->count()) }}</span> ក្នុងចំណោម {{ $kh($totalAll) }}
                @if ($department !== 'all' && $rows->count() < $totalAll)
                    • <a class="rpt-link" href="{{ request()->fullUrlWithQuery(['department' => 'all']) }}">បង្ហាញជំពូកទាំងអស់</a>
                @endif
            </span>
            @if ($canSave)
                <button type="button" class="rpt-btn rpt-btn-dark" id="btnSaveAll">រក្សាទុកទាំងអស់ <span id="dirtyLabel"></span></button>
            @endif
        </div>
    </div>

    @if ($canSave)
        <div class="rpt-dock" id="saveDock">
            <span style="color:var(--rpt-s500);font-size:12px">ត្រូវរក្សាទុក</span>
            <span class="rpt-badge rpt-badge-warn" id="dockCount">០</span>
            <button type="button" class="rpt-btn rpt-btn-primary rpt-btn-sm" id="dockSave">រក្សាទុក</button>
            <button type="button" class="rpt-btn rpt-btn-outline rpt-btn-sm" id="dockTop">ឡើងលើ</button>
        </div>

        <div class="modal fade phd-rpt-modal" id="mdlIssue" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <form class="modal-content phd-rpt" id="frmIssue">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title mb-1" style="font-weight:600">បញ្ហាប្រឈម និងសកម្មភាព</h5>
                            <div class="rpt-sub"><span id="issueIndicator"></span> • {{ $periodLabel }}</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="បិទ"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action_id">
                        <input type="hidden" name="indicator_id">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="rpt-label">បញ្ហាប្រឈម</label><textarea class="rpt-input" name="issue_text"></textarea></div>
                            <div class="col-md-6"><label class="rpt-label">សកម្មភាពដោះស្រាយ</label><textarea class="rpt-input" name="action_text"></textarea></div>
                            <div class="col-md-4"><label class="rpt-label">អ្នកទទួលខុសត្រូវ</label><input class="rpt-input" name="action_owner"></div>
                            <div class="col-md-4"><label class="rpt-label">កាលកំណត់</label><input class="rpt-input" type="date" name="action_due"></div>
                            <div class="col-md-4"><label class="rpt-label">ស្ថានភាព</label>
                                <select class="rpt-input" name="action_status">
                                    @foreach (\Modules\Report\Entities\ReportAction::STATUSES as $k => $label)
                                        <option value="{{ $k }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-8 resolution"><label class="rpt-label">ដំណោះស្រាយ</label><textarea class="rpt-input" name="resolution_text"></textarea></div>
                            <div class="col-md-4 resolution"><label class="rpt-label">ថ្ងៃដោះស្រាយ</label><input class="rpt-input" type="date" name="resolution_date"></div>
                        </div>
                        <div class="rpt-sub mt-3" id="issueStatus"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="rpt-btn rpt-btn-outline" id="btnNewIssue">បញ្ហាថ្មី</button>
                        <button type="submit" class="rpt-btn rpt-btn-primary" id="btnSaveIssue">រក្សាទុក</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('rpt_js')
    <script>
        (function () {
            const PERIOD = @json($p['code']);
            const tbody = document.querySelector('#tblEntry tbody');
            const rows = Array.from(tbody.querySelectorAll('tr.rpt-row'));
            const selDept = document.getElementById('selDept');
            const selUnit = document.getElementById('selUnit');
            const search = document.getElementById('inpSearch');
            const onlyEmpty = document.getElementById('onlyEmpty');
            const statusLine = document.getElementById('statusLine');
            const setStatus = (m) => { statusLine.textContent = m || ''; };
            const exportLinks = [document.getElementById('btnExportXlsx'), document.getElementById('btnExportPdf')];
            const baseHrefs = exportLinks.map((a) => a.href);

            // keep the "type" hidden field in sync with the chosen tag so the server resolves the period type
            document.querySelector('select[name="tag"]').addEventListener('change', (e) => {
                const t = e.target.value;
                document.getElementById('inpType').value = t[0] === 'M' ? 'month' : t[0] === 'Q' ? 'quarter' : t[0] === 'H' ? 'half' : t === 'N9' ? 'nine' : 'year';
            });

            // An indicator is a list row (tr[data-id]) or a grid cell (td[data-id]) inside a unit's grid row.
            const item = (id) => tbody.querySelector(`[data-id="${id}"]`);
            const isEmpty = (tr) => tr.dataset.id ? tr.dataset.empty === '1' : !!tr.querySelector('[data-id][data-empty="1"]');
            const filledCount = () => tbody.querySelectorAll('[data-id][data-empty="0"]').length;

            /* ---------- client-side filters (department, unit, search, only empty) ---------- */
            function applyFilters() {
                const dept = selDept.value === 'all' ? '' : selDept.value;
                const unit = selUnit.value, q = search.value.trim().toLowerCase(), empty = onlyEmpty.checked;
                let shown = 0;
                const visibleUnits = new Set(), visibleDepts = new Set();
                rows.forEach((tr) => {
                    const ok = (!dept || tr.dataset.dept === dept) && (!unit || tr.dataset.unit === unit)
                        && (!q || tr.dataset.search.includes(q)) && (!empty || isEmpty(tr));
                    tr.hidden = !ok;
                    if (ok) { shown += tr.dataset.id ? 1 : tr.querySelectorAll('[data-id]').length; visibleUnits.add(tr.dataset.unit); visibleDepts.add(tr.dataset.dept); }
                });
                tbody.querySelectorAll('[data-unit-row]').forEach((tr) => { tr.hidden = !visibleUnits.has(tr.dataset.unitRow); });
                tbody.querySelectorAll('[data-dept-row]').forEach((tr) => { tr.hidden = !visibleDepts.has(tr.dataset.deptRow); });
                document.getElementById('cntShown').textContent = RPT.kh(shown);
                const qs = new URLSearchParams({ department: dept || 'all', unit, q, only_empty: empty ? 1 : 0 });
                exportLinks.forEach((a, i) => { a.href = baseHrefs[i] + '&' + qs.toString(); });
            }
            applyFilters(); // so Export / PDF follow the department opened on the server
            // The department is chosen on the server; only offer that department's units.
            Array.from(selUnit.options).forEach((o) => { o.hidden = !!o.dataset.dept && selDept.value !== 'all' && o.dataset.dept !== selDept.value; });
            [selUnit, onlyEmpty].forEach((el) => el.addEventListener('change', applyFilters));

            // Collapse / expand a department or unit group by clicking its header row.
            tbody.addEventListener('click', (e) => {
                const head = e.target.closest('tr.rpt-toggle');
                if (!head) return;
                const collapsed = head.classList.toggle('collapsed');
                const match = head.dataset.unitRow
                    ? (tr) => tr.dataset.unit === head.dataset.unitRow
                    : (tr) => tr.dataset.dept === head.dataset.deptRow && !tr.dataset.deptRow;
                tbody.querySelectorAll('tr.rpt-row, tr[data-unit-row]').forEach((tr) => {
                    if (tr === head || !match(tr)) return;
                    tr.classList.toggle('rpt-folded', collapsed);
                    if (!collapsed) tr.classList.remove('collapsed');
                });
            });
            search.addEventListener('input', applyFilters);
            search.addEventListener('keydown', (e) => { if (e.key === 'Enter') e.preventDefault(); });

            /* ---------- grids: column totals, Enter moves down ---------- */
            const toNum = (s) => { const n = parseFloat(String(s ?? '').replace(/[០-៩]/g, (d) => '០១២៣៤៥៦៧៨៩'.indexOf(d))); return Number.isFinite(n) ? n : null; };
            function updateTotals(table) {
                table.querySelectorAll('tfoot [data-total]').forEach((td) => {
                    let sum = 0, any = false;
                    table.querySelectorAll(`tbody td[data-col="${td.dataset.total}"]`).forEach((cell) => {
                        const inp = cell.querySelector('input[data-field="value"]');
                        // what the report will show: the typed value, else the HIS / HRM / months value
                        const v = inp ? (inp.value !== '' ? toNum(inp.value) : toNum(cell.dataset.calc)) : toNum(cell.dataset.display);
                        if (v !== null) { sum += v; any = true; }
                    });
                    td.textContent = any ? RPT.kh(Math.round(sum * 100) / 100) : '—';
                });
            }
            tbody.querySelectorAll('table.rpt-matrix').forEach(updateTotals);
            const outerHead = document.querySelector('#tblEntry > thead');
            if (outerHead) document.getElementById('tblEntry').style.setProperty('--rpt-head', outerHead.offsetHeight + 'px');
            tbody.addEventListener('keydown', (e) => {
                const inp = e.target.closest('.rpt-mcell input.rpt-cell-input');
                if (!inp || e.key !== 'Enter') return;
                e.preventDefault();
                const cell = inp.closest('td');
                const table = cell.closest('table');
                const below = Array.from(table.querySelectorAll(`tbody td[data-col="${cell.dataset.col}"] input.rpt-cell-input`));
                const next = below[below.indexOf(inp) + (e.shiftKey ? -1 : 1)];
                if (next) { next.focus(); next.select(); }
            });

            const btnSaveAll = document.getElementById('btnSaveAll');
            if (!btnSaveAll) return;

            /* ---------- dirty tracking + save dock ---------- */
            const DIRTY = new Map(); // indicator id -> {value, target}
            const dock = document.getElementById('saveDock');
            function refreshDirty() {
                const n = DIRTY.size;
                document.getElementById('dirtyLabel').textContent = n ? `(${RPT.kh(n)})` : '';
                document.getElementById('dockCount').textContent = RPT.kh(n);
                dock.classList.toggle('show', n > 0);
            }
            function markDirty(tr) {
                const get = (f) => tr.querySelector(`[data-field="${f}"]`)?.value ?? '';
                DIRTY.set(tr.dataset.id, { indicator_id: Number(tr.dataset.id), value: get('value'), target: get('target') });
                tr.querySelectorAll('.rpt-cell-input').forEach((i) => i.classList.add('dirty'));
                refreshDirty();
            }
            tbody.addEventListener('input', (e) => {
                const inp = e.target.closest('.rpt-cell-input');
                if (!inp) return;
                let s = inp.value.replace(/[០-៩]/g, (d) => '០១២៣៤៥៦៧៨៩'.indexOf(d)).replace(/[^\d.\-]/g, '');
                const dot = s.indexOf('.');
                if (dot !== -1) s = s.slice(0, dot + 1) + s.slice(dot + 1).replace(/\./g, '');
                if (inp.value !== s) inp.value = s;
                markDirty(inp.closest('[data-id]'));
                const grid = inp.closest('table.rpt-matrix');
                if (grid) updateTotals(grid);
            });
            window.addEventListener('beforeunload', (e) => { if (DIRTY.size) { e.preventDefault(); e.returnValue = ''; } });

            // percent comes from the server and already accounts for "lower is better" indicators
            function statusBadge(value, percent) {
                if (value === null) return '<span class="rpt-badge rpt-badge-muted">មិនទាន់បញ្ចូល</span>';
                if (percent !== null && percent < 100) return '<span class="rpt-badge rpt-badge-warn">មិនទាន់គ្រប់</span>';
                return '<span class="rpt-badge rpt-badge-ok">បានបញ្ចូល</span>';
            }

            async function saveAll() {
                if (!DIRTY.size) { setStatus('គ្មានអ្វីត្រូវរក្សាទុក'); return; }
                btnSaveAll.disabled = true;
                setStatus('កំពុងរក្សាទុក…');
                try {
                    const res = await RPT.send(@json(route('report.health.entry.save')), 'POST', { period: PERIOD, rows: Array.from(DIRTY.values()) });
                    res.saved.forEach((s) => {
                        const tr = item(s.indicator_id);
                        if (!tr) return;
                        tr.dataset.empty = s.value === null ? '1' : '0';
                        tr.querySelector('[data-status]').innerHTML = statusBadge(s.value, s.percent);
                        tr.querySelectorAll('.rpt-cell-input').forEach((i) => i.classList.remove('dirty'));
                        DIRTY.delete(String(s.indicator_id));
                    });
                    document.getElementById('cntFilled').textContent = RPT.kh(filledCount());
                    refreshDirty();
                    setStatus(res.message);
                } catch (err) {
                    setStatus('បរាជ័យរក្សាទុក: ' + err.message);
                } finally {
                    btnSaveAll.disabled = false;
                }
            }
            btnSaveAll.addEventListener('click', saveAll);
            document.getElementById('dockSave').addEventListener('click', saveAll);
            document.getElementById('dockTop').addEventListener('click', () => document.getElementById('tblEntry').scrollIntoView({ behavior: 'smooth' }));

            /* ---------- copy previous period (fills only empty cells) ---------- */
            document.getElementById('btnCopyPrev')?.addEventListener('click', async () => {
                setStatus('កំពុងយកតម្លៃរយៈពេលមុន…');
                try {
                    const url = new URL(@json(route('report.health.entry.previous')));
                    url.searchParams.set('period', PERIOD);
                    const res = await RPT.send(url, 'GET');
                    let changed = 0;
                    res.values.forEach((v) => {
                        const tr = item(v.indicator_id);
                        if (!tr) return;
                        let touched = false;
                        ['value', 'target'].forEach((f) => {
                            const inp = tr.querySelector(`[data-field="${f}"]`);
                            if (inp && inp.value === '' && v[f] !== null) { inp.value = String(Number(v[f])); touched = true; }
                        });
                        if (touched) { markDirty(tr); changed++; }
                    });
                    tbody.querySelectorAll('table.rpt-matrix').forEach(updateTotals);
                    setStatus(`បានយកពី ${res.period} ${RPT.kh(changed)} ជួរ (សូមចុច «រក្សាទុកទាំងអស់»)`);
                } catch (err) {
                    setStatus('បរាជ័យយករយៈពេលមុន: ' + err.message);
                }
            });

            /* ---------- issue / action modal ---------- */
            const ISSUES = @json($issueData);
            const modalEl = document.getElementById('mdlIssue');
            const modal = new bootstrap.Modal(modalEl);
            const form = document.getElementById('frmIssue');
            const toggleResolution = () => {
                const done = form.action_status.value === 'done';
                form.querySelectorAll('.resolution').forEach((el) => { el.hidden = !done; });
                if (done && !form.resolution_date.value) form.resolution_date.value = new Date().toISOString().slice(0, 10);
            };
            form.action_status.addEventListener('change', toggleResolution);
            function fill(id, a) {
                form.action_id.value = a?.id || '';
                form.indicator_id.value = id;
                form.issue_text.value = a?.issue_text || '';
                form.action_text.value = a?.action_text || '';
                form.action_owner.value = a?.action_owner || '';
                form.action_due.value = a?.due || '';
                form.action_status.value = a?.action_status || 'planned';
                form.resolution_text.value = a?.resolution_text || '';
                form.resolution_date.value = a?.res_date || '';
                document.getElementById('issueStatus').textContent = a?.id ? 'កំពុងកែបញ្ហាចុងក្រោយ — ចុច «បញ្ហាថ្មី» ដើម្បីបន្ថែមមួយទៀត' : '';
                toggleResolution();
            }
            tbody.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-issue]');
                if (!btn) return;
                const id = btn.dataset.issue;
                document.getElementById('issueIndicator').textContent = btn.closest('[data-id]').querySelector('[data-name]').textContent;
                fill(id, ISSUES[id] || null);
                modal.show();
            });
            document.getElementById('btnNewIssue').addEventListener('click', () => fill(form.indicator_id.value, null));
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const body = Object.fromEntries(new FormData(form).entries());
                body.period = PERIOD;
                const id = body.action_id;
                delete body.action_id;
                const btn = document.getElementById('btnSaveIssue');
                btn.disabled = true;
                try {
                    const url = id ? @json(url('report/health/issues')) + '/' + id : @json(route('report.health.issues.store'));
                    const res = await RPT.send(url, id ? 'PUT' : 'POST', body);
                    const a = res.action;
                    ISSUES[body.indicator_id] = { ...a, due: a.action_due ? a.action_due.slice(0, 10) : '', res_date: a.resolution_date ? a.resolution_date.slice(0, 10) : '' };
                    const link = tbody.querySelector(`[data-issue="${body.indicator_id}"]`);
                    if (link && !id) link.textContent = 'បញ្ហា (+)';
                    setStatus(res.message);
                    modal.hide();
                } catch (err) {
                    document.getElementById('issueStatus').textContent = 'បរាជ័យរក្សាទុក: ' + err.message;
                } finally {
                    btn.disabled = false;
                }
            });
        })();
    </script>
@endpush
