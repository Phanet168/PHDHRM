<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>របាយការណ៍ប្រចាំថ្ងៃ</title>
    @php
        $fontToFileUri = static function (?string $path): ?string {
            if (!$path || !is_file($path)) {
                return null;
            }

            return 'file:///' . ltrim(str_replace('\\', '/', $path), '/');
        };

        $khmerBodyFontPath = storage_path('fonts/KhmerOSsiemreap.ttf');
        if (!is_file($khmerBodyFontPath)) {
            $khmerBodyFontPath = collect(glob(storage_path('fonts/khmerbody_normal_*.ttf')) ?: [])->first();
        }

        $khmerTitleFontPath = storage_path('fonts/khmer M1.volt.ttf');
        if (!is_file($khmerTitleFontPath)) {
            $khmerTitleFontPath = collect(glob(storage_path('fonts/*M1*.ttf')) ?: [])->first();
        }

        $khmerBodyFontUri = $fontToFileUri($khmerBodyFontPath);
        $khmerTitleFontUri = $fontToFileUri($khmerTitleFontPath);

        $groupedRows = $rows
            ->groupBy(function ($row) {
                $label = trim(($row->indicator_code ? $row->indicator_code . ' - ' : '') . ($row->indicator_name ?? ''));

                return $label !== '' ? $label : 'មិនមានសូចនាករ';
            });
        $rowNumber = 1;
        $currentUnitName = collect($orgHeaderLines ?? [$reportOrgName])->filter()->last() ?: $reportOrgName;
        $khmerYear = strtr((string) $reportYear, ['0' => '០', '1' => '១', '2' => '២', '3' => '៣', '4' => '៤', '5' => '៥', '6' => '៦', '7' => '៧', '8' => '៨', '9' => '៩']);
    @endphp
    <style>
        @page {
            size: A4 landscape;
            margin: 16mm 12mm 18mm 12mm;
        }

        @font-face {
            font-family: "Planning Khmer Body";
            src: url("{{ $khmerBodyFontUri }}") format("truetype");
        }

        @font-face {
            font-family: "Planning Khmer M1";
            src: url("{{ $khmerTitleFontUri }}") format("truetype");
        }

        body {
            margin: 0;
            color: #173b2d;
            font-size: 11px;
            font-family: "Planning Khmer Body", sans-serif;
        }

        table { width: 100%; border-collapse: collapse; }
        .header-table td, .sign-table td { border: none; padding: 2px 0; vertical-align: top; }
        .org-block {
            padding-top: 28px;
            font-size: 11px;
            line-height: 1.45;
        }
        .report-table th, .report-table td { border: 1px solid #000000; padding: 4px; vertical-align: middle; }
        .page-title {
            margin: 18px 0 12px;
            font-size: 18px;
            text-align: center;
            line-height: 1.55;
            padding-top: 4px;
            padding-bottom: 4px;
            font-family: "Planning Khmer M1", "Planning Khmer Body", sans-serif;
        }
        .kingdom-title {
            font-size: 18px;
            text-align: center;
            font-family: "Planning Khmer M1", "Planning Khmer Body", sans-serif;
        }
        .nation-line {
            font-size: 17px;
            text-align: center;
            font-family: "Planning Khmer M1", "Planning Khmer Body", sans-serif;
        }
        .month-band { background: #edf6ef; font-weight: 700; text-align: center; }
        .indicator-row td { background: #eef6f0; font-weight: 700; color: #124f2c; }
        .number-col { width: 34px; text-align: center; }
        .activity-col { width: 28%; }
        .desc-col { width: 20%; }
        .note-col { width: 7%; }
        .day-cell { width: 16px; text-align: center; padding: 3px 1px; }
        .weekend { background: #dbe8f5; }
        .inactive-day { background: #eef2f5; color: #8f99a5; }
        .day-hit { font-weight: 700; }
        .text-center { text-align: center; }
        .title-font { font-family: "Planning Khmer M1", "Planning Khmer Body", sans-serif; }
        .org-title-font {
            font-family: "Planning Khmer M1", "Planning Khmer Body", sans-serif;
            font-size: 11px;
        }
        .footer-title {
            font-family: "Planning Khmer M1", "Planning Khmer Body", sans-serif;
            text-align: center;
        }
        .footer-role {
            font-family: "Planning Khmer M1", "Planning Khmer Body", sans-serif;
            text-align: center;
        }
        .footer-lunar {
            font-family: "Planning Khmer Body", sans-serif;
            text-align: center;
        }
    </style>
</head>
<body>
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

    <div class="page-title">ផែនការសកម្មភាពប្រចាំថ្ងៃ ខែ{{ $monthLabel }} ឆ្នាំ {{ $khmerYear }}</div>

    <table class="report-table">
        <thead>
            <tr>
                <th rowspan="2" class="number-col">ល.រ</th>
                <th rowspan="2" class="activity-col">សកម្មភាពដែលត្រូវអនុវត្ត</th>
                <th rowspan="2" class="desc-col">បរិយាយសកម្មភាព</th>
                <th colspan="{{ $timelineDays->count() }}" class="month-band">កាលបរិច្ឆេទ</th>
                <th rowspan="2" class="note-col">សម្គាល់</th>
            </tr>
            <tr>
                @foreach ($timelineDays as $day)
                    @php
                        $isActualDay = $day->is_actual_day ?? true;
                        $isWeekend = $isActualDay && in_array($day->dayOfWeekIso, [6, 7], true);
                    @endphp
                    <th class="day-cell {{ $isWeekend ? 'weekend' : '' }} {{ !$isActualDay ? 'inactive-day' : '' }}">{{ $day->day }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($groupedRows as $indicatorLabel => $indicatorRows)
                <tr class="indicator-row">
                    <td colspan="{{ 4 + $timelineDays->count() }}">សូចនាករ៖ {{ $indicatorLabel }}</td>
                </tr>
                @foreach ($indicatorRows as $row)
                    <tr>
                        <td class="text-center">{{ $rowNumber++ }}</td>
                        <td>{{ $row->activity_title }}</td>
                        <td>{{ $row->goal_text ?: '-' }}</td>
                        @foreach ($timelineDays as $day)
                            @php
                                $isActualDay = $day->is_actual_day ?? true;
                                $isSelected = in_array($day->day, $row->activity_day_numbers, true);
                                $isWeekend = $isActualDay && in_array($day->dayOfWeekIso, [6, 7], true);
                            @endphp
                            <td class="day-cell {{ $isWeekend ? 'weekend' : '' }} {{ !$isActualDay ? 'inactive-day' : '' }} {{ $isSelected ? 'day-hit' : '' }}">{{ $isSelected ? '√' : '' }}</td>
                        @endforeach
                        <td></td>
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="{{ 4 + $timelineDays->count() }}" class="text-center">មិនមានរបាយការណ៍ផែនការសកម្មភាពប្រចាំថ្ងៃទេ។</td>
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
            <td class="text-center">ថ្ងៃទី......... ខែ............ ឆ្នាំ.........</td>
            <td class="text-center">ថ្ងៃទី......... ខែ............ ឆ្នាំ.........</td>
            <td class="text-center">ស្ទឹងត្រែង ថ្ងៃទី......... ខែ............ ឆ្នាំ២០២៦</td>
        </tr>
        <tr>
            <td class="footer-role">ប្រធានមន្ទីរ</td>
            <td class="footer-role">ប្រធាន{{ $currentUnitName }}</td>
            <td class="footer-role">អ្នករៀបចំផែនការ</td>
        </tr>
        <tr><td colspan="3" style="height: 56px;"></td></tr>
        <tr>
            <td class="text-center"><strong>....................................</strong></td>
            <td class="text-center"><strong>....................................</strong></td>
            <td class="text-center"><strong>....................................</strong></td>
        </tr>
    </table>
</body>
</html>
