@extends('backend.layouts.app')
@section('title', localize('org_role_management', 'គ្រប់គ្រងតួនាទីតាមអង្គភាព'))
@section('content')
    @include('humanresource::master-data.header')
    @include('backend.layouts.common.validation')

    @php
        $roleLabels = [
            'head' => localize('head_of_unit', 'ប្រធានអង្គភាព'),
            'deputy_head' => localize('deputy_head', 'អនុប្រធានអង្គភាព'),
            'manager' => localize('manager', 'អ្នកគ្រប់គ្រង/ប្រធានការិយាល័យ'),
        ];

        $scopeLabels = [
            'self' => localize('self_unit_only', 'តែអង្គភាពខ្លួនឯង'),
            'self_and_children' => localize('self_and_child_units', 'អង្គភាពខ្លួនឯង និងអង្គភាពរង'),
        ];
    @endphp

    <div class="card mb-4 fixed-tab-body">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="fs-17 fw-semi-bold mb-0">{{ localize('org_role_management', 'គ្រប់គ្រងតួនាទីតាមអង្គភាព') }}</h6>
                </div>
                <div class="text-end">
                    <div class="actions">
                        @can('create_department')
                            <a href="#" id="open-create-org-role" class="btn btn-success btn-sm" data-bs-toggle="modal"
                                data-bs-target="#create-user-org-role">
                                <i class="fa fa-plus-circle"></i>&nbsp;{{ localize('add', 'បន្ថែម') }}
                            </a>
                            @include('humanresource::master-data.user-org-roles.modal.create')
                        @endcan
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body">
            <div class="alert alert-info mb-3">
                <div class="fw-semibold mb-1">{{ localize('org_role_quick_guide', 'ការណែនាំខ្លី') }}</div>
                <div>{{ localize('org_role_guide_1', '១) ជ្រើសអ្នកប្រើ និងអង្គភាព') }}</div>
                <div>{{ localize('org_role_guide_2', '២) ជ្រើសតួនាទី (ប្រធាន/អនុប្រធាន/អ្នកគ្រប់គ្រង)') }}</div>
                <div>{{ localize('org_role_guide_3', '៣) កំណត់ Scope៖ តែអង្គភាពខ្លួនឯង ឬរួមទាំងអង្គភាពរង') }}</div>
                <div>{{ localize('org_role_guide_4', '៤) បើកស្ថានភាព សកម្ម ដើម្បីអនុវត្តក្នុង workflow') }}</div>
            </div>

            <form method="GET" action="{{ route('user-org-roles.index') }}" class="mb-3">
                <div class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label mb-1">{{ localize('user', 'អ្នកប្រើប្រាស់') }}</label>
                        <select name="user_id"
                            class="form-control org-role-user-ajax"
                            data-placeholder="{{ localize('select_user', 'ជ្រើសអ្នកប្រើប្រាស់') }}">
                            <option value="">{{ localize('all', 'ទាំងអស់') }}</option>
                            @if ((int) $selected_user_id > 0 && filled($selected_user_text))
                                <option value="{{ $selected_user_id }}" selected>{{ $selected_user_text }}</option>
                            @endif
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1">{{ localize('status', 'ស្ថានភាព') }}</label>
                        <select name="is_active" class="form-control select-basic-single">
                            <option value="">{{ localize('all', 'ទាំងអស់') }}</option>
                            <option value="1" @selected((string) $selected_status === '1')>
                                {{ localize('active', 'សកម្ម') }}
                            </option>
                            <option value="0" @selected((string) $selected_status === '0')>
                                {{ localize('inactive', 'អសកម្ម') }}
                            </option>
                        </select>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fa fa-search"></i>&nbsp;{{ localize('filter', 'ស្វែងរក') }}
                        </button>
                        <a href="{{ route('user-org-roles.index') }}" class="btn btn-secondary btn-sm">
                            {{ localize('reset', 'សម្អាត') }}
                        </a>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table id="example" class="table display table-bordered table-striped table-hover">
                    <thead>
                        <tr>
                            <th width="5%">{{ localize('sl', 'ល.រ') }}</th>
                            <th width="20%">{{ localize('user', 'អ្នកប្រើប្រាស់') }}</th>
                            <th width="20%">{{ localize('org_unit', 'អង្គភាព') }}</th>
                            <th width="12%">{{ localize('role', 'តួនាទី') }}</th>
                            <th width="14%">{{ localize('scope', 'វិសាលភាព') }}</th>
                            <th width="14%">{{ localize('effective_date', 'កាលបរិច្ឆេទមានសុពលភាព') }}</th>
                            <th width="7%">{{ localize('status', 'ស្ថានភាព') }}</th>
                            <th width="8%">{{ localize('action', 'សកម្មភាព') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($roles as $item)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $item->user?->full_name ?? '-' }}</div>
                                    <small class="text-muted">{{ $item->user?->email ?? '-' }}</small>
                                </td>
                                <td>{{ $item->department?->department_name ?? '-' }}</td>
                                <td>{{ $roleLabels[$item->org_role] ?? $item->org_role }}</td>
                                <td>{{ $scopeLabels[$item->scope_type] ?? $item->scope_type }}</td>
                                <td>
                                    {{ optional($item->effective_from)->format('d/m/Y') ?? '-' }}
                                    <br>
                                    <small class="text-muted">
                                        {{ optional($item->effective_to)->format('d/m/Y') ?? localize('open_end', 'មិនកំណត់ថ្ងៃបញ្ចប់') }}
                                    </small>
                                </td>
                                <td>
                                    @if ($item->is_active)
                                        <span class="badge bg-success">{{ localize('active', 'សកម្ម') }}</span>
                                    @else
                                        <span class="badge bg-danger">{{ localize('inactive', 'អសកម្ម') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @can('update_department')
                                        <a href="#" class="btn btn-primary-soft btn-sm me-1" data-bs-toggle="modal"
                                            data-bs-target="#update-user-org-role-{{ $item->id }}"
                                            title="{{ localize('edit', 'កែប្រែ') }}"><i class="fa fa-edit"></i></a>
                                        @include('humanresource::master-data.user-org-roles.modal.edit')
                                    @endcan
                                    @can('delete_department')
                                        <a href="javascript:void(0)" class="btn btn-danger-soft btn-sm delete-confirm"
                                            data-bs-toggle="tooltip" title="{{ localize('delete', 'លុប') }}"
                                            data-route="{{ route('user-org-roles.destroy', $item->uuid) }}"
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

@push('js')
    <script>
        (function() {
            const modal = document.getElementById('create-user-org-role');
            if (!modal) return;

            modal.addEventListener('show.bs.modal', function() {
                const filterUser = document.querySelector('form[action="{{ route('user-org-roles.index') }}"] select[name="user_id"]');
                const modalUser = modal.querySelector('select[name="user_id"]');
                if (!filterUser || !modalUser) return;

                // If no user is selected in modal yet, prefill from filter user.
                if (!modalUser.value && filterUser.value) {
                    const text = filterUser.options[filterUser.selectedIndex]
                        ? filterUser.options[filterUser.selectedIndex].text
                        : filterUser.value;
                    if (!modalUser.querySelector('option[value="' + filterUser.value + '"]')) {
                        const opt = new Option(text, filterUser.value, true, true);
                        modalUser.add(opt);
                    }
                    modalUser.value = filterUser.value;
                    if (window.jQuery) {
                        window.jQuery(modalUser).trigger('change');
                    } else {
                        modalUser.dispatchEvent(new Event('change'));
                    }
                }
            });
        })();

        (function($) {
            "use strict";
            if (!$ || !$.fn || !$.fn.select2) {
                return;
            }

            $('.org-role-user-ajax').each(function() {
                var $el = $(this);
                var placeholder = $el.data('placeholder') || 'Select user';
                var inModal = $el.closest('.modal');

                if ($el.hasClass('select2-hidden-accessible')) {
                    $el.select2('destroy');
                }

                $el.select2({
                    width: '100%',
                    allowClear: true,
                    placeholder: placeholder,
                    dropdownParent: inModal.length ? inModal : $(document.body),
                    minimumInputLength: 0,
                    ajax: {
                        url: '{{ route('user-org-roles.user-options') }}',
                        dataType: 'json',
                        delay: 250,
                        data: function(params) {
                            return {
                                q: params.term || '',
                                page: params.page || 1
                            };
                        },
                        processResults: function(data, params) {
                            params.page = params.page || 1;
                            return {
                                results: data.results || [],
                                pagination: data.pagination || {
                                    more: false
                                }
                            };
                        },
                        cache: true
                    }
                });
            });
        })(window.jQuery);
    </script>
@endpush
