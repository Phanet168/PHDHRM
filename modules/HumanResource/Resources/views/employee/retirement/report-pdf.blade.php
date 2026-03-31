<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>{{ localize('retirement_report', 'របាយការណ៍មន្ត្រីចូលនិវត្តន៍') }}</title>
    @php
        $meta = $export_meta ?? [
            'admin_text' => 'រដ្ឋបាលខេត្តស្ទឹងត្រែង',
            'unit_text' => 'មន្ទីរសុខាភិបាល',
            'location_text' => 'ស្ទឹងត្រែង',
            'approval_text' => 'ប្រធានមន្ទីរសុខាភិបាល',
            'hr_manager_text' => 'មន្ត្រីគ្រប់គ្រងបុគ្គលិក',
        ];
        $displayUnitText = 'មន្ទីរសុខាភិបាលនៃរដ្ឋបាលខេត្ត';
        $logoFileUri = $logo_file_uri ?? null;
        $logoDataUri = $logo_data_uri ?? null;
        $logoSrc = $logoFileUri ?: $logoDataUri;

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

        $khmerHeadFontPath = storage_path('fonts/KhmerOSmuol.ttf');
        if (!is_file($khmerHeadFontPath)) {
            $khmerHeadFontPath = collect(glob(storage_path('fonts/khmerhead_normal_*.ttf')) ?: [])->first();
        }

        $khmerBodyFontUri = $fontToFileUri($khmerBodyFontPath);
        $khmerHeadFontUri = $fontToFileUri($khmerHeadFontPath);

        $toKhmerNumber = static function ($value): string {
            return strtr((string) $value, [
                '0' => '០',
                '1' => '១',
                '2' => '២',
                '3' => '៣',
                '4' => '៤',
                '5' => '៥',
                '6' => '៦',
                '7' => '៧',
                '8' => '៨',
                '9' => '៩',
            ]);
        };

        $formatDate = static function ($value): string {
            if (empty($value)) {
                return '-';
            }
            try {
                return \Carbon\Carbon::parse($value)->format('d/m/Y');
            } catch (\Throwable $e) {
                return (string) $value;
            }
        };

        $payLevelNameByCode = (array) ($pay_level_name_by_code ?? []);

        $normalizePayLevelCodeKey = static function (string $value): string {
            $clean = strtoupper(trim($value));
            $clean = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $clean) ?? $clean;
            $clean = preg_replace('/\s+/u', '', $clean) ?? $clean;
            return $clean;
        };

        $normalizePayLevelCodeCompactKey = static function (string $value) use ($normalizePayLevelCodeKey): string {
            $clean = $normalizePayLevelCodeKey($value);
            return preg_replace('/[^A-Z0-9]/', '', $clean) ?? '';
        };

        $findPayLevelLabelByCode = static function (array $map, string $raw) use ($normalizePayLevelCodeKey, $normalizePayLevelCodeCompactKey): string {
            $key = $normalizePayLevelCodeKey($raw);
            if ($key !== '' && isset($map[$key]) && trim((string) $map[$key]) !== '') {
                return trim((string) $map[$key]);
            }

            $compact = $normalizePayLevelCodeCompactKey($raw);
            if ($compact !== '' && isset($map[$compact]) && trim((string) $map[$compact]) !== '') {
                return trim((string) $map[$compact]);
            }

            return '';
        };

        $isLikelyPayLevelCode = static function (string $value) use ($normalizePayLevelCodeKey): bool {
            $clean = $normalizePayLevelCodeKey($value);
            return $clean !== '' && (bool) preg_match('/^[A-Z](?:[.\-]?\d+){1,3}$/', $clean);
        };

        $normalizePayLevelCodeToKhmer = static function (string $value): string {
            $clean = trim($value);
            if ($clean === '') {
                return '';
            }

            $letterMap = [
                'A' => 'ក',
                'B' => 'ខ',
                'C' => 'គ',
                'D' => 'ឃ',
                'E' => 'ង',
                'F' => 'ច',
                'G' => 'ឆ',
                'H' => 'ជ',
            ];

            $digitMap = [
                '0' => '០',
                '1' => '១',
                '2' => '២',
                '3' => '៣',
                '4' => '៤',
                '5' => '៥',
                '6' => '៦',
                '7' => '៧',
                '8' => '៨',
                '9' => '៩',
            ];

            return strtr(strtoupper($clean), $letterMap + $digitMap);
        };

        $getPayLevel = static function ($employee) use ($payLevelNameByCode, $findPayLevelLabelByCode, $isLikelyPayLevelCode, $normalizePayLevelCodeToKhmer): string {
            $activePayLevel = optional(optional($employee->currentPayGradeHistory)->payLevel);
            $latestPayLevel = optional(optional($employee->latestPayGradeHistory)->payLevel);

            $value = trim((string) (
                $activePayLevel->level_name_mk
                ?: $activePayLevel->level_name_km
                ?: $latestPayLevel->level_name_mk
                ?: $latestPayLevel->level_name_km
                ?: '-'
            ));

            if ($value !== '' && $value !== '-') {
                $mapped = $findPayLevelLabelByCode($payLevelNameByCode, $value);
                if ($mapped !== '') {
                    return $mapped;
                }
                if ($isLikelyPayLevelCode($value)) {
                    return $normalizePayLevelCodeToKhmer($value);
                }
                return $value;
            }

            $legacyCode = trim((string) ($employee->employee_grade ?: $employee->class_code ?: ''));
            if ($legacyCode !== '') {
                $mapped = $findPayLevelLabelByCode($payLevelNameByCode, $legacyCode);
                if ($mapped !== '') {
                    return $mapped;
                }
                return $normalizePayLevelCodeToKhmer($legacyCode);
            }

            return '-';
        };

        $today = \Carbon\Carbon::parse($as_of);
        $khmerMonths = [
            1 => 'មករា',
            2 => 'កុម្ភៈ',
            3 => 'មីនា',
            4 => 'មេសា',
            5 => 'ឧសភា',
            6 => 'មិថុនា',
            7 => 'កក្កដា',
            8 => 'សីហា',
            9 => 'កញ្ញា',
            10 => 'តុលា',
            11 => 'វិច្ឆិកា',
            12 => 'ធ្នូ',
        ];
        $monthKh = $khmerMonths[(int) $today->month] ?? '';
        $solarDateKh = sprintf(
            '%s, ថ្ងៃទី%s ខែ%s ឆ្នាំ %s',
            $meta['location_text'],
            $toKhmerNumber($today->format('d')),
            $monthKh,
            $toKhmerNumber($today->format('Y'))
        );

        $lunarDateKh = 'ថ្ងៃ........ ខែ........ ឆ្នាំ........ ពស........';
        try {
            $khmerDate = new \PPhatDev\LunarDate\KhmerDate($today->toDateString());
            $lunarDateKh = trim((string) $khmerDate->toLunarDate());
            $lunarDateKh = preg_replace('/^\s*ត្រូវ\s*នឹង\s*/u', '', $lunarDateKh) ?: $lunarDateKh;
            $lunarDateKh = str_replace(
                ['ពុទ្ធសករាជ', 'ព.ស.', 'ព.ស'],
                'ពស',
                $lunarDateKh
            );
        } catch (\Throwable $e) {
            // Keep fallback text.
        }
    @endphp
    <style>
        @if (!empty($khmerBodyFontUri))
        @font-face {
            font-family: "Khmer OS Siemreap";
            font-style: normal;
            font-weight: normal;
            src: url("{{ $khmerBodyFontUri }}") format("truetype");
        }
        @endif

        @if (!empty($khmerHeadFontUri))
        @font-face {
            font-family: "Khmer M1";
            font-style: normal;
            font-weight: normal;
            src: url("{{ $khmerHeadFontUri }}") format("truetype");
        }
        @endif

        @page {
            margin: 16px 16px 22px 16px;
        }

        body {
            font-family: "Khmer OS Siemreap", "khmerbody", "khmerfallback", "DejaVu Sans", sans-serif;
            font-size: 11px;
            line-height: 1.45;
            color: #111827;
        }

        .head-km {
            font-family: "Khmer M1", "khmerhead", "Khmer OS Siemreap", "khmerfallback", "DejaVu Sans", sans-serif;
            font-weight: normal;
            color: #002060;
        }

        .doc-header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .doc-header td {
            vertical-align: top;
        }

        .left-meta {
            width: 32%;
            text-align: left;
            padding-top: 42px;
        }

        .center-meta {
            width: 36%;
            text-align: center;
        }

        .right-meta {
            width: 32%;
            text-align: right;
        }

        .logo {
            width: 90px;
            height: 90px;
            object-fit: contain;
            margin-bottom: 6px;
        }

        .km-title {
            font-family: "Khmer M1", "khmerhead", "Khmer OS Siemreap", "khmerfallback", "DejaVu Sans", sans-serif;
            text-align: center;
            margin: 8px 0 8px;
            font-size: 14px;
            color: #002060;
        }

        .section-title {
            font-family: "Khmer M1", "khmerhead", "Khmer OS Siemreap", "khmerfallback", "DejaVu Sans", sans-serif;
            margin: 10px 0 6px;
            font-size: 12px;
            color: #002060;
        }

        table.grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            table-layout: fixed;
        }

        table.grid th,
        table.grid td {
            border: 1px solid #111827;
            padding: 4px 5px;
            vertical-align: top;
            word-break: break-word;
        }

        table.grid th {
            text-align: center;
            background: #f3f4f6;
            font-family: "Khmer M1", "khmerhead", "Khmer OS Siemreap", "khmerfallback", "DejaVu Sans", sans-serif;
            font-weight: 700;
            color: #002060;
        }

        table.grid td {
            font-family: "Khmer OS Siemreap", "khmerbody", "khmerfallback", "DejaVu Sans", sans-serif;
        }

        .text-center {
            text-align: center;
        }

        .no-data {
            text-align: center;
            color: #6b7280;
            padding: 8px;
        }

        .signature {
            width: 100%;
            border-collapse: collapse;
            margin-top: 24px;
        }

        .signature td {
            width: 50%;
            text-align: center;
            vertical-align: top;
            padding: 4px 8px;
        }

        .signature .space {
            height: 56px;
        }

        .signature .date-line {
            font-family: "Khmer OS Siemreap", "khmerbody", "khmerfallback", "DejaVu Sans", sans-serif;
            font-size: 11px;
            font-weight: 700;
            color: #002060;
            margin-bottom: 2px;
        }
    </style>
</head>
<body>
    <table class="doc-header">
        <tr>
            <td class="left-meta">
                @if (!empty($logoSrc))
                    <img src="{{ $logoSrc }}" alt="logo" class="logo">
                @endif
                <div class="head-km">{{ $meta['admin_text'] }}</div>
                <div class="head-km">{{ $displayUnitText }}</div>
            </td>
            <td class="center-meta">
                <div class="head-km" style="font-size:14px;">ព្រះរាជាណាចក្រកម្ពុជា</div>
                <div class="head-km" style="font-size:14px;">ជាតិ សាសនា ព្រះមហាក្សត្រ</div>
                <div class="head-km" style="font-size:11px;">♦♦♦</div>
            </td>
            <td class="right-meta">
                <div style="height:54px;"></div>
                <div class="head-km">&nbsp;</div>
            </td>
        </tr>
    </table>

    <div class="km-title head-km">
        តារាងបញ្ជីរាយនាមមន្ត្រីត្រូវចូលនិវត្តន៍ ឆ្នាំ{{ $toKhmerNumber($report_year) }}
    </div>

    <div class="section-title head-km">
        I. បញ្ជីមន្ត្រីត្រូវចូលនិវត្តន៍ក្នុងឆ្នាំ {{ $toKhmerNumber($report_year) }}
    </div>
    <table class="grid">
        <thead>
            <tr>
                <th style="width:4%;">ល.រ</th>
                <th style="width:11%;">លេខបុគ្គលិក</th>
                <th style="width:12%;">អត្តលេខមន្ត្រី (១០ខ្ទង់)</th>
                <th style="width:15%;">គោត្តនាម និងនាម</th>
                <th style="width:15%;">មុខតំណែង</th>
                <th style="width:10%;">កាំប្រាក់</th>
                <th style="width:16%;">អង្គភាព</th>
                <th style="width:8%;">ថ្ងៃខែឆ្នាំកំណើត</th>
                <th style="width:9%;">ថ្ងៃចូលនិវត្តន៍</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($yearly_employees as $employee)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td>{{ $employee->employee_id ?: '-' }}</td>
                    <td>{{ $employee->official_id_10 ?: '-' }}</td>
                    <td>{{ $employee->full_name ?: '-' }}</td>
                    <td>{{ $employee->position?->position_name_km ?: ($employee->position?->position_name ?: '-') }}</td>
                    <td>{{ $getPayLevel($employee) }}</td>
                    <td>{{ $employee->display_unit_path ?: ($employee->display_unit_name ?: '-') }}</td>
                    <td class="text-center">{{ $formatDate($employee->date_of_birth) }}</td>
                    <td class="text-center">{{ $formatDate($employee->retirement_date) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="no-data">មិនមានទិន្នន័យ</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title head-km">
        II. ព្យាករណ៍មន្ត្រីចូលនិវត្តន៍ {{ $toKhmerNumber($forecast_start_year) }} - {{ $toKhmerNumber($forecast_end_year) }}
    </div>
    <table class="grid">
        <thead>
            <tr>
                <th style="width:4%;">ល.រ</th>
                <th style="width:11%;">លេខបុគ្គលិក</th>
                <th style="width:20%;">ឈ្មោះមន្ត្រី</th>
                <th style="width:22%;">អង្គភាព</th>
                <th style="width:14%;">ថ្ងៃចូលនិវត្តន៍</th>
                <th style="width:10%;">ថ្ងៃនៅសល់</th>
                <th style="width:19%;">ឆ្នាំចូលនិវត្តន៍</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($forecast_employees as $employee)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td>{{ $employee->employee_id ?: '-' }}</td>
                    <td>{{ $employee->full_name ?: '-' }}</td>
                    <td>{{ $employee->display_unit_path ?: ($employee->display_unit_name ?: '-') }}</td>
                    <td class="text-center">{{ $formatDate($employee->retirement_date) }}</td>
                    <td class="text-center">{{ $employee->days_to_retirement }}</td>
                    <td class="text-center">{{ $toKhmerNumber(\Carbon\Carbon::parse($employee->retirement_date)->year) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="no-data">មិនមានទិន្នន័យ</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title head-km">III. បញ្ជីមន្ត្រីដែលបានចូលនិវត្តន៍រួច</div>
    <table class="grid">
        <thead>
            <tr>
                <th style="width:4%;">ល.រ</th>
                <th style="width:11%;">លេខបុគ្គលិក</th>
                <th style="width:18%;">ឈ្មោះមន្ត្រី</th>
                <th style="width:11%;">កាលបរិច្ឆេទ</th>
                <th style="width:14%;">ស្ថានភាពដើម</th>
                <th style="width:14%;">ស្ថានភាពថ្មី</th>
                <th style="width:28%;">ព័ត៌មានបន្ថែម</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($retired_records as $record)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td>{{ $record->employee?->employee_id ?: '-' }}</td>
                    <td>{{ $record->employee?->full_name ?: '-' }}</td>
                    <td class="text-center">{{ $formatDate($record->event_date) }}</td>
                    <td>{{ $record->from_value ?: '-' }}</td>
                    <td>{{ $record->to_value ?: '-' }}</td>
                    <td>{{ $record->details ?: '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="no-data">មិនមានទិន្នន័យ</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="signature">
        <tr>
            <td>&nbsp;</td>
            <td><div class="date-line">{{ $lunarDateKh }}</div></td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td><div class="date-line">{{ $solarDateKh }}</div></td>
        </tr>
        <tr>
            <td class="head-km">ឯកភាព</td>
            <td class="head-km">{{ $meta['hr_manager_text'] }}</td>
        </tr>
        <tr>
            <td class="head-km">{{ $meta['approval_text'] }}</td>
            <td class="space">&nbsp;</td>
        </tr>
    </table>
</body>
</html>
