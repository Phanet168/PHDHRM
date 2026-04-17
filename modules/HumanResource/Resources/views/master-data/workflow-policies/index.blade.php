@extends('backend.layouts.app')
@section('title', localize('workflow_policy_matrix', 'Workflow Policy Matrix'))
@section('content')
    @include('humanresource::master-data.org-structure.header')
    @include('backend.layouts.common.validation')
    @include('backend.layouts.common.message')

    @php
        $defaultStep = [
            'step_order' => 1,
            'step_key' => '',
            'step_name' => '',
            'action_type' => 'approve',
            'org_role' => 'manager',
            'scope_type' => 'self_and_children',
            'is_final_approval' => 1,
            'is_required' => 1,
            'can_return' => 1,
            'can_reject' => 1,
        ];
        $actionTypeLabels = is_array($action_type_labels) ? $action_type_labels : $action_type_labels->toArray();
        $orgRoleLabels = is_array($org_role_labels) ? $org_role_labels : $org_role_labels->toArray();
        $scopeTypeLabels = is_array($scope_type_labels) ? $scope_type_labels : $scope_type_labels->toArray();
        $formatKey = function (?string $key): string {
            $key = (string) $key;
            if ($key === '') {
                return '-';
            }
            return ucwords(str_replace(['_', '-', '.'], ' ', $key));
        };
        $conditionLabelMap = [
            'days' => localize('days', 'Days'),
            'min_days' => localize('min_days', 'Minimum days'),
            'max_days' => localize('max_days', 'Maximum days'),
            'employee_type_id' => localize('employee_type', 'Employee type'),
            'employee_type_code' => localize('employee_type_code', 'Employee type code'),
            'org_unit_type_id' => localize('org_unit_type', 'Org unit type'),
            'org_unit_type_code' => localize('org_unit_type_code', 'Org unit type code'),
            'is_full_right' => localize('full_right_status', 'Full-right status'),
        ];
        $formatConditionValue = function ($value): string {
            if (is_bool($value)) {
                return $value ? localize('yes', 'Yes') : localize('no', 'No');
            }
            if (is_array($value)) {
                return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            return (string) $value;
        };
    @endphp

    <div class="card mb-4 fixed-tab-body">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h6 class="fs-17 fw-semi-bold mb-0">{{ localize('workflow_policy_matrix', 'Workflow Policy Matrix') }}</h6>
                <small class="text-muted">{{ localize('workflow_policy_matrix_desc', 'Central approval rules by module + request type + condition.') }}</small>
            </div>
            @can('create_department')
                <button type="button" class="btn btn-success btn-sm workflow-policy-create-btn" data-bs-toggle="modal"
                    data-bs-target="#workflow-policy-modal">
                    <i class="fa fa-plus-circle"></i>&nbsp;{{ localize('add', 'Add') }}
                </button>
            @endcan
        </div>
        <div class="card-body">
            <div class="alert alert-info py-2 mb-3">
                <div class="small mb-0">
                    <strong>{{ localize('quick_guide', 'Quick guide') }}:</strong>
                    1) {{ localize('select_module_and_type', 'Select module + request type') }}
                    &nbsp;→&nbsp;2) {{ localize('set_approval_steps', 'Set approval steps') }}
                    &nbsp;→&nbsp;3) {{ localize('save_and_test', 'Save and test') }}
                </div>
            </div>

            <form method="GET" action="{{ route('workflow-policies.index') }}" class="mb-3">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label mb-1">{{ localize('module', 'Module') }}</label>
                        <select name="module_key" class="form-control select-basic-single">
                            <option value="">{{ localize('all', 'All') }}</option>
                            @foreach ($module_options as $moduleKey)
                                <option value="{{ $moduleKey }}" @selected($selected_module_key === $moduleKey)>
                                    {{ $formatKey($moduleKey) }} ({{ $moduleKey }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label mb-1">{{ localize('request_type', 'Request Type') }}</label>
                        <select name="request_type_key" class="form-control select-basic-single">
                            <option value="">{{ localize('all', 'All') }}</option>
                            @foreach ($request_type_options as $requestTypeKey)
                                <option value="{{ $requestTypeKey }}" @selected($selected_request_type_key === $requestTypeKey)>
                                    {{ $formatKey($requestTypeKey) }} ({{ $requestTypeKey }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i>&nbsp;{{ localize('filter', 'Filter') }}</button>
                        <a href="{{ route('workflow-policies.index') }}" class="btn btn-secondary btn-sm">{{ localize('reset', 'Reset') }}</a>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table display table-bordered table-striped table-hover">
                    <thead>
                        <tr>
                            <th width="4%">SL</th>
                            <th width="9%">{{ localize('module', 'Module') }}</th>
                            <th width="10%">{{ localize('request_type', 'Request Type') }}</th>
                            <th width="11%">{{ localize('policy_name', 'Policy name') }}</th>
                            <th width="6%">{{ localize('priority', 'Priority') }}</th>
                            <th width="18%">{{ localize('condition', 'Condition') }}</th>
                            <th width="24%">{{ localize('approval_steps', 'Approval steps') }}</th>
                            <th width="6%">{{ localize('status', 'Status') }}</th>
                            <th width="12%">{{ localize('action', 'Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse ($definitions as $item)
                            @php
                                $stepsPayload = $item->steps
                                    ->map(fn($s) => [
                                        'step_order' => (int) $s->step_order,
                                        'step_key' => (string) ($s->step_key ?? ''),
                                        'step_name' => (string) $s->step_name,
                                        'action_type' => (string) $s->action_type,
                                        'org_role' => (string) $s->org_role,
                                        'scope_type' => (string) $s->scope_type,
                                        'is_final_approval' => (int) $s->is_final_approval,
                                        'is_required' => (int) $s->is_required,
                                        'can_return' => (int) $s->can_return,
                                        'can_reject' => (int) $s->can_reject,
                                    ])
                                    ->values()
                                    ->all();
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
                                    @if (!empty($item->condition_json))
                                        <ul class="list-unstyled small mb-0">
                                            @foreach ($item->condition_json as $conditionKey => $conditionValue)
                                                <li>
                                                    <span class="text-muted">
                                                        {{ $conditionLabelMap[$conditionKey] ?? $formatKey($conditionKey) }}:
                                                    </span>
                                                    <span class="fw-semibold">{{ $formatConditionValue($conditionValue) }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($item->steps->isEmpty())
                                        <span class="text-danger">{{ localize('no_steps_defined', 'No steps defined') }}</span>
                                    @else
                                        <ol class="mb-0 ps-3">
                                            @foreach ($item->steps as $step)
                                                <li class="mb-1">
                                                    <div class="fw-semibold">{{ $step->step_order }}. {{ $step->step_name }}</div>
                                                    <small class="text-muted d-block">
                                                        {{ $actionTypeLabels[$step->action_type] ?? $step->action_type }}
                                                        &middot;
                                                        {{ $orgRoleLabels[$step->org_role] ?? $step->org_role }}
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
                                    @can('update_department')
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
                                    @endcan
                                    @can('delete_department')
                                        <a href="javascript:void(0)" class="btn btn-danger-soft btn-sm delete-confirm"
                                            data-route="{{ route('workflow-policies.destroy', $item->id) }}"
                                            data-csrf="{{ csrf_token() }}">
                                            <i class="fa fa-trash"></i>
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">{{ localize('no_data_found', 'No data found') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @canany(['create_department', 'update_department'])
        <div class="modal fade" id="workflow-policy-modal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
            aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="workflow-policy-modal-title">{{ localize('add', 'Add') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ localize('close', 'Close') }}"></button>
                    </div>
                    <form id="workflow-policy-form" action="{{ route('workflow-policies.store') }}" method="POST">
                        @csrf
                        <input type="hidden" id="workflow-policy-form-method" name="_method" value="POST">
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">{{ localize('module', 'Module') }} *</label>
                                    <input type="text" name="module_key" id="wf-module-key" class="form-control" list="wf-module-key-list" required>
                                    <small class="text-muted">{{ localize('module_key_help', 'Example: correspondence') }}</small>
                                    <datalist id="wf-module-key-list">
                                        @foreach ($module_options as $moduleKey)
                                            <option value="{{ $moduleKey }}">
                                        @endforeach
                                    </datalist>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">{{ localize('request_type', 'Request type') }} *</label>
                                    <input type="text" name="request_type_key" id="wf-request-type-key" class="form-control" list="wf-request-type-key-list" required>
                                    <small class="text-muted">{{ localize('request_type_key_help', 'Example: incoming_letter') }}</small>
                                    <datalist id="wf-request-type-key-list">
                                        @foreach ($request_type_options as $requestTypeKey)
                                            <option value="{{ $requestTypeKey }}">
                                        @endforeach
                                    </datalist>
                                </div>
                                <div class="col-md-3"><label class="form-label">{{ localize('policy_name', 'Policy name') }} *</label><input type="text" name="name" id="wf-name" class="form-control" required></div>
                                <div class="col-md-2"><label class="form-label">{{ localize('priority', 'Priority') }} *</label><input type="number" min="1" name="priority" id="wf-priority" class="form-control" required></div>
                                <div class="col-md-1"><label class="form-label">{{ localize('status', 'Status') }}</label><select name="is_active" id="wf-active" class="form-control"><option value="1">{{ localize('active', 'Active') }}</option><option value="0">{{ localize('inactive', 'Inactive') }}</option></select></div>
                                <div class="col-md-6"><label class="form-label">{{ localize('description', 'Description') }}</label><textarea name="description" id="wf-description" class="form-control" rows="2"></textarea></div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ localize('condition_json', 'Condition (JSON, optional)') }}</label>
                                    <textarea name="condition_json" id="wf-condition-json" class="form-control" rows="2" placeholder='{"min_days":2,"max_days":3}'></textarea>
                                    <small class="text-muted">{{ localize('condition_json_help', 'Leave empty if no condition is required.') }}</small>
                                </div>
                            </div>
                            <hr class="my-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0">{{ localize('approval_steps', 'Approval steps') }}</h6>
                                <button type="button" class="btn btn-success-soft btn-sm" id="wf-add-step-row"><i class="fa fa-plus"></i>&nbsp;{{ localize('add', 'Add') }}</button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>{{ localize('step_key', 'Step key') }}</th>
                                            <th>{{ localize('step_name', 'Step name') }}</th>
                                            <th>{{ localize('action', 'Action') }}</th>
                                            <th>{{ localize('org_role', 'Org role') }}</th>
                                            <th>{{ localize('scope', 'Scope') }}</th>
                                            <th>{{ localize('final', 'Final') }}</th>
                                            <th>{{ localize('required', 'Required') }}</th>
                                            <th>{{ localize('return', 'Return') }}</th>
                                            <th>{{ localize('reject', 'Reject') }}</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="wf-steps-body"></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-danger" data-bs-dismiss="modal">{{ localize('close', 'Close') }}</button>
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
            var defaultStep = @json($defaultStep);
            var actionTypeLabels = @json($actionTypeLabels);
            var orgRoleLabels = @json($orgRoleLabels);
            var scopeTypeLabels = @json($scopeTypeLabels);
            var yesText = @json(localize('yes', 'Yes'));
            var noText = @json(localize('no', 'No'));
            var addText = @json(localize('add', 'Add'));
            var editText = @json(localize('edit', 'Edit'));

            function yesNoSelect(name, value) {
                return '<select name="'+name+'" class="form-control form-control-sm">'
                    + '<option value="1"'+(String(value)==='1'?' selected':'')+'>'+yesText+'</option>'
                    + '<option value="0"'+(String(value)==='0'?' selected':'')+'>'+noText+'</option>'
                    + '</select>';
            }
            function listSelect(name, value, map) {
                var html = '<select name="'+name+'" class="form-control form-control-sm">';
                Object.keys(map || {}).forEach(function(code) {
                    var label = map[code] || code;
                    html += '<option value="'+code+'"'+(String(code)===String(value)?' selected':'')+'>'+label+'</option>';
                });
                return html + '</select>';
            }
            function text(name, value, req) { return '<input type="text" name="'+name+'" class="form-control form-control-sm" value="'+(value||'').replace(/"/g,'&quot;')+'" '+(req?'required':'')+'>'; }
            function number(name, value) { return '<input type="number" min="1" name="'+name+'" class="form-control form-control-sm" value="'+(value||'')+'" required>'; }
            function rowHtml(i, s) {
                return '<tr class="wf-step-row">'
                    + '<td>' + number('steps['+i+'][step_order]', s.step_order) + '</td>'
                    + '<td>' + text('steps['+i+'][step_key]', s.step_key, false) + '</td>'
                    + '<td>' + text('steps['+i+'][step_name]', s.step_name, true) + '</td>'
                    + '<td>' + listSelect('steps['+i+'][action_type]', s.action_type, actionTypeLabels) + '</td>'
                    + '<td>' + listSelect('steps['+i+'][org_role]', s.org_role, orgRoleLabels) + '</td>'
                    + '<td>' + listSelect('steps['+i+'][scope_type]', s.scope_type, scopeTypeLabels) + '</td>'
                    + '<td>' + yesNoSelect('steps['+i+'][is_final_approval]', String(s.is_final_approval)) + '</td>'
                    + '<td>' + yesNoSelect('steps['+i+'][is_required]', String(s.is_required)) + '</td>'
                    + '<td>' + yesNoSelect('steps['+i+'][can_return]', String(s.can_return)) + '</td>'
                    + '<td>' + yesNoSelect('steps['+i+'][can_reject]', String(s.can_reject)) + '</td>'
                    + '<td><button type="button" class="btn btn-danger-soft btn-sm wf-remove-row"><i class="fa fa-trash"></i></button></td>'
                    + '</tr>';
            }
            function renderRows(steps) {
                var rows = (steps && steps.length) ? steps : [defaultStep];
                var html = '';
                rows.forEach(function(s, i) { html += rowHtml(i, s); });
                $('#wf-steps-body').html(html);
            }
            function resetCreateMode() {
                $('#workflow-policy-modal-title').text(addText);
                $('#workflow-policy-form').attr('action', storeAction);
                $('#workflow-policy-form-method').val('POST');
                $('#wf-module-key,#wf-request-type-key,#wf-name,#wf-description,#wf-condition-json').val('');
                $('#wf-priority').val(100); $('#wf-active').val('1');
                renderRows([defaultStep]);
            }

            $(document).on('click', '.workflow-policy-create-btn', resetCreateMode);
            $(document).on('click', '.workflow-policy-edit-btn', function() {
                var $b = $(this);
                $('#workflow-policy-modal-title').text(editText);
                $('#workflow-policy-form').attr('action', $b.data('action'));
                $('#workflow-policy-form-method').val('PATCH');
                $('#wf-module-key').val($b.data('module'));
                $('#wf-request-type-key').val($b.data('request-type'));
                $('#wf-name').val($b.data('name'));
                $('#wf-priority').val($b.data('priority'));
                $('#wf-active').val(String($b.data('active')));
                var desc = '';
                try { desc = JSON.parse($b.attr('data-description') || '""'); } catch (e) { desc = $b.data('description') || ''; }
                $('#wf-description').val(desc || '');
                var condition = $b.attr('data-condition') || '[]';
                try { $('#wf-condition-json').val(JSON.stringify(JSON.parse(condition))); } catch (e) { $('#wf-condition-json').val(''); }
                var steps = [];
                try { steps = JSON.parse($b.attr('data-steps') || '[]'); } catch (e) { steps = []; }
                renderRows(steps);
            });
            $(document).on('click', '#wf-add-step-row', function() {
                var idx = $('#wf-steps-body .wf-step-row').length;
                $('#wf-steps-body').append(rowHtml(idx, defaultStep));
            });
            $(document).on('click', '.wf-remove-row', function() {
                if ($('#wf-steps-body .wf-step-row').length <= 1) return;
                $(this).closest('tr').remove();
                var rows = [];
                $('#wf-steps-body .wf-step-row').each(function(){
                    rows.push({
                        step_order: $(this).find('input[name*="[step_order]"]').val() || 1,
                        step_key: $(this).find('input[name*="[step_key]"]').val() || '',
                        step_name: $(this).find('input[name*="[step_name]"]').val() || '',
                        action_type: $(this).find('select[name*="[action_type]"]').val() || 'approve',
                        org_role: $(this).find('select[name*="[org_role]"]').val() || 'manager',
                        scope_type: $(this).find('select[name*="[scope_type]"]').val() || 'self_and_children',
                        is_final_approval: $(this).find('select[name*="[is_final_approval]"]').val() || '1',
                        is_required: $(this).find('select[name*="[is_required]"]').val() || '1',
                        can_return: $(this).find('select[name*="[can_return]"]').val() || '1',
                        can_reject: $(this).find('select[name*="[can_reject]"]').val() || '1'
                    });
                });
                renderRows(rows);
            });
        })(jQuery);
    </script>
@endpush
