@php
    $groupedRows = $rows->groupBy(function ($row) {
        $label = trim(($row->indicator_code ? $row->indicator_code . ' - ' : '') . ($row->indicator_name ?? ''));

        return $label !== '' ? $label : 'មិនមានសូចនាករ';
    });

    $rowNumber = 1;
    $headerLines = $orgHeaderLines ?? [$reportOrgName];
    $currentUnitName = collect($headerLines)->filter()->last() ?: $reportOrgName;
    $khmerYear = strtr((string) $reportYear, ['0' => '០', '1' => '១', '2' => '២', '3' => '៣', '4' => '៤', '5' => '៥', '6' => '៦', '7' => '៧', '8' => '៨', '9' => '៩']);
    $totalColumns = 5 + $timelineDays->count();
    $leftBlockSpan = 10;
    $leftGapSpan = 2;
    $centerBlockSpan = 10;
    $centerGapSpan = 2;
    $rightBlockSpan = max($totalColumns - $leftBlockSpan - $leftGapSpan - $centerBlockSpan - $centerGapSpan, 12);
@endphp

<table>
    <tr>
        <td colspan="{{ $leftBlockSpan }}"></td>
        <td colspan="{{ $centerBlockSpan }}"></td>
        <td colspan="{{ $rightBlockSpan }}" style="text-align: center; font-family: 'Khmer M1';">ព្រះរាជាណាចក្រកម្ពុជា</td>
    </tr>
    <tr>
        <td colspan="{{ $leftBlockSpan }}" style="font-size: 11px;">{{ $headerLines[0] ?? 'មន្ទីរសុខាភិបាលនៃរដ្ឋបាលខេត្ត' }}</td>
        <td colspan="{{ $centerBlockSpan }}"></td>
        <td colspan="{{ $rightBlockSpan }}" style="text-align: center; font-family: 'Khmer M1';">ជាតិ សាសនា ព្រះមហាក្សត្រ</td>
    </tr>
    @foreach (array_slice($headerLines, 1) as $line)
        <tr>
            <td colspan="{{ $leftBlockSpan }}" style="font-size: 11px;">{{ $line }}</td>
            <td colspan="{{ $centerBlockSpan + $rightBlockSpan }}"></td>
        </tr>
    @endforeach

    <tr>
        <td colspan="{{ $totalColumns }}"></td>
    </tr>
    <tr>
        <td colspan="{{ $totalColumns }}" style="text-align: center;">ផែនការសកម្មភាពប្រចាំថ្ងៃ ខែ{{ $monthLabel }} ឆ្នាំ {{ $khmerYear }}</td>
    </tr>
    <tr>
        <td colspan="{{ $totalColumns }}"></td>
    </tr>

    <tr>
        <td rowspan="2" style="text-align: center; vertical-align: middle; border: 1px solid #000000;">ល.រ</td>
        <td rowspan="2" style="text-align: center; vertical-align: middle; border: 1px solid #000000;">សកម្មភាពដែលត្រូវអនុវត្ត</td>
        <td rowspan="2" style="text-align: center; vertical-align: middle; border: 1px solid #000000;">បរិយាយសកម្មភាព</td>
        <td colspan="{{ $timelineDays->count() }}" style="text-align: center; border: 1px solid #000000;">កាលបរិច្ឆេទ</td>
        <td rowspan="2" style="text-align: center; vertical-align: middle; border: 1px solid #000000;">សម្គាល់</td>
    </tr>
    <tr>
        @foreach ($timelineDays as $day)
            @php
                $isActualDay = $day->is_actual_day ?? true;
                $isWeekend = $isActualDay && in_array($day->dayOfWeekIso, [6, 7], true);
                $backgroundColor = !$isActualDay ? '#eef2f5' : ($isWeekend ? '#dbe8f5' : '#ffffff');
                $textColor = !$isActualDay ? '#8f99a5' : '#000000';
            @endphp
            <td style="text-align: center; border: 1px solid #000000; background-color: {{ $backgroundColor }}; color: {{ $textColor }};">{{ $day->day }}</td>
        @endforeach
    </tr>

    @forelse ($groupedRows as $indicatorLabel => $indicatorRows)
        <tr>
            <td colspan="{{ $totalColumns }}" style="color: #124f2c; background-color: #eaf4ec; border: 1px solid #000000;">
                សូចនាករ៖ {{ $indicatorLabel }}
            </td>
        </tr>
        @foreach ($indicatorRows as $row)
            <tr>
                <td style="text-align: center; border: 1px solid #000000;">{{ $rowNumber++ }}</td>
                <td style="border: 1px solid #000000;">{{ $row->activity_title }}</td>
                <td style="border: 1px solid #000000;">{{ $row->goal_text ?: '-' }}</td>
                @foreach ($timelineDays as $day)
                    @php
                        $isActualDay = $day->is_actual_day ?? true;
                        $isSelected = in_array($day->day, $row->activity_day_numbers, true);
                        $isWeekend = $isActualDay && in_array($day->dayOfWeekIso, [6, 7], true);
                        $backgroundColor = !$isActualDay ? '#eef2f5' : ($isWeekend ? '#dbe8f5' : '#ffffff');
                    @endphp
                    <td style="text-align: center; border: 1px solid #000000; background-color: {{ $backgroundColor }};">
                        {{ $isSelected ? '√' : '' }}
                    </td>
                @endforeach
                <td style="border: 1px solid #000000;"></td>
            </tr>
        @endforeach
    @empty
        <tr>
            <td colspan="{{ $totalColumns }}" style="text-align: center; border: 1px solid #000000;">
                មិនមានរបាយការណ៍ផែនការសកម្មភាពប្រចាំថ្ងៃទេ។
            </td>
        </tr>
    @endforelse
</table>

<table>
    <tr>
        <td colspan="{{ $leftBlockSpan }}"></td>
        <td colspan="{{ $leftGapSpan }}"></td>
        <td colspan="{{ $centerBlockSpan }}"></td>
        <td colspan="{{ $centerGapSpan }}"></td>
        <td colspan="{{ $rightBlockSpan }}"></td>
    </tr>
    <tr>
        <td colspan="{{ $leftBlockSpan }}" style="text-align: center;">បានឃើញ និង..............</td>
        <td colspan="{{ $leftGapSpan }}"></td>
        <td colspan="{{ $centerBlockSpan }}" style="text-align: center;">បានឃើញ និង..............</td>
        <td colspan="{{ $centerGapSpan }}"></td>
        <td colspan="{{ $rightBlockSpan }}" style="text-align: center;">ថ្ងៃ.......................ខែ.................ឆ្នាំមមី អដ្ឋស័ក ព.ស.២៥៧០</td>
    </tr>
    <tr>
        <td colspan="{{ $leftBlockSpan }}" style="text-align: center;">ថ្ងៃទី......... ខែ............ ឆ្នាំ.........</td>
        <td colspan="{{ $leftGapSpan }}"></td>
        <td colspan="{{ $centerBlockSpan }}" style="text-align: center;">ថ្ងៃទី......... ខែ............ ឆ្នាំ.........</td>
        <td colspan="{{ $centerGapSpan }}"></td>
        <td colspan="{{ $rightBlockSpan }}" style="text-align: center;">ស្ទឹងត្រែង ថ្ងៃទី......... ខែ............ ឆ្នាំ{{ $khmerYear }}</td>
    </tr>
    <tr>
        <td colspan="{{ $leftBlockSpan }}" style="text-align: center;">ប្រធានមន្ទីរ</td>
        <td colspan="{{ $leftGapSpan }}"></td>
        <td colspan="{{ $centerBlockSpan }}" style="text-align: center;">ប្រធាន{{ $currentUnitName }}</td>
        <td colspan="{{ $centerGapSpan }}"></td>
        <td colspan="{{ $rightBlockSpan }}" style="text-align: center;">អ្នករៀបចំផែនការ</td>
    </tr>
</table>
