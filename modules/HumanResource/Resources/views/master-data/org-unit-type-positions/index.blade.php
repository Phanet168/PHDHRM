@extends('backend.layouts.app')
@section('title', localize('org_position_matrix', 'Org Position Matrix'))
@section('content')
    @include('humanresource::master-data.header')
    @include('backend.layouts.common.validation')

    <div class="card mb-4 fixed-tab-body">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="fs-17 fw-semi-bold mb-0">{{ localize('org_position_matrix', 'Org Position Matrix') }}</h6>
                </div>
                <div class="text-end">
                    @can('create_positions')
                        <a href="#" class="btn btn-success btn-sm" data-bs-toggle="modal"
                            data-bs-target="#create-org-position-matrix">
                            <i class="fa fa-plus-circle"></i>&nbsp;{{ localize('add', 'Add') }}
                        </a>
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
                            <th width="20%">{{ localize('org_unit_type', 'Org Unit Type') }}</th>
                            <th width="20%">{{ localize('position', 'Position') }}</th>
                            <th width="10%">{{ localize('rank', 'Rank') }}</th>
                            <th width="10%">{{ localize('leadership', 'Leadership') }}</th>
                            <th width="10%">{{ localize('approval', 'Approval') }}</th>
                            <th width="10%">{{ localize('status', 'Status') }}</th>
                            <th width="15%">{{ localize('action', 'Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    {{ app()->getLocale() === 'en'
                                        ? ($item->unitType?->name ?? '-')
                                        : ($item->unitType?->name_km ?: ($item->unitType?->name ?? '-')) }}
                                </td>
                                <td>
                                    {{ $item->position?->position_name_km ?: ($item->position?->position_name ?? '-') }}
                                </td>
                                <td>{{ $item->hierarchy_rank ?? ($item->position?->position_rank ?? '-') }}</td>
                                <td>
                                    @if ($item->is_leadership)
                                        <span class="badge bg-info">{{ localize('yes', 'Yes') }}</span>
                                    @else
                                        <span class="badge bg-secondary">{{ localize('no', 'No') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($item->can_approve)
                                        <span class="badge bg-success">{{ localize('can_approve', 'Can Approve') }}</span>
                                    @else
                                        <span class="badge bg-secondary">{{ localize('no', 'No') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($item->is_active)
                                        <span class="badge bg-success">{{ localize('active', 'Active') }}</span>
                                    @else
                                        <span class="badge bg-danger">{{ localize('inactive', 'Inactive') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @can('update_positions')
                                        <a href="#" class="btn btn-primary-soft btn-sm me-1" data-bs-toggle="modal"
                                            data-bs-target="#update-org-position-matrix-{{ $item->id }}">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endcan
                                    @can('delete_positions')
                                        <a href="javascript:void(0)" class="btn btn-danger-soft btn-sm delete-confirm"
                                            data-route="{{ route('org-unit-type-positions.destroy', $item->id) }}"
                                            data-csrf="{{ csrf_token() }}">
                                            <i class="fa fa-trash"></i>
                                        </a>
                                    @endcan
                                </td>
                            </tr>

                            @can('update_positions')
                                <div class="modal fade" id="update-org-position-matrix-{{ $item->id }}"
                                    data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">{{ localize('edit', 'Edit') }}</h5>
                                            </div>
                                            <form action="{{ route('org-unit-type-positions.update', $item->id) }}"
                                                method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <div class="modal-body">
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">{{ localize('org_unit_type', 'Org Unit Type') }} <span
                                                                    class="text-danger">*</span></label>
                                                            <select name="unit_type_id" class="form-control select-basic-single"
                                                                required>
                                                                @foreach ($unitTypes as $type)
                                                                    <option value="{{ $type->id }}" @selected((int) $item->unit_type_id === (int) $type->id)>
                                                                        {{ app()->getLocale() === 'en' ? $type->name : ($type->name_km ?: $type->name) }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">{{ localize('position', 'Position') }} <span
                                                                    class="text-danger">*</span></label>
                                                            <select name="position_id" class="form-control select-basic-single"
                                                                required>
                                                                @foreach ($positions as $position)
                                                                    <option value="{{ $position->id }}" @selected((int) $item->position_id === (int) $position->id)>
                                                                        {{ $position->position_name_km ?: $position->position_name }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">{{ localize('rank', 'Rank') }}</label>
                                                            <input type="number" name="hierarchy_rank" min="1"
                                                                class="form-control" value="{{ $item->hierarchy_rank }}">
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">{{ localize('leadership', 'Leadership') }}</label>
                                                            <select name="is_leadership" class="form-control">
                                                                <option value="1" @selected((int) $item->is_leadership === 1)>{{ localize('yes', 'Yes') }}</option>
                                                                <option value="0" @selected((int) $item->is_leadership === 0)>{{ localize('no', 'No') }}</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">{{ localize('approval', 'Approval') }}</label>
                                                            <select name="can_approve" class="form-control">
                                                                <option value="1" @selected((int) $item->can_approve === 1)>{{ localize('yes', 'Yes') }}</option>
                                                                <option value="0" @selected((int) $item->can_approve === 0)>{{ localize('no', 'No') }}</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">{{ localize('status', 'Status') }}</label>
                                                            <select name="is_active" class="form-control">
                                                                <option value="1" @selected((int) $item->is_active === 1)>{{ localize('active', 'Active') }}</option>
                                                                <option value="0" @selected((int) $item->is_active === 0)>{{ localize('inactive', 'Inactive') }}</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6">
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
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @can('create_positions')
        <div class="modal fade" id="create-org-position-matrix" data-bs-backdrop="static" data-bs-keyboard="false"
            tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ localize('add', 'Add') }}</h5>
                    </div>
                    <form action="{{ route('org-unit-type-positions.store') }}" method="POST">
                        @csrf
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">{{ localize('org_unit_type', 'Org Unit Type') }} <span
                                            class="text-danger">*</span></label>
                                    <select name="unit_type_id" class="form-control select-basic-single" required>
                                        <option value="">{{ localize('select_one', 'Select One') }}</option>
                                        @foreach ($unitTypes as $type)
                                            <option value="{{ $type->id }}">
                                                {{ app()->getLocale() === 'en' ? $type->name : ($type->name_km ?: $type->name) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ localize('position', 'Position') }} <span
                                            class="text-danger">*</span></label>
                                    <select name="position_id" class="form-control select-basic-single" required>
                                        <option value="">{{ localize('select_one', 'Select One') }}</option>
                                        @foreach ($positions as $position)
                                            <option value="{{ $position->id }}">
                                                {{ $position->position_name_km ?: $position->position_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">{{ localize('rank', 'Rank') }}</label>
                                    <input type="number" name="hierarchy_rank" min="1" class="form-control"
                                        placeholder="{{ localize('rank', 'Rank') }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">{{ localize('leadership', 'Leadership') }}</label>
                                    <select name="is_leadership" class="form-control">
                                        <option value="1">{{ localize('yes', 'Yes') }}</option>
                                        <option value="0" selected>{{ localize('no', 'No') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">{{ localize('approval', 'Approval') }}</label>
                                    <select name="can_approve" class="form-control">
                                        <option value="1">{{ localize('yes', 'Yes') }}</option>
                                        <option value="0" selected>{{ localize('no', 'No') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ localize('status', 'Status') }}</label>
                                    <select name="is_active" class="form-control">
                                        <option value="1" selected>{{ localize('active', 'Active') }}</option>
                                        <option value="0">{{ localize('inactive', 'Inactive') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
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
    @endcan
@endsection
