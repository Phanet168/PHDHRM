@extends('backend.layouts.app')
@section('title', localize('employee_report_management', 'ការគ្រប់គ្រងរបាយការណ៍បុគ្គលិក'))

@section('content')
    @include('humanresource::reports_header')
    @include('backend.layouts.common.validation')

    <div class="card mb-4 fixed-tab-body">
        <div class="card-header">
            <h6 class="fs-17 fw-semi-bold mb-1">{{ localize('employee_report_management', 'ការគ្រប់គ្រងរបាយការណ៍បុគ្គលិក') }}</h6>
            <div class="small text-muted">
                {{ localize('employee_report_management_flow_hint', 'លំដាប់ប្រើប្រាស់៖ ១) បង្កើតគំរូ ២) ជ្រើសលក្ខខណ្ឌ ៣) ចុចស្វែងរក និងទាញយក') }}
            </div>
        </div>

        <div class="card-body">
            <div class="alert alert-light border mb-4">
                <div class="row g-2 align-items-center">
                    <div class="col-md-4">
                        <span class="badge bg-primary me-2">ជំហាន ១</span>
                        <span>{{ localize('step_1_create_template', 'កំណត់គំរូរបាយការណ៍') }}</span>
                    </div>
                    <div class="col-md-4">
                        <span class="badge bg-secondary me-2">ជំហាន ២</span>
                        <span>{{ localize('step_2_set_filter', 'ជ្រើសលក្ខខណ្ឌស្វែងរក') }}</span>
                    </div>
                    <div class="col-md-4">
                        <span class="badge bg-success me-2">ជំហាន ៣</span>
                        <span>{{ localize('step_3_preview_export', 'មើលលទ្ធផល និងទាញយក CSV') }}</span>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="border rounded p-3 h-100">
                        <h6 class="mb-3">{{ localize('step_1_template_list', 'ជំហាន ១.A - បញ្ជីគំរូ') }}</h6>

                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ localize('template_name', 'ឈ្មោះគំរូ') }}</th>
                                        <th class="text-end">{{ localize('action', 'សកម្មភាព') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($templates as $template)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $template->name }}</div>
                                                <small class="text-muted">{{ $reportTypeOptions[$template->report_type] ?? $template->report_type }}</small>
                                            </td>
                                            <td class="text-end">
                                                <a href="{{ route('reports.employee-report-templates.index', ['edit' => $template->uuid]) }}"
                                                    class="btn btn-sm btn-outline-primary">
                                                    <i class="fa fa-edit"></i>
                                                </a>
                                                <form action="{{ route('reports.employee-report-templates.destroy', $template->uuid) }}" method="POST"
                                                    class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                                        onclick="return confirm('{{ localize('confirm_delete', 'តើអ្នកប្រាកដថាចង់លុប?') }}')">
                                                        <i class="fa fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="2" class="text-center text-muted py-3">
                                                {{ localize('no_template_found', 'មិនទាន់មានគំរូរបាយការណ៍ទេ') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="border rounded p-3 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">
                                {{ $editingTemplate ? localize('step_1b_edit_template', 'ជំហាន ១.B - កែគំរូ') : localize('step_1b_new_template', 'ជំហាន ១.B - បង្កើតគំរូថ្មី') }}
                            </h6>
                            @if ($editingTemplate)
                                <a href="{{ route('reports.employee-report-templates.index') }}" class="btn btn-sm btn-outline-secondary">
                                    {{ localize('new_template', 'បង្កើតគំរូថ្មី') }}
                                </a>
                            @endif
                        </div>

                        <form method="POST"
                            action="{{ $editingTemplate ? route('reports.employee-report-templates.update', $editingTemplate->uuid) : route('reports.employee-report-templates.store') }}">
                            @csrf
                            @if ($editingTemplate)
                                @method('PATCH')
                            @endif

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="name" class="form-label">{{ localize('template_name', 'ឈ្មោះគំរូ') }} <span class="text-danger">*</span></label>
                                    <input type="text" id="name" name="name" class="form-control"
                                        value="{{ old('name', $editingTemplate?->name) }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="report_type" class="form-label">{{ localize('report_type', 'ប្រភេទរបាយការណ៍') }} <span class="text-danger">*</span></label>
                                    <select id="report_type" name="report_type" class="form-select" required>
                                        @foreach ($reportTypeOptions as $key => $label)
                                            <option value="{{ $key }}" @selected(old('report_type', $editingTemplate?->report_type ?: 'custom') === $key)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-12">
                                    <label for="description" class="form-label">{{ localize('description', 'សេចក្តីពិពណ៌នា') }}</label>
                                    <textarea id="description" name="description" class="form-control" rows="2">{{ old('description', $editingTemplate?->description) }}</textarea>
                                </div>

                                @php
                                    $currentColumns = old('columns', $editingTemplate?->columns ?: ['employee_id', 'full_name', 'department', 'position', 'phone', 'work_status']);
                                @endphp
                                <div class="col-12">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                        <label class="form-label mb-0">{{ localize('select_columns', 'ជ្រើសជួរឈរដែលចង់បង្ហាញ') }} <span class="text-danger">*</span></label>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-sm btn-outline-primary js-select-all-columns">{{ localize('select_all', 'ជ្រើសទាំងអស់') }}</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary js-clear-all-columns">{{ localize('clear_all', 'ដោះចេញទាំងអស់') }}</button>
                                        </div>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-12">
                                            <div class="accordion" id="columnGroupAccordion">
                                                @foreach ($columnGroups as $groupKey => $group)
                                                    @php
                                                        $groupColumns = (array) ($group['columns'] ?? []);
                                                        $selectedInGroup = count(array_intersect($groupColumns, (array) $currentColumns));
                                                        $collapseId = 'group_collapse_' . $groupKey;
                                                        $headingId = 'group_heading_' . $groupKey;
                                                    @endphp
                                                    <div class="accordion-item mb-2 border rounded">
                                                        <h2 class="accordion-header" id="{{ $headingId }}">
                                                            <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button"
                                                                data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}"
                                                                aria-expanded="{{ $loop->first ? 'true' : 'false' }}" aria-controls="{{ $collapseId }}">
                                                                <span class="fw-semibold me-2">{{ $group['label'] }}</span>
                                                                <span class="badge bg-light text-dark border">{{ $selectedInGroup }}/{{ count($groupColumns) }}</span>
                                                            </button>
                                                        </h2>
                                                        <div id="{{ $collapseId }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}"
                                                            aria-labelledby="{{ $headingId }}" data-bs-parent="#columnGroupAccordion">
                                                            <div class="accordion-body pt-2">
                                                                <div class="d-flex align-items-center justify-content-end mb-2 gap-2">
                                                                    <button type="button" class="btn btn-xs btn-outline-primary js-select-group" data-group="{{ $groupKey }}">{{ localize('select_all', 'ជ្រើសទាំងអស់') }}</button>
                                                                    <button type="button" class="btn btn-xs btn-outline-secondary js-clear-group" data-group="{{ $groupKey }}">{{ localize('clear', 'ដោះចេញ') }}</button>
                                                                </div>
                                                                <div class="row g-2">
                                                                    @foreach ($groupColumns as $key)
                                                                        @if (array_key_exists($key, $columnOptions))
                                                                            <div class="col-md-6 col-xl-4">
                                                                                <div class="form-check border rounded px-2 py-1 h-100">
                                                                                    <input class="form-check-input js-column-checkbox" type="checkbox" name="columns[]" id="col_{{ $groupKey }}_{{ $key }}" value="{{ $key }}"
                                                                                        data-group="{{ $groupKey }}" {{ in_array($key, (array) $currentColumns, true) ? 'checked' : '' }}>
                                                                                    <label class="form-check-label" for="col_{{ $groupKey }}_{{ $key }}">{{ $columnOptions[$key] }}</label>
                                                                                </div>
                                                                            </div>
                                                                        @endif
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                                            {{ old('is_active', $editingTemplate?->is_active ?? true) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_active">{{ localize('active', 'ប្រើប្រាស់') }}</label>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-save me-1"></i>{{ localize('save', 'រក្សាទុក') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="border rounded p-3 mt-3 mb-3">
                <h6 class="mb-3">{{ localize('step_2_generate_report', 'ជំហាន ២ - កំណត់លក្ខខណ្ឌ និងបង្ហាញរបាយការណ៍') }}</h6>
                <form method="GET" action="{{ route('reports.employee-report-templates.index') }}">
                    <input type="hidden" name="run" value="1">
                    <div class="row g-3">
                        <div class="col-md-2">
                            <label for="template" class="form-label">{{ localize('report_template', 'គំរូរបាយការណ៍') }}</label>
                            <select id="template" name="template" class="form-select">
                                <option value="">{{ localize('select_one', 'សូមជ្រើស') }}</option>
                                @foreach ($templates as $template)
                                    <option value="{{ $template->uuid }}" @selected((string) request('template') === (string) $template->uuid)>
                                        {{ $template->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="department_id" class="form-label">{{ localize('department', 'អង្គភាព') }}</label>
                            <select id="department_id" name="department_id" class="form-select">
                                <option value="">{{ localize('all', 'ទាំងអស់') }}</option>
                                @foreach ($departmentTreeOptions as $department)
                                    <option value="{{ $department->id }}" @selected((string) request('department_id') === (string) $department->id)
                                        title="{{ $department->path }}">
                                        {{ $department->label }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted d-block mt-1">{{ localize('select_parent_from_tree_hint', 'ជ្រើសអង្គភាពជារចនាសម្ព័ន្ធមេ-កូន') }}</small>
                        </div>
                        <div class="col-md-2">
                            <label for="position_id" class="form-label">{{ localize('position', 'មុខតំណែង') }}</label>
                            <select id="position_id" name="position_id" class="form-select">
                                <option value="">{{ localize('all', 'ទាំងអស់') }}</option>
                                @foreach ($positions as $position)
                                    <option value="{{ $position->id }}" @selected((string) request('position_id') === (string) $position->id)>
                                        {{ $position->position_name_km ?: $position->position_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="status" class="form-label">{{ localize('status', 'ស្ថានភាព') }}</label>
                            <select id="status" name="status" class="form-select">
                                <option value="">{{ localize('all', 'ទាំងអស់') }}</option>
                                <option value="active" @selected(request('status') === 'active')>{{ localize('active', 'កំពុងប្រើ') }}</option>
                                <option value="inactive" @selected(request('status') === 'inactive')>{{ localize('inactive', 'មិនប្រើ') }}</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="group_by" class="form-label">{{ localize('group_by', 'ចាត់ក្រុមតាម') }}</label>
                            <select id="group_by" name="group_by" class="form-select">
                                <option value="">{{ localize('none', 'មិនចាត់ក្រុម') }}</option>
                                @foreach ($groupByOptions as $groupKey => $groupLabel)
                                    <option value="{{ $groupKey }}" @selected((string) request('group_by') === (string) $groupKey)>{{ $groupLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="keyword" class="form-label">{{ localize('keyword', 'ពាក្យស្វែងរក') }}</label>
                            <input type="text" id="keyword" name="keyword" class="form-control"
                                value="{{ request('keyword') }}" placeholder="{{ localize('search', 'ឈ្មោះ / ទូរសព្ទ / អ៊ីមែល') }}">
                        </div>
                        <div class="col-12 d-flex justify-content-end gap-2">
                            <a href="{{ route('reports.employee-report-templates.index') }}" class="btn btn-outline-secondary">{{ localize('reset', 'សម្អាត') }}</a>
                            <button type="submit" class="btn btn-success"><i class="fa fa-filter me-1"></i>{{ localize('find', 'ស្វែងរក') }}</button>
                        </div>
                    </div>
                </form>
            </div>

            @if ($reportRows)
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <h6 class="mb-0">{{ localize('step_3_report_result', 'ជំហាន ៣ - លទ្ធផលរបាយការណ៍') }}</h6>
                    @if ($selectedTemplate)
                        <div class="d-flex align-items-center gap-2">
                            <a href="{{ route('reports.employee-report-templates.export-csv', request()->query()) }}" class="btn btn-sm btn-outline-primary">
                                <i class="fa fa-file-text-o me-1"></i>{{ localize('download_csv', 'ទាញយក CSV') }}
                            </a>
                            <a href="{{ route('reports.employee-report-templates.export-excel', request()->query()) }}" class="btn btn-sm btn-outline-success">
                                <i class="fa fa-file-excel-o me-1"></i>{{ localize('download_excel', 'ទាញយក Excel') }}
                            </a>
                            <a href="{{ route('reports.employee-report-templates.export-pdf', request()->query()) }}" class="btn btn-sm btn-outline-danger" target="_blank" rel="noopener">
                                <i class="fa fa-file-pdf-o me-1"></i>{{ localize('download_pdf', 'ទាញយក PDF') }}
                            </a>
                        </div>
                    @endif
                </div>

                @if (!$selectedTemplate)
                    <div class="alert alert-warning">
                        {{ localize('please_select_template_before_run', 'សូមជ្រើសគំរូរបាយការណ៍មុនពេលចុចស្វែងរក។') }}
                    </div>
                @endif

                @if (!empty($groupedSummary) && $selectedGroupBy)
                    @php
                        $groupKeyLabel = (string) $selectedGroupBy;
                        $groupDisplayLabel = $groupByOptions[$groupKeyLabel] ?? $groupKeyLabel;
                    @endphp
                    <div class="border rounded p-3 mb-3 bg-light">
                        <h6 class="mb-2">
                            {{ localize('group_summary', 'សង្ខេបតាមក្រុម') }}:
                            {{ $groupDisplayLabel }}
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th width="5%">#</th>
                                        <th>{{ localize('group_name', 'ឈ្មោះក្រុម') }}</th>
                                        <th width="20%">{{ localize('total_employee', 'ចំនួនបុគ្គលិក') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($groupedSummary as $summary)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $summary['group_label'] }}</td>
                                            <td>{{ $summary['total'] }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center text-muted">{{ localize('no_data_found', 'មិនមានទិន្នន័យ') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead>
                            <tr>
                                @foreach ($selectedColumns as $column)
                                    <th>{{ $columnOptions[$column] ?? $column }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($reportRows as $row)
                                <tr>
                                    @foreach ($selectedColumns as $column)
                                        <td>{{ $row[$column] ?? '' }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($selectedColumns) ?: 1 }}" class="text-center text-muted py-3">
                                        {{ localize('no_data_found', 'មិនមានទិន្នន័យ') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-2">
                    {{ $reportRows->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection

@push('js')
    <script>
        (function() {
            "use strict";

            function setCheckboxes(selector, checked) {
                document.querySelectorAll(selector).forEach(function(el) {
                    el.checked = checked;
                });
            }

            var selectAllBtn = document.querySelector('.js-select-all-columns');
            var clearAllBtn = document.querySelector('.js-clear-all-columns');

            if (selectAllBtn) {
                selectAllBtn.addEventListener('click', function() {
                    setCheckboxes('.js-column-checkbox', true);
                });
            }

            if (clearAllBtn) {
                clearAllBtn.addEventListener('click', function() {
                    setCheckboxes('.js-column-checkbox', false);
                });
            }

            document.querySelectorAll('.js-select-group').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var group = this.getAttribute('data-group');
                    setCheckboxes('.js-column-checkbox[data-group="' + group + '"]', true);
                });
            });

            document.querySelectorAll('.js-clear-group').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var group = this.getAttribute('data-group');
                    setCheckboxes('.js-column-checkbox[data-group="' + group + '"]', false);
                });
            });
        })();
    </script>
@endpush
