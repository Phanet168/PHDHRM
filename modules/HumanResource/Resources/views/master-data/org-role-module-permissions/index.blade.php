@extends('backend.layouts.app')
@section('title', localize('org_role_permission_matrix', 'Org Role Permission Matrix'))
@section('content')
    @include('humanresource::master-data.org-structure.header')
    @include('backend.layouts.common.validation')

    <div class="card mb-4 fixed-tab-body">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h6 class="fs-17 fw-semi-bold mb-0">
                    {{ localize('org_role_permission_matrix', 'Org Role Permission Matrix') }}
                </h6>
                <small class="text-muted">
                    {{ localize('org_role_permission_matrix_desc', 'Configure module actions by org role (head/deputy/manager).') }}
                </small>
            </div>
            @can('create_department')
                @if ($matrix_ready)
                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal"
                        data-bs-target="#create-org-role-permission-modal">
                        <i class="fa fa-plus-circle"></i>&nbsp;{{ localize('add', 'Add') }}
                    </button>
                @endif
            @endcan
        </div>

        <div class="card-body">
            @if (!$matrix_ready)
                <div class="alert alert-warning mb-3">
                    <strong>{{ localize('action_required', 'Action required') }}:</strong>
                    {{ localize('permission_matrix_migration_hint', 'Org Role Permission Matrix table is not ready yet. Please run migration first.') }}
                    <code>php artisan migrate</code>
                </div>
            @endif

            <form method="GET" action="{{ route('org-role-module-permissions.index') }}" class="mb-3">
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
                    <div class="col-md-4">
                        <label class="form-label mb-1">{{ localize('role', 'Role') }}</label>
                        <select name="org_role" class="form-control select-basic-single">
                            <option value="">{{ localize('all', 'All') }}</option>
                            @foreach ($org_role_options as $roleKey)
                                <option value="{{ $roleKey }}" @selected($selected_role === $roleKey)>
                                    {{ $role_labels[$roleKey] ?? $roleKey }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">{{ localize('status', 'Status') }}</label>
                        <select name="is_active" class="form-control select-basic-single">
                            <option value="">{{ localize('all', 'All') }}</option>
                            <option value="1" @selected((string) $selected_status === '1')>{{ localize('active', 'Active') }}</option>
                            <option value="0" @selected((string) $selected_status === '0')>{{ localize('inactive', 'Inactive') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2 text-md-end">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fa fa-search"></i>&nbsp;{{ localize('filter', 'Filter') }}
                        </button>
                        <a href="{{ route('org-role-module-permissions.index') }}" class="btn btn-secondary btn-sm">
                            {{ localize('reset', 'Reset') }}
                        </a>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table display table-bordered table-striped table-hover">
                    <thead>
                        <tr>
                            <th width="5%">SL</th>
                            <th width="14%">{{ localize('module', 'Module') }}</th>
                            <th width="22%">{{ localize('action', 'Action') }}</th>
                            <th width="18%">{{ localize('role', 'Role') }}</th>
                            <th width="8%">{{ localize('status', 'Status') }}</th>
                            <th width="23%">{{ localize('note', 'Note') }}</th>
                            <th width="10%">{{ localize('action', 'Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($records as $item)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td><code>{{ $item->module_key }}</code></td>
                                <td>{{ $action_labels[$item->action_key] ?? $item->action_key }}</td>
                                <td>{{ $role_labels[$item->org_role] ?? $item->org_role }}</td>
                                <td>
                                    @if ($item->is_active)
                                        <span class="badge bg-success">{{ localize('active', 'Active') }}</span>
                                    @else
                                        <span class="badge bg-danger">{{ localize('inactive', 'Inactive') }}</span>
                                    @endif
                                </td>
                                <td>{{ $item->note ?: '-' }}</td>
                                <td>
                                    @can('update_department')
                                        <button type="button" class="btn btn-primary-soft btn-sm me-1" data-bs-toggle="modal"
                                            data-bs-target="#edit-org-role-permission-modal-{{ $item->id }}">
                                            <i class="fa fa-edit"></i>
                                        </button>
                                    @endcan
                                    @can('delete_department')
                                        <a href="javascript:void(0)" class="btn btn-danger-soft btn-sm delete-confirm"
                                            data-route="{{ route('org-role-module-permissions.destroy', $item->id) }}"
                                            data-csrf="{{ csrf_token() }}">
                                            <i class="fa fa-trash"></i>
                                        </a>
                                    @endcan
                                </td>
                            </tr>

                            @can('update_department')
                                <div class="modal fade" id="edit-org-role-permission-modal-{{ $item->id }}"
                                    data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">{{ localize('edit', 'Edit') }}</h5>
                                            </div>
                                            <form action="{{ route('org-role-module-permissions.update', $item->id) }}"
                                                method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <div class="modal-body">
                                                    <div class="row g-3">
                                                        <div class="col-md-4">
                                                            <label class="form-label">{{ localize('module', 'Module') }} <span
                                                                    class="text-danger">*</span></label>
                                                            <select name="module_key"
                                                                class="form-control select-basic-single role-module-select"
                                                                data-action-target="#edit-action-select-{{ $item->id }}"
                                                                required>
                                                                @foreach ($module_options as $moduleKey)
                                                                    <option value="{{ $moduleKey }}" @selected($item->module_key === $moduleKey)>
                                                                        {{ $moduleKey }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">{{ localize('action', 'Action') }} <span
                                                                    class="text-danger">*</span></label>
                                                            <select name="action_key"
                                                                id="edit-action-select-{{ $item->id }}"
                                                                class="form-control select-basic-single role-action-select"
                                                                data-selected="{{ $item->action_key }}" required></select>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">{{ localize('role', 'Role') }} <span
                                                                    class="text-danger">*</span></label>
                                                            <select name="org_role" class="form-control select-basic-single"
                                                                required>
                                                                @foreach ($org_role_options as $roleKey)
                                                                    <option value="{{ $roleKey }}" @selected($item->org_role === $roleKey)>
                                                                        {{ $role_labels[$roleKey] ?? $roleKey }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label">{{ localize('status', 'Status') }}</label>
                                                            <select name="is_active" class="form-control">
                                                                <option value="1" @selected((int) $item->is_active === 1)>{{ localize('active', 'Active') }}</option>
                                                                <option value="0" @selected((int) $item->is_active === 0)>{{ localize('inactive', 'Inactive') }}</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-9">
                                                            <label class="form-label">{{ localize('note', 'Note') }}</label>
                                                            <input type="text" name="note" class="form-control"
                                                                value="{{ $item->note }}">
                                                        </div>
                                                    </div>
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
                            @endcan
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    @if ($matrix_ready)
                                        {{ localize('no_data_found', 'No data found') }}
                                    @else
                                        {{ localize('permission_matrix_waiting_migrate', 'No data yet because migration is pending.') }}
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @can('create_department')
        @if ($matrix_ready)
            <div class="modal fade" id="create-org-role-permission-modal" data-bs-backdrop="static"
                data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ localize('add', 'Add') }}</h5>
                    </div>
                    <form action="{{ route('org-role-module-permissions.store') }}" method="POST">
                        @csrf
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">{{ localize('module', 'Module') }} <span
                                            class="text-danger">*</span></label>
                                    <select name="module_key"
                                        class="form-control select-basic-single role-module-select"
                                        data-action-target="#create-action-select" required>
                                        <option value="">{{ localize('select_one', 'Select One') }}</option>
                                        @foreach ($module_options as $moduleKey)
                                            <option value="{{ $moduleKey }}">{{ $moduleKey }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">{{ localize('action', 'Action') }} <span
                                            class="text-danger">*</span></label>
                                    <select name="action_key" id="create-action-select"
                                        class="form-control select-basic-single role-action-select" required>
                                        <option value="">{{ localize('select_one', 'Select One') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">{{ localize('role', 'Role') }} <span
                                            class="text-danger">*</span></label>
                                    <select name="org_role" class="form-control select-basic-single" required>
                                        <option value="">{{ localize('select_one', 'Select One') }}</option>
                                        @foreach ($org_role_options as $roleKey)
                                            <option value="{{ $roleKey }}">{{ $role_labels[$roleKey] ?? $roleKey }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">{{ localize('status', 'Status') }}</label>
                                    <select name="is_active" class="form-control">
                                        <option value="1" selected>{{ localize('active', 'Active') }}</option>
                                        <option value="0">{{ localize('inactive', 'Inactive') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-9">
                                    <label class="form-label">{{ localize('note', 'Note') }}</label>
                                    <input type="text" name="note" class="form-control"
                                        placeholder="{{ localize('note', 'Note') }}">
                                </div>
                            </div>
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
        @endif
    @endcan
@endsection

@push('js')
    <script>
        (function($) {
            "use strict";
            var moduleActionMap = @json($module_action_map);
            var actionLabels = @json($action_labels);
            var selectOneText = @json(localize('select_one', 'Select One'));

            function renderActionOptions($moduleSelect) {
                var targetSelector = $moduleSelect.data('action-target');
                if (!targetSelector) return;

                var $actionSelect = $(targetSelector);
                if (!$actionSelect.length) return;

                var moduleKey = String($moduleSelect.val() || '').trim().toLowerCase();
                var actions = moduleActionMap[moduleKey] || [];
                var selected = String($actionSelect.data('selected') || $actionSelect.val() || '');
                var html = '<option value="">' + selectOneText + '</option>';

                actions.forEach(function(actionKey) {
                    var label = actionLabels[actionKey] || actionKey;
                    var isSelected = (selected === actionKey) ? ' selected' : '';
                    html += '<option value="' + actionKey + '"' + isSelected + '>' + label + '</option>';
                });

                $actionSelect.html(html);
                if ($actionSelect.hasClass('select2-hidden-accessible')) {
                    $actionSelect.trigger('change.select2');
                } else {
                    $actionSelect.trigger('change');
                }
            }

            $(document).on('change', '.role-module-select', function() {
                renderActionOptions($(this));
            });

            $('.role-module-select').each(function() {
                renderActionOptions($(this));
            });

            $('#create-org-role-permission-modal').on('shown.bs.modal', function() {
                var $moduleSelect = $(this).find('.role-module-select').first();
                renderActionOptions($moduleSelect);
            });
        })(window.jQuery);
    </script>
@endpush
