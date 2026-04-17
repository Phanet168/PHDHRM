<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ localize('facility_stock_report', 'Facility Stock Report') }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Khmer OS Battambang', 'Khmer OS', 'Noto Sans Khmer', sans-serif;
            font-size: 10px;
            color: #000;
            background: #fff;
            padding: 6mm;
        }
        .report-page { max-width: 195mm; margin: 0 auto; }
        .report-header { text-align: center; margin-bottom: 8px; border-bottom: 2px solid #000; padding-bottom: 5px; }
        .report-header .facility-name { font-size: 13px; font-weight: bold; margin-bottom: 2px; }
        .report-header .report-title { font-size: 15px; font-weight: bold; margin-top: 3px; }
        .report-header .sub-info { font-size: 9px; color: #444; }
        .info-row { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 6px; font-size: 10px; }
        .items-table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        .items-table th, .items-table td { border: 1px solid #000; padding: 3px 4px; }
        .items-table th { background: #f0f0f0; font-weight: 700; text-align: center; font-size: 9px; }
        .items-table td { font-size: 9px; }
        .items-table .text-end { text-align: right; }
        .items-table .text-center { text-align: center; }
        .items-table .med-kh { font-size: 8px; color: #444; }
        .items-table tfoot td { font-weight: 700; background: #f8f8f8; }
        .text-danger { color: #b91c1c; }
        .text-warning { color: #b45309; }
        .text-success { color: #166534; }
        .note-row { margin-top: 6px; font-size: 8px; color: #555; }
        .sig-area { margin-top: 18px; display: flex; justify-content: space-between; gap: 14px; }
        .sig-block { text-align: center; width: 33%; }
        .sig-line { margin-top: 35px; border-top: 1px dotted #000; padding-top: 3px; font-size: 9px; }
        .print-date { text-align: right; font-size: 8px; color: #888; margin-top: 8px; }
        .no-print { text-align: center; margin-bottom: 10px; }
        .no-print button { padding: 8px 24px; font-size: 14px; cursor: pointer; background: #1a6e3e; color: #fff; border: none; border-radius: 4px; }
        .no-print button:hover { background: #145530; }
        .no-print .btn-close-page { background: #666; margin-left: 8px; }
        @media print {
            body { padding: 0; margin: 0; }
            .no-print { display: none !important; }
            .report-page { max-width: 100%; }
        }
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
            <div class="facility-name">{{ $facilityName }}</div>
            <div class="sub-info">{{ localize('ministry_of_health', 'Ministry of Health') }} - {{ localize('kingdom_of_cambodia', 'Kingdom of Cambodia') }}</div>
            <div class="report-title">
                {{ $summary ? localize('stock_summary_for_verification', 'របាយការណ៍ស្តុកសរុបសម្រាប់ផ្ទៀងផ្ទាត់') : localize('batch_stock_for_verification', 'របាយការណ៍ស្តុកតាម Batch សម្រាប់ផ្ទៀងផ្ទាត់') }}
            </div>
        </div>

        <div class="info-row">
            <div><strong>{{ localize('facility', 'Facility') }}:</strong> {{ $facilityName }}</div>
            <div><strong>{{ localize('view_type', 'View type') }}:</strong> {{ $summary ? localize('summary_by_medicine', 'សរុបតាមឱសថ') : localize('by_batch', 'តាម Batch') }}</div>
            <div><strong>{{ localize('search', 'Search') }}:</strong> {{ $search !== '' ? $search : '-' }}</div>
        </div>

        <table class="items-table">
            <thead>
                @if($summary)
                    <tr>
                        <th style="width: 24px;">#</th>
                        <th>{{ localize('facility', 'Facility') }}</th>
                        <th>{{ localize('medicine', 'Medicine') }}</th>
                        <th>{{ localize('category', 'Category') }}</th>
                        <th>{{ localize('unit', 'Unit') }}</th>
                        <th>{{ localize('nearest_expiry', 'ផុតកំណត់ជិត') }}</th>
                        <th>{{ localize('system_qty', 'ចំនួនក្នុងប្រព័ន្ធ') }}</th>
                        <th>{{ localize('actual_qty', 'ចំនួនជាក់ស្តែង') }}</th>
                        <th>{{ localize('variance', 'គម្លាត') }}</th>
                        <th>{{ localize('remark', 'កំណត់សម្គាល់') }}</th>
                    </tr>
                @else
                    <tr>
                        <th style="width: 24px;">#</th>
                        <th>{{ localize('facility', 'Facility') }}</th>
                        <th>{{ localize('medicine', 'Medicine') }}</th>
                        <th>{{ localize('category', 'Category') }}</th>
                        <th>{{ localize('batch_no', 'Batch') }}</th>
                        <th>{{ localize('expiry_date', 'Expiry') }}</th>
                        <th>{{ localize('system_qty', 'ចំនួនក្នុងប្រព័ន្ធ') }}</th>
                        <th>{{ localize('actual_qty', 'ចំនួនជាក់ស្តែង') }}</th>
                        <th>{{ localize('variance', 'គម្លាត') }}</th>
                        <th>{{ localize('remark', 'កំណត់សម្គាល់') }}</th>
                    </tr>
                @endif
            </thead>
            <tbody>
                @forelse($stocks as $idx => $stock)
                    @if($summary)
                        @php
                            $qty = (float) $stock->total_quantity;
                            $nearExpiry = $stock->nearest_expiry ? \Carbon\Carbon::parse($stock->nearest_expiry) : null;
                            $isExpiring = $nearExpiry && $nearExpiry->lte(now()->addMonths(3));
                            $isLow = $qty <= 10;
                        @endphp
                        <tr>
                            <td class="text-center">{{ $idx + 1 }}</td>
                            <td>{{ $stock->department_name ?: '-' }}</td>
                            <td>
                                <strong>{{ $stock->medicine_code }}</strong> - {{ $stock->medicine_name }}
                            </td>
                            <td>{{ $stock->category_name ?: '-' }}</td>
                            <td class="text-center">{{ $stock->medicine_unit ?: '-' }}</td>
                            <td class="text-center {{ $isExpiring ? 'text-danger' : '' }}">{{ $nearExpiry ? $nearExpiry->format('d/m/Y') : '-' }}</td>
                            <td class="text-end {{ $isLow ? 'text-warning' : 'text-success' }}">{{ number_format($qty, 2) }}</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>{{ $isExpiring ? localize('expiring_soon', 'ជិតផុតកំណត់') : ($isLow ? localize('low_stock', 'ស្តុកទាប') : '') }}</td>
                        </tr>
                    @else
                        @php
                            $isExpiring = $stock->expiry_date && $stock->expiry_date->lte(now()->addMonths(3));
                            $isLow = (float) $stock->quantity <= 10;
                        @endphp
                        <tr>
                            <td class="text-center">{{ $idx + 1 }}</td>
                            <td>{{ $stock->department?->department_name ?: '-' }}</td>
                            <td>
                                <strong>{{ $stock->medicine?->code }}</strong> - {{ $stock->medicine?->name ?: '-' }}
                                @if($stock->medicine?->name_kh)
                                    <br><span class="med-kh">{{ $stock->medicine?->name_kh }}</span>
                                @endif
                            </td>
                            <td>{{ $stock->medicine?->category?->name ?: '-' }}</td>
                            <td class="text-center">{{ $stock->batch_no ?: '-' }}</td>
                            <td class="text-center {{ $isExpiring ? 'text-danger' : '' }}">{{ optional($stock->expiry_date)->format('d/m/Y') ?: '-' }}</td>
                            <td class="text-end {{ $isLow ? 'text-warning' : 'text-success' }}">{{ number_format((float) $stock->quantity, 2) }}</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>{{ $isExpiring ? localize('expiring_soon', 'ជិតផុតកំណត់') : ($isLow ? localize('low_stock', 'ស្តុកទាប') : '') }}</td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="10" class="text-center">{{ localize('no_data', 'No data') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="note-row">
            {{ localize('stock_verification_note', 'ចំណាំ: សូមបញ្ចូលចំនួនជាក់ស្តែងដោយដៃ រួចគណនាគម្លាត ដើម្បីផ្ទៀងផ្ទាត់ស្តុកក្នុងប្រព័ន្ធជាមួយស្តុកជាក់ស្តែង។') }}
        </div>

        <div class="sig-area">
            <div class="sig-block">
                <div class="sig-line">{{ localize('prepared_by', 'Prepared by') }}</div>
            </div>
            <div class="sig-block">
                <div class="sig-line">{{ localize('verified_by', 'Verified by') }}</div>
            </div>
            <div class="sig-block">
                <div class="sig-line">{{ localize('approved_by', 'Approved by') }}</div>
            </div>
        </div>

        <div class="print-date">{{ localize('printed_on', 'Printed on') }}: {{ now()->format('d/m/Y H:i') }}</div>
    </div>
</body>
</html>
