@extends('backend.layouts.app')
@section('title', 'គ្រប់គ្រងឡើងតួនាទី')

@section('content')
    @include('humanresource::employee_header')
    @include('backend.layouts.common.validation')

    @php
        $fixKhmerText = function ($text) {
            $value = trim((string) $text);
            if ($value === '') {
                return '';
            }

            $looksMojibake = str_contains($value, 'á') || str_contains($value, 'â') || str_contains($value, 'Ã');
            if (!$looksMojibake) {
                return $value;
            }

            $iconv = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);
            if (is_string($iconv) && $iconv !== '' && preg_match('/\p{Khmer}/u', $iconv)) {
                return trim($iconv);
            }

            $mb = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
            if (is_string($mb) && $mb !== '' && preg_match('/\p{Khmer}/u', $mb)) {
                return trim($mb);
            }

            return $value;
        };
    @endphp

    <div class="card mb-3 fixed-tab-body">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="fs-17 fw-semi-bold mb-0">គ្រប់គ្រងឡើងតួនាទី</h6>
                <div class="d-flex gap-2">
                    <form method="GET" action="{{ route('employee-position-promotions.index') }}" class="d-flex gap-2">
                        <input type="number" class="form-control form-control-sm" name="year" value="{{ $year }}"
                            min="1950" max="2100" style="width: 120px;">
                        <button type="submit" class="btn btn-sm btn-primary">បង្ហាញឆ្នាំ</button>
                    </form>
                    <a href="{{ route('employee-position-promotions.export', ['year' => $year]) }}"
                        class="btn btn-sm btn-outline-success">
                        <i class="fa fa-download me-1"></i> CSV
                    </a>
                </div>
            </div>
        </div>

        <div class="card-body">
            <div class="alert alert-info mb-3">
                រាល់ពេលឡើងតួនាទី ប្រព័ន្ធនឹងកែ <strong>តួនាទីបច្ចុប្បន្ន</strong> និងកត់ត្រា
                <strong>ប្រវត្តការងារ</strong> ដោយស្វ័យប្រវត្តិ។
            </div>

            <form action="{{ route('employee-position-promotions.store') }}" method="POST" class="mb-4">
                @csrf

                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">ជ្រើសមន្ត្រី <span class="text-danger">*</span></label>
                        <select id="employee_id" name="employee_id" class="form-select" required>
                            <option value="">-- ជ្រើសមន្ត្រី --</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}"
                                    data-current-position="{{ $fixKhmerText($current_position_labels[$employee->id] ?? '-') }}"
                                    {{ (int) old('employee_id') === (int) $employee->id ? 'selected' : '' }}>
                                    {{ $employee->employee_id }} - {{ $fixKhmerText($employee->full_name) }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">អាចស្វែងរកតាមលេខសម្គាល់ ឬ ឈ្មោះបាន</small>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">តួនាទីបច្ចុប្បន្ន</label>
                        <input type="text" id="current_position_label" class="form-control bg-light" readonly value="-">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">តួនាទីថ្មី <span class="text-danger">*</span></label>
                        <select name="position_id" class="form-select" required>
                            <option value="">-- ជ្រើសតួនាទីថ្មី --</option>
                            @foreach ($positions as $position)
                                @php
                                    $positionLabel = $position->position_name_km ?: $position->position_name;
                                @endphp
                                <option value="{{ $position->id }}" {{ (int) old('position_id') === (int) $position->id ? 'selected' : '' }}>
                                    {{ $fixKhmerText($positionLabel) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="row g-3 mt-0">
                    <div class="col-md-3">
                        <label class="form-label">ថ្ងៃមានប្រសិទ្ធិភាព <span class="text-danger">*</span></label>
                        <input type="date" name="effective_date" class="form-control"
                            value="{{ old('effective_date', now()->toDateString()) }}" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">លេខលិខិត</label>
                        <input type="text" name="document_reference" class="form-control"
                            value="{{ old('document_reference') }}" placeholder="ឧ. ១២៣/២៦">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">ថ្ងៃខែលិខិត</label>
                        <input type="date" name="document_date" class="form-control" value="{{ old('document_date') }}">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">កំណត់សម្គាល់</label>
                        <input type="text" name="note" class="form-control" value="{{ old('note') }}"
                            placeholder="ព័ត៌មានបន្ថែម (ប្រសិនបើមាន)">
                    </div>
                </div>

                <div class="text-end mt-3">
                    <button type="submit" class="btn btn-success">
                        <i class="fa fa-save me-1"></i> រក្សាទុក
                    </button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead>
                        <tr>
                            <th width="5%">ល.រ</th>
                            <th>មន្ត្រី</th>
                            <th>អង្គភាព</th>
                            <th>តួនាទីចាស់</th>
                            <th>តួនាទីថ្មី</th>
                            <th>ថ្ងៃមានប្រសិទ្ធិភាព</th>
                            <th>លេខលិខិត</th>
                            <th>ថ្ងៃខែលិខិត</th>
                            <th>សម្គាល់</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($promotions as $promotion)
                            @php
                                $unit = $promotion->employee?->sub_department?->department_name
                                    ?: ($promotion->employee?->department?->department_name ?: '-');
                                $newPosition = $promotion->position?->position_name_km
                                    ?: ($promotion->position?->position_name ?: '-');
                                $oldPosition = $previous_position_labels[$promotion->id] ?? '-';
                                $displayNote = trim(str_replace('[POSITION_PROMOTION] |', '', (string) $promotion->note));
                            @endphp
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    {{ $promotion->employee?->employee_id }} -
                                    {{ $fixKhmerText($promotion->employee?->full_name ?? '-') }}
                                </td>
                                <td>{{ $fixKhmerText($unit) }}</td>
                                <td>{{ $fixKhmerText($oldPosition) }}</td>
                                <td>{{ $fixKhmerText($newPosition) }}</td>
                                <td>{{ optional($promotion->start_date)->format('Y-m-d') ?: '-' }}</td>
                                <td>{{ $promotion_documents[$promotion->id]['document_reference'] ?? '-' }}</td>
                                <td>{{ $promotion_documents[$promotion->id]['document_date'] ?? '-' }}</td>
                                <td>{{ $fixKhmerText($displayNote !== '' ? $displayNote : '-') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted">មិនទាន់មានទិន្នន័យឡើងតួនាទីក្នុងឆ្នាំនេះទេ</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('js')
    <script>
        (function() {
            "use strict";

            var employeeSelect = document.getElementById('employee_id');
            var currentPositionInput = document.getElementById('current_position_label');

            var refreshCurrentPosition = function() {
                if (!employeeSelect || !currentPositionInput) {
                    return;
                }

                var option = employeeSelect.options[employeeSelect.selectedIndex];
                if (!option || !option.value) {
                    currentPositionInput.value = '-';
                    return;
                }

                currentPositionInput.value = option.getAttribute('data-current-position') || '-';
            };

            if (employeeSelect) {
                employeeSelect.addEventListener('change', refreshCurrentPosition);
            }
            refreshCurrentPosition();
        })();

        (function($) {
            "use strict";
            if (!$ || !$.fn || !$.fn.select2) {
                return;
            }

            $('#employee_id').select2({
                width: '100%',
                allowClear: true,
                placeholder: '-- ស្វែងរកមន្ត្រី --'
            });

            $('#employee_id').on('select2:select select2:clear', function() {
                this.dispatchEvent(new Event('change'));
            });
        })(window.jQuery);
    </script>
@endpush

