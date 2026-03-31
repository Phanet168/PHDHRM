@extends('backend.layouts.app')
@section('title', 'Employee Status Management')
@section('content')
    @include('humanresource::master-data.header')
    @include('backend.layouts.common.validation')

    <div class="card mb-4 fixed-tab-body">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="fs-17 fw-semi-bold mb-0">Employee Status Management</h6>
                </div>
                <div class="text-end">
                    @can('create_setup_rules')
                        <a href="#" class="btn btn-success btn-sm" data-bs-toggle="modal"
                            data-bs-target="#create-employee-status">
                            <i class="fa fa-plus-circle"></i>&nbsp;Add Employee Status
                        </a>
                        @include('humanresource::master-data.employee-statuses.modal.create')
                    @endcan
                </div>
            </div>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table id="example" class="table display table-bordered table-striped table-hover">
                    <thead>
                        <tr>
                            <th width="5%">SL</th>
                            <th width="12%">Code</th>
                            <th width="20%">Status (KM)</th>
                            <th width="18%">Status (EN)</th>
                            <th width="12%">Transition Group</th>
                            <th width="8%">Sort</th>
                            <th width="10%">Status</th>
                            <th width="15%">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($employee_statuses as $item)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $item->code ?? '-' }}</td>
                                <td>{{ $item->name_km ?? '-' }}</td>
                                <td>{{ $item->name_en }}</td>
                                <td>
                                    @if ($item->transition_group === 'inactive')
                                        <span class="badge bg-danger">Inactive</span>
                                    @elseif($item->transition_group === 'suspended')
                                        <span class="badge bg-warning text-dark">Suspended</span>
                                    @else
                                        <span class="badge bg-success">Active</span>
                                    @endif
                                </td>
                                <td>{{ (int) ($item->sort_order ?? 0) }}</td>
                                <td>
                                    @if ($item->is_active)
                                        <span class="badge bg-success">{{ localize('active') }}</span>
                                    @else
                                        <span class="badge bg-danger">{{ localize('inactive') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @can('update_setup_rules')
                                        <a href="#" class="btn btn-primary-soft btn-sm me-1" data-bs-toggle="modal"
                                            data-bs-target="#update-employee-status-{{ $item->id }}" title="{{ localize('edit') }}">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                        @include('humanresource::master-data.employee-statuses.modal.edit')
                                    @endcan

                                    @can('delete_setup_rules')
                                        <a href="javascript:void(0)" class="btn btn-danger-soft btn-sm delete-confirm"
                                            data-bs-toggle="tooltip" title="{{ localize('delete') }}"
                                            data-route="{{ route('employee-statuses.destroy', $item->uuid) }}"
                                            data-csrf="{{ csrf_token() }}">
                                            <i class="fa fa-trash"></i>
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
