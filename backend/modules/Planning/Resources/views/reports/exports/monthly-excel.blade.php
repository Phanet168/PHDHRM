@php
    $groupedRows = $rows->groupBy(function ($row) {
        $label = trim(($row->indicator_code ? $row->indicator_code . ' - ' : '') . ($row->indicator_name ?? ''));

        return $label !== '' ? $label : 'មិនមានសូចនាករ';
    });

    $headerLines = $orgHeaderLines ?? [$reportOrgName];
    $currentUnitName = collect($headerLines)->filter()->last() ?: $reportOrgName;
    $khmerYear = strtr((string) $reportYear, ['0' => '០', '1' => '១', '2' => '២', '3' => '៣', '4' => '៤', '5' => '៥', '6' => '៦', '7' => '៧', '8' => '៨', '9' => '៩']);
    $totalColumns = 3 + $timelineMonths->count();
    $leftBlockSpan = 2;   // A:B
    $centerBlockSpan = 7; // C:I
    $rightBlockSpan = 6;  // J:O
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
        <td colspan="{{ $totalColumns }}" style="text-align: center;">ផែនការសកម្មភាពប្រចាំខែ ឆ្នាំ {{ $khmerYear }}</td>
    </tr>
    <tr>
        <td colspan="{{ $totalColumns }}"></td>
    </tr>

    <tr>
        <td style="text-align: center; vertical-align: middle; border: 1px solid #000000;">សកម្មភាពដែលត្រូវអនុវត្ត</td>
        <td style="text-align: center; vertical-align: middle; border: 1px solid #000000;">កំណត់ចំណាំ</td>
        @foreach ($timelineMonths as $month)
            <td style="text-align: center; vertical-align: middle; border: 1px solid #000000;">{{ $month->label }}</td>
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
                <td style="border: 1px solid #000000;">{{ $row->activity_title }}</td>
                <td style="border: 1px solid #000000;">{{ $row->notes ?: '-' }}</td>
                @foreach ($timelineMonths as $month)
                    @php $isSelected = in_array($month->month, $row->activity_month_numbers ?? [], true); @endphp
                    <td style="text-align: center; border: 1px solid #000000;">{{ $isSelected ? '√' : '' }}</td>
                @endforeach
            </tr>
        @endforeach
    @empty
        <tr>
            <td colspan="{{ $totalColumns }}" style="text-align: center; border: 1px solid #000000;">
                មិនមានរបាយការណ៍ផែនការសកម្មភាពប្រចាំខែទេ។
            </td>
        </tr>
    @endforelse
</table>
