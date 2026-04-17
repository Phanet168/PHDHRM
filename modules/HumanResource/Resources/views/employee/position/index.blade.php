@extends('backend.layouts.app')
@section('title', localize('position_list'))
@section('content')
    @include('humanresource::master-data.header')
    @include('backend.layouts.common.validation')
    <div class="card mb-4 fixed-tab-body">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="fs-17 fw-semi-bold mb-0">Role / Position List</h6>
                </div>
                <div class="text-end">
                    <div class="actions">
                        @can('create_positions')
                            <a href="#" class="btn btn-success btn-sm" data-bs-toggle="modal"
                                data-bs-target="#create-position"><i
                                    class="fa fa-plus-circle"></i>&nbsp;Add Role / Position</a>
                            @include('humanresource::employee.position.modal.create')
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
                            <th width="20%">Position (EN)</th>
                            <th width="20%">Position (KM)</th>
                            <th width="8%">Rank</th>
                            <th width="12%">Budget</th>
                            <th width="12%">Provincial Level</th>
                            <th width="10%">Status</th>
                            <th width="13%">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($positions as $position)
                            @php($positionIdentifier = $position->uuid ?: $position->id)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $position->position_name }}</td>
                                <td>{{ $position->position_name_km ?? '-' }}</td>
                                <td>{{ $position->position_rank ?? '-' }}</td>
                                <td>{{ number_format((float) ($position->budget_amount ?? 0), 2) }}</td>
                                <td>
                                    @if ($position->is_prov_level)
                                        <span class="badge bg-info">Yes</span>
                                    @else
                                        <span class="badge bg-secondary">No</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($position->is_active == 1)
                                        <span class="badge bg-success">{{ localize('active') }}</span>
                                    @elseif($position->is_active == 0)
                                        <span class="badge bg-danger ">{{ localize('inactive') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @can('update_positions')
                                        <a href="#" class="btn btn-primary-soft btn-sm me-1" data-bs-toggle="modal"
                                            data-bs-target="#update-position-{{ $position->id }}" title="Edit"><i
                                                class="fa fa-edit"></i></a>
                                        @include('humanresource::employee.position.modal.edit')
                                    @endcan

                                    @can('delete_positions')
                                        <a href="javascript:void(0)" class="btn btn-danger-soft btn-sm delete-confirm"
                                            data-bs-toggle="tooltip" title="Delete"
                                            data-route="{{ route('positions.destroy', $positionIdentifier) }}"
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
