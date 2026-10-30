<!doctype html>
<html lang="km">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>របាយការណ៍វត្តមាន — {{ $employee->full_name }}</title>
    @include('humanresource::attendance.missions.letter-style')
    <style>
        .report-head { display: flex; align-items: center; gap: 5mm; border-bottom: 2px solid #1f3a8a; padding-bottom: 4mm; }
        .report-head img { width: 18mm; height: 18mm; object-fit: contain; }
        .report-head h1 { margin: 0; text-align: left; font-size: 18px; }
        .report-head .period { color: #334; font-size: 13px; }
        .who { display: grid; grid-template-columns: 1fr 1fr; gap: 1mm 8mm; margin: 4mm 0; font-size: 13px; }
        .who b { font-weight: 700; }
        .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 2.5mm; margin: 3mm 0 5mm; }
        .summary div { border: 1px solid #d5dbe5; border-radius: 6px; padding: 2mm 3mm; text-align: center; }
        .summary strong { display: block; font-size: 18px; line-height: 1.5; color: #1f3a8a; }
        .summary span { font-size: 11.5px; color: #445; }
        table.days { width: 100%; border-collapse: collapse; font-size: 12px; }
        table.days th, table.days td { border: 1px solid #c9d1dc; padding: 1.2mm 1.6mm; text-align: center; vertical-align: top; }
        table.days th { background: #eef2f8; font-weight: 700; }
        table.days td.left { text-align: left; }
        table.days tr.rest td { background: #f7f8fa; color: #667; }
        .st { font-weight: 700; }
        .st-on_time { color: #15803d; } .st-late, .st-late_and_early_leave { color: #b7791f; } .st-early_leave { color: #c2410c; }
        .st-absent { color: #b91c1c; } .st-incomplete { color: #64748b; } .st-leave { color: #c2410c; } .st-mission { color: #6d28d9; }
        .st-holiday { color: #be185d; } .st-day_off { color: #64748b; } .st-in_progress { color: #1d4ed8; }
        .note { font-size: 11px; color: #556; }
        .foot { margin-top: 3mm; font-size: 11px; color: #667; }
        .signatures { display: flex; justify-content: space-between; margin-top: 8mm; break-inside: avoid; }
        .signatures > div { width: 45%; text-align: center; }
        .signatures .space { height: 22mm; }
        @media screen and (max-width: 800px) {
            .summary { grid-template-columns: repeat(2, 1fr); }
            .who { grid-template-columns: 1fr; }
            .table-wrap { overflow-x: auto; }
            table.days { min-width: 640px; }
        }
    </style>
</head>
<body>
@php
    $digits = fn ($value) => strtr((string) $value, ['0'=>'០','1'=>'១','2'=>'២','3'=>'៣','4'=>'៤','5'=>'៥','6'=>'៦','7'=>'៧','8'=>'៨','9'=>'៩']);
    $months = ['មករា','កុម្ភៈ','មីនា','មេសា','ឧសភា','មិថុនា','កក្កដា','សីហា','កញ្ញា','តុលា','វិច្ឆិកា','ធ្នូ'];
    $weekdays = [1 => 'ចន្ទ', 'អង្គារ', 'ពុធ', 'ព្រហ', 'សុក្រ', 'សៅរ៍', 'អាទិត្យ'];
    $date = fn ($d) => $digits($d->day).' '.$months[$d->month - 1].' '.$digits($d->year);
    $clock = fn ($value) => $value ? $digits(\Carbon\Carbon::parse($value)->format('H:i')) : '—';
    $minutes = function (int $m) use ($digits) {
        if ($m <= 0) return '—';
        $h = intdiv($m, 60); $r = $m % 60;
        return trim(($h ? $digits($h).'ម៉ោង ' : '').($r ? $digits($r).'នាទី' : ''));
    };
    $labels = ['on_time' => 'ទាន់ពេល', 'late' => 'មកយឺត', 'early_leave' => 'ចេញមុន', 'late_and_early_leave' => 'យឺត និងចេញមុន',
        'absent' => 'អវត្តមាន', 'incomplete' => 'មិនគ្រប់', 'leave' => 'ច្បាប់', 'mission' => 'បេសកកម្ម',
        'holiday' => 'ថ្ងៃបុណ្យ', 'day_off' => 'ថ្ងៃឈប់សម្រាក', 'in_progress' => 'កំពុងបន្ត'];
    $sessionNames = ['morning' => 'ព្រឹក', 'afternoon' => 'ល្ងាច', 'duty' => 'វេនយាម', 'work' => 'វេនធ្វើការ'];
    $logo = app_setting()->logo ?? null;
    $period = $from->isSameMonth($to) && $from->day === 1 && $to->isLastOfMonth()
        ? 'ខែ'.$months[$from->month - 1].' ឆ្នាំ'.$digits($from->year)
        : $date($from).' ដល់ '.$date($to);
@endphp
<div class="toolbar">
    <button type="button" onclick="window.print()">បោះពុម្ព / រក្សាទុកជា PDF</button>
</div>
<main class="sheet">
    <div class="report-head">
        @if($logo)<img src="{{ $logo }}" alt="">@endif
        <div>
            <h1>របាយការណ៍វត្តមានមន្ត្រី</h1>
            <div class="period">{{ $period }}</div>
        </div>
    </div>

    <div class="who">
        <div>ឈ្មោះ៖ <b>{{ $employee->full_name }}</b></div>
        <div>អត្តលេខ៖ <b>{{ $digits($employee->employee_id) }}</b></div>
        <div>មុខតំណែង៖ <b>{{ $position ?: '—' }}</b></div>
        <div>អង្គភាព៖ <b>{{ $unit ?: '—' }}</b></div>
    </div>

    <section class="summary">
        <div><strong>{{ $digits($summary['present']) }}</strong><span>ថ្ងៃមកធ្វើការ</span></div>
        <div><strong>{{ $digits($summary['late']) }}</strong><span>ដងមកយឺត ({{ $minutes($summary['late_minutes']) }})</span></div>
        <div><strong>{{ $digits($summary['early']) }}</strong><span>ដងចេញមុន ({{ $minutes($summary['early_minutes']) }})</span></div>
        <div><strong>{{ $digits($summary['absent']) }}</strong><span>ថ្ងៃអវត្តមាន</span></div>
        <div><strong>{{ $digits($summary['incomplete']) }}</strong><span>ថ្ងៃស្កេនមិនគ្រប់</span></div>
        <div><strong>{{ $digits($summary['leave']) }}</strong><span>ថ្ងៃច្បាប់</span></div>
        <div><strong>{{ $digits($summary['mission']) }}</strong><span>ថ្ងៃបេសកកម្ម</span></div>
        <div><strong>{{ $minutes($summary['worked_minutes']) }}</strong><span>ម៉ោងធ្វើការសរុប</span></div>
    </section>

    <div class="table-wrap">
        <table class="days">
            <thead>
                <tr><th>កាលបរិច្ឆេទ</th><th>ស្ថានភាព</th><th>វេន / ម៉ោងស្កេន</th><th>ម៉ោងធ្វើការ</th><th>យឺត</th><th>ចេញមុន</th></tr>
            </thead>
            <tbody>
            @forelse($days as $day)
                @php
                    $d = \Carbon\Carbon::parse($day['date']);
                    $code = $day['is_provisional'] && in_array($day['attendance_status'], ['absent', 'incomplete'], true) ? 'in_progress' : $day['attendance_status'];
                    $rest = in_array($code, ['holiday', 'day_off', 'leave', 'mission'], true);
                @endphp
                <tr class="{{ $rest ? 'rest' : '' }}">
                    <td class="left">{{ $weekdays[$d->dayOfWeekIso] }} {{ $digits($d->day) }}/{{ $digits($d->month) }}</td>
                    <td><span class="st st-{{ $code }}">{{ $labels[$code] ?? $code }}</span>
                        @if($code === 'holiday' && ! empty($day['holiday_name']))<div class="note">{{ $day['holiday_name'] }}</div>@endif</td>
                    <td class="left">
                        @forelse($day['sessions'] as $session)
                            @if(! $rest || $session['in_time'] || $session['out_time'])
                                <div>{{ $sessionNames[$session['name'] ?? 'work'] ?? ($session['name'] ?? '') }}៖ {{ $session['in_continued'] ?? false ? 'បន្ត' : $clock($session['in_time']) }} → {{ $session['out_continued'] ?? false ? 'បន្ត' : $clock($session['out_time']) }}</div>
                            @endif
                        @empty
                            {{ $day['in_time'] || $day['out_time'] ? $clock($day['in_time']).' → '.$clock($day['out_time']) : '—' }}
                        @endforelse
                        @if(! empty($day['adjustment_note']))<div class="note">កែតម្រូវ៖ {{ $day['adjustment_note'] }}</div>@endif
                    </td>
                    <td>{{ $minutes((int) $day['worked_minutes']) }}</td>
                    <td>{{ $minutes((int) $day['late_minutes']) }}</td>
                    <td>{{ $minutes((int) $day['early_leave_minutes']) }}</td>
                </tr>
            @empty
                <tr><td colspan="6">មិនមានទិន្នន័យវត្តមានក្នុងរយៈពេលនេះ</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="foot">ទាញពីកម្មវិធី PHD HRM នៅ {{ $date(now()) }} ម៉ោង {{ $digits(now()->format('H:i')) }} · ថ្ងៃខាងមុខមិនរាប់ជាអវត្តមានទេ។</div>

    <div class="signatures">
        <div>បានឃើញ និងឯកភាព<div class="signature-title">ប្រធានអង្គភាព</div><div class="space"></div></div>
        <div>ធ្វើនៅថ្ងៃទី.......ខែ.......ឆ្នាំ.......<div class="signature-title">មន្ត្រីរាយការណ៍</div><div class="space"></div><b>{{ $employee->full_name }}</b></div>
    </div>
</main>
</body>
</html>
