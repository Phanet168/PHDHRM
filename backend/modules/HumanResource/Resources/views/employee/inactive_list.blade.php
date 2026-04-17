@extends('backend.layouts.app')
@section('title', localize('employee_list'))
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
            max-height: 260px;
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
    <div class="card mb-4 fixed-tab-body">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="fs-17 fw-semi-bold mb-0">{{ localize('employee_list') }}</h6>
                </div>
                <div class="text-end">
                    <div class="actions">
                        <button type="button" class="btn btn-success" data-bs-toggle="collapse"
                            data-bs-target="#flush-collapseOne" aria-expanded="false" aria-controls="flush-collapseOne"> <i
                                class="fas fa-filter"></i> {{ localize('filter') }}</button>
                        @can('create_inactive_employees_list')
                            <a href="{{ route('employees.create') }}" class="btn btn-success"><i
                                    class="fa fa-plus-circle"></i>&nbsp;{{ localize('add_employee') }}</a>
                        @endcan

                    </div>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-12">
                    <div class="accordion accordion-flush" id="accordionFlushExample">
                        <div class="accordion-item">
                            <div id="flush-collapseOne" class="accordion-collapse collapse bg-white mb-4"
                                aria-labelledby="flush-headingOne" data-bs-parent="#accordionFlushExample">

                                <div class="row">
                                    <div class="col-md-3 mb-4">
                                        <select id="employee_name" class="select-basic-single">
                                            <option selected disabled>{{ localize('select_employee') }}</option>
                                            @foreach ($employees as $employee)
                                                <option value="{{ $employee->id }}">{{ $employee->full_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-4">
                                        <div id="department-tree-combo" class="employee-org-combo"
                                            data-all-label="{{ localize('all_department') }}">
                                            <button type="button" id="department-tree-combo-toggle"
                                                class="employee-org-combo-toggle">
                                                <span id="department-tree-combo-label"
                                                    class="combo-label">{{ localize('all_department') }}</span>
                                                <i class="fa fa-chevron-down"></i>
                                            </button>
                                            <div class="employee-org-combo-dropdown">
                                                <div class="employee-org-combo-tools">
                                                    <input type="text" id="department-tree-search"
                                                        class="form-control form-control-sm"
                                                        placeholder="{{ localize('search_department') }}">
                                                    <button type="button" id="department-tree-clear"
                                                        class="btn btn-outline-secondary btn-sm">
                                                        {{ localize('reset') }}
                                                    </button>
                                                </div>
                                                <div id="department-tree-panel" class="employee-org-tree-filter-body">
                                                    @include('humanresource::employee.partials.filter-org-tree', ['nodes' => $org_unit_tree ?? []])
                                                </div>
                                            </div>
                                        </div>
                                        <select id="department" class="d-none">
                                            <option value="">{{ localize('all_department') }}</option>
                                            @foreach ($departments as $department)
                                                <option value="{{ $department->id }}">
                                                    {{ $department->path ?? $department->label ?? $department->department_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2 mb-4">
                                        <select id="designation" class="select-basic-single">
                                            <option value="">{{ localize('all_position') }}</option>
                                            @foreach ($positions as $key => $position)
                                                <option value="{{ $position->id }}">
                                                    {{ $position->position_name_km ?: $position->position_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2 mb-4">
                                        <input type="text" id="official_id_10" class="form-control"
                                            placeholder="{{ localize('official_id_10_digit') }}">
                                    </div>
                                    <div class="col-md-2 mb-4">
                                        <select id="work_status_name" class="select-basic-single">
                                            <option value="">{{ localize('all_work_status') }}</option>
                                            @foreach (($work_status_options ?? collect()) as $statusName)
                                                <option value="{{ $statusName }}">{{ $statusName }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2 mb-4">
                                        <select id="employee_status" class="select-basic-single">
                                            <option value="">{{ localize('all_employee_status') }}</option>
                                            <option value="1">{{ localize('active') }}</option>
                                            <option value="0" selected>{{ localize('inactive') }}</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 mb-4">
                                        <select id="gender" class="select-basic-single">
                                            <option value="">{{ localize('all_gender') }}</option>
                                            @foreach ($genders as $gender)
                                                @php
                                                    $g = mb_strtolower(trim((string) $gender->gender_name));
                                                    $gLabel = $g;
                                                    if (in_array($g, ['male', 'm', 'ប្រុស'], true)) {
                                                        $gLabel = localize('male');
                                                    } elseif (in_array($g, ['female', 'f', 'ស្រី'], true)) {
                                                        $gLabel = localize('female');
                                                    }
                                                @endphp
                                                <option value="{{ $gender->id }}">{{ $gLabel }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2 mb-4 align-self-end">
                                        <button type="button" id="filter"
                                            class="btn btn-success">{{ localize('find') }}</button>
                                        <button type="button" id="search-reset"
                                            class="btn btn-danger">{{ localize('reset') }}</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="table_customize">
                {{ $dataTable->table() }}
            </div>
        </div>
    </div>

@endsection
@push('js')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
    <script src="{{ module_asset('HumanResource/js/employee-filter.js') }}"></script>
@endpush
