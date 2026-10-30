@extends('report::health.layout')
@php
    $kh = fn ($v) => \Modules\Report\Services\PeriodCode::kh($v);
    $canManage = auth()->user()->can('update_health_report_settings');
    $canDelete = auth()->user()->can('delete_health_report_settings');
@endphp

@section('rpt_title', 'ផ្នែក និងជំពូក')

@section('rpt')
    <section class="rpt-title">
        <div>
            <div class="rpt-eyebrow">ការគ្រប់គ្រងប្រព័ន្ធ</div>
            <h1>ផ្នែក និងជំពូក</h1>
            <p>គ្រប់គ្រងរចនាសម្ព័ន្ធ ជំពូក ផ្នែក និងសិទ្ធិទទួលខុសត្រូវ</p>
        </div>
        @if ($canManage)
            <div class="rpt-actions">
                <button type="button" class="rpt-btn rpt-btn-outline" data-unit='{}'>បន្ថែមផ្នែក</button>
                <button type="button" class="rpt-btn rpt-btn-primary" data-dept='{}'>បន្ថែមជំពូក</button>
            </div>
        @endif
    </section>

    <div class="rpt-card">
        <div class="rpt-table-wrap">
            <table class="rpt-table">
                <thead><tr><th style="width:110px">លេខកូដ</th><th>ឈ្មោះ</th><th>ប្រភេទ</th><th class="text-end">សូចនាករ</th><th class="text-end">លំដាប់</th><th style="width:140px"></th></tr></thead>
                <tbody>
                    @forelse ($departments as $d)
                        <tr class="rpt-group-dept">
                            <td>{{ $d->code }}</td>
                            <td>{{ $d->name }} @unless ($d->active)<span class="rpt-badge rpt-badge-muted">បិទ</span>@endunless</td>
                            <td>ជំពូក</td>
                            <td class="text-end">{{ $kh($d->indicators_count) }}</td>
                            <td class="text-end">{{ $kh($d->sort_order) }}</td>
                            <td class="text-end" style="white-space:nowrap">
                                @if ($canManage)
                                    <button type="button" class="rpt-link" data-unit="{{ json_encode(['department_id' => $d->id]) }}">+ ផ្នែក</button>
                                    <button type="button" class="rpt-link ms-2" data-dept="{{ json_encode($d->only(['id', 'code', 'name', 'sort_order', 'active'])) }}">កែ</button>
                                @endif
                                @if ($canDelete && !$d->indicators_count && $d->units->isEmpty())
                                    <form method="POST" action="{{ route('report.health.structure.departments.destroy', $d) }}" class="d-inline ms-2" onsubmit="return confirm('លុបជំពូកនេះ?')">@csrf @method('DELETE')<button class="rpt-link rpt-link-danger">លុប</button></form>
                                @endif
                            </td>
                        </tr>
                        @foreach ($d->units as $u)
                            <tr>
                                <td class="rpt-id" style="padding-left:32px">{{ $u->code }}</td>
                                <td class="rpt-name">{{ $u->name }} @unless ($u->active)<span class="rpt-badge rpt-badge-muted">បិទ</span>@endunless</td>
                                <td style="color:var(--rpt-s500)">{{ $u->unit_type ?: 'ផ្នែក' }}</td>
                                <td class="text-end">{{ $kh($u->indicators_count) }}</td>
                                <td class="text-end">{{ $kh($u->sort_order) }}</td>
                                <td class="text-end" style="white-space:nowrap">
                                    <a class="rpt-link" href="{{ route('report.health.settings', ['tab' => 'indicators', 'unit' => $u->id]) }}">សូចនាករ</a>
                                    @if ($canManage)
                                        <button type="button" class="rpt-link ms-2" data-unit="{{ json_encode($u->only(['id', 'department_id', 'code', 'name', 'unit_type', 'sort_order', 'active'])) }}">កែ</button>
                                    @endif
                                    @if ($canDelete && !$u->indicators_count)
                                        <form method="POST" action="{{ route('report.health.structure.units.destroy', $u) }}" class="d-inline ms-2" onsubmit="return confirm('លុបផ្នែកនេះ?')">@csrf @method('DELETE')<button class="rpt-link rpt-link-danger">លុប</button></form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="6" class="rpt-empty">មិនទាន់មានជំពូក — នាំចូលពី «ការកំណត់ › នាំចូលទិន្នន័យមេ»</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($canManage)
        <div class="modal fade phd-rpt-modal" id="mdlDept" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content phd-rpt" method="POST" action="{{ route('report.health.structure.departments.save') }}">
                    @csrf
                    <input type="hidden" name="id">
                    <div class="modal-header"><h5 class="modal-title" style="font-weight:600">ជំពូក</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="បិទ"></button></div>
                    <div class="modal-body row g-3">
                        <div class="col-4"><label class="rpt-label">លេខកូដ</label><input class="rpt-input" name="code" maxlength="64" placeholder="ស្វ័យប្រវត្តិ ({{ $nextDepartmentCode }})"></div>
                        <div class="col-8"><label class="rpt-label">ឈ្មោះ</label><input class="rpt-input" name="name" required></div>
                        <div class="col-4"><label class="rpt-label">លំដាប់</label><input class="rpt-input" type="number" min="0" name="sort_order" value="0"></div>
                        <div class="col-8 d-flex align-items-end"><label class="d-flex gap-2 align-items-center"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" checked> សកម្ម</label></div>
                    </div>
                    <div class="modal-footer"><button class="rpt-btn rpt-btn-primary" type="submit">រក្សាទុក</button></div>
                </form>
            </div>
        </div>

        <div class="modal fade phd-rpt-modal" id="mdlUnit" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content phd-rpt" method="POST" action="{{ route('report.health.structure.units.save') }}">
                    @csrf
                    <input type="hidden" name="id">
                    <div class="modal-header"><h5 class="modal-title" style="font-weight:600">ផ្នែក</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="បិទ"></button></div>
                    <div class="modal-body row g-3">
                        <div class="col-12"><label class="rpt-label">ជំពូក</label>
                            <select class="rpt-input" name="department_id" required>
                                @foreach ($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-4"><label class="rpt-label">លេខកូដ</label><input class="rpt-input" name="code" maxlength="64" placeholder="ស្វ័យប្រវត្តិ ({{ $nextUnitCode }})"></div>
                        <div class="col-8"><label class="rpt-label">ឈ្មោះ</label><input class="rpt-input" name="name" required></div>
                        <div class="col-6"><label class="rpt-label">ប្រភេទ</label><input class="rpt-input" name="unit_type" placeholder="ការិយាល័យ, កម្មវិធី..."></div>
                        <div class="col-3"><label class="rpt-label">លំដាប់</label><input class="rpt-input" type="number" min="0" name="sort_order" value="0"></div>
                        <div class="col-3 d-flex align-items-end"><label class="d-flex gap-2 align-items-center"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" checked> សកម្ម</label></div>
                    </div>
                    <div class="modal-footer"><button class="rpt-btn rpt-btn-primary" type="submit">រក្សាទុក</button></div>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('rpt_js')
    <script>
        (function () {
            const wire = (attr, id) => {
                const el = document.getElementById(id);
                if (!el) return;
                const modal = new bootstrap.Modal(el);
                const form = el.querySelector('form');
                document.querySelectorAll(`[${attr}]`).forEach((btn) => btn.addEventListener('click', () => {
                    const data = JSON.parse(btn.getAttribute(attr) || '{}');
                    form.reset();
                    form.querySelector('[name=id]').value = data.id || '';
                    Object.entries(data).forEach(([k, v]) => {
                        const inputs = form.querySelectorAll(`[name="${k}"]`);
                        inputs.forEach((inp) => {
                            if (inp.type === 'checkbox') inp.checked = !!v;
                            else if (inp.type !== 'hidden' || k === 'id') inp.value = v ?? '';
                        });
                    });
                    modal.show();
                }));
            };
            wire('data-dept', 'mdlDept');
            wire('data-unit', 'mdlUnit');
        })();
    </script>
@endpush
