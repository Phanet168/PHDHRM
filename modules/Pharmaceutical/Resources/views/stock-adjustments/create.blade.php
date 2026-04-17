@extends('backend.layouts.app')

@section('title', localize('new_stock_adjustment', 'New Stock Adjustment'))

@section('content')
    <div class="body-content">
        @include('pharmaceutical::_nav')

        @php
            $adjustmentTypeOptions = [
                \Modules\Pharmaceutical\Entities\PharmStockAdjustment::TYPE_DAMAGED => localize('damaged_deduct_stock', 'ខូចខាត (ដកចេញពីស្តុក)'),
                \Modules\Pharmaceutical\Entities\PharmStockAdjustment::TYPE_EXPIRED => localize('expired_deduct_stock', 'ផុតកំណត់ (ដកចេញពីស្តុក)'),
                \Modules\Pharmaceutical\Entities\PharmStockAdjustment::TYPE_LOSS => localize('loss_deduct_stock', 'បាត់បង់ (ដកចេញពីស្តុក)'),
                \Modules\Pharmaceutical\Entities\PharmStockAdjustment::TYPE_CORRECTION => localize('correction_add_stock', 'កែតម្រូវ (បូកចូលស្តុក)'),
            ];
        @endphp

        <div class="card pharm-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">{{ localize('new_stock_adjustment', 'New stock adjustment') }}</h6>
                <a href="{{ route('pharmaceutical.help', ['article' => 'adjustments']) }}" class="btn btn-sm btn-outline-info">
                    <i class="fa fa-life-ring me-1"></i>{{ localize('help', 'Help') }}
                </a>
            </div>
            <div class="card-body">
                <div class="alert alert-info py-2">
                    <i class="fa fa-info-circle me-1"></i>
                    {{ localize('stock_adjustment_form_help', 'Form នេះប្រើសម្រាប់កែស្តុកជាក់ស្តែង: ខូចខាត, ផុតកំណត់, បាត់បង់ ឬកែតម្រូវចំនួនស្តុក។') }}
                </div>

                <form method="POST" action="{{ route('pharmaceutical.stock-adjustments.store') }}" id="adjForm">
                    @csrf
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">{{ localize('facility', 'Facility') }} <span class="text-danger">*</span></label>
                            <select name="department_id" class="form-select" required>
                                <option value="">-- {{ localize('select', 'Select') }} --</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}">{{ $dept->department_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <h6>{{ localize('adjustment_items', 'Adjustment items') }}</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm" id="adjItemsTable">
                            <thead>
                                <tr>
                                    <th style="width:22%">{{ localize('medicine', 'Medicine') }}</th>
                                    <th style="width:14%">{{ localize('type', 'Type') }}</th>
                                    <th style="width:10%">{{ localize('quantity', 'Qty') }}</th>
                                    <th style="width:10%">{{ localize('batch_no', 'Batch') }}</th>
                                    <th style="width:10%">{{ localize('expiry_date', 'Expiry') }}</th>
                                    <th style="width:10%">{{ localize('date', 'Date') }}</th>
                                    <th>{{ localize('reason', 'Reason') }}</th>
                                    <th style="width:40px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="adj-item-row">
                                    <td>
                                        <select name="items[0][medicine_id]" class="form-select form-select-sm" required>
                                            <option value="">--</option>
                                            @foreach($medicines as $med)
                                                <option value="{{ $med->id }}">{{ $med->code }} – {{ $med->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="items[0][adjustment_type]" class="form-select form-select-sm" required>
                                            @foreach($adjustmentTypeOptions as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="number" name="items[0][quantity]" class="form-control form-control-sm" min="0.01" step="0.01" required></td>
                                    <td><input type="text" name="items[0][batch_no]" class="form-control form-control-sm"></td>
                                    <td><input type="date" name="items[0][expiry_date]" class="form-control form-control-sm"></td>
                                    <td><input type="date" name="items[0][adjustment_date]" class="form-control form-control-sm" value="{{ now()->format('Y-m-d') }}" required></td>
                                    <td><input type="text" name="items[0][reason]" class="form-control form-control-sm"></td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger remove-adj-row"><i class="fa fa-times"></i></button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary mb-3" id="addAdjRow">
                        <i class="fa fa-plus me-1"></i>{{ localize('add_item', 'Add item') }}
                    </button>

                    <div class="small text-muted mb-3">
                        <div>{{ localize('adjustment_note_deduct', 'ខូចខាត / ផុតកំណត់ / បាត់បង់ = ដកចេញពីស្តុក') }}</div>
                        <div>{{ localize('adjustment_note_add', 'កែតម្រូវ = បូកចូលស្តុក') }}</div>
                    </div>

                    <div>
                        <button type="submit" class="btn btn-success">{{ localize('save', 'Save') }}</button>
                        <a href="{{ route('pharmaceutical.stock-adjustments.index') }}" class="btn btn-secondary">{{ localize('cancel', 'Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('js')
<script>
(function() {
    "use strict";
    let idx = 1;
    document.getElementById('addAdjRow').addEventListener('click', function() {
        const tbody = document.querySelector('#adjItemsTable tbody');
        const firstRow = tbody.querySelector('.adj-item-row');
        const clone = firstRow.cloneNode(true);
        clone.querySelectorAll('input, select').forEach(function(el) {
            el.name = el.name.replace(/\[\d+\]/, '[' + idx + ']');
            if (el.tagName === 'SELECT') el.selectedIndex = 0;
            else if (el.type !== 'date') el.value = '';
        });
        tbody.appendChild(clone);
        idx++;
    });
    document.querySelector('#adjItemsTable').addEventListener('click', function(e) {
        if (e.target.closest('.remove-adj-row')) {
            const rows = document.querySelectorAll('#adjItemsTable tbody .adj-item-row');
            if (rows.length > 1) e.target.closest('.adj-item-row').remove();
        }
    });
})();
</script>
@endpush
