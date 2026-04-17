@extends('backend.layouts.app')

@section('title', localize('incoming_letters', 'លិខិតចូល'))

@section('content')
    @php
        $periodOptions = [
            'today' => localize('today', 'ថ្ងៃនេះ'),
            'yesterday' => localize('yesterday', 'ម្សិលមិញ'),
            'this_week' => localize('this_week', 'សប្តាហ៍នេះ'),
            'this_month' => localize('this_month', 'ខែនេះ'),
            'all' => localize('all_time', 'ទាំងអស់'),
            'custom' => localize('custom_range', 'ជ្រើសរើសថ្ងៃ'),
        ];
        $selectedPeriod = $period ?? 'today';
        $selectedStartDate = $startDate ?? '';
        $selectedEndDate = $endDate ?? '';
        $selectedPerPage = (int) ($perPage ?? 20);

        $statusLabels = [
            'pending' => localize('pending', 'កំពុងរង់ចាំ'),
            'in_progress' => localize('in_progress', 'កំពុងដំណើរការ'),
            'completed' => localize('completed', 'បានបញ្ចប់'),
            'archived' => localize('archived', 'បានរក្សាទុក'),
        ];

        $statusClasses = [
            'pending' => 'bg-warning text-dark',
            'in_progress' => 'bg-primary',
            'completed' => 'bg-success',
            'archived' => 'bg-secondary',
        ];

        $priorityLabels = [
            'normal' => localize('normal', 'ធម្មតា'),
            'urgent' => localize('urgent', 'បន្ទាន់'),
            'confidential' => localize('confidential', 'សម្ងាត់'),
        ];

        $priorityClasses = [
            'normal' => 'corr-priority-normal',
            'urgent' => 'corr-priority-urgent',
            'confidential' => 'corr-priority-confidential',
        ];
    @endphp

    <div class="body-content">
        @include('correspondence::_nav')

        <div class="card corr-card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0">{{ localize('incoming_letters', 'លិខិតចូល') }}</h6>
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <a href="{{ route('correspondence.help', ['article' => 'incoming']) }}" class="btn btn-sm btn-outline-info">
                        <i class="fa fa-life-ring me-1"></i>{{ localize('help', 'ជំនួយ') }}
                    </a>
                </div>
            </div>
            <div class="card-body border-bottom py-2">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-lg-3 col-md-4">
                        <label class="form-label mb-1">{{ localize('search', 'ស្វែងរក') }}</label>
                        <input type="text" class="form-control form-control-sm" name="search" value="{{ $search ?? '' }}"
                            placeholder="{{ localize('search', 'ស្វែងរក') }}">
                    </div>
                    <div class="col-lg-2 col-md-3">
                        <label class="form-label mb-1">{{ localize('display_period', 'រយៈពេល') }}</label>
                        <select name="period" class="form-select form-select-sm">
                            @foreach ($periodOptions as $periodKey => $periodLabel)
                                <option value="{{ $periodKey }}" {{ $selectedPeriod === $periodKey ? 'selected' : '' }}>{{ $periodLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-3">
                        <label class="form-label mb-1">{{ localize('from_date', 'ពីថ្ងៃ') }}</label>
                        <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $selectedStartDate }}">
                    </div>
                    <div class="col-lg-2 col-md-3">
                        <label class="form-label mb-1">{{ localize('to_date', 'ដល់ថ្ងៃ') }}</label>
                        <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $selectedEndDate }}">
                    </div>
                    <div class="col-lg-1 col-md-2">
                        <label class="form-label mb-1">{{ localize('show', 'បង្ហាញ') }}</label>
                        <select name="per_page" class="form-select form-select-sm">
                            @foreach ([10, 20, 50, 100] as $pageSize)
                                <option value="{{ $pageSize }}" {{ $selectedPerPage === $pageSize ? 'selected' : '' }}>{{ $pageSize }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="fa fa-filter me-1"></i>{{ localize('apply_filter', 'បង្ហាញ') }}
                        </button>
                        <a href="{{ route('correspondence.incoming') }}" class="btn btn-sm btn-outline-secondary">{{ localize('reset', 'កំណត់ឡើងវិញ') }}</a>
                    </div>
                </form>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped mb-0 align-middle">
                        <thead>
                            <tr>
                                <th width="5%">{{ localize('sl', 'ល.រ') }}</th>
                                <th>{{ localize('registry_no', 'លេខចុះបញ្ជី') }}</th>
                                <th>{{ localize('subject', 'ប្រធានបទ') }}</th>
                                <th>{{ localize('from', 'មកពី') }}</th>
                                <th>{{ localize('received_date', 'ថ្ងៃទទួលលិខិត') }}</th>
                                <th width="6%">{{ localize('attachments', 'ឯកសារភ្ជាប់') }}</th>
                                <th>{{ localize('status', 'ស្ថានភាព') }}</th>
                                <th>{{ localize('workflow_step', 'ជំហានលំហូរការងារ') }}</th>
                                <th width="10%">{{ localize('action', 'សកម្មភាព') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($letters as $index => $letter)
                                @php
                                    $attachments = is_array($letter->attachment_path) ? $letter->attachment_path : json_decode($letter->attachment_path, true);
                                    if (!is_array($attachments)) {
                                        $attachments = !empty($letter->attachment_path) ? [(string) $letter->attachment_path] : [];
                                    }
                                    $hasAttachments = !empty($attachments);
                                @endphp
                                <tr>
                                    <td>{{ $letters->firstItem() + $index }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $letter->registry_no ?: '-' }}</div>
                                        <div class="small text-muted">{{ localize('letter_no', 'លេខលិខិត') }}: {{ $letter->letter_no ?: '-' }}</div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $letter->subject }}</div>
                                        <div class="small text-muted d-flex flex-wrap gap-1 mt-1">
                                            <span class="corr-priority-chip {{ $priorityClasses[$letter->priority ?? 'normal'] ?? 'corr-priority-normal' }}">
                                                {{ $priorityLabels[$letter->priority ?? 'normal'] ?? ($letter->priority ?: localize('normal', 'ធម្មតា')) }}
                                            </span>
                                            @if($letter->originDepartment)
                                                <span class="corr-soft-chip">{{ $letter->originDepartment->department_name }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div>{{ $letter->from_org ?: '-' }}</div>
                                        @if($letter->to_org)
                                            <div class="small text-muted">{{ localize('to', 'To') }}: {{ $letter->to_org }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div>{{ optional($letter->received_date)->format('d/m/Y') ?: '-' }}</div>
                                        <div class="small text-muted">{{ optional($letter->letter_date)->format('d/m/Y') ?: '-' }}</div>
                                    </td>
                                    <td class="text-center">
                                        @if ($hasAttachments)
                                            <i class="fa fa-paperclip text-primary" title="{{ localize('has_attachments', 'មានឯកសារភ្ជាប់') }}"></i>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $statusClasses[$letter->status ?? 'pending'] ?? 'bg-secondary' }}">{{ $statusLabels[$letter->status ?? 'pending'] ?? ($letter->status ?: '-') }}</span>
                                    </td>
                                    <td>
                                        <span class="corr-step-badge">{{ $letter->current_step_label }}</span>
                                    </td>
                                    <td>
                                        <a href="{{ route('correspondence.show', $letter->id) }}" class="btn btn-sm btn-info">
                                            {{ localize('show', 'បង្ហាញ') }}
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted">
                                        {{ localize('no_data_available', 'មិនមានទិន្នន័យ') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($letters->hasPages())
                <div class="card-footer">
                    {{ $letters->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection

@push('css')
    <style>
        .corr-priority-chip {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: 600;
        }

        .corr-priority-normal {
            background: #eef2ff;
            color: #3730a3;
        }

        .corr-priority-urgent {
            background: #fee2e2;
            color: #b91c1c;
        }

        .corr-priority-confidential {
            background: #fef3c7;
            color: #92400e;
        }

        .corr-soft-chip {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 2px 8px;
            font-size: 11px;
            background: #f3f4f6;
            color: #4b5563;
        }

        .corr-step-badge {
            display: inline-flex;
            border-radius: 8px;
            padding: 4px 8px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 12px;
            font-weight: 600;
            line-height: 1.3;
        }
    </style>
@endpush


