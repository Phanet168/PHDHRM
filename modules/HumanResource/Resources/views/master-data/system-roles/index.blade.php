@extends('backend.layouts.app')
@section('title', localize('system_roles', 'System Roles'))
@section('content')
    @include('humanresource::master-data.org-structure.header')
    @include('backend.layouts.common.validation')

    <div class="card mb-4 fixed-tab-body">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="fs-17 fw-semi-bold mb-0">{{ localize('system_role_management', 'System Role Management') }}</h6>
                </div>
                <div class="text-end">
                    @can('create_department')
                        <a href="#" class="btn btn-success btn-sm" data-bs-toggle="modal"
                            data-bs-target="#create-system-role">
                            <i class="fa fa-plus-circle"></i>&nbsp;{{ localize('add_role', 'Add Role') }}
                        </a>
                        @include('humanresource::master-data.system-roles.modal.create')
                    @endcan
                </div>
            </div>
        </div>

        <div class="card-body">
            <div class="alert alert-info mb-3">
                <strong>System Roles</strong> define functional authority levels used across modules
                (Org Role Assignment, Permission Matrix, Workflow Steps).
                <code>is_system = true</code> roles cannot be deleted.
            </div>

            <div class="table-responsive">
                <table id="example" class="table display table-bordered table-striped table-hover">
                    <thead>
                        <tr>
                            <th width="4%">#</th>
                            <th width="12%">{{ localize('code', 'Code') }}</th>
                            <th width="18%">{{ localize('name_en', 'Name (EN)') }}</th>
                            <th width="18%">{{ localize('name_km', 'Name (KM)') }}</th>
                            <th width="8%">{{ localize('level', 'Level') }}</th>
                            <th width="10%">{{ localize('can_approve', 'Can Approve') }}</th>
                            <th width="8%">{{ localize('system', 'System') }}</th>
                            <th width="8%">{{ localize('status', 'Status') }}</th>
                            <th width="14%">{{ localize('action', 'Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($roles as $item)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td><code>{{ $item->code }}</code></td>
                                <td>{{ $item->name }}</td>
                                <td>{{ $item->name_km ?: '-' }}</td>
                                <td class="text-center">{{ $item->level }}</td>
                                <td class="text-center">
                                    @if ($item->can_approve)
                                        <span class="badge bg-success">Yes</span>
                                    @else
                                        <span class="badge bg-secondary">No</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($item->is_system)
                                        <i class="fa fa-lock text-warning" title="Protected"></i>
                                    @else
                                        <i class="fa fa-unlock text-muted"></i>
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
                                    @can('update_department')
                                        <a href="#" class="btn btn-primary-soft btn-sm me-1" data-bs-toggle="modal"
                                            data-bs-target="#edit-system-role-{{ $item->id }}">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                        @include('humanresource::master-data.system-roles.modal.edit', ['role' => $item])
                                    @endcan

                                    @can('delete_department')
                                        @if (!$item->is_system)
                                            <a href="#" class="btn btn-danger-soft btn-sm delete-system-role"
                                                data-url="{{ route('system-roles.destroy', $item->uuid) }}">
                                                <i class="fa fa-trash"></i>
                                            </a>
                                        @endif
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

@push('js')
    <script>
        $(document).on('click', '.delete-system-role', function(e) {
            e.preventDefault();
            var url = $(this).data('url');
            if (!confirm('Are you sure you want to delete this role?')) return;

            $.ajax({
                url: url,
                type: 'DELETE',
                data: { _token: '{{ csrf_token() }}' },
                success: function(res) {
                    if (res.success) {
                        toastr.success(res.message);
                        location.reload();
                    } else {
                        toastr.error(res.message);
                    }
                },
                error: function(xhr) {
                    var msg = xhr.responseJSON?.message || 'Error deleting role.';
                    toastr.error(msg);
                }
            });
        });
    </script>
@endpush
