@extends('backend.layouts.app')
@section('title', localize('workflow_policy_matrix', 'Workflow Policy Matrix'))
@section('content')
    @include('humanresource::master-data.org-structure.header')
    @include('backend.layouts.common.validation')
    @include('backend.layouts.common.message')

    @php
        $formatKey = function (?string $key): string {
            $key = (string) $key;
            if ($key === '') {
                return '-';
            }
            return ucwords(str_replace(['_', '-', '.'], ' ', $key));
        };
        $actorTypeLabels = is_array($actor_type_labels ?? null) ? $actor_type_labels : [];
        $scopeTypeLabels = is_array($scope_type_labels ?? null) ? $scope_type_labels : [];
        $actionTypeLabels = is_array($action_type_labels ?? null) ? $action_type_labels : [];
        $moduleOptions = is_array($module_options ?? null) ? $module_options : [];
        $requestTypeOptions = is_array($request_type_options ?? null) ? $request_type_options : [];
        $requestTypeOptionsByModule = is_array($request_type_options_by_module ?? null) ? $request_type_options_by_module : [];
        $moduleLabels = is_array($module_labels ?? null) ? $module_labels : [];
        $requestTypeLabels = is_array($request_type_labels ?? null) ? $request_type_labels : [];
        $policyTemplates = is_array($policy_templates ?? null) ? $policy_templates : [];

        $responsibilities = collect($responsibilities ?? [])->map(function ($item) {
            return [
                'id' => (int) $item->id,
                'code' => (string) $item->code,
                'label' => (string) ($item->name_km ?: $item->name),
            ];
        })->values();
        $positions = collect($positions ?? [])->map(function ($item) {
            return [
                'id' => (int) $item->id,
                'label' => (string) ($item->position_name_km ?: $item->position_name),
            ];
        })->values();
        $spatieRoles = collect($spatie_roles ?? [])->map(function ($item) {
            return [
                'id' => (int) $item->id,
                'label' => (string) $item->name,
            ];
        })->values();
        $users = collect($users ?? [])->map(function ($item) {
            $fullName = trim((string) ($item->full_name ?? ''));
            $email = trim((string) ($item->email ?? ''));
            $label = $fullName !== '' ? $fullName : ('ID: ' . (int) $item->id);
            if ($email !== '') {
                $label .= ' (' . $email . ')';
            }

            return [
                'id' => (int) $item->id,
                'label' => $label,
            ];
        })->values();
    @endphp

    <div class="card mb-4 fixed-tab-body">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h6 class="fs-17 fw-semi-bold mb-0">{{ localize('workflow_policy_matrix', 'Workflow Policy Matrix') }}</h6>
                <small class="text-muted">
                    {{ localize('workflow_actor_based_desc', 'Actor-based approval steps: specific user > position > responsibility > spatie role (fallback).') }}
                </small>
            </div>
            @canany(['create_org_governance', 'create_department'])
                <button type="button" class="btn btn-success btn-sm workflow-policy-create-btn" data-bs-toggle="modal"
                    data-bs-target="#workflow-policy-modal">
                    <i class="fa fa-plus-circle"></i>&nbsp;{{ localize('add', 'Add') }}
                </button>
            @endcanany
        </div>
        <div class="card-body">
            <div class="alert alert-info py-2 mb-3">
                <div class="small mb-0">
                    <strong>{{ localize('quick_guide', 'Quick guide') }}:</strong>
                    1) {{ localize('select_module_and_type', 'Select module + request type') }}
                    &nbsp;->&nbsp;2) {{ localize('set_actor_steps', 'Set step actor type + target') }}
                    &nbsp;->&nbsp;3) {{ localize('preview_resolution', 'Preview resolution') }}
                    &nbsp;->&nbsp;4) {{ localize('save', 'Save') }}
                </div>
            </div>

            <form method="GET" action="{{ route('workflow-policies.index') }}" class="mb-3">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label mb-1">{{ localize('module', 'Module') }}</label>
                        <select name="module_key" id="wf-filter-module-key" class="form-control select-basic-single">
                            <option value="">{{ localize('all', 'All') }}</option>
                            @foreach ($moduleOptions as $moduleKey)
                                <option value="{{ $moduleKey }}" @selected($selected_module_key === $moduleKey)>
                                    {{ $moduleLabels[$moduleKey] ?? $formatKey($moduleKey) }} ({{ $moduleKey }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label mb-1">{{ localize('request_type', 'Request Type') }}</label>
                        <select name="request_type_key" id="wf-filter-request-type-key" class="form-control select-basic-single">
                            <option value="">{{ localize('all', 'All') }}</option>
                            @foreach (($selected_module_key !== '' ? ($requestTypeOptionsByModule[$selected_module_key] ?? $requestTypeOptions) : $requestTypeOptions) as $requestTypeKey)
                                <option value="{{ $requestTypeKey }}" @selected($selected_request_type_key === $requestTypeKey)>
                                    {{ $requestTypeLabels[$requestTypeKey] ?? $formatKey($requestTypeKey) }} ({{ $requestTypeKey }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fa fa-search"></i>&nbsp;{{ localize('filter', 'Filter') }}
                        </button>
                        <a href="{{ route('workflow-policies.index') }}" class="btn btn-secondary btn-sm">
                            {{ localize('reset', 'Reset') }}
                        </a>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table display table-bordered table-striped table-hover">
                    <thead>
                        <tr>
                            <th width="4%">SL</th>
                            <th width="10%">{{ localize('module', 'Module') }}</th>
                            <th width="10%">{{ localize('request_type', 'Request Type') }}</th>
                            <th width="12%">{{ localize('policy_name', 'Policy name') }}</th>
                            <th width="6%">{{ localize('priority', 'Priority') }}</th>
                            <th width="30%">{{ localize('approval_steps', 'Approval steps') }}</th>
                            <th width="8%">{{ localize('status', 'Status') }}</th>
                            <th width="10%">{{ localize('action', 'Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($definitions as $item)
                            @php
                                $stepsPayload = $item->steps->map(function ($s) {
                                    return [
                                        'step_order' => (int) $s->step_order,
                                        'step_key' => (string) ($s->step_key ?? ''),
                                        'step_name' => (string) $s->step_name,
                                        'action_type' => (string) $s->action_type,
                                        'actor_type' => method_exists($s, 'getEffectiveActorType') ? (string) $s->getEffectiveActorType() : (string) ($s->actor_type ?? 'responsibility'),
                                        'actor_user_id' => !empty($s->actor_user_id) ? (int) $s->actor_user_id : null,
                                        'actor_position_id' => !empty($s->actor_position_id) ? (int) $s->actor_position_id : null,
                                        'actor_responsibility_id' => !empty($s->actor_responsibility_id) ? (int) $s->actor_responsibility_id : null,
                                        'actor_role_id' => !empty($s->actor_role_id) ? (int) $s->actor_role_id : null,
                                        'org_role' => (string) ($s->org_role ?? ''),
                                        'system_role_id' => !empty($s->system_role_id) ? (int) $s->system_role_id : null,
                                        'scope_type' => (string) $s->scope_type,
                                        'is_final_approval' => (int) $s->is_final_approval,
                                        'is_required' => (int) $s->is_required,
                                        'can_return' => (int) $s->can_return,
                                        'can_reject' => (int) $s->can_reject,
                                    ];
                                })->values()->all();
                            @endphp
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $formatKey($item->module_key) }}</div>
                                    <small class="text-muted"><code>{{ $item->module_key }}</code></small>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $formatKey($item->request_type_key) }}</div>
                                    <small class="text-muted"><code>{{ $item->request_type_key }}</code></small>
                                </td>
                                <td>{{ $item->name }}</td>
                                <td>{{ $item->priority }}</td>
                                <td>
                                    @if ($item->steps->isEmpty())
                                        <span class="text-danger">{{ localize('no_steps_defined', 'No steps defined') }}</span>
                                    @else
                                        <ol class="mb-0 ps-3">
                                            @foreach ($item->steps as $step)
                                                @php
                                                    $actorType = method_exists($step, 'getEffectiveActorType') ? $step->getEffectiveActorType() : ($step->actor_type ?? 'responsibility');
                                                    $actorLabel = $actorTypeLabels[$actorType] ?? $actorType;
                                                    $targetText = '-';
                                                    if ($actorType === 'specific_user' && $step->actorUser) {
                                                        $targetText = $step->actorUser->full_name . ' (' . $step->actorUser->email . ')';
                                                    } elseif ($actorType === 'position' && $step->actorPosition) {
                                                        $targetText = $step->actorPosition->position_name_km ?: $step->actorPosition->position_name;
                                                    } elseif ($actorType === 'responsibility' && $step->actorResponsibility) {
                                                        $targetText = ($step->actorResponsibility->name_km ?: $step->actorResponsibility->name) . ' (' . $step->actorResponsibility->code . ')';
                                                    } elseif ($actorType === 'spatie_role' && $step->actorRole) {
                                                        $targetText = $step->actorRole->name;
                                                    } elseif (!empty($step->org_role)) {
                                                        $targetText = $step->org_role;
                                                    }
                                                @endphp
                                                <li class="mb-1">
                                                    <div class="fw-semibold">{{ $step->step_order }}. {{ $step->step_name }}</div>
                                                    <small class="text-muted d-block">
                                                        {{ $actionTypeLabels[$step->action_type] ?? $step->action_type }}
                                                        &middot;
                                                        {{ $actorLabel }}: {{ $targetText }}
                                                        &middot;
                                                        {{ $scopeTypeLabels[$step->scope_type] ?? $step->scope_type }}
                                                    </small>
                                                </li>
                                            @endforeach
                                        </ol>
                                    @endif
                                </td>
                                <td>
                                    @if ($item->is_active)
                                        <span class="badge bg-success">{{ localize('active', 'Active') }}</span>
                                    @else
                                        <span class="badge bg-danger">{{ localize('inactive', 'Inactive') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @canany(['update_org_governance', 'update_department'])
                                        <button type="button" class="btn btn-primary-soft btn-sm me-1 workflow-policy-edit-btn"
                                            data-bs-toggle="modal" data-bs-target="#workflow-policy-modal"
                                            data-action="{{ route('workflow-policies.update', $item->id) }}"
                                            data-module="{{ $item->module_key }}"
                                            data-request-type="{{ $item->request_type_key }}"
                                            data-name="{{ $item->name }}"
                                            data-priority="{{ (int) $item->priority }}"
                                            data-active="{{ (int) $item->is_active }}"
                                            data-description='@json($item->description ?? "")'
                                            data-condition='@json($item->condition_json ?? [])'
                                            data-steps='@json($stepsPayload)'>
                                            <i class="fa fa-edit"></i>
                                        </button>
                                    @endcanany
                                    @canany(['delete_org_governance', 'delete_department'])
                                        <a href="javascript:void(0)" class="btn btn-danger-soft btn-sm delete-confirm"
                                            data-route="{{ route('workflow-policies.destroy', $item->id) }}"
                                            data-csrf="{{ csrf_token() }}">
                                            <i class="fa fa-trash"></i>
                                        </a>
                                    @endcanany
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">{{ localize('no_data_found', 'No data found') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @canany(['create_org_governance', 'create_department', 'update_org_governance', 'update_department'])
        <div class="modal fade" id="workflow-policy-modal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
            aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="workflow-policy-modal-title">{{ localize('add', 'Add') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="{{ localize('close', 'Close') }}"></button>
                    </div>
                    <form id="workflow-policy-form" action="{{ route('workflow-policies.store') }}" method="POST">
                        @csrf
                        <input type="hidden" id="workflow-policy-form-method" name="_method" value="POST">
                        <div class="modal-body">
                            <div class="alert alert-light border py-2 px-3 mb-3">
                                <div class="fw-semibold mb-1">{{ localize('workflow_form_quick_help_title', 'របៀបបំពេញ (ងាយយល់)') }}</div>
                                <div class="small text-muted">
                                    1) {{ localize('workflow_form_quick_help_1', 'ជ្រើស ម៉ូឌុល + ប្រភេទសំណើ') }}
                                    &nbsp;->&nbsp;2) {{ localize('workflow_form_quick_help_2', 'ដាក់ឈ្មោះគោលការណ៍ និងអាទិភាព') }}
                                    &nbsp;->&nbsp;3) {{ localize('workflow_form_quick_help_3', 'បន្ថែមជំហានអនុម័ត និងជ្រើសអ្នកអនុម័ត') }}
                                    &nbsp;->&nbsp;4) {{ localize('save', 'រក្សាទុក') }}
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">{{ localize('module', 'Module') }} *</label>
                                    <select name="module_key" id="wf-module-key" class="form-control select-basic-single" required>
                                        <option value="">{{ localize('select_module', 'Select module') }}</option>
                                        @foreach ($moduleOptions as $moduleKey)
                                            <option value="{{ $moduleKey }}">
                                                {{ $moduleLabels[$moduleKey] ?? $formatKey($moduleKey) }} ({{ $moduleKey }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">{{ localize('request_type', 'Request type') }} *</label>
                                    <select name="request_type_key" id="wf-request-type-key" class="form-control select-basic-single" required>
                                        <option value="">{{ localize('select_request_type', 'Select request type') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">{{ localize('policy_name', 'Policy name') }} *</label>
                                    <input type="text" name="name" id="wf-name" class="form-control" required
                                        placeholder="{{ localize('workflow_policy_name_placeholder', 'ឧ. លំហូរអនុម័តសុំច្បាប់') }}">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">{{ localize('priority', 'Priority') }} *</label>
                                    <input type="number" min="1" name="priority" id="wf-priority" class="form-control" required>
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label">{{ localize('status', 'Status') }}</label>
                                    <select name="is_active" id="wf-active" class="form-control">
                                        <option value="1">{{ localize('active', 'Active') }}</option>
                                        <option value="0">{{ localize('inactive', 'Inactive') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ localize('description', 'Description') }}</label>
                                    <textarea name="description" id="wf-description" class="form-control" rows="2"
                                        placeholder="{{ localize('workflow_description_placeholder', 'ពិពណ៌នាខ្លីអំពីគោលការណ៍នេះ') }}"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ localize('condition_json', 'Condition (JSON, optional)') }}</label>
                                    <textarea name="condition_json" id="wf-condition-json" class="form-control" rows="2"
                                        placeholder='{"min_days":2,"max_days":3}'></textarea>
                                    <small class="text-muted">
                                        {{ localize('condition_json_help_simple', 'វាលនេះមិនចាំបាច់ទេ។ ប្រើតែពេលចង់បែងចែក policy តាមលក្ខខណ្ឌពិសេស។') }}
                                    </small>
                                </div>
                                <div class="col-md-12 d-flex justify-content-between align-items-center">
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="wf-apply-template">
                                        <i class="fa fa-magic"></i>&nbsp;{{ localize('apply_template', 'Apply template') }}
                                    </button>
                                    <div>
                                        <button type="button" class="btn btn-outline-info btn-sm" id="wf-preview-resolution">
                                            <i class="fa fa-search"></i>&nbsp;{{ localize('preview_resolution', 'Preview Resolution') }}
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="small text-muted">
                                        <strong>{{ localize('preview_optional_label', 'ផ្នែកសាកល្បង (មិនចាំបាច់):') }}</strong>
                                        {{ localize('preview_optional_desc', 'ប្រើសម្រាប់ពិនិត្យថាលំហូរដែលកំណត់ អាចផ្គូផ្គងតាមបរិបទបានឬអត់ មុនរក្សាទុក។') }}
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">{{ localize('preview_days', 'Preview days') }}</label>
                                    <input type="number" min="0" id="wf-preview-days" class="form-control" value="1">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">{{ localize('preview_employee_id', 'Preview employee ID') }}</label>
                                    <input type="number" min="1" id="wf-preview-employee-id" class="form-control"
                                        placeholder="{{ localize('optional', 'Optional') }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">{{ localize('preview_department_id', 'Preview department ID') }}</label>
                                    <input type="number" min="1" id="wf-preview-department-id" class="form-control"
                                        placeholder="{{ localize('optional', 'Optional') }}">
                                </div>
                                <div class="col-md-12">
                                    <div id="wf-preview-result" class="small"></div>
                                </div>
                            </div>
                            <hr class="my-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0">{{ localize('approval_steps', 'Approval steps') }}</h6>
                                <button type="button" class="btn btn-success-soft btn-sm" id="wf-add-step-row">
                                    <i class="fa fa-plus"></i>&nbsp;{{ localize('add', 'Add') }}
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>{{ localize('sl', 'ល.រ') }}</th>
                                            <th>{{ localize('step_key_optional', 'កូដជំហាន (មិនបង្ខំ)') }}</th>
                                            <th>{{ localize('step_name', 'Step name') }}</th>
                                            <th>{{ localize('action_label_simple', 'សកម្មភាព') }}</th>
                                            <th>{{ localize('actor_type_simple', 'ប្រភេទអ្នកអនុម័ត') }}</th>
                                            <th>{{ localize('actor_target_simple', 'ជ្រើសអ្នកអនុម័ត') }}</th>
                                            <th>{{ localize('scope', 'Scope') }}</th>
                                            <th>{{ localize('final_question', 'ជំហានចុងក្រោយ?') }}</th>
                                            <th>{{ localize('required_question', 'ចាំបាច់?') }}</th>
                                            <th>{{ localize('can_return_question', 'អាចត្រឡប់?') }}</th>
                                            <th>{{ localize('can_reject_question', 'អាចបដិសេធ?') }}</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="wf-steps-body"></tbody>
                                </table>
                            </div>
                            <small class="text-muted d-block mt-2">
                                {{ localize('workflow_final_step_note', 'ចំណាំ: ត្រូវមានយ៉ាងហោចណាស់ ១ ជំហានដែលកំណត់ជា "ជំហានចុងក្រោយ? = បាទ/ចាស"។') }}
                            </small>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-danger"
                                data-bs-dismiss="modal">{{ localize('close', 'Close') }}</button>
                            <button type="submit" class="btn btn-primary">{{ localize('save', 'Save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endcanany
@endsection

@push('js')
    <script>
        (function($) {
            "use strict";

            var storeAction = @json(route('workflow-policies.store'));
            var previewUrl = @json(route('workflow-policies.preview'));
            var actionTypeLabels = @json($actionTypeLabels);
            var actorTypeLabels = @json($actorTypeLabels);
            var scopeTypeLabels = @json($scopeTypeLabels);
            var responsibilities = @json($responsibilities);
            var positions = @json($positions);
            var spatieRoles = @json($spatieRoles);
            var users = @json($users);
            var moduleLabels = @json($moduleLabels);
            var requestTypeLabels = @json($requestTypeLabels);
            var allRequestTypeOptions = @json(array_values($requestTypeOptions));
            var requestTypeOptionsByModule = @json($requestTypeOptionsByModule);
            var policyTemplates = @json($policyTemplates);

            var yesText = @json(localize('yes', 'Yes'));
            var noText = @json(localize('no', 'No'));
            var addText = @json(localize('add', 'Add'));
            var editText = @json(localize('edit', 'Edit'));
            var selectRequestTypeText = @json(localize('select_request_type', 'Select request type'));
            var selectUserText = @json(localize('select_user', 'Select user'));
            var selectPositionText = @json(localize('select_position', 'ជ្រើសមុខតំណែង'));
            var selectResponsibilityText = @json(localize('select_responsibility', 'ជ្រើសតួនាទីទទួលខុសត្រូវ'));
            var selectRoleText = @json(localize('select_role', 'ជ្រើសតួនាទី'));
            var stepOptionalText = @json(localize('optional', 'Optional'));
            var candidateLabelText = @json(localize('workflow_candidates_label', 'នាក់ដែលអាចអនុវត្ត'));
            var allText = @json(localize('all', 'All'));
            var noTemplateText = @json(localize('workflow_template_not_found', 'No template found for selected module and request type.'));
            var previewNoResultText = @json(localize('preview_no_result', 'No matching workflow for the preview context.'));

            function escapeHtml(value) {
                return String(value || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function fallbackLabel(key) {
                return String(key || '').replace(/[_\-.]+/g, ' ').replace(/\b\w/g, function(ch) {
                    return ch.toUpperCase();
                });
            }

            function requestTypeLabel(key) {
                return requestTypeLabels[key] || fallbackLabel(key);
            }

            function mapToOptions(map) {
                var html = '';
                Object.keys(map || {}).forEach(function(key) {
                    html += '<option value="' + escapeHtml(key) + '">' + escapeHtml(map[key] || key) + '</option>';
                });
                return html;
            }

            function setSelectValue($select, value) {
                $select.val(value);
                if ($select.data('select2')) {
                    $select.trigger('change.select2');
                } else {
                    $select.trigger('change');
                }
            }

            function optionListFromObjects(items, valueKey, labelKey, selectedValue, placeholderText) {
                var placeholder = String(placeholderText || '');
                var html = '<option value="">' + escapeHtml(placeholder) + '</option>';
                (items || []).forEach(function(item) {
                    var value = String(item[valueKey] || '');
                    var label = String(item[labelKey] || value);
                    var selected = String(selectedValue || '') === value ? ' selected' : '';
                    html += '<option value="' + escapeHtml(value) + '"' + selected + '>' + escapeHtml(label) + '</option>';
                });
                return html;
            }

            function initActorUserSelect2($scope) {
                if (!$.fn || typeof $.fn.select2 !== 'function') {
                    return;
                }

                var $root = $scope && $scope.length ? $scope : $(document);
                $root.find('.wf-actor-user').each(function() {
                    var $select = $(this);
                    if ($select.data('select2')) {
                        return;
                    }

                    $select.select2({
                        width: '100%',
                        allowClear: true,
                        placeholder: selectUserText,
                        dropdownParent: $('#workflow-policy-modal')
                    });
                });
            }

            function initWorkflowModalSelect2() {
                if (!$.fn || typeof $.fn.select2 !== 'function') {
                    return;
                }

                var $modal = $('#workflow-policy-modal');
                $modal.find('#wf-module-key, #wf-request-type-key').each(function() {
                    var $select = $(this);
                    if ($select.data('select2')) {
                        $select.select2('destroy');
                    }

                    $select.select2({
                        width: '100%',
                        dropdownParent: $modal
                    });
                });
            }

            function roleCodeToResponsibilityId(code) {
                var found = (responsibilities || []).find(function(item) {
                    return String(item.code || '') === String(code || '');
                });
                return found ? String(found.id) : '';
            }

            function yesNoSelect(name, value) {
                return '<select name="' + name + '" class="form-control form-control-sm">' +
                    '<option value="1"' + (String(value) === '1' ? ' selected' : '') + '>' + yesText + '</option>' +
                    '<option value="0"' + (String(value) === '0' ? ' selected' : '') + '>' + noText + '</option>' +
                    '</select>';
            }

            function stepRowHtml(index, step) {
                step = step || {};
                var actorType = String(step.actor_type || 'responsibility');
                var responsibilityId = step.actor_responsibility_id || step.system_role_id || roleCodeToResponsibilityId(step.org_role);

                var html = '';
                html += '<tr class="wf-step-row">';
                html += '<td><input type="number" min="1" name="steps[' + index + '][step_order]" class="form-control form-control-sm" value="' + escapeHtml(step.step_order || (index + 1)) + '" required></td>';
                html += '<td><input type="text" name="steps[' + index + '][step_key]" class="form-control form-control-sm" value="' + escapeHtml(step.step_key || '') + '" placeholder="' + escapeHtml(stepOptionalText) + '"></td>';
                html += '<td><input type="text" name="steps[' + index + '][step_name]" class="form-control form-control-sm" value="' + escapeHtml(step.step_name || '') + '" required></td>';
                html += '<td><select name="steps[' + index + '][action_type]" class="form-control form-control-sm">' + mapToOptions(actionTypeLabels) + '</select></td>';
                html += '<td><select name="steps[' + index + '][actor_type]" class="form-control form-control-sm wf-actor-type">' + mapToOptions(actorTypeLabels) + '</select></td>';
                html += '<td>';
                html += '<select name="steps[' + index + '][actor_user_id]" class="form-control form-control-sm wf-actor-input wf-actor-user mb-1">' +
                    optionListFromObjects(users, 'id', 'label', step.actor_user_id, selectUserText) + '</select>';
                html += '<select name="steps[' + index + '][actor_position_id]" class="form-control form-control-sm wf-actor-input wf-actor-position mb-1">' +
                    optionListFromObjects(positions, 'id', 'label', step.actor_position_id, selectPositionText) + '</select>';
                html += '<select name="steps[' + index + '][actor_responsibility_id]" class="form-control form-control-sm wf-actor-input wf-actor-responsibility mb-1">' +
                    optionListFromObjects(responsibilities, 'id', 'label', responsibilityId, selectResponsibilityText) + '</select>';
                html += '<select name="steps[' + index + '][actor_role_id]" class="form-control form-control-sm wf-actor-input wf-actor-role mb-1">' +
                    optionListFromObjects(spatieRoles, 'id', 'label', step.actor_role_id, selectRoleText) + '</select>';
                html += '<input type="hidden" name="steps[' + index + '][org_role]" class="wf-org-role-legacy" value="' + escapeHtml(step.org_role || '') + '">';
                html += '<input type="hidden" name="steps[' + index + '][system_role_id]" class="wf-system-role-legacy" value="' + escapeHtml(step.system_role_id || '') + '">';
                html += '</td>';
                html += '<td><select name="steps[' + index + '][scope_type]" class="form-control form-control-sm">' + mapToOptions(scopeTypeLabels) + '</select></td>';
                html += '<td>' + yesNoSelect('steps[' + index + '][is_final_approval]', String(step.is_final_approval || 0)) + '</td>';
                html += '<td>' + yesNoSelect('steps[' + index + '][is_required]', String(step.is_required || 1)) + '</td>';
                html += '<td>' + yesNoSelect('steps[' + index + '][can_return]', String(step.can_return || 1)) + '</td>';
                html += '<td>' + yesNoSelect('steps[' + index + '][can_reject]', String(step.can_reject || 1)) + '</td>';
                html += '<td><button type="button" class="btn btn-danger-soft btn-sm wf-remove-row"><i class="fa fa-trash"></i></button></td>';
                html += '</tr>';

                return html;
            }

            function syncLegacyRoleFields($row) {
                var actorType = String($row.find('.wf-actor-type').val() || '');
                var roleCode = '';
                var roleId = '';

                if (actorType === 'responsibility') {
                    roleId = String($row.find('.wf-actor-responsibility').val() || '');
                    var roleMatch = (responsibilities || []).find(function(item) {
                        return String(item.id) === roleId;
                    });
                    roleCode = roleMatch ? String(roleMatch.code || '') : '';
                }

                $row.find('.wf-org-role-legacy').val(roleCode);
                $row.find('.wf-system-role-legacy').val(roleId);
            }

            function toggleActorInputs($row) {
                var actorType = String($row.find('.wf-actor-type').val() || '');
                $row.find('.wf-actor-input').each(function() {
                    var $input = $(this);
                    $input.hide();
                    $input.prop('required', false);
                    if ($input.hasClass('wf-actor-user') && $input.data('select2')) {
                        $input.next('.select2-container').hide();
                    }
                });

                if (actorType === 'specific_user') {
                    var $userSelect = $row.find('.wf-actor-user');
                    $userSelect.show().prop('required', true);
                    if ($userSelect.data('select2')) {
                        $userSelect.next('.select2-container').show();
                    }
                } else if (actorType === 'position') {
                    $row.find('.wf-actor-position').show().prop('required', true);
                } else if (actorType === 'responsibility') {
                    $row.find('.wf-actor-responsibility').show().prop('required', true);
                } else if (actorType === 'spatie_role') {
                    $row.find('.wf-actor-role').show().prop('required', true);
                }

                syncLegacyRoleFields($row);
            }

            function applyStepDefaults($row, step) {
                $row.find('select[name*="[action_type]"]').val(step.action_type || 'approve');
                $row.find('select[name*="[actor_type]"]').val(step.actor_type || 'responsibility');
                $row.find('select[name*="[scope_type]"]').val(step.scope_type || 'self_and_children');
                toggleActorInputs($row);
            }

            function renderRows(steps) {
                steps = Array.isArray(steps) && steps.length ? steps : [{
                    step_order: 1,
                    step_key: '',
                    step_name: '',
                    action_type: 'approve',
                    actor_type: 'responsibility',
                    scope_type: 'self_and_children',
                    is_final_approval: 1,
                    is_required: 1,
                    can_return: 1,
                    can_reject: 1
                }];

                var html = '';
                steps.forEach(function(step, idx) {
                    html += stepRowHtml(idx, step || {});
                });
                $('#wf-steps-body').html(html);
                initActorUserSelect2($('#wf-steps-body'));

                $('#wf-steps-body .wf-step-row').each(function(i) {
                    applyStepDefaults($(this), steps[i] || {});
                });
            }

            function requestTypeOptionsForModule(moduleKey) {
                var key = String(moduleKey || '').trim();
                if (key !== '' && Array.isArray(requestTypeOptionsByModule[key])) {
                    return requestTypeOptionsByModule[key];
                }
                return Array.isArray(allRequestTypeOptions) ? allRequestTypeOptions : [];
            }

            function renderRequestTypeSelect($select, options, selectedValue, firstOptionText) {
                var html = '<option value="">' + escapeHtml(firstOptionText) + '</option>';
                (options || []).forEach(function(requestTypeKey) {
                    html += '<option value="' + escapeHtml(requestTypeKey) + '">'
                        + escapeHtml(requestTypeLabel(requestTypeKey)) + ' (' + escapeHtml(requestTypeKey) + ')'
                        + '</option>';
                });
                $select.html(html);
                var nextValue = String(selectedValue || '');
                if (nextValue === '' || options.indexOf(nextValue) === -1) {
                    nextValue = '';
                }
                setSelectValue($select, nextValue);
            }

            function syncModalRequestTypes(selectedValue) {
                var moduleKey = String($('#wf-module-key').val() || '').trim();
                var options = requestTypeOptionsForModule(moduleKey);
                renderRequestTypeSelect($('#wf-request-type-key'), options, selectedValue, selectRequestTypeText);
            }

            function syncFilterRequestTypes(selectedValue) {
                var moduleKey = String($('#wf-filter-module-key').val() || '').trim();
                var options = requestTypeOptionsForModule(moduleKey);
                renderRequestTypeSelect($('#wf-filter-request-type-key'), options, selectedValue, allText);
            }

            function templateKey(moduleKey, requestTypeKey) {
                return String(moduleKey || '') + '::' + String(requestTypeKey || '');
            }

            function applyTemplate() {
                var moduleKey = String($('#wf-module-key').val() || '').trim();
                var requestTypeKey = String($('#wf-request-type-key').val() || '').trim();
                var template = policyTemplates[templateKey(moduleKey, requestTypeKey)];
                if (!template) {
                    if (window.toastr && typeof window.toastr.warning === 'function') {
                        window.toastr.warning(noTemplateText);
                    }
                    return;
                }

                $('#wf-name').val(template.name || '');
                $('#wf-description').val(template.description || '');
                $('#wf-priority').val(template.priority || 100);
                var conditionPayload = template.condition_json || [];
                $('#wf-condition-json').val(Array.isArray(conditionPayload) && !conditionPayload.length ? '' : JSON.stringify(conditionPayload));
                renderRows(template.steps || []);
            }

            function resetCreateMode() {
                $('#workflow-policy-modal-title').text(addText);
                $('#workflow-policy-form').attr('action', storeAction);
                $('#workflow-policy-form-method').val('POST');
                setSelectValue($('#wf-module-key'), '');
                setSelectValue($('#wf-request-type-key'), '');
                $('#wf-name,#wf-description,#wf-condition-json').val('');
                $('#wf-priority').val(100);
                setSelectValue($('#wf-active'), '1');
                $('#wf-preview-result').empty();
                renderRows([]);
                syncModalRequestTypes('');
            }

            function previewResolution() {
                var payload = {
                    module_key: String($('#wf-module-key').val() || ''),
                    request_type_key: String($('#wf-request-type-key').val() || ''),
                    days: $('#wf-preview-days').val() || null,
                    employee_id: $('#wf-preview-employee-id').val() || null,
                    department_id: $('#wf-preview-department-id').val() || null
                };

                if (!payload.module_key || !payload.request_type_key) {
                    return;
                }

                $.ajax({
                    url: previewUrl,
                    type: 'GET',
                    data: payload,
                    success: function(res) {
                        var data = res && res.data ? res.data : null;
                        if (!data || !Array.isArray(data.steps)) {
                            $('#wf-preview-result').html('<div class="text-warning">' + escapeHtml(previewNoResultText) + '</div>');
                            return;
                        }

                        var html = '<div class="alert alert-light border mt-2 mb-0">';
                        html += '<div class="fw-semibold mb-1">' + escapeHtml(data.name || '') + '</div>';
                        html += '<ul class="mb-0 ps-3">';
                        data.steps.forEach(function(step) {
                            var actorType = String(step.resolved_actor_type || step.actor_type || '');
                            var actorTypeText = String(actorTypeLabels[actorType] || actorType);
                            var count = Number(step.resolved_candidate_count || 0);
                            html += '<li><strong>' + escapeHtml(String(step.step_order || '')) + '. ' + escapeHtml(String(step.step_name || '')) + '</strong> - '
                                + escapeHtml(actorTypeText) + ' - ' + count + ' ' + escapeHtml(candidateLabelText) + '</li>';
                        });
                        html += '</ul></div>';
                        $('#wf-preview-result').html(html);
                    },
                    error: function(xhr) {
                        var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : previewNoResultText;
                        $('#wf-preview-result').html('<div class="text-danger">' + escapeHtml(msg) + '</div>');
                    }
                });
            }

            $(document).on('click', '.workflow-policy-create-btn', resetCreateMode);

            $(document).on('click', '.workflow-policy-edit-btn', function() {
                var $button = $(this);
                $('#workflow-policy-modal-title').text(editText);
                $('#workflow-policy-form').attr('action', $button.data('action'));
                $('#workflow-policy-form-method').val('PATCH');
                setSelectValue($('#wf-module-key'), String($button.data('module') || ''));
                syncModalRequestTypes(String($button.data('request-type') || ''));
                $('#wf-name').val($button.data('name'));
                $('#wf-priority').val($button.data('priority'));
                setSelectValue($('#wf-active'), String($button.data('active')));

                var desc = '';
                try {
                    desc = JSON.parse($button.attr('data-description') || '""');
                } catch (e) {
                    desc = $button.data('description') || '';
                }
                $('#wf-description').val(desc || '');

                var condition = $button.attr('data-condition') || '[]';
                try {
                    $('#wf-condition-json').val(JSON.stringify(JSON.parse(condition)));
                } catch (e) {
                    $('#wf-condition-json').val('');
                }

                var steps = [];
                try {
                    steps = JSON.parse($button.attr('data-steps') || '[]');
                } catch (e) {
                    steps = [];
                }
                $('#wf-preview-result').empty();
                renderRows(steps);
            });

            $(document).on('change', '#wf-module-key', function() {
                syncModalRequestTypes('');
            });

            $(document).on('change', '#wf-filter-module-key', function() {
                syncFilterRequestTypes('');
            });

            $(document).on('click', '#wf-add-step-row', function() {
                var index = $('#wf-steps-body .wf-step-row').length;
                $('#wf-steps-body').append(stepRowHtml(index, {
                    step_order: index + 1,
                    action_type: 'approve',
                    actor_type: 'responsibility',
                    scope_type: 'self_and_children',
                    is_final_approval: 0,
                    is_required: 1,
                    can_return: 1,
                    can_reject: 1
                }));
                var $newRow = $('#wf-steps-body .wf-step-row').last();
                initActorUserSelect2($newRow);
                applyStepDefaults($newRow, {});
            });

            $(document).on('click', '.wf-remove-row', function() {
                if ($('#wf-steps-body .wf-step-row').length <= 1) {
                    return;
                }
                $(this).closest('tr').remove();

                var rows = [];
                $('#wf-steps-body .wf-step-row').each(function(idx) {
                    var $row = $(this);
                    rows.push({
                        step_order: $row.find('input[name*="[step_order]"]').val() || idx + 1,
                        step_key: $row.find('input[name*="[step_key]"]').val() || '',
                        step_name: $row.find('input[name*="[step_name]"]').val() || '',
                        action_type: $row.find('select[name*="[action_type]"]').val() || 'approve',
                        actor_type: $row.find('select[name*="[actor_type]"]').val() || 'responsibility',
                        actor_user_id: $row.find('.wf-actor-user').val() || null,
                        actor_position_id: $row.find('.wf-actor-position').val() || null,
                        actor_responsibility_id: $row.find('.wf-actor-responsibility').val() || null,
                        actor_role_id: $row.find('.wf-actor-role').val() || null,
                        org_role: $row.find('.wf-org-role-legacy').val() || '',
                        system_role_id: $row.find('.wf-system-role-legacy').val() || null,
                        scope_type: $row.find('select[name*="[scope_type]"]').val() || 'self_and_children',
                        is_final_approval: $row.find('select[name*="[is_final_approval]"]').val() || '0',
                        is_required: $row.find('select[name*="[is_required]"]').val() || '1',
                        can_return: $row.find('select[name*="[can_return]"]').val() || '1',
                        can_reject: $row.find('select[name*="[can_reject]"]').val() || '1'
                    });
                });
                renderRows(rows);
            });

            $(document).on('change', '.wf-actor-type, .wf-actor-responsibility', function() {
                var $row = $(this).closest('.wf-step-row');
                toggleActorInputs($row);
            });

            $(document).on('click', '#wf-apply-template', applyTemplate);
            $(document).on('click', '#wf-preview-resolution', previewResolution);

            $('#workflow-policy-modal').on('shown.bs.modal', function() {
                initWorkflowModalSelect2();
                initActorUserSelect2($('#wf-steps-body'));
            });

            initWorkflowModalSelect2();
            syncModalRequestTypes($('#wf-request-type-key').val() || '');
            syncFilterRequestTypes($('#wf-filter-request-type-key').val() || '');
            renderRows([]);
        })(window.jQuery);
    </script>
@endpush
