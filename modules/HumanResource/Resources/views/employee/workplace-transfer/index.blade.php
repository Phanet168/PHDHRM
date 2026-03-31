@extends('backend.layouts.app')
@section('title', 'គ្រប់គ្រងផ្លាស់ប្តូរកន្លែងការងារ')
@push('css')
    <style>
        .employee-org-combo {
            position: relative;
        }

        .employee-org-combo-toggle {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            min-height: 38px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            background: #fff;
            padding: 6px 10px;
            text-align: left;
        }

        .employee-org-combo-toggle .combo-label {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .employee-org-combo-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            z-index: 1200;
            border: 1px solid #d8dee5;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
        }

        .employee-org-combo.is-open .employee-org-combo-dropdown {
            display: block;
        }

        .employee-org-combo-tools {
            display: flex;
            gap: 8px;
            padding: 8px;
            border-bottom: 1px solid #e7edf3;
        }

        .employee-org-tree-filter-body {
            max-height: 300px;
            overflow: auto;
            padding: 8px 10px;
        }

        .employee-org-tree,
        .employee-org-tree ul {
            list-style: none;
            margin: 0;
            padding-left: 16px;
        }

        .employee-org-tree-item {
            position: relative;
            margin: 2px 0;
            padding-left: 14px;
        }

        .employee-org-tree-item::before {
            content: '';
            position: absolute;
            top: -6px;
            left: 0;
            width: 12px;
            height: 16px;
            border-left: 1px dotted #9aa8b6;
            border-bottom: 1px dotted #9aa8b6;
        }

        .employee-org-tree-item::after {
            content: '';
            position: absolute;
            left: 0;
            top: 10px;
            bottom: -8px;
            border-left: 1px dotted #9aa8b6;
        }

        .employee-org-tree-item:last-child::after {
            display: none;
        }

        .employee-org-tree > .employee-org-tree-item {
            padding-left: 6px;
        }

        .employee-org-tree > .employee-org-tree-item::before,
        .employee-org-tree > .employee-org-tree-item::after {
            display: none;
        }

        .employee-org-tree-item > .employee-org-tree {
            display: none;
        }

        .employee-org-tree-item.is-open > .employee-org-tree {
            display: block;
        }

        .employee-org-tree-row {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .employee-org-tree-toggle {
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

        .employee-org-tree-toggle-placeholder {
            width: 16px;
            height: 16px;
            display: inline-block;
        }

        .employee-org-tree-node-filter {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 1px 4px;
            border-radius: 3px;
            font-size: 12px;
            color: #1f2f40;
            text-decoration: none;
        }

        .employee-org-tree-node-filter:hover {
            background: #eef4f9;
            color: #0f5e95;
        }

        .employee-org-tree-node-filter.is-active {
            background: #1f75b8;
            color: #fff;
        }

        .employee-org-tree-order {
            min-width: 20px;
            padding: 0 4px;
            border: 1px solid #c3d0dc;
            border-radius: 10px;
            text-align: center;
            font-size: 10px;
            color: #45607a;
            background: #f1f6fb;
            line-height: 15px;
        }

        .employee-org-tree-icon {
            color: #8a7a12;
            font-size: 11px;
            width: 13px;
            text-align: center;
        }

        .employee-org-tree-name {
            font-weight: 600;
            line-height: 1.25;
        }

        .employee-org-tree-type {
            color: #6b7785;
            font-size: 10px;
        }
    </style>
@endpush

@section('content')
    @include('humanresource::employee_header')
    @include('backend.layouts.common.validation')

    <div class="card mb-3 fixed-tab-body">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="fs-17 fw-semi-bold mb-0">គ្រប់គ្រងផ្លាស់ប្តូរកន្លែងការងារ</h6>
                <form method="GET" action="{{ route('employee-workplace-transfers.index') }}" class="d-flex gap-2">
                    <input type="number" class="form-control form-control-sm" name="year" value="{{ $year }}"
                        min="1950" max="2100" style="width: 120px;">
                    <button type="submit" class="btn btn-sm btn-primary">បង្ហាញឆ្នាំ</button>
                </form>
            </div>
        </div>

        <div class="card-body">
            <div class="alert alert-info mb-3">
                រាល់ពេលផ្លាស់ប្តូរកន្លែងការងារ ប្រព័ន្ធនឹងកត់ត្រាទៅក្នុង <strong>ប្រវត្តការងារ</strong> និង
                <strong>ប្រវត្តសេវាកម្ម</strong> ដោយស្វ័យប្រវត្តិ។
            </div>

            <form action="{{ route('employee-workplace-transfers.store') }}" method="POST" class="mb-4">
                @csrf

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">ជ្រើសមន្ត្រី <span class="text-danger">*</span></label>
                        <select id="employee_id" name="employee_id" class="form-select" required>
                            <option value="">-- ជ្រើសមន្ត្រី --</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}"
                                    data-current-unit="{{ $current_unit_labels[$employee->id] ?? '-' }}"
                                    {{ (int) old('employee_id') === (int) $employee->id ? 'selected' : '' }}>
                                    {{ $employee->employee_id }} - {{ $employee->full_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">អង្គភាពបច្ចុប្បន្ន</label>
                        <input type="text" id="current_unit_label" class="form-control bg-light" readonly value="-">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">អង្គភាពថ្មី (គោលដៅ) <span class="text-danger">*</span></label>
                        <div id="target-department-tree-combo" class="employee-org-combo"
                            data-all-label="{{ localize('select_department') }}">
                            <button type="button" id="target-department-tree-combo-toggle" class="employee-org-combo-toggle">
                                <span id="target-department-tree-combo-label"
                                    class="combo-label">{{ localize('select_department') }}</span>
                                <i class="fa fa-chevron-down"></i>
                            </button>
                            <div class="employee-org-combo-dropdown">
                                <div class="employee-org-combo-tools">
                                    <input type="text" id="target-department-tree-search" class="form-control form-control-sm"
                                        placeholder="{{ localize('search_department') }}">
                                    <button type="button" id="target-department-tree-clear"
                                        class="btn btn-outline-secondary btn-sm">
                                        {{ localize('reset') }}
                                    </button>
                                </div>
                                <div id="target-department-tree-panel" class="employee-org-tree-filter-body">
                                    @include('humanresource::employee.partials.filter-org-tree', ['nodes' => $org_unit_tree ?? []])
                                </div>
                            </div>
                        </div>

                        <select id="department_id" name="department_id" class="d-none" required>
                            <option value="">{{ localize('select_department') }}</option>
                            @foreach (($org_unit_options ?? collect()) as $unit)
                                <option value="{{ $unit->id }}"
                                    {{ (int) old('department_id') === (int) $unit->id ? 'selected' : '' }}>
                                    {{ $unit->path ?? $unit->label ?? ('#' . $unit->id) }}
                                </option>
                            @endforeach
                        </select>
                        @if ($errors->has('department_id'))
                            <div class="text-danger small mt-1">{{ $errors->first('department_id') }}</div>
                        @endif
                    </div>
                </div>

                <div class="row g-3 mt-0">
                    <div class="col-md-3">
                        <label class="form-label">ថ្ងៃមានប្រសិទ្ធិភាព <span class="text-danger">*</span></label>
                        <input type="date" name="effective_date" class="form-control"
                            value="{{ old('effective_date', now()->toDateString()) }}" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">លេខលិខិត</label>
                        <input type="text" name="document_reference" class="form-control"
                            value="{{ old('document_reference') }}" placeholder="ឧ. 123/26">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">ថ្ងៃខែលិខិត</label>
                        <input type="date" name="document_date" class="form-control" value="{{ old('document_date') }}">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">កំណត់សម្គាល់</label>
                        <input type="text" name="note" class="form-control" value="{{ old('note') }}"
                            placeholder="ព័ត៌មានបន្ថែម (ប្រសិនបើមាន)">
                    </div>
                </div>

                <div class="text-end mt-3">
                    <button type="submit" class="btn btn-success">
                        <i class="fa fa-save me-1"></i> រក្សាទុក
                    </button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead>
                        <tr>
                            <th width="5%">ល.រ</th>
                            <th>មន្ត្រី</th>
                            <th>អង្គភាពចាស់</th>
                            <th>អង្គភាពថ្មី</th>
                            <th>ថ្ងៃមានប្រសិទ្ធិភាព</th>
                            <th>លេខលិខិត</th>
                            <th>ថ្ងៃខែលិខិត</th>
                            <th>សម្គាល់</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transfers as $transfer)
                            @php
                                $newUnit = $transfer->department?->department_name ?: '-';
                                $oldUnit = $previous_unit_labels[$transfer->id] ?? '-';
                                $displayNote = trim(str_replace('[WORKPLACE_TRANSFER] |', '', (string) $transfer->note));
                            @endphp
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $transfer->employee?->employee_id }} - {{ $transfer->employee?->full_name ?? '-' }}</td>
                                <td>{{ $oldUnit }}</td>
                                <td>{{ $newUnit }}</td>
                                <td>{{ display_date($transfer->start_date) }}</td>
                                <td>{{ $transfer_documents[$transfer->id]['document_reference'] ?? '-' }}</td>
                                <td>{{ display_date($transfer_documents[$transfer->id]['document_date'] ?? null) }}</td>
                                <td>{{ $displayNote !== '' ? $displayNote : '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted">មិនទាន់មានទិន្នន័យផ្លាស់ប្តូរកន្លែងការងារក្នុងឆ្នាំនេះទេ</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('js')
    <script>
        (function() {
            "use strict";

            var employeeSelect = document.getElementById('employee_id');
            var currentUnitInput = document.getElementById('current_unit_label');

            function refreshCurrentUnit() {
                if (!employeeSelect || !currentUnitInput) {
                    return;
                }

                var option = employeeSelect.options[employeeSelect.selectedIndex];
                if (!option || !option.value) {
                    currentUnitInput.value = '-';
                    return;
                }

                currentUnitInput.value = option.getAttribute('data-current-unit') || '-';
            }

            if (employeeSelect) {
                employeeSelect.addEventListener('change', refreshCurrentUnit);
            }
            refreshCurrentUnit();
        })();

        (function($) {
            "use strict";
            if (!$ || !$.fn || !$.fn.select2) {
                return;
            }

            $('#employee_id').select2({
                width: '100%',
                allowClear: true,
                placeholder: '-- ស្វែងរកមន្ត្រី --'
            });

            $('#employee_id').on('select2:select select2:clear', function() {
                this.dispatchEvent(new Event('change'));
            });
        })(window.jQuery);

        (function($) {
            "use strict";

            var $department = $('#department_id');
            var $combo = $('#target-department-tree-combo');
            var $comboToggle = $('#target-department-tree-combo-toggle');
            var $comboLabel = $('#target-department-tree-combo-label');
            var $comboPanel = $('#target-department-tree-panel');
            var $comboSearch = $('#target-department-tree-search');
            var $comboClear = $('#target-department-tree-clear');

            if (!$department.length || !$combo.length || !$comboPanel.length) {
                return;
            }

            var allDepartmentLabel = (($combo.data('allLabel') || $department.find('option:first').text() || '') + '')
                .trim();

            function setTreeOpenState($item, isOpen) {
                if (!$item || !$item.length || !$item.hasClass('has-children')) {
                    return;
                }

                $item.toggleClass('is-open', isOpen);
                var $toggle = $item.children('.employee-org-tree-row').find('.employee-org-tree-toggle').first();
                if ($toggle.length) {
                    $toggle.attr('aria-expanded', isOpen ? 'true' : 'false');
                    $toggle.find('.toggle-symbol').text(isOpen ? '-' : '+');
                }
            }

            function closeAllTreeItems() {
                $comboPanel.find('.employee-org-tree-item.has-children').each(function() {
                    setTreeOpenState($(this), false);
                });
            }

            function markTreeSelection(unitId) {
                var selectedUnitId = (((unitId || '') + '')).trim();
                $comboPanel.find('.employee-org-tree-node-filter.is-active').removeClass('is-active');

                if (!selectedUnitId) {
                    $comboLabel.text(allDepartmentLabel);
                    return;
                }

                var $selectedNode = $comboPanel.find(
                    '.employee-org-tree-node-filter[data-org-unit-id="' + selectedUnitId + '"]'
                ).first();

                if (!$selectedNode.length) {
                    $comboLabel.text(allDepartmentLabel);
                    return;
                }

                $selectedNode.addClass('is-active');
                $selectedNode.parents('.employee-org-tree-item').each(function() {
                    setTreeOpenState($(this), true);
                });

                var name = ($selectedNode.find('.employee-org-tree-name').first().text() || '').trim();
                $comboLabel.text(name || allDepartmentLabel);
            }

            function selectDepartment(unitId) {
                $department.val(unitId).trigger('change');
                markTreeSelection(unitId);
            }

            function openCombo() {
                $combo.addClass('is-open');
            }

            function closeCombo() {
                $combo.removeClass('is-open');
            }

            function filterTree(keyword) {
                var term = (((keyword || '') + '')).trim().toLowerCase();

                if (!term) {
                    $comboPanel.find('.employee-org-tree-item').show();
                    closeAllTreeItems();
                    markTreeSelection($department.val());
                    return;
                }

                closeAllTreeItems();
                $comboPanel.find('.employee-org-tree-item').hide();

                $comboPanel.find('.employee-org-tree-node-filter').each(function() {
                    var $node = $(this);
                    var text = ($node.text() || '').toLowerCase();
                    if (text.indexOf(term) === -1) {
                        return;
                    }

                    var $item = $node.closest('.employee-org-tree-item');
                    $item.show();
                    $item.parents('.employee-org-tree-item').show().each(function() {
                        setTreeOpenState($(this), true);
                    });
                    $item.find('.employee-org-tree-item').show();
                    setTreeOpenState($item, true);
                });
            }

            $comboPanel.find('.employee-org-tree-toggle').each(function() {
                $(this).attr('aria-expanded', 'false').find('.toggle-symbol').text('+');
            });

            $comboToggle.on('click', function(event) {
                event.preventDefault();
                if ($combo.hasClass('is-open')) {
                    closeCombo();
                } else {
                    openCombo();
                    if ($comboSearch.length) {
                        $comboSearch.trigger('focus');
                    }
                }
            });

            $(document).on('click', function(event) {
                if (!$combo.is(event.target) && $combo.has(event.target).length === 0) {
                    closeCombo();
                }
            });

            $comboPanel.on('click', '.employee-org-tree-toggle', function(event) {
                event.preventDefault();
                event.stopPropagation();
                var $item = $(this).closest('.employee-org-tree-item');
                setTreeOpenState($item, !$item.hasClass('is-open'));
            });

            $comboPanel.on('click', '.employee-org-tree-node-filter', function(event) {
                event.preventDefault();
                var unitId = (((($(this).data('orgUnitId') || '') + ''))).trim();
                selectDepartment(unitId);
                closeCombo();
            });

            if ($comboClear.length) {
                $comboClear.on('click', function(event) {
                    event.preventDefault();
                    if ($comboSearch.length) {
                        $comboSearch.val('');
                    }
                    filterTree('');
                    selectDepartment('');
                });
            }

            if ($comboSearch.length) {
                $comboSearch.on('input', function() {
                    filterTree($(this).val());
                });
            }

            markTreeSelection($department.val());
        })(window.jQuery);
    </script>
@endpush
