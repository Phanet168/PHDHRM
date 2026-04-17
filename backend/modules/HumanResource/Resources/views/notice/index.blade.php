@extends('backend.layouts.app')
@section('title', localize('notice_list'))
@section('content')
    @php use Modules\HumanResource\Entities\Notice; @endphp
    @include('backend.layouts.common.validation')
    @include('backend.layouts.common.message')
    <div class="card mb-4">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="fs-17 fw-semi-bold mb-0">{{ localize('notice_list') }}</h6>
                </div>
                <div class="text-end">
                    <div class="actions">
                        @can('create_notice')
                            <a href="#" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addNotice"><i
                                    class="fa fa-plus-circle"></i>&nbsp;{{ localize('add_notice') }}</a>
                            @include('humanresource::notice.create')
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
                            <th class="text-center" width="5%">{{ localize('sl') }}</th>
                            <th width="17%">{{ localize('notice_type') }}</th>
                            <th width="18%">{{ localize('notice_descriptiion') }}</th>
                            <th width="10%">{{ localize('notice_date') }}</th>
                            <th width="10%">{{ localize('audience', 'Audience') }}</th>
                            <th width="10%">{{ localize('status') }}</th>
                            <th width="10%">{{ localize('delivery', 'Delivery') }}</th>
                            <th width="10%">{{ localize('notice_attachment') }}</th>
                            <th width="15%">{{ localize('action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dbData as $key => $data)
                            @php
                                $status = $data->status ?: Notice::STATUS_DRAFT;
                                $statusClass = match ($status) {
                                    Notice::STATUS_DRAFT => 'secondary',
                                    Notice::STATUS_PENDING_APPROVAL => 'warning',
                                    Notice::STATUS_APPROVED => 'info',
                                    Notice::STATUS_REJECTED => 'danger',
                                    Notice::STATUS_SCHEDULED => 'primary',
                                    Notice::STATUS_SENT => 'success',
                                    Notice::STATUS_PARTIAL_FAILED => 'dark',
                                    default => 'secondary',
                                };
                                $statusLabel = match ($status) {
                                    Notice::STATUS_DRAFT => localize('draft', 'Draft'),
                                    Notice::STATUS_PENDING_APPROVAL => localize('pending_approval', 'Pending approval'),
                                    Notice::STATUS_APPROVED => localize('approved', 'Approved'),
                                    Notice::STATUS_REJECTED => localize('rejected', 'Rejected'),
                                    Notice::STATUS_SCHEDULED => localize('scheduled', 'Scheduled'),
                                    Notice::STATUS_SENT => localize('sent', 'Sent'),
                                    Notice::STATUS_PARTIAL_FAILED => localize('partial_failed', 'Partial failed'),
                                    default => ucfirst((string) $status),
                                };
                                $audienceLabel = match ((string) $data->audience_type) {
                                    Notice::AUDIENCE_USERS => localize('selected_users', 'Selected users'),
                                    Notice::AUDIENCE_ROLES => localize('selected_roles', 'Selected roles'),
                                    Notice::AUDIENCE_DEPARTMENTS => localize('selected_units', 'Selected units'),
                                    default => localize('all_users', 'All users'),
                                };
                                $deliverySummary = ((int) $data->delivery_success) . '/' . ((int) $data->delivery_total);
                                $canApproveCurrent = (bool) ($canApproveMap[(int) $data->id] ?? false);
                            @endphp
                            <tr>
                                <td class="text-center">{{ $key + 1 }}</td>
                                <td>{{ $data->notice_type }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($data->notice_descriptiion, 90) }}</td>
                                <td>{{ optional($data->notice_date)->format('d/m/Y') }}</td>
                                <td>{{ $audienceLabel }}</td>
                                <td>
                                    <span class="badge bg-{{ $statusClass }}">{{ $statusLabel }}</span>
                                </td>
                                <td>
                                    <div class="small fw-semibold">{{ $deliverySummary }}</div>
                                    @if (!empty($data->delivery_last_error))
                                        <div class="small text-danger">{{ \Illuminate\Support\Str::limit($data->delivery_last_error, 30) }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if (!empty($data->notice_attachment))
                                        <a href="{{ asset('storage/' . $data->notice_attachment) }}" target="_blank"
                                            rel="noopener" class="btn btn-info-soft btn-sm">
                                            <i class="fa fa-paperclip"></i>
                                        </a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @can('update_notice')
                                        <a href="#" class="btn btn-primary-soft btn-sm me-1" data-bs-toggle="modal"
                                            data-bs-target="#editNotice{{ $data->id }}" title="Edit"><i
                                                class="fa fa-edit"></i></a>
                                        @include('humanresource::notice.edit')

                                        @if (in_array($status, [Notice::STATUS_DRAFT, Notice::STATUS_REJECTED], true))
                                            <form action="{{ route('notice.submit', $data->uuid) }}" method="POST"
                                                class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-info-soft btn-sm me-1"
                                                    onclick="return confirm('{{ localize('submit_for_approval_confirm', 'Submit this notice for approval?') }}')">
                                                    <i class="fa fa-paper-plane"></i>
                                                </button>
                                            </form>
                                        @endif

                                        @if ($status === Notice::STATUS_PENDING_APPROVAL && $canApproveCurrent)
                                            <form action="{{ route('notice.approve', $data->uuid) }}" method="POST"
                                                class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-success-soft btn-sm me-1"
                                                    onclick="return confirm('{{ localize('approve_notice_confirm', 'Approve this notice?') }}')">
                                                    <i class="fa fa-check"></i>
                                                </button>
                                            </form>

                                            <button type="button" class="btn btn-warning-soft btn-sm me-1 notice-reject-btn"
                                                data-form="rejectNotice{{ $data->id }}">
                                                <i class="fa fa-times"></i>
                                            </button>
                                            <form id="rejectNotice{{ $data->id }}"
                                                action="{{ route('notice.reject', $data->uuid) }}" method="POST" class="d-none">
                                                @csrf
                                                <input type="hidden" name="rejected_reason" value="">
                                            </form>
                                        @elseif ($status === Notice::STATUS_PENDING_APPROVAL)
                                            <span class="badge bg-secondary">
                                                {{ localize('waiting_other_approver', 'Waiting other approver') }}
                                            </span>
                                        @endif

                                        @if (in_array($status, [Notice::STATUS_APPROVED, Notice::STATUS_SCHEDULED, Notice::STATUS_PARTIAL_FAILED], true))
                                            <form action="{{ route('notice.send', $data->uuid) }}" method="POST"
                                                class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-secondary-soft btn-sm me-1"
                                                    onclick="return confirm('{{ localize('send_notice_now_confirm', 'Send this notice now?') }}')">
                                                    <i class="fa fa-send"></i>
                                                </button>
                                            </form>
                                        @endif
                                    @endcan

                                    @can('delete_notice')
                                        <a href="javascript:void(0)" class="btn btn-danger-soft btn-sm delete-confirm"
                                            data-bs-toggle="tooltip" title="Delete"
                                            data-route="{{ route('notice.destroy', $data->uuid) }}"
                                            data-csrf="{{ csrf_token() }}"><i class="fa fa-trash"></i></a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center">{{ localize('empty_data') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('js')
    <script>
        (function($) {
            "use strict";
            var rejectPromptText = @json(localize('rejected_reason', 'Rejected reason'));
            var rejectRequiredText = @json(localize('please_enter_rejected_reason', 'Please enter rejected reason.'));

            function pad2(value) {
                return String(value).padStart(2, '0');
            }

            function normalizeNoticeDateValue(rawValue) {
                var value = (rawValue || '').toString().trim();
                if (!value) {
                    return '';
                }

                var ymd = value.match(/^(\d{4})[\/-](\d{1,2})[\/-](\d{1,2})$/);
                if (ymd) {
                    return pad2(ymd[3]) + '/' + pad2(ymd[2]) + '/' + ymd[1];
                }

                var dmy = value.match(/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/);
                if (dmy) {
                    return pad2(dmy[1]) + '/' + pad2(dmy[2]) + '/' + dmy[3];
                }

                return value;
            }

            function initNoticeDatePicker(scope) {
                if (typeof $.fn.datepicker !== 'function') {
                    return;
                }

                $(scope).find('input.notice-date-picker').each(function() {
                    var $input = $(this);
                    $input.attr('autocomplete', 'off');
                    $input.attr('placeholder', 'DD/MM/YYYY');
                    $input.val(normalizeNoticeDateValue($input.val()));

                    if ($input.hasClass('hasDatepicker')) {
                        $input.datepicker('destroy');
                    }

                    $input.datepicker({
                        dateFormat: 'dd/mm/yy',
                        changeMonth: true,
                        changeYear: true,
                        showAnim: 'slideDown',
                        onSelect: function(dateText) {
                            $input.val(normalizeNoticeDateValue(dateText));
                        }
                    });
                });
            }

            function syncAudienceTargets(scope) {
                $(scope).find('.notice-audience-type').each(function() {
                    var $select = $(this);
                    var audience = ($select.val() || 'all').toString();
                    var prefix = ($select.data('target-prefix') || '').toString();

                    if (!prefix) {
                        return;
                    }

                    var usersSelector = '#' + prefix + '_users';
                    var rolesSelector = '#' + prefix + '_roles';
                    var departmentsSelector = '#' + prefix + '_departments';

                    $(usersSelector + ',' + rolesSelector + ',' + departmentsSelector).hide();

                    if (audience === 'users') {
                        $(usersSelector).show();
                    } else if (audience === 'roles') {
                        $(rolesSelector).show();
                    } else if (audience === 'departments') {
                        $(departmentsSelector).show();
                    }
                });
            }

            $(document).ready(function() {
                initNoticeDatePicker(document);
                syncAudienceTargets(document);
            });

            $(document).on('shown.bs.modal', '#addNotice, [id^="editNotice"]', function() {
                initNoticeDatePicker(this);
                syncAudienceTargets(this);
            });

            $(document).on('change blur', 'input.notice-date-picker', function() {
                $(this).val(normalizeNoticeDateValue($(this).val()));
            });

            $(document).on('change', '.notice-audience-type', function() {
                syncAudienceTargets($(this).closest('.modal'));
            });

            $(document).on('click', '.set-workflow-action', function() {
                var action = ($(this).data('workflow-action') || 'draft').toString();
                $(this).closest('form').find('.workflow-action-input').val(action);
            });

            $(document).on('click', '.notice-reject-btn', function() {
                var formId = ($(this).data('form') || '').toString();
                if (!formId) {
                    return;
                }

                var reason = prompt(rejectPromptText);
                if (reason === null) {
                    return;
                }

                reason = reason.trim();
                if (!reason.length) {
                    alert(rejectRequiredText);
                    return;
                }

                var $form = $('#' + formId);
                $form.find('input[name="rejected_reason"]').val(reason);
                $form.submit();
            });
        })(jQuery);
    </script>
@endpush
