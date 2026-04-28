@extends('backend.layouts.app')
@section('title', localize('user_assignments', 'User Assignments'))
@section('content')
    @include('humanresource::master-data.org-structure.header')
    @include('backend.layouts.common.validation')

    @php
        $scopeLabels = is_array($scope_labels ?? null) ? $scope_labels : [];
        $normalizeScope = static function (?string $scope): string {
            $value = trim((string) $scope);
            return $value === 'self' ? 'self_only' : $value;
        };
    @endphp

    <div class="card mb-4 fixed-tab-body">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="fs-17 fw-semi-bold mb-0">{{ localize('user_assignments', 'User Assignments') }}</h6>
                    <small class="text-muted">
                        {{ localize('user_assignments_desc', 'Canonical governance assignment source (user + department + position + responsibility template + scope).') }}
                    </small>
                </div>
                <div class="text-end">
                    <a href="{{ $legacy_index_route }}" class="btn btn-info-soft btn-sm me-1">
                        <i class="fa fa-history"></i>&nbsp;{{ localize('legacy_org_role_screen', 'Legacy Org Role Screen') }}
                    </a>
                    @canany(['create_org_governance', 'create_department'])
                        <a href="#" id="open-create-user-assignment" class="btn btn-success btn-sm" data-bs-toggle="modal"
                            data-bs-target="#create-user-assignment">
                            <i class="fa fa-plus-circle"></i>&nbsp;{{ localize('add', 'Add') }}
                        </a>
                    @endcanany
                </div>
            </div>
        </div>

        <div class="card-body">
            <div class="alert alert-info mb-3">
                <div class="fw-semibold mb-1">{{ localize('governance_assignment_guide', 'Governance assignment guide') }}</div>
                <div>{{ localize('guide_user_assignment_1', '1) Use Responsibility Template as main selector (module-specific preset).') }}</div>
                <div>{{ localize('guide_user_assignment_2', '2) Department + Position can auto-fill from current employee posting.') }}</div>
                <div>{{ localize('guide_user_assignment_3', '3) Legacy org-role table is kept temporarily and auto-synced by service layer.') }}</div>
                <div>{{ localize('guide_user_assignment_4', '4) Module Action Matrix is advanced override only.') }}</div>
            </div>

            <form method="GET" action="{{ route('user-assignments.index') }}" class="mb-3">
                <div class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label mb-1">{{ localize('user', 'User') }}</label>
                        <select name="user_id" class="form-control user-assignment-user-ajax"
                            data-placeholder="{{ localize('select_user', 'Select user') }}">
                            <option value="">{{ localize('all', 'All') }}</option>
                            @if ((int) $selected_user_id > 0 && filled($selected_user_text))
                                <option value="{{ $selected_user_id }}" selected>{{ $selected_user_text }}</option>
                            @endif
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1">{{ localize('status', 'Status') }}</label>
                        <select name="is_active" class="form-control select-basic-single">
                            <option value="">{{ localize('all', 'All') }}</option>
                            <option value="1" @selected((string) $selected_status === '1')>
                                {{ localize('active', 'Active') }}
                            </option>
                            <option value="0" @selected((string) $selected_status === '0')>
                                {{ localize('inactive', 'Inactive') }}
                            </option>
                        </select>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fa fa-search"></i>&nbsp;{{ localize('filter', 'Filter') }}
                        </button>
                        <a href="{{ route('user-assignments.index') }}" class="btn btn-secondary btn-sm">
                            {{ localize('reset', 'Reset') }}
                        </a>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table id="example" class="table display table-bordered table-striped table-hover">
                    <thead>
                        <tr>
                            <th width="4%">{{ localize('sl', 'SL') }}</th>
                            <th width="16%">{{ localize('user', 'User') }}</th>
                            <th width="12%">{{ localize('org_unit', 'Org Unit') }}</th>
                            <th width="10%">{{ localize('position', 'Position') }}</th>
                            <th width="14%">{{ localize('responsibility_template', 'Responsibility Template') }}</th>
                            <th width="12%">{{ localize('responsibility', 'Responsibility') }}</th>
                            <th width="10%">{{ localize('scope', 'Scope') }}</th>
                            <th width="6%">{{ localize('primary', 'Primary') }}</th>
                            <th width="12%">{{ localize('effective_date', 'Effective date') }}</th>
                            <th width="6%">{{ localize('status', 'Status') }}</th>
                            <th width="8%">{{ localize('legacy_sync', 'Legacy sync') }}</th>
                            <th width="8%">{{ localize('action', 'Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($assignments as $item)
                            @php
                                $legacy = $item->legacyOrgRole;
                                $currentScope = $normalizeScope($item->scope_type);
                                $legacyScope = $normalizeScope($legacy?->scope_type);
                                $isLegacyMatched = $legacy
                                    && (int) $legacy->department_id === (int) $item->department_id
                                    && (int) ($legacy->system_role_id ?? 0) === (int) ($item->responsibility_id ?? 0)
                                    && $legacyScope === $currentScope;
                            @endphp
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $item->user?->full_name ?? '-' }}</div>
                                    <small class="text-muted">{{ $item->user?->email ?? '-' }}</small>
                                </td>
                                <td>{{ $item->department?->department_name ?? '-' }}</td>
                                <td>{{ $item->position?->position_name_km ?: ($item->position?->position_name ?? '-') }}</td>
                                <td>
                                    @if ($item->responsibilityTemplate)
                                        <div class="fw-semibold">{{ $item->responsibilityTemplate->name_km ?: $item->responsibilityTemplate->name }}</div>
                                        <small class="text-muted">
                                            <code>{{ $item->responsibilityTemplate->module_key }}::{{ $item->responsibilityTemplate->template_key }}</code>
                                        </small>
                                    @else
                                        <span class="text-muted">{{ localize('legacy_direct_responsibility', 'Legacy direct responsibility') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($item->responsibility)
                                        <div class="fw-semibold">{{ $item->responsibility->name_km ?: $item->responsibility->name }}</div>
                                        <small class="text-muted"><code>{{ $item->responsibility->code }}</code></small>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>{{ $scopeLabels[$currentScope] ?? $currentScope }}</td>
                                <td>
                                    @if ($item->is_primary)
                                        <span class="badge bg-primary">{{ localize('yes', 'Yes') }}</span>
                                    @else
                                        <span class="badge bg-secondary">{{ localize('no', 'No') }}</span>
                                    @endif
                                </td>
                                <td>
                                    {{ optional($item->effective_from)->format('d/m/Y') ?? '-' }}
                                    <br>
                                    <small class="text-muted">
                                        {{ optional($item->effective_to)->format('d/m/Y') ?? localize('open_end', 'Open end') }}
                                    </small>
                                </td>
                                <td>
                                    @if ($item->is_active)
                                        <span class="badge bg-success">{{ localize('active', 'Active') }}</span>
                                    @else
                                        <span class="badge bg-danger">{{ localize('inactive', 'Inactive') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($isLegacyMatched)
                                        <span class="badge bg-success">{{ localize('synced', 'Synced') }}</span>
                                    @elseif ($legacy)
                                        <span class="badge bg-warning text-dark">{{ localize('mismatch', 'Mismatch') }}</span>
                                    @else
                                        <span class="badge bg-secondary">{{ localize('pending', 'Pending') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @canany(['update_org_governance', 'update_department'])
                                        <a href="#" class="btn btn-primary-soft btn-sm me-1" data-bs-toggle="modal"
                                            data-bs-target="#update-user-assignment-{{ $item->id }}"
                                            title="{{ localize('edit', 'Edit') }}"><i class="fa fa-edit"></i></a>
                                    @endcanany
                                    @canany(['delete_org_governance', 'delete_department'])
                                        <a href="javascript:void(0)" class="btn btn-danger-soft btn-sm delete-confirm"
                                            data-bs-toggle="tooltip" title="{{ localize('delete', 'Delete') }}"
                                            data-route="{{ route('user-assignments.destroy', $item->uuid) }}"
                                            data-csrf="{{ csrf_token() }}">
                                            <i class="fa fa-trash"></i>
                                        </a>
                                    @endcanany
                                </td>
                            </tr>

                            @canany(['update_org_governance', 'update_department'])
                                <div class="modal fade" id="update-user-assignment-{{ $item->id }}" data-bs-backdrop="static"
                                    data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">
                                                    {{ localize('edit_user_assignment', 'Edit user assignment') }}
                                                </h5>
                                            </div>
                                            <form action="{{ route('user-assignments.update', $item->uuid) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <div class="modal-body">
                                                    @include('humanresource::master-data.user-assignments.partials.form-fields', [
                                                        'item' => $item,
                                                        'departments' => $departments,
                                                        'positions' => $positions,
                                                        'responsibilities' => $responsibilities,
                                                        'template_groups' => $template_groups,
                                                        'template_module_options' => $template_module_options,
                                                        'scope_options' => $scope_options,
                                                        'scope_labels' => $scopeLabels,
                                                    ])
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-danger"
                                                        data-bs-dismiss="modal">{{ localize('close', 'Close') }}</button>
                                                    <button class="btn btn-primary">{{ localize('save', 'Save') }}</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endcanany
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @canany(['create_org_governance', 'create_department'])
        <div class="modal fade" id="create-user-assignment" data-bs-backdrop="static" data-bs-keyboard="false"
            tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ localize('add_user_assignment', 'Add user assignment') }}</h5>
                    </div>
                    <form action="{{ route('user-assignments.store') }}" method="POST">
                        @csrf
                        <div class="modal-body">
                            @include('humanresource::master-data.user-assignments.partials.form-fields', [
                                'item' => null,
                                'departments' => $departments,
                                'positions' => $positions,
                                'responsibilities' => $responsibilities,
                                'template_groups' => $template_groups,
                                'template_module_options' => $template_module_options,
                                'scope_options' => $scope_options,
                                'scope_labels' => $scopeLabels,
                                'old_user_id' => $old_user_id ?? 0,
                                'old_user_text' => $old_user_text ?? '',
                                'selected_user_id' => $selected_user_id ?? 0,
                                'selected_user_text' => $selected_user_text ?? '',
                            ])
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-danger"
                                data-bs-dismiss="modal">{{ localize('close', 'Close') }}</button>
                            <button class="btn btn-primary">{{ localize('save', 'Save') }}</button>
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
            if (!$ || !$.fn || !$.fn.select2) {
                return;
            }

            var templateGroups = @json($template_groups ?? []);
            var selectTemplateText = @json(localize('select_template', 'Select template'));
            var userPlacementUrlTemplate = @json(route('user-assignments.user-placement', ['user' => '__USER__']));

            function setSelectValue($el, value) {
                if (!$el || !$el.length) return;
                $el.val(value == null ? '' : String(value));
                if ($el.hasClass('select2-hidden-accessible')) {
                    $el.trigger('change.select2');
                } else {
                    $el.trigger('change');
                }
            }

            function findTemplateById(templateId) {
                var normalized = String(templateId || '');
                var found = null;
                Object.keys(templateGroups || {}).forEach(function(moduleKey) {
                    (templateGroups[moduleKey] || []).forEach(function(tpl) {
                        if (String(tpl.id || '') === normalized) {
                            found = tpl;
                        }
                    });
                });
                return found;
            }

            function buildTemplateOptions(moduleKey, selectedId) {
                var html = '<option value="">' + selectTemplateText + '</option>';
                var selected = String(selectedId || '');
                var selectedExists = false;
                var normalizedModule = String(moduleKey || '').trim().toLowerCase();

                Object.keys(templateGroups || {}).forEach(function(groupKey) {
                    var templates = templateGroups[groupKey] || [];
                    if (normalizedModule && String(groupKey).trim().toLowerCase() !== normalizedModule) {
                        return;
                    }
                    if (!templates.length) return;

                    html += '<optgroup label="' + groupKey + '">';
                    templates.forEach(function(tpl) {
                        var id = String(tpl.id || '');
                        var label = String(tpl.label || id);
                        var selectedAttr = (id === selected) ? ' selected' : '';
                        if (selectedAttr) selectedExists = true;
                        html += '<option value="' + id + '"' + selectedAttr + '>' + label + '</option>';
                    });
                    html += '</optgroup>';
                });

                return {
                    html: html,
                    selectedExists: selectedExists
                };
            }

            function syncTemplateSelect($form, keepSelected) {
                var $moduleSelect = $form.find('.ua-template-module').first();
                var $templateSelect = $form.find('.ua-template-select').first();
                if (!$templateSelect.length) return;

                var selectedId = keepSelected ? String($templateSelect.val() || '') : '';
                var payload = buildTemplateOptions($moduleSelect.val(), selectedId);
                $templateSelect.html(payload.html);
                if (!payload.selectedExists) {
                    selectedId = '';
                }
                setSelectValue($templateSelect, selectedId);
            }

            function applyTemplateToForm($form) {
                var $templateSelect = $form.find('.ua-template-select').first();
                var template = findTemplateById($templateSelect.val());
                if (!template) {
                    return;
                }

                var $responsibility = $form.find('.ua-responsibility-select').first();
                var $position = $form.find('.ua-position-select').first();
                var $scope = $form.find('select[name="scope_type"]').first();
                var $module = $form.find('.ua-template-module').first();

                if (template.module_key) {
                    setSelectValue($module, template.module_key);
                }
                if (template.responsibility_id) {
                    setSelectValue($responsibility, template.responsibility_id);
                }
                if (template.position_id) {
                    setSelectValue($position, template.position_id);
                }
                if (template.default_scope_type) {
                    setSelectValue($scope, template.default_scope_type);
                }
            }

            function applyUserPlacement($form, userId) {
                var id = String(userId || '').trim();
                if (!id) return;

                var url = String(userPlacementUrlTemplate || '').replace('__USER__', encodeURIComponent(id));
                if (!url) return;

                $.getJSON(url)
                    .done(function(res) {
                        var $department = $form.find('select[name="department_id"]').first();
                        var $position = $form.find('.ua-position-select').first();

                        if (res && res.department_id) {
                            setSelectValue($department, res.department_id);
                        }
                        if (res && res.position_id) {
                            setSelectValue($position, res.position_id);
                        }
                    });
            }

            $('.user-assignment-user-ajax').each(function() {
                var $el = $(this);
                var inModal = $el.closest('.modal');
                var placeholder = $el.data('placeholder') || 'Select user';

                if ($el.hasClass('select2-hidden-accessible')) {
                    $el.select2('destroy');
                }

                $el.select2({
                    width: '100%',
                    allowClear: true,
                    placeholder: placeholder,
                    dropdownParent: inModal.length ? inModal : $(document.body),
                    minimumInputLength: 0,
                    ajax: {
                        url: '{{ route('user-assignments.user-options') }}',
                        dataType: 'json',
                        delay: 250,
                        data: function(params) {
                            return {
                                q: params.term || '',
                                page: params.page || 1
                            };
                        },
                        processResults: function(data) {
                            return {
                                results: data.results || [],
                                pagination: data.pagination || {
                                    more: false
                                }
                            };
                        },
                        cache: true
                    }
                });
            });

            $('.modal form').each(function() {
                syncTemplateSelect($(this), true);
            });

            $(document).on('change', '.ua-template-module', function() {
                syncTemplateSelect($(this).closest('form'), true);
            });

            $(document).on('change', '.ua-template-select', function() {
                applyTemplateToForm($(this).closest('form'));
            });

            $(document).on('change', '.modal .user-assignment-user-ajax', function() {
                var $form = $(this).closest('form');
                applyUserPlacement($form, $(this).val());
            });

            const createModal = document.getElementById('create-user-assignment');
            if (createModal) {
                createModal.addEventListener('show.bs.modal', function() {
                    const filterUser = document.querySelector(
                        'form[action="{{ route('user-assignments.index') }}"] select[name="user_id"]');
                    const modalUser = createModal.querySelector('select[name="user_id"]');
                    if (!filterUser || !modalUser) return;

                    if (!modalUser.value && filterUser.value) {
                        const text = filterUser.options[filterUser.selectedIndex]
                            ? filterUser.options[filterUser.selectedIndex].text
                            : filterUser.value;
                        if (!modalUser.querySelector('option[value="' + filterUser.value + '"]')) {
                            const opt = new Option(text, filterUser.value, true, true);
                            modalUser.add(opt);
                        }
                        modalUser.value = filterUser.value;
                        $(modalUser).trigger('change');
                    }

                    const form = createModal.querySelector('form');
                    if (form) {
                        syncTemplateSelect($(form), true);
                    }
                });
            }
        })(window.jQuery);
    </script>
@endpush
