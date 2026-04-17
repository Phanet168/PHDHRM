@extends('backend.layouts.app')

@section('title', localize('distribution_detail', 'ព័ត៌មានការចែកចាយ'))

@section('content')
    <div class="body-content">
        @include('pharmaceutical::_nav')

        <div class="card pharm-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">{{ localize('distribution_detail', 'ព័ត៌មានការចែកចាយ') }} – {{ $distribution->reference_no }}</h6>
                <a href="{{ route('pharmaceutical.distributions.index') }}" class="btn btn-sm btn-secondary">{{ localize('back', 'ត្រឡប់ក្រោយ') }}</a>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><strong>{{ localize('type', 'ប្រភេទ') }}:</strong> {{ $distribution->type_label }}</div>
                    <div class="col-md-3"><strong>{{ localize('status', 'ស្ថានភាព') }}:</strong> <span class="badge bg-secondary">{{ $distribution->status_label }}</span></div>
                    <div class="col-md-3"><strong>{{ localize('distribution_date', 'កាលបរិច្ឆេទចែកចាយ') }}:</strong> {{ optional($distribution->distribution_date)->format('d/m/Y') ?: '-' }}</div>
                    <div class="col-md-3"><strong>{{ localize('received_date', 'កាលបរិច្ឆេទទទួល') }}:</strong> {{ optional($distribution->received_date)->format('d/m/Y') ?: '-' }}</div>
                    <div class="col-md-6"><strong>{{ localize('from', 'ពី') }}:</strong> {{ $distribution->fromDepartment?->department_name ?: '-' }}</div>
                    <div class="col-md-6"><strong>{{ localize('to', 'ទៅ') }}:</strong> {{ $distribution->toDepartment?->department_name ?: '-' }}</div>
                    @if($distribution->note)
                        <div class="col-md-12"><strong>{{ localize('note', 'កំណត់សម្គាល់') }}:</strong> {{ $distribution->note }}</div>
                    @endif
                </div>

                {{-- Action buttons --}}
                @if(($canDistribute ?? false) && $distribution->status === \Modules\Pharmaceutical\Entities\PharmDistribution::STATUS_DRAFT)
                    <form method="POST" action="{{ route('pharmaceutical.distributions.send', $distribution->id) }}" class="d-inline" onsubmit="return confirm('{{ localize('confirm_send', 'បញ្ជាក់ការផ្ញើ?') }}')">
                        @csrf
                        <button class="btn btn-primary btn-sm"><i class="fa fa-paper-plane me-1"></i>{{ localize('send', 'ផ្ញើ') }}</button>
                    </form>
                @endif

                @if(($canReceive ?? false) && in_array($distribution->status, [\Modules\Pharmaceutical\Entities\PharmDistribution::STATUS_SENT, \Modules\Pharmaceutical\Entities\PharmDistribution::STATUS_PARTIAL]))
                    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#receiveModal">
                        <i class="fa fa-check me-1"></i>{{ localize('receive', 'ទទួល') }}
                    </button>
                @endif
            </div>
        </div>

        {{-- Items --}}
        <div class="card pharm-card">
            <div class="card-header">
                <h6 class="mb-0">{{ localize('items', 'មុខឱសថ') }}</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ localize('sl', 'SL') }}</th>
                                <th>{{ localize('medicine', 'ឱសថ') }}</th>
                                <th>{{ localize('category', 'ប្រភេទឱសថ') }}</th>
                                <th>{{ localize('batch_no', 'លេខបាច់') }}</th>
                                <th>{{ localize('expiry_date', 'ផុតកំណត់') }}</th>
                                <th class="text-end">{{ localize('qty_sent', 'បរិមាណផ្ញើ') }}</th>
                                <th class="text-end">{{ localize('qty_received', 'បរិមាណទទួល') }}</th>
                                <th class="text-end">{{ localize('unit_price', 'តម្លៃឯកតា') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($distribution->items as $item)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $item->medicine?->name ?: '-' }} ({{ $item->medicine?->code }})</td>
                                    <td>{{ $item->medicine?->category?->name ?: '-' }}</td>
                                    <td>{{ $item->batch_no ?: '-' }}</td>
                                    <td>{{ optional($item->expiry_date)->format('d/m/Y') ?: '-' }}</td>
                                    <td class="text-end">{{ number_format((float) $item->quantity_sent, 2) }}</td>
                                    <td class="text-end">{{ number_format((float) $item->quantity_received, 2) }}</td>
                                    <td class="text-end">{{ number_format((float) $item->unit_price, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Receive modal --}}
    @if(($canReceive ?? false) && in_array($distribution->status, [\Modules\Pharmaceutical\Entities\PharmDistribution::STATUS_SENT, \Modules\Pharmaceutical\Entities\PharmDistribution::STATUS_PARTIAL]))
    <div class="modal fade" id="receiveModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form method="POST" action="{{ route('pharmaceutical.distributions.receive', $distribution->id) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title">{{ localize('receive_items', 'ទទួលមុខឱសថ') }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>{{ localize('medicine', 'ឱសថ') }}</th>
                                    <th>{{ localize('qty_sent', 'បរិមាណផ្ញើ') }}</th>
                                    <th>{{ localize('qty_received', 'បរិមាណទទួល') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($distribution->items as $i => $item)
                                    <tr>
                                        <td>
                                            {{ $item->medicine?->name }} ({{ $item->medicine?->code }})
                                            <input type="hidden" name="items[{{ $i }}][id]" value="{{ $item->id }}">
                                        </td>
                                        <td>{{ number_format((float) $item->quantity_sent, 2) }}</td>
                                        <td>
                                            <input type="number" name="items[{{ $i }}][quantity_received]" class="form-control form-control-sm"
                                                   value="{{ (float) $item->quantity_sent }}" min="0" step="0.01" required>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">{{ localize('received_note', 'កំណត់សម្គាល់ពេលទទួល') }}</label>
                        <textarea name="received_note" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">{{ localize('confirm_receive', 'បញ្ជាក់ទទួល') }}</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ localize('close', 'បិទ') }}</button>
                </div>
            </form>
        </div>
    </div>
    @endif
@endsection
