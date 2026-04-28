@extends('backend.layouts.app')

@section('title', localize('pharm_user_management', 'Pharmaceutical User Management'))

@section('content')
    <div class="body-content">
        @include('pharmaceutical::_nav')

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">
                    <i class="fa fa-users-cog"></i>
                    {{ localize('pharm_user_management', 'Pharmaceutical User Management') }}
                </h5>
                <a href="{{ route('pharmaceutical.users.create') }}" class="btn btn-sm btn-success">
                    <i class="fa fa-plus-circle"></i> {{ localize('add_user', 'Add User') }}
                </a>
            </div>

            <div class="card-body">
                {{-- Filter --}}
                <form method="GET" class="row g-2 mb-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small">{{ localize('search', 'Search') }}</label>
                        <input type="text" name="search" class="form-control form-control-sm"
                               value="{{ $search }}" placeholder="{{ localize('search_name_email', 'Name or email...') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">{{ localize('department', 'Department') }}</label>
                        <select name="department_id" class="form-select form-select-sm">
                            <option value="">{{ localize('all_departments', 'All departments') }}</option>
                            @foreach ($departments as $dept)
                                @php
                                    $typeLabel = match((int) $dept->unit_type_id) {
                                        1 => 'PHD', 4 => 'OD', 6 => 'Hospital', 7 => 'HC', default => '?'
                                    };
                                @endphp
                                <option value="{{ $dept->id }}" {{ $filterDept == $dept->id ? 'selected' : '' }}>
                                    [{{ $typeLabel }}] {{ $dept->department_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-sm btn-primary w-100">
                            <i class="fa fa-search"></i> {{ localize('filter', 'Filter') }}
                        </button>
                    </div>
                </form>

                {{-- Table --}}
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>{{ localize('user', 'User') }}</th>
                                <th>{{ localize('department', 'Department') }}</th>
                                <th>{{ localize('level', 'Level') }}</th>
                                <th>{{ localize('org_role', 'Role') }}</th>
                                <th>{{ localize('scope', 'Scope') }}</th>
                                <th>{{ localize('status', 'Status') }}</th>
                                <th style="width:120px;">{{ localize('actions', 'Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($roles as $i => $role)
                                @php
                                    $typeLabel = '?';
                                    if ($role->department) {
                                        $typeLabel = match((int) $role->department->unit_type_id) {
                                            1 => 'PHD', 4 => 'OD', 6 => 'Hospital', 7 => 'HC', default => '?'
                                        };
                                    }
                                    $roleCode = $role->systemRole?->code ?: $role->org_role;
                                    $roleLabel = $roleLabels[$roleCode] ?? ($roleCode ?: '-');
                                    $scopeLabel = match($role->scope_type) {
                                        'self', 'self_only'       => localize('scope_self_only', 'Self only'),
                                        'self_unit_only'          => localize('scope_self_unit_only', 'Self unit only'),
                                        'self_and_children'       => localize('scope_self_children', 'Self + children'),
                                        'all'                     => localize('scope_all', 'All'),
                                        default                   => $role->scope_type ?: '—',
                                    };
                                @endphp
                                <tr class="{{ $role->is_active ? '' : 'table-secondary' }}">
                                    <td>{{ $roles->firstItem() + $i }}</td>
                                    <td>
                                        @if($role->user)
                                            <strong>{{ $role->user->full_name }}</strong>
                                            @if($role->user->email)
                                                <br><small class="text-muted">{{ $role->user->email }}</small>
                                            @endif
                                        @else
                                            <span class="text-danger"><i class="fa fa-exclamation-triangle me-1"></i>{{ localize('user_deleted', 'User deleted') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($role->department)
                                            {{ $role->department->department_name }}
                                        @else
                                            <span class="text-warning"><i class="fa fa-exclamation-triangle me-1"></i>{{ localize('dept_not_found', 'Dept not found') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($typeLabel !== '?')
                                        <span class="badge bg-{{ match($typeLabel) { 'PHD'=>'primary', 'OD'=>'info', 'Hospital'=>'warning', 'HC'=>'success', default=>'secondary' } }}">
                                            {{ $typeLabel }}
                                        </span>
                                        @else
                                            <span class="badge bg-secondary">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $roleLabel }}</td>
                                    <td>{{ $scopeLabel }}</td>
                                    <td>
                                        @if ($role->is_active)
                                            <span class="badge bg-success">{{ localize('active', 'Active') }}</span>
                                        @else
                                            <span class="badge bg-danger">{{ localize('inactive', 'Inactive') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <form method="POST" action="{{ route('pharmaceutical.users.toggle', $role->uuid) }}"
                                              class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-xs btn-outline-{{ $role->is_active ? 'warning' : 'success' }}"
                                                    title="{{ $role->is_active ? localize('deactivate', 'Deactivate') : localize('activate', 'Activate') }}">
                                                <i class="fa fa-{{ $role->is_active ? 'ban' : 'check' }}"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('pharmaceutical.users.destroy', $role->uuid) }}"
                                              class="d-inline" onsubmit="return confirm('{{ localize('confirm_delete', 'Are you sure?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-xs btn-outline-danger"
                                                    title="{{ localize('delete', 'Delete') }}">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        {{ localize('no_users_found', 'No users found.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $roles->links() }}
            </div>
        </div>
    </div>
@endsection
