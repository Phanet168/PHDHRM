@extends('backend.layouts.app')

@section('title', localize('new_distribution', 'បង្កើតការចែកចាយ'))

@section('content')
    <div class="body-content">
        @include('pharmaceutical::_nav')

        <div class="card pharm-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">{{ localize('new_distribution', 'បង្កើតការចែកចាយ') }} – {{ \Modules\Pharmaceutical\Entities\PharmDistribution::typeLabels()[$type] ?? $type }}</h6>
                <a href="{{ route('pharmaceutical.help', ['article' => 'distributions']) }}" class="btn btn-sm btn-outline-info">
                    <i class="fa fa-life-ring me-1"></i>{{ localize('help', 'ជំនួយ') }}
                </a>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('pharmaceutical.distributions.store') }}" id="distributionForm">
                    @csrf
                    <input type="hidden" name="distribution_type" value="{{ $type }}">

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">{{ localize('from_facility', 'ពីអង្គភាព') }} <span class="text-danger">*</span></label>
                            <select name="from_department_id" class="form-select" required>
                                <option value="">-- {{ localize('select', 'ជ្រើសរើស') }} --</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}">{{ $dept->department_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ localize('to_facility', 'ទៅអង្គភាព') }} <span class="text-danger">*</span></label>
                            <select name="to_department_id" class="form-select" required>
                                <option value="">-- {{ localize('select', 'ជ្រើសរើស') }} --</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}">{{ $dept->department_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ localize('distribution_date', 'កាលបរិច្ឆេទចែកចាយ') }} <span class="text-danger">*</span></label>
                            <input type="date" name="distribution_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">{{ localize('note', 'កំណត់សម្គាល់') }}</label>
                            <textarea name="note" class="form-control" rows="2"></textarea>
                        </div>
                    </div>

                    <h6>{{ localize('medicine_items', 'មុខឱសថ') }}</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm" id="itemsTable">
                            <thead>
                                <tr>
                                    <th style="width:30%">{{ localize('medicine', 'ឱសថ') }}</th>
                                    <th>{{ localize('quantity', 'បរិមាណ') }}</th>
                                    <th>{{ localize('batch_no', 'លេខបាច់') }}</th>
                                    <th>{{ localize('expiry_date', 'កាលបរិច្ឆេទផុតកំណត់') }}</th>
                                    <th>{{ localize('unit_price', 'តម្លៃឯកតា') }}</th>
                                    <th style="width:50px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="item-row">
                                    <td>
                                        <select name="items[0][medicine_id]" class="form-select form-select-sm" required>
                                            <option value="">--</option>
                                            @foreach($medicines as $med)
                                                <option value="{{ $med->id }}">{{ $med->code }} – {{ $med->name }} ({{ $med->unit }})</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="number" name="items[0][quantity_sent]" class="form-control form-control-sm" min="0.01" step="0.01" required></td>
                                    <td><input type="text" name="items[0][batch_no]" class="form-control form-control-sm"></td>
                                    <td><input type="date" name="items[0][expiry_date]" class="form-control form-control-sm"></td>
                                    <td><input type="number" name="items[0][unit_price]" class="form-control form-control-sm" step="0.01" min="0"></td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="fa fa-times"></i></button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary mb-3" id="addRow">
                        <i class="fa fa-plus me-1"></i>{{ localize('add_item', 'បន្ថែមមុខឱសថ') }}
                    </button>

                    <div>
                        <button type="submit" class="btn btn-success">{{ localize('save', 'រក្សាទុក') }}</button>
                        <a href="{{ route('pharmaceutical.distributions.index') }}" class="btn btn-secondary">{{ localize('cancel', 'បោះបង់') }}</a>
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
    document.getElementById('addRow').addEventListener('click', function() {
        const tbody = document.querySelector('#itemsTable tbody');
        const firstRow = tbody.querySelector('.item-row');
        const clone = firstRow.cloneNode(true);
        clone.querySelectorAll('input, select').forEach(function(el) {
            el.name = el.name.replace(/\[\d+\]/, '[' + idx + ']');
            if (el.tagName === 'SELECT') el.selectedIndex = 0;
            else el.value = '';
        });
        tbody.appendChild(clone);
        idx++;
    });
    document.querySelector('#itemsTable').addEventListener('click', function(e) {
        if (e.target.closest('.remove-row')) {
            const rows = document.querySelectorAll('#itemsTable tbody .item-row');
            if (rows.length > 1) e.target.closest('.item-row').remove();
        }
    });
})();
</script>
@endpush
