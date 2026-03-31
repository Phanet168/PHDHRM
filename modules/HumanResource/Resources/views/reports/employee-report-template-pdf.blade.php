<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>{{ localize('employee_report_management', 'របាយការណ៍បុគ្គលិក') }}</title>
    @php
        $meta = $meta ?? [
            'admin_text' => 'រដ្ឋបាលខេត្តស្ទឹងត្រែង',
            'unit_text' => 'មន្ទីរសុខាភិបាលនៃរដ្ឋបាលខេត្ត',
            'title_text' => 'តារាងរបាយការណ៍បុគ្គលិក',
            'location_text' => 'ស្ទឹងត្រែង',
            'approval_text' => 'ប្រធានមន្ទីរសុខាភិបាល',
            'hr_manager_text' => 'មន្ត្រីគ្រប់គ្រងបុគ្គលិក',
        ];
        $displayUnitText = 'មន្ទីរសុខាភិបាលនៃរដ្ឋបាលខេត្ត';

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

        $today = \Carbon\Carbon::today();
        $months = [
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
        $solarDateKh = sprintf(
            '%s ថ្ងៃទី%s ខែ%s ឆ្នាំ %s',
            $meta['location_text'] ?? 'ស្ទឹងត្រែង',
            $toKhmerNumber($today->format('d')),
            $months[(int) $today->month] ?? '',
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
        } catch (\Throwable $exception) {
            // Keep fallback text.
        }

        $logoPath = null;
        try {
            $app = \Modules\Setting\Entities\Application::find(1);
            $logo = trim((string) ($app->logo ?? ''));
            if ($logo !== '') {
                $clean = ltrim(str_replace('\\', '/', $logo), '/');
                $candidate = public_path('storage/' . $clean);
                if (is_file($candidate)) {
                    $logoPath = 'file:///' . ltrim(str_replace('\\', '/', $candidate), '/');
                }
            }
        } catch (\Throwable $exception) {
            $logoPath = null;
        }
        $logoSrc = $logoPath;
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

        @page { margin: 16px 16px 22px 16px; }
        body { font-family: "Khmer OS Siemreap", "khmerbody", "khmerfallback", "DejaVu Sans", sans-serif; font-size: 11px; line-height: 1.45; color: #111827; }
        .head-km { font-family: "Khmer M1", "khmerhead", "Khmer OS Siemreap", "khmerfallback", "DejaVu Sans", sans-serif; color: #002060; }

        .doc-header { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .doc-header td { vertical-align: top; }
        .left-meta { width: 32%; text-align: left; padding-top: 26px; }
        .center-meta { width: 36%; text-align: center; padding-top: 0; }
        .right-meta { width: 32%; text-align: right; }
        .left-block { width: 230px; max-width: 230px; }
        .logo-wrap { width: 100%; text-align: center; }
        .logo { width: 64px; height: 64px; margin-bottom: 6px; margin-left: -3cm; display: inline-block; }
        .left-meta .logo + .head-km { margin-top: 8px; }
        .left-meta .head-km { line-height: 1.35; text-align: left; }
        .km-title {
            font-family: "Khmer M1", "khmerhead", "Khmer OS Siemreap", "khmerfallback", "DejaVu Sans", sans-serif;
            text-align: center;
            margin: 4px 0 8px;
            font-size: 14px;
            color: #002060;
        }

        table.grid { width: 100%; border-collapse: collapse; margin-bottom: 10px; table-layout: fixed; }
        table.grid th, table.grid td { border: 1px solid #111827; padding: 4px 5px; vertical-align: top; word-break: break-word; }
        table.grid th {
            text-align: center;
            background: #f3f4f6;
            font-family: "Khmer M1", "khmerhead", "Khmer OS Siemreap", "khmerfallback", "DejaVu Sans", sans-serif;
            font-weight: 400;
            color: #002060;
        }
        table.grid td {
            font-family: "Khmer OS Siemreap", "khmerbody", "khmerfallback", "DejaVu Sans", sans-serif;
        }
        .summary { margin-bottom: 10px; }
        .summary td { border: 1px solid #9ca3af; padding: 4px 6px; }
        .signature { width: 100%; border-collapse: collapse; margin-top: 24px; }
        .signature td { width: 50%; text-align: center; vertical-align: top; padding: 4px 8px; }
        .signature .space { height: 56px; }
        .signature .date-line {
            font-family: "Khmer OS Siemreap", "khmerbody", "khmerfallback", "DejaVu Sans", sans-serif;
            font-size: 11px;
            font-weight: 700;
            color: #002060;
            margin-bottom: 2px;
        }
        .muted { color: #4b5563; }
    </style>
</head>
<body>
    <table class="doc-header">
        <tr>
            <td class="left-meta">
                <div class="left-block">
                    @if (!empty($logoSrc))
                        <div class="logo-wrap">
                            <img src="{{ $logoSrc }}" alt="logo" class="logo" width="64" height="64">
                        </div>
                    @endif
                    <div class="head-km">{{ $meta['admin_text'] ?? '' }}</div>
                    <div class="head-km">{{ $displayUnitText }}</div>
                </div>
            </td>
            <td class="center-meta">
                <div class="head-km" style="font-size:14px;">ព្រះរាជាណាចក្រកម្ពុជា</div>
                <div class="head-km" style="font-size:14px;">ជាតិ សាសនា ព្រះមហាក្សត្រ</div>
                <div class="head-km" style="font-size:11px;">♦♦♦</div>
            </td>
            <td class="right-meta">
                <div style="height:0;"></div>
                <div class="head-km">&nbsp;</div>
            </td>
        </tr>
    </table>

    <div class="km-title head-km">{{ $meta['title_text'] ?? localize('employee_report_management', 'តារាងរបាយការណ៍បុគ្គលិក') }}</div>

    @if (!empty($group_label) && !empty($grouped_summary) && count($grouped_summary) > 0)
        <table class="summary" width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <td class="head-km" colspan="2">សង្ខេបតាមក្រុម: {{ $group_label }}</td>
            </tr>
            @foreach ($grouped_summary as $summary)
                <tr>
                    <td>{{ $summary['group_label'] ?? '-' }}</td>
                    <td width="22%">{{ $summary['total'] ?? 0 }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <table class="grid">
        <thead>
            <tr>
                @foreach ($selected_columns as $column)
                    <th>{{ $column_options[$column] ?? $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($selected_columns as $column)
                        <td>{{ $row[$column] ?? '' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($selected_columns) ?: 1 }}" style="text-align:center;" class="muted">{{ localize('no_data_found', 'មិនមានទិន្នន័យ') }}</td>
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
            <td class="head-km">{{ $meta['hr_manager_text'] ?? 'មន្ត្រីគ្រប់គ្រងបុគ្គលិក' }}</td>
        </tr>
        <tr>
            <td class="head-km">{{ $meta['approval_text'] ?? 'ប្រធានមន្ទីរសុខាភិបាល' }}</td>
            <td class="space">&nbsp;</td>
        </tr>
    </table>
</body>
</html>
