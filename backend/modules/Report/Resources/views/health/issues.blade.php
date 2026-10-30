@extends('report::health.layout')
@php
    use Modules\Report\Services\PeriodCode;
    $kh = fn ($v) => PeriodCode::kh($v);
    $badge = ['planned' => 'rpt-badge-muted', 'ongoing' => 'rpt-badge-info', 'done' => 'rpt-badge-ok', 'blocked' => 'rpt-badge-danger'];
    $canEnter = auth()->user()->can('update_health_activity_report') || auth()->user()->can('update_health_report_settings');
@endphp

@section('rpt_title', 'បញ្ហាប្រឈម')

@section('rpt')
    <section class="rpt-title">
        <div>
            <div class="rpt-eyebrow">ការគ្រប់គ្រងប្រព័ន្ធ</div>
            <h1>បញ្ហាប្រឈម</h1>
            <p>តាមដាន Issue, Action, អ្នកទទួលខុសត្រូវ និងកាលកំណត់</p>
        </div>
        <div class="rpt-actions">
            <a class="rpt-btn rpt-btn-outline" href="{{ route('report.health.issues.export', request()->query()) }}">Export Excel</a>
            @if ($canEnter)
                <button type="button" class="rpt-btn rpt-btn-primary" data-new-issue>បន្ថែមថ្មី</button>
            @endif
        </div>
    </section>

    <section class="row g-3 mb-4">
        @foreach ($statuses as $key => $label)
            <div class="col-6 col-xl-3">
                <a class="rpt-card d-block text-decoration-none" style="padding:18px;color:inherit" href="{{ route('report.health.issues', array_merge(request()->query(), ['status' => $key, 'page' => null])) }}">
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="color:var(--rpt-s500)">{{ $label }}</span>
                        <span class="rpt-badge {{ $badge[$key] }}">{{ $kh($counts[$key] ?? 0) }}</span>
                    </div>
                </a>
            </div>
        @endforeach
    </section>

    <div class="rpt-card">
        <form method="GET" class="rpt-toolbar">
            <div class="rpt-search">
                @include('report::health._icon', ['name' => 'search'])
                <input class="rpt-input" type="search" name="q" value="{{ request('q') }}" placeholder="ស្វែងរកសូចនាករ បញ្ហា ឬអ្នកទទួលខុសត្រូវ...">
            </div>
            <select class="rpt-select" name="year">
                <option value="">ឆ្នាំទាំងអស់</option>
                @foreach ($years as $y)
                    <option value="{{ $y }}" @selected(request('year') == $y)>ឆ្នាំ {{ $kh($y) }}</option>
                @endforeach
            </select>
            <select class="rpt-select" name="status">
                <option value="">ស្ថានភាពទាំងអស់</option>
                @foreach ($statuses as $key => $label)
                    <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <select class="rpt-select" name="department">
                <option value="">ជំពូកទាំងអស់</option>
                @foreach ($departments as $d)
                    <option value="{{ $d->id }}" @selected(request('department') == $d->id)>{{ $d->name }}</option>
                @endforeach
            </select>
            <button class="rpt-btn rpt-btn-primary" type="submit">អនុវត្ត</button>
            @if (request()->hasAny(['q', 'year', 'status', 'department']))
                <a class="rpt-link" href="{{ route('report.health.issues') }}">សម្អាត</a>
            @endif
        </form>

        <div class="rpt-table-wrap">
            <table class="rpt-table">
                <thead><tr><th>រយៈពេល</th><th>សូចនាករ</th><th>បញ្ហាប្រឈម</th><th>សកម្មភាព</th><th>អ្នកទទួលខុសត្រូវ</th><th>កាលកំណត់</th><th>ស្ថានភាព</th><th></th></tr></thead>
                <tbody>
                    @forelse ($actions as $a)
                        <tr>
                            <td style="white-space:nowrap">{{ $a->period?->label }}</td>
                            <td class="rpt-name" style="max-width:280px">{{ $a->indicator?->name }}<div class="rpt-sub">{{ \Modules\Report\Entities\ReportIndicator::shortCode($a->indicator?->code) }} • {{ $a->indicator?->unit?->name }}</div></td>
                            <td style="max-width:240px">{{ $a->issue_text ?: '—' }}</td>
                            <td style="max-width:240px">{{ $a->action_text ?: '—' }}
                                @if ($a->action_status === 'done' && $a->resolution_text)
                                    <div class="rpt-sub" style="color:var(--rpt-e700)">✓ {{ $a->resolution_text }} {{ $a->resolution_date ? '(' . $a->resolution_date->format('d/m/Y') . ')' : '' }}</div>
                                @endif
                            </td>
                            <td>{{ $a->action_owner ?: '—' }}</td>
                            <td style="white-space:nowrap" class="{{ $a->action_due && $a->action_due->isPast() && $a->action_status !== 'done' ? 'text-danger' : '' }}">{{ $a->action_due?->format('d/m/Y') ?? '—' }}</td>
                            <td><span class="rpt-badge {{ $badge[$a->action_status] ?? 'rpt-badge-muted' }}">{{ $a->status_label }}</span></td>
                            <td style="white-space:nowrap">
                                @if ($canEnter)
                                    <button type="button" class="rpt-link" data-edit="{{ json_encode(['id' => $a->id, 'indicator_id' => $a->indicator_id, 'period' => $a->period?->code, 'issue_text' => $a->issue_text, 'action_text' => $a->action_text, 'action_owner' => $a->action_owner, 'action_due' => $a->action_due?->format('Y-m-d'), 'action_status' => $a->action_status, 'resolution_text' => $a->resolution_text, 'resolution_date' => $a->resolution_date?->format('Y-m-d')]) }}">កែ</button>
                                @endif
                                @canany(['delete_health_activity_report', 'delete_health_report_settings'])
                                    <form method="POST" action="{{ route('report.health.issues.destroy', $a) }}" class="d-inline ms-2" onsubmit="return confirm('លុបបញ្ហានេះ?')">
                                        @csrf @method('DELETE')
                                        <button class="rpt-link rpt-link-danger" type="submit">លុប</button>
                                    </form>
                                @endcanany
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8">
                            <div class="rpt-empty">
                                <div class="mx-auto mb-3" style="display:grid;place-items:center;width:64px;height:64px;border-radius:16px;background:var(--rpt-e50);color:var(--rpt-e700)">@include('report::health._icon', ['name' => 'alert'])</div>
                                <div style="font-weight:600;color:var(--rpt-s950)">មិនទាន់មានបញ្ហាប្រឈម</div>
                                <div class="mt-1">បញ្ហាអាចបន្ថែមពីទំព័រ «បញ្ចូលទិន្នន័យ» ឬប៊ូតុង «បន្ថែមថ្មី»</div>
                            </div>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($actions->hasPages())
            <div class="rpt-foot">{{ $actions->links() }}</div>
        @endif
    </div>

    @if ($canEnter)
        <div class="modal fade phd-rpt-modal" id="mdlIssue" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <form class="modal-content phd-rpt" method="POST" id="frmIssue" action="{{ route('report.health.issues.store') }}">
                    @csrf
                    <input type="hidden" name="_method" value="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" style="font-weight:600" id="issueTitle">បញ្ហាប្រឈមថ្មី</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="បិទ"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-8 create-only">
                                <label class="rpt-label">សូចនាករ</label>
                                <select class="rpt-input" name="indicator_id">
                                    @foreach ($indicators as $i)
                                        <option value="{{ $i->id }}">{{ \Modules\Report\Entities\ReportIndicator::shortCode($i->code) }} —{{ \Illuminate\Support\Str::limit($i->name, 90) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 create-only">
                                <label class="rpt-label">រយៈពេល</label>
                                <div class="d-flex gap-2">
                                    <select class="rpt-input" id="issueYear">
                                        @foreach ($years as $y)<option value="{{ $y }}" @selected($y == $p['year'])>{{ $y }}</option>@endforeach
                                    </select>
                                    <select class="rpt-input" id="issueTag">
                                        @foreach (PeriodCode::allTags() as $tag)<option value="{{ $tag }}" @selected($tag === $p['tag'])>{{ PeriodCode::tagLabel($tag) }}</option>@endforeach
                                    </select>
                                </div>
                                <input type="hidden" name="period">
                            </div>
                            <div class="col-md-6"><label class="rpt-label">បញ្ហាប្រឈម</label><textarea class="rpt-input" name="issue_text"></textarea></div>
                            <div class="col-md-6"><label class="rpt-label">សកម្មភាពដោះស្រាយ</label><textarea class="rpt-input" name="action_text"></textarea></div>
                            <div class="col-md-4"><label class="rpt-label">អ្នកទទួលខុសត្រូវ</label><input class="rpt-input" name="action_owner"></div>
                            <div class="col-md-4"><label class="rpt-label">កាលកំណត់</label><input class="rpt-input" type="date" name="action_due"></div>
                            <div class="col-md-4"><label class="rpt-label">ស្ថានភាព</label>
                                <select class="rpt-input" name="action_status">
                                    @foreach ($statuses as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-md-8 resolution"><label class="rpt-label">ដំណោះស្រាយ</label><textarea class="rpt-input" name="resolution_text"></textarea></div>
                            <div class="col-md-4 resolution"><label class="rpt-label">ថ្ងៃដោះស្រាយ</label><input class="rpt-input" type="date" name="resolution_date"></div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="submit" class="rpt-btn rpt-btn-primary">រក្សាទុក</button></div>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('rpt_js')
    <script>
        (function () {
            const form = document.getElementById('frmIssue');
            if (!form) return;
            const modal = new bootstrap.Modal(document.getElementById('mdlIssue'));
            const storeUrl = form.getAttribute('action');
            const toggleResolution = () => {
                const done = form.action_status.value === 'done';
                form.querySelectorAll('.resolution').forEach((el) => { el.hidden = !done; });
                if (done && !form.resolution_date.value) form.resolution_date.value = new Date().toISOString().slice(0, 10);
            };
            form.action_status.addEventListener('change', toggleResolution);
            function open(a) {
                const editing = !!a;
                form.setAttribute('action', editing ? @json(url('report/health/issues')) + '/' + a.id : storeUrl);
                form._method.value = editing ? 'PUT' : 'POST';
                form.querySelectorAll('.create-only').forEach((el) => { el.hidden = editing; });
                document.getElementById('issueTitle').textContent = editing ? 'កែបញ្ហាប្រឈម' : 'បញ្ហាប្រឈមថ្មី';
                ['issue_text', 'action_text', 'action_owner', 'action_due', 'resolution_text', 'resolution_date'].forEach((f) => { form[f].value = a?.[f] || ''; });
                form.action_status.value = a?.action_status || 'planned';
                toggleResolution();
                modal.show();
            }
            form.addEventListener('submit', () => {
                if (form._method.value === 'POST') form.period.value = document.getElementById('issueYear').value + '-' + document.getElementById('issueTag').value;
            });
            document.querySelector('[data-new-issue]')?.addEventListener('click', () => open(null));
            document.querySelectorAll('[data-edit]').forEach((btn) => btn.addEventListener('click', () => open(JSON.parse(btn.dataset.edit))));
        })();
    </script>
@endpush
