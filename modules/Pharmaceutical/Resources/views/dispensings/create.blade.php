@extends('backend.layouts.app')

@section('title', localize('new_dispensing', 'New Dispensing'))

@section('content')
    <div class="body-content">
        @include('pharmaceutical::_nav')

        <div class="card pharm-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fa fa-hand-holding-medical me-1"></i>{{ localize('new_dispensing', 'ផ្តល់ឱសថថ្មី') }} — {{ $dept->department_name }}</h6>
                <a href="{{ route('pharmaceutical.help', ['article' => 'dispensing']) }}" class="btn btn-sm btn-outline-info">
                    <i class="fa fa-life-ring me-1"></i>{{ localize('help', 'Help') }}
                </a>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('pharmaceutical.dispensings.store') }}" id="dispensingForm">
                    @csrf

                    {{-- Patient Info --}}
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label">{{ localize('dispensing_date', 'Date') }} <span class="text-danger">*</span></label>
                            <input type="date" name="dispensing_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ localize('patient_name', 'ឈ្មោះអ្នកជំងឺ') }} <span class="text-danger">*</span></label>
                            <input type="text" name="patient_name" class="form-control" required value="{{ old('patient_name') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">{{ localize('patient_id_no', 'លេខអត្តសញ្ញាណ') }}</label>
                            <input type="text" name="patient_id_no" class="form-control" value="{{ old('patient_id_no') }}">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">{{ localize('gender', 'ភេទ') }}</label>
                            <select name="patient_gender" class="form-select">
                                <option value="">-</option>
                                <option value="M">{{ localize('male', 'ប្រុស') }}</option>
                                <option value="F">{{ localize('female', 'ស្រី') }}</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">{{ localize('age', 'អាយុ') }}</label>
                            <input type="number" name="patient_age" class="form-control" min="0" max="200" value="{{ old('patient_age') }}">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ localize('diagnosis', 'រោគវិនិច្ឆ័យ') }}</label>
                            <input type="text" name="diagnosis" class="form-control" value="{{ old('diagnosis') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ localize('note', 'កំណត់សម្គាល់') }}</label>
                            <textarea name="note" class="form-control" rows="1">{{ old('note') }}</textarea>
                        </div>
                    </div>

                    {{-- Medicine Items --}}
                    <h6 class="mt-3">{{ localize('medicine_items', 'មុខឱសថផ្តល់') }}</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm" id="itemsTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:30%">{{ localize('medicine', 'ឱសថ') }} <span class="text-danger">*</span></th>
                                    <th style="width:10%">{{ localize('quantity', 'ចំនួន') }} <span class="text-danger">*</span></th>
                                    <th style="width:12%">{{ localize('batch_no', 'Batch') }}</th>
                                    <th style="width:25%">{{ localize('dosage_instruction', 'របៀបប្រើ') }}</th>
                                    <th style="width:10%">{{ localize('duration_days', 'រយៈពេល(ថ្ងៃ)') }}</th>
                                    <th style="width:5%"></th>
                                </tr>
                            </thead>
                            <tbody id="itemsBody">
                                <tr class="item-row">
                                    <td>
                                        <select name="items[0][medicine_id]" class="form-select form-select-sm" required>
                                            <option value="">--{{ localize('select', 'Select') }}--</option>
                                            @foreach($medicines as $med)
                                                <option value="{{ $med->id }}">{{ $med->code }} – {{ $med->name }} ({{ $med->unit }})</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="number" name="items[0][quantity]" class="form-control form-control-sm" step="0.01" min="0.01" required></td>
                                    <td><input type="text" name="items[0][batch_no]" class="form-control form-control-sm"></td>
                                    <td><input type="text" name="items[0][dosage_instruction]" class="form-control form-control-sm" placeholder="ឧ: 1x3 ក្រោយអាហារ"></td>
                                    <td><input type="number" name="items[0][duration_days]" class="form-control form-control-sm" min="1"></td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="fa fa-times"></i></button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-success mb-3" id="addRow">
                        <i class="fa fa-plus me-1"></i>{{ localize('add_row', 'Add row') }}
                    </button>

                    @if($stocks->count())
                        <div class="alert alert-info py-2 small">
                            <strong>{{ localize('available_stock', 'សន្និធិមាន') }}:</strong>
                            @foreach($stocks as $medId => $batches)
                                @php $med = $batches->first()->medicine; @endphp
                                <span class="badge bg-secondary me-1">{{ $med->name }}: {{ number_format($batches->sum('quantity')) }}</span>
                            @endforeach
                        </div>
                    @endif

                    <div class="text-end">
                        <a href="{{ route('pharmaceutical.dispensings.index') }}" class="btn btn-secondary">{{ localize('cancel', 'Cancel') }}</a>
                        <button type="submit" class="btn btn-success"><i class="fa fa-save me-1"></i>{{ localize('save', 'Save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('js')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        let rowIndex = 1;
        document.getElementById('addRow').addEventListener('click', function () {
            const tbody = document.getElementById('itemsBody');
            const firstRow = tbody.querySelector('.item-row');
            const newRow = firstRow.cloneNode(true);

            newRow.querySelectorAll('input, select').forEach(el => {
                el.name = el.name.replace(/\[\d+\]/, '[' + rowIndex + ']');
                if (el.tagName === 'SELECT') el.selectedIndex = 0;
                else el.value = '';
            });
            tbody.appendChild(newRow);
            rowIndex++;
        });

        document.getElementById('itemsTable').addEventListener('click', function (e) {
            if (e.target.closest('.remove-row')) {
                const rows = document.querySelectorAll('#itemsBody .item-row');
                if (rows.length > 1) {
                    e.target.closest('.item-row').remove();
                }
            }
        });
    });
</script>
@endpush
