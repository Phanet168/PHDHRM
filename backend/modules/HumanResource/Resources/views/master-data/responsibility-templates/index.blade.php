@extends('backend.layouts.app')
@section('title', localize('responsibility_templates', 'Responsibility Templates'))
@section('content')
    @include('humanresource::master-data.org-structure.header')
    @include('backend.layouts.common.validation')

    <div class="card mb-4 fixed-tab-body">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h6 class="fs-17 fw-semi-bold mb-0">{{ localize('responsibility_templates', 'Responsibility Templates') }}</h6>
                <small class="text-muted">
                    {{ localize('responsibility_templates_desc', 'Module-specific presets for assignment UX: responsibility + default scope + action presets.') }}
                </small>
            </div>
            @canany(['create_org_governance', 'create_department'])
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal"
                    data-bs-target="#create-responsibility-template-modal">
                    <i class="fa fa-plus-circle"></i>&nbsp;{{ localize('add', 'Add') }}
                </button>
            @endcanany
        </div>

        <div class="card-body">
            <form method="GET" action="{{ route('responsibility-templates.index') }}" class="mb-3">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label mb-1">{{ localize('module', 'Module') }}</label>
                        <select name="module_key" class="form-control select-basic-single">
                            <option value="">{{ localize('all', 'All') }}</option>
                            @foreach ($module_options as $moduleKey)
                                <option value="{{ $moduleKey }}" @selected($selected_module === $moduleKey)>
                                    {{ $moduleKey }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1">{{ localize('status', 'Status') }}</label>
                        <select name="is_active" class="form-control select-basic-single">
                            <option value="">{{ localize('all', 'All') }}</option>
                            <option value="1" @selected((string) $selected_status === '1')>{{ localize('active', 'Active') }}</option>
                            <option value="0" @selected((string) $selected_status === '0')>{{ localize('inactive', 'Inactive') }}</option>
                        </select>
                    </div>
                    <div class="col-md-5 text-md-end">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fa fa-search"></i>&nbsp;{{ localize('filter', 'Filter') }}
                        </button>
                        <a href="{{ route('responsibility-templates.index') }}" class="btn btn-secondary btn-sm">
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
                            <th width="13%">{{ localize('template_key', 'Template key') }}</th>
                            <th width="15%">{{ localize('template_name', 'Template name') }}</th>
                            <th width="13%">{{ localize('responsibility', 'Responsibility') }}</th>
                            <th width="12%">{{ localize('position', 'Position') }}</th>
                            <th width="11%">{{ localize('default_scope', 'Default scope') }}</th>
                            <th width="10%">{{ localize('actions', 'Actions') }}</th>
                            <th width="6%">{{ localize('status', 'Status') }}</th>
                            <th width="6%">{{ localize('action', 'Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($records as $item)
                            @php
                                $selectedActions = collect($item->action_presets_json ?? [])->map(fn ($v) => (string) $v)->values()->all();
                                $scopeLabel = $scope_labels[$item->default_scope_type] ?? $item->default_scope_type;
                                $positionName = $item->position?->position_name_km ?: ($item->position?->position_name ?? '-');
                            @endphp
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td><code>{{ $item->module_key }}</code></td>
                                <td><code>{{ $item->template_key }}</code></td>
                                <td>
                                    <div class="fw-semibold">{{ $item->name_km ?: $item->name }}</div>
                                    @if (!empty($item->name_km) && !empty($item->name))
                                        <small class="text-muted">{{ $item->name }}</small>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $item->responsibility?->name_km ?: ($item->responsibility?->name ?? '-') }}</div>
                                    <small class="text-muted"><code>{{ $item->responsibility?->code ?? '-' }}</code></small>
                                </td>
                                <td>{{ $positionName }}</td>
                                <td>{{ $scopeLabel }}</td>
                                <td>
                                    @if (!empty($selectedActions))
                                        <div class="small">
                                            @foreach ($selectedActions as $actionKey)
                                                <span class="badge bg-light text-dark border mb-1">
                                                    {{ $action_labels[$actionKey] ?? $actionKey }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-muted">-</span>
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
                                        <button type="button" class="btn btn-primary-soft btn-sm me-1" data-bs-toggle="modal"
                                            data-bs-target="#edit-responsibility-template-modal-{{ $item->id }}">
                                            <i class="fa fa-edit"></i>
                                        </button>
                                    @endcanany
                                    @canany(['delete_org_governance', 'delete_department'])
                                        <a href="javascript:void(0)" class="btn btn-danger-soft btn-sm delete-confirm"
                                            data-route="{{ route('responsibility-templates.destroy', $item->id) }}"
                                            data-csrf="{{ csrf_token() }}">
                                            <i class="fa fa-trash"></i>
                                        </a>
                                    @endcanany
                                </td>
                            </tr>

                            @canany(['update_org_governance', 'update_department'])
                                <div class="modal fade" id="edit-responsibility-template-modal-{{ $item->id }}"
                                    data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">{{ localize('edit', 'Edit') }}</h5>
                                            </div>
                                            <form action="{{ route('responsibility-templates.update', $item->id) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <div class="modal-body">
                                                    @include('humanresource::master-data.responsibility-templates.partials.form-fields', [
                                                        'module_options' => $module_options,
                                                        'positions' => $positions,
                                                        'responsibilities' => $responsibilities,
                                                        'scope_options' => $scope_options,
                                                        'scope_labels' => $scope_labels,
                                                        'module_action_map' => $module_action_map,
                                                        'action_labels' => $action_labels,
                                                        'form_item' => $item,
                                                    ])
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-danger"
                                                        data-bs-dismiss="modal">{{ localize('close', 'Close') }}</button>
                                                    <button type="submit"
                                                        class="btn btn-primary">{{ localize('save', 'Save') }}</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endcanany
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">{{ localize('no_data_found', 'No data found') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @canany(['create_org_governance', 'create_department'])
        <div class="modal fade" id="create-responsibility-template-modal" data-bs-backdrop="static" data-bs-keyboard="false"
            tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ localize('add', 'Add') }}</h5>
                    </div>
                    <form action="{{ route('responsibility-templates.store') }}" method="POST">
                        @csrf
                        <div class="modal-body">
                            @include('humanresource::master-data.responsibility-templates.partials.form-fields', [
                                'module_options' => $module_options,
                                'positions' => $positions,
                                'responsibilities' => $responsibilities,
                                'scope_options' => $scope_options,
                                'scope_labels' => $scope_labels,
                                'module_action_map' => $module_action_map,
                                'action_labels' => $action_labels,
                                'form_item' => null,
                            ])
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

            var moduleActionMap = @json($module_action_map);
            var actionLabels = @json($action_labels);
            var selectActionText = @json(localize('select_actions', 'Select actions'));

            function buildActionCheckboxes(moduleKey, selectedActions, inputName) {
                var actions = moduleActionMap[String(moduleKey || '').trim().toLowerCase()] || [];
                var selected = Array.isArray(selectedActions) ? selectedActions.map(function(v) {
                    return String(v || '').trim().toLowerCase();
                }) : [];

                if (!actions.length) {
                    return '<div class="text-muted small">-</div>';
                }

                var html = '<div class="row g-1">';
                actions.forEach(function(actionKey) {
                    var checked = selected.indexOf(String(actionKey)) !== -1 ? ' checked' : '';
                    var label = actionLabels[actionKey] || actionKey;
                    html += '<div class="col-md-6">';
                    html += '<label class="form-check form-check-sm">';
                    html += '<input class="form-check-input" type="checkbox" name="' + inputName + '[]" value="' + actionKey + '"' + checked + '>';
                    html += '<span class="form-check-label small">' + label + '</span>';
                    html += '</label>';
                    html += '</div>';
                });
                html += '</div>';

                return html;
            }

            function renderActionsForForm($form) {
                var $moduleSelect = $form.find('.rt-module-key').first();
                var moduleKey = String($moduleSelect.val() || '').trim().toLowerCase();
                var selectedActions = [];
                var selectedRaw = $form.find('.rt-action-presets-selected').first().val();
                try {
                    selectedActions = selectedRaw ? JSON.parse(selectedRaw) : [];
                } catch (e) {
                    selectedActions = [];
                }

                var html = buildActionCheckboxes(moduleKey, selectedActions, 'action_presets');
                $form.find('.rt-actions-wrap').html(html);
                $form.find('.rt-action-presets-selected').val('[]');
            }

            $(document).on('change', '.rt-module-key', function() {
                renderActionsForForm($(this).closest('form'));
            });

            $('form').each(function() {
                renderActionsForForm($(this));
            });
        })(window.jQuery);
    </script>
@endpush
