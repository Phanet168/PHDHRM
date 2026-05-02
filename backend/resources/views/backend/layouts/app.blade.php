<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-100"
    @if (app_setting()->rtl_ltr == 1) dir="ltr" @else dir="rtl" @endif>

<head>
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="floating_number" content="{{ app_setting()->floating_number }}" />
    <meta name="negative_amount_symbol" content="{{ app_setting()->negative_amount_symbol }}" />
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="{{ app_setting()->title }}">
    <meta name="author" content="{{ app_setting()->title }}">
    <meta name="base-url" content="{{ url('/') }}">
    <meta name="get-localization-strings" content="{{ route('get-localization-strings') }}">
    <meta name="default-localization" content="{{ app_setting()->lang?->value }}">
    <title>@yield('title')</title>
    <!-- App favicon -->

    <link rel="shortcut icon" class="favicon_show" href="{{ app_setting()->favicon }}">
    @include('backend.layouts.assets.css')
    @php
        $isKhmerUi = app()->getLocale() === 'km' || (string) (app_setting()->lang?->value ?? '') === 'km';
    @endphp
    @if ($isKhmerUi)
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>
            :root {
                --khmer-font-family: "Noto Sans Khmer", "Khmer OS Battambang", "Khmer OS Siemreap", "Khmer OS", "Leelawadee UI", sans-serif;
            }

            body,
            .main-content,
            .sidebar,
            .navbar,
            .card,
            .table,
            .btn,
            .form-control,
            .form-select,
            .dropdown-menu,
            .modal-content,
            .dataTables_wrapper,
            .select2-container,
            .bootstrap-tagsinput {
                font-family: var(--khmer-font-family) !important;
                letter-spacing: 0;
            }

            .form-control,
            .form-select,
            .btn,
            .table td,
            .table th {
                line-height: 1.45;
            }

            .workflow-notification-menu {
                width: min(420px, calc(100vw - 24px));
                min-width: min(420px, calc(100vw - 24px));
                max-width: min(420px, calc(100vw - 24px));
                border: 1px solid #e5e7eb;
                border-radius: 16px;
                overflow: hidden;
                box-shadow: 0 16px 40px rgba(15, 23, 42, 0.12);
            }

            .workflow-notification-menu .list-group-item,
            .workflow-notification-menu .small,
            .workflow-notification-menu .fw-semi-bold,
            .workflow-notification-menu .fw-bold {
                line-height: 1.6;
                white-space: normal;
                word-break: break-word;
            }

            .workflow-notification-menu .list-group {
                max-height: min(65vh, 560px);
                overflow-y: auto;
                overscroll-behavior: contain;
            }

            .notification-hub-trigger {
                position: relative;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 40px;
                height: 40px;
                border-radius: 999px;
                color: #475467 !important;
                background: transparent;
                transition: background-color 0.18s ease, color 0.18s ease;
            }

            .notification-hub-trigger:hover {
                background: #f3f4f6;
                color: #111827 !important;
                text-decoration: none;
            }

            .notification-hub-trigger svg {
                width: 20px;
                height: 20px;
                display: block;
            }

            .notification-hub-badge {
                position: absolute;
                top: 1px;
                right: 0;
                min-width: 18px;
                height: 18px;
                padding: 0 4px;
                border-radius: 999px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border: 2px solid #fff;
                background: #ef4444;
                color: #fff;
                font-size: 10px;
                font-weight: 700;
                line-height: 1;
                box-shadow: 0 6px 12px rgba(239, 68, 68, 0.22);
            }

            .workflow-notification-header {
                padding: 14px 16px 12px;
                background: #fff;
            }

            .workflow-notification-section {
                padding: 14px 16px;
                background: #fff;
            }

            .workflow-notification-section + .workflow-notification-section {
                border-top: 1px solid #f1f5f9;
            }

            .workflow-notification-count {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 24px;
                height: 24px;
                padding: 0 8px;
                border-radius: 999px;
                background: #f3f4f6;
                color: #344054;
                font-size: 12px;
                font-weight: 700;
            }

            .workflow-notification-action {
                white-space: nowrap;
            }

            .workflow-notification-status {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                margin-top: 6px;
                font-size: 12px;
                font-weight: 600;
            }

            .workflow-notification-status-dot {
                width: 8px;
                height: 8px;
                border-radius: 999px;
                display: inline-block;
            }

            .workflow-notification-status.unread {
                color: #b42318;
            }

            .workflow-notification-status.unread .workflow-notification-status-dot {
                background: #ef4444;
            }

            .workflow-notification-status.read {
                color: #475467;
            }

            .workflow-notification-status.read .workflow-notification-status-dot {
                background: #98a2b3;
            }

            .workflow-notification-row {
                display: flex;
                align-items: flex-start;
                gap: 12px;
            }

            .workflow-notification-icon {
                width: 36px;
                height: 36px;
                flex: 0 0 36px;
                border-radius: 12px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 15px;
            }

            .workflow-notification-content {
                min-width: 0;
                flex: 1 1 auto;
            }

            .workflow-notification-meta {
                display: flex;
                flex-wrap: wrap;
                gap: 6px 10px;
                margin-top: 6px;
                font-size: 12px;
                color: #667085;
            }

            .workflow-notification-type {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                font-size: 12px;
                font-weight: 700;
                color: #475467;
            }

            .workflow-notification-type-dot {
                width: 8px;
                height: 8px;
                border-radius: 999px;
                display: inline-block;
            }

            .workflow-notification-footer {
                padding: 10px 12px;
                border-top: 1px solid #f1f5f9;
                background: #fcfcfd;
            }

            .workflow-notification-footer .btn {
                border-radius: 10px;
            }
        </style>
    @endif
    @stack('css')
</head>

<body class="fixed sidebar-mini @yield('body-class')">
    <!-- Page Loader -->
    <div class="page-loader-wrapper">
        <div class="loader">
            <div class="preloader">
                <div class="spinner-layer pl-green">
                    <div class="circle-clipper left">
                        <div class="circle"></div>
                    </div>
                    <div class="circle-clipper right">
                        <div class="circle"></div>
                    </div>
                </div>
            </div>
            <p>{{ localize('please_wait') }}</p>
        </div>
    </div>
    <!-- #END# Page Loader -->
    <div class="wrapper">
        @include('backend.layouts.sidebar')
        <!-- Page Content  -->
        <div class="content-wrapper">
            <div class="main-content">
                <!--Navbar-->
                <nav class="navbar-custom-menu navbar navbar-expand-xl m-0">
                    <div class="sidebar-toggle-icon" id="sidebarCollapse">sidebar toggle<span></span></div>
                    <!--/.sidebar toggle icon-->
                    <!-- Collapse -->
                    <div class="collapse navbar-collapse" id="navbarSupportedContent">
                        <!-- Toggler -->
                        <button type="button" class="navbar-toggler" data-bs-toggle="collapse"
                            data-bs-target="#navbar-collapse" aria-expanded="true"
                            aria-label="Toggle navigation"><span></span> <span></span></button>
                        <div class="d-flex align-items-center justify-content-between w-100 flex-wrap gap-2 me-xl-3">
                            <a class="nav-link cache-btn ms-xl-3 d-inline-flex" href="{{ route('all_clear') }}"
                                >
                                <span class="me-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26"
                                        viewBox="0 0 26 26" fill="none">
                                        <path
                                            d="M0.925 13.0005C0.925 19.6585 6.342 25.075 13.0005 25.075C13.3074 25.075 13.5555 24.8263 13.5555 24.52C13.5555 24.2136 13.3074 23.965 13.0005 23.965C6.95381 23.965 2.03504 19.0462 2.03504 13.0005C2.03504 6.9543 6.95382 2.03504 13.0005 2.03504C19.0467 2.03504 23.965 6.95429 23.965 13.0005C23.965 13.3068 24.2131 13.5555 24.52 13.5555C24.8269 13.5555 25.075 13.3068 25.075 13.0005C25.075 6.342 19.6585 0.925 13.0005 0.925C6.34199 0.925 0.925 6.34199 0.925 13.0005Z"
                                            fill="#188753" stroke="#188753" stroke-width="0.15" />
                                        <path
                                            d="M7.24125 20.2744H18.7607C19.0677 20.2744 19.3158 20.0257 19.3158 19.7194V12.0386C19.3158 11.7323 19.0677 11.4836 18.7607 11.4836H7.24125C6.93433 11.4836 6.68623 11.7323 6.68623 12.0386V19.7194C6.68623 20.0257 6.93433 20.2744 7.24125 20.2744ZM18.2057 12.5936V19.1644H7.79627V12.5936H18.2057Z"
                                            fill="#188753" stroke="#188753" stroke-width="0.15" />
                                        <path
                                            d="M12.4465 19.7194C12.4465 20.0258 12.6946 20.2745 13.0015 20.2745C13.3084 20.2745 13.5565 20.0258 13.5565 19.7194V17.7994C13.5565 17.493 13.3084 17.2443 13.0015 17.2443C12.6946 17.2443 12.4465 17.493 12.4465 17.7994V19.7194Z"
                                            fill="#188753" stroke="#188753" stroke-width="0.15" />
                                        <path
                                            d="M15.3247 19.7193C15.3247 20.0257 15.5728 20.2744 15.8797 20.2744C16.1866 20.2744 16.4347 20.0257 16.4347 19.7193V15.8792C16.4347 15.5728 16.1866 15.3242 15.8797 15.3242C15.5728 15.3242 15.3247 15.5728 15.3247 15.8792V19.7193Z"
                                            fill="#188753" stroke="#188753" stroke-width="0.15" />
                                        <path
                                            d="M9.56672 19.7184C9.56672 20.0248 9.81482 20.2735 10.1217 20.2735C10.4287 20.2735 10.6768 20.0248 10.6768 19.7184V13.9606C10.6768 13.6543 10.4287 13.4056 10.1217 13.4056C9.81482 13.4056 9.56672 13.6543 9.56672 13.9606V19.7184Z"
                                            fill="#188753" stroke="#188753" stroke-width="0.15" />
                                        <path
                                            d="M7.24027 12.5934H18.7607C19.0677 12.5934 19.3158 12.3448 19.3158 12.0384V11.0784C19.3158 10.243 18.6366 9.5638 17.8017 9.5638H14.5146V5.31959C14.5146 4.48465 13.8349 3.80549 12.9995 3.80549C12.1651 3.80549 11.4859 4.48471 11.4859 5.31959V9.5638H8.19935C7.36444 9.5638 6.68525 10.243 6.68525 11.0784V12.0384C6.68525 12.3448 6.93335 12.5934 7.24027 12.5934ZM18.2057 11.0784V11.4834H7.79529V11.0784C7.79529 10.8552 7.97638 10.6738 8.19935 10.6738H12.0409C12.3479 10.6738 12.596 10.4252 12.596 10.1188V5.31959C12.596 5.0969 12.7771 4.91553 12.9995 4.91553C13.2232 4.91553 13.4046 5.0971 13.4046 5.31959V10.1188C13.4046 10.4252 13.6527 10.6738 13.9596 10.6738H17.8017C18.0246 10.6738 18.2057 10.8552 18.2057 11.0784Z"
                                            fill="#188753" stroke="#188753" stroke-width="0.15" />
                                    </svg>
                                </span>{{ localize('cache_clear') }}
                            </a>
                        </div>
                    </div>
                    @php
                        $correspondenceNotifications = collect();
                        $correspondenceUnreadCount = 0;
                        if (auth()->check() && \Illuminate\Support\Facades\Route::has('correspondence.index')) {
                            $correspondenceUnreadCount = auth()->user()
                                ->unreadNotifications()
                                ->where('type', \App\Notifications\CorrespondenceAssignedNotification::class)
                                ->count();
                            $correspondenceNotifications = auth()->user()
                                ->notifications()
                                ->where('type', \App\Notifications\CorrespondenceAssignedNotification::class)
                                ->latest()
                                ->limit(10)
                                ->get();
                        }

                        $leaveWorkflowNotifications = collect();
                        $leaveWorkflowUnreadCount = 0;
                        if (auth()->check() && \Illuminate\Support\Facades\Route::has('leave.notifications.open')) {
                            $leaveWorkflowUnreadCount = auth()->user()
                                ->unreadNotifications()
                                ->where('type', \App\Notifications\LeaveWorkflowNotification::class)
                                ->count();
                            $leaveWorkflowNotifications = auth()->user()
                                ->notifications()
                                ->where('type', \App\Notifications\LeaveWorkflowNotification::class)
                                ->latest()
                                ->limit(10)
                                ->get();
                        }

                        $attendanceWorkflowNotifications = collect();
                        $attendanceWorkflowUnreadCount = 0;
                        if (auth()->check() && \Illuminate\Support\Facades\Route::has('attendance-adjustments.notifications.open')) {
                            $attendanceWorkflowUnreadCount = auth()->user()
                                ->unreadNotifications()
                                ->where('type', \App\Notifications\AttendanceWorkflowNotification::class)
                                ->count();
                            $attendanceWorkflowNotifications = auth()->user()
                                ->notifications()
                                ->where('type', \App\Notifications\AttendanceWorkflowNotification::class)
                                ->latest()
                                ->limit(10)
                                ->get();
                        }

                        $hasCorrespondenceNotifications = auth()->check() && \Illuminate\Support\Facades\Route::has('correspondence.index');
                        $hasLeaveNotifications = auth()->check() && \Illuminate\Support\Facades\Route::has('leave.notifications.open');
                        $hasAttendanceNotifications = auth()->check() && \Illuminate\Support\Facades\Route::has('attendance-adjustments.notifications.open');
                        $unifiedWorkflowUnreadCount = $correspondenceUnreadCount + $leaveWorkflowUnreadCount + $attendanceWorkflowUnreadCount;
                        $unifiedWorkflowNotifications = collect();

                        if ($hasLeaveNotifications) {
                            $unifiedWorkflowNotifications = $unifiedWorkflowNotifications->merge(
                                $leaveWorkflowNotifications->map(function ($notification) {
                                    return [
                                        'source' => 'leave',
                                        'notification' => $notification,
                                        'link' => route('leave.notifications.open', $notification->id),
                                        'list_link' => route('leave.index'),
                                    ];
                                })
                            );
                        }

                        if ($hasAttendanceNotifications) {
                            $unifiedWorkflowNotifications = $unifiedWorkflowNotifications->merge(
                                $attendanceWorkflowNotifications->map(function ($notification) {
                                    return [
                                        'source' => 'attendance',
                                        'notification' => $notification,
                                        'link' => route('attendance-adjustments.notifications.open', $notification->id),
                                        'list_link' => route('attendance-adjustments.index'),
                                    ];
                                })
                            );
                        }

                        if ($hasCorrespondenceNotifications) {
                            $unifiedWorkflowNotifications = $unifiedWorkflowNotifications->merge(
                                $correspondenceNotifications->map(function ($notification) {
                                    $data = $notification->data ?? [];

                                    return [
                                        'source' => 'correspondence',
                                        'notification' => $notification,
                                        'link' => route('correspondence.notifications.open', $notification->id),
                                        'list_link' => route('correspondence.index'),
                                        'data' => $data,
                                    ];
                                })
                            );
                        }

                        $unifiedWorkflowNotifications = $unifiedWorkflowNotifications
                            ->sortByDesc(function ($item) {
                                return optional($item['notification']->created_at)->timestamp ?? 0;
                            })
                            ->take(12)
                            ->values();

                        $retirementNotification = null;
                        if (
                            auth()->check() &&
                            auth()->user()->can('read_employee') &&
                            \Illuminate\Support\Facades\Route::has('employee-retirements.index')
                        ) {
                            $retirementNotification = app(\Modules\HumanResource\Support\EmployeeRetirementService::class)
                                ->notificationSummary(auth()->user(), 6);
                        }
                    @endphp
                    <div class="navbar-icon d-flex">
                        <ul class="navbar-nav flex-row gap-3 align-items-center">
                            <!--/.dropdown-->
                            @if ($hasCorrespondenceNotifications || $hasLeaveNotifications || $hasAttendanceNotifications)
                                <li class="nav-item dropdown">
                                    <a class="notification-hub-trigger" href="#"
                                        role="button" data-bs-toggle="dropdown" aria-expanded="false"
                                        title="{{ localize('all_notifications', 'ការជូនដំណឹង') }}">
                                        <svg viewBox="0 0 24 24" aria-hidden="true" fill="none">
                                            <path d="M12 3a4 4 0 0 0-4 4v1.17c0 .53-.21 1.04-.59 1.41L6.29 10.7A2 2 0 0 0 5.7 12.1V15l-1.41 1.41A1 1 0 0 0 5 18h14a1 1 0 0 0 .71-1.71L18.3 15v-2.9a2 2 0 0 0-.59-1.4l-1.12-1.13A2 2 0 0 1 16 8.17V7a4 4 0 0 0-4-4Z" fill="currentColor"/>
                                            <path d="M9.5 19a2.5 2.5 0 0 0 5 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                        </svg>
                                        @if ($unifiedWorkflowUnreadCount > 0)
                                            <span class="notification-hub-badge">
                                                {{ $unifiedWorkflowUnreadCount > 99 ? '99+' : $unifiedWorkflowUnreadCount }}
                                            </span>
                                        @endif
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-end shadow p-0 workflow-notification-menu">
                                        <div class="workflow-notification-header border-bottom">
                                            <div class="fw-bold">{{ localize('all_notifications', 'ការជូនដំណឹង') }}</div>
                                            <div class="small text-muted">
                                                {{ localize('unread_total', 'មិនទាន់អានសរុប') }}: {{ $unifiedWorkflowUnreadCount }}
                                            </div>
                                        </div>
                                        <div class="list-group list-group-flush">
                                            @forelse ($unifiedWorkflowNotifications as $entry)
                                                @php
                                                    $notification = $entry['notification'];
                                                    $data = $entry['data'] ?? ($notification->data ?? []);
                                                    $source = $entry['source'];
                                                    $context = trim((string) ($data['context'] ?? ''));
                                                    $title = trim((string) ($data['title'] ?? localize('notification', 'ការជូនដំណឹង')));
                                                    $message = trim((string) ($data['message'] ?? ''));
                                                    $metaPrimary = trim((string) ($data['employee_name'] ?? $data['sender_name'] ?? $data['reference_no'] ?? '-'));
                                                    $metaSecondary = '';
                                                    $metaAudience = trim((string) ($data['audience_label'] ?? ''));
                                                    $stepName = trim((string) ($data['step_name'] ?? ''));
                                                    $contextLabel = '';
                                                    $dateText = '';
                                                    $formatNotificationDate = function (?string $value, bool $includeTime = false): string {
                                                        $value = trim((string) $value);
                                                        if ($value === '') {
                                                            return '';
                                                        }

                                                        try {
                                                            return $includeTime
                                                                ? \Carbon\Carbon::parse($value)->format('d-m-Y H:i')
                                                                : \Carbon\Carbon::parse($value)->format('d-m-Y');
                                                        } catch (\Throwable $e) {
                                                            return $value;
                                                        }
                                                    };

                                                    if ($source === 'leave') {
                                                        $title = match ($context) {
                                                            'submitted' => localize('leave_request_submitted', 'សំណើសុំច្បាប់បានដាក់រួច'),
                                                            'forwarded' => localize('leave_request_forwarded', 'សំណើសុំច្បាប់ត្រូវបានបញ្ជូនបន្ត'),
                                                            'approved' => localize('leave_request_approved', 'សំណើសុំច្បាប់ត្រូវបានអនុម័ត'),
                                                            'rejected' => localize('leave_request_rejected', 'សំណើសុំច្បាប់ត្រូវបានបដិសេធ'),
                                                            'pending_review' => localize('leave_request_waiting_for_you', 'មានសំណើសុំច្បាប់រង់ចាំអ្នក'),
                                                            'handover_assigned' => localize('handover_leave_notification_title', 'អ្នកត្រូវបានកំណត់ជាអ្នកទទួលការងារជំនួស'),
                                                            default => $title,
                                                        };
                                                        $message = match ($context) {
                                                            'submitted' => localize('leave_request_submitted_message', 'សំណើសុំច្បាប់របស់អ្នកបានដាក់រួច និងកំពុងរង់ចាំការពិនិត្យតាមលំហូរការងារ។'),
                                                            'forwarded' => localize('leave_request_forwarded_message', 'សំណើសុំច្បាប់របស់អ្នកត្រូវបានពិនិត្យរួច ហើយបញ្ជូនទៅអ្នកអនុម័តបន្ទាប់។'),
                                                            'approved' => localize('leave_request_approved_message', 'សំណើសុំច្បាប់របស់អ្នកត្រូវបានអនុម័តរួច។'),
                                                            'rejected' => localize('leave_request_rejected_message', 'សំណើសុំច្បាប់របស់អ្នកត្រូវបានបដិសេធ។'),
                                                            'pending_review' => localize('leave_request_waiting_for_you_message', 'មានសំណើសុំច្បាប់មួយកំពុងរង់ចាំការពិនិត្យ ឬអនុម័តពីអ្នក។'),
                                                            default => $message,
                                                        };
                                                        $typeLabel = localize('leave_workflow_short', 'សុំច្បាប់');
                                                        $typeDotStyle = 'background:#3b82f6;';
                                                        $iconClass = match ($context) {
                                                            'submitted' => 'fa-paper-plane',
                                                            'forwarded' => 'fa-share',
                                                            'approved' => 'fa-check',
                                                            'rejected' => 'fa-times',
                                                            'pending_review' => 'fa-hourglass-half',
                                                            'handover_assigned' => 'fa-user-plus',
                                                            default => 'fa-bell',
                                                        };
                                                        $iconStyle = match ($context) {
                                                            'submitted' => 'color:#0d6efd;background:#e7f1ff;',
                                                            'forwarded' => 'color:#0891b2;background:#ecfeff;',
                                                            'approved' => 'color:#198754;background:#e9f7ef;',
                                                            'rejected' => 'color:#dc3545;background:#fdecef;',
                                                            'pending_review' => 'color:#f97316;background:#fff1e6;',
                                                            'handover_assigned' => 'color:#7c3aed;background:#f3e8ff;',
                                                            default => 'color:#6c757d;background:#f1f3f5;',
                                                        };
                                                        $metaSecondary = trim((string) ($data['leave_type_name'] ?? ''));
                                                        $contextLabel = match ($context) {
                                                            'submitted' => localize('notification_context_submitted', 'បានដាក់សំណើ'),
                                                            'forwarded' => localize('notification_context_forwarded', 'បានបញ្ជូនបន្ត'),
                                                            'approved' => localize('notification_context_approved', 'បានអនុម័ត'),
                                                            'rejected' => localize('notification_context_rejected', 'បានបដិសេធ'),
                                                            'pending_review' => localize('notification_context_pending_review', 'កំពុងរង់ចាំសកម្មភាព'),
                                                            'handover_assigned' => localize('notification_context_handover', 'បានកំណត់ជាអ្នកជំនួស'),
                                                            default => '',
                                                        };
                                                        $fromDateText = $formatNotificationDate((string) ($data['from_date'] ?? ''));
                                                        $toDateText = $formatNotificationDate((string) ($data['to_date'] ?? ''));
                                                        $dateText = trim($fromDateText . ' - ' . $toDateText, ' -');
                                                    } elseif ($source === 'attendance') {
                                                        $title = match ($context) {
                                                            'submitted' => localize('attendance_adjustment_submitted', 'សំណើកែប្រែវត្តមានបានដាក់រួច'),
                                                            'pending_review' => localize('attendance_adjustment_waiting_for_you', 'មានសំណើកែប្រែវត្តមានរង់ចាំអ្នក'),
                                                            default => $title,
                                                        };
                                                        $message = match ($context) {
                                                            'submitted' => localize('attendance_adjustment_submitted_message', 'សំណើកែប្រែវត្តមានរបស់អ្នកបានដាក់រួច និងកំពុងរង់ចាំការពិនិត្យតាមលំហូរការងារ។'),
                                                            'pending_review' => localize('attendance_adjustment_waiting_for_you_message', 'មានសំណើកែប្រែវត្តមានថ្មីមួយកំពុងរង់ចាំការពិនិត្យ ឬអនុម័តពីអ្នក។'),
                                                            default => $message,
                                                        };
                                                        $typeLabel = localize('attendance_workflow_short', 'វត្តមាន');
                                                        $typeDotStyle = 'background:#f59e0b;';
                                                        $iconClass = match ($context) {
                                                            'submitted' => 'fa-paper-plane',
                                                            'pending_review' => 'fa-hourglass-half',
                                                            default => 'fa-clock-o',
                                                        };
                                                        $iconStyle = match ($context) {
                                                            'submitted' => 'color:#0d6efd;background:#e7f1ff;',
                                                            'pending_review' => 'color:#f97316;background:#fff1e6;',
                                                            default => 'color:#6c757d;background:#f1f3f5;',
                                                        };
                                                        $contextLabel = match ($context) {
                                                            'submitted' => localize('notification_context_submitted', 'បានដាក់សំណើ'),
                                                            'pending_review' => localize('notification_context_pending_review', 'កំពុងរង់ចាំសកម្មភាព'),
                                                            default => '',
                                                        };
                                                        $dateText = $formatNotificationDate((string) ($data['attendance_date'] ?? ''));
                                                    } else {
                                                        $letterType = trim((string) ($data['letter_type'] ?? ''));
                                                        $subject = trim((string) ($data['subject'] ?? ''));
                                                        $title = match (true) {
                                                            $context === 'delegated' => localize('correspondence_notification_delegated', 'លិខិតត្រូវបានចាត់តាំងមកអ្នក'),
                                                            $context === 'office_commented' => localize('correspondence_notification_office_commented', 'អង្គភាពបានផ្តល់យោបល់រួច សូមអនុប្រធានពិនិត្យបន្ត'),
                                                            $context === 'deputy_reviewed' => localize('correspondence_notification_deputy_reviewed', 'អនុប្រធានបានពិនិត្យរួច សូមប្រធានមន្ទីរសម្រេច'),
                                                            $letterType === 'outgoing' && $context === 'distributed' => localize('correspondence_notification_outgoing', 'លិខិតចេញមកដល់អ្នក ត្រូវចុចទទួល'),
                                                            $context === 'distributed' => localize('correspondence_notification_distributed', 'មានលិខិតត្រូវអនុវត្ត'),
                                                            default => localize('correspondence_notifications', 'ជូនដំណឹងលិខិតរដ្ឋបាល'),
                                                        };
                                                        $message = $subject !== '' ? $subject : trim((string) ($data['registry_no'] ?? ''));
                                                        $typeLabel = localize('correspondence_short', 'លិខិត');
                                                        $typeDotStyle = 'background:#10b981;';
                                                        $iconClass = 'fa-envelope-open-o';
                                                        $iconStyle = 'color:#059669;background:#ecfdf3;';
                                                        $dateText = $formatNotificationDate((string) ($data['assigned_at'] ?? $data['created_at'] ?? ''), true);
                                                    }
                                                    $createdAtText = optional($notification->created_at)->format('d-m-Y H:i');
                                                @endphp
                                                <a href="{{ $entry['link'] }}"
                                                    class="list-group-item list-group-item-action py-3 {{ $notification->read_at ? 'bg-light text-muted' : '' }}">
                                                    <div class="workflow-notification-row">
                                                        <span class="workflow-notification-icon" style="{{ $iconStyle }}">
                                                            <i class="fa {{ $iconClass }}"></i>
                                                        </span>
                                                        <div class="workflow-notification-content">
                                                            <div class="d-flex justify-content-between align-items-start gap-2">
                                                                <div class="fw-semi-bold mb-1">{{ $title }}</div>
                                                                <div class="workflow-notification-status {{ $notification->read_at ? 'read' : 'unread' }}">
                                                                    <span class="workflow-notification-status-dot"></span>{{ $notification->read_at ? localize('read_status', 'បានអាន') : localize('unread_status', 'មិនទាន់អាន') }}
                                                                </div>
                                                            </div>
                                                            @if ($message !== '')
                                                                <div class="small text-dark mb-1">{{ $message }}</div>
                                                            @endif
                                                            @if ($metaAudience !== '' || $contextLabel !== '')
                                                                <div class="workflow-notification-meta mb-1">
                                                                    @if ($metaAudience !== '')
                                                                        <span class="workflow-notification-type">{{ $metaAudience }}</span>
                                                                    @endif
                                                                    @if ($contextLabel !== '')
                                                                        <span>{{ $contextLabel }}</span>
                                                                    @endif
                                                                </div>
                                                            @endif
                                                            @if ($stepName !== '')
                                                                <div class="small text-muted mb-1">{{ $stepName }}</div>
                                                            @endif
                                                            <div class="workflow-notification-meta">
                                                                <span class="workflow-notification-type"><span class="workflow-notification-type-dot" style="{{ $typeDotStyle }}"></span>{{ $typeLabel }}</span>
                                                                @if ($metaPrimary !== '')
                                                                    <span>{{ $metaPrimary }}</span>
                                                                @endif
                                                                @if ($metaSecondary !== '')
                                                                    <span>{{ $metaSecondary }}</span>
                                                                @endif
                                                                @if ($dateText !== '')
                                                                    <span>{{ $dateText }}</span>
                                                                @endif
                                                                @if ($createdAtText !== '')
                                                                    <span>{{ $createdAtText }}</span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                </a>
                                            @empty
                                                <div class="list-group-item small text-muted py-3">
                                                    {{ localize('no_notifications_available', 'មិនទាន់មានការជូនដំណឹងថ្មី') }}
                                                </div>
                                            @endforelse
                                            @if (false)
                                                <div class="list-group-item py-3">
                                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                                        <div>
                                                            <div class="fw-semi-bold">{{ localize('leave_workflow_notifications', 'ដំណឹងលំហូរការងារសុំច្បាប់') }}</div>
                                                            <div class="small text-muted">{{ localize('unread_total', 'Unread total') }}: {{ $leaveWorkflowUnreadCount }}</div>
                                                            <div class="workflow-notification-status {{ $leaveWorkflowUnreadCount > 0 ? 'unread' : 'read' }}"><span class="workflow-notification-status-dot"></span>{{ $leaveWorkflowUnreadCount > 0 ? localize('unread_status', 'មិនទាន់អាន') : localize('read_status', 'បានអាន') }}</div>
                                                        </div>
                                                        <a href="{{ route('leave.index') }}" class="btn btn-sm btn-outline-primary">{{ localize('view_all', 'មើលទាំងអស់') }}</a>
                                                    </div>
                                                </div>
                                            @endif
                                            @if (false)
                                                <div class="list-group-item py-3">
                                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                                        <div>
                                                            <div class="fw-semi-bold">{{ localize('attendance_workflow_notifications', 'ដំណឹងលំហូរការងារវត្តមាន') }}</div>
                                                            <div class="small text-muted">{{ localize('unread_total', 'Unread total') }}: {{ $attendanceWorkflowUnreadCount }}</div>
                                                            <div class="workflow-notification-status {{ $attendanceWorkflowUnreadCount > 0 ? 'unread' : 'read' }}"><span class="workflow-notification-status-dot"></span>{{ $attendanceWorkflowUnreadCount > 0 ? localize('unread_status', 'មិនទាន់អាន') : localize('read_status', 'បានអាន') }}</div>
                                                        </div>
                                                        <a href="{{ route('attendance-adjustments.index') }}" class="btn btn-sm btn-outline-primary">{{ localize('view_all', 'មើលទាំងអស់') }}</a>
                                                    </div>
                                                </div>
                                            @endif
                                            @if (false)
                                                <div class="list-group-item py-3">
                                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                                        <div>
                                                            <div class="fw-semi-bold">{{ localize('correspondence_notifications', 'Correspondence notifications') }}</div>
                                                            <div class="small text-muted">{{ localize('unread_total', 'Unread total') }}: {{ $correspondenceUnreadCount }}</div>
                                                            <div class="workflow-notification-status {{ $correspondenceUnreadCount > 0 ? 'unread' : 'read' }}"><span class="workflow-notification-status-dot"></span>{{ $correspondenceUnreadCount > 0 ? localize('unread_status', 'មិនទាន់អាន') : localize('read_status', 'បានអាន') }}</div>
                                                        </div>
                                                        <a href="{{ route('correspondence.index') }}" class="btn btn-sm btn-outline-primary">{{ localize('view_all', 'មើលទាំងអស់') }}</a>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                        @if (false)
                                        <div class="workflow-notification-footer">
                                            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                                                <div class="d-flex flex-wrap gap-2">
                                                    @if ($hasLeaveNotifications)
                                                        <a href="{{ route('leave.index') }}" class="btn btn-sm btn-outline-primary">{{ localize('leave_workflow_short', 'សុំច្បាប់') }}</a>
                                                    @endif
                                                    @if ($hasAttendanceNotifications)
                                                        <a href="{{ route('attendance-adjustments.index') }}" class="btn btn-sm btn-outline-primary">{{ localize('attendance_workflow_short', 'វត្តមាន') }}</a>
                                                    @endif
                                                    @if ($hasCorrespondenceNotifications)
                                                        <a href="{{ route('correspondence.index') }}" class="btn btn-sm btn-outline-primary">{{ localize('correspondence_short', 'លិខិត') }}</a>
                                                    @endif
                                                </div>
                                                <div class="d-flex flex-wrap gap-2">
                                                    @if ($leaveWorkflowNotifications->isNotEmpty() && \Illuminate\Support\Facades\Route::has('leave.notifications.clear'))
                                                        <form method="POST" action="{{ route('leave.notifications.clear') }}">
                                                            @csrf
                                                            <button class="btn btn-sm btn-outline-secondary" type="submit">{{ localize('clear_leave_notifications', 'Clear ច្បាប់') }}</button>
                                                        </form>
                                                    @endif
                                                    @if ($attendanceWorkflowNotifications->isNotEmpty() && \Illuminate\Support\Facades\Route::has('attendance-adjustments.notifications.clear'))
                                                        <form method="POST" action="{{ route('attendance-adjustments.notifications.clear') }}">
                                                            @csrf
                                                            <button class="btn btn-sm btn-outline-secondary" type="submit">{{ localize('clear_attendance_notifications', 'Clear វត្តមាន') }}</button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        @endif
                                        <div class="workflow-notification-footer">
                                            <div class="d-flex flex-wrap gap-2 justify-content-end align-items-center">
                                                @if (\Illuminate\Support\Facades\Route::has('attendance-adjustments.notifications.make_unread'))
                                                    <form method="POST" action="{{ route('attendance-adjustments.notifications.make_unread') }}">
                                                        @csrf
                                                        <button class="btn btn-sm btn-outline-primary" type="submit">{{ localize('make_unread_again', 'មិនទាន់អានវិញ') }}</button>
                                                    </form>
                                                @endif
                                                @if (\Illuminate\Support\Facades\Route::has('attendance-adjustments.notifications.clear_all'))
                                                    <form method="POST" action="{{ route('attendance-adjustments.notifications.clear_all') }}">
                                                        @csrf
                                                        <button class="btn btn-sm btn-outline-danger" type="submit">{{ localize('delete_all_notifications', 'លុបទាំងអស់') }}</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            @endif
                            @if (false && auth()->check() && \Illuminate\Support\Facades\Route::has('correspondence.index'))
                                <li class="nav-item dropdown">
                                    <a class="position-relative d-inline-flex align-items-center text-dark" href="#"
                                        role="button" data-bs-toggle="dropdown" aria-expanded="false"
                                        title="{{ localize('correspondence_notifications', 'Correspondence notifications') }}">
                                        <i class="fa fa-envelope-o fs-4"></i>
                                        <span
                                            class="position-absolute top-0 start-100 translate-middle badge rounded-pill {{ $correspondenceUnreadCount > 0 ? 'bg-danger' : 'bg-secondary' }}">
                                            {{ $correspondenceUnreadCount > 99 ? '99+' : $correspondenceUnreadCount }}
                                        </span>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-end shadow p-0 workflow-notification-menu">
                                        <div class="px-3 py-2 border-bottom">
                                            <div class="fw-bold">
                                                {{ localize('correspondence_notifications', 'Correspondence notifications') }}
                                            </div>
                                            <div class="small text-muted">
                                                {{ localize('unread_total', 'Unread total') }}:
                                                {{ $correspondenceUnreadCount }}
                                            </div>
                                        </div>

                                        <div class="list-group list-group-flush">
                                            @forelse ($correspondenceNotifications as $notification)
                                                @php
                                                    $data = $notification->data ?? [];
                                                    $context = trim((string) ($data['context'] ?? ''));
                                                    $letterType = trim((string) ($data['letter_type'] ?? ''));
                                                    $subject = trim((string) ($data['subject'] ?? ''));
                                                    $subjectLooksBroken = $subject !== '' && preg_match('/^\?+(\s+\?+)*$/u', $subject);
                                                    $displaySubject = $subjectLooksBroken
                                                        ? localize('subject_not_readable_km', 'ប្រធានបទមិនអាចបង្ហាញបាន')
                                                        : $subject;
                                                    $registryNo = trim((string) ($data['registry_no'] ?? ''));
                                                    $assignedAt = trim((string) ($data['assigned_at'] ?? ''));
                                                    $assignedBy = trim((string) ($data['assigned_by'] ?? ''));
                                                    $targetDepartmentName = trim((string) ($data['target_department_name'] ?? ''));
                                                    $notificationTitle = match (true) {
                                                        $context === 'delegated' => localize('correspondence_notification_delegated', 'លិខិតត្រូវបានចាត់តាំងមកអ្នក'),
                                                        $context === 'office_commented' => localize('correspondence_notification_office_commented', 'អង្គភាពបានផ្តល់យោបល់រួច សូមអនុប្រធានពិនិត្យបន្ត'),
                                                        $context === 'deputy_reviewed' => localize('correspondence_notification_deputy_reviewed', 'អនុប្រធានបានពិនិត្យរួច សូមប្រធានមន្ទីរសម្រេច'),
                                                        $letterType === 'outgoing' && $context === 'distributed' => localize('correspondence_notification_outgoing', 'លិខិតចេញមកដល់អ្នក ត្រូវចុចទទួល'),
                                                        $context === 'distributed' => localize('correspondence_notification_distributed', 'មានលិខិតត្រូវអនុវត្ត'),
                                                        default => localize('correspondence_assigned', 'Correspondence assigned'),
                                                    };
                                                    $link = route('correspondence.notifications.open', $notification->id);
                                                @endphp
                                                <a href="{{ $link }}"
                                                    class="list-group-item list-group-item-action py-2 {{ $notification->read_at ? 'bg-light text-muted' : '' }}">
                                                    <div class="fw-semi-bold mb-1">
                                                        {{ $notificationTitle }}
                                                    </div>
                                                    @if ($displaySubject !== '')
                                                        <div class="small text-dark mb-1" style="line-height: 1.35;">
                                                            {{ $displaySubject }}
                                                        </div>
                                                    @endif
                                                    <div class="small text-muted">
                                                        {{ localize('registry_no', 'លេខចុះបញ្ជី') }}:
                                                        <span class="fw-semibold text-dark">{{ $registryNo !== '' ? $registryNo : '-' }}</span>
                                                        @if ($assignedAt !== '')
                                                            <span class="mx-1">|</span>{{ $assignedAt }}
                                                        @endif
                                                    </div>
                                                    @if ($targetDepartmentName !== '' || $assignedBy !== '')
                                                        <div class="small text-muted mt-1">
                                                            @if ($targetDepartmentName !== '')
                                                                {{ localize('target', 'គោលដៅ') }}: {{ $targetDepartmentName }}
                                                            @endif
                                                            @if ($targetDepartmentName !== '' && $assignedBy !== '')
                                                                <span class="mx-1">|</span>
                                                            @endif
                                                            @if ($assignedBy !== '')
                                                                {{ localize('by', 'ផ្ញើដោយ') }}: {{ $assignedBy }}
                                                            @endif
                                                        @endif
                                                    </div>
                                                </a>
                                            @empty
                                                <div class="list-group-item small text-muted">
                                                    {{ localize('no_correspondence_notifications', 'No new correspondence notifications.') }}
                                                </div>
                                            @endforelse
                                        </div>

                                        <div class="p-2 border-top d-flex justify-content-between gap-2">
                                            <form method="POST" action="{{ route('correspondence.notifications.read') }}">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-secondary" type="submit">
                                                    {{ localize('mark_all_read', 'Mark all read') }}
                                                </button>
                                            </form>
                                            <div class="d-flex gap-2">
                                                @if (\Illuminate\Support\Facades\Route::has('correspondence.notifications.clear'))
                                                    <form method="POST" action="{{ route('correspondence.notifications.clear') }}">
                                                        @csrf
                                                        <button class="btn btn-sm btn-outline-secondary" type="submit">
                                                            {{ localize('clear_notifications', 'Clear') }}
                                                        </button>
                                                    </form>
                                                @endif
                                                <a href="{{ route('correspondence.index') }}"
                                                    class="btn btn-sm btn-outline-primary">
                                                    {{ localize('view_all', 'View all') }}
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            @endif
                            @if (false && auth()->check() && \Illuminate\Support\Facades\Route::has('leave.notifications.open'))
                                <li class="nav-item dropdown">
                                    <a class="position-relative d-inline-flex align-items-center text-dark" href="#"
                                        role="button" data-bs-toggle="dropdown" aria-expanded="false"
                                        title="{{ localize('leave_workflow_notifications', 'ដំណឹងលំហូរការងារសុំច្បាប់') }}">
                                        <i class="fa fa-bell-o fs-4"></i>
                                        <span
                                            class="position-absolute top-0 start-100 translate-middle badge rounded-pill {{ $leaveWorkflowUnreadCount > 0 ? 'bg-danger' : 'bg-secondary' }}">
                                            {{ $leaveWorkflowUnreadCount > 99 ? '99+' : $leaveWorkflowUnreadCount }}
                                        </span>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-end shadow p-0 workflow-notification-menu">
                                        <div class="px-3 py-2 border-bottom">
                                            <div class="fw-bold">
                                                {{ localize('leave_workflow_notifications', 'ដំណឹងលំហូរការងារសុំច្បាប់') }}
                                            </div>
                                            <div class="small text-muted">
                                                {{ localize('unread_total', 'Unread total') }}:
                                                {{ $leaveWorkflowUnreadCount }}
                                            </div>
                                        </div>
                                        <div class="list-group list-group-flush">
                                            @forelse ($leaveWorkflowNotifications as $notification)
                                                @php
                                                    $data = $notification->data ?? [];
                                                    $title = trim((string) ($data['title'] ?? localize('leave_workflow', 'លំហូរការងារសុំច្បាប់')));
                                                    $message = trim((string) ($data['message'] ?? ''));
                                                    $context = trim((string) ($data['context'] ?? ''));
                                                    $title = match ($context) {
                                                        'submitted' => localize('leave_request_submitted', 'សំណើសុំច្បាប់បានដាក់រួច'),
                                                        'forwarded' => localize('leave_request_forwarded', 'សំណើសុំច្បាប់ត្រូវបានបញ្ជូនបន្ត'),
                                                        'approved' => localize('leave_request_approved', 'សំណើសុំច្បាប់ត្រូវបានអនុម័ត'),
                                                        'rejected' => localize('leave_request_rejected', 'សំណើសុំច្បាប់ត្រូវបានបដិសេធ'),
                                                        'pending_review' => localize('leave_request_waiting_for_you', 'មានសំណើសុំច្បាប់រង់ចាំអ្នក'),
                                                        'handover_assigned' => localize('handover_leave_notification_title', 'អ្នកត្រូវបានកំណត់ជាអ្នកទទួលការងារជំនួស'),
                                                        default => $title,
                                                    };
                                                    $message = match ($context) {
                                                        'submitted' => localize('leave_request_submitted_message', 'សំណើសុំច្បាប់របស់អ្នកបានដាក់រួច និងកំពុងរង់ចាំការពិនិត្យតាមលំហូរការងារ។'),
                                                        'forwarded' => localize('leave_request_forwarded_message', 'សំណើសុំច្បាប់របស់អ្នកត្រូវបានពិនិត្យរួច ហើយបញ្ជូនទៅអ្នកអនុម័តបន្ទាប់។'),
                                                        'approved' => localize('leave_request_approved_message', 'សំណើសុំច្បាប់របស់អ្នកត្រូវបានអនុម័តរួច។ សូមបោះពុម្ព និងដាក់ឯកសារជូនរដ្ឋបាល។'),
                                                        'rejected' => localize('leave_request_rejected_message', 'សំណើសុំច្បាប់របស់អ្នកត្រូវបានបដិសេធ។ សូមបើកសំណើដើម្បីមើលមតិយោបល់។'),
                                                        'pending_review' => localize('leave_request_waiting_for_you_message', 'មានសំណើសុំច្បាប់មួយកំពុងរង់ចាំការពិនិត្យ ឬអនុម័តពីអ្នក។'),
                                                        default => $message,
                                                    };
                                                    $iconClass = match ($context) {
                                                        'submitted' => 'fa-paper-plane',
                                                        'forwarded' => 'fa-share',
                                                        'approved' => 'fa-check',
                                                        'rejected' => 'fa-times',
                                                        'pending_review' => 'fa-hourglass-half',
                                                        'handover_assigned' => 'fa-user-plus',
                                                        default => 'fa-bell',
                                                    };
                                                    $iconStyle = match ($context) {
                                                        'submitted' => 'color:#0d6efd;background:#e7f1ff;',
                                                        'forwarded' => 'color:#0dcaf0;background:#e8fbff;',
                                                        'approved' => 'color:#198754;background:#e9f7ef;',
                                                        'rejected' => 'color:#dc3545;background:#fdecef;',
                                                        'pending_review' => 'color:#fd7e14;background:#fff1e6;',
                                                        'handover_assigned' => 'color:#0d6efd;background:#eef4ff;',
                                                        default => 'color:#6c757d;background:#f1f3f5;',
                                                    };
                                                    $leaveTypeName = trim((string) ($data['leave_type_name'] ?? ''));
                                                    $dateRange = trim((string) (($data['from_date'] ?? '') . ' - ' . ($data['to_date'] ?? '')));
                                                    $link = route('leave.notifications.open', $notification->id);
                                                @endphp
                                                <a href="{{ $link }}"
                                                    class="list-group-item list-group-item-action py-2 {{ $notification->read_at ? 'bg-light text-muted' : '' }}">
                                                    <div class="workflow-notification-row">
                                                        <span class="workflow-notification-icon" style="{{ $iconStyle }}">
                                                            <i class="fa {{ $iconClass }}"></i>
                                                        </span>
                                                        <div class="workflow-notification-content">
                                                            <div class="fw-semi-bold mb-1">{{ $title }}</div>
                                                            @if ($message !== '')
                                                                <div class="small text-dark mb-1">{{ $message }}</div>
                                                            @endif
                                                            <div class="small text-muted">
                                                                {{ trim((string) ($data['employee_name'] ?? '-')) }}
                                                                @if ($leaveTypeName !== '')
                                                                    <span class="mx-1">|</span>{{ $leaveTypeName }}
                                                                @endif
                                                            </div>
                                                            @if (trim($dateRange, ' -') !== '')
                                                                <div class="small text-muted mt-1">{{ $dateRange }}</div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </a>
                                            @empty
                                                <div class="list-group-item small text-muted">
                                                    {{ localize('no_leave_workflow_notifications', 'មិនទាន់មានដំណឹងលំហូរការងារសុំច្បាប់ថ្មី។') }}
                                                </div>
                                            @endforelse
                                        </div>
                                        <div class="p-2 border-top d-flex justify-content-between gap-2">
                                                <a href="{{ route('leave.index') }}" class="btn btn-sm btn-outline-primary">
                                                    {{ localize('view_all', 'មើលទាំងអស់') }}
                                                </a>
                                                @if ($leaveWorkflowNotifications->isNotEmpty() && \Illuminate\Support\Facades\Route::has('leave.notifications.clear'))
                                                    <form method="POST" action="{{ route('leave.notifications.clear') }}">
                                                        @csrf
                                                        <button class="btn btn-sm btn-outline-secondary" type="submit">
                                                            {{ localize('clear_notifications', 'Clear') }}
                                                        </button>
                                                    </form>
                                                @endif
                                        </div>
                                    </div>
                                </li>
                            @endif
                            @if (false && auth()->check() && \Illuminate\Support\Facades\Route::has('attendance-adjustments.notifications.open'))
                                <li class="nav-item dropdown">
                                    <a class="position-relative d-inline-flex align-items-center text-dark" href="#"
                                        role="button" data-bs-toggle="dropdown" aria-expanded="false"
                                        title="{{ localize('attendance_workflow_notifications', 'ដំណឹងលំហូរការងារវត្តមាន') }}">
                                        <i class="fa fa-clock-o fs-4"></i>
                                        <span
                                            class="position-absolute top-0 start-100 translate-middle badge rounded-pill {{ $attendanceWorkflowUnreadCount > 0 ? 'bg-danger' : 'bg-secondary' }}">
                                            {{ $attendanceWorkflowUnreadCount > 99 ? '99+' : $attendanceWorkflowUnreadCount }}
                                        </span>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-end shadow p-0 workflow-notification-menu">
                                        <div class="px-3 py-2 border-bottom">
                                            <div class="fw-bold">
                                                {{ localize('attendance_workflow_notifications', 'ដំណឹងលំហូរការងារវត្តមាន') }}
                                            </div>
                                            <div class="small text-muted">
                                                {{ localize('unread_total', 'Unread total') }}:
                                                {{ $attendanceWorkflowUnreadCount }}
                                            </div>
                                        </div>
                                        <div class="list-group list-group-flush">
                                            @forelse ($attendanceWorkflowNotifications as $notification)
                                                @php
                                                    $data = $notification->data ?? [];
                                                    $title = trim((string) ($data['title'] ?? localize('attendance_workflow', 'លំហូរការងារវត្តមាន')));
                                                    $message = trim((string) ($data['message'] ?? ''));
                                                    $context = trim((string) ($data['context'] ?? ''));
                                                    $title = match ($context) {
                                                        'submitted' => localize('attendance_adjustment_submitted', 'សំណើកែប្រែវត្តមានបានដាក់រួច'),
                                                        'pending_review' => localize('attendance_adjustment_waiting_for_you', 'មានសំណើកែប្រែវត្តមានរង់ចាំអ្នក'),
                                                        default => $title,
                                                    };
                                                    $message = match ($context) {
                                                        'submitted' => localize('attendance_adjustment_submitted_message', 'សំណើកែប្រែវត្តមានរបស់អ្នកបានដាក់រួច និងកំពុងរង់ចាំការពិនិត្យតាមលំហូរការងារ។'),
                                                        'pending_review' => localize('attendance_adjustment_waiting_for_you_message', 'មានសំណើកែប្រែវត្តមានថ្មីមួយកំពុងរង់ចាំការពិនិត្យ ឬអនុម័តពីអ្នក។'),
                                                        default => $message,
                                                    };
                                                    $iconClass = match ($context) {
                                                        'submitted' => 'fa-paper-plane',
                                                        'pending_review' => 'fa-hourglass-half',
                                                        default => 'fa-clock-o',
                                                    };
                                                    $iconStyle = match ($context) {
                                                        'submitted' => 'color:#0d6efd;background:#e7f1ff;',
                                                        'pending_review' => 'color:#fd7e14;background:#fff1e6;',
                                                        default => 'color:#6c757d;background:#f1f3f5;',
                                                    };
                                                    $attendanceDate = trim((string) ($data['attendance_date'] ?? ''));
                                                    $link = route('attendance-adjustments.notifications.open', $notification->id);
                                                @endphp
                                                <a href="{{ $link }}"
                                                    class="list-group-item list-group-item-action py-2 {{ $notification->read_at ? 'bg-light text-muted' : '' }}">
                                                    <div class="workflow-notification-row">
                                                        <span class="workflow-notification-icon" style="{{ $iconStyle }}">
                                                            <i class="fa {{ $iconClass }}"></i>
                                                        </span>
                                                        <div class="workflow-notification-content">
                                                            <div class="fw-semi-bold mb-1">{{ $title }}</div>
                                                            @if ($message !== '')
                                                                <div class="small text-dark mb-1">{{ $message }}</div>
                                                            @endif
                                                            <div class="small text-muted">
                                                                {{ trim((string) ($data['employee_name'] ?? '-')) }}
                                                            </div>
                                                            @if ($attendanceDate !== '')
                                                                <div class="small text-muted mt-1">{{ $attendanceDate }}</div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </a>
                                            @empty
                                                <div class="list-group-item small text-muted">
                                                    {{ localize('no_attendance_workflow_notifications', 'មិនទាន់មានដំណឹងលំហូរការងារវត្តមានថ្មី។') }}
                                                </div>
                                            @endforelse
                                        </div>
                                        <div class="p-2 border-top d-flex justify-content-between gap-2">
                                                <a href="{{ route('attendance-adjustments.index') }}" class="btn btn-sm btn-outline-primary">
                                                    {{ localize('view_all', 'មើលទាំងអស់') }}
                                                </a>
                                                @if ($attendanceWorkflowNotifications->isNotEmpty() && \Illuminate\Support\Facades\Route::has('attendance-adjustments.notifications.clear'))
                                                    <form method="POST" action="{{ route('attendance-adjustments.notifications.clear') }}">
                                                        @csrf
                                                        <button class="btn btn-sm btn-outline-secondary" type="submit">
                                                            {{ localize('clear_notifications', 'Clear') }}
                                                        </button>
                                                    </form>
                                                @endif
                                        </div>
                                    </div>
                                </li>
                            @endif
                            @if ($retirementNotification)
                                <li class="nav-item dropdown">
                                    <a class="position-relative d-inline-flex align-items-center text-dark" href="#"
                                        role="button" data-bs-toggle="dropdown" aria-expanded="false"
                                        title="{{ localize('retirement_notifications', 'Retirement notifications') }}">
                                        <i class="fa fa-bell-o fs-4"></i>
                                        @if (($retirementNotification['due_count'] ?? 0) > 0)
                                            <span
                                                class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                                {{ $retirementNotification['due_count'] > 99 ? '99+' : $retirementNotification['due_count'] }}
                                            </span>
                                        @endif
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-end shadow p-0" style="min-width: 340px;">
                                        <div class="px-3 py-2 border-bottom">
                                            <div class="fw-bold">
                                                {{ localize('retirement_notifications', 'Retirement notifications') }}
                                            </div>
                                            <div class="small text-muted">
                                                {{ localize('due_retirement_count_summary', 'Due now') }}:
                                                {{ $retirementNotification['due_count'] ?? 0 }}
                                                |
                                                {{ localize('upcoming_retirement_count_summary', 'Upcoming') }}:
                                                {{ $retirementNotification['upcoming_count'] ?? 0 }}
                                            </div>
                                        </div>

                                        <div class="list-group list-group-flush">
                                            @forelse (($retirementNotification['due_employees'] ?? collect()) as $retirementEmployee)
                                                <div class="list-group-item">
                                                    <div class="fw-semi-bold">
                                                        {{ $retirementEmployee->employee_id }} -
                                                        {{ $retirementEmployee->full_name }}
                                                    </div>
                                                    <div class="small text-muted">
                                                        {{ localize('retirement_date', 'Retirement date') }}:
                                                        {{ $retirementEmployee->retirement_date }}
                                                    </div>
                                                </div>
                                            @empty
                                                <div class="list-group-item small text-muted">
                                                    {{ localize('no_due_retirement_notification', 'No employee is currently due for retirement.') }}
                                                </div>
                                            @endforelse
                                        </div>

                                        <div class="p-2 border-top text-end">
                                            <a href="{{ route('employee-retirements.index') }}"
                                                class="btn btn-sm btn-outline-primary">
                                                {{ localize('view_all', 'View all') }}
                                            </a>
                                        </div>
                                    </div>
                                </li>
                            @endif
                            <li class="nav-item d-none d-md-block">
                                <a href="#" id="btnFullscreen">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="42" height="42"
                                        viewBox="0 0 48 48" fill="none">
                                        <circle cx="24" cy="24" r="24" fill="#F8FAF8" />
                                        <path
                                            d="M33.4394 14H30.9024C30.5927 14 30.3418 14.251 30.3418 14.5606C30.3418 14.8703 30.5927 15.1212 30.9024 15.1212H32.086L30.1889 17.0184C29.9699 17.2373 29.9699 17.5923 30.1889 17.8111C30.2983 17.9206 30.4418 17.9754 30.5853 17.9754C30.7287 17.9754 30.8723 17.9206 30.9816 17.8111L32.8788 15.914V17.0977C32.8788 17.4073 33.1297 17.6583 33.4394 17.6583C33.749 17.6583 34 17.4073 34 17.0977V14.5606C34 14.251 33.749 14 33.4394 14Z"
                                            fill="black" />
                                        <path
                                            d="M18.0184 29.1888L16.1212 31.0859V29.9023C16.1212 29.5926 15.8703 29.3416 15.5606 29.3416C15.251 29.3416 15 29.5926 15 29.9023V32.4393C15 32.7489 15.251 32.9999 15.5606 32.9999H18.0977C18.4073 32.9999 18.6583 32.7489 18.6583 32.4393C18.6583 32.1296 18.4073 31.8787 18.0977 31.8787H16.914L18.8111 29.9815C19.0301 29.7626 19.0301 29.4077 18.8111 29.1888C18.5924 28.9698 18.2372 28.9698 18.0184 29.1888Z"
                                            fill="black" />
                                        <path
                                            d="M16.914 15.1212H18.0977C18.4073 15.1212 18.6583 14.8703 18.6583 14.5606C18.6583 14.251 18.4073 14 18.0977 14H15.5606C15.251 14 15 14.251 15 14.5606V17.0977C15 17.4073 15.251 17.6583 15.5606 17.6583C15.8703 17.6583 16.1212 17.4073 16.1212 17.0977V15.914L18.0184 17.8111C18.1278 17.9206 18.2713 17.9754 18.4148 17.9754C18.5582 17.9754 18.7018 17.9206 18.8111 17.8111C19.0301 17.5923 19.0301 17.2373 18.8111 17.0184L16.914 15.1212Z"
                                            fill="black" />
                                        <path
                                            d="M33.4394 29.3416C33.1297 29.3416 32.8788 29.5926 32.8788 29.9023V31.0859L30.9816 29.1888C30.7629 28.9698 30.4077 28.9698 30.1889 29.1888C29.9699 29.4077 29.9699 29.7626 30.1889 29.9815L32.086 31.8787H30.9024C30.5927 31.8787 30.3418 32.1296 30.3418 32.4393C30.3418 32.7489 30.5927 32.9999 30.9024 32.9999H33.4394C33.749 32.9999 34 32.7489 34 32.4393V29.9023C34 29.5926 33.749 29.3416 33.4394 29.3416Z"
                                            fill="black" />
                                        <path
                                            d="M30.3414 27.2851H18.881C18.2142 27.2851 17.6716 26.7614 17.6716 26.1176V21.1049C17.6716 20.4611 18.2142 19.9374 18.881 19.9374H30.3414C31.0081 19.9374 31.5507 20.4611 31.5507 21.1049V26.1176C31.5507 26.7614 31.0081 27.2851 30.3414 27.2851ZM18.881 21.0179C18.8314 21.0179 18.7909 21.057 18.7909 21.1049V26.1176C18.7909 26.1655 18.8314 26.2046 18.881 26.2046H30.3414C30.391 26.2046 30.4314 26.1655 30.4314 26.1176V21.1049C30.4314 21.057 30.391 21.0179 30.3414 21.0179H18.881Z"
                                            fill="#188753" />
                                    </svg>
                                </a>
                            </li>
                            <li class="nav-item dropdown user-menu">
                                <a class="dropdown-toggle admin-btn me-2 me-sm-3 me-xl-0" href="#" role="button"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                    <img class="admin-img me-1 me-sm-2"
                                        src="{{ Auth::user()->profile_image ? asset('storage/' . Auth::user()->profile_image) : asset('backend/assets/dist/img/avatar.jpg') }}"
                                        alt="{{ localize('profile_picture') }}" />
                                    <span
                                        class="fw-bold fs-15 lh-sm text-start d-none d-md-block">{{ auth()->user() ? ucwords(auth()->user()->full_name) : '' }}<br />
                                        <span
                                            class="fs-12">{{ auth()->user()->user_type_id == 1 ? localize('admin') : localize('staff') }}</span></span>
                                </a>
                                <div class="dropdown-menu new-dropdown shadow">
                                    <div class="dropdown-header d-sm-none">
                                        <a href="" class="header-arrow"><i
                                                class="icon ion-md-arrow-back"></i></a>
                                    </div>
                                    <div class="user-header">
                                        <div class="img-user">
                                            <img src="{{ Auth::user()->profile_image ? asset('storage/' . Auth::user()->profile_image) : asset('backend/assets/dist/img/avatar.jpg') }}"
                                                alt="{{ localize('profile_picture') }}" />
                                        </div>
                                        <!-- img-user -->
                                        <h6>{{ auth()->user() ? ucwords(auth()->user()->full_name) : '' }}</h6>
                                        <span>{{ auth()->user() ? auth()->user()->email : '' }}</span>
                                    </div>
                                    <!-- user-header -->
                                    <div class="mb-3 text-center">
                                        <a href="{{ route('empProfile') }}" class="color_1 fs-16">{{localize('manage_your_account')}}</a>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <form action="{{ route('logout') }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="bg-smoke text-black rounded-3 px-3 py-2 border-0">
                                                {{ localize('sign_out') }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <!--/.dropdown-menu -->
                            </li>
                        </ul>
                        <!--/.navbar nav-->
                    </div>
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                        data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                        aria-expanded="false" aria-label="Toggle navigation">
                        <i class="typcn typcn-th-menu-outline"></i>
                    </button>
                </nav>
                <!--/.navbar-->
                <div class="body-content">
                    {{-- <div class="tile"> --}}

                    @yield('content')

                    {{-- </div> --}}
                </div>
                <!--/.body content-->
            </div>
            <!--/.main content-->
            @include('backend.layouts.footer')
            <!--/.footer content-->
            <div class="overlay"></div>
        </div>
        <!--/.wrapper-->
    </div>
    <!-- Update Profile Modal -->
    <div class="modal fade" id="updateProfile" data-bs-backdrop="static" data-bs-keyboard="false"
        aria-labelledby="staticBackdropLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"> {{ localize('update_profile_information') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form class="userProfileUpdate" action="{{ route('profile.update') }}" method="POST"
                    enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="card-body p-2">
                            <div class="row">
                                <div class="col-md-6 col-sm-12">
                                    <div class="form-group mb-2">
                                        <label>{{ localize('full_name') }}<span class="text-danger">*</span></label>
                                        <input type="text" name="full_name" id="full_name" class="form-control"
                                            value="{{ Auth::user()->full_name }}">
                                        <span class="text-danger error_full_name"></span>
                                    </div>
                                    <div class="form-group mb-2">
                                        <label>{{ localize('email') }}</label>
                                        <input type="email" name="email" id="email" class="form-control"
                                            value="{{ Auth::user()->email }}">
                                        <span class="text-danger error_email"></span>
                                    </div>
                                    <div class="form-group mb-2">
                                        <label>{{ localize('contact_no') }}<span class="text-danger">*</span></label>
                                        <input type="number" name="contact_no" id="contact_no" class="form-control"
                                            value="{{ Auth::user()->contact_no }}">
                                        <span class="text-danger error_contact_no"></span>
                                    </div>
                                </div>
                                <div class="col-md-6 col-sm-12 text-center">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label for="profilePictureUpload" class="label-upload">
                                                <div class="avatar-upload">
                                                    <input type="file" id="profilePictureUpload"
                                                        name="profile_image" class="txt-file">
                                                    <div class="avatar-preview">
                                                        <div id="profilePicturePreview"
                                                            style="background-image: url({{ Auth::user()->profile_image ? asset('storage/' . Auth::user()->profile_image) : asset('backend/assets/dist/img/avatar.jpg') }})">
                                                        </div>
                                                    </div>
                                                </div>
                                            </label>
                                            <label>{{ localize('profile_picture') }}</label>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="signatureUpload" class="label-upload">
                                                <div class="avatar-upload">
                                                    <input type="file" id="signatureUpload" name="signature"
                                                        class="txt-file">
                                                    <div class="signature-preview">
                                                        <div id="signaturePreview"
                                                            style="background-image: url({{ Auth::user()->signature ? asset('storage/' . Auth::user()->signature) : asset('backend/assets/dist/img/nopreview.jpeg') }})">
                                                        </div>
                                                    </div>
                                                </div>
                                            </label>
                                            <label>{{ localize('signature') }}</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-danger" data-bs-dismiss="modal">{{ localize('close') }}</button>
                        <button class="btn btn-success">{{ localize('update') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" data-bs-backdrop="static" data-bs-keyboard="false"
        tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"> {{ localize('change_password') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="changePasswordForm" action="{{ route('profile.changePassword') }}" method="post">
                    @csrf
                    <div class="modal-body">
                        <div class="card-body p-2">
                            <div class="row">
                                <div class="col-md-12 col-sm-12">
                                    <div class="form-group mb-2">
                                        <label>{{ localize('current_password') }}</label>
                                        <input type="password" name="current_password" id="current_password"
                                            class="form-control" required autocomplete="current-password">
                                        <div class="text-danger" id="current_password_error"></div>
                                    </div>
                                    <div class="form-group mb-2">
                                        <label>{{ localize('new_password') }}</label>
                                        <input type="password" name="password" id="password" class="form-control"
                                            required autocomplete="new-password">
                                        <div class="text-danger" id="new_password_error"></div>
                                    </div>
                                    <div class="form-group mb-2">
                                        <label>{{ localize('confirm_password') }}</label>
                                        <input id="password-confirm" type="password" class="form-control"
                                            name="password_confirmation" required autocomplete="new-password">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger"
                            data-bs-dismiss="modal">{{ localize('close') }}</button>
                        <button type="reset" class="btn btn-warning">{{ localize('reset') }}</button>
                        <button class="btn btn-success">{{ localize('update') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @include('backend.layouts.assets.js')
    <script src="{{ asset('backend/assets/menuSearch.js') }}"></script>
    <script src="{{ asset('backend/assets/dist/js/localization.js') }}"></script>
    @stack('js')
    <script>
        @if (session()->has('toastr'))
            toastr.error("{{ session('toastr') }}");
        @endif
    </script>
</body>

</html>
