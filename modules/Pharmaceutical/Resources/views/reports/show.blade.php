@extends('backend.layouts.app')

@section('title', localize('report_detail', 'Report detail'))

@section('content')
    <div class="body-content">
        @include('pharmaceutical::_nav')

        <div class="card pharm-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">{{ localize('report_detail', 'Report detail') }} – {{ $report->reference_no }}</h6>
                <div>
                    <a href="{{ route('pharmaceutical.reports.print', $report->id) }}" target="_blank" class="btn btn-sm btn-outline-success">
                        <i class="fa fa-print me-1"></i>{{ localize('print', 'Print') }}
                    </a>
                    <a href="{{ route('pharmaceutical.reports.index') }}" class="btn btn-sm btn-secondary">{{ localize('back', 'Back') }}</a>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4"><strong>{{ localize('facility', 'Facility') }}:</strong> {{ $report->department?->department_name ?: '-' }}</div>
                    <div class="col-md-4"><strong>{{ localize('report_to', 'Report to') }}:</strong> {{ $report->parentDepartment?->department_name ?: '-' }}</div>
                    <div class="col-md-4"><strong>{{ localize('report_type', 'Type') }}:</strong> {{ $report->type_label }}</div>
                    <div class="col-md-4"><strong>{{ localize('period', 'Period') }}:</strong> {{ $report->period_label ?: (optional($report->period_start)->format('d/m/Y') . ' – ' . optional($report->period_end)->format('d/m/Y')) }}</div>
                    <div class="col-md-4"><strong>{{ localize('status', 'Status') }}:</strong> {!! $report->status_badge !!}</div>
                    <div class="col-md-4"><strong>{{ localize('submitted_at', 'Submitted at') }}:</strong> {{ optional($report->submitted_at)->format('d/m/Y H:i') ?: '-' }}</div>
                    @if($report->reviewed_at)
                        <div class="col-md-4"><strong>{{ localize('reviewed_at', 'Reviewed at') }}:</strong> {{ $report->reviewed_at->format('d/m/Y H:i') }}</div>
                        <div class="col-md-4"><strong>{{ localize('reviewed_by', 'Reviewed by') }}:</strong> {{ $report->reviewer?->full_name ?? '-' }}</div>
                    @endif
                    @if($report->note)
                        <div class="col-md-12"><strong>{{ localize('note', 'Note') }}:</strong> {{ $report->note }}</div>
                    @endif
                    @if($report->reviewer_note)
                        <div class="col-md-12"><strong>{{ localize('reviewer_note', 'Reviewer note') }}:</strong> <span class="text-info">{{ $report->reviewer_note }}</span></div>
                    @endif
                </div>

                {{-- Action buttons --}}
                <div class="d-flex gap-2 flex-wrap">
                    @if($report->status === \Modules\Pharmaceutical\Entities\PharmReport::STATUS_DRAFT)
                        <a href="{{ route('pharmaceutical.reports.edit', $report->id) }}" class="btn btn-warning btn-sm">
                            <i class="fa fa-edit me-1"></i>{{ localize('edit', 'Edit') }}
                        </a>
                        <form method="POST" action="{{ route('pharmaceutical.reports.submit', $report->id) }}" class="d-inline" onsubmit="return confirm('{{ localize('confirm_submit', 'Confirm submit?') }}')">
                            @csrf
                            <button class="btn btn-primary btn-sm"><i class="fa fa-paper-plane me-1"></i>{{ localize('submit', 'Submit') }}</button>
                        </form>
                        <form method="POST" action="{{ route('pharmaceutical.reports.destroy', $report->id) }}" class="d-inline" onsubmit="return confirm('{{ localize('confirm_delete', 'Are you sure?') }}')">
                            @csrf @method('DELETE')
                            <button class="btn btn-danger btn-sm"><i class="fa fa-trash me-1"></i>{{ localize('delete', 'Delete') }}</button>
                        </form>
                    @endif

                    @if(($canReview ?? false) && $report->status === \Modules\Pharmaceutical\Entities\PharmReport::STATUS_SUBMITTED)
                        <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#reviewModal" onclick="document.getElementById('reviewAction').value='review'">
                            <i class="fa fa-search me-1"></i>{{ localize('mark_reviewed', 'Mark reviewed') }}
                        </button>
                        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#reviewModal" onclick="document.getElementById('reviewAction').value='approve'">
                            <i class="fa fa-check me-1"></i>{{ localize('approve', 'Approve') }}
                        </button>
                    @endif

                    @if(($canReview ?? false) && $report->status === \Modules\Pharmaceutical\Entities\PharmReport::STATUS_REVIEWED)
                        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#reviewModal" onclick="document.getElementById('reviewAction').value='approve'">
                            <i class="fa fa-check me-1"></i>{{ localize('approve', 'Approve') }}
                        </button>
                    @endif
                </div>
            </div>
        </div>

        {{-- Report items --}}
        <div class="card pharm-card">
            <div class="card-header">
                <h6 class="mb-0">{{ localize('report_items', 'Report items') }} ({{ $report->items->count() }})</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ localize('sl', 'SL') }}</th>
                                <th>{{ localize('medicine', 'Medicine') }}</th>
                                <th class="text-end">{{ localize('opening_stock', 'Opening') }}</th>
                                <th class="text-end">{{ localize('received', 'Received') }}</th>
                                <th class="text-end">{{ localize('dispensed', 'Dispensed') }}</th>
                                <th class="text-end">{{ localize('damaged', 'Damaged') }}</th>
                                <th class="text-end">{{ localize('expired', 'Expired') }}</th>
                                <th class="text-end">{{ localize('adjustment', 'Adjust') }}</th>
                                <th class="text-end">{{ localize('closing_stock', 'Closing') }}</th>
                                <th>{{ localize('note', 'Note') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $totals = ['opening' => 0, 'received' => 0, 'dispensed' => 0, 'damaged' => 0, 'adjustment' => 0, 'expired' => 0, 'closing' => 0]; @endphp
                            @foreach($report->items as $item)
                                @php
                                    $totals['opening']    += (float) $item->opening_stock;
                                    $totals['received']   += (float) $item->received_qty;
                                    $totals['dispensed']  += (float) $item->dispensed_qty;
                                    $totals['damaged']    += (float) ($item->damaged_qty ?? 0);
                                    $totals['adjustment'] += (float) $item->adjustment_qty;
                                    $totals['expired']    += (float) $item->expired_qty;
                                    $totals['closing']    += (float) $item->closing_stock;
                                @endphp
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>
                                        {{ $item->medicine?->name ?: '-' }}
                                        <small class="text-muted">({{ $item->medicine?->code }})</small>
                                        @if($item->medicine?->name_kh)
                                            <br><small class="text-muted">{{ $item->medicine->name_kh }}</small>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ number_format((float) $item->opening_stock, 2) }}</td>
                                    <td class="text-end">{{ number_format((float) $item->received_qty, 2) }}</td>
                                    <td class="text-end">{{ number_format((float) $item->dispensed_qty, 2) }}</td>
                                    <td class="text-end">{{ number_format((float) ($item->damaged_qty ?? 0), 2) }}</td>
                                    <td class="text-end">{{ number_format((float) $item->expired_qty, 2) }}</td>
                                    <td class="text-end">{{ number_format((float) $item->adjustment_qty, 2) }}</td>
                                    <td class="text-end fw-bold">{{ number_format((float) $item->closing_stock, 2) }}</td>
                                    <td><small>{{ $item->note ?: '-' }}</small></td>
                                </tr>
                            @endforeach
                        </tbody>
                        @if($report->items->count() > 1)
                        <tfoot class="table-light">
                            <tr class="fw-bold">
                                <td colspan="2" class="text-end">{{ localize('total', 'Total') }}</td>
                                <td class="text-end">{{ number_format($totals['opening'], 2) }}</td>
                                <td class="text-end">{{ number_format($totals['received'], 2) }}</td>
                                <td class="text-end">{{ number_format($totals['dispensed'], 2) }}</td>
                                <td class="text-end">{{ number_format($totals['damaged'], 2) }}</td>
                                <td class="text-end">{{ number_format($totals['expired'], 2) }}</td>
                                <td class="text-end">{{ number_format($totals['adjustment'], 2) }}</td>
                                <td class="text-end">{{ number_format($totals['closing'], 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>

        {{-- Period comparison --}}
        @if(!empty($prevData) && !empty($prevData['items']))
        <div class="card pharm-card mt-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fa fa-chart-line me-1"></i>{{ localize('period_comparison', 'Period comparison') }}
                    <small class="text-muted">
                        – {{ localize('vs_previous', 'vs previous') }}:
                        {{ $prevData['report']->period_label ?: (optional($prevData['report']->period_start)->format('d/m/Y') . ' – ' . optional($prevData['report']->period_end)->format('d/m/Y')) }}
                    </small>
                </h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-0" style="font-size:0.85rem">
                        <thead class="table-light">
                            <tr>
                                <th rowspan="2">{{ localize('medicine', 'Medicine') }}</th>
                                <th colspan="2" class="text-center">{{ localize('dispensed', 'Dispensed') }}</th>
                                <th colspan="2" class="text-center">{{ localize('damaged', 'Damaged') }}</th>
                                <th colspan="2" class="text-center">{{ localize('expired', 'Expired') }}</th>
                                <th colspan="2" class="text-center">{{ localize('closing_stock', 'Closing') }}</th>
                            </tr>
                            <tr>
                                <th class="text-end">{{ localize('prev', 'Prev') }}</th>
                                <th class="text-end">{{ localize('current', 'Current') }}</th>
                                <th class="text-end">{{ localize('prev', 'Prev') }}</th>
                                <th class="text-end">{{ localize('current', 'Current') }}</th>
                                <th class="text-end">{{ localize('prev', 'Prev') }}</th>
                                <th class="text-end">{{ localize('current', 'Current') }}</th>
                                <th class="text-end">{{ localize('prev', 'Prev') }}</th>
                                <th class="text-end">{{ localize('current', 'Current') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($report->items as $item)
                                @php
                                    $prev = $prevData['items'][$item->medicine_id] ?? null;
                                @endphp
                                <tr>
                                    <td>{{ $item->medicine?->name }} <small class="text-muted">({{ $item->medicine?->code }})</small></td>
                                    <td class="text-end">{{ $prev ? number_format($prev['dispensed_qty'], 2) : '-' }}</td>
                                    <td class="text-end {{ $prev && (float)$item->dispensed_qty > $prev['dispensed_qty'] ? 'text-danger' : 'text-success' }}">{{ number_format((float)$item->dispensed_qty, 2) }}</td>
                                    <td class="text-end">{{ $prev ? number_format($prev['damaged_qty'], 2) : '-' }}</td>
                                    <td class="text-end {{ $prev && (float)($item->damaged_qty ?? 0) > $prev['damaged_qty'] ? 'text-danger' : '' }}">{{ number_format((float)($item->damaged_qty ?? 0), 2) }}</td>
                                    <td class="text-end">{{ $prev ? number_format($prev['expired_qty'], 2) : '-' }}</td>
                                    <td class="text-end {{ $prev && (float)$item->expired_qty > $prev['expired_qty'] ? 'text-danger' : '' }}">{{ number_format((float)$item->expired_qty, 2) }}</td>
                                    <td class="text-end">{{ $prev ? number_format($prev['closing_stock'], 2) : '-' }}</td>
                                    <td class="text-end fw-bold">{{ number_format((float)$item->closing_stock, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- Review modal --}}
    @if($canReview ?? false)
    <div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('pharmaceutical.reports.review', $report->id) }}" class="modal-content">
                @csrf
                <input type="hidden" name="action" id="reviewAction" value="review">
                <div class="modal-header">
                    <h6 class="modal-title">{{ localize('review_report', 'Review report') }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">{{ localize('reviewer_note', 'Reviewer note') }}</label>
                        <textarea name="reviewer_note" class="form-control" rows="3" placeholder="{{ localize('optional_note', 'Optional note...') }}"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">{{ localize('confirm', 'Confirm') }}</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ localize('cancel', 'Cancel') }}</button>
                </div>
            </form>
        </div>
    </div>
    @endif
@endsection
