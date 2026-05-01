@extends('backend.layouts.app')
@section('title', localize('attendance_workflow', 'Attendance Workflow'))

@push('css')
<style>
    .workflow-shell {
        --wf-surface: #ffffff;
        --wf-bg-soft: #f4f7fb;
        --wf-line: #e4e9f2;
        --wf-text-muted: #5f6b7a;
        --wf-navy: #0f2742;
        --wf-cyan: #1fa2a6;
        --wf-green: #1f9d55;
        --wf-orange: #e38b2c;
        --wf-red: #dc3f45;
    }

    .workflow-hero {
        background: linear-gradient(125deg, #0f2742 0%, #1a3c66 45%, #1fa2a6 100%);
        border-radius: 14px;
        color: #fff;
        padding: 1rem 1.2rem;
        box-shadow: 0 10px 24px rgba(8, 25, 43, 0.2);
    }

    .workflow-hero-subtitle {
        color: rgba(255, 255, 255, 0.83);
        font-size: 0.92rem;
    }

    .workflow-stat-card {
        background: var(--wf-surface);
        border: 1px solid var(--wf-line);
        border-radius: 12px;
        box-shadow: 0 6px 18px rgba(17, 40, 66, 0.06);
    }

    .workflow-stat-card .icon-pill {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.95rem;
    }

    .icon-pill.navy {
        background: rgba(15, 39, 66, 0.12);
        color: var(--wf-navy);
    }

    .icon-pill.green {
        background: rgba(31, 157, 85, 0.14);
        color: var(--wf-green);
    }

    .icon-pill.orange {
        background: rgba(227, 139, 44, 0.16);
        color: var(--wf-orange);
    }

    .icon-pill.red {
        background: rgba(220, 63, 69, 0.14);
        color: var(--wf-red);
    }

    .workflow-pane {
        border: 1px solid var(--wf-line);
        border-radius: 12px;
        box-shadow: 0 6px 18px rgba(17, 40, 66, 0.05);
        overflow: hidden;
    }

    .workflow-pane .card-header {
        border-bottom: 1px solid var(--wf-line);
        background: var(--wf-bg-soft);
    }

    .workflow-kpi {
        background: #f8fafc;
        border: 1px solid var(--wf-line);
        border-radius: 10px;
        padding: 0.65rem;
    }

    .workflow-table thead th {
        border-bottom-width: 1px;
        color: #435163;
        font-weight: 600;
        letter-spacing: 0.01em;
    }

    .workflow-table tbody tr td {
        border-color: #edf1f7;
    }

    .workflow-table code {
        background: #f1f5fb;
        border-radius: 6px;
        padding: 0.15rem 0.35rem;
        display: inline-block;
    }

    .workflow-step {
        border: 1px solid var(--wf-line);
        border-radius: 12px;
        padding: 0.95rem;
        height: 100%;
        background: #fff;
        position: relative;
    }

    .workflow-step .step-index {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        background: #eaf1fb;
        color: #23436d;
        margin-bottom: 0.6rem;
    }

    .policy-chip {
        display: inline-flex;
        align-items: center;
        padding: 0.18rem 0.55rem;
        border-radius: 999px;
        background: #eef6ff;
        border: 1px solid #d7e7fb;
        color: #274b72;
        font-size: 0.76rem;
        margin: 0.12rem 0.1rem 0.12rem 0;
    }

    .policy-table td,
    .policy-table th {
        vertical-align: middle;
    }

    .policy-meta {
        color: #607085;
        font-size: 0.82rem;
    }

    .policy-form-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: #3f4f63;
    }

    .department-cascader-shell {
        border: 1px solid #dce5f0;
        border-radius: 10px;
        background: #f9fbff;
        padding: 0.7rem;
    }

    .department-cascader {
        display: grid;
        gap: 0.5rem;
    }

    .department-cascade-level .form-select {
        background: #fff;
        border-color: #cad7e6;
        min-height: 37px;
    }

    .department-selected-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
    }

    .department-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        background: #1f4f87;
        color: #fff;
        border-radius: 999px;
        font-size: 0.75rem;
        padding: 0.24rem 0.4rem 0.24rem 0.62rem;
        line-height: 1.3;
        max-width: 100%;
    }

    .department-chip span {
        max-width: 100%;
        word-break: break-word;
    }

    .department-chip .btn-close {
        font-size: 0.52rem;
        opacity: 0.95;
    }

    .department-chip .btn-close:hover {
        opacity: 1;
    }

    .approval-chain {
        display: grid;
        gap: 0.32rem;
    }

    .approver-line {
        display: flex;
        align-items: flex-start;
        gap: 0.45rem;
        line-height: 1.3;
    }

    .approval-level-chip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 60px;
        padding: 0.12rem 0.45rem;
        font-size: 0.72rem;
        font-weight: 700;
        color: #2f4b69;
        background: #ecf4ff;
        border: 1px solid #d5e5fa;
        border-radius: 999px;
        white-space: nowrap;
    }

    .approver-name {
        font-weight: 600;
        color: #1f2f40;
        word-break: break-word;
    }

    .select2-container--bootstrap4 .select2-results__option .select2-approver-option {
        line-height: 1.25;
    }

    .select2-approver-name {
        font-weight: 600;
        color: #223345;
        font-size: 0.91rem;
    }

    .select2-approver-meta {
        color: #66768a;
        font-size: 0.76rem;
        margin-top: 0.1rem;
    }

    .approval-panel-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        align-items: center;
        justify-content: space-between;
    }

    .approval-summary-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.75rem;
    }

    .approval-summary-card {
        background: linear-gradient(180deg, #ffffff 0%, #f7faff 100%);
        border: 1px solid var(--wf-line);
        border-radius: 12px;
        padding: 0.9rem 1rem;
    }

    .approval-summary-card .summary-label {
        color: #607085;
        font-size: 0.78rem;
        margin-bottom: 0.2rem;
    }

    .approval-summary-card .summary-value {
        color: #0f2742;
        font-size: 1.45rem;
        font-weight: 700;
        line-height: 1.1;
    }

    .policy-card-list {
        display: grid;
        gap: 1rem;
    }

    .policy-card {
        border: 1px solid var(--wf-line);
        border-radius: 14px;
        background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
        box-shadow: 0 10px 24px rgba(17, 40, 66, 0.05);
        overflow: hidden;
    }

    .policy-card-header {
        padding: 1rem 1rem 0.85rem;
        border-bottom: 1px solid #edf2f8;
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        align-items: flex-start;
        justify-content: space-between;
    }

    .policy-card-body {
        padding: 1rem;
    }

    .policy-card-title {
        color: #163250;
        font-size: 1rem;
        font-weight: 700;
        margin-bottom: 0.15rem;
    }

    .policy-card-subtitle {
        color: #67788c;
        font-size: 0.83rem;
    }

    .policy-status-group {
        display: flex;
        flex-wrap: wrap;
        gap: 0.45rem;
        align-items: center;
        justify-content: flex-end;
    }

    .policy-detail-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.15fr) minmax(0, 0.85fr);
        gap: 1rem;
    }

    .policy-detail-card {
        border: 1px solid #e9eff7;
        border-radius: 12px;
        background: #fff;
        padding: 0.9rem;
        height: 100%;
    }

    .policy-detail-label {
        font-size: 0.76rem;
        font-weight: 700;
        color: #5e6e81;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-bottom: 0.5rem;
    }

    .policy-day-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        background: #edf5ff;
        color: #1f4a76;
        border: 1px solid #d8e7fb;
        border-radius: 999px;
        padding: 0.35rem 0.7rem;
        font-size: 0.82rem;
        font-weight: 600;
    }

    .approval-timeline {
        display: grid;
        gap: 0.65rem;
    }

    .approval-timeline-item {
        position: relative;
        display: grid;
        grid-template-columns: 74px minmax(0, 1fr);
        gap: 0.7rem;
        align-items: start;
    }

    .approval-timeline-item:not(:last-child)::after {
        content: "";
        position: absolute;
        left: 35px;
        top: 30px;
        bottom: -12px;
        width: 2px;
        background: linear-gradient(180deg, #d6e5f8 0%, #edf4fc 100%);
    }

    .approval-timeline-badge {
        position: relative;
        z-index: 1;
        min-height: 30px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.22rem 0.5rem;
        border-radius: 999px;
        background: #eaf3ff;
        border: 1px solid #d4e4fa;
        color: #24496f;
        font-size: 0.73rem;
        font-weight: 700;
    }

    .approval-timeline-content {
        border: 1px solid #ebf1f7;
        border-radius: 12px;
        background: #f9fbfe;
        padding: 0.7rem 0.8rem;
    }

    .approval-timeline-content .approver-name {
        display: block;
        margin-bottom: 0.2rem;
    }

    .policy-action-row {
        margin-top: 0.9rem;
        padding-top: 0.9rem;
        border-top: 1px solid #edf2f8;
        display: flex;
        flex-wrap: wrap;
        gap: 0.55rem;
        justify-content: flex-end;
    }

    .workflow-modal-section {
        border: 1px solid #e3ebf5;
        border-radius: 14px;
        background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
        padding: 1rem;
        height: 100%;
    }

    .workflow-modal-section-title {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        font-size: 0.96rem;
        font-weight: 700;
        color: #15314d;
        margin-bottom: 0.9rem;
    }

    .workflow-modal-section-title .section-icon {
        width: 34px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        background: #eaf3ff;
        color: #1f4f87;
    }

    .workflow-mini-preview {
        border: 1px dashed #cad9ea;
        border-radius: 12px;
        background: #f8fbff;
        padding: 0.85rem;
    }

    .preview-chip-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.45rem;
    }

    .preview-empty {
        color: #7b8b9e;
        font-size: 0.83rem;
    }

    .workflow-preview-flow {
        display: grid;
        gap: 0.5rem;
    }

    .workflow-preview-step {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        padding: 0.55rem 0.7rem;
        border-radius: 12px;
        background: #fff;
        border: 1px solid #e5edf7;
    }

    .workflow-preview-step .step-badge {
        min-width: 62px;
        text-align: center;
        border-radius: 999px;
        padding: 0.16rem 0.5rem;
        background: #edf4ff;
        color: #24496f;
        font-size: 0.72rem;
        font-weight: 700;
    }

    .workflow-preview-step .step-text {
        color: #223345;
        font-weight: 600;
        line-height: 1.35;
        word-break: break-word;
    }

    .modal-footer.workflow-modal-footer {
        background: #f8fbff;
        border-top: 1px solid #e6edf7;
    }

    #workflowPolicyModal .modal-dialog {
        max-width: 1080px;
    }

    #workflowPolicyModal .modal-content {
        max-height: calc(100vh - 2rem);
        overflow: hidden;
    }

    #workflowPolicyModal .modal-body {
        max-height: calc(100vh - 170px);
        overflow-y: auto;
        overflow-x: hidden;
    }

    .workflow-modal-section.org-scope-section {
        overflow: visible;
    }

    .wf-org-tree-panel {
        border: 1px solid #d8dee5;
        border-radius: 12px;
        background: #fff;
        max-height: 420px;
        min-height: 260px;
        overflow: auto;
        padding: 12px 14px;
        box-shadow: inset 0 1px 2px rgba(15, 39, 66, 0.04);
    }

    .wf-org-hierarchy-tree,
    .wf-org-hierarchy-tree ul {
        list-style: none;
        margin: 0;
        padding-left: 16px;
    }

    .wf-org-hierarchy-tree li {
        position: relative;
        margin: 2px 0;
        padding-left: 14px;
    }

    .wf-org-hierarchy-tree li::before {
        content: '';
        position: absolute;
        top: -6px;
        left: 0;
        width: 12px;
        height: 16px;
        border-left: 1px dotted #9aa8b6;
        border-bottom: 1px dotted #9aa8b6;
    }

    .wf-org-hierarchy-tree li::after {
        content: '';
        position: absolute;
        left: 0;
        top: 10px;
        bottom: -8px;
        border-left: 1px dotted #9aa8b6;
    }

    .wf-org-hierarchy-tree li:last-child::after {
        display: none;
    }

    .wf-org-hierarchy-tree > li {
        padding-left: 6px;
    }

    .wf-org-hierarchy-tree > li::before,
    .wf-org-hierarchy-tree > li::after {
        display: none;
    }

    .wf-org-tree-row {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        max-width: 100%;
    }

    .wf-org-tree-toggle {
        width: 16px;
        height: 16px;
        border: 1px solid #7f8da0;
        background: #fff;
        color: #1f2f40;
        padding: 0;
        line-height: 14px;
        text-align: center;
        border-radius: 2px;
        font-size: 12px;
        cursor: pointer;
    }

    .wf-org-tree-toggle:hover {
        background: #ecf3f9;
        border-color: #47627f;
    }

    .wf-org-tree-toggle-placeholder {
        width: 16px;
        height: 16px;
        display: inline-block;
    }

    .wf-org-tree-item > .wf-org-hierarchy-tree {
        display: none;
    }

    .wf-org-tree-item.is-open > .wf-org-hierarchy-tree {
        display: block;
    }

    .wf-org-tree-node {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 6px;
        border-radius: 8px;
        font-size: 13px;
        color: #1f2f40;
        border: 0;
        background: transparent;
        text-align: left;
        cursor: pointer;
        max-width: calc(100% - 24px);
        flex-wrap: wrap;
    }

    .wf-org-tree-node:hover:not(:disabled) {
        background: #eef4f9;
        color: #0f5e95;
    }

    .wf-org-tree-node.is-active {
        background: #1f75b8;
        color: #fff;
    }

    .wf-org-tree-node:disabled {
        opacity: 0.55;
        cursor: not-allowed;
    }

    .wf-org-tree-icon {
        color: #8a7a12;
        font-size: 12px;
        width: 14px;
        text-align: center;
    }

    .wf-org-tree-order {
        min-width: 22px;
        padding: 0 4px;
        border: 1px solid #c3d0dc;
        border-radius: 10px;
        text-align: center;
        font-size: 11px;
        color: #45607a;
        background: #f1f6fb;
        line-height: 16px;
    }

    .wf-org-tree-name {
        font-weight: 600;
        line-height: 1.3;
        word-break: break-word;
    }

    .wf-org-tree-type {
        color: #6b7785;
        font-size: 11px;
    }

    .wf-org-tree-node.is-active .wf-org-tree-icon {
        color: #ffe89d;
    }

    .wf-org-tree-node.is-active .wf-org-tree-order {
        border-color: #d6ebff;
        color: #e9f6ff;
        background: #2d86ca;
    }

    .wf-org-tree-node.is-active .wf-org-tree-type {
        color: #d6ebff;
    }

    @media (max-width: 991.98px) {
        .approval-level-chip {
            min-width: 54px;
            font-size: 0.68rem;
        }

        .policy-table {
            min-width: 760px;
        }

        .department-cascader-shell {
            padding: 0.6rem;
        }

        .approval-summary-grid,
        .policy-detail-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 767.98px) {
        .policy-card-header,
        .policy-action-row,
        .policy-status-group,
        .approval-panel-toolbar {
            justify-content: flex-start;
        }

        .approval-timeline-item {
            grid-template-columns: 1fr;
        }

        .approval-timeline-item:not(:last-child)::after {
            display: none;
        }
    }
</style>
@endpush

@section('content')
    @include('humanresource::attendance_header')

    <div class="workflow-shell">
        <div class="workflow-hero mb-3 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <h5 class="mb-1 fw-semibold">{{ localize('attendance_workflow', 'Attendance Workflow') }}</h5>
                <div class="workflow-hero-subtitle">
                    {{ localize('workflow_overview_hint', 'Overview of attendance activity, device status, and exception handling') }}
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-light text-dark">
                    {{ localize('last_refreshed', 'Last refreshed') }}: {{ now()->format('H:i:s') }}
                </span>
                <a href="{{ request()->fullUrl() }}" class="btn btn-sm btn-light">
                    <i class="fas fa-sync-alt me-1"></i>{{ localize('refresh_now', 'Refresh') }}
                </a>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="workflow-stat-card card att-card h-100">
                <div class="card-body">
                    <span class="icon-pill navy mb-2"><i class="fas fa-users"></i></span>
                    <div class="text-muted small">{{ localize('employees_in_scope', 'បុគ្គលិកក្នុងសិទ្ធិ') }}</div>
                    <div class="fs-2 fw-bold">{{ $workflow['employees_in_scope'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="workflow-stat-card card att-card h-100">
                <div class="card-body">
                    <span class="icon-pill green mb-2"><i class="fas fa-check-circle"></i></span>
                    <div class="text-muted small">{{ localize('today_present', 'វត្តមានថ្ងៃនេះ') }}</div>
                    <div class="fs-2 fw-bold text-success">{{ $workflow['today_present'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="workflow-stat-card card att-card h-100">
                <div class="card-body">
                    <span class="icon-pill orange mb-2"><i class="fas fa-user-clock"></i></span>
                    <div class="text-muted small">{{ localize('today_absent', 'អវត្តមានថ្ងៃនេះ') }}</div>
                    <div class="fs-2 fw-bold text-warning">{{ $workflow['today_absent'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="workflow-stat-card card att-card h-100">
                <div class="card-body">
                    <span class="icon-pill red mb-2"><i class="fas fa-exclamation-triangle"></i></span>
                    <div class="text-muted small">{{ localize('pending_adjustments', 'សំណើកែប្រែរង់ចាំ') }}</div>
                    <div class="fs-2 fw-bold text-danger">{{ $workflow['pending_adjustments'] }}</div>
                    <div class="small text-muted mt-1">
                        {{ localize('today_exceptions', 'ករណីខុសថ្ងៃនេះ') }}: {{ $workflow['today_exceptions'] }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="workflow-pane card att-card h-100">
                <div class="card-header fw-semibold">{{ localize('device_approval_flow', 'Device Approval Flow') }}</div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-4 text-center">
                            <div class="workflow-kpi">
                            <div class="small text-muted">Pending</div>
                            <div class="fs-4 fw-bold text-warning">{{ $workflow['device_pending'] }}</div>
                            </div>
                        </div>
                        <div class="col-4 text-center">
                            <div class="workflow-kpi">
                            <div class="small text-muted">Active</div>
                            <div class="fs-4 fw-bold text-success">{{ $workflow['device_active'] }}</div>
                            </div>
                        </div>
                        <div class="col-4 text-center">
                            <div class="workflow-kpi">
                            <div class="small text-muted">Blocked</div>
                            <div class="fs-4 fw-bold text-danger">{{ $workflow['device_blocked'] }}</div>
                            </div>
                        </div>
                    </div>
                    <a href="{{ route('role.user.list') }}" class="btn btn-outline-primary">
                        {{ localize('open_device_management', 'Open Device Management') }}
                    </a>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="workflow-pane card h-100">
                <div class="card-header fw-semibold">{{ localize('device_connectivity', 'Device Connectivity') }}</div>
                <div class="card-body">
                    <div class="row g-3 mb-2">
                        <div class="col-6 text-center">
                            <div class="workflow-kpi">
                            <div class="small text-muted">Online</div>
                            <div class="fs-4 fw-bold text-success">{{ $workflow['device_online'] }}</div>
                            </div>
                        </div>
                        <div class="col-6 text-center">
                            <div class="workflow-kpi">
                            <div class="small text-muted">Offline</div>
                            <div class="fs-4 fw-bold text-secondary">{{ $workflow['device_offline'] }}</div>
                            </div>
                        </div>
                    </div>
                    <p class="text-muted small mb-0">
                        {{ localize('device_online_hint', 'Online means active device login within the last') }}
                        <strong>{{ $workflow['device_online_window_minutes'] }}</strong>
                        {{ localize('minutes', 'minutes') }}.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="workflow-pane card h-100">
                <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                    <span>{{ localize('recent_device_activity', 'Recent Device Activity') }}</span>
                    <span class="badge bg-light text-dark">{{ $workflow['device_recent_activity']->count() }}</span>
                </div>
                <div class="table-responsive">
                    <table class="workflow-table table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ localize('employee', 'Employee') }}</th>
                                <th>{{ localize('device', 'Device') }}</th>
                                <th>{{ localize('last_login', 'Last Login') }}</th>
                                <th class="text-center">{{ localize('state', 'State') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($workflow['device_recent_activity'] as $device)
                                @php
                                    $status = (string) $device->status;
                                    $stateLabel = ucfirst($status);
                                    $stateClass = 'bg-secondary';

                                    if ($status === 'active' && $device->is_online) {
                                        $stateLabel = localize('online', 'Online');
                                        $stateClass = 'bg-success';
                                    } elseif ($status === 'active') {
                                        $stateLabel = localize('offline', 'Offline');
                                        $stateClass = 'bg-warning text-dark';
                                    } elseif ($status === 'pending') {
                                        $stateLabel = localize('pending', 'Pending');
                                        $stateClass = 'bg-warning text-dark';
                                    } elseif ($status === 'blocked') {
                                        $stateLabel = localize('blocked', 'Blocked');
                                        $stateClass = 'bg-danger';
                                    }
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ optional($device->user)->full_name ?? optional($device->user)->email ?? '-' }}</div>
                                        <div class="text-muted small">{{ optional(optional($device->user)->employee)->employee_id ?? optional($device->user)->email ?? '-' }}</div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold small">{{ $device->device_name ?: localize('unnamed_device', 'Unnamed device') }}</div>
                                        <code class="small text-muted">{{ \Illuminate\Support\Str::limit($device->device_id, 30) }}</code>
                                        @if($device->platform)
                                            <div><span class="badge bg-info text-dark mt-1">{{ strtoupper($device->platform) }}</span></div>
                                        @endif
                                    </td>
                                    <td>
                                        @if($device->last_login_at)
                                            <div>{{ $device->last_login_at->format('Y-m-d H:i') }}</div>
                                            <small class="text-muted">{{ $device->last_login_at->diffForHumans() }}</small>
                                        @else
                                            <span class="text-muted">{{ localize('no_login_yet', 'No login yet') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="badge {{ $stateClass }}">{{ $stateLabel }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        {{ localize('no_device_activity', 'No device activity found yet.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="workflow-pane card h-100">
                <div class="card-header fw-semibold">{{ localize('qr_workflow', 'QR Workflow') }}</div>
                <div class="card-body">
                    <p class="mb-2 text-muted">{{ localize('qr_units_available', 'Available units for QR') }}: <strong>{{ $workflow['qr_units'] }}</strong></p>
                    <a href="{{ route('attendances.qrCreate') }}" class="btn btn-outline-success me-2">{{ localize('generate_qr', 'Generate QR') }}</a>
                    <a href="{{ route('attendances.exceptions', ['date' => $today]) }}" class="btn btn-outline-danger mt-2 mt-md-0">{{ localize('view_exceptions', 'View Exceptions') }}</a>
                </div>
            </div>
        </div>
    </div>

    <div class="workflow-pane card mb-2">
        <div class="card-header fw-semibold">{{ localize('attendance_workflow_steps', 'លំដាប់លំហូរការងារ') }}</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="workflow-step">
                        <div class="step-index">1</div>
                        <div class="fw-semibold mb-2">{{ localize('device_request', 'ស្នើសុំឧបករណ៍') }}</div>
                        <div class="text-muted small">{{ localize('device_request_desc', 'អ្នកប្រើ login លើកដំបូង ហើយឧបករណ៍ចូល pending') }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="workflow-step">
                        <div class="step-index">2</div>
                        <div class="fw-semibold mb-2">{{ localize('manager_approval', 'អនុម័ត') }}</div>
                        <div class="text-muted small">{{ localize('manager_approval_desc', 'អ្នកគ្រប់គ្រង approve/block/reject ឧបករណ៍') }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="workflow-step">
                        <div class="step-index">3</div>
                        <div class="fw-semibold mb-2">{{ localize('attendance_capture', 'កត់វត្តមាន') }}</div>
                        <div class="text-muted small">{{ localize('attendance_capture_desc', 'Manual, Monthly, Missing ឬ QR តាមសិទ្ធិអង្គភាព') }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="workflow-step">
                        <div class="step-index">4</div>
                        <div class="fw-semibold mb-2">{{ localize('exception_review', 'ពិនិត្យករណីខុស') }}</div>
                        <div class="text-muted small">{{ localize('exception_review_desc', 'ត្រួតពិនិត្យ missing attendance និង unpaired punches') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="mt-4">
        <div class="mb-2">
            <h6 class="mb-1 fw-semibold">{{ localize('attendance_rules', 'ច្បាប់') }}</h6>
            <div class="text-muted small">{{ localize('attendance_rules_hint', 'Configure attendance approval flow rules and conditions here.') }}</div>
        </div>
        @include('humanresource::attendance.partials.approval-flow-panel')
    </div>
@endsection
