@extends('backend.layouts.app')
@section('title', 'គ្រប់គ្រងប្រវត្តការងារ')
@push('css')
    <link rel="stylesheet" href="{{ module_asset('HumanResource/css/employee.css') }}">
@endpush

@section('content')
    @include('humanresource::employee_header')

    @php
        $workHistoryRows = old('work_histories');
        if (!is_array($workHistoryRows)) {
            $workHistoryRows = $employee->workHistories
                ->map(function ($r) {
                    return [
                        'id' => $r->id,
                        'work_status_name' => $r->work_status_name,
                        'start_date' => optional($r->start_date)->format('Y-m-d'),
                        'document_reference' => $r->document_reference,
                        'document_date' => optional($r->document_date)->format('Y-m-d'),
                        'note' => $r->note,
                    ];
                })
                ->toArray();
        }
        if (empty($workHistoryRows)) {
            $workHistoryRows = [[]];
        }

        $incentiveRows = old('incentives');
        if (!is_array($incentiveRows)) {
            $incentiveRows = $employee->incentives->map(fn($r) => $r->toArray())->toArray();
        }
        if (empty($incentiveRows)) {
            $incentiveRows = [[]];
        }

        $unitLabel = $employee?->sub_department?->department_name ?: ($employee?->department?->department_name ?: '-');
        $positionLabel = $employee?->position?->position_name_km ?: ($employee?->position?->position_name ?: '-');
        $workStatusOptions = collect($work_status_options ?? [])->filter()->values();
        $workStatusOptionsJs = $workStatusOptions->values()->all();
    @endphp

    <div class="card mb-3 fixed-tab-body">
        @include('backend.layouts.common.validation')
        <div class="card-header">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h6 class="fs-17 fw-semi-bold mb-0">គ្រប់គ្រងប្រវត្តការងារ និងការលើកទឹកចិត្ត</h6>
                    <div class="small text-muted mt-1">
                        {{ $employee->full_name }} | អង្គភាព: {{ $unitLabel }} | តួនាទី: {{ $positionLabel }}
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('employees.edit', $employee->id) }}" class="btn btn-secondary btn-sm">
                        <i class="fa fa-arrow-left me-1"></i> ត្រឡប់ទៅកែព័ត៌មានមន្រ្តី
                    </a>
                    <a href="{{ route('employee-pay-promotions.index') }}" class="btn btn-outline-primary btn-sm">
                        <i class="fa fa-line-chart me-1"></i> គ្រប់គ្រងឡើងកាំប្រាក់
                    </a>
                    <a href="{{ route('employees.show', $employee->id) }}" class="btn btn-info btn-sm text-white">
                        <i class="fa fa-eye me-1"></i> មើលព័ត៌មានមន្រ្តី
                    </a>
                </div>
            </div>
        </div>

        <div class="card-body">
            <div class="alert alert-success mb-3">
                <form action="{{ route('employees.status_transition.store', $employee->id) }}" method="POST" class="row g-2 align-items-end">
                    @csrf
                    <input type="hidden" name="to_work_status_name" value="{{ $return_to_work_status_name ?? 'ចូលបម្រើការងារវិញ' }}">
                    <input type="hidden" name="transition_type" value="return_to_work">
                    <div class="col-md-3">
                        <label class="form-label mb-1">ស្ថានភាព</label>
                        <input type="text" class="form-control" readonly value="{{ $return_to_work_status_name ?? 'ចូលបម្រើការងារវិញ' }}">
                    </div>
                    <div class="col-md-2">
                        <label for="return_to_work_effective_date" class="form-label mb-1">ថ្ងៃចូលវិញ <span class="text-danger">*</span></label>
                        <input type="date" id="return_to_work_effective_date" name="effective_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="col-md-3">
                        <label for="return_to_work_document_reference" class="form-label mb-1">លេខឯកសារ</label>
                        <input type="text" id="return_to_work_document_reference" name="document_reference" class="form-control" placeholder="ប្រកាស/សេចក្តីសម្រេច">
                    </div>
                    <div class="col-md-2">
                        <label for="return_to_work_document_date" class="form-label mb-1">កាលបរិច្ឆេទលិខិត</label>
                        <input type="date" id="return_to_work_document_date" name="document_date" class="form-control">
                    </div>
                    <div class="col-md-2 text-end">
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fa fa-sign-in me-1"></i> ចូលបម្រើការងារវិញ
                        </button>
                    </div>
                </form>
            </div>

            <form action="{{ route('employees.career_management.update', $employee->id) }}" method="POST">
                @csrf
                @method('PATCH')

                <div class="gov-section-card mb-3">
                    <h6 class="gov-section-title">ប្រវត្តការងារ</h6>
                    <div class="table-responsive mb-2">
                        <table class="table table-bordered" id="work-history-table">
                            <thead>
                                <tr>
                                    <th>ស្ថានភាពការងារ</th>
                                    <th>ថ្ងៃចាប់ផ្តើម</th>
                                    <th>ប្រកាស/សេចក្តីសម្រេច</th>
                                    <th>កាលបរិច្ឆេទលិខិត</th>
                                    <th>សម្គាល់</th>
                                    <th width="80">សកម្មភាព</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($workHistoryRows as $idx => $row)
                                    <tr>
                                        <td>
                                            <input type="hidden" name="work_histories[{{ $idx }}][id]"
                                                value="{{ $row['id'] ?? '' }}">
                                            <select name="work_histories[{{ $idx }}][work_status_name]" class="form-select">
                                                <option value="">-- ជ្រើសស្ថានភាព --</option>
                                                @foreach ($workStatusOptions as $statusOption)
                                                    <option value="{{ $statusOption }}"
                                                        {{ (($row['work_status_name'] ?? '') === $statusOption) ? 'selected' : '' }}>
                                                        {{ $statusOption }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td><input type="date" name="work_histories[{{ $idx }}][start_date]"
                                                class="form-control" value="{{ $row['start_date'] ?? '' }}"></td>
                                        <td><input type="text" name="work_histories[{{ $idx }}][document_reference]"
                                                class="form-control" value="{{ $row['document_reference'] ?? '' }}"></td>
                                        <td><input type="date" name="work_histories[{{ $idx }}][document_date]"
                                                class="form-control" value="{{ $row['document_date'] ?? '' }}"></td>
                                        <td><input type="text" name="work_histories[{{ $idx }}][note]"
                                                class="form-control" value="{{ $row['note'] ?? '' }}"></td>
                                        <td><button type="button" class="btn btn-sm btn-danger repeater-remove">លុប</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary repeater-add" data-target="#work-history-table"
                        data-repeater="work_histories">+ បន្ថែម</button>
                </div>

                <div class="gov-section-card mb-3">
                    <h6 class="gov-section-title">ការលើកទឹកចិត្ត</h6>
                    <div class="table-responsive mb-2">
                        <table class="table table-bordered" id="incentive-table">
                            <thead>
                                <tr>
                                    <th>កាលបរិច្ឆេទ</th>
                                    <th>ឋានានុក្រម</th>
                                    <th>ជាតិ/ប្រភេទចម្រុះ</th>
                                    <th>ប្រភេទ</th>
                                    <th>ថ្នាក់</th>
                                    <th>មូលហេតុ</th>
                                    <th width="80">សកម្មភាព</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($incentiveRows as $idx => $row)
                                    <tr>
                                        <td><input type="date" name="incentives[{{ $idx }}][incentive_date]"
                                                class="form-control" value="{{ $row['incentive_date'] ?? '' }}"></td>
                                        <td><input type="text" name="incentives[{{ $idx }}][hierarchy_level]"
                                                class="form-control" value="{{ $row['hierarchy_level'] ?? '' }}"></td>
                                        <td><input type="text" name="incentives[{{ $idx }}][nationality_type]"
                                                class="form-control" value="{{ $row['nationality_type'] ?? '' }}"></td>
                                        <td><input type="text" name="incentives[{{ $idx }}][incentive_type]"
                                                class="form-control" value="{{ $row['incentive_type'] ?? '' }}"></td>
                                        <td><input type="text" name="incentives[{{ $idx }}][incentive_class]"
                                                class="form-control" value="{{ $row['incentive_class'] ?? '' }}"></td>
                                        <td><input type="text" name="incentives[{{ $idx }}][reason]"
                                                class="form-control" value="{{ $row['reason'] ?? '' }}"></td>
                                        <td><button type="button" class="btn btn-sm btn-danger repeater-remove">លុប</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary repeater-add" data-target="#incentive-table"
                        data-repeater="incentives">+ បន្ថែម</button>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-success">
                        <i class="fa fa-save me-1"></i> រក្សាទុក
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('js')
    <script>
        (function($) {
            "use strict";

            var workStatusOptions = @json($workStatusOptionsJs);

            function escapeHtml(value) {
                return String(value || "")
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/\"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }

            function workStatusSelectHtml(index) {
                var options = '<option value="">-- ជ្រើសស្ថានភាព --</option>';
                for (var i = 0; i < workStatusOptions.length; i++) {
                    var value = escapeHtml(workStatusOptions[i]);
                    options += '<option value="' + value + '">' + value + '</option>';
                }

                return '<select name="work_histories[' + index + '][work_status_name]" class="form-select">' + options + '</select>';
            }

            function rowTemplate(repeater, index) {
                if (repeater === "work_histories") {
                    return '<tr>' +
                        '<td><input type="hidden" name="work_histories[' + index + '][id]" value="">' +
                        workStatusSelectHtml(index) + '</td>' +
                        '<td><input type="date" name="work_histories[' + index + '][start_date]" class="form-control"></td>' +
                        '<td><input type="text" name="work_histories[' + index + '][document_reference]" class="form-control"></td>' +
                        '<td><input type="date" name="work_histories[' + index + '][document_date]" class="form-control"></td>' +
                        '<td><input type="text" name="work_histories[' + index + '][note]" class="form-control"></td>' +
                        '<td><button type="button" class="btn btn-sm btn-danger repeater-remove">លុប</button></td>' +
                        '</tr>';
                }

                if (repeater === "incentives") {
                    return '<tr>' +
                        '<td><input type="date" name="incentives[' + index + '][incentive_date]" class="form-control"></td>' +
                        '<td><input type="text" name="incentives[' + index + '][hierarchy_level]" class="form-control"></td>' +
                        '<td><input type="text" name="incentives[' + index + '][nationality_type]" class="form-control"></td>' +
                        '<td><input type="text" name="incentives[' + index + '][incentive_type]" class="form-control"></td>' +
                        '<td><input type="text" name="incentives[' + index + '][incentive_class]" class="form-control"></td>' +
                        '<td><input type="text" name="incentives[' + index + '][reason]" class="form-control"></td>' +
                        '<td><button type="button" class="btn btn-sm btn-danger repeater-remove">លុប</button></td>' +
                        '</tr>';
                }

                return "";
            }

            function reindexRepeater(table, repeater) {
                $(table).find("tbody tr").each(function(rowIndex) {
                    $(this).find("input, select, textarea").each(function() {
                        var name = $(this).attr("name");
                        if (!name) {
                            return;
                        }
                        var re = new RegExp(repeater + "\\\\[\\\\d+\\\\]");
                        $(this).attr("name", name.replace(re, repeater + "[" + rowIndex + "]"));
                    });
                });
            }

            $(document).on("click", ".repeater-add", function() {
                var target = $(this).data("target");
                var repeater = $(this).data("repeater");
                var table = $(target);
                if (!table.length || !repeater) {
                    return;
                }

                var index = table.find("tbody tr").length;
                var row = rowTemplate(repeater, index);
                if (!row) {
                    return;
                }

                table.find("tbody").append(row);
            });

            $(document).on("click", ".repeater-remove", function() {
                var row = $(this).closest("tr");
                var table = row.closest("table");
                if (!table.length) {
                    return;
                }

                if (table.find("tbody tr").length === 1) {
                    row.find("input[type=text], input[type=date], textarea").val("");
                    row.find("select").prop("selectedIndex", 0);
                    return;
                }

                row.remove();
                var repeater = table.closest(".table-responsive").find(".repeater-add").data("repeater");
                if (repeater) {
                    reindexRepeater(table, repeater);
                }
            });
        })(jQuery);
    </script>
@endpush
