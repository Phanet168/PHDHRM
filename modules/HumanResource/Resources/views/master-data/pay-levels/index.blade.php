@extends('backend.layouts.app')
@section('title', 'Pay Level Management')
@section('content')
    @include('humanresource::master-data.header')
    @include('backend.layouts.common.validation')
    <div class="card mb-4 fixed-tab-body">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="fs-17 fw-semi-bold mb-0">Government Pay Level Management</h6>
                </div>
                <div class="text-end">
                    <div class="actions">
                        @can('create_setup_rules')
                            <a href="#" class="btn btn-success btn-sm" data-bs-toggle="modal"
                                data-bs-target="#create-pay-level"><i
                                    class="fa fa-plus-circle"></i>&nbsp;Add Pay Level</a>
                            @include('humanresource::master-data.pay-levels.modal.create')
                        @endcan
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="example" class="table display table-bordered table-striped table-hover">
                    <thead>
                        <tr>
                            <th width="5%">SL</th>
                            <th width="15%">Code</th>
                            <th width="22%">Pay Level (KM)</th>
                            <th width="14%">Base Budget</th>
                            <th width="10%">Sort</th>
                            <th width="10%">Status</th>
                            <th width="12%">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pay_levels as $pay_level)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $pay_level->level_code }}</td>
                                <td>{{ $pay_level->level_name_km ?? '-' }}</td>
                                <td>{{ number_format((float) ($pay_level->budget_amount ?? 0), 2) }}</td>
                                <td>{{ $pay_level->sort_order }}</td>
                                <td>
                                    @if ($pay_level->is_active)
                                        <span class="badge bg-success">{{ localize('active') }}</span>
                                    @else
                                        <span class="badge bg-danger">{{ localize('inactive') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @can('update_setup_rules')
                                        <a href="#" class="btn btn-primary-soft btn-sm me-1" data-bs-toggle="modal"
                                            data-bs-target="#update-pay-level-{{ $pay_level->id }}" title="{{ localize('edit') }}"><i
                                                class="fa fa-edit"></i></a>
                                        @include('humanresource::master-data.pay-levels.modal.edit')
                                    @endcan
                                    @can('delete_setup_rules')
                                        <a href="javascript:void(0)" class="btn btn-danger-soft btn-sm delete-confirm"
                                            data-bs-toggle="tooltip" title="{{ localize('delete') }}"
                                            data-route="{{ route('pay-levels.destroy', $pay_level->uuid) }}"
                                            data-csrf="{{ csrf_token() }}"><i class="fa fa-trash"></i></a>
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
