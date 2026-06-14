<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>របាយការណ៍ប្រចាំខែ</title>
    <style>
        body { font-family: khmerbody, khmerfallback, "DejaVu Sans", sans-serif; font-size: 10px; color: #173b2d; }
        table { width: 100%; border-collapse: collapse; }
        .header-table td, .sign-table td { border: none; padding: 2px 0; vertical-align: top; }
        .org-block { padding-top: 28px; font-size: 11px; line-height: 1.45; }
        .report-table th, .report-table td { border: 1px solid #000000; padding: 4px; vertical-align: middle; }
        .page-title { margin: 18px 0 12px; font-size: 16px; font-weight: 700; text-align: center; line-height: 1.55; padding-top: 4px; padding-bottom: 4px; font-family: "Khmer M1", khmerhead, khmerbody, khmerfallback, "DejaVu Sans", sans-serif; }
        .kingdom-title { font-size: 17px; font-weight: 700; text-align: center; font-family: "Khmer M1", khmerhead, khmerbody, khmerfallback, "DejaVu Sans", sans-serif; }
        .nation-line { font-size: 16px; font-weight: 700; text-align: center; font-family: "Khmer M1", khmerhead, khmerbody, khmerfallback, "DejaVu Sans", sans-serif; }
        .month-band { background: #edf6ef; font-weight: 700; text-align: center; }
        .indicator-row td { background: #eef6f0; font-weight: 700; color: #124f2c; }
        .activity-col { width: 34%; }
        .note-col { width: 22%; }
        .month-cell { width: 32px; text-align: center; padding: 3px 1px; }
        .month-hit { font-weight: 700; }
        .footer-title, .footer-role { font-family: "Khmer M1", khmerhead, khmerbody, khmerfallback, "DejaVu Sans", sans-serif; text-align: center; }
        .footer-lunar { font-family: khmerbody, khmerfallback, "DejaVu Sans", sans-serif; text-align: center; }
        .org-title-font { font-family: "Khmer M1", khmerhead, khmerbody, khmerfallback, "DejaVu Sans", sans-serif; font-size: 11px; }
    </style>
</head>
<body>
@php
    $groupedRows = $rows->groupBy(function ($row) {
        $label = trim(($row->indicator_code ? $row->indicator_code . ' - ' : '') . ($row->indicator_name ?? ''));
        return $label !== '' ? $label : 'មិនមានសូចនាករ';
    });
    $currentUnitName = collect($orgHeaderLines ?? [$reportOrgName])->filter()->last() ?: $reportOrgName;
    $khmerYear = strtr((string) $reportYear, ['0' => '០', '1' => '១', '2' => '២', '3' => '៣', '4' => '៤', '5' => '៥', '6' => '៦', '7' => '៧', '8' => '៨', '9' => '៩']);
@endphp

<table class="header-table">
    <tr>
        <td style="width: 50%;"></td>
        <td style="width: 50%; padding-left: 40px;" class="kingdom-title">ព្រះរាជាណាចក្រកម្ពុជា</td>
    </tr>
    <tr>
        <td class="org-block org-title-font">
            @foreach (($orgHeaderLines ?? [$reportOrgName]) as $line)
                <div @if(!$loop->first) style="margin-top: 6px;" @endif>{{ $line }}</div>
            @endforeach
        </td>
        <td style="padding-left: 40px;" class="nation-line">ជាតិ សាសនា ព្រះមហាក្សត្រ</td>
    </tr>
</table>

<div class="page-title">ផែនការសកម្មភាពប្រចាំខែ ឆ្នាំ {{ $khmerYear }}</div>

<table class="report-table">
    <thead>
    <tr>
        <th rowspan="2" class="activity-col">សកម្មភាពដែលត្រូវអនុវត្ត</th>
        <th rowspan="2" class="note-col">កំណត់ចំណាំ</th>
        <th colspan="{{ $timelineMonths->count() }}" class="month-band">ខែ</th>
    </tr>
    <tr>
        @foreach ($timelineMonths as $month)
            <th class="month-cell">{{ $month->label }}</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @forelse ($groupedRows as $indicatorLabel => $indicatorRows)
        <tr class="indicator-row">
            <td colspan="{{ 2 + $timelineMonths->count() }}">សូចនាករ៖ {{ $indicatorLabel }}</td>
        </tr>
        @foreach ($indicatorRows as $row)
            <tr>
                <td>{{ $row->activity_title }}</td>
                <td>{{ $row->notes ?: '-' }}</td>
                @foreach ($timelineMonths as $month)
                    @php $isSelected = in_array($month->month, $row->activity_month_numbers ?? [], true); @endphp
                    <td class="month-cell">{{ $isSelected ? '√' : '' }}</td>
                @endforeach
            </tr>
        @endforeach
    @empty
        <tr>
            <td colspan="{{ 2 + $timelineMonths->count() }}" style="text-align: center;">មិនមានរបាយការណ៍ផែនការសកម្មភាពប្រចាំខែទេ។</td>
        </tr>
    @endforelse
    </tbody>
</table>

<table class="sign-table" style="margin-top: 28px;">
    <tr>
        <td class="footer-title">បានឃើញ និង..............</td>
        <td class="footer-title">បានឃើញ និង..............</td>
        <td class="footer-lunar">ថ្ងៃ.......................ខែ.................ឆ្នាំមមី អដ្ឋស័ក ព.ស.២៥៧០</td>
    </tr>
    <tr>
        <td style="text-align: center;">ថ្ងៃទី......... ខែ............ ឆ្នាំ.........</td>
        <td style="text-align: center;">ថ្ងៃទី......... ខែ............ ឆ្នាំ.........</td>
        <td style="text-align: center;">ស្ទឹងត្រែង ថ្ងៃទី......... ខែ............ ឆ្នាំ{{ $khmerYear }}</td>
    </tr>
    <tr>
        <td class="footer-role">ប្រធានមន្ទីរ</td>
        <td class="footer-role">ប្រធាន{{ $currentUnitName }}</td>
        <td class="footer-role">អ្នករៀបចំផែនការ</td>
    </tr>
</table>
</body>
</html>
