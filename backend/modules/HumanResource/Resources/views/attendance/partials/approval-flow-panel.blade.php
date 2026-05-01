@php
    $totalPolicies = count($approvalPolicies ?? []);
    $activePolicies = collect($approvalPolicies ?? [])->where('is_active', true)->count();
    $requestTypeCount = collect($approvalPolicies ?? [])->pluck('request_type_key')->filter()->unique()->count();
@endphp

<div class="workflow-pane card mb-4">
    <div class="card-header fw-semibold">
        <div class="approval-panel-toolbar">
            <div>
                <div>{{ localize('attendance_approval_flow_panel', 'áž•áŸ’áž‘áž¶áŸ†áž„áž‚áŸ’ážšáž”áŸ‹áž‚áŸ’ážšáž„ Flow áž¢áŸ’áž“áž€áž¢áž“áž»áž˜áŸážážœážáŸ’ážáž˜áž¶áž“ážáž¶áž˜áž›áž€áŸ’ážážážŽáŸ’ážŒ') }}</div>
                <div class="small text-muted fw-normal mt-1">
                    {{ localize('attendance_approval_flow_hint', 'áž€áŸ†ážŽážáŸ‹áž¢áž„áŸ’áž‚áž—áž¶áž– áž›áž€áŸ’ážážážŽáŸ’ážŒážáŸ’áž„áŸƒ áž“áž·áž„áž¢áŸ’áž“áž€áž¢áž“áž»áž˜áŸážáž‡áž¶áž”áž»áž‚áŸ’áž‚áž›ážáž¶áž˜áž›áŸ†ážŠáž¶áž”áŸ‹ážáŸ’áž“áž¶áž€áŸ‹') }}
                </div>
            </div>
            <button
                type="button"
                class="btn btn-success btn-sm"
                id="create-workflow-policy-btn"
                data-bs-toggle="modal"
                data-bs-target="#workflowPolicyModal"
            >
                <i class="fa fa-plus me-1"></i>{{ localize('add_condition', 'áž”áž“áŸ’ážáŸ‚áž˜áž›áž€áŸ’ážážážŽáŸ’ážŒ') }}
            </button>
        </div>
    </div>

    <div class="card-body">
        <div class="approval-summary-grid mb-4">
            <div class="approval-summary-card">
                <div class="summary-label">{{ localize('total_conditions', 'áž›áž€áŸ’ážážážŽáŸ’ážŒážŸážšáž»áž”') }}</div>
                <div class="summary-value">{{ $totalPolicies }}</div>
            </div>
            <div class="approval-summary-card">
                <div class="summary-label">{{ localize('active_conditions', 'áž›áž€áŸ’ážážážŽáŸ’ážŒáž€áŸ†áž–áž»áž„áž”áŸ’ážšáž¾') }}</div>
                <div class="summary-value text-success">{{ $activePolicies }}</div>
            </div>
            <div class="approval-summary-card">
                <div class="summary-label">{{ localize('request_types_covered', 'áž”áŸ’ážšáž—áŸáž‘ážŸáŸ†ážŽáž¾ážŠáŸ‚áž›áž”áž¶áž“áž‚áŸ’ážšáž”ážŠážŽáŸ’ážáž”áŸ‹') }}</div>
                <div class="summary-value text-primary">{{ $requestTypeCount }}</div>
            </div>
        </div>

        <div class="policy-card-list">
            @forelse($approvalPolicies as $policy)
                <div class="policy-card">
                    <div class="policy-card-header">
                        <div>
                            <div class="policy-card-title">{{ $policy['request_type_label'] }}</div>
                            <div class="policy-card-subtitle">
                                {{ $policy['name'] }}
                            </div>
                        </div>
                        <div class="policy-status-group">
                            @if($policy['is_active'])
                                <span class="badge bg-success">{{ localize('active', 'Active') }}</span>
                            @else
                                <span class="badge bg-secondary">{{ localize('inactive', 'Inactive') }}</span>
                            @endif
                            <span class="policy-day-pill">
                                <i class="far fa-calendar-alt"></i>
                                {{ $policy['day_condition'] }}
                            </span>
                        </div>
                    </div>

                    <div class="policy-card-body">
                        <div class="policy-detail-grid">
                            <div class="policy-detail-card">
                                <div class="policy-detail-label">{{ localize('organization_unit', 'áž¢áž„áŸ’áž‚áž—áž¶áž–') }}</div>
                                @if(!empty($policy['department_labels']))
                                    <div>
                                        @foreach($policy['department_labels'] as $label)
                                            <span class="policy-chip">{{ $label }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="text-muted">{{ localize('no_unit_selected', 'áž˜áž·áž“áž‘áž¶áž“áŸ‹áž‡áŸ’ážšáž¾ážŸáž¢áž„áŸ’áž‚áž—áž¶áž–') }}</div>
                                @endif

                                @if(!empty($policy['description']))
                                    <div class="policy-detail-label mt-3">{{ localize('description', 'áž€áž¶ážšáž–áž·áž–ážŽáŸŒáž“áž¶') }}</div>
                                    <div class="policy-meta">{{ $policy['description'] }}</div>
                                @endif
                            </div>

                            <div class="policy-detail-card">
                                <div class="policy-detail-label">{{ localize('approval_chain', 'áž›áŸ†ážŠáž¶áž”áŸ‹áž¢áŸ’áž“áž€áž¢áž“áž»áž˜áŸáž') }}</div>
                                <div class="approval-timeline">
                                    @foreach(($policy['approver_steps'] ?? []) as $step)
                                        <div class="approval-timeline-item">
                                            <span class="approval-timeline-badge">{{ $step['stage_label'] ?? "" }}</span>
                                            <div class="approval-timeline-content">
                                                <span class="approver-name">{{ $step['label'] ?? "-" }}</span>
                                                <div class="policy-meta">{{ $step['stage_hint'] ?? "" }}</div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="policy-action-row">
                            <button
                                type="button"
                                class="btn btn-outline-primary btn-sm edit-workflow-policy-btn"
                                data-bs-toggle="modal"
                                data-bs-target="#workflowPolicyModal"
                                data-policy='@json($policy)'
                            >
                                <i class="fa fa-edit me-1"></i>{{ localize('edit', 'áž€áŸ‚ážŸáž˜áŸ’ážšáž½áž›') }}
                            </button>
                            <form
                                method="POST"
                                action="{{ route('attendances.workflow_policies.destroy', $policy['id']) }}"
                                onsubmit="return confirm('{{ localize('delete_this_policy_confirm', 'ážáž¾áž¢áŸ’áž“áž€áž…áž„áŸ‹áž›áž»áž”áž›áž€áŸ’ážážážŽáŸ’ážŒáž“áŸáŸ‡áž˜áŸ‚áž“áž‘áŸ?') }}');"
                            >
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                    <i class="fa fa-trash me-1"></i>{{ localize('delete', 'áž›áž»áž”') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center text-muted py-4 border rounded-3 bg-light">
                    {{ localize('no_approval_flow_configured', 'áž˜áž·áž“áž‘áž¶áž“áŸ‹áž˜áž¶áž“áž€áž¶ážšáž€áŸ†ážŽážáŸ‹ Flow áž¢áž“áž»áž˜áŸážážœážáŸ’ážáž˜áž¶áž“') }}
                </div>
            @endforelse
        </div>
    </div>
</div>

<div class="modal fade" id="workflowPolicyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="workflowPolicyForm" method="POST" action="{{ route('attendances.workflow_policies.store') }}">
                @csrf
                <input type="hidden" name="_method" id="workflowPolicyMethod" value="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="workflowPolicyModalTitle">{{ localize('add_condition', 'áž”áž“áŸ’ážáŸ‚áž˜áž›áž€áŸ’ážážážŽáŸ’ážŒ') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-lg-7">
                            <div class="workflow-modal-section">
                                <div class="workflow-modal-section-title">
                                    <span class="section-icon"><i class="fas fa-sliders-h"></i></span>
                                    <span>{{ localize('policy_setup', 'áž€áž¶ážšáž€áŸ†ážŽážáŸ‹áž›áž€áŸ’ážážážŽáŸ’ážŒ') }}</span>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="policy-form-label">{{ localize('request_type', 'áž”áŸ’ážšáž—áŸáž‘ážŸáŸ†ážŽáž¾') }}</label>
                                        <select class="form-select" name="request_type_key" id="wf_request_type_key" required>
                                            @foreach($requestTypeOptions as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="policy-form-label">{{ localize('policy_name', 'ážˆáŸ’áž˜áŸ„áŸ‡áž›áž€áŸ’ážážážŽáŸ’ážŒ') }}</label>
                                        <input type="text" class="form-control" name="name" id="wf_name" required maxlength="190">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="policy-form-label">{{ localize('min_days', 'ážáŸ’áž„áŸƒáž¢áž”áŸ’áž”áž”ážšáž˜áž¶') }}</label>
                                        <input type="number" step="0.5" min="0" class="form-control" name="min_days" id="wf_min_days">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="policy-form-label">{{ localize('max_days', 'ážáŸ’áž„áŸƒáž¢ážáž·áž”ážšáž˜áž¶') }}</label>
                                        <input type="number" step="0.5" min="0" class="form-control" name="max_days" id="wf_max_days">
                                    </div>
                                    <input type="hidden" name="priority" id="wf_priority" value="100">

                                    <div class="col-12 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox" role="switch" id="wf_is_active" name="is_active" value="1" checked>
                                            <label class="form-check-label" for="wf_is_active">{{ localize('active', 'Active') }}</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-5">
                            <div class="workflow-modal-section">
                                <div class="workflow-modal-section-title">
                                    <span class="section-icon"><i class="fas fa-eye"></i></span>
                                    <span>{{ localize('quick_preview', 'áž˜áž¾áž›ážŸáž„áŸ’ážáŸáž”') }}</span>
                                </div>

                                <div class="workflow-mini-preview mb-3">
                                    <div class="policy-detail-label">{{ localize('selected_units', 'áž¢áž„áŸ’áž‚áž—áž¶áž–ážŠáŸ‚áž›áž”áž¶áž“áž‡áŸ’ážšáž¾ážŸ') }}</div>
                                    <div id="wf_selected_departments_preview" class="preview-chip-list">
                                        <span class="preview-empty">{{ localize('no_unit_selected', 'áž˜áž·áž“áž‘áž¶áž“áŸ‹áž‡áŸ’ážšáž¾ážŸáž¢áž„áŸ’áž‚áž—áž¶áž–') }}</span>
                                    </div>
                                </div>

                                <div class="workflow-mini-preview">
                                    <div class="policy-detail-label">{{ localize('approval_flow_preview', 'áž›áŸ†ážŠáž¶áž”áŸ‹áž¢áŸ’áž“áž€áž¢áž“áž»áž˜áŸáž') }}</div>
                                    <div id="wf_approver_preview" class="workflow-preview-flow">
                                        <div class="workflow-preview-step">
                                            <span class="step-badge">{{ localize('level_1', 'áž‡áž¶áž“áŸ‹áž‘áž¸áŸ¡') }}</span>
                                            <span class="step-text">{{ localize('select_approver', 'áž‡áŸ’ážšáž¾ážŸáž¢áŸ’áž“áž€áž¢áž“áž»áž˜áŸáž') }}</span>
                                        </div>
                                        <div class="workflow-preview-step">
                                            <span class="step-badge">{{ localize('final_level', 'áž…áž»áž„áž€áŸ’ážšáŸ„áž™') }}</span>
                                            <span class="step-text">{{ localize('select_approver', 'áž‡áŸ’ážšáž¾ážŸáž¢áŸ’áž“áž€áž¢áž“áž»áž˜áŸáž') }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="workflow-modal-section org-scope-section">
                                <div class="workflow-modal-section-title">
                                    <span class="section-icon"><i class="fas fa-sitemap"></i></span>
                                    <span>{{ localize('organization_scope', 'áž¢áž„áŸ’áž‚áž—áž¶áž– áž“áž·áž„ážœáž·ážŸáž¶áž›áž—áž¶áž–') }}</span>
                                </div>

                                <label class="policy-form-label">{{ localize('organization_unit', 'áž¢áž„áŸ’áž‚áž—áž¶áž–') }}</label>
                                <div class="mb-2">
                                    @if (!empty($orgUnitTree))
                                        <div class="wf-org-tree-panel mb-3" id="wf-org-tree-panel">
                                            @include('humanresource::attendance.partials.org-unit-selector-tree', [
                                                'nodes' => $orgUnitTree,
                                                'selected_org_unit_id' => 0,
                                            ])
                                        </div>
                                    @endif
                                </div>

                                <select class="form-select d-none" name="department_ids[]" id="wf_department_ids" multiple required>
                                    @foreach($orgUnitOptions as $option)
                                        @php
                                            $optionId = (int) data_get($option, 'id', 0);
                                            $optionLabel = trim((string) (data_get($option, 'path') ?: data_get($option, 'name') ?: ('#' . $optionId)));
                                            $optionParentId = data_get($option, 'parent_id');
                                        @endphp
                                        @if($optionId > 0)
                                            <option value="{{ $optionId }}" data-parent-id="{{ $optionParentId !== null ? (int) $optionParentId : '' }}">{{ $optionLabel }}</option>
                                        @endif
                                    @endforeach
                                </select>

                                <small class="text-muted">
                                    {{ localize('select_units_parent_child_hint', 'áž‡áŸ’ážšáž¾ážŸáž¢áž„áŸ’áž‚áž—áž¶áž–áž˜áŸ áž áž¾áž™áž”áž¾áž˜áž¶áž“áž¢áž„áŸ’áž‚áž—áž¶áž–áž€áž¼áž“ ážŸáž¼áž˜áž‡áŸ’ážšáž¾ážŸáž”áž“áŸ’áž') }}
                                </small>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="workflow-modal-section">
                                <div class="workflow-modal-section-title">
                                    <span class="section-icon"><i class="fas fa-user-check"></i></span>
                                    <span>{{ localize('approver_assignment', 'áž€áž¶ážšáž€áŸ†ážŽážáŸ‹áž¢áŸ’áž“áž€áž¢áž“áž»áž˜áŸáž') }}</span>
                                </div>

                                <div class="alert alert-light border small py-2 mb-3">
                                    <strong>Flow:</strong>
                                    1 person = final approver, 2 people = first comments then second approves, 3 people = first and second comment then third approves. Select from left to right.
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="policy-form-label">Step 1</label>
                                        <select class="form-select" name="first_approver_user_id" id="wf_first_approver_user_id">
                                            <option value="">{{ localize('select_approver', 'áž‡áŸ’ážšáž¾ážŸáž¢áŸ’áž“áž€áž¢áž“áž»áž˜áŸáž') }}</option>
                                            @foreach($approverUserOptions as $user)
                                                <option value="{{ $user['id'] }}" data-search="{{ $user['search_text'] ?? '' }}">{{ $user['label'] }}</option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted">If only one person is selected, this person becomes the final approver.</small>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="policy-form-label">Step 2 (Optional)</label>
                                        <select class="form-select" name="middle_approver_user_id" id="wf_middle_approver_user_id">
                                            <option value="">{{ localize('skip_middle_approver', 'áž˜áž·áž“áž”áŸ’ážšáž¾áž‡áž¶áž“áŸ‹áž‘áž¸áŸ¢') }}</option>
                                            @foreach($approverUserOptions as $user)
                                                <option value="{{ $user['id'] }}" data-search="{{ $user['search_text'] ?? '' }}">{{ $user['label'] }}</option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted">If selected, this person comments and forwards to the next approver.</small>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="policy-form-label">Step 3 (Optional)</label>
                                        <select class="form-select" name="final_approver_user_id" id="wf_final_approver_user_id">
                                            <option value="">{{ localize('select_approver', 'áž‡áŸ’ážšáž¾ážŸáž¢áŸ’áž“áž€áž¢áž“áž»áž˜áŸáž') }}</option>
                                            @foreach($approverUserOptions as $user)
                                                <option value="{{ $user['id'] }}" data-search="{{ $user['search_text'] ?? '' }}">{{ $user['label'] }}</option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted">If selected, this person becomes the final approver.</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="workflow-modal-section">
                                <div class="workflow-modal-section-title">
                                    <span class="section-icon"><i class="far fa-sticky-note"></i></span>
                                    <span>{{ localize('additional_note', 'áž€áŸ†ážŽážáŸ‹áž…áŸ†ážŽáž¶áŸ†áž”áž“áŸ’ážáŸ‚áž˜') }}</span>
                                </div>
                                <label class="policy-form-label">{{ localize('description', 'áž€áž¶ážšáž–áž·áž–ážŽáŸŒáž“áž¶') }}</label>
                                <textarea class="form-control" name="description" id="wf_description" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer workflow-modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ localize('cancel', 'áž”áŸ„áŸ‡áž”áž„áŸ‹') }}</button>
                    <button type="submit" class="btn btn-success">{{ localize('save', 'ážšáž€áŸ’ážŸáž¶áž‘áž»áž€') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('js')
<script>
    (function () {
        var storeRoute = @json(route('attendances.workflow_policies.store'));
        var updateRouteTemplate = @json(route('attendances.workflow_policies.update', ['policyId' => '__ID__']));
        var form = document.getElementById('workflowPolicyForm');
        var methodInput = document.getElementById('workflowPolicyMethod');
        var modalTitle = document.getElementById('workflowPolicyModalTitle');

        if (!form || !methodInput || !modalTitle) {
            return;
        }

        var inputs = {
            requestType: document.getElementById('wf_request_type_key'),
            name: document.getElementById('wf_name'),
            departments: document.getElementById('wf_department_ids'),
            minDays: document.getElementById('wf_min_days'),
            maxDays: document.getElementById('wf_max_days'),
            priority: document.getElementById('wf_priority'),
            isActive: document.getElementById('wf_is_active'),
            firstApprover: document.getElementById('wf_first_approver_user_id'),
            middleApprover: document.getElementById('wf_middle_approver_user_id'),
            finalApprover: document.getElementById('wf_final_approver_user_id'),
            description: document.getElementById('wf_description')
        };
        var orgTreePanel = document.getElementById('wf-org-tree-panel');
        var selectedDepartmentsPreview = document.getElementById('wf_selected_departments_preview');
        var approverPreview = document.getElementById('wf_approver_preview');
        var departmentNodeMap = {};
        var childrenByParent = {};

        function findDirectChildToggle(item) {
            if (!item) {
                return null;
            }

            var row = Array.prototype.find.call(item.children || [], function (child) {
                return child.classList && child.classList.contains('wf-org-tree-row');
            });

            return row ? row.querySelector('.wf-org-tree-toggle') : null;
        }

        function setTreeExpanded(item, expanded) {
            if (!item) {
                return;
            }

            item.classList.toggle('is-open', expanded);
            var toggle = findDirectChildToggle(item);
            if (!toggle) {
                return;
            }

            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            var symbol = toggle.querySelector('.toggle-symbol');
            if (symbol) {
                symbol.textContent = expanded ? '-' : '+';
            }
        }

        window.wfOrgTreeToggle = function (toggleElement) {
            var item = toggleElement ? toggleElement.closest('.wf-org-tree-item') : null;
            if (!item) {
                return false;
            }

            setTreeExpanded(item, !item.classList.contains('is-open'));
            return false;
        };

        window.wfOrgTreeSelect = function (nodeElement) {
            if (!nodeElement || nodeElement.disabled) {
                return false;
            }

            setDepartmentSelection([nodeElement.getAttribute('data-org-unit-id') || '']);
            return false;
        };

        function syncActiveTreeNode(selectedId) {
            if (!orgTreePanel) {
                return;
            }

            var normalizedId = String(selectedId || '');
            orgTreePanel.querySelectorAll('.wf-org-tree-node.is-active').forEach(function (node) {
                node.classList.remove('is-active');
            });

            if (normalizedId === '') {
                return;
            }

            var activeNode = orgTreePanel.querySelector('.wf-org-tree-node[data-org-unit-id="' + normalizedId + '"]');
            if (!activeNode) {
                return;
            }

            activeNode.classList.add('is-active');

            var currentItem = activeNode.closest('.wf-org-tree-item');
            while (currentItem) {
                if (currentItem.classList.contains('has-children')) {
                    setTreeExpanded(currentItem, true);
                }
                var parentList = currentItem.parentElement;
                currentItem = parentList ? parentList.closest('.wf-org-tree-item') : null;
            }
        }

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function selectedOptionLabel(element) {
            if (!element || !element.options || element.selectedIndex < 0) {
                return '';
            }

            return String(element.options[element.selectedIndex].text || '').trim();
        }

        function selectedApproverSteps() {
            var labels = [
                selectedOptionLabel(inputs.firstApprover),
                selectedOptionLabel(inputs.middleApprover),
                selectedOptionLabel(inputs.finalApprover)
            ].filter(function (label) {
                return label !== '';
            });

            return labels.map(function (label, index) {
                var isFinal = index === (labels.length - 1);
                return {
                    label: isFinal ? @json(localize('final_level', 'Final')) : ('Step ' + (index + 1)),
                    value: label,
                    hint: isFinal ? 'Approve' : 'Comment / Forward'
                };
            });
        }

        function renderDepartmentPreview() {
            if (!selectedDepartmentsPreview || !inputs.departments) {
                return;
            }

            var labels = Array.prototype.slice.call(inputs.departments.selectedOptions || [])
                .map(function (option) {
                    return String(option.text || '').trim();
                })
                .filter(function (label) {
                    return label !== '';
                });

            if (labels.length === 0) {
                selectedDepartmentsPreview.innerHTML = '<span class="preview-empty">'
                    + escapeHtml(@json(localize('no_unit_selected', 'áž˜áž·áž“áž‘áž¶áž“áŸ‹áž‡áŸ’ážšáž¾ážŸáž¢áž„áŸ’áž‚áž—áž¶áž–')))
                    + '</span>';
                return;
            }

            selectedDepartmentsPreview.innerHTML = labels.map(function (label) {
                return '<span class="policy-chip">' + escapeHtml(label) + '</span>';
            }).join('');
        }

        function renderApproverPreview() {
            if (!approverPreview) {
                return;
            }

            var steps = selectedApproverSteps();
            if (steps.length === 0) {
                steps = [{
                    label: 'Step 1',
                    value: @json(localize('select_approver', 'Select approver')),
                    hint: ''
                }];
            }

            approverPreview.innerHTML = steps.map(function (step) {
                return '<div class="workflow-preview-step">'
                    + '<span class="step-badge">' + escapeHtml(step.label) + '</span>'
                    + '<span class="step-text">' + escapeHtml(step.value + (step.hint ? ' - ' + step.hint : '')) + '</span>'
                    + '</div>';
            }).join('');
        }

        function setSelectValue(element, value) {
            if (!element) {
                return;
            }

            var normalized = value === null || value === undefined ? '' : value;
            element.value = normalized;

            if (window.jQuery && window.jQuery.fn && window.jQuery(element).data('select2')) {
                window.jQuery(element).val(normalized).trigger('change');
            }

            renderApproverPreview();
        }

        function setMultiSelectValues(element, values) {
            if (!element) {
                return;
            }

            var normalized = Array.isArray(values)
                ? values.map(function (value) { return String(value); })
                : [];

            Array.prototype.forEach.call(element.options, function (option) {
                option.selected = normalized.indexOf(String(option.value)) !== -1;
            });

            if (window.jQuery && window.jQuery.fn && window.jQuery(element).data('select2')) {
                window.jQuery(element).val(normalized).trigger('change');
            }

            renderDepartmentPreview();
        }

        function setDepartmentSelection(ids) {
            var normalized = Array.isArray(ids)
                ? ids.map(function (id) { return String(id); }).filter(function (id) { return id !== ''; })
                : [];

            setMultiSelectValues(inputs.departments, normalized);

            var selectedId = normalized.length ? parseInt(normalized[0], 10) : 0;
            if (!selectedId) {
                syncActiveTreeNode('');
                return;
            }

            var selectedNode = departmentNodeMap[String(selectedId)] || null;
            if (!selectedNode) {
                syncActiveTreeNode(String(selectedId));
                return;
            }

            syncActiveTreeNode(String(selectedNode.id));
        }

        function buildDepartmentTree() {
            if (!inputs.departments) {
                return;
            }

            var departmentNodes = Array.prototype.slice.call(inputs.departments.options || []).map(function (option) {
                return {
                    id: String(option.value || ''),
                    label: String(option.text || ''),
                    parentId: String(option.getAttribute('data-parent-id') || '')
                };
            }).filter(function (node) {
                return node.id !== '';
            });

            departmentNodeMap = {};
            childrenByParent = {};

            departmentNodes.forEach(function (node) {
                departmentNodeMap[node.id] = node;
            });

            departmentNodes.forEach(function (node) {
                var parentExists = node.parentId !== '' && departmentNodeMap[node.parentId];
                var parentKey = parentExists ? node.parentId : 'root';

                if (!childrenByParent[parentKey]) {
                    childrenByParent[parentKey] = [];
                }

                childrenByParent[parentKey].push(node);
            });

            Object.keys(childrenByParent).forEach(function (key) {
                childrenByParent[key].sort(function (a, b) {
                    return a.label.localeCompare(b.label, undefined, { sensitivity: 'base' });
                });
            });

        }

        function ensureDepartmentSelection(event) {
            var selectedIds = Array.prototype.slice.call(inputs.departments.selectedOptions || [])
                .map(function (option) {
                    return String(option.value);
                })
                .filter(function (id) {
                    return id !== '';
                });

            if (selectedIds.length > 0) {
                return true;
            }

            event.preventDefault();
            alert(@json(localize('please_select_org_unit', 'Please select at least one organization unit.')));
            return false;
        }

        function ensureApproverSelection(event) {
            if (selectedApproverSteps().length > 0) {
                return true;
            }

            event.preventDefault();
            alert('Please select at least one approver.');
            return false;
        }

        function resetForm() {
            form.action = storeRoute;
            methodInput.value = 'POST';
            modalTitle.textContent = @json(localize('add_condition', 'áž”áž“áŸ’ážáŸ‚áž˜áž›áž€áŸ’ážážážŽáŸ’ážŒ'));
            form.reset();
            inputs.priority.value = '100';
            inputs.isActive.checked = true;
            setDepartmentSelection([]);
            setSelectValue(inputs.firstApprover, '');
            setSelectValue(inputs.middleApprover, '');
            setSelectValue(inputs.finalApprover, '');
            renderDepartmentPreview();
            renderApproverPreview();
        }

        var createBtn = document.getElementById('create-workflow-policy-btn');
        if (createBtn) {
            createBtn.addEventListener('click', function () {
                resetForm();
            });
        }

        document.querySelectorAll('.edit-workflow-policy-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var payload = {};
                try {
                    payload = JSON.parse(btn.getAttribute('data-policy') || '{}');
                } catch (e) {
                    payload = {};
                }

                var id = payload.id || 0;
                form.action = updateRouteTemplate.replace('__ID__', String(id));
                methodInput.value = 'PUT';
                modalTitle.textContent = @json(localize('edit_condition', 'áž€áŸ‚ážŸáž˜áŸ’ážšáž½áž›áž›áž€áŸ’ážážážŽáŸ’ážŒ'));

                inputs.requestType.value = payload.request_type_key || 'attendance_adjustment';
                inputs.name.value = payload.name || '';
                inputs.minDays.value = payload.min_days ?? '';
                inputs.maxDays.value = payload.max_days ?? '';
                inputs.priority.value = payload.priority || 100;
                inputs.isActive.checked = Boolean(payload.is_active);
                setDepartmentSelection(payload.department_ids || []);
                var payloadApproverSteps = Array.isArray(payload.approver_steps) ? payload.approver_steps : [];
                setSelectValue(inputs.firstApprover, payloadApproverSteps[0] && payloadApproverSteps[0].user_id ? String(payloadApproverSteps[0].user_id) : (payload.first_approver_user_id ? String(payload.first_approver_user_id) : ''));
                setSelectValue(inputs.middleApprover, payloadApproverSteps[1] && payloadApproverSteps[1].user_id ? String(payloadApproverSteps[1].user_id) : (payload.middle_approver_user_id ? String(payload.middle_approver_user_id) : ''));
                setSelectValue(inputs.finalApprover, payloadApproverSteps[2] && payloadApproverSteps[2].user_id ? String(payloadApproverSteps[2].user_id) : (payload.final_approver_user_id ? String(payload.final_approver_user_id) : ''));
                inputs.description.value = payload.description || '';
                renderDepartmentPreview();
                renderApproverPreview();
            });
        });

        buildDepartmentTree();
        setDepartmentSelection([]);
        renderDepartmentPreview();
        renderApproverPreview();

        form.addEventListener('submit', function (event) {
            if (!ensureDepartmentSelection(event)) {
                return false;
            }

            return ensureApproverSelection(event);
        });

        [inputs.firstApprover, inputs.middleApprover, inputs.finalApprover].forEach(function (element) {
            if (!element) {
                return;
            }

            element.addEventListener('change', renderApproverPreview);
        });

        if (orgTreePanel) {
            orgTreePanel.querySelectorAll('.wf-org-tree-item.has-children').forEach(function (item) {
                setTreeExpanded(item, true);
            });
        }

        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
            var $firstApprover = window.jQuery('#wf_first_approver_user_id');
            var $middleApprover = window.jQuery('#wf_middle_approver_user_id');
            var $finalApprover = window.jQuery('#wf_final_approver_user_id');

            function approverMatcher(params, data) {
                var term = window.jQuery.trim(params.term || '').toLowerCase();
                if (term === '') {
                    return data;
                }

                var text = ((data.text || '') + '').toLowerCase();
                var searchMeta = '';
                if (data.element) {
                    searchMeta = ((data.element.getAttribute('data-search') || '') + '').toLowerCase();
                }

                if (text.indexOf(term) > -1 || searchMeta.indexOf(term) > -1) {
                    return data;
                }

                return null;
            }

            function splitApproverText(rawText) {
                var text = (rawText || '').toString();
                var parts = text.split(/•|â€¢/).map(function (part) {
                    return window.jQuery.trim(part);
                }).filter(function (part) {
                    return part !== '';
                });

                return {
                    name: parts.length ? parts[0] : text,
                    meta: parts.length > 1 ? parts.slice(1).join(' • ') : ''
                };
            }

            function renderApproverOption(data) {
                if (!data.id) {
                    return data.text;
                }

                var parsed = splitApproverText(data.text || '');
                var meta = parsed.meta !== ''
                    ? '<div class="select2-approver-meta">' + window.jQuery('<div>').text(parsed.meta).html() + '</div>'
                    : '';

                var html = '<div class="select2-approver-option">'
                    + '<div class="select2-approver-name">' + window.jQuery('<div>').text(parsed.name).html() + '</div>'
                    + meta
                    + '</div>';

                return window.jQuery(html);
            }

            function renderApproverSelection(data) {
                if (!data || !data.text) {
                    return data ? data.text : '';
                }

                return splitApproverText(data.text).name;
            }

            $firstApprover.select2({
                theme: 'bootstrap4',
                width: '100%',
                placeholder: @json(localize('search_employee_name', 'ážŸáŸ’ážœáŸ‚áž„ážšáž€ážˆáŸ’áž˜áŸ„áŸ‡áž”áž»áž‚áŸ’áž‚áž›áž·áž€')),
                allowClear: true,
                minimumInputLength: 1,
                matcher: approverMatcher,
                templateResult: renderApproverOption,
                templateSelection: renderApproverSelection,
                dropdownParent: window.jQuery('#workflowPolicyModal')
            }).on('change', renderApproverPreview);

            $finalApprover.select2({
                theme: 'bootstrap4',
                width: '100%',
                placeholder: @json(localize('search_employee_name', 'ážŸáŸ’ážœáŸ‚áž„ážšáž€ážˆáŸ’áž˜áŸ„áŸ‡áž”áž»áž‚áŸ’áž‚áž›áž·áž€')),
                allowClear: true,
                minimumInputLength: 1,
                matcher: approverMatcher,
                templateResult: renderApproverOption,
                templateSelection: renderApproverSelection,
                dropdownParent: window.jQuery('#workflowPolicyModal')
            }).on('change', renderApproverPreview);

            $middleApprover.select2({
                theme: 'bootstrap4',
                width: '100%',
                placeholder: @json(localize('middle_approver_optional', 'áž¢áŸ’áž“áž€áž¢áž“áž»áž˜áŸážáž‡áž¶áž“áŸ‹áž‘áž¸áŸ¢ (áž‡áž˜áŸ’ážšáž¾ážŸ)')),
                allowClear: true,
                minimumInputLength: 1,
                matcher: approverMatcher,
                templateResult: renderApproverOption,
                templateSelection: renderApproverSelection,
                dropdownParent: window.jQuery('#workflowPolicyModal')
            }).on('change', renderApproverPreview);

        }
    })();
</script>
@endpush


