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
    <div class="fw-semibold mb-1">{{ localize('assignment_meaning', 'Meaning of this assignment') }}</div>
    <div>{{ localize('assignment_meaning_0', 'Responsibility Template = module preset (actions + responsibility + default scope).') }}</div>
    <div>{{ localize('assignment_meaning_1', 'Responsibility = business authority (can be auto-resolved from template).') }}</div>
    <div>{{ localize('assignment_meaning_2', 'Scope = data visibility range within department hierarchy.') }}</div>
    <div>{{ localize('assignment_meaning_3', 'Primary = default governance assignment for this user.') }}</div>
</div>

<div class="row">
    <div class="form-group mb-2 mx-0 row">
        <label class="col-lg-3 col-form-label ps-0">{{ localize('user', 'User') }} <span class="text-danger">*</span></label>
        <div class="col-lg-9">
            <select name="user_id" class="form-control user-assignment-user-ajax"
                data-placeholder="{{ localize('select_user', 'Select user') }}" required>
                <option value="">{{ localize('select_user', 'Select user') }}</option>
                @if ((int) $selectedUserIdValue > 0 && filled($selectedUserTextValue))
                    <option value="{{ (int) $selectedUserIdValue }}" selected>{{ $selectedUserTextValue }}</option>
                @endif
            </select>
        </div>
    </div>

    <div class="form-group mb-2 mx-0 row">
        <label class="col-lg-3 col-form-label ps-0">{{ localize('org_unit', 'Org unit') }} <span class="text-danger">*</span></label>
        <div class="col-lg-9">
            <select name="department_id" class="form-control select-basic-single" required>
                <option value="">{{ localize('select_org_unit', 'Select org unit') }}</option>
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
        <label class="col-lg-3 col-form-label ps-0">{{ localize('position', 'Position') }}</label>
        <div class="col-lg-9">
            <select name="position_id" class="form-control select-basic-single ua-position-select">
                <option value="">{{ localize('not_set', 'Not set') }}</option>
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
        <label class="col-lg-3 col-form-label ps-0">{{ localize('template_module', 'Template Module') }}</label>
        <div class="col-lg-9">
            <select name="template_module_key" class="form-control select-basic-single ua-template-module">
                <option value="">{{ localize('select_module', 'Select module') }}</option>
                @foreach ($templateModuleOptions as $moduleKey)
                    <option value="{{ $moduleKey }}" @selected((string) $selectedTemplateModule === (string) $moduleKey)>
                        {{ $moduleKey }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="form-group mb-2 mx-0 row">
        <label class="col-lg-3 col-form-label ps-0">{{ localize('responsibility_template', 'Responsibility Template') }}</label>
        <div class="col-lg-9">
            <select name="responsibility_template_id" class="form-control select-basic-single ua-template-select">
                <option value="">{{ localize('select_template', 'Select template') }}</option>
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
                {{ localize('template_usage_hint', 'Choose template first. System will auto-set responsibility and default scope.') }}
            </small>
        </div>
    </div>

    <div class="form-group mb-2 mx-0 row">
        <label class="col-lg-3 col-form-label ps-0">{{ localize('responsibility_fallback', 'Responsibility (Fallback)') }}</label>
        <div class="col-lg-9">
            <select name="responsibility_id" class="form-control select-basic-single ua-responsibility-select">
                <option value="">{{ localize('select_responsibility', 'Select responsibility') }}</option>
                @foreach ($responsibilities as $responsibility)
                    <option value="{{ $responsibility->id }}"
                        @selected((int) old('responsibility_id', (int) ($formItem->responsibility_id ?? 0)) === (int) $responsibility->id)>
                        {{ ($responsibility->name_km ?: $responsibility->name) . ' (' . $responsibility->code . ')' }}
                    </option>
                @endforeach
            </select>
            <small class="text-muted d-block mt-1">
                {{ localize('fallback_responsibility_hint', 'Used for legacy compatibility when template is not selected.') }}
            </small>
        </div>
    </div>

    <div class="form-group mb-2 mx-0 row">
        <label class="col-lg-3 col-form-label ps-0">{{ localize('scope', 'Scope') }} <span class="text-danger">*</span></label>
        <div class="col-lg-9">
            <select name="scope_type" class="form-control select-basic-single" required>
                @foreach ($scope_options as $option)
                    <option value="{{ $option }}"
                        @selected(old('scope_type', (string) ($formItem->scope_type ?? 'self_and_children')) === $option)>
                        {{ $scopeLabels[$option] ?? $option }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="form-group mb-2 mx-0 row">
        <label class="col-lg-3 col-form-label ps-0">{{ localize('primary_assignment', 'Primary assignment') }}</label>
        <div class="col-lg-9">
            <select name="is_primary" class="form-control">
                <option value="1" @selected((int) old('is_primary', (int) ($formItem->is_primary ?? 0)) === 1)>
                    {{ localize('yes', 'Yes') }}
                </option>
                <option value="0" @selected((int) old('is_primary', (int) ($formItem->is_primary ?? 0)) === 0)>
                    {{ localize('no', 'No') }}
                </option>
            </select>
        </div>
    </div>

    <div class="form-group mb-2 mx-0 row">
        <label class="col-lg-3 col-form-label ps-0">{{ localize('effective_from', 'Effective from') }}</label>
        <div class="col-lg-9">
            <input type="date" name="effective_from" class="form-control"
                value="{{ old('effective_from', optional($formItem?->effective_from)->toDateString()) }}">
        </div>
    </div>

    <div class="form-group mb-2 mx-0 row">
        <label class="col-lg-3 col-form-label ps-0">{{ localize('effective_to', 'Effective to') }}</label>
        <div class="col-lg-9">
            <input type="date" name="effective_to" class="form-control"
                value="{{ old('effective_to', optional($formItem?->effective_to)->toDateString()) }}">
        </div>
    </div>

    <div class="form-group mb-2 mx-0 row">
        <label class="col-lg-3 col-form-label ps-0">{{ localize('status', 'Status') }}</label>
        <div class="col-lg-9">
            <select name="is_active" class="form-control">
                <option value="1" @selected((int) old('is_active', (int) ($formItem->is_active ?? 1)) === 1)>
                    {{ localize('active', 'Active') }}
                </option>
                <option value="0" @selected((int) old('is_active', (int) ($formItem->is_active ?? 1)) === 0)>
                    {{ localize('inactive', 'Inactive') }}
                </option>
            </select>
        </div>
    </div>

    <div class="form-group mb-2 mx-0 row">
        <label class="col-lg-3 col-form-label ps-0">{{ localize('note', 'Note') }}</label>
        <div class="col-lg-9">
            <textarea name="note" rows="2" class="form-control">{{ old('note', (string) ($formItem->note ?? '')) }}</textarea>
        </div>
    </div>
</div>
