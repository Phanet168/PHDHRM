@extends('backend.layouts.app')

@section('title', localize('new_report', 'New report'))

@section('content')
    <div class="body-content">
        @include('pharmaceutical::_nav')

        <div class="card pharm-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">{{ localize('new_pharm_report', 'New pharmaceutical report') }}</h6>
                <a href="{{ route('pharmaceutical.help', ['article' => 'reports']) }}" class="btn btn-sm btn-outline-info">
                    <i class="fa fa-life-ring me-1"></i>{{ localize('help', 'Help') }}
                </a>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('pharmaceutical.reports.store') }}" id="reportForm">
                    @csrf
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">{{ localize('facility', 'Facility') }} <span class="text-danger">*</span></label>
                            <select name="department_id" id="departmentSelect" class="form-select" required>
                                <option value="">-- {{ localize('select', 'Select') }} --</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}">{{ $dept->department_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ localize('report_to', 'Report to') }}</label>
                            <input type="hidden" name="parent_department_id" id="parentDeptId" value="">
                            <input type="text" id="parentDeptDisplay" class="form-control" readonly placeholder="--" style="background:#f8f9fa;">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ localize('report_type', 'Report type') }} <span class="text-danger">*</span></label>
                            <select name="report_type" class="form-select" required>
                                @foreach(\Modules\Pharmaceutical\Entities\PharmReport::typeLabels() as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ localize('period_label', 'Period label') }}</label>
                            <input type="text" name="period_label" class="form-control" placeholder="e.g. 2026-03, 2026-Q1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ localize('period_start', 'Period start') }} <span class="text-danger">*</span></label>
                            <input type="date" name="period_start" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ localize('period_end', 'Period end') }} <span class="text-danger">*</span></label>
                            <input type="date" name="period_end" class="form-control" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">{{ localize('note', 'Note') }}</label>
                            <textarea name="note" class="form-control" rows="2"></textarea>
                        </div>
                    </div>

                    <h6 class="d-flex align-items-center">
                        {{ localize('report_items', 'Report items') }}
                        <button type="button" class="btn btn-sm btn-outline-primary ms-3" id="btnAutoGenerate">
                            <i class="fa fa-magic me-1"></i>{{ localize('auto_generate', 'Auto-generate from data') }}
                        </button>
                        <span id="autoGenSpinner" class="spinner-border spinner-border-sm ms-2 d-none" role="status"></span>
                    </h6>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm" id="reportItemsTable">
                            <thead>
                                <tr>
                                    <th style="width:18%">{{ localize('medicine', 'Medicine') }}</th>
                                    <th>{{ localize('opening_stock', 'Opening') }}</th>
                                    <th>{{ localize('received', 'Received') }}</th>
                                    <th>{{ localize('dispensed', 'Dispensed') }}</th>
                                    <th>{{ localize('damaged', 'Damaged') }}</th>
                                    <th>{{ localize('expired', 'Expired') }}</th>
                                    <th>{{ localize('adjustment', 'Adjust') }}</th>
                                    <th>{{ localize('closing_stock', 'Closing') }}</th>
                                    <th>{{ localize('note', 'Note') }}</th>
                                    <th style="width:40px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="rpt-item-row">
                                    <td>
                                        <select name="items[0][medicine_id]" class="form-select form-select-sm" required>
                                            <option value="">--</option>
                                            @foreach($medicines as $med)
                                                <option value="{{ $med->id }}">{{ $med->code }} – {{ $med->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="number" name="items[0][opening_stock]" class="form-control form-control-sm" min="0" step="0.01" required></td>
                                    <td><input type="number" name="items[0][received_qty]" class="form-control form-control-sm" min="0" step="0.01" required></td>
                                    <td><input type="number" name="items[0][dispensed_qty]" class="form-control form-control-sm" min="0" step="0.01" required></td>
                                    <td><input type="number" name="items[0][damaged_qty]" class="form-control form-control-sm" min="0" step="0.01"></td>
                                    <td><input type="number" name="items[0][expired_qty]" class="form-control form-control-sm" min="0" step="0.01"></td>
                                    <td><input type="number" name="items[0][adjustment_qty]" class="form-control form-control-sm" step="0.01"></td>
                                    <td><input type="number" name="items[0][closing_stock]" class="form-control form-control-sm" min="0" step="0.01" required></td>
                                    <td><input type="text" name="items[0][note]" class="form-control form-control-sm"></td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger remove-rpt-row"><i class="fa fa-times"></i></button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary mb-3" id="addRptRow">
                        <i class="fa fa-plus me-1"></i>{{ localize('add_item', 'Add item') }}
                    </button>

                    <div>
                        <button type="submit" class="btn btn-success">{{ localize('save', 'Save') }}</button>
                        <a href="{{ route('pharmaceutical.reports.index') }}" class="btn btn-secondary">{{ localize('cancel', 'Cancel') }}</a>
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

    // ── Parent department auto-fill ──
    var parentMap = @json($parentMap ?? []);
    var deptSelect = document.getElementById('departmentSelect');
    var parentIdField = document.getElementById('parentDeptId');
    var parentDisplay = document.getElementById('parentDeptDisplay');

    if (deptSelect) {
        deptSelect.addEventListener('change', function() {
            var id = this.value;
            if (parentMap[id]) {
                parentIdField.value = parentMap[id].id;
                parentDisplay.value = parentMap[id].name;
            } else {
                parentIdField.value = '';
                parentDisplay.value = '--';
            }
        });
    }

    // ── Auto-calculate closing stock ──
    function calcClosing(row) {
        var opening = parseFloat(row.querySelector('[name$="[opening_stock]"]').value) || 0;
        var received = parseFloat(row.querySelector('[name$="[received_qty]"]').value) || 0;
        var dispensed = parseFloat(row.querySelector('[name$="[dispensed_qty]"]').value) || 0;
        var damaged = parseFloat(row.querySelector('[name$="[damaged_qty]"]').value) || 0;
        var expired = parseFloat(row.querySelector('[name$="[expired_qty]"]').value) || 0;
        var adjustment = parseFloat(row.querySelector('[name$="[adjustment_qty]"]').value) || 0;
        var closing = opening + received - dispensed - damaged - expired + adjustment;
        row.querySelector('[name$="[closing_stock]"]').value = Math.max(0, closing).toFixed(2);
    }

    document.querySelector('#reportItemsTable').addEventListener('input', function(e) {
        var field = e.target.name || '';
        if (field.match(/opening_stock|received_qty|dispensed_qty|damaged_qty|adjustment_qty|expired_qty/)) {
            calcClosing(e.target.closest('.rpt-item-row'));
        }
    });

    // ── Dynamic rows ──
    let idx = 1;
    document.getElementById('addRptRow').addEventListener('click', function() {
        const tbody = document.querySelector('#reportItemsTable tbody');
        const firstRow = tbody.querySelector('.rpt-item-row');
        const clone = firstRow.cloneNode(true);
        clone.querySelectorAll('input, select').forEach(function(el) {
            el.name = el.name.replace(/\[\d+\]/, '[' + idx + ']');
            if (el.tagName === 'SELECT') el.selectedIndex = 0;
            else el.value = '';
        });
        tbody.appendChild(clone);
        idx++;
    });
    document.querySelector('#reportItemsTable').addEventListener('click', function(e) {
        if (e.target.closest('.remove-rpt-row')) {
            const rows = document.querySelectorAll('#reportItemsTable tbody .rpt-item-row');
            if (rows.length > 1) e.target.closest('.rpt-item-row').remove();
        }
    });

    // ── Auto-generate from actual data ──
    var medicines = @json($medicines->keyBy('id'));
    document.getElementById('btnAutoGenerate').addEventListener('click', function() {
        var deptId = deptSelect.value;
        var periodStart = document.querySelector('[name="period_start"]').value;
        var periodEnd = document.querySelector('[name="period_end"]').value;

        if (!deptId || !periodStart || !periodEnd) {
            alert('{{ localize("select_facility_period", "Please select facility and period dates first.") }}');
            return;
        }

        var spinner = document.getElementById('autoGenSpinner');
        spinner.classList.remove('d-none');

        fetch('{{ route("pharmaceutical.reports.generate-data") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ department_id: deptId, period_start: periodStart, period_end: periodEnd })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            spinner.classList.add('d-none');
            if (!data.success || !data.items.length) {
                alert('{{ localize("no_data_found", "No transaction data found for this period.") }}');
                return;
            }
            // Clear existing rows
            var tbody = document.querySelector('#reportItemsTable tbody');
            tbody.innerHTML = '';
            idx = 0;

            data.items.forEach(function(item) {
                var row = document.createElement('tr');
                row.className = 'rpt-item-row';

                // Medicine select
                var medOpts = '<option value="">--</option>';
                @foreach($medicines as $med)
                medOpts += '<option value="{{ $med->id }}"' + (item.medicine_id == {{ $med->id }} ? ' selected' : '') + '>{{ $med->code }} – {{ addslashes($med->name) }}</option>';
                @endforeach

                row.innerHTML =
                    '<td><select name="items['+idx+'][medicine_id]" class="form-select form-select-sm" required>' + medOpts + '</select></td>' +
                    '<td><input type="number" name="items['+idx+'][opening_stock]" class="form-control form-control-sm" min="0" step="0.01" value="'+item.opening_stock+'" required></td>' +
                    '<td><input type="number" name="items['+idx+'][received_qty]" class="form-control form-control-sm" min="0" step="0.01" value="'+item.received_qty+'" required></td>' +
                    '<td><input type="number" name="items['+idx+'][dispensed_qty]" class="form-control form-control-sm" min="0" step="0.01" value="'+item.dispensed_qty+'" required></td>' +
                    '<td><input type="number" name="items['+idx+'][damaged_qty]" class="form-control form-control-sm" min="0" step="0.01" value="'+item.damaged_qty+'"></td>' +
                    '<td><input type="number" name="items['+idx+'][expired_qty]" class="form-control form-control-sm" min="0" step="0.01" value="'+item.expired_qty+'"></td>' +
                    '<td><input type="number" name="items['+idx+'][adjustment_qty]" class="form-control form-control-sm" step="0.01" value="'+item.adjustment_qty+'"></td>' +
                    '<td><input type="number" name="items['+idx+'][closing_stock]" class="form-control form-control-sm" min="0" step="0.01" value="'+item.closing_stock+'" required></td>' +
                    '<td><input type="text" name="items['+idx+'][note]" class="form-control form-control-sm"></td>' +
                    '<td><button type="button" class="btn btn-sm btn-outline-danger remove-rpt-row"><i class="fa fa-times"></i></button></td>';
                tbody.appendChild(row);
                idx++;
            });
        })
        .catch(function(err) {
            spinner.classList.add('d-none');
            console.error(err);
            alert('Error generating data.');
        });
    });
})();
</script>
@endpush
