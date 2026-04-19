@extends('backend.layouts.app')
@section('title', localize('org_unit_management'))
@push('css')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        .org-map-canvas {
            width: 100%;
            height: 260px;
            border: 1px solid #d8dee5;
            border-radius: 6px;
        }

        .org-tree-panel {
            border: 1px solid #d8dee5;
            border-radius: 8px;
            background: #fff;
            max-height: 630px;
            overflow: auto;
            padding: 12px;
        }

        .org-hierarchy-tree,
        .org-hierarchy-tree ul {
            list-style: none;
            margin: 0;
            padding-left: 16px;
        }

        .org-hierarchy-tree li {
            position: relative;
            margin: 2px 0;
            padding-left: 14px;
        }

        .org-hierarchy-tree li::before {
            content: '';
            position: absolute;
            top: -6px;
            left: 0;
            width: 12px;
            height: 16px;
            border-left: 1px dotted #9aa8b6;
            border-bottom: 1px dotted #9aa8b6;
        }

        .org-hierarchy-tree li::after {
            content: '';
            position: absolute;
            left: 0;
            top: 10px;
            bottom: -8px;
            border-left: 1px dotted #9aa8b6;
        }

        .org-hierarchy-tree li:last-child::after {
            display: none;
        }

        .org-hierarchy-tree>li {
            padding-left: 6px;
        }

        .org-hierarchy-tree>li::before,
        .org-hierarchy-tree>li::after {
            display: none;
        }

        .org-tree-node {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 1px 4px;
            border-radius: 3px;
            font-size: 13px;
            color: #1f2f40;
            text-decoration: none;
        }

        .org-tree-row {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .org-tree-toggle {
            width: 16px;
            height: 16px;
            border: 1px solid #7f8da0;
            background: #fff;
            color: #1f2f40;
            padding: 0;
            line-height: 14px;
            text-align: center;
            border-radius: 2px;
            font-size: 12px;
            cursor: pointer;
        }

        .org-tree-toggle:hover {
            background: #ecf3f9;
            border-color: #47627f;
        }

        .org-tree-toggle-placeholder {
            width: 16px;
            height: 16px;
            display: inline-block;
        }

        .org-node-actions {
            display: none;
            align-items: center;
            gap: 4px;
            margin-left: 4px;
        }

        .org-tree-item:hover > .org-tree-row .org-node-actions,
        .org-tree-row .org-tree-node.is-active ~ .org-node-actions {
            display: inline-flex;
        }

        .org-node-action {
            width: 20px;
            height: 20px;
            border: 1px solid #c5d0dc;
            border-radius: 3px;
            background: #fff;
            color: #3a4a5c;
            font-size: 11px;
            line-height: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .org-node-action:hover {
            background: #edf3f9;
            border-color: #7c93ad;
        }

        .org-tree-item > .org-hierarchy-tree {
            display: none;
        }

        .org-tree-item.is-open > .org-hierarchy-tree {
            display: block;
        }

        .org-tree-node:hover {
            background: #eef4f9;
            color: #0f5e95;
        }

        .org-tree-node.is-active {
            background: #1f75b8;
            color: #fff;
        }

        .org-tree-icon {
            color: #8a7a12;
            font-size: 12px;
            width: 14px;
            text-align: center;
        }

        .org-tree-order {
            min-width: 22px;
            padding: 0 4px;
            border: 1px solid #c3d0dc;
            border-radius: 10px;
            text-align: center;
            font-size: 11px;
            color: #45607a;
            background: #f1f6fb;
            line-height: 16px;
        }

        .org-tree-node.is-active .org-tree-icon {
            color: #ffe89d;
        }

        .org-tree-node.is-active .org-tree-order {
            border-color: #d6ebff;
            color: #e9f6ff;
            background: #2d86ca;
        }

        .org-tree-name {
            font-weight: 600;
            line-height: 1.3;
        }

        .org-tree-type {
            color: #6b7785;
            font-size: 11px;
        }

        .org-tree-node.is-active .org-tree-type {
            color: #d6ebff;
        }

        .org-tree-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 10px;
        }

        .org-tree-title {
            font-size: 14px;
            font-weight: 700;
            margin: 0;
        }

        .org-tree-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 10px;
        }

        .org-context-menu {
            position: fixed;
            min-width: 190px;
            background: #fff;
            border: 1px solid #d5dde6;
            border-radius: 6px;
            box-shadow: 0 8px 20px rgba(24, 45, 71, 0.18);
            z-index: 1065;
            padding: 6px 0;
            display: none;
        }

        .org-context-menu.is-open {
            display: block;
        }

        .org-context-item {
            width: 100%;
            border: 0;
            background: transparent;
            text-align: left;
            padding: 7px 14px;
            font-size: 13px;
            color: #1f2f40;
            cursor: pointer;
        }

        .org-context-item:hover {
            background: #eef4fa;
            color: #0f5e95;
        }

        .org-context-item:disabled {
            color: #9aa6b2;
            background: transparent;
            cursor: not-allowed;
        }

        .org-context-divider {
            margin: 5px 0;
            border-top: 1px solid #e8edf3;
        }

        .org-selected-card {
            border: 1px solid #d8dee5;
            border-radius: 8px;
            padding: 10px 12px;
            background: #f8fafc;
            margin-bottom: 10px;
        }

        .org-selected-title {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .org-selected-empty {
            font-size: 12px;
            color: #5a6978;
            margin-bottom: 10px;
        }

        .org-info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 6px 12px;
            font-size: 12px;
        }

        .org-info-item strong {
            color: #32475b;
            margin-right: 4px;
        }

        @media (max-width: 767px) {
            .org-info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush
@section('content')
    @include('backend.layouts.common.validation')
    @include('humanresource::master-data.header')
    <div class="card mb-4">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="fs-17 fw-semi-bold mb-0">{{ localize('org_unit_management') }}</h6>
                </div>
                <div class="text-end">
                    <div class="actions">
                        @can('read_department')
                            <a href="{{ route('departments.import-template') }}" class="btn btn-info btn-sm me-1">
                                <i class="fa fa-download"></i>&nbsp;{{ localize('import_template') }}
                            </a>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="org-tree-panel">
                <div class="org-tree-actions">
                    <p class="org-tree-title mb-0">{{ localize('org_structure_tree') }}</p>
                    @if (($selected_org_unit_id ?? 0) > 0)
                        <a href="{{ route('departments.index') }}" class="btn btn-sm btn-outline-secondary">
                            {{ localize('show_all') }}
                        </a>
                    @endif
                </div>

                <div class="org-tree-toolbar">
                    @can('create_department')
                        <button type="button" class="btn btn-success btn-sm js-open-create-root"
                            data-org-open-create="1" data-parent-id="" data-bs-toggle="modal"
                            data-bs-target="#create-department">
                            <i class="fa fa-plus-circle"></i> {{ localize('add_root_unit') }}
                        </button>
                    @endcan
                </div>

                @if (!empty($selected_unit))
                    <div class="org-selected-card">
                        <div class="org-selected-title">{{ $selected_unit->department_name }}</div>
                        <div class="org-info-grid">
                            <div class="org-info-item"><strong>{{ localize('sort_order') }}:</strong> {{ is_null($selected_unit->sort_order) ? '-' : (int) $selected_unit->sort_order }}</div>
                            <div class="org-info-item"><strong>{{ localize('type') }}:</strong> {{ $selected_unit->unitType?->display_name ?? '-' }}</div>
                            <div class="org-info-item"><strong>{{ localize('standard_staffing_level') }}:</strong> {{ $selected_unit->sslType?->display_name ?? '-' }}</div>
                            <div class="org-info-item"><strong>{{ localize('parent_unit') }}:</strong> {{ $selected_unit->parentDept?->department_name ?? '-' }}</div>
                            <div class="org-info-item"><strong>{{ localize('path') }}:</strong> {{ $selected_unit_path ?? $selected_unit->department_name }}</div>
                            <div class="org-info-item"><strong>{{ localize('location_code') }}:</strong> {{ $selected_unit->location_code ?? '-' }}</div>
                            <div class="org-info-item"><strong>{{ localize('latitude') }}:</strong> {{ $selected_unit->latitude ?? '-' }}</div>
                            <div class="org-info-item"><strong>{{ localize('longitude') }}:</strong> {{ $selected_unit->longitude ?? '-' }}</div>
                            <div class="org-info-item"><strong>{{ localize('children') }}:</strong> {{ (int) ($selected_unit_child_count ?? 0) }}</div>
                            <div class="org-info-item"><strong>{{ localize('employees') }}:</strong> {{ (int) ($selected_unit_employee_count ?? 0) }}</div>
                        </div>
                    </div>
                @else
                    <div class="org-selected-empty">
                        {{ localize('select_org_unit_from_tree') }}
                    </div>
                @endif

                @if (!empty($hierarchy_tree))
                    @include('humanresource::department.partials.hierarchy-tree', [
                        'nodes' => $hierarchy_tree,
                        'selected_org_unit_id' => $selected_org_unit_id ?? 0,
                    ])
                @else
                    <p class="text-muted mb-0">{{ localize('no_org_unit_data') }}</p>
                @endif
            </div>
        </div>
    </div>
    <div class="org-context-menu" id="orgTreeContextMenu" aria-hidden="true">
        @can('create_department')
            <button type="button" class="org-context-item" data-action="add-sub"
                onclick="return window.orgUnitContextAddSub ? window.orgUnitContextAddSub(this) : false;">
                {{ localize('add_sub_unit') }}
            </button>
        @endcan
        @can('update_department')
            <button type="button" class="org-context-item" data-action="edit">{{ localize('edit_unit') }}</button>
        @endcan
        @can('delete_department')
            <button type="button" class="org-context-item text-danger"
                data-action="delete">{{ localize('delete_unit') }}</button>
        @endcan
        <div class="org-context-divider"></div>
        <button type="button" class="org-context-item" data-action="refresh">{{ localize('refresh') }}</button>
    </div>
    @can('create_department')
        @include('humanresource::department.modal.create')
    @endcan
    <!-- Edit Modal -->
    <div class="modal fade" id="edit-department" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
        aria-labelledby="staticBackdropLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <!-- Modal Body -->
            </div>
        </div>
    </div>
@endsection
@push('js')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ module_asset('HumanResource/js/org-location-picker.js') }}"></script>
    <script>
        window.orgUnitI18n = Object.assign({}, window.orgUnitI18n || {}, {
            chooseUnitTypeFirst: @json(localize('choose_unit_type_first')),
            topLevelNoParent: @json(localize('top_level_unit_no_parent')),
            selectParentOrLeaveBlank: @json(localize('select_parent_or_leave_blank')),
            addSubUnit: @json(localize('add_sub_unit')),
            addRootUnit: @json(localize('add_root_unit')),
            deleteConfirm: @json(localize('delete_org_unit_confirm')),
            deleteFailed: @json(localize('delete_failed')),
        });
    </script>
    <script src="{{ module_asset('HumanResource/js/department.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var treePanel = document.querySelector('.org-tree-panel');
            if (!treePanel) {
                return;
            }
            var contextMenu = document.getElementById('orgTreeContextMenu');
            var contextTargetItem = null;
            var i18n = window.orgUnitI18n || {};

            function t(key, fallback) {
                return i18n[key] || fallback;
            }

            function setExpanded(item, expanded) {
                item.classList.toggle('is-open', expanded);
                var toggle = item.querySelector('.org-tree-row .org-tree-toggle');
                if (!toggle) {
                    return;
                }
                toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                var symbol = toggle.querySelector('.toggle-symbol');
                if (symbol) {
                    symbol.textContent = expanded ? '-' : '+';
                }
            }

            var branchItems = treePanel.querySelectorAll('.org-tree-item.has-children');

            branchItems.forEach(function(item) {
                var toggle = item.querySelector('.org-tree-row .org-tree-toggle');
                if (!toggle) {
                    return;
                }

                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var isOpen = item.classList.contains('is-open');
                    setExpanded(item, !isOpen);
                });

                setExpanded(item, false);
            });

            var rootList = treePanel.querySelector('.org-hierarchy-tree');
            if (rootList) {
                Array.prototype.forEach.call(rootList.children, function(child) {
                    if (child.classList && child.classList.contains('org-tree-item') && child.classList.contains('has-children')) {
                        setExpanded(child, true);
                    }
                });
            }

            var activeNode = treePanel.querySelector('.org-tree-node.is-active');
            if (activeNode) {
                var currentItem = activeNode.closest('.org-tree-item');
                while (currentItem) {
                    if (currentItem.classList.contains('has-children')) {
                        setExpanded(currentItem, true);
                    }
                    var parentList = currentItem.parentElement;
                    currentItem = parentList ? parentList.closest('.org-tree-item') : null;
                }
            }

            function openCreateModal(parentId) {
                var modalEl = document.getElementById('create-department');
                if (!modalEl) {
                    return;
                }

                var form = modalEl.querySelector('form.js-org-unit-form');
                if (!form) {
                    return;
                }

                form.reset();
                form.setAttribute('data-forced-parent-id', parentId ? String(parentId) : '');

                var unitTypeSelect = form.querySelector('.js-unit-type-select');
                if (unitTypeSelect) {
                    unitTypeSelect.value = '';
                    unitTypeSelect.dispatchEvent(new Event('change'));
                }

                var parentSelect = form.querySelector('.js-parent-unit-select');
                if (parentSelect) {
                    parentSelect.disabled = false;
                    parentSelect.value = parentId ? String(parentId) : '';
                    if (window.jQuery && window.jQuery(parentSelect).data('select2')) {
                        window.jQuery(parentSelect).trigger('change');
                    }
                }

                var modalTitle = modalEl.querySelector('.modal-title');
                if (modalTitle) {
                    modalTitle.textContent = parentId ? t('addSubUnit', 'Add Sub Unit') : t('addRootUnit', 'Add Root Unit');
                }

                if (window.bootstrap && window.bootstrap.Modal) {
                    var createModal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
                    createModal.show();
                } else if (window.jQuery) {
                    window.jQuery(modalEl).modal('show');
                } else {
                    // Last-resort fallback if modal library is not available.
                    modalEl.style.display = 'block';
                    modalEl.classList.add('show');
                    modalEl.removeAttribute('aria-hidden');
                }
            }
            window.orgUnitOpenCreateModal = openCreateModal;

            function openEditModal(editUrl) {
                if (!editUrl || !window.jQuery) {
                    return;
                }

                window.jQuery.ajax({
                    type: 'GET',
                    dataType: 'html',
                    url: editUrl,
                    success: function(html) {
                        var modal = window.jQuery('#edit-department');
                        modal.find('.modal-content').html(html);
                        modal.modal('show');
                        // Init map pickers after modal animation completes
                        setTimeout(function () {
                            if (window.initOrgMapPickers) {
                                window.initOrgMapPickers(document.getElementById('edit-department'));
                            }
                        }, 350);
                    }
                });
            }
            window.orgUnitOpenEditModal = openEditModal;

            function deleteUnit(deleteUrl, csrf) {
                if (!deleteUrl || !window.jQuery) {
                    return;
                }

                if (!window.confirm(t('deleteConfirm', 'Delete this org unit?'))) {
                    return;
                }

                window.jQuery.ajax({
                    type: 'POST',
                    url: deleteUrl,
                    data: {
                        _method: 'DELETE',
                        _token: csrf
                    },
                    success: function(response) {
                        if (response && response.success) {
                            window.location.href = '{{ route('departments.index') }}';
                            return;
                        }

                        window.alert((response && response.message) ? response.message : t('deleteFailed', 'Delete failed.'));
                    },
                    error: function(xhr) {
                        var message = t('deleteFailed', 'Delete failed.');
                        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        }

                        window.alert(message);
                    }
                });
            }
            window.orgUnitDeleteUnit = deleteUnit;

            function hideContextMenu() {
                if (!contextMenu) {
                    return;
                }

                contextMenu.classList.remove('is-open');
                contextMenu.setAttribute('aria-hidden', 'true');
                contextMenu.style.left = '-9999px';
                contextMenu.style.top = '-9999px';
                contextTargetItem = null;
            }

            window.orgUnitContextAddSub = function(button) {
                var parentId = Number((button && button.getAttribute('data-parent-id')) || 0);

                if (!(parentId > 0) && contextMenu) {
                    parentId = Number(contextMenu.getAttribute('data-parent-id') || 0);
                }

                if (!(parentId > 0) && contextTargetItem) {
                    parentId = Number(contextTargetItem.getAttribute('data-org-unit-id') || 0);
                }

                if (!(parentId > 0)) {
                    return false;
                }

                openCreateModal(parentId);
                hideContextMenu();
                return false;
            };

            function setContextActionState(action, enabled, payload) {
                if (!contextMenu) {
                    return;
                }

                var item = contextMenu.querySelector('[data-action="' + action + '"]');
                if (!item) {
                    return;
                }

                item.disabled = !enabled;
                item.dataset.payload = payload || '';
            }

            function showContextMenu(item, x, y) {
                if (!contextMenu || !item) {
                    return;
                }

                contextTargetItem = item;
                var unitId = Number(item.getAttribute('data-org-unit-id') || 0);
                contextMenu.setAttribute('data-parent-id', unitId > 0 ? String(unitId) : '');
                var addBtn = item.querySelector('.js-node-add-child');
                var editBtn = item.querySelector('.js-node-edit');
                var deleteBtn = item.querySelector('.js-node-delete');
                var editUrl = (editBtn ? editBtn.getAttribute('data-edit-url') : '') || item.getAttribute('data-org-edit-url') || '';
                var deleteUrl = (deleteBtn ? deleteBtn.getAttribute('data-delete-url') : '') || item.getAttribute('data-org-delete-url') || '';
                var csrfToken = (deleteBtn ? deleteBtn.getAttribute('data-csrf') : '') || item.getAttribute('data-org-csrf') || '';
                var addActionBtn = contextMenu.querySelector('[data-action="add-sub"]');
                if (addActionBtn) {
                    addActionBtn.setAttribute('data-parent-id', unitId > 0 ? String(unitId) : '');
                }

                setContextActionState('add-sub', unitId > 0, unitId > 0 ? String(unitId) : '');
                setContextActionState('edit', !!editUrl, editUrl);
                setContextActionState(
                    'delete',
                    !!deleteUrl && !!csrfToken,
                    (deleteUrl && csrfToken)
                        ? JSON.stringify({
                            url: deleteUrl,
                            csrf: csrfToken
                        })
                        : ''
                );

                contextMenu.style.left = x + 'px';
                contextMenu.style.top = y + 'px';
                contextMenu.classList.add('is-open');
                contextMenu.setAttribute('aria-hidden', 'false');

                var menuRect = contextMenu.getBoundingClientRect();
                var maxLeft = window.innerWidth - menuRect.width - 8;
                var maxTop = window.innerHeight - menuRect.height - 8;

                if (menuRect.left > maxLeft) {
                    contextMenu.style.left = Math.max(8, maxLeft) + 'px';
                }
                if (menuRect.top > maxTop) {
                    contextMenu.style.top = Math.max(8, maxTop) + 'px';
                }
            }

            document.addEventListener('click', function(e) {
                var openBtn = e.target.closest('[data-org-open-create]');
                if (!openBtn) {
                    return;
                }

                e.preventDefault();
                e.stopPropagation();

                var rawParentId = openBtn.getAttribute('data-parent-id');
                var parentId = rawParentId === null || rawParentId === '' ? null : Number(rawParentId || 0);
                if ((parentId === null || parentId <= 0) && contextMenu && contextMenu.contains(openBtn)) {
                    var fromContextMenu = Number(contextMenu.getAttribute('data-parent-id') || 0);
                    parentId = fromContextMenu > 0 ? fromContextMenu : null;
                }
                if (parentId !== null && (!Number.isInteger(parentId) || parentId <= 0)) {
                    parentId = null;
                }

                openCreateModal(parentId);
                if (contextMenu && contextMenu.classList.contains('is-open')) {
                    hideContextMenu();
                }
            });

            treePanel.addEventListener('click', function(e) {
                if (contextMenu && contextMenu.classList.contains('is-open')) {
                    hideContextMenu();
                }

                var addBtn = e.target.closest('.js-node-add-child');
                if (addBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    var parentId = Number(addBtn.getAttribute('data-parent-id') || 0);
                    if (parentId > 0) {
                        openCreateModal(parentId);
                    }
                    return;
                }

                var editBtn = e.target.closest('.js-node-edit');
                if (editBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    openEditModal(editBtn.getAttribute('data-edit-url'));
                    return;
                }

                var deleteBtn = e.target.closest('.js-node-delete');
                if (deleteBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    deleteUnit(deleteBtn.getAttribute('data-delete-url'), deleteBtn.getAttribute('data-csrf'));
                }
            });

            treePanel.addEventListener('contextmenu', function(e) {
                var item = e.target.closest('.org-tree-item');
                if (!item || !treePanel.contains(item)) {
                    return;
                }

                e.preventDefault();
                e.stopPropagation();
                showContextMenu(item, e.clientX, e.clientY);
            });

            if (contextMenu) {
                contextMenu.addEventListener('click', function(e) {
                    var actionBtn = e.target.closest('.org-context-item[data-action]');
                    if (!actionBtn || actionBtn.disabled) {
                        return;
                    }

                    var action = actionBtn.getAttribute('data-action');
                    if (action === 'add-sub') {
                        // Use the same flow as the "+" button because it is already working reliably.
                        var targetAddBtn = contextTargetItem ? contextTargetItem.querySelector('.js-node-add-child') : null;
                        if (targetAddBtn) {
                            targetAddBtn.dispatchEvent(new MouseEvent('click', {
                                bubbles: true,
                                cancelable: true,
                                view: window
                            }));
                            hideContextMenu();
                            return;
                        }

                        var parentId = Number(actionBtn.getAttribute('data-parent-id') || actionBtn.dataset.payload || 0);
                        if (!(parentId > 0) && contextTargetItem) {
                            parentId = Number(contextTargetItem.getAttribute('data-org-unit-id') || 0);
                        }
                        if (!(parentId > 0) && contextMenu) {
                            parentId = Number(contextMenu.getAttribute('data-parent-id') || 0);
                        }
                        if (parentId > 0) {
                            openCreateModal(parentId);
                        }
                    } else if (action === 'edit') {
                        var editUrl = actionBtn.dataset.payload || '';
                        if (editUrl) {
                            openEditModal(editUrl);
                        }
                    } else if (action === 'delete') {
                        try {
                            var parsed = JSON.parse(actionBtn.dataset.payload || '{}');
                            if (parsed.url && parsed.csrf) {
                                deleteUnit(parsed.url, parsed.csrf);
                            }
                        } catch (err) {
                            // ignore invalid payload
                        }
                    } else if (action === 'refresh') {
                        window.location.reload();
                    }

                    hideContextMenu();
                });
            }

            document.addEventListener('click', function(e) {
                if (!contextMenu || !contextMenu.classList.contains('is-open')) {
                    return;
                }

                if (e.target.closest('#orgTreeContextMenu')) {
                    return;
                }

                hideContextMenu();
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    hideContextMenu();
                }
            });

            @php
                $orgCreateValidationError = $errors->hasAny([
                    'department_name',
                    'unit_type_id',
                    'parent_id',
                    'sort_order',
                    'location_code',
                    'latitude',
                    'longitude',
                    'is_active',
                ]);
            @endphp
            var hasCreateValidationError = @json($orgCreateValidationError);
            if (hasCreateValidationError) {
                var createModalEl = document.getElementById('create-department');
                if (createModalEl) {
                    if (window.bootstrap && window.bootstrap.Modal) {
                        window.bootstrap.Modal.getOrCreateInstance(createModalEl).show();
                    } else if (window.jQuery) {
                        window.jQuery(createModalEl).modal('show');
                    } else {
                        createModalEl.style.display = 'block';
                        createModalEl.classList.add('show');
                        createModalEl.removeAttribute('aria-hidden');
                    }
                }
            }
        });
    </script>
@endpush

