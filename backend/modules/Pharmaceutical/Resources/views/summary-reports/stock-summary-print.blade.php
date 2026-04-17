<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ localize('stock_summary_report', 'Stock Summary Report') }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Khmer OS Battambang', 'Khmer OS', 'Noto Sans Khmer', sans-serif;
            font-size: 10px; color: #000; background: #fff; padding: 6mm;
        }
        .report-page { max-width: 195mm; margin: 0 auto; }
        .report-header { text-align: center; margin-bottom: 8px; border-bottom: 2px solid #000; padding-bottom: 5px; }
        .report-header .facility-name { font-size: 13px; font-weight: bold; margin-bottom: 2px; }
        .report-header .report-title { font-size: 15px; font-weight: bold; margin-top: 3px; }
        .report-header .sub-info { font-size: 9px; color: #444; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 10px; }
        .items-table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        .items-table th, .items-table td { border: 1px solid #000; padding: 2px 4px; }
        .items-table th { background: #f0f0f0; font-weight: 600; text-align: center; font-size: 9px; }
        .items-table td { font-size: 9px; }
        .items-table .text-end { text-align: right; }
        .items-table .text-center { text-align: center; }
        .items-table .med-kh { font-size: 8px; color: #444; }
        .items-table tfoot td { font-weight: 700; background: #f8f8f8; }
        .col-opening { color: #0056b3; }
        .col-closing { color: #155724; font-weight: 700; }
        .col-variance-neg { color: #dc3545; font-weight: 700; }
        .formula-note { margin-top: 6px; font-size: 8px; color: #555; border-top: 1px dashed #999; padding-top: 3px; }
        .sig-area { margin-top: 18px; display: flex; justify-content: space-between; }
        .sig-block { text-align: center; width: 30%; }
        .sig-line { margin-top: 35px; border-top: 1px dotted #000; padding-top: 3px; font-size: 9px; }
        .print-date { text-align: right; font-size: 8px; color: #888; margin-top: 8px; }
        .no-print { text-align: center; margin-bottom: 10px; }
        .no-print button { padding: 8px 24px; font-size: 14px; cursor: pointer; background: #1a6e3e; color: #fff; border: none; border-radius: 4px; }
        .no-print button:hover { background: #145530; }
        .no-print .btn-close-page { background: #666; margin-left: 8px; }
        @media print { body { padding: 0; margin: 0; } .no-print { display: none !important; } .report-page { max-width: 100%; } }
        @page { size: A4 landscape; margin: 8mm; }
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
            <div class="report-title">{{ localize('stock_summary_report', 'របាយការណ៍ស្តុកដើមគ្រា-ចុងគ្រា') }}</div>
        </div>

        <div class="info-row">
            <div><strong>{{ localize('facility', 'Facility') }}:</strong> {{ $facilityName ?? ($facility?->department_name) }}</div>
            <div><strong>{{ localize('period', 'Period') }}:</strong> {{ \Carbon\Carbon::parse($periodStart)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($periodEnd)->format('d/m/Y') }}</div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width:20px">#</th>
                    <th>{{ localize('medicine', 'Medicine') }}</th>
                    <th>{{ localize('unit', 'Unit') }}</th>
                    <th class="col-opening">{{ localize('opening_stock', 'ស្តុកដើមគ្រា') }}</th>
                    <th>{{ localize('received', 'ទទួល') }}</th>
                    <th>{{ localize('dispensed', 'ចេញប្រើ') }}</th>
                    <th>{{ localize('damaged', 'ខូចខាត') }}</th>
                    <th>{{ localize('expired', 'ផុតកំណត់') }}</th>
                    <th>{{ localize('adjustment', 'កែតម្រូវ') }}</th>
                    <th class="col-closing">{{ localize('closing_stock', 'ស្តុកចុងគ្រា') }}</th>
                    <th>{{ localize('current_stock', 'ស្តុកពិត') }}</th>
                    <th>{{ localize('variance', 'គម្លាត') }}</th>
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
                    <td class="text-end col-opening">{{ number_format($item['opening_stock'], 2) }}</td>
                    <td class="text-end">{{ number_format($item['received_qty'], 2) }}</td>
                    <td class="text-end">{{ number_format($item['dispensed_qty'], 2) }}</td>
                    <td class="text-end">{{ number_format($item['damaged_qty'], 2) }}</td>
                    <td class="text-end">{{ number_format($item['expired_qty'], 2) }}</td>
                    <td class="text-end">{{ number_format($item['adjustment_qty'], 2) }}</td>
                    <td class="text-end col-closing">{{ number_format($item['closing_stock'], 2) }}</td>
                    <td class="text-end">{{ number_format($item['current_stock'], 2) }}</td>
                    <td class="text-end {{ $item['variance'] < 0 ? 'col-variance-neg' : '' }}">{{ $item['variance'] != 0 ? number_format($item['variance'], 2) : '-' }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" style="text-align:right;"><strong>{{ localize('total', 'Total') }}</strong></td>
                    <td class="text-end col-opening">{{ number_format($totals['opening_stock'], 2) }}</td>
                    <td class="text-end">{{ number_format($totals['received_qty'], 2) }}</td>
                    <td class="text-end">{{ number_format($totals['dispensed_qty'], 2) }}</td>
                    <td class="text-end">{{ number_format($totals['damaged_qty'], 2) }}</td>
                    <td class="text-end">{{ number_format($totals['expired_qty'], 2) }}</td>
                    <td class="text-end">{{ number_format($totals['adjustment_qty'], 2) }}</td>
                    <td class="text-end col-closing">{{ number_format($totals['closing_stock'], 2) }}</td>
                    <td class="text-end">{{ number_format($totals['current_stock'], 2) }}</td>
                    <td class="text-end {{ $totals['variance'] < 0 ? 'col-variance-neg' : '' }}">{{ $totals['variance'] != 0 ? number_format($totals['variance'], 2) : '-' }}</td>
                </tr>
            </tfoot>
        </table>

        <div class="formula-note">
            {{ localize('stock_formula', 'រូបមន្ត: ស្តុកចុងគ្រា = ស្តុកដើមគ្រា + ទទួល – ចេញប្រើ – ខូចខាត – ផុតកំណត់ + កែតម្រូវ') }}
            &nbsp;|&nbsp;
            {{ localize('variance_info', 'គម្លាត = ស្តុកពិត − ស្តុកចុងគ្រា (បើអវិជ្ជមាន = ខ្វះ)') }}
        </div>

        <div class="sig-area">
            <div class="sig-block">
                <div class="sig-line">{{ localize('prepared_by', 'Prepared by') }}<br><small>________________</small></div>
            </div>
            <div class="sig-block">
                <div class="sig-line">{{ localize('verified_by', 'Verified by') }}<br><small>________________</small></div>
            </div>
            <div class="sig-block">
                <div class="sig-line">{{ localize('approved_by', 'Approved by') }}<br><small>________________</small></div>
            </div>
        </div>

        <div class="print-date">{{ localize('printed_on', 'Printed on') }}: {{ now()->format('d/m/Y H:i') }}</div>
    </div>
</body>
</html>
