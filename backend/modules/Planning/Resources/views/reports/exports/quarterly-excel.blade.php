@php
    $groupedRows = $rows->groupBy(function ($row) {
        $label = trim(($row->indicator_code ? $row->indicator_code . ' - ' : '') . ($row->indicator_name ?? ''));
        return $label !== '' ? $label : 'មិនមានសូចនាករ';
    });
    $headerLines = $orgHeaderLines ?? [$reportOrgName];
    $khmerYear = strtr((string) $reportYear, ['0' => '០', '1' => '១', '2' => '២', '3' => '៣', '4' => '៤', '5' => '៥', '6' => '៦', '7' => '៧', '8' => '៨', '9' => '៩']);
@endphp

<table>
    <tr><td colspan="2"></td><td colspan="7" style="text-align: center; font-family: 'Khmer M1';">ព្រះរាជាណាចក្រកម្ពុជា</td></tr>
    <tr>
        <td colspan="2" style="font-size: 11px;">{{ $headerLines[0] ?? 'មន្ទីរសុខាភិបាលនៃរដ្ឋបាលខេត្ត' }}</td>
        <td colspan="7" style="text-align: center; font-family: 'Khmer M1';">ជាតិ សាសនា ព្រះមហាក្សត្រ</td>
    </tr>
    @foreach (array_slice($headerLines, 1) as $line)
        <tr><td colspan="2" style="font-size: 11px;">{{ $line }}</td><td colspan="7"></td></tr>
    @endforeach
    <tr><td colspan="9"></td></tr>
    <tr><td colspan="9" style="text-align: center;">ផែនការសកម្មភាពប្រចាំត្រីមាស ឆ្នាំ {{ $khmerYear }}</td></tr>
    <tr><td colspan="9"></td></tr>
    <tr>
        <td style="text-align: center; border: 1px solid #000000;">សកម្មភាពដែលត្រូវអនុវត្ត</td>
        <td style="text-align: center; border: 1px solid #000000;">លទ្ធផលរំពឹងទុក</td>
        @foreach ($timelineQuarters as $quarter)
            <td style="text-align: center; border: 1px solid #000000;">{{ $quarter->label }}</td>
        @endforeach
        <td style="text-align: center; border: 1px solid #000000;">ថវិកា</td>
        <td style="text-align: center; border: 1px solid #000000;">ប្រភពថវិកា</td>
        <td style="text-align: center; border: 1px solid #000000;">អ្នកទទួលខុសត្រូវ</td>
    </tr>
    @forelse ($groupedRows as $indicatorLabel => $indicatorRows)
        <tr><td colspan="9" style="color: #124f2c; background-color: #eaf4ec; border: 1px solid #000000;">សូចនាករ៖ {{ $indicatorLabel }}</td></tr>
        @foreach ($indicatorRows as $row)
            <tr>
                <td style="border: 1px solid #000000;">{{ $row->activity_title }}</td>
                <td style="border: 1px solid #000000;">{{ $row->expected_results ?: '-' }}</td>
                @foreach ($timelineQuarters as $quarter)
                    <td style="text-align: center; border: 1px solid #000000;">{{ in_array($quarter->quarter, $row->activity_quarters ?? [], true) ? '√' : '' }}</td>
                @endforeach
                <td style="text-align: right; border: 1px solid #000000;">{{ number_format((float) $row->total_cost, 2) }}</td>
                <td style="border: 1px solid #000000;">{{ $row->funding_source_names ?: '-' }}</td>
                <td style="border: 1px solid #000000;">{{ $row->responsible_org_unit_name ?: '-' }}</td>
            </tr>
        @endforeach
    @empty
        <tr><td colspan="9" style="text-align: center; border: 1px solid #000000;">មិនមានរបាយការណ៍ផែនការសកម្មភាពប្រចាំត្រីមាសទេ។</td></tr>
    @endforelse
</table>
