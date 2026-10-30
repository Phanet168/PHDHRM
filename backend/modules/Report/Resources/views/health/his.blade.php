@extends('report::health.layout')
@php
    use Modules\Report\Services\PeriodCode;
    $kh = fn ($v) => PeriodCode::kh($v);
    $num = fn ($v) => PeriodCode::kh(number_format((float) $v));
@endphp

@section('rpt_title', 'ទិន្នន័យ HIS')

@section('rpt')
    <section class="rpt-title">
        <div>
            <div class="rpt-eyebrow">នាំចូលទិន្នន័យលម្អិត</div>
            <h1>ទិន្នន័យ HIS (HC1 / HO2)</h1>
            <p>នាំចូលឯកសារ HIS ទាំងមូល — ជំងឺតាម ICD-10 × ក្រុមអាយុ × ភេទ × មូលដ្ឋានសុខាភិបាល — ហើយពិនិត្យផលបូកជាមួយជួរ «សរុប» ដោយស្វ័យប្រវត្តិ</p>
        </div>
    </section>

    <div class="row g-4">
        <div class="col-xl-8">
            <form class="rpt-card rpt-card-pad" method="POST" action="{{ route('report.health.his.preview') }}" enctype="multipart/form-data" id="frmHis">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="rpt-label">ប្រភេទឯកសារ</label>
                        <select class="rpt-input" name="template_id" id="selTemplate">
                            @foreach ($templates as $t)
                                <option value="{{ $t->id }}" data-type="{{ $t->facility_type }}" @selected(old('template_id') == $t->id)>{{ $t->name }} ({{ $kh($t->blocks_count) }} តារាង)</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="rpt-label">មូលដ្ឋានសុខាភិបាល</label>
                        <select class="rpt-input" name="facility_id" id="selFacility">
                            @foreach ($facilities->groupBy('type_name') as $type => $list)
                                <optgroup label="{{ $type }}">
                                    @foreach ($list as $f)
                                        <option value="{{ $f->id }}" data-type="{{ $f->type_code }}" @selected(old('facility_id') == $f->id)>{{ $f->department_name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="rpt-label">ឆ្នាំ</label>
                        <select class="rpt-input" name="year">
                            @foreach ($years as $y)<option value="{{ $y }}" @selected($y == $p['year'])>{{ $kh($y) }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
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
                    <div class="col-md-6">
                        <label class="rpt-label">ឯកសារ HIS (.xls / .xlsx)</label>
                        <input class="rpt-input" type="file" name="file" accept=".xls,.xlsx" required>
                    </div>
                </div>
                <div class="d-flex justify-content-end mt-4">
                    <button class="rpt-btn rpt-btn-primary" type="submit">អាន និងពិនិត្យឯកសារ</button>
                </div>
            </form>
        </div>
        <aside class="col-xl-4">
            <div class="rpt-note h-100">
                <span style="color:var(--rpt-a600)">@include('report::health._icon', ['name' => 'alert'])</span>
                <div>
                    <h3>របៀបដំណើរការ</h3>
                    <p>• HC1 = ឯកសាររួមមណ្ឌលសុខភាពទាំងអស់របស់ OD → ជ្រើស <b>ស្រុកប្រតិបត្តិ</b><br>
                        • HO2 = ឯកសារមន្ទីរពេទ្យ → ជ្រើស <b>មន្ទីរពេទ្យ</b><br>
                        • ប្រព័ន្ធអានតារាងតាម header៖ អាយុ ភេទ តំបន់ ចេញ/ស្លាប់ បញ្ជូន…<br>
                        • នាំចូលម្តងទៀតសម្រាប់មូលដ្ឋាន និងរយៈពេលដដែល នឹងជំនួសទិន្នន័យចាស់ (មិនស្ទួន)</p>
                </div>
            </div>
        </aside>
    </div>

    @if ($preview)
        <div class="rpt-card mt-4" id="previewCard">
            <div class="rpt-toolbar">
                <div class="me-auto">
                    <h2>ពិនិត្យ រួចបញ្ជាក់នាំចូល</h2>
                    <div class="rpt-sub">{{ $preview['template'] }} • {{ $preview['facility'] }} • {{ PeriodCode::label($preview['period']) }} • {{ $preview['file_name'] }}</div>
                </div>
                <span class="rpt-badge rpt-badge-info" style="font-size:13px">{{ $num($preview['values']) }} តម្លៃ</span>
                <span class="rpt-badge {{ $preview['checks'] ? 'rpt-badge-danger' : 'rpt-badge-ok' }}" style="font-size:13px">{{ $preview['checks'] ? 'ផលបូកមិនត្រូវ ' . $kh($preview['checks']) : '✓ ផលបូកត្រូវទាំងអស់' }}</span>
                <form method="POST" action="{{ route('report.health.his.commit') }}" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'កំពុងនាំចូល…'">
                    @csrf
                    <input type="hidden" name="token" value="{{ $preview['token'] }}">
                    <button class="rpt-btn rpt-btn-dark" type="submit" @disabled(!$preview['values'])>បញ្ជាក់នាំចូល</button>
                </form>
            </div>
            @if ($preview['duplicate'])
                <div class="rpt-alert rpt-alert-err" style="margin:16px 20px 0">ឯកសារនេះដូចគ្នានឹងឯកសារដែលបាននាំចូលរួច៖ {{ $preview['duplicate']['file_name'] }} ({{ \Illuminate\Support\Carbon::parse($preview['duplicate']['created_at'])->format('d/m/Y H:i') }})។ បើបញ្ជាក់ ទិន្នន័យសម្រាប់មូលដ្ឋាន និងរយៈពេលដែលបានជ្រើស នឹងត្រូវជំនួស។</div>
            @endif
            <div class="rpt-table-wrap">
                <table class="rpt-table">
                    <thead><tr><th>Sheet</th><th>តារាង</th><th class="text-end">តម្លៃ</th><th class="text-end">ធាតុទិន្នន័យ</th><th class="text-end">ជំងឺ (ICD-10)</th><th class="text-end">ផលបូក</th><th>ការពិនិត្យ</th></tr></thead>
                    <tbody>
                        @foreach ($preview['blocks'] as $b)
                            <tr>
                                <td class="rpt-id">{{ $b['sheet'] }}</td>
                                <td class="rpt-name">{{ $b['title'] }}@if ($b['error'])<div class="rpt-sub" style="color:var(--rpt-r700)">{{ $b['error'] }}</div>@endif</td>
                                <td class="text-end">{{ $num($b['values']) }}</td>
                                <td class="text-end">{{ $num($b['elements']) }}</td>
                                <td class="text-end">{{ $b['diagnoses'] ? $num($b['diagnoses']) : '—' }}</td>
                                <td class="text-end">{{ $num($b['sum']) }}</td>
                                <td>
                                    @if ($b['checks_count'])
                                        <details>
                                            <summary class="rpt-badge rpt-badge-danger" style="cursor:pointer">{{ $kh($b['checks_count']) }} មិនត្រូវ</summary>
                                            <div class="rpt-sub mt-2">
                                                @foreach ($b['checks'] as $c)
                                                    <div>{{ $c['cell'] }} — {{ $c['what'] }}@if ($c['expected'] !== null): ឯកសារ {{ $c['expected'] }} ≠ បូក {{ $c['actual'] }}@endif</div>
                                                @endforeach
                                            </div>
                                        </details>
                                    @else
                                        <span class="rpt-badge rpt-badge-ok">✓ ត្រូវ</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="rpt-card mt-4">
        <div class="rpt-toolbar"><h2 class="me-auto">ការនាំចូលកន្លងមក</h2></div>
        <div class="rpt-table-wrap">
            <table class="rpt-table">
                <thead><tr><th>កាលបរិច្ឆេទ</th><th>ប្រភេទ</th><th>មូលដ្ឋានសុខាភិបាល</th><th>រយៈពេល</th><th>ឯកសារ</th><th class="text-end">តម្លៃ</th><th>អ្នកនាំចូល</th><th></th></tr></thead>
                <tbody>
                    @forelse ($batches as $b)
                        <tr>
                            <td style="white-space:nowrap">{{ $b->created_at?->format('d/m/Y H:i') }}</td>
                            <td><span class="rpt-badge rpt-badge-info">{{ $b->template?->code }}</span></td>
                            <td>{{ $b->facility?->department_name ?? '#' . $b->facility_id }}</td>
                            <td>{{ $b->period?->label }}</td>
                            <td style="max-width:220px" class="text-truncate" title="{{ $b->file_name }}">{{ $b->file_name }}</td>
                            <td class="text-end">{{ $num($b->facts_count) }}</td>
                            <td>{{ $b->creator?->full_name ?? '—' }}</td>
                            <td class="text-end">
                                @canany(['delete_health_activity_report', 'delete_health_report_settings'])
                                    <form method="POST" action="{{ route('report.health.his.destroy', $b) }}" onsubmit="return confirm('លុបការនាំចូលនេះ និងទិន្នន័យ {{ $num($b->facts_count) }} តម្លៃរបស់វា?')">
                                        @csrf @method('DELETE')
                                        <button class="rpt-link rpt-link-danger" type="submit">លុប</button>
                                    </form>
                                @endcanany
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="rpt-empty">មិនទាន់មានការនាំចូល HIS</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($batches->hasPages())<div class="rpt-foot">{{ $batches->links() }}</div>@endif
    </div>
@endsection

@push('rpt_js')
    <script>
        (function () {
            const tpl = document.getElementById('selTemplate');
            const fac = document.getElementById('selFacility');
            // Default the facility to the type the template expects (HC1 → OD, HO2 → hospital).
            const pickFacility = () => {
                const type = tpl.selectedOptions[0]?.dataset.type;
                const match = Array.from(fac.options).find((o) => o.dataset.type === type);
                if (match) fac.value = match.value;
            };
            tpl.addEventListener('change', pickFacility);
            if (!@json(old('facility_id'))) pickFacility();
            document.getElementById('selTag').addEventListener('change', (e) => {
                document.getElementById('inpType').value = e.target.selectedOptions[0].dataset.type;
            });
            document.getElementById('frmHis').addEventListener('submit', (e) => {
                const btn = e.target.querySelector('button[type=submit]');
                btn.disabled = true;
                btn.textContent = 'កំពុងអាន Excel…';
            });
            document.getElementById('previewCard')?.scrollIntoView({ behavior: 'smooth' });
        })();
    </script>
@endpush
