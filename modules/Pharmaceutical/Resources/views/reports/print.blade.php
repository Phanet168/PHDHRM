<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ localize('pharm_report', 'Pharmaceutical Report') }} – {{ $report->reference_no }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Khmer OS Battambang', 'Khmer OS', 'Noto Sans Khmer', sans-serif;
            font-size: 11px;
            color: #000;
            background: #fff;
            padding: 8mm;
        }

        .report-page { max-width: 190mm; margin: 0 auto; }

        /* ── Header ── */
        .report-header { text-align: center; margin-bottom: 10px; border-bottom: 2px solid #000; padding-bottom: 6px; }
        .report-header .facility-name { font-size: 14px; font-weight: bold; margin-bottom: 2px; }
        .report-header .report-title { font-size: 16px; font-weight: bold; margin-top: 4px; letter-spacing: 0.5px; }
        .report-header .sub-info { font-size: 10px; color: #444; }

        /* ── Info Grid ── */
        .info-grid { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .info-grid .left, .info-grid .right { width: 48%; }
        .info-grid table { width: 100%; }
        .info-grid table th { text-align: left; padding: 2px 6px 2px 0; font-weight: 600; white-space: nowrap; width: 110px; font-size: 11px; }
        .info-grid table td { padding: 2px 0; font-size: 11px; }

        /* ── Items Table ── */
        .items-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .items-table th, .items-table td { border: 1px solid #000; padding: 3px 5px; }
        .items-table th { background: #f0f0f0; font-weight: 600; text-align: center; font-size: 10px; }
        .items-table td { font-size: 10px; }
        .items-table .text-end { text-align: right; }
        .items-table .text-center { text-align: center; }
        .items-table .med-name-kh { font-size: 9px; color: #444; }
        .items-table tfoot td { font-weight: 700; background: #f8f8f8; }

        /* ── Note ── */
        .note-section { margin-top: 8px; padding: 4px 8px; border: 1px dashed #999; font-size: 10px; }

        /* ── Reviewer Note ── */
        .reviewer-note { margin-top: 6px; padding: 4px 8px; background: #fef6e6; border: 1px solid #f0c040; font-size: 10px; }

        /* ── Footer / Signatures ── */
        .sig-area { margin-top: 20px; display: flex; justify-content: space-between; }
        .sig-block { text-align: center; width: 40%; }
        .sig-line { margin-top: 40px; border-top: 1px dotted #000; padding-top: 3px; font-size: 10px; }

        .print-date { text-align: right; font-size: 8px; color: #888; margin-top: 10px; }

        /* ── Print button ── */
        .no-print { text-align: center; margin-bottom: 12px; }
        .no-print button {
            padding: 8px 24px; font-size: 14px; cursor: pointer;
            background: #1a6e3e; color: #fff; border: none; border-radius: 4px;
        }
        .no-print button:hover { background: #145530; }
        .no-print .btn-close-page { background: #666; margin-left: 8px; }

        @media print {
            body { padding: 0; margin: 0; }
            .no-print { display: none !important; }
            .report-page { max-width: 100%; }
        }

        @page {
            size: A4 portrait;
            margin: 10mm;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">&#128424; {{ localize('print', 'Print') }}</button>
        <button class="btn-close-page" onclick="window.close()">{{ localize('close', 'Close') }}</button>
    </div>

    <div class="report-page">
        {{-- ═══ Header ═══ --}}
        <div class="report-header">
            <div class="facility-name">{{ $report->department?->department_name }}</div>
            <div class="sub-info">{{ localize('ministry_of_health', 'Ministry of Health') }} – {{ localize('kingdom_of_cambodia', 'Kingdom of Cambodia') }}</div>
            <div class="report-title">{{ localize('pharmaceutical_stock_report', 'របាយការណ៍ស្តុកឱសថ') }}</div>
            <div class="sub-info">{{ localize('ref', 'Ref') }}: {{ $report->reference_no }}</div>
        </div>

        {{-- ═══ Info ═══ --}}
        <div class="info-grid">
            <div class="left">
                <table>
                    <tr>
                        <th>{{ localize('facility', 'Facility') }}:</th>
                        <td><strong>{{ $report->department?->department_name }}</strong></td>
                    </tr>
                    <tr>
                        <th>{{ localize('report_to', 'Report to') }}:</th>
                        <td>{{ $report->parentDepartment?->department_name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th>{{ localize('report_type', 'Type') }}:</th>
                        <td>{{ $report->type_label }}</td>
                    </tr>
                    <tr>
                        <th>{{ localize('period', 'Period') }}:</th>
                        <td>{{ $report->period_label ?? ($report->period_start?->format('d/m/Y') . ' - ' . $report->period_end?->format('d/m/Y')) }}</td>
                    </tr>
                </table>
            </div>
            <div class="right">
                <table>
                    <tr>
                        <th>{{ localize('status', 'Status') }}:</th>
                        <td>{{ $report->status_label }}</td>
                    </tr>
                    <tr>
                        <th>{{ localize('submitted_by', 'Submitted by') }}:</th>
                        <td>{{ $report->submitter?->name ?? '-' }}</td>
                    </tr>
                    @if($report->submitted_at)
                    <tr>
                        <th>{{ localize('submitted_at', 'Submitted at') }}:</th>
                        <td>{{ $report->submitted_at?->format('d/m/Y H:i') }}</td>
                    </tr>
                    @endif
                    @if($report->reviewer)
                    <tr>
                        <th>{{ localize('reviewed_by', 'Reviewed by') }}:</th>
                        <td>{{ $report->reviewer->name }}</td>
                    </tr>
                    <tr>
                        <th>{{ localize('reviewed_at', 'Reviewed at') }}:</th>
                        <td>{{ $report->reviewed_at?->format('d/m/Y H:i') }}</td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>

        {{-- ═══ Items Table ═══ --}}
        @php
            $totOpening = $totReceived = $totDispensed = $totDamaged = $totAdj = $totExpired = $totClosing = 0;
        @endphp
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width:30px">#</th>
                    <th>{{ localize('medicine', 'Medicine') }}</th>
                    <th>{{ localize('unit', 'Unit') }}</th>
                    <th>{{ localize('opening_stock', 'Opening') }}</th>
                    <th>{{ localize('received', 'Received') }}</th>
                    <th>{{ localize('dispensed', 'Dispensed') }}</th>
                    <th>{{ localize('damaged', 'Damaged') }}</th>
                    <th>{{ localize('expired', 'Expired') }}</th>
                    <th>{{ localize('adjustment', 'Adjust') }}</th>
                    <th>{{ localize('closing_stock', 'Closing') }}</th>
                    <th>{{ localize('note', 'Note') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report->items as $idx => $item)
                @php
                    $totOpening += $item->opening_stock;
                    $totReceived += $item->received_qty;
                    $totDispensed += $item->dispensed_qty;
                    $totDamaged += $item->damaged_qty;
                    $totAdj += $item->adjustment_qty;
                    $totExpired += $item->expired_qty;
                    $totClosing += $item->closing_stock;
                @endphp
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>
                        {{ $item->medicine?->name }}
                        @if($item->medicine?->name_kh)
                            <br><span class="med-name-kh">{{ $item->medicine->name_kh }}</span>
                        @endif
                    </td>
                    <td class="text-center">{{ $item->medicine?->unit }}</td>
                    <td class="text-end">{{ number_format($item->opening_stock, 2) }}</td>
                    <td class="text-end">{{ number_format($item->received_qty, 2) }}</td>
                    <td class="text-end">{{ number_format($item->dispensed_qty, 2) }}</td>
                    <td class="text-end">{{ number_format($item->damaged_qty, 2) }}</td>
                    <td class="text-end">{{ number_format($item->expired_qty, 2) }}</td>
                    <td class="text-end">{{ number_format($item->adjustment_qty, 2) }}</td>
                    <td class="text-end"><strong>{{ number_format($item->closing_stock, 2) }}</strong></td>
                    <td>{{ $item->note }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" style="text-align:right;"><strong>{{ localize('total', 'Total') }}</strong></td>
                    <td class="text-end">{{ number_format($totOpening, 2) }}</td>
                    <td class="text-end">{{ number_format($totReceived, 2) }}</td>
                    <td class="text-end">{{ number_format($totDispensed, 2) }}</td>
                    <td class="text-end">{{ number_format($totDamaged, 2) }}</td>
                    <td class="text-end">{{ number_format($totExpired, 2) }}</td>
                    <td class="text-end">{{ number_format($totAdj, 2) }}</td>
                    <td class="text-end"><strong>{{ number_format($totClosing, 2) }}</strong></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        {{-- ═══ Period Comparison ═══ --}}
        @if(!empty($prevData) && !empty($prevData['items']))
        <div style="margin-top: 12px;">
            <div style="font-weight: 700; font-size: 12px; margin-bottom: 4px; border-bottom: 1px solid #000; padding-bottom: 2px;">
                {{ localize('period_comparison', 'Period Comparison') }}
                @if(!empty($prevData['report']))
                    <span style="font-weight: 400; font-size: 10px; color: #444;">
                        ({{ $prevData['report']->period_start?->format('d/m/Y') }} – {{ $prevData['report']->period_end?->format('d/m/Y') }}
                        vs {{ $report->period_start?->format('d/m/Y') }} – {{ $report->period_end?->format('d/m/Y') }})
                    </span>
                @endif
            </div>
            <table class="items-table" style="font-size: 9px;">
                <thead>
                    <tr>
                        <th>{{ localize('medicine', 'Medicine') }}</th>
                        <th colspan="2">{{ localize('dispensed', 'Dispensed') }}</th>
                        <th colspan="2">{{ localize('damaged', 'Damaged') }}</th>
                        <th colspan="2">{{ localize('expired', 'Expired') }}</th>
                        <th colspan="2">{{ localize('closing_stock', 'Closing') }}</th>
                    </tr>
                    <tr>
                        <th></th>
                        <th>{{ localize('previous', 'Prev') }}</th>
                        <th>{{ localize('current', 'Current') }}</th>
                        <th>{{ localize('previous', 'Prev') }}</th>
                        <th>{{ localize('current', 'Current') }}</th>
                        <th>{{ localize('previous', 'Prev') }}</th>
                        <th>{{ localize('current', 'Current') }}</th>
                        <th>{{ localize('previous', 'Prev') }}</th>
                        <th>{{ localize('current', 'Current') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report->items as $item)
                    @php
                        $prev = $prevData['items'][$item->medicine_id] ?? null;
                    @endphp
                    <tr>
                        <td>{{ $item->medicine?->name }}</td>
                        <td class="text-end">{{ $prev ? number_format($prev->dispensed_qty, 2) : '-' }}</td>
                        <td class="text-end" style="{{ $prev && $item->dispensed_qty > $prev->dispensed_qty ? 'color:red;font-weight:700;' : '' }}">{{ number_format($item->dispensed_qty, 2) }}</td>
                        <td class="text-end">{{ $prev ? number_format($prev->damaged_qty, 2) : '-' }}</td>
                        <td class="text-end" style="{{ $prev && $item->damaged_qty > $prev->damaged_qty ? 'color:red;font-weight:700;' : '' }}">{{ number_format($item->damaged_qty, 2) }}</td>
                        <td class="text-end">{{ $prev ? number_format($prev->expired_qty, 2) : '-' }}</td>
                        <td class="text-end" style="{{ $prev && $item->expired_qty > $prev->expired_qty ? 'color:red;font-weight:700;' : '' }}">{{ number_format($item->expired_qty, 2) }}</td>
                        <td class="text-end">{{ $prev ? number_format($prev->closing_stock, 2) : '-' }}</td>
                        <td class="text-end" style="{{ $prev && $item->closing_stock < $prev->closing_stock ? 'color:red;font-weight:700;' : '' }}">{{ number_format($item->closing_stock, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        {{-- ═══ Note ═══ --}}
        @if($report->note)
        <div class="note-section">
            <strong>{{ localize('note', 'Note') }}:</strong> {{ $report->note }}
        </div>
        @endif

        {{-- ═══ Reviewer Note ═══ --}}
        @if($report->reviewer_note)
        <div class="reviewer-note">
            <strong>{{ localize('reviewer_note', 'Reviewer note') }}:</strong> {{ $report->reviewer_note }}
        </div>
        @endif

        {{-- ═══ Signatures ═══ --}}
        <div class="sig-area">
            <div class="sig-block">
                <div class="sig-line">
                    {{ localize('prepared_by', 'Prepared by') }}<br>
                    <small>{{ $report->submitter?->name ?? '________________' }}</small>
                </div>
            </div>
            <div class="sig-block">
                <div class="sig-line">
                    {{ localize('reviewed_by', 'Reviewed by') }}<br>
                    <small>{{ $report->reviewer?->name ?? '________________' }}</small>
                </div>
            </div>
        </div>

        <div class="print-date">
            {{ localize('printed_on', 'Printed on') }}: {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>
</body>
</html>
