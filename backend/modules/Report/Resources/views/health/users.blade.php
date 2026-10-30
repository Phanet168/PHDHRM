@extends('report::health.layout')
@php
    $kh = fn ($v) => \Modules\Report\Services\PeriodCode::kh($v);
    $canManage = auth()->user()->can('update_health_report_settings');
@endphp

@section('rpt_title', 'អ្នកប្រើប្រាស់')

@section('rpt')
    <section class="rpt-title">
        <div>
            <div class="rpt-eyebrow">ការគ្រប់គ្រងប្រព័ន្ធ</div>
            <h1>អ្នកប្រើប្រាស់</h1>
            <p>កំណត់អ្នកប្រើប្រាស់ HRM ដែលទទួលខុសត្រូវបញ្ចូលទិន្នន័យតាមផ្នែក។ តួនាទី និងសិទ្ធិ (read / update / create) កំណត់នៅ «User Management › Role»។</p>
        </div>
        @if ($canManage)
            <button type="button" class="rpt-btn rpt-btn-primary" data-assign='{}'>បន្ថែមថ្មី</button>
        @endif
    </section>

    <div class="rpt-card">
        <form method="GET" class="rpt-toolbar">
            <div class="rpt-search">
                @include('report::health._icon', ['name' => 'search'])
                <input class="rpt-input" type="search" name="q" value="{{ request('q') }}" placeholder="ស្វែងរកឈ្មោះ ឬអ៊ីមែល...">
            </div>
            <button class="rpt-btn rpt-btn-primary" type="submit">អនុវត្ត</button>
        </form>
        <div class="rpt-table-wrap">
            <table class="rpt-table">
                <thead><tr><th>អ្នកប្រើប្រាស់</th><th>ផ្នែកទទួលខុសត្រូវ</th><th>សិទ្ធិ</th><th style="width:120px"></th></tr></thead>
                <tbody>
                    @forelse ($assignments as $userId => $list)
                        @php($u = $list->first()->user)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <span style="display:grid;place-items:center;width:40px;height:40px;border-radius:12px;background:var(--rpt-e100);color:var(--rpt-e700);font-weight:600">{{ mb_substr($u?->full_name ?? '?', 0, 1) }}</span>
                                    <div><div style="font-weight:600">{{ $u?->full_name ?? '#' . $userId }}</div><div class="rpt-sub">{{ $u?->email }}</div></div>
                                </div>
                            </td>
                            <td>
                                @foreach ($list as $a)
                                    <span class="rpt-badge rpt-badge-muted mb-1">{{ $a->unit?->name }}</span>
                                @endforeach
                            </td>
                            <td>
                                @if ($list->every('can_edit'))
                                    <span class="rpt-badge rpt-badge-ok">បញ្ចូល / កែ</span>
                                @else
                                    <span class="rpt-badge rpt-badge-info">មើលប៉ុណ្ណោះ</span>
                                @endif
                            </td>
                            <td class="text-end" style="white-space:nowrap">
                                @if ($canManage)
                                    <button type="button" class="rpt-link" data-assign="{{ json_encode(['user_id' => (int) $userId, 'unit_ids' => $list->pluck('unit_id')->values(), 'can_edit' => $list->every('can_edit')]) }}">កែ</button>
                                    <form method="POST" action="{{ route('report.health.users.destroy', $userId) }}" class="d-inline ms-2" onsubmit="return confirm('ដកសិទ្ធិអ្នកប្រើនេះ?')">@csrf @method('DELETE')<button class="rpt-link rpt-link-danger">ដក</button></form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4">
                            <div class="rpt-empty">
                                <div class="mx-auto mb-3" style="display:grid;place-items:center;width:64px;height:64px;border-radius:16px;background:var(--rpt-e50);color:var(--rpt-e700)">@include('report::health._icon', ['name' => 'users'])</div>
                                <div style="font-weight:600;color:var(--rpt-s950)">មិនទាន់កំណត់អ្នកប្រើប្រាស់</div>
                                <div class="mt-1">អ្នកមានសិទ្ធិ «update_health_report_settings» អាចបញ្ចូលទិន្នន័យគ្រប់ផ្នែក</div>
                            </div>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($canManage)
        <div class="modal fade phd-rpt-modal" id="mdlAssign" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <form class="modal-content phd-rpt" method="POST" action="{{ route('report.health.users.store') }}">
                    @csrf
                    <div class="modal-header"><h5 class="modal-title" style="font-weight:600">កំណត់ផ្នែកឱ្យអ្នកប្រើប្រាស់</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="បិទ"></button></div>
                    <div class="modal-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-8"><label class="rpt-label">អ្នកប្រើប្រាស់</label>
                                <select class="rpt-input" name="user_id" required>
                                    <option value="">— ជ្រើសរើស —</option>
                                    @foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->full_name }}{{ $u->email ? ' — ' . $u->email : '' }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end"><label class="d-flex gap-2 align-items-center"><input type="hidden" name="can_edit" value="0"><input type="checkbox" name="can_edit" value="1" checked> អាចបញ្ចូល / កែទិន្នន័យ</label></div>
                        </div>
                        <label class="rpt-label">ផ្នែក</label>
                        @foreach ($departments as $d)
                            <div class="mb-3">
                                <div style="font-weight:600;color:var(--rpt-e900)" class="mb-1">{{ $d->name }}</div>
                                <div class="row g-1">
                                    @foreach ($d->units as $unit)
                                        <label class="col-md-6 d-flex gap-2 align-items-center"><input type="checkbox" name="unit_ids[]" value="{{ $unit->id }}"> {{ $unit->name }}</label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
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
            const el = document.getElementById('mdlAssign');
            if (!el) return;
            const modal = new bootstrap.Modal(el);
            const form = el.querySelector('form');
            document.querySelectorAll('[data-assign]').forEach((btn) => btn.addEventListener('click', () => {
                const d = JSON.parse(btn.dataset.assign);
                form.user_id.value = d.user_id || '';
                const units = (d.unit_ids || []).map(String);
                form.querySelectorAll('input[name="unit_ids[]"]').forEach((c) => { c.checked = units.includes(c.value); });
                form.querySelector('input[type=checkbox][name=can_edit]').checked = d.can_edit !== false;
                modal.show();
            }));
        })();
    </script>
@endpush
