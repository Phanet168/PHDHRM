@extends('report::health.layout')
@php
    use Modules\Report\Services\Hr\HrStatistics;
    use Modules\Report\Services\PeriodCode;
    $kh = fn ($v) => PeriodCode::kh($v);
    $sexes = \Modules\Report\Entities\ReportFact::SEXES;
    $event = $indicator->hrm_measure !== 'headcount';
@endphp

@section('rpt_title', 'បញ្ជីឈ្មោះ')

@section('rpt')
    <section class="rpt-title">
        <div>
            <div class="rpt-eyebrow">ទិន្នន័យ HRM • {{ PeriodCode::label($code) }}</div>
            <h1>{{ $indicator->name }}</h1>
            <p>{{ $indicator->hrmLabel() }} — <b>{{ $kh($people->count()) }}</b> នាក់</p>
        </div>
        <a class="rpt-btn" href="{{ url()->previous() }}">← ត្រឡប់</a>
    </section>

    <div class="rpt-card">
        <div class="rpt-table-wrap">
            <table class="rpt-table">
                <thead><tr><th>#</th><th>ឈ្មោះ</th><th>អត្តលេខ</th><th>ភេទ</th><th>ជំនាញ</th><th>ប្រភេទ</th><th>អង្គភាព</th>@if ($event)<th>កាលបរិច្ឆេទ</th>@endif</tr></thead>
                <tbody>
                    @forelse ($people as $i => $p)
                        <tr>
                            <td class="rpt-id">{{ $kh($i + 1) }}</td>
                            <td class="rpt-name">{{ $p->name }}</td>
                            <td>{{ $p->official_id_10 ?: $p->staff_no }}</td>
                            <td>{{ $sexes[$p->sex] ?? '—' }}</td>
                            <td>{{ $p->profession ?? '—' }}</td>
                            <td>{{ HrStatistics::EMPLOYMENT[$p->employment] ?? '—' }}</td>
                            <td>{{ $p->unit ?? '—' }}<div class="rpt-sub">{{ HrStatistics::WORKPLACES[$p->workplace] ?? '' }}</div></td>
                            @if ($event)<td>{{ $p->event_date ? \Illuminate\Support\Carbon::parse($p->event_date)->format('d/m/Y') : '—' }}</td>@endif
                        </tr>
                    @empty
                        <tr><td colspan="8" class="rpt-empty">គ្មានទិន្នន័យ — មិនទាន់រក្សាទុកស្ថិតិ HRM សម្រាប់ខែនេះ ឬគ្មាននរណាត្រូវលក្ខខណ្ឌ</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
