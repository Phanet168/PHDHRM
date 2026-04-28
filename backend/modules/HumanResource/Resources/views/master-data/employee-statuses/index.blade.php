@extends('backend.layouts.app')
@section('title', localize('employee_status_management', 'ការគ្រប់គ្រងស្ថានភាពបុគ្គលិក'))
@section('content')
    @include('humanresource::master-data.header')
    @include('backend.layouts.common.validation')

    <div class="card mb-4 fixed-tab-body">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="fs-17 fw-semi-bold mb-0">{{ localize('employee_status_management', 'ការគ្រប់គ្រងស្ថានភាពបុគ្គលិក') }}</h6>
                </div>
                <div class="text-end">
                    @can('create_setup_rules')
                        <a href="#" class="btn btn-success btn-sm" data-bs-toggle="modal"
                            data-bs-target="#create-employee-status">
                            <i class="fa fa-plus-circle"></i>&nbsp;{{ localize('add_employee_status', 'បន្ថែមស្ថានភាពបុគ្គលិក') }}
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
                            <th width="5%">{{ localize('sl') }}</th>
                            <th width="12%">{{ localize('code') }}</th>
                            <th width="20%">{{ localize('status_km_label', 'ស្ថានភាព (ខ្មែរ)') }}</th>
                            <th width="18%">{{ localize('status_en_label', 'ស្ថានភាព (អង់គ្លេស)') }}</th>
                            <th width="12%">{{ localize('transition_group', 'ក្រុមផ្លាស់ប្តូរ') }}</th>
                            <th width="8%">{{ localize('sort_order') }}</th>
                            <th width="10%">{{ localize('status') }}</th>
                            <th width="15%">{{ localize('action') }}</th>
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
                                        <span class="badge bg-danger">{{ localize('inactive') }}</span>
                                    @elseif($item->transition_group === 'suspended')
                                        <span class="badge bg-warning text-dark">{{ localize('suspended') }}</span>
                                    @else
                                        <span class="badge bg-success">{{ localize('active') }}</span>
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
