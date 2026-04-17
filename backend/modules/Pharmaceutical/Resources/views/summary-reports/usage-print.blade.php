<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ localize('medicine_usage_report', 'Medicine Usage Report') }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Khmer OS Battambang', 'Khmer OS', 'Noto Sans Khmer', sans-serif;
            font-size: 11px; color: #000; background: #fff; padding: 8mm;
        }
        .report-page { max-width: 190mm; margin: 0 auto; }
        .report-header { text-align: center; margin-bottom: 10px; border-bottom: 2px solid #000; padding-bottom: 6px; }
        .report-header .facility-name { font-size: 14px; font-weight: bold; margin-bottom: 2px; }
        .report-header .report-title { font-size: 16px; font-weight: bold; margin-top: 4px; }
        .report-header .sub-info { font-size: 10px; color: #444; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 11px; }
        .items-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .items-table th, .items-table td { border: 1px solid #000; padding: 3px 5px; }
        .items-table th { background: #f0f0f0; font-weight: 600; text-align: center; font-size: 10px; }
        .items-table td { font-size: 10px; }
        .items-table .text-end { text-align: right; }
        .items-table .text-center { text-align: center; }
        .items-table .med-kh { font-size: 9px; color: #444; }
        .items-table tfoot td { font-weight: 700; background: #f8f8f8; }
        .sig-area { margin-top: 20px; display: flex; justify-content: space-between; }
        .sig-block { text-align: center; width: 40%; }
        .sig-line { margin-top: 40px; border-top: 1px dotted #000; padding-top: 3px; font-size: 10px; }
        .print-date { text-align: right; font-size: 8px; color: #888; margin-top: 10px; }
        .no-print { text-align: center; margin-bottom: 12px; }
        .no-print button { padding: 8px 24px; font-size: 14px; cursor: pointer; background: #1a6e3e; color: #fff; border: none; border-radius: 4px; }
        .no-print button:hover { background: #145530; }
        .no-print .btn-close-page { background: #666; margin-left: 8px; }
        @media print { body { padding: 0; margin: 0; } .no-print { display: none !important; } .report-page { max-width: 100%; } }
        @page { size: A4 portrait; margin: 10mm; }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">&#128424; {{ localize('print', 'Print') }}</button>
        <button class="btn-close-page" onclick="window.close()">{{ localize('close', 'Close') }}</button>
    </div>

    <div class="report-page">
        <div class="report-header">
            <div class="facility-name">{{ $facilityName ?? ($facility?->department_name) }}</div>
            <div class="sub-info">{{ localize('ministry_of_health', 'Ministry of Health') }} – {{ localize('kingdom_of_cambodia', 'Kingdom of Cambodia') }}</div>
            <div class="report-title">{{ localize('medicine_usage_report', 'របាយការណ៍ប្រើប្រាស់ឱសថ') }}</div>
        </div>

        <div class="info-row">
            <div><strong>{{ localize('facility', 'Facility') }}:</strong> {{ $facilityName ?? ($facility?->department_name) }}</div>
            <div><strong>{{ localize('period', 'Period') }}:</strong> {{ \Carbon\Carbon::parse($periodStart)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($periodEnd)->format('d/m/Y') }}</div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width:25px">#</th>
                    <th>{{ localize('medicine', 'Medicine') }}</th>
                    <th>{{ localize('unit', 'Unit') }}</th>
                    <th>{{ localize('total_dispensed', 'បរិមាណប្រើប្រាស់') }}</th>
                    <th>{{ localize('dispense_count', 'ចំនួនលើកចេញ') }}</th>
                    <th>{{ localize('patient_count', 'ចំនួនអ្នកជំងឺ') }}</th>
                    <th>{{ localize('current_stock', 'ស្តុកបច្ចុប្បន្ន') }}</th>
                    <th>{{ localize('avg_per_dispense', 'មធ្យម/លើក') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $idx => $item)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>
                        <strong>{{ $item['medicine_code'] }}</strong> – {{ $item['medicine_name'] }}
                        @if($item['medicine_name_kh'])
                            <br><span class="med-kh">{{ $item['medicine_name_kh'] }}</span>
                        @endif
                    </td>
                    <td class="text-center">{{ $item['unit'] }}</td>
                    <td class="text-end" style="font-weight:700">{{ number_format($item['total_qty'], 2) }}</td>
                    <td class="text-end">{{ $item['dispense_count'] }}</td>
                    <td class="text-end">{{ $item['patient_count'] }}</td>
                    <td class="text-end">{{ number_format($item['current_stock'], 2) }}</td>
                    <td class="text-end">{{ $item['dispense_count'] > 0 ? number_format($item['total_qty'] / $item['dispense_count'], 2) : '-' }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" style="text-align:right;"><strong>{{ localize('total', 'Total') }}</strong></td>
                    <td class="text-end">{{ number_format($totals['total_qty'], 2) }}</td>
                    <td class="text-end">{{ $totals['dispense_count'] }}</td>
                    <td class="text-end">{{ $totals['patient_count'] }}</td>
                    <td class="text-end">{{ number_format($totals['current_stock'], 2) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        <div class="sig-area">
            <div class="sig-block">
                <div class="sig-line">{{ localize('prepared_by', 'Prepared by') }}<br><small>________________</small></div>
            </div>
            <div class="sig-block">
                <div class="sig-line">{{ localize('verified_by', 'Verified by') }}<br><small>________________</small></div>
            </div>
        </div>

        <div class="print-date">{{ localize('printed_on', 'Printed on') }}: {{ now()->format('d/m/Y H:i') }}</div>
    </div>
</body>
</html>
