@php
    $formItem = $item ?? null;
    $scopeLabels = is_array($scope_labels ?? null) ? $scope_labels : [];
    $templateGroups = is_array($template_groups ?? null) ? $template_groups : [];
    $templateModuleOptions = is_array($template_module_options ?? null) ? $template_module_options : array_keys($templateGroups);
    $selectedTemplateId = (int) old('responsibility_template_id', (int) ($formItem->responsibility_template_id ?? 0));
    $selectedTemplateModule = old('template_module_key', (string) ($formItem->responsibilityTemplate->module_key ?? ''));
    $selectedUserIdValue = old('user_id', (int) ($formItem->user_id ?? ($old_user_id ?? $selected_user_id ?? 0)));
    $selectedUserTextValue = '';
    if ($formItem && $formItem->user) {
        $selectedUserTextValue = trim(($formItem->user->full_name ?? '-') . ' (' . ($formItem->user->email ?? '-') . ')');
    } elseif (!empty($old_user_text ?? '')) {
        $selectedUserTextValue = (string) $old_user_text;
    } elseif (!empty($selected_user_text ?? '')) {
        $selectedUserTextValue = (string) $selected_user_text;
    }
@endphp

<div class="alert alert-light border mb-3">
    <div class="fw-semibold mb-1">{{ localize('assignment_meaning', 'របៀបបំពេញងាយៗ') }}</div>
    <div>{{ localize('assignment_meaning_easy_1', '1) ជ្រើសអ្នកប្រើប្រាស់') }}</div>
    <div>{{ localize('assignment_meaning_easy_2', '2) ជ្រើសអង្គភាព') }}</div>
    <div>{{ localize('assignment_meaning_easy_3', '3) ជ្រើសគំរូតួនាទី ឬ តួនាទី') }}</div>
    <div>{{ localize('assignment_meaning_easy_4', '4) ជ្រើសវិសាលភាព ហើយរក្សាទុក') }}</div>
</div>

<div class="row">
    <div class="form-group mb-2 mx-0 row">
        <label class="col-lg-3 col-form-label ps-0">{{ localize('user', 'អ្នកប្រើប្រាស់') }} <span class="text-danger">*</span></label>
        <div class="col-lg-9">
            <select name="user_id" class="form-control user-assignment-user-ajax"
                data-placeholder="{{ localize('select_user', 'ជ្រើសអ្នកប្រើប្រាស់') }}" required>
                <option value="">{{ localize('select_user', 'ជ្រើសអ្នកប្រើប្រាស់') }}</option>
                @if ((int) $selectedUserIdValue > 0 && filled($selectedUserTextValue))
                    <option value="{{ (int) $selectedUserIdValue }}" selected>{{ $selectedUserTextValue }}</option>
                @endif
            </select>
        </div>
    </div>

    <div class="form-group mb-2 mx-0 row">
        <label class="col-lg-3 col-form-label ps-0">{{ localize('org_unit', 'អង្គភាព') }} <span class="text-danger">*</span></label>
        <div class="col-lg-9">
            <select name="department_id" class="form-control select-basic-single" required>
                <option value="">{{ localize('select_org_unit', 'ជ្រើសអង្គភាព') }}</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}"
                        @selected((int) old('department_id', (int) ($formItem->department_id ?? 0)) === (int) $department->id)>
                        {{ $department->label }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="form-group mb-2 mx-0 row">
        <label class="col-lg-3 col-form-label ps-0">{{ localize('responsibility_template', 'គំរូតួនាទី') }}</label>
        <div class="col-lg-9">
            <select name="responsibility_template_id" class="form-control select-basic-single ua-template-select">
                <option value="">{{ localize('select_template', 'ជ្រើសគំរូតួនាទី') }}</option>
                @foreach ($templateGroups as $moduleKey => $templates)
                    <optgroup label="{{ $moduleKey }}">
                        @foreach ($templates as $template)
                            <option value="{{ (int) ($template['id'] ?? 0) }}"
                                data-module-key="{{ (string) ($template['module_key'] ?? '') }}"
                                data-responsibility-id="{{ (int) ($template['responsibility_id'] ?? 0) }}"
                                data-position-id="{{ (int) ($template['position_id'] ?? 0) }}"
                                data-default-scope="{{ (string) ($template['default_scope_type'] ?? '') }}"
                                @selected($selectedTemplateId === (int) ($template['id'] ?? 0))>
                                {{ (string) ($template['label'] ?? ('#' . (int) ($template['id'] ?? 0))) }}
                            </option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            <small class="text-muted d-block mt-1">
                {{ localize('template_usage_hint', 'បើជ្រើសគំរូតួនាទី ប្រព័ន្ធនឹងបំពេញតួនាទី និងវិសាលភាពជាមុនឲ្យ។') }}
            </small>
        </div>
    </div>

    <div class="form-group mb-2 mx-0 row">
        <label class="col-lg-3 col-form-label ps-0">{{ localize('responsibility_simple', 'តួនាទី') }}</label>
        <div class="col-lg-9">
            <select name="responsibility_id" class="form-control select-basic-single ua-responsibility-select">
                <option value="">{{ localize('select_responsibility', 'ជ្រើសតួនាទី') }}</option>
                @foreach ($responsibilities as $responsibility)
                    <option value="{{ $responsibility->id }}"
                        @selected((int) old('responsibility_id', (int) ($formItem->responsibility_id ?? 0)) === (int) $responsibility->id)>
                        {{ ($responsibility->name_km ?: $responsibility->name) . ' (' . $responsibility->code . ')' }}
                    </option>
                @endforeach
            </select>
            <small class="text-muted d-block mt-1">
                {{ localize('responsibility_usage_hint', 'បើមិនជ្រើសគំរូតួនាទី សូមជ្រើសតួនាទីដោយផ្ទាល់នៅទីនេះ។') }}
            </small>
        </div>
    </div>

    <div class="form-group mb-2 mx-0 row">
        <label class="col-lg-3 col-form-label ps-0">{{ localize('scope', 'វិសាលភាព') }} <span class="text-danger">*</span></label>
        <div class="col-lg-9">
            <select name="scope_type" class="form-control select-basic-single" required>
                @foreach ($scope_options as $option)
                    <option value="{{ $option }}"
                        @selected(old('scope_type', (string) ($formItem->scope_type ?? 'self_and_children')) === $option)>
                        {{ $scopeLabels[$option] ?? $option }}
                    </option>
                @endforeach
            </select>
            <small class="text-muted d-block mt-1">
                {{ localize('scope_usage_hint', 'ជ្រើសថា អ្នកប្រើនេះអាចមើល ឬ គ្រប់គ្រង ត្រឹមអង្គភាពខ្លួនឯង ឬ រួមទាំងអង្គភាពរង។') }}
            </small>
        </div>
    </div>

    <div class="form-group mb-2 mx-0 row">
        <label class="col-lg-3 col-form-label ps-0">{{ localize('status', 'ស្ថានភាព') }}</label>
        <div class="col-lg-9">
            <select name="is_active" class="form-control">
                <option value="1" @selected((int) old('is_active', (int) ($formItem->is_active ?? 1)) === 1)>
                    {{ localize('active', 'សកម្ម') }}
                </option>
                <option value="0" @selected((int) old('is_active', (int) ($formItem->is_active ?? 1)) === 0)>
                    {{ localize('inactive', 'អសកម្ម') }}
                </option>
            </select>
        </div>
    </div>

    <div class="col-12 mt-2">
        <details class="border rounded p-3 bg-light">
            <summary class="fw-semibold" style="cursor: pointer;">
                {{ localize('advanced_options', 'ជម្រើសបន្ថែម') }}
            </summary>
            <div class="mt-3">
                <div class="form-group mb-2 mx-0 row">
                    <label class="col-lg-3 col-form-label ps-0">{{ localize('position', 'មុខតំណែង') }}</label>
                    <div class="col-lg-9">
                        <select name="position_id" class="form-control select-basic-single ua-position-select">
                            <option value="">{{ localize('not_set', 'មិនកំណត់') }}</option>
                            @foreach ($positions as $position)
                                @php
                                    $positionLabel = $position->position_name_km ?: $position->position_name;
                                @endphp
                                <option value="{{ $position->id }}"
                                    @selected((int) old('position_id', (int) ($formItem->position_id ?? 0)) === (int) $position->id)>
                                    {{ $positionLabel }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-group mb-2 mx-0 row">
                    <label class="col-lg-3 col-form-label ps-0">{{ localize('template_module', 'ក្រុមមុខងារ') }}</label>
                    <div class="col-lg-9">
                        <select name="template_module_key" class="form-control select-basic-single ua-template-module">
                            <option value="">{{ localize('select_module', 'ជ្រើសក្រុមមុខងារ') }}</option>
                            @foreach ($templateModuleOptions as $moduleKey)
                                <option value="{{ $moduleKey }}" @selected((string) $selectedTemplateModule === (string) $moduleKey)>
                                    {{ $moduleKey }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted d-block mt-1">
                            {{ localize('template_module_hint', 'ប្រើករណីចង់តម្រៀបគំរូតួនាទីតាម module ប៉ុណ្ណោះ។') }}
                        </small>
                    </div>
                </div>

                <div class="form-group mb-2 mx-0 row">
                    <label class="col-lg-3 col-form-label ps-0">{{ localize('primary_assignment', 'ការកំណត់លំនាំដើម') }}</label>
                    <div class="col-lg-9">
                        <select name="is_primary" class="form-control">
                            <option value="1" @selected((int) old('is_primary', (int) ($formItem->is_primary ?? 0)) === 1)>
                                {{ localize('yes', 'បាទ/ចាស') }}
                            </option>
                            <option value="0" @selected((int) old('is_primary', (int) ($formItem->is_primary ?? 0)) === 0)>
                                {{ localize('no', 'ទេ') }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="form-group mb-2 mx-0 row">
                    <label class="col-lg-3 col-form-label ps-0">{{ localize('effective_from', 'ចាប់ផ្តើមពី') }}</label>
                    <div class="col-lg-9">
                        <input type="date" name="effective_from" class="form-control"
                            value="{{ old('effective_from', optional($formItem?->effective_from)->toDateString()) }}">
                    </div>
                </div>

                <div class="form-group mb-2 mx-0 row">
                    <label class="col-lg-3 col-form-label ps-0">{{ localize('effective_to', 'បញ្ចប់នៅ') }}</label>
                    <div class="col-lg-9">
                        <input type="date" name="effective_to" class="form-control"
                            value="{{ old('effective_to', optional($formItem?->effective_to)->toDateString()) }}">
                    </div>
                </div>

                <div class="form-group mb-2 mx-0 row">
                    <label class="col-lg-3 col-form-label ps-0">{{ localize('note', 'កំណត់សម្គាល់') }}</label>
                    <div class="col-lg-9">
                        <textarea name="note" rows="2" class="form-control">{{ old('note', (string) ($formItem->note ?? '')) }}</textarea>
                    </div>
                </div>
            </div>
        </details>
    </div>
</div>
