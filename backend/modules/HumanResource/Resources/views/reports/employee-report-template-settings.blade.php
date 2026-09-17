            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="border rounded p-3 h-100">
                        <h6 class="mb-3">{{ 'គំរូដែលបានរក្សាទុក' }}</h6>

                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ 'ឈ្មោះគំរូ' }}</th>
                                        <th class="text-end">{{ 'សកម្មភាព' }}</th>
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
                            <input type="hidden" name="_template_form" value="1">
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                                        onclick="return confirm('{{ 'តើអ្នកប្រាកដថាចង់លុប?' }}')">
                                                        <i class="fa fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="2" class="text-center text-muted py-3">
                                                {{ 'មិនទាន់មានគំរូរបាយការណ៍ទេ' }}
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
                                {{ $editingTemplate ? 'កែសម្រួលគំរូ' : 'បង្កើតគំរូថ្មី' }}
                            </h6>
                            @if ($editingTemplate)
                                <a href="{{ route('reports.employee-report-templates.index') }}" class="btn btn-sm btn-outline-secondary">
                                    {{ 'បង្កើតគំរូថ្មី' }}
                                </a>
                            @endif
                        </div>

                        <form id="report-template-form" method="POST"
                            action="{{ $editingTemplate ? route('reports.employee-report-templates.update', $editingTemplate->uuid) : route('reports.employee-report-templates.store') }}">
                            @csrf
                            <input type="hidden" name="_template_form" value="1">
                            @if ($editingTemplate)
                                @method('PATCH')
                            @endif

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="name" class="form-label">{{ 'ឈ្មោះគំរូ' }} <span class="text-danger">*</span></label>
                                    <input type="text" id="name" name="name" class="form-control"
                                        value="{{ old('name', $editingTemplate?->name) }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="report_type" class="form-label">{{ 'ប្រភេទរបាយការណ៍' }} <span class="text-danger">*</span></label>
                                    <select id="report_type" name="report_type" class="form-select" required>
                                        @foreach ($reportTypeOptions as $key => $label)
                                            <option value="{{ $key }}" @selected(old('report_type', $editingTemplate?->report_type ?: 'custom') === $key)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-12">
                                    <label for="description" class="form-label">{{ 'សេចក្តីពិពណ៌នា' }}</label>
                                    <textarea id="description" name="description" class="form-control" rows="2">{{ old('description', $editingTemplate?->description) }}</textarea>
                                </div>

                                @php
                                    $currentColumns = old('columns', $editingTemplate?->columns ?: ['employee_id', 'full_name', 'department', 'position', 'phone', 'work_status']);
                                @endphp
                                <div class="col-12">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                        <label class="form-label mb-0">{{ 'ជ្រើសជួរឈរដែលចង់បង្ហាញ' }} <span class="text-danger">*</span></label>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-sm btn-outline-primary js-select-all-columns">{{ 'ជ្រើសទាំងអស់' }}</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary js-clear-all-columns">{{ 'ដោះចេញទាំងអស់' }}</button>
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
                                                                    <button type="button" class="btn btn-xs btn-outline-primary js-select-group" data-group="{{ $groupKey }}">{{ 'ជ្រើសទាំងអស់' }}</button>
                                                                    <button type="button" class="btn btn-xs btn-outline-secondary js-clear-group" data-group="{{ $groupKey }}">{{ 'ដោះចេញ' }}</button>
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
                                        <input type="hidden" name="is_active" value="0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                                            {{ old('is_active', $editingTemplate?->is_active ?? true) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_active">{{ 'ប្រើប្រាស់' }}</label>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-save me-1"></i>{{ 'រក្សាទុក' }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

