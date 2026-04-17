<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ localize('dispensing_receipt', 'Dispensing Receipt') }} – {{ $dispensing->reference_no }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Khmer OS Battambang', 'Khmer OS', 'Noto Sans Khmer', sans-serif;
            font-size: 11px;
            color: #000;
            background: #fff;
            padding: 6mm;
        }

        .receipt { max-width: 148mm; margin: 0 auto; }

        /* ── Header ── */
        .receipt-header { text-align: center; margin-bottom: 8px; border-bottom: 2px solid #000; padding-bottom: 6px; }
        .receipt-header .facility-name { font-size: 13px; font-weight: bold; margin-bottom: 1px; }
        .receipt-header .receipt-title { font-size: 15px; font-weight: bold; margin-top: 3px; letter-spacing: 0.5px; }
        .receipt-header .sub-info { font-size: 9.5px; color: #444; }

        /* ── Info Grid ── */
        .info-grid { display: flex; justify-content: space-between; margin-bottom: 6px; }
        .info-grid .left, .info-grid .right { width: 48%; }
        .info-grid table { width: 100%; }
        .info-grid table th { text-align: left; padding: 1px 4px 1px 0; font-weight: 600; white-space: nowrap; width: 85px; font-size: 10.5px; }
        .info-grid table td { padding: 1px 0; font-size: 10.5px; }

        /* ── Medicines Table ── */
        .med-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .med-table th, .med-table td { border: 1px solid #000; padding: 3px 4px; }
        .med-table th { background: #f0f0f0; font-weight: 600; text-align: center; font-size: 10px; }
        .med-table td { font-size: 10px; }
        .med-table .text-end { text-align: right; }
        .med-table .text-center { text-align: center; }
        .med-table .med-name-kh { font-size: 9px; color: #444; }

        /* ── Note ── */
        .note-section { margin-top: 6px; padding: 3px 6px; border: 1px dashed #999; font-size: 10px; }
        .note-section strong { font-size: 10px; }

        /* ── Footer ── */
        .receipt-footer { margin-top: 14px; display: flex; justify-content: space-between; }
        .receipt-footer .sig-block { text-align: center; width: 40%; }
        .receipt-footer .sig-line { margin-top: 30px; border-top: 1px dotted #000; padding-top: 3px; font-size: 10px; }

        .print-date { text-align: right; font-size: 8px; color: #888; margin-top: 6px; }

        /* ── Print button (hidden when printing) ── */
        .no-print { text-align: center; margin-bottom: 12px; }
        .no-print button {
            padding: 8px 24px; font-size: 14px; cursor: pointer;
            background: #1a6e3e; color: #fff; border: none; border-radius: 4px;
        }
        .no-print button:hover { background: #145530; }

        @media print {
            body { padding: 0; margin: 0; }
            .no-print { display: none !important; }
            .receipt { max-width: 100%; }
        }

        @page {
            size: A5 portrait;
            margin: 6mm;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()"><i class="fa fa-print"></i> {{ localize('print', 'Print') }}</button>
        <button onclick="window.close()" style="background:#666;margin-left:8px;">{{ localize('close', 'Close') }}</button>
    </div>

    <div class="receipt">
        {{-- ═══ Header ═══ --}}
        <div class="receipt-header">
            <div class="facility-name">{{ $dispensing->department?->department_name }}</div>
            <div class="sub-info">{{ localize('ministry_of_health', 'Ministry of Health') }} – {{ localize('kingdom_of_cambodia', 'Kingdom of Cambodia') }}</div>
            <div class="receipt-title">{{ localize('dispensing_receipt', 'វិក្កយបត្រផ្តល់ឱសថ') }}</div>
            <div class="sub-info">{{ localize('ref', 'Ref') }}: {{ $dispensing->reference_no }}</div>
        </div>

        {{-- ═══ Patient & Visit Info ═══ --}}
        <div class="info-grid">
            <div class="left">
                <table>
                    <tr>
                        <th>{{ localize('patient_name', 'Patient') }}:</th>
                        <td><strong>{{ $dispensing->patient_name }}</strong></td>
                    </tr>
                    <tr>
                        <th>{{ localize('gender', 'Gender') }}:</th>
                        <td>
                            @if($dispensing->patient_gender === 'M') {{ localize('male', 'ប្រុស') }}
                            @elseif($dispensing->patient_gender === 'F') {{ localize('female', 'ស្រី') }}
                            @else - @endif
                        </td>
                    </tr>
                    <tr>
                        <th>{{ localize('age', 'Age') }}:</th>
                        <td>{{ $dispensing->patient_age ? $dispensing->patient_age . ' ' . localize('years', 'ឆ្នាំ') : '-' }}</td>
                    </tr>
                    <tr>
                        <th>{{ localize('id_no', 'ID No.') }}:</th>
                        <td>{{ $dispensing->patient_id_no ?? '-' }}</td>
                    </tr>
                </table>
            </div>
            <div class="right">
                <table>
                    <tr>
                        <th>{{ localize('date', 'Date') }}:</th>
                        <td>{{ $dispensing->dispensing_date?->format('d/m/Y') }}</td>
                    </tr>
                    <tr>
                        <th>{{ localize('diagnosis', 'Diagnosis') }}:</th>
                        <td>{{ $dispensing->diagnosis ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th>{{ localize('dispensed_by', 'Dispensed by') }}:</th>
                        <td>{{ $dispensing->dispenser?->full_name ?? '-' }}</td>
                    </tr>
                </table>
            </div>
        </div>

        {{-- ═══ Medicines Table ═══ --}}
        <table class="med-table">
            <thead>
                <tr>
                    <th style="width:30px;">{{ localize('no', 'No') }}</th>
                    <th>{{ localize('medicine_name', 'Medicine') }}</th>
                    <th style="width:80px;">{{ localize('dosage_form', 'Form') }}</th>
                    <th style="width:70px;" class="text-center">{{ localize('quantity', 'Qty') }}</th>
                    <th>{{ localize('dosage_instruction', 'Dosage / Usage') }}</th>
                    <th style="width:55px;" class="text-center">{{ localize('duration_days', 'Days') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($dispensing->items as $item)
                    <tr>
                        <td class="text-center">{{ $loop->iteration }}</td>
                        <td>
                            <strong>{{ $item->medicine?->name }}</strong>
                            @if($item->medicine?->name_kh)
                                <span class="med-name-kh"> ({{ $item->medicine->name_kh }})</span>
                            @endif
                            @if($item->medicine?->strength)
                                <br><small>{{ $item->medicine->strength }}</small>
                            @endif
                        </td>
                        <td class="text-center">{{ $item->medicine?->dosage_form ?? '-' }}</td>
                        <td class="text-center"><strong>{{ number_format($item->quantity, 0) }}</strong> {{ $item->medicine?->unit }}</td>
                        <td>{{ $item->dosage_instruction ?? '-' }}</td>
                        <td class="text-center">{{ $item->duration_days ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- ═══ Note ═══ --}}
        @if($dispensing->note)
            <div class="note-section">
                <strong>{{ localize('note', 'Note') }}:</strong> {{ $dispensing->note }}
            </div>
        @endif

        {{-- ═══ Signature ═══ --}}
        <div class="receipt-footer">
            <div class="sig-block">
                <div class="sig-line">{{ localize('patient_signature', 'Patient / Representative') }}</div>
            </div>
            <div class="sig-block">
                <div class="sig-line">{{ localize('pharmacist_signature', 'Pharmacist') }}</div>
            </div>
        </div>

        <div class="print-date">
            {{ localize('printed_on', 'Printed on') }}: {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>
</body>
</html>
