@extends('backend.layouts.app')
@section('title', 'Professional Skill Management')
@section('content')
    @include('humanresource::master-data.header')
    @include('backend.layouts.common.validation')
    <div class="card mb-4 fixed-tab-body">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="fs-17 fw-semi-bold mb-0">Professional Skill Management</h6>
                </div>
                <div class="text-end">
                    <div class="actions">
                        @can('create_setup_rules')
                            <a href="#" class="btn btn-success btn-sm" data-bs-toggle="modal"
                                data-bs-target="#create-professional-skill"><i
                                    class="fa fa-plus-circle"></i>&nbsp;Add Professional Skill</a>
                            @include('humanresource::master-data.professional-skills.modal.create')
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
                            <th width="12%">Code</th>
                            <th width="20%">Skill (KM)</th>
                            <th width="18%">Skill (EN)</th>
                            <th width="10%">Shortcut</th>
                            <th width="8%">Retire Age</th>
                            <th width="10%">Budget</th>
                            <th width="8%">Status</th>
                            <th width="9%">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($skills as $skill)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $skill->code ?? '-' }}</td>
                                <td>{{ $skill->name_km ?: '-' }}</td>
                                <td>{{ $skill->name_en ?: '-' }}</td>
                                <td>{{ $skill->shortcut_km ?: ($skill->shortcut_en ?: '-') }}</td>
                                <td>{{ $skill->retire_age ?? '-' }}</td>
                                <td>{{ number_format((float) ($skill->budget_amount ?? 0), 2) }}</td>
                                <td>
                                    @if ($skill->is_active)
                                        <span class="badge bg-success">{{ localize('active') }}</span>
                                    @else
                                        <span class="badge bg-danger">{{ localize('inactive') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @can('update_setup_rules')
                                        <a href="#" class="btn btn-primary-soft btn-sm me-1" data-bs-toggle="modal"
                                            data-bs-target="#update-professional-skill-{{ $skill->id }}" title="{{ localize('edit') }}"><i
                                                class="fa fa-edit"></i></a>
                                        @include('humanresource::master-data.professional-skills.modal.edit')
                                    @endcan
                                    @can('delete_setup_rules')
                                        <a href="javascript:void(0)" class="btn btn-danger-soft btn-sm delete-confirm"
                                            data-bs-toggle="tooltip" title="{{ localize('delete') }}"
                                            data-route="{{ route('professional-skills.destroy', $skill->uuid) }}"
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
