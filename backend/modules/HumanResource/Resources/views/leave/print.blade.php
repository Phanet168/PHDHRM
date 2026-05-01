<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ localize('leave_request_form', 'Leave Request Form') }}</title>
    <style>
        body { font-family: "Noto Sans Khmer", "Khmer OS Battambang", Arial, sans-serif; color: #1f2937; margin: 24px; }
        .page { max-width: 900px; margin: 0 auto; }
        .toolbar { margin-bottom: 20px; display: flex; gap: 12px; }
        .btn { border: 1px solid #cbd5e1; background: #fff; padding: 10px 14px; text-decoration: none; color: #111827; border-radius: 6px; }
        .btn-primary { background: #166534; color: #fff; border-color: #166534; }
        .sheet { border: 1px solid #d1d5db; padding: 28px; border-radius: 12px; }
        h1, h2, h3 { margin: 0; }
        .title { text-align: center; margin-bottom: 24px; }
        .meta, .grid { width: 100%; border-collapse: collapse; }
        .meta td { padding: 8px 0; vertical-align: top; }
        .label { width: 220px; font-weight: 600; }
        .section { margin-top: 24px; }
        .note { margin-top: 28px; padding: 14px; border: 1px dashed #9ca3af; border-radius: 8px; background: #f9fafb; }
        .signature-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 32px; margin-top: 40px; }
        .signature-box { text-align: center; min-height: 120px; }
        .line { margin-top: 72px; border-top: 1px solid #9ca3af; padding-top: 8px; }
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; }
            .sheet { border: 0; border-radius: 0; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="toolbar no-print">
            <button type="button" class="btn btn-primary" onclick="window.print()">{{ localize('print', 'Print') }}</button>
            <a href="{{ route('leave.index') }}" class="btn">{{ localize('back', 'Back') }}</a>
        </div>

        <div class="sheet">
            <div class="title">
                <h2>{{ localize('leave_request_form', 'Leave Request Form') }}</h2>
                <div>{{ localize('hard_copy_submission_note', 'Approved document must be printed and filed at administration as hard copy.') }}</div>
            </div>

            <table class="meta">
                <tr>
                    <td class="label">{{ localize('employee_name') }}</td>
                    <td>{{ $leave->employee?->full_name }}</td>
                </tr>
                <tr>
                    <td class="label">{{ localize('leave_type', 'Leave type') }}</td>
                    <td>{{ $leave->leaveType?->display_name }}</td>
                </tr>
                <tr>
                    <td class="label">{{ localize('apply_date') }}</td>
                    <td>{{ $leave->leave_apply_date }}</td>
                </tr>
                <tr>
                    <td class="label">{{ localize('leave_period', 'Leave period') }}</td>
                    <td>{{ $leave->leave_apply_start_date }} - {{ $leave->leave_apply_end_date }}</td>
                </tr>
                <tr>
                    <td class="label">{{ localize('approved_period', 'Approved period') }}</td>
                    <td>{{ $leave->leave_approved_start_date ?: '-' }} - {{ $leave->leave_approved_end_date ?: '-' }}</td>
                </tr>
                <tr>
                    <td class="label">{{ localize('total_days') }}</td>
                    <td>{{ $leave->total_approved_day ?: $leave->total_apply_day }}</td>
                </tr>
                <tr>
                    <td class="label">{{ localize('replacement_employee', 'Replacement employee') }}</td>
                    <td>{{ $leave->handoverEmployee?->full_name ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="label">{{ localize('reason') }}</td>
                    <td>{{ $leave->reason ?: '-' }}</td>
                </tr>
                <tr>
                    <td class="label">{{ localize('status') }}</td>
                    <td>{{ $leave->workflow_status ?: '-' }}</td>
                </tr>
            </table>

            <div class="section">
                <h3>{{ localize('workflow_summary', 'Workflow summary') }}</h3>
                <div class="note">
                    {{ localize('workflow_completed_note', 'This request has completed the configured approval flow. Please print this document and submit it to administration for filing.') }}
                </div>
            </div>

            <div class="signature-grid">
                <div class="signature-box">
                    <div>{{ localize('requester', 'Requester') }}</div>
                    <div class="line">{{ $leave->employee?->full_name }}</div>
                </div>
                <div class="signature-box">
                    <div>{{ localize('replacement_employee', 'Replacement employee') }}</div>
                    <div class="line">{{ $leave->handoverEmployee?->full_name ?? '' }}</div>
                </div>
                <div class="signature-box">
                    <div>{{ localize('final_approver', 'Final approver') }}</div>
                    <div class="line">{{ $leave->approvedBy?->full_name ?? '' }}</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
