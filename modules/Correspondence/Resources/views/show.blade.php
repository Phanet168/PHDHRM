@extends('backend.layouts.app')

@section('title', localize('correspondence_detail', 'ព័ត៌មានលម្អិតលិខិត'))

@section('content')
    @php
        $isIncoming = $letter->letter_type === \Modules\Correspondence\Entities\CorrespondenceLetter::TYPE_INCOMING;
        $step = (string) ($letter->current_step ?? '');
        $approved = (string) ($letter->final_decision ?? '') === \Modules\Correspondence\Entities\CorrespondenceLetter::DECISION_APPROVED;
        $finalDecision = trim((string) ($letter->final_decision ?? ''));
        $isFinalApproved = $finalDecision === \Modules\Correspondence\Entities\CorrespondenceLetter::DECISION_APPROVED;
        $isFinalRejected = $finalDecision === \Modules\Correspondence\Entities\CorrespondenceLetter::DECISION_REJECTED;
        $currentUserId = (int) auth()->id();
        $isChild = (bool) ($isChildLetter ?? false);
        $parentLetter = $letter->parentLetter;
        $highlightDistributionId = max(0, (int) ($highlightDistributionId ?? request()->query('highlight_distribution', 0)));
        $focusAction = trim((string) request()->query('focus_action', ''));
        $workflowAssignments = is_array($workflowAssignments ?? null) ? $workflowAssignments : [];
        $outgoingToOrgText = (string) ($letter->to_org ?: '-');
        if (!$isIncoming) {
            $outgoingToDepartments = $letter->distributions
                ? $letter->distributions
                    ->filter(function ($dist) {
                        return in_array($dist->distribution_type, [
                            \Modules\Correspondence\Entities\CorrespondenceLetterDistribution::TYPE_TO,
                            null,
                            '',
                        ], true);
                    })
                    ->map(function ($dist) {
                        return $dist->targetDepartment?->department_name;
                    })
                    ->filter()
                    ->unique()
                    ->values()
                : collect();

            if ($outgoingToDepartments->isNotEmpty()) {
                $outgoingToOrgText = $outgoingToDepartments->implode(', ');
            }
        }

        $flowSteps = $isIncoming
            ? [
                \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_INCOMING_RECEIVED => localize('flow_received_km', 'ទទួល/ចុះឈ្មោះលិខិត'),
                \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_INCOMING_DELEGATED => localize('flow_delegated_admin_office_km2', 'ពិនិត្យ និងបែងចែកលិខិត'),
                \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_INCOMING_OFFICE_COMMENT => localize('flow_office_comment_km2', 'ពិនិត្យ និងផ្តល់យោបល់'),
                \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_INCOMING_DEPUTY_REVIEW => localize('flow_deputy_review_km2', 'ពិនិត្យដោយអនុប្រធានមន្ទីរ'),
                \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_INCOMING_DIRECTOR_DECISION => localize('flow_director_decision_km2', 'សម្រេចដោយប្រធានមន្ទីរ'),
            ]
            : [
                \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_OUTGOING_DRAFT => localize('flow_draft', 'សេចក្តីព្រាង'),
                \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_OUTGOING_DISTRIBUTED => localize('flow_distributed', 'បានបែងចែក'),
                \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_CLOSED => localize('flow_closed', 'បានបិទ'),
            ];
        $flowOwners = $isIncoming
            ? [
                \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_INCOMING_RECEIVED => localize('flow_owner_registry', 'ចុះបញ្ជី/ទទួលលិខិត'),
                \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_INCOMING_DELEGATED => localize('flow_owner_head_or_deputy_admin_office_km2', 'ប្រធាន/អនុប្រធានការិយាល័យរដ្ឋបាល'),
                \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_INCOMING_OFFICE_COMMENT => localize('flow_owner_office_manager_multi_v2', 'អង្គភាពពាក់ព័ន្ធ (អាចលើសពី 2)'),
                \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_INCOMING_DEPUTY_REVIEW => localize('flow_owner_deputy', 'អនុប្រធានមន្ទីរទទួលខុសត្រូវ'),
                \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_INCOMING_DIRECTOR_DECISION => localize('flow_owner_director', 'ប្រធានមន្ទីរ'),
            ]
            : [
                \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_OUTGOING_DRAFT => localize('flow_owner_prepare', 'អ្នករៀបចំ/ចុះបញ្ជី'),
                \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_OUTGOING_DISTRIBUTED => localize('flow_owner_recipients', 'អ្នកទទួល (To/CC)'),
                \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_CLOSED => localize('flow_owner_close', 'បិទសំណុំការងារ'),
            ];
        $flowKeys = array_keys($flowSteps);
        $currentFlowStep = (string) ($letter->current_step ?? '');
        $currentFlowIndex = array_search($currentFlowStep, $flowKeys, true);
        $nextFlowLabel = ($currentFlowIndex !== false && isset($flowKeys[$currentFlowIndex + 1]))
            ? ($flowSteps[$flowKeys[$currentFlowIndex + 1]] ?? null)
            : null;
        $flowTotalSteps = count($flowKeys);
        $flowCompletedSteps = $currentFlowIndex === false ? 0 : ($currentFlowIndex + 1);
        $flowProgressPercent = $flowTotalSteps > 0
            ? (int) round(($flowCompletedSteps / $flowTotalSteps) * 100)
            : 0;

        $stepGuides = $isIncoming
            ? [
                \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_INCOMING_RECEIVED => localize('guide_incoming_received', 'កត់ត្រា និងផ្ទៀងផ្ទាត់ព័ត៌មានលិខិតចូល'),
                \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_INCOMING_DELEGATED => localize('guide_incoming_delegated', 'បែងចែកទៅប្រធានអង្គភាពពាក់ព័ន្ធ'),
                \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_INCOMING_OFFICE_COMMENT => localize('guide_incoming_office_comment_multi_v2', 'អង្គភាពពាក់ព័ន្ធ (អាចច្រើន) ពិនិត្យ និងផ្តល់យោបល់ (CC ជូនអនុប្រធានមន្ទីរ)'),
                \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_INCOMING_DEPUTY_REVIEW => localize('guide_incoming_deputy_review', 'អនុប្រធានមន្ទីរទទួលខុសត្រូវ ពិនិត្យ និងផ្តល់យោបល់ទៅប្រធានមន្ទីរ'),
                \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_INCOMING_DIRECTOR_DECISION => localize('guide_incoming_director_decision', 'ប្រធានមន្ទីរទទួល និងសម្រេចបញ្ចប់ដំណើរការ'),
            ]
            : [
                \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_OUTGOING_DRAFT => localize('guide_outgoing_draft', 'រៀបចំខ្លឹមសារ និងឯកសារភ្ជាប់'),
                \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_OUTGOING_DISTRIBUTED => localize('guide_outgoing_distributed', 'បញ្ជូនទៅអ្នកទទួល To/CC ហើយតាមដានការទទួល'),
                \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_CLOSED => localize('guide_closed', 'បិទសំណុំការងារ នៅពេលបញ្ចប់គ្រប់ជំហាន'),
            ];

        $actionTypeLabels = [
            'created' => localize('action_created', 'បង្កើតលិខិត'),
            'delegate' => localize('action_delegate', 'ចាត់តាំង'),
            'office_comment' => localize('action_office_comment_related_units', 'មតិអង្គភាពពាក់ព័ន្ធ'),
            'deputy_review' => localize('action_deputy_review', 'ពិនិត្យដោយអនុប្រធាន'),
            'director_approved' => localize('action_director_approved', 'អនុម័តដោយប្រធានមន្ទីរ'),
            'director_rejected' => localize('action_director_rejected', 'មិនអនុម័តដោយប្រធានមន្ទីរ'),
            'distribute' => localize('action_distribute', 'បែងចែក'),
            'acknowledge' => localize('action_acknowledge', 'ទទួលស្គាល់ការទទួល'),
            'feedback' => localize('action_feedback', 'ផ្តល់មតិយោបល់'),
            'feedback_to_parent' => localize('action_feedback_parent', 'ផ្ញើមតិទៅអង្គភាពមេ'),
            'child_feedback_received' => localize('action_child_feedback_received', 'ទទួលមតិពីអង្គភាពរង'),
            'child_acknowledged' => localize('action_child_acknowledged', 'អង្គភាពរងបានទទួលស្គាល់'),
            'closed' => localize('action_closed', 'បិទសំណុំការងារ'),
        ];

        $recentActionNotes = $letter->actions
            ->filter(fn($row) => trim((string) ($row->note ?? '')) !== '')
            ->sortByDesc(function ($row) {
                return (string) ($row->acted_at ?? '');
            })
            ->take(6)
            ->values();

        $recentFeedbackNotes = $letter->distributions
            ->filter(fn($row) => trim((string) ($row->feedback_note ?? '')) !== '')
            ->sortByDesc(function ($row) {
                return (string) ($row->feedback_at ?? '');
            })
            ->take(6)
            ->values();
    @endphp

    <div class="body-content">
        @include('correspondence::_nav')

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if (session('warning'))
            <div class="alert alert-warning">{{ session('warning') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <div class="fw-semibold mb-1">{{ localize('please_check_form_errors', 'សូមពិនិត្យព័ត៌មានបញ្ចូលម្តងទៀត') }}</div>
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $errorMessage)
                        <li>{{ $errorMessage }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php
            $highlightedDistribution = $highlightDistributionId > 0 ? $letter->distributions->firstWhere('id', $highlightDistributionId) : null;
            $highlightedDistributionStatus = (string) ($highlightedDistribution->status ?? '');
            $highlightedDistributionIsForCurrentUser = $highlightedDistribution
                && (((int) ($highlightedDistribution->target_user_id ?? 0) === $currentUserId) || !empty($canDistribute));
            $highlightedDistributionCanAcknowledge = $highlightedDistributionIsForCurrentUser
                && $highlightedDistributionStatus === \Modules\Correspondence\Entities\CorrespondenceLetterDistribution::STATUS_PENDING_ACK;
            $highlightedDistributionCanFeedback = $highlightedDistributionIsForCurrentUser
                && $isIncoming
                && in_array($highlightedDistributionStatus, [
                    \Modules\Correspondence\Entities\CorrespondenceLetterDistribution::STATUS_ACKNOWLEDGED,
                    \Modules\Correspondence\Entities\CorrespondenceLetterDistribution::STATUS_FEEDBACK_SENT,
                ], true);
            $highlightedTargetLabel = $highlightedDistribution?->targetDepartment?->department_name
                ?: (!empty($highlightedDistribution?->target_user_id) ? ($userMap[(int) $highlightedDistribution->target_user_id] ?? ('#' . $highlightedDistribution->target_user_id)) : '-');
        @endphp

        @if ($highlightedDistribution)
            <div class="alert {{ $highlightedDistributionCanAcknowledge || $highlightedDistributionCanFeedback ? 'alert-primary' : 'alert-info' }} d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                    <div class="fw-semibold mb-1">{{ localize('notification_opened', 'បើកពី Notification') }}</div>
                    @if ($highlightedDistributionCanAcknowledge)
                        <div>{{ localize('notification_ack_instruction', 'លិខិតនេះបានផ្ញើមកអ្នករួចហើយ។ សូមចុច Acknowledge នៅជួរដែលបានរំលេចខាងក្រោម ដើម្បីទទួលស្គាល់ការទទួល។') }}</div>
                    @elseif ($highlightedDistributionCanFeedback)
                        <div>{{ localize('notification_feedback_instruction', 'លិខិតនេះកំពុងរង់ចាំមតិយោបល់ពីអ្នក។ សូមចុះទៅជួរដែលបានរំលេចខាងក្រោម ហើយបំពេញ Feedback។') }}</div>
                    @else
                        <div>{{ localize('notification_distribution_opened', 'ប្រព័ន្ធបានបើកទៅកាន់ជួរចែកចាយដែលពាក់ព័ន្ធរួចហើយ។') }}</div>
                    @endif
                    <div class="small text-muted mt-1">{{ localize('target', 'គោលដៅ') }}: {{ $highlightedTargetLabel }}</div>
                </div>
                <a href="#distribution-action-{{ (int) $highlightedDistribution->id }}" class="btn btn-sm btn-outline-primary">
                    {{ localize('go_to_action', 'ទៅកន្លែងអនុវត្ត') }}
                </a>
            </div>
        @endif

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">{{ localize('correspondence_detail', 'ព័ត៌មានលម្អិតលិខិត') }}</h6>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('correspondence.help', ['article' => 'workflow']) }}" class="btn btn-sm btn-outline-info">
                        <i class="fa fa-life-ring"></i> {{ localize('help', 'ជំនួយ') }}
                    </a>
                    <a href="{{ route('correspondence.print', $letter->id) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="fa fa-print"></i> {{ localize('print_report', 'បោះពុម្ពរបាយការណ៍') }}
                    </a>
                    <a href="{{ $isIncoming ? route('correspondence.incoming') : route('correspondence.outgoing') }}" class="btn btn-sm btn-secondary">
                        {{ localize('back', 'ត្រឡប់ក្រោយ') }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4"><strong>{{ localize('letter_type', 'ប្រភេទលិខិត') }}:</strong> {{ $isIncoming ? localize('incoming_letters', 'លិខិតចូល') : localize('outgoing_letters', 'លិខិតចេញ') }}</div>
                    <div class="col-md-4"><strong>{{ localize('registry_no', 'លេខចុះបញ្ជី') }}:</strong> {{ $letter->registry_no ?: '-' }}</div>
                    <div class="col-md-4"><strong>{{ localize('letter_no', 'លេខលិខិត') }}:</strong> {{ $letter->letter_no ?: '-' }}</div>
                    <div class="col-md-12"><strong>{{ localize('subject', 'ប្រធានបទ') }}:</strong> {{ $letter->subject }}</div>
                    @if ($isIncoming)
                        <div class="col-md-6"><strong>{{ localize('from_org', 'អង្គភាពចេញលិខិត') }}:</strong> {{ $letter->from_org ?: '-' }}</div>
                    @endif
                    <div class="{{ $isIncoming ? 'col-md-6' : 'col-md-12' }}"><strong>{{ localize('to_org', 'អង្គភាពទទួល') }}:</strong> {{ $isIncoming ? ($letter->to_org ?: '-') : $outgoingToOrgText }}</div>
                    <div class="col-md-4"><strong>{{ localize('letter_date', 'ថ្ងៃលិខិត') }}:</strong> {{ optional($letter->letter_date)->format('d/m/Y') ?: '-' }}</div>
                    @if ($isIncoming)
                        <div class="col-md-4"><strong>{{ localize('received_date', 'ថ្ងៃទទួលលិខិត') }}:</strong> {{ optional($letter->received_date)->format('d/m/Y') ?: '-' }}</div>
                    @else
                        <div class="col-md-4"><strong>{{ localize('sent_date', 'ថ្ងៃផ្ញើចេញ') }}:</strong> {{ optional($letter->sent_date)->format('d/m/Y') ?: '-' }}</div>
                    @endif
                    <div class="col-md-4"><strong>{{ localize('current_step', 'ជំហានបច្ចុប្បន្ន') }}:</strong> {{ $letter->current_step_label }}</div>
                    <div class="col-md-4"><strong>{{ localize('status', 'ស្ថានភាព') }}:</strong> {{ $letter->status }}</div>
                    @if ($isIncoming)
                        <div class="col-md-4">
                            <strong>{{ localize('final_decision', 'សម្រេចចុងក្រោយ') }}:</strong>
                            @if ($isFinalApproved)
                                <span class="badge bg-success">✓ {{ localize('approved_km', 'បានអនុម័ត') }}</span>
                            @elseif ($isFinalRejected)
                                <span class="badge bg-danger">✕ {{ localize('rejected_km', 'បានបដិសេធ') }}</span>
                            @else
                                -
                            @endif
                        </div>
                        <div class="col-md-6"><strong>{{ localize('origin_org_unit', 'អង្គភាពដើម') }}:</strong> {{ $letter->originDepartment?->department_name ?: '-' }}</div>
                        <div class="col-md-6"><strong>{{ localize('assigned_org_unit', 'អង្គភាពដែលបានចាត់តាំង') }}:</strong> {{ $letter->assignedDepartment?->department_name ?: '-' }}</div>
                    @endif
                    @if ($isChild && $parentLetter)
                        <div class="col-md-12">
                            <strong>{{ localize('parent_letter', 'លិខិតមេ') }}:</strong>
                            <a href="{{ route('correspondence.show', $parentLetter->id) }}">#{{ $parentLetter->id }} - {{ $parentLetter->subject }}</a>
                        </div>
                    @endif
                    @if ($letter->childLetters->count() > 0)
                        <div class="col-md-12">
                            <strong>{{ localize('child_letters', 'លិខិតកូន') }}:</strong>
                            @foreach ($letter->childLetters as $childLetter)
                                <a href="{{ route('correspondence.show', $childLetter->id) }}" class="badge bg-light text-dark border me-1 mb-1">
                                    #{{ $childLetter->id }} {{ $childLetter->assignedDepartment?->department_name ?: '-' }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                    @if (!empty($letter->attachment_path))
                        @php
                            $attachments = is_array($letter->attachment_path) ? $letter->attachment_path : json_decode($letter->attachment_path, true);
                            if (!is_array($attachments)) {
                                $attachments = [(string) $letter->attachment_path];
                            }
                        @endphp
                        <div class="col-md-12">
                            <strong>{{ localize('attachments', 'ឯកសារភ្ជាប់') }}:</strong>
                            <div class="mt-1 d-flex flex-wrap gap-2">
                                @foreach ($attachments as $path)
                                    @if (!empty($path))
                                        @php
                                            $attachmentUrl = asset('storage/' . ltrim($path, '/'));
                                            $previewUrl = route('correspondence.attachments.preview', [$letter->id, $loop->index]);
                                            $attachmentExt = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                                            $previewableExts = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];
                                            $isPreviewable = in_array($attachmentExt, $previewableExts, true);
                                        @endphp
                                        <a href="{{ $previewUrl }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                            {{ localize('view', 'មើល') }}: {{ basename($path) }}
                                        </a>
                                        <a href="{{ $attachmentUrl }}" download class="btn btn-sm btn-outline-secondary">
                                            {{ localize('download', 'ទាញយក') }}
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif
                    <div class="col-md-12"><strong>{{ localize('summary', 'ខ្លឹមសារសង្ខេប') }}:</strong> {{ $letter->summary ?: '-' }}</div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0">{{ localize('workflow_timeline_km', 'បន្ទាត់ពេលវេលាដំណើរការ') }}</h6>
                <div class="small text-muted">
                    <span class="badge bg-success me-2">{{ $flowCompletedSteps }}/{{ $flowTotalSteps }} ({{ $flowProgressPercent }}%)</span>
                    {{ localize('current_step_km', 'ជំហានបច្ចុប្បន្ន') }}:
                    <span class="badge bg-primary">{{ $letter->current_step_label }}</span>
                    @if ($isIncoming && ($isFinalApproved || $isFinalRejected))
                        <span class="ms-2">{{ localize('final_decision', 'សម្រេចចុងក្រោយ') }}:</span>
                        @if ($isFinalApproved)
                            <span class="badge bg-success">✓ {{ localize('approved_km', 'បានអនុម័ត') }}</span>
                        @else
                            <span class="badge bg-danger">✕ {{ localize('rejected_km', 'បានបដិសេធ') }}</span>
                        @endif
                    @endif
                    @if (!empty($nextFlowLabel))
                        <span class="ms-2">{{ localize('next_step_km', 'ជំហានបន្ទាប់') }}:</span>
                        <span class="badge bg-light text-dark border">{{ $nextFlowLabel }}</span>
                    @endif
                </div>
            </div>
            <div class="card-body pt-2">
                <div class="corr-progress-wrap mb-2">
                    <div class="corr-progress-bar" style="width: {{ $flowProgressPercent }}%;"></div>
                </div>
                <div class="corr-legend mb-2">
                    <span class="corr-legend-item is-done"><i class="fa fa-check"></i> {{ localize('legend_done', 'រួចរាល់') }}</span>
                    <span class="corr-legend-item is-current"><i class="fa fa-dot-circle"></i> {{ localize('legend_current', 'កំពុងដំណើរការ') }}</span>
                    <span class="corr-legend-item is-pending">○ {{ localize('legend_pending', 'មិនទាន់ដល់ជំហាន') }}</span>
                </div>
                <div class="corr-timeline">
                    @foreach ($flowSteps as $flowKey => $flowLabel)
                        @php
                            $isDecisionResolvedStep = $isIncoming
                                && ($isFinalApproved || $isFinalRejected)
                                && $flowKey === \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_INCOMING_DIRECTOR_DECISION;
                            $isClosedResolvedStep = !$isIncoming
                                && $flowKey === \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_CLOSED
                                && in_array((string) ($letter->status ?? ''), [
                                    \Modules\Correspondence\Entities\CorrespondenceLetter::STATUS_COMPLETED,
                                    \Modules\Correspondence\Entities\CorrespondenceLetter::STATUS_ARCHIVED,
                                ], true);
                            $isCurrentFlowStep = $currentFlowStep === $flowKey;
                            $isCompletedFlowStep = $currentFlowIndex !== false && $loop->index < $currentFlowIndex;
                            if ($isDecisionResolvedStep || $isClosedResolvedStep) {
                                $isCurrentFlowStep = false;
                                $isCompletedFlowStep = true;
                            }
                            $flowOwnerLabel = $flowOwners[$flowKey] ?? '';
                        @endphp
                        <div class="corr-step {{ $isCurrentFlowStep ? 'is-current' : ($isCompletedFlowStep ? 'is-done' : 'is-pending') }}">
                            <div class="corr-step-index">
                                @if ($isDecisionResolvedStep)
                                    @if ($isFinalApproved)
                                        <i class="fa fa-check"></i>
                                    @else
                                        <i class="fa fa-times text-danger"></i>
                                    @endif
                                @elseif ($isCompletedFlowStep)
                                    <i class="fa fa-check"></i>
                                @elseif ($isCurrentFlowStep)
                                    <i class="fa fa-dot-circle"></i>
                                @else
                                    {{ $loop->iteration }}
                                @endif
                            </div>
                            <div class="corr-step-label">{{ $flowLabel }}</div>
                            @if ($flowOwnerLabel !== '')
                                <div class="corr-step-owner">{{ $flowOwnerLabel }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header">
                        <h6 class="mb-0">{{ localize('workflow_actions_km', 'សកម្មភាពតាមដំណើរការ') }}</h6>
                    </div>
                    <div class="card-body">
                        @php
                            $incomingFinalized = in_array((string) ($letter->status ?? ''), [
                                \Modules\Correspondence\Entities\CorrespondenceLetter::STATUS_COMPLETED,
                                \Modules\Correspondence\Entities\CorrespondenceLetter::STATUS_ARCHIVED,
                            ], true) || trim((string) ($letter->final_decision ?? '')) !== '';
                            $canDoDelegate = $isIncoming
                                && !$incomingFinalized
                                && in_array($step, [\Modules\Correspondence\Entities\CorrespondenceLetter::STEP_INCOMING_RECEIVED, \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_INCOMING_DELEGATED], true)
                                && !empty($isRecipientActor)
                                && $canDelegate;
                            $canDoOfficeComment = $isIncoming
                                && !$incomingFinalized
                                && $step === \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_INCOMING_OFFICE_COMMENT
                                && $canOfficeComment;
                            $canDoDeputyReview = $isIncoming
                                && !$incomingFinalized
                                && $step === \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_INCOMING_DEPUTY_REVIEW
                                && $canDeputyReview;
                            $canDoDirectorDecision = $isIncoming
                                && !$incomingFinalized
                                && $step === \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_INCOMING_DIRECTOR_DECISION
                                && $canDirectorDecision;
                            $canDoDistributeAction = !$isIncoming
                                && $canDistribute
                                && in_array($step, [
                                    \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_OUTGOING_DRAFT,
                                    \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_OUTGOING_DISTRIBUTED,
                                ], true);
                            $canDoCloseAction = !$isIncoming
                                && $canClose
                                && $step === \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_OUTGOING_DISTRIBUTED;
                            $canDoParentFeedback = !empty($canSendParentFeedback);
                            $hasWorkflowAction = $canDoDelegate || $canDoOfficeComment || $canDoDeputyReview || $canDoDirectorDecision || $canDoDistributeAction || $canDoCloseAction || $canDoParentFeedback;

                            $distributionTotal = $letter->distributions->count();
                            $distributionReceived = $letter->distributions
                                ->whereIn('status', [
                                    \Modules\Correspondence\Entities\CorrespondenceLetterDistribution::STATUS_ACKNOWLEDGED,
                                    \Modules\Correspondence\Entities\CorrespondenceLetterDistribution::STATUS_FEEDBACK_SENT,
                                    \Modules\Correspondence\Entities\CorrespondenceLetterDistribution::STATUS_CLOSED,
                                ])
                                ->count();
                            $distributionPending = max(0, $distributionTotal - $distributionReceived);
                        @endphp
                        @if ($isIncoming)
                            @if ($canDoDelegate)
                                <form id="workflow-action-delegate" method="POST" action="{{ route('correspondence.progress', $letter->id) }}" class="mb-3 border rounded p-2">
                                    @csrf
                                    <input type="hidden" name="action" value="delegate">
                                    <div class="fw-semibold mb-2">{{ localize('delegate_assign', 'ជំហានទី 2: ប្រធាន/អនុប្រធានការិយាល័យរដ្ឋបាល បែងចែកទៅអង្គភាពពាក់ព័ន្ធ') }}</div>
                                    <div class="small text-muted mb-2">{{ localize('delegate_step_hint_chain', 'រដ្ឋបាលកំណត់អ្នកអនុវត្តតាមលំដាប់ជំហាន 3, 4, 5 ម្តងតែមួយ ហើយប្រព័ន្ធនឹងបន្តទៅអ្នកបន្ទាប់ដោយស្វ័យប្រវត្តិ។') }}</div>
                                    <div class="mb-2">
                                        <label class="form-label">{{ localize('assigned_org_unit', 'អង្គភាពដែលបានចាត់តាំង') }}</label>
                                        <select class="form-select form-select-sm" name="assigned_department_id">
                                            <option value="">-- {{ localize('select_org_unit', 'ជ្រើសរើសអង្គភាព') }} --</option>
                                            @foreach (($orgUnitOptions ?? collect()) as $unit)
                                                @php
                                                    $displayName = $unit->path ?? '';
                                                    if ($displayName !== '') {
                                                        $parts = explode(' > ', $displayName);
                                                        $displayName = trim(end($parts));
                                                    } else {
                                                        $displayName = $unit->label ?? ('#' . $unit->id);
                                                    }
                                                @endphp
                                                <option value="{{ $unit->id }}" @selected((int) $unit->id === (int) ($letter->assigned_department_id ?? 0))>{{ $displayName }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">{{ localize('office_comment_user', 'អ្នកអនុវត្តជំហានទី 3') }}</label>
                                        <select class="form-select form-select-sm js-user-search" name="office_comment_user_id" data-placeholder="{{ localize('select_user', 'ជ្រើសរើសអ្នកប្រើ') }}">
                                            @if (!empty($workflowAssignments['office_comment_user_id']) && !empty($userMap[(int) $workflowAssignments['office_comment_user_id']]))
                                                <option value="{{ (int) $workflowAssignments['office_comment_user_id'] }}" selected>{{ $userMap[(int) $workflowAssignments['office_comment_user_id']] }}</option>
                                            @endif
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">{{ localize('deputy_review_user', 'អ្នកអនុវត្តជំហានទី 4') }}</label>
                                        <select class="form-select form-select-sm js-user-search" name="deputy_review_user_id" data-placeholder="{{ localize('select_user', 'ជ្រើសរើសអ្នកប្រើ') }}">
                                            @if (!empty($workflowAssignments['deputy_review_user_id']) && !empty($userMap[(int) $workflowAssignments['deputy_review_user_id']]))
                                                <option value="{{ (int) $workflowAssignments['deputy_review_user_id'] }}" selected>{{ $userMap[(int) $workflowAssignments['deputy_review_user_id']] }}</option>
                                            @endif
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">{{ localize('director_user', 'អ្នកអនុវត្តជំហានទី 5') }}</label>
                                        <select class="form-select form-select-sm js-user-search" name="director_user_id" data-placeholder="{{ localize('select_user', 'ជ្រើសរើសអ្នកប្រើ') }}">
                                            @if (!empty($workflowAssignments['director_user_id']) && !empty($userMap[(int) $workflowAssignments['director_user_id']]))
                                                <option value="{{ (int) $workflowAssignments['director_user_id'] }}" selected>{{ $userMap[(int) $workflowAssignments['director_user_id']] }}</option>
                                            @endif
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">{{ localize('note', 'ចំណារ') }}</label>
                                        <textarea class="form-control form-control-sm" name="note" rows="2"></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-primary">{{ localize('save', 'រក្សាទុក') }}</button>
                                </form>
                            @endif

                            @if ($canDoOfficeComment)
                                <form id="workflow-action-office-comment" method="POST" action="{{ route('correspondence.progress', $letter->id) }}" class="mb-3 border rounded p-2">
                                    @csrf
                                    <input type="hidden" name="action" value="office_comment">
                                    <div class="fw-semibold mb-2">{{ localize('office_comment_km_multi', 'ជំហានទី 3: អង្គភាពពាក់ព័ន្ធ (អាចលើសពី 2) ពិនិត្យ និងផ្តល់យោបល់') }}</div>
                                    @if (!empty($workflowAssignments['deputy_review_user_id']) && !empty($userMap[(int) $workflowAssignments['deputy_review_user_id']]))
                                        <div class="small text-muted mb-2">{{ localize('next_actor_deputy', 'អ្នកបន្ទាប់') }}: {{ $userMap[(int) $workflowAssignments['deputy_review_user_id']] }}</div>
                                    @endif
                                    <label class="form-label">{{ localize('note_required', 'មតិយោបល់') }} <span class="text-danger">*</span></label>
                                    <textarea class="form-control form-control-sm mb-2" name="note" rows="3" required placeholder="{{ localize('enter_comment_placeholder', 'សូមបញ្ចូលមតិយោបល់របស់អ្នក...') }}"></textarea>
                                    <button type="submit" class="btn btn-sm btn-primary">{{ localize('submit', 'បញ្ជូន') }}</button>
                                </form>
                            @endif

                            @if ($canDoDeputyReview)
                                <form id="workflow-action-deputy-review" method="POST" action="{{ route('correspondence.progress', $letter->id) }}" class="mb-3 border rounded p-2">
                                    @csrf
                                    <input type="hidden" name="action" value="deputy_review">
                                    <div class="fw-semibold mb-2">{{ localize('deputy_review_km', 'ជំហានទី 4: អនុប្រធានមន្ទីរទទួលខុសត្រូវ ពិនិត្យ និងផ្តល់យោបល់') }}</div>
                                    @if (!empty($workflowAssignments['director_user_id']) && !empty($userMap[(int) $workflowAssignments['director_user_id']]))
                                        <div class="small text-muted mb-2">{{ localize('next_actor_director', 'អ្នកបន្ទាប់') }}: {{ $userMap[(int) $workflowAssignments['director_user_id']] }}</div>
                                    @endif
                                    <label class="form-label">{{ localize('note_required', 'មតិយោបល់') }} <span class="text-danger">*</span></label>
                                    <textarea class="form-control form-control-sm mb-2" name="note" rows="3" required placeholder="{{ localize('enter_comment_placeholder', 'សូមបញ្ចូលមតិយោបល់របស់អ្នក...') }}"></textarea>
                                    <button type="submit" class="btn btn-sm btn-primary">{{ localize('submit', 'បញ្ជូន') }}</button>
                                </form>
                            @endif

                            @if ($canDoDirectorDecision)
                                <form id="workflow-action-director-decision" method="POST" action="{{ route('correspondence.progress', $letter->id) }}" class="mb-3 border rounded p-2">
                                    @csrf
                                    <input type="hidden" name="action" value="director_decision">
                                    <div class="fw-semibold mb-2">{{ localize('director_decision_km', 'ជំហានទី 5: ប្រធានមន្ទីរ សម្រេចបញ្ចប់ដំណើរការ') }}</div>
                                    <select name="decision" class="form-select form-select-sm mb-2" required>
                                        <option value="">-- {{ localize('select_decision', 'ជ្រើសរើសការសម្រេច') }} --</option>
                                        <option value="approved">{{ localize('approved', 'អនុម័ត') }}</option>
                                        <option value="rejected">{{ localize('rejected', 'បដិសេធ') }}</option>
                                    </select>
                                    <label class="form-label">{{ localize('note_optional', 'មតិយោបល់') }}</label>
                                    <textarea class="form-control form-control-sm mb-2" name="note" rows="3" placeholder="{{ localize('decision_note', 'សូមបញ្ចូលការសម្រេចចិត្ត / Decision note') }}"></textarea>
                                    <button type="submit" class="btn btn-sm btn-primary">{{ localize('submit', 'បញ្ជូន') }}</button>
                                </form>
                            @endif
                        @endif

                        @if ($canDoDistributeAction)
                                <form method="POST" action="{{ route('correspondence.distribute', $letter->id) }}" class="mb-3 border rounded p-2">
                                    @csrf
                                    <div class="fw-semibold mb-2">{{ localize('distribute_to_recipients', 'បែងចែកទៅអ្នកទទួល') }}</div>
                                    @if (!$isIncoming)
                                        <div class="mb-2">
                                            <label class="form-label">{{ localize('to_org_units', 'ទៅអង្គភាព') }}</label>
                                            <select class="form-select form-select-sm js-org-multi" name="to_department_ids[]" multiple data-placeholder="{{ localize('select_org_unit', 'ជ្រើសរើសអង្គភាព') }}">
                                                @foreach (($orgUnitOptions ?? collect()) as $unit)
                                                    @php
                                                        $displayName = $unit->path ?? '';
                                                        if ($displayName !== '') {
                                                            $parts = explode(' > ', $displayName);
                                                            $displayName = trim(end($parts));
                                                        } else {
                                                            $displayName = $unit->label ?? ('#' . $unit->id);
                                                        }
                                                    @endphp
                                                    <option value="{{ $unit->id }}">{{ $displayName }}</option>
                                                @endforeach
                                            </select>
                                            <small class="text-muted">{{ localize('to_org_unit_hint', 'សូមជ្រើសរើសអង្គភាពទទួលសំខាន់ (To)') }}</small>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label">{{ localize('cc_org_units', 'ជូនចម្លងអង្គភាព') }}</label>
                                            <select class="form-select form-select-sm js-org-multi" name="cc_department_ids[]" multiple data-placeholder="{{ localize('select_org_unit', 'ជ្រើសរើសអង្គភាព') }}">
                                                @foreach (($orgUnitOptions ?? collect()) as $unit)
                                                    @php
                                                        $displayName = $unit->path ?? '';
                                                        if ($displayName !== '') {
                                                            $parts = explode(' > ', $displayName);
                                                            $displayName = trim(end($parts));
                                                        } else {
                                                            $displayName = $unit->label ?? ('#' . $unit->id);
                                                        }
                                                    @endphp
                                                    <option value="{{ $unit->id }}">{{ $displayName }}</option>
                                                @endforeach
                                            </select>
                                            <small class="text-muted">{{ localize('cc_org_unit_hint', 'អង្គភាពជូនចម្លង (CC) សម្រាប់ជូនដំណឹងប៉ុណ្ណោះ') }}</small>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label">{{ localize('to_users', 'ទៅបុគ្គល') }}</label>
                                            <select class="form-select form-select-sm js-user-multi" name="to_user_ids[]" multiple data-placeholder="{{ localize('select_user', 'ជ្រើសរើសអ្នកប្រើ') }}"></select>
                                            <small class="text-muted">{{ localize('to_required_for_send', 'សូមជ្រើសរើសអ្នកទទួលសំខាន់ (To)') }}</small>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label">{{ localize('cc_users', 'ជូនចម្លងបុគ្គល') }}</label>
                                            <select class="form-select form-select-sm js-user-multi" name="cc_user_ids[]" multiple data-placeholder="{{ localize('select_user', 'ជ្រើសរើសអ្នកប្រើ') }}"></select>
                                            <small class="text-muted">{{ localize('cc_info_only', 'អ្នកជូនចម្លង (CC) សម្រាប់ជូនដំណឹងប៉ុណ្ណោះ') }}</small>
                                        </div>
                                    @else
                                        <div class="mb-2">
                                            <label class="form-label">{{ localize('target_org_unit', 'អង្គភាពគោលដៅ') }}</label>
                                            <select class="form-select form-select-sm" name="target_department_ids[]" multiple size="8">
                                                @foreach (($orgUnitOptions ?? collect()) as $unit)
                                                    @php
                                                        $displayName = $unit->path ?? '';
                                                        if ($displayName !== '') {
                                                            $parts = explode(' > ', $displayName);
                                                            $displayName = trim(end($parts));
                                                        } else {
                                                            $displayName = $unit->label ?? ('#' . $unit->id);
                                                        }
                                                    @endphp
                                                    <option value="{{ $unit->id }}">{{ $displayName }}</option>
                                                @endforeach
                                            </select>
                                            <small class="text-muted">{{ localize('can_select_multiple_units', 'អាចជ្រើសរើសអង្គភាពច្រើនបាន') }}</small>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label">{{ localize('target_user', 'អ្នកប្រើគោលដៅ') }}</label>
                                            <select class="form-select form-select-sm js-user-search" name="target_user_id" data-placeholder="{{ localize('select_user', 'ជ្រើសរើសអ្នកប្រើ') }}"></select>
                                        </div>
                                    @endif
                                    <div class="mb-2">
                                        <label class="form-label">{{ localize('note', 'ចំណារ') }}</label>
                                        <textarea class="form-control form-control-sm" name="note" rows="2"></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-primary">{{ localize('distribute', 'បែងចែក') }}</button>
                                </form>
                        @endif

                        @if (!$isIncoming && !$highlightedDistributionCanAcknowledge)
                            <div class="alert alert-light border mb-3">
                                {{ localize('outgoing_detail_read_only', 'ទំព័រលិខិតចេញនេះប្រើសម្រាប់មើលព័ត៌មាន និងតាមដានស្ថានភាព។ ប្រសិនបើអ្នកជាអ្នកទទួល ប្រព័ន្ធនឹងបង្ហាញប៊ូតុង Acknowledge នៅក្នុងតារាង Distribution ខាងក្រោម។') }}
                            </div>
                        @endif

                        @if ($canDoCloseAction)
                            <form method="POST" action="{{ route('correspondence.progress', $letter->id) }}" class="border rounded p-2">
                                @csrf
                                <input type="hidden" name="action" value="close">
                                <div class="fw-semibold mb-2">{{ localize('close_workflow', 'បិទលំហូរការងារ') }}</div>
                                <textarea class="form-control form-control-sm mb-2" name="note" rows="2" placeholder="{{ localize('close_note', 'ចំណារបិទលំហូរ (មិនបាច់បំពេញក៏បាន)') }}"></textarea>
                                <button type="submit" class="btn btn-sm btn-success">{{ localize('close', 'បិទ') }}</button>
                            </form>
                        @endif

                        @if ($canDoParentFeedback)
                            <form method="POST" action="{{ route('correspondence.feedback_parent', $letter->id) }}" class="mt-3 border rounded p-2">
                                @csrf
                                <div class="fw-semibold mb-2">{{ localize('feedback_to_parent_department', 'ផ្ញើមតិយោបល់ទៅអង្គភាពមេ') }}</div>
                                <textarea class="form-control form-control-sm mb-2" name="feedback_note" rows="3" required placeholder="{{ localize('feedback_note_required', 'សរសេរមតិយោបល់ទៅអង្គភាពមេ') }}"></textarea>
                                <button type="submit" class="btn btn-sm btn-outline-primary">{{ localize('send_feedback_to_parent', 'ផ្ញើមតិយោបល់') }}</button>
                            </form>
                        @endif

                        @if ($isIncoming && !$hasWorkflowAction)
                            @php
                                $userInChain = in_array($currentUserId, [
                                    (int) ($workflowAssignments['office_comment_user_id'] ?? 0),
                                    (int) ($workflowAssignments['deputy_review_user_id'] ?? 0),
                                    (int) ($workflowAssignments['director_user_id'] ?? 0),
                                ], true);
                                $userCompletedOwnAction = $letter->actions
                                    ->where('acted_by', $currentUserId)
                                    ->contains(function ($row) {
                                        return in_array((string) ($row->action_type ?? ''), [
                                            'office_comment',
                                            'deputy_review',
                                            'director_approved',
                                            'director_rejected',
                                        ], true);
                                    });
                                $currentHandlerId = (int) ($letter->current_handler_user_id ?? 0);
                                $currentHandlerName = $currentHandlerId > 0
                                    ? ($userMap[$currentHandlerId] ?? ('#' . $currentHandlerId))
                                    : localize('unassigned', 'មិនទាន់ចាត់តាំង');
                            @endphp
                            @if ($userInChain && !$isRecipientActor)
                                @if ($userCompletedOwnAction)
                                    <div class="alert alert-info border mb-3">
                                        {{ localize('your_comment_completed_wait_next', 'អ្នកបានផ្តល់យោបល់រួចរាល់។ សូមរង់ចាំអ្នកបន្ទាប់អនុវត្តបន្ត។') }}
                                        {{ localize('current_actor_km', 'អ្នកកំពុងអនុវត្តបច្ចុប្បន្ន') }}:
                                        <strong>{{ $currentHandlerName }}</strong>
                                        ({{ $letter->current_step_label }})
                                    </div>
                                @else
                                    <div class="alert alert-warning border mb-3">
                                        {{ localize('not_your_turn_in_chain', 'មិនទាន់ដល់វេនអ្នកទេ។') }}
                                        {{ localize('current_actor_km', 'អ្នកកំពុងអនុវត្តបច្ចុប្បន្ន') }}:
                                        <strong>{{ $currentHandlerName }}</strong>
                                        ({{ $letter->current_step_label }})
                                    </div>
                                @endif
                            @endif
                        @endif

                        @if (!$hasWorkflowAction)
                            <div class="border rounded p-3 bg-light mt-3">
                                <div class="fw-semibold mb-2">{{ localize('workflow_status_km', 'ស្ថានភាពដំណើរការ') }}</div>
                                <div class="small mb-1">
                                    {{ localize('current_step_km', 'ជំហានបច្ចុប្បន្ន') }}:
                                    <span class="badge bg-primary">{{ $letter->current_step_label }}</span>
                                </div>
                                <div class="small mb-2">
                                    {{ localize('status_km', 'ស្ថានភាព') }}:
                                    <span class="badge bg-secondary">{{ $letter->status }}</span>
                                </div>
                                @if ($distributionTotal > 0)
                                    <div class="small mb-2">
                                        {{ localize('received_km', 'បានទទួល') }}: {{ $distributionReceived }} / {{ $distributionTotal }}
                                        ({{ localize('not_received_km', 'មិនទាន់ទទួល') }}: {{ $distributionPending }})
                                    </div>
                                @endif

                                <div class="small text-muted mb-2">{{ localize('workflow_path_km', 'ផ្លូវដំណើរការ') }}</div>
                                <ol class="small mb-0 ps-3">
                                    @foreach ($flowSteps as $flowKey => $flowLabel)
                                        @php
                                            $isDecisionResolvedStep = $isIncoming
                                                && ($isFinalApproved || $isFinalRejected)
                                                && $flowKey === \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_INCOMING_DIRECTOR_DECISION;
                                            $isClosedResolvedStep = !$isIncoming
                                                && $flowKey === \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_CLOSED
                                                && in_array((string) ($letter->status ?? ''), [
                                                    \Modules\Correspondence\Entities\CorrespondenceLetter::STATUS_COMPLETED,
                                                    \Modules\Correspondence\Entities\CorrespondenceLetter::STATUS_ARCHIVED,
                                                ], true);
                                            $isCurrentFlowStep = $currentFlowStep === $flowKey;
                                            $isCompletedFlowStep = $currentFlowIndex !== false && $loop->index < $currentFlowIndex;
                                            if ($isDecisionResolvedStep || $isClosedResolvedStep) {
                                                $isCurrentFlowStep = false;
                                                $isCompletedFlowStep = true;
                                            }
                                        @endphp
                                        <li class="mb-1">
                                            @if ($isDecisionResolvedStep)
                                                @if ($isFinalApproved)
                                                    <span class="badge bg-success me-1">✓</span>
                                                @else
                                                    <span class="badge bg-danger me-1"><i class="fa fa-times"></i></span>
                                                @endif
                                            @elseif ($isCompletedFlowStep)
                                                <span class="badge bg-success me-1">✓</span>
                                            @elseif ($isCurrentFlowStep)
                                                <span class="badge bg-primary me-1">●</span>
                                            @else
                                                <span class="badge bg-light text-dark border me-1">○</span>
                                            @endif
                                            <span class="{{ $isCurrentFlowStep ? 'fw-semibold text-primary' : 'text-muted' }}">{{ $flowLabel }}</span>
                                        </li>
                                    @endforeach
                                </ol>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">{{ localize('process_guide_and_notes_km', 'សេចក្តីណែនាំដំណើរការ និងកំណត់ចំណាំ') }}</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="fw-semibold mb-2">{{ localize('step_by_step_guide_km', 'សេចក្តីណែនាំតាមជំហាន') }}</div>
                                <ol class="small mb-0 ps-3">
                                    @foreach ($flowSteps as $flowKey => $flowLabel)
                                        @php
                                            $isCurrentGuide = $currentFlowStep === $flowKey;
                                            $guideText = $stepGuides[$flowKey] ?? '';
                                        @endphp
                                        <li class="mb-2">
                                            <div class="{{ $isCurrentGuide ? 'fw-semibold text-primary' : '' }}">{{ $flowLabel }}</div>
                                            @if ($guideText !== '')
                                                <div class="text-muted">{{ $guideText }}</div>
                                            @endif
                                        </li>
                                    @endforeach
                                </ol>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                    <div class="fw-semibold">{{ localize('recent_notes_and_feedback_km', 'កំណត់ចំណាំ និងមតិយោបល់ថ្មីៗ') }}</div>
                                    <div class="btn-group btn-group-sm corr-note-filters" role="group" aria-label="note filters">
                                        <button type="button" class="btn btn-outline-secondary is-active" data-note-filter="all">{{ localize('all', 'ទាំងអស់') }}</button>
                                        <button type="button" class="btn btn-outline-secondary" data-note-filter="notes">{{ localize('notes', 'ចំណារ') }}</button>
                                        <button type="button" class="btn btn-outline-secondary" data-note-filter="feedback">{{ localize('feedback', 'មតិយោបល់') }}</button>
                                    </div>
                                </div>
                                <div class="corr-role-legend mb-2">
                                    <span class="corr-role-legend-item"><span class="corr-avatar is-head">ប</span>{{ localize('head_of_unit', 'ប្រធានអង្គភាព') }}</span>
                                    <span class="corr-role-legend-item"><span class="corr-avatar is-deputy">អ</span>{{ localize('deputy_head', 'អនុប្រធានអង្គភាព') }}</span>
                                    <span class="corr-role-legend-item"><span class="corr-avatar is-manager">គ</span>{{ localize('manager', 'អ្នកគ្រប់គ្រង/ប្រធានការិយាល័យ') }}</span>
                                </div>

                                <div class="corr-note-section" data-note-type="notes">
                                    <div class="small text-muted mb-1">{{ localize('notes', 'ចំណារ') }}</div>
                                    @if ($recentActionNotes->isNotEmpty())
                                        <div class="corr-note-list mb-3">
                                            @foreach ($recentActionNotes as $noteItem)
                                                @php
                                                    $noteByUserId = (int) ($noteItem->acted_by ?? 0);
                                                    $noteByName = $noteByUserId > 0
                                                        ? ($userMap[$noteByUserId] ?? ('#' . $noteByUserId))
                                                        : localize('unknown_user', 'មិនស្គាល់');
                                                    $noteByRole = $noteByUserId > 0
                                                        ? (trim((string) ($userRoleMap[$noteByUserId] ?? '')) ?: '-')
                                                        : '-';
                                                    $noteByNameOnly = trim((string) preg_replace('/\s*\(.*\)$/', '', $noteByName));
                                                    $noteByAvatar = mb_substr($noteByNameOnly !== '' ? $noteByNameOnly : $noteByName, 0, 1);
                                                    $noteAvatarClass = match ($noteByRole) {
                                                        localize('head_of_unit', 'ប្រធានអង្គភាព') => 'is-head',
                                                        localize('deputy_head', 'អនុប្រធានអង្គភាព') => 'is-deputy',
                                                        localize('manager', 'អ្នកគ្រប់គ្រង/ប្រធានការិយាល័យ') => 'is-manager',
                                                        default => 'is-default',
                                                    };
                                                @endphp
                                                <div class="corr-note-item">
                                                    <div class="corr-note-head">
                                                        <span>{{ $actionTypeLabels[$noteItem->action_type] ?? ucfirst(str_replace('_', ' ', (string) $noteItem->action_type)) }}</span>
                                                        <span>{{ optional($noteItem->acted_at)->format('d/m/Y H:i') ?: '-' }}</span>
                                                    </div>
                                                    <div class="small text-muted mb-1 corr-person-wrap">
                                                        <span class="corr-avatar {{ $noteAvatarClass }}">{{ $noteByAvatar }}</span>
                                                        <span>{{ localize('by', 'ដោយ') }}: {{ $noteByName }} @if ($noteByRole !== '-') ({{ $noteByRole }}) @endif</span>
                                                    </div>
                                                    <div>{{ $noteItem->note }}</div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="small text-muted mb-3">{{ localize('no_notes_yet', 'មិនទាន់មានចំណារ') }}</div>
                                    @endif
                                </div>

                                <div class="corr-note-section" data-note-type="feedback">
                                    <div class="small text-muted mb-1">{{ localize('feedback', 'មតិយោបល់') }}</div>
                                    @if ($recentFeedbackNotes->isNotEmpty())
                                        <div class="corr-note-list">
                                            @foreach ($recentFeedbackNotes as $feedbackItem)
                                                @php
                                                    $feedbackAction = $letter->actions
                                                        ->sortByDesc(function ($row) {
                                                            return (string) ($row->acted_at ?? '');
                                                        })
                                                        ->first(function ($row) use ($feedbackItem) {
                                                            return (string) ($row->action_type ?? '') === 'feedback'
                                                                && (int) ($row->target_user_id ?? 0) === (int) ($feedbackItem->target_user_id ?? 0)
                                                                && (int) ($row->target_department_id ?? 0) === (int) ($feedbackItem->target_department_id ?? 0)
                                                                && trim((string) ($row->note ?? '')) === trim((string) ($feedbackItem->feedback_note ?? ''));
                                                        });
                                                    $feedbackByUserId = (int) (($feedbackAction->acted_by ?? 0) ?: ($feedbackItem->target_user_id ?? 0));
                                                    $feedbackByName = $feedbackByUserId > 0
                                                        ? ($userMap[$feedbackByUserId] ?? ('#' . $feedbackByUserId))
                                                        : localize('unknown_user', 'មិនស្គាល់');
                                                    $feedbackByRole = $feedbackByUserId > 0
                                                        ? (trim((string) ($userRoleMap[$feedbackByUserId] ?? '')) ?: '-')
                                                        : '-';
                                                    $feedbackByNameOnly = trim((string) preg_replace('/\s*\(.*\)$/', '', $feedbackByName));
                                                    $feedbackByAvatar = mb_substr($feedbackByNameOnly !== '' ? $feedbackByNameOnly : $feedbackByName, 0, 1);
                                                    $feedbackAvatarClass = match ($feedbackByRole) {
                                                        localize('head_of_unit', 'ប្រធានអង្គភាព') => 'is-head',
                                                        localize('deputy_head', 'អនុប្រធានអង្គភាព') => 'is-deputy',
                                                        localize('manager', 'អ្នកគ្រប់គ្រង/ប្រធានការិយាល័យ') => 'is-manager',
                                                        default => 'is-default',
                                                    };
                                                    $feedbackTarget = $feedbackItem->targetDepartment?->department_name
                                                        ?: (!empty($feedbackItem->target_user_id) ? ($userMap[(int) $feedbackItem->target_user_id] ?? ('#' . $feedbackItem->target_user_id)) : '-');
                                                @endphp
                                                <div class="corr-note-item">
                                                    <div class="corr-note-head">
                                                        <span class="corr-person-wrap">
                                                            <span class="corr-avatar {{ $feedbackAvatarClass }}">{{ $feedbackByAvatar }}</span>
                                                            <span>{{ localize('by', 'ដោយ') }}: {{ $feedbackByName }} @if ($feedbackByRole !== '-') ({{ $feedbackByRole }}) @endif</span>
                                                        </span>
                                                        <span>{{ optional($feedbackItem->feedback_at)->format('d/m/Y H:i') ?: '-' }}</span>
                                                    </div>
                                                    <div class="small text-muted mb-1">{{ localize('target', 'គោលដៅ') }}: {{ $feedbackTarget }}</div>
                                                    <div>{{ $feedbackItem->feedback_note }}</div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="small text-muted">{{ localize('no_feedback_yet', 'មិនទាន់មានមតិយោបល់') }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">{{ localize('distribution_status', 'បែងចែក / ទទួលស្គាល់ / មតិយោបល់') }}</h6>
                    </div>
                    <div class="card-body">
                        @php
                            $toReceived = $letter->distributions
                                ->filter(function ($dist) {
                                    return in_array($dist->distribution_type, [
                                        \Modules\Correspondence\Entities\CorrespondenceLetterDistribution::TYPE_TO,
                                        null,
                                        '',
                                    ], true);
                                })
                                ->whereIn('status', [
                                    \Modules\Correspondence\Entities\CorrespondenceLetterDistribution::STATUS_ACKNOWLEDGED,
                                    \Modules\Correspondence\Entities\CorrespondenceLetterDistribution::STATUS_FEEDBACK_SENT,
                                    \Modules\Correspondence\Entities\CorrespondenceLetterDistribution::STATUS_CLOSED,
                                ])
                                ->count();
                            $toPending = $letter->distributions
                                ->filter(function ($dist) {
                                    return in_array($dist->distribution_type, [
                                        \Modules\Correspondence\Entities\CorrespondenceLetterDistribution::TYPE_TO,
                                        null,
                                        '',
                                    ], true);
                                })
                                ->where('status', \Modules\Correspondence\Entities\CorrespondenceLetterDistribution::STATUS_PENDING_ACK)
                                ->count();
                            $ccReceived = $letter->distributions
                                ->where('distribution_type', \Modules\Correspondence\Entities\CorrespondenceLetterDistribution::TYPE_CC)
                                ->whereIn('status', [
                                    \Modules\Correspondence\Entities\CorrespondenceLetterDistribution::STATUS_ACKNOWLEDGED,
                                    \Modules\Correspondence\Entities\CorrespondenceLetterDistribution::STATUS_FEEDBACK_SENT,
                                    \Modules\Correspondence\Entities\CorrespondenceLetterDistribution::STATUS_CLOSED,
                                ])
                                ->count();
                            $ccPending = $letter->distributions
                                ->where('distribution_type', \Modules\Correspondence\Entities\CorrespondenceLetterDistribution::TYPE_CC)
                                ->where('status', \Modules\Correspondence\Entities\CorrespondenceLetterDistribution::STATUS_PENDING_ACK)
                                ->count();
                        @endphp
                        @if (!$isIncoming)
                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <div class="border rounded p-2 small">
                                        <div class="fw-semibold">{{ localize('to_recipients', 'អ្នកទទួលសំខាន់ (To)') }}</div>
                                        <div>{{ localize('received', 'បានទទួល') }}: {{ $toReceived }}</div>
                                        <div>{{ localize('not_received', 'មិនទាន់ទទួល') }}: {{ $toPending }}</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded p-2 small">
                                        <div class="fw-semibold">{{ localize('cc_recipients', 'ជូនចម្លង (CC)') }}</div>
                                        <div>{{ localize('received', 'បានទទួល') }}: {{ $ccReceived }}</div>
                                        <div>{{ localize('not_received', 'មិនទាន់ទទួល') }}: {{ $ccPending }}</div>
                                    </div>
                                </div>
                            </div>
                        @endif
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ localize('sl', 'ល.រ') }}</th>
                                        <th>{{ localize('target_org_unit', 'អង្គភាពគោលដៅ') }}</th>
                                        <th>{{ localize('target_user', 'អ្នកប្រើគោលដៅ') }}</th>
                                        <th>{{ localize('recipient_type', 'ប្រភេទ') }}</th>
                                        <th>{{ localize('status', 'ស្ថានភាព') }}</th>
                                        <th>{{ localize('acknowledged_at', 'ថ្ងៃ/ម៉ោងទទួលស្គាល់') }}</th>
                                        <th>{{ localize('feedback', 'មតិយោបល់') }}</th>
                                        <th>{{ localize('child_letter', 'លិខិតកូន') }}</th>
                                        <th>{{ localize('action', 'សកម្មភាព') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($letter->distributions as $distribution)
                                        @php
                                            $distStatus = (string) ($distribution->status ?? 'pending_ack');
                                            $statusLabel = $distributionStatuses[$distStatus] ?? $distStatus;
                                            $canActDist = ((int) ($distribution->target_user_id ?? 0) === $currentUserId) || $canDistribute;
                                            $distType = (string) ($distribution->distribution_type ?? \Modules\Correspondence\Entities\CorrespondenceLetterDistribution::TYPE_TO);
                                            $distTypeLabel = $distType === \Modules\Correspondence\Entities\CorrespondenceLetterDistribution::TYPE_CC
                                                ? localize('cc', 'CC')
                                                : localize('to', 'To');
                                            $distributionRowId = (int) ($distribution->id ?? 0);
                                            $isHighlightedDistribution = $highlightDistributionId > 0 && $highlightDistributionId === $distributionRowId;
                                            $isFocusedAcknowledgeAction = $isHighlightedDistribution
                                                && $focusAction === 'acknowledge'
                                                && $canActDist
                                                && $distStatus === \Modules\Correspondence\Entities\CorrespondenceLetterDistribution::STATUS_PENDING_ACK;
                                            $isFocusedFeedbackAction = $isHighlightedDistribution
                                                && $focusAction === 'feedback'
                                                && $canActDist
                                                && $isIncoming
                                                && in_array($distStatus, [\Modules\Correspondence\Entities\CorrespondenceLetterDistribution::STATUS_ACKNOWLEDGED, \Modules\Correspondence\Entities\CorrespondenceLetterDistribution::STATUS_FEEDBACK_SENT], true);
                                            $distributionFeedbackAction = null;
                                            if (trim((string) ($distribution->feedback_note ?? '')) !== '') {
                                                $distributionFeedbackAction = $letter->actions
                                                    ->sortByDesc(function ($row) {
                                                        return (string) ($row->acted_at ?? '');
                                                    })
                                                    ->first(function ($row) use ($distribution) {
                                                        return (string) ($row->action_type ?? '') === 'feedback'
                                                            && (int) ($row->target_user_id ?? 0) === (int) ($distribution->target_user_id ?? 0)
                                                            && (int) ($row->target_department_id ?? 0) === (int) ($distribution->target_department_id ?? 0)
                                                            && trim((string) ($row->note ?? '')) === trim((string) ($distribution->feedback_note ?? ''));
                                                    });
                                            }
                                            $distributionFeedbackByUserId = (int) (($distributionFeedbackAction->acted_by ?? 0) ?: ($distribution->target_user_id ?? 0));
                                            $distributionFeedbackByName = $distributionFeedbackByUserId > 0
                                                ? ($userMap[$distributionFeedbackByUserId] ?? ('#' . $distributionFeedbackByUserId))
                                                : '';
                                            $distributionFeedbackByRole = $distributionFeedbackByUserId > 0
                                                ? (trim((string) ($userRoleMap[$distributionFeedbackByUserId] ?? '')) ?: '')
                                                : '';
                                            $distributionFeedbackByNameOnly = trim((string) preg_replace('/\s*\(.*\)$/', '', $distributionFeedbackByName));
                                            $distributionFeedbackByAvatar = $distributionFeedbackByName !== ''
                                                ? mb_substr($distributionFeedbackByNameOnly !== '' ? $distributionFeedbackByNameOnly : $distributionFeedbackByName, 0, 1)
                                                : '';
                                            $distributionAvatarClass = match ($distributionFeedbackByRole) {
                                                localize('head_of_unit', 'ប្រធានអង្គភាព') => 'is-head',
                                                localize('deputy_head', 'អនុប្រធានអង្គភាព') => 'is-deputy',
                                                localize('manager', 'អ្នកគ្រប់គ្រង/ប្រធានការិយាល័យ') => 'is-manager',
                                                default => 'is-default',
                                            };
                                        @endphp
                                        <tr id="distribution-row-{{ $distributionRowId }}"
                                            class="{{ $isHighlightedDistribution ? 'table-warning correspondence-highlight-row' : '' }}">
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $distribution->targetDepartment?->department_name ?: '-' }}</td>
                                            <td>{{ !empty($distribution->target_user_id) ? ($userMap[(int) $distribution->target_user_id] ?? ('#' . $distribution->target_user_id)) : '-' }}</td>
                                            <td>{{ $distTypeLabel }}</td>
                                            <td>{{ $statusLabel }}</td>
                                            <td>{{ optional($distribution->acknowledged_at)->format('d/m/Y H:i') ?: '-' }}</td>
                                            <td>
                                                @if (!empty($distribution->feedback_note))
                                                    <div>{{ $distribution->feedback_note }}</div>
                                                    @if ($distributionFeedbackByName !== '')
                                                        <div class="small text-muted corr-person-wrap">
                                                            @if ($distributionFeedbackByAvatar !== '')
                                                                <span class="corr-avatar {{ $distributionAvatarClass }}">{{ $distributionFeedbackByAvatar }}</span>
                                                            @endif
                                                            <span>
                                                                {{ localize('by', 'ដោយ') }}: {{ $distributionFeedbackByName }}
                                                                @if ($distributionFeedbackByRole !== '')
                                                                    ({{ $distributionFeedbackByRole }})
                                                                @endif
                                                            </span>
                                                        </div>
                                                    @endif
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>
                                                @if (!empty($distribution->child_letter_id) && $distribution->childLetter)
                                                    <a href="{{ route('correspondence.show', $distribution->childLetter->id) }}">
                                                        #{{ $distribution->childLetter->id }}
                                                    </a>
                                                    <div class="small text-muted">{{ $distribution->childLetter->assignedDepartment?->department_name ?: '-' }}</div>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td id="distribution-action-{{ $distributionRowId }}">
                                                @if ($canActDist)
                                                    @if ($distStatus === \Modules\Correspondence\Entities\CorrespondenceLetterDistribution::STATUS_PENDING_ACK)
                                                        <form method="POST" action="{{ route('correspondence.acknowledge', $distribution->id) }}" class="mb-1 {{ $isFocusedAcknowledgeAction ? 'correspondence-action-focus rounded p-1 border border-primary' : '' }}">
                                                            @csrf
                                                            <input type="hidden" name="note" value="{{ localize('received', 'បានទទួល') }}">
                                                            <button class="btn btn-sm btn-outline-success w-100">{{ localize('acknowledge', 'ទទួលស្គាល់') }}</button>
                                                        </form>
                                                    @endif

                                                    @if ($isIncoming && in_array($distStatus, [\Modules\Correspondence\Entities\CorrespondenceLetterDistribution::STATUS_ACKNOWLEDGED, \Modules\Correspondence\Entities\CorrespondenceLetterDistribution::STATUS_FEEDBACK_SENT], true))
                                                        <form method="POST" action="{{ route('correspondence.feedback', $distribution->id) }}" class="{{ $isFocusedFeedbackAction ? 'correspondence-action-focus rounded p-1 border border-primary' : '' }}">
                                                            @csrf
                                                            <textarea name="feedback_note" rows="2" class="form-control form-control-sm mb-1" required>{{ $distribution->feedback_note }}</textarea>
                                                            <button class="btn btn-sm btn-outline-primary w-100">{{ localize('send_feedback', 'ផ្ញើមតិយោបល់') }}</button>
                                                        </form>
                                                    @endif
                                                @else
                                                    -
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center text-muted">{{ localize('no_distribution_records', 'មិនទាន់មានកំណត់ត្រាបែងចែក') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">{{ localize('action_history', 'ប្រវត្តិសកម្មភាព') }}</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ localize('sl', 'ល.រ') }}</th>
                                        <th>{{ localize('date', 'កាលបរិច្ឆេទ') }}</th>
                                        <th>{{ localize('action_type', 'ប្រភេទសកម្មភាព') }}</th>
                                        <th>{{ localize('by', 'ដោយ') }}</th>
                                        <th>{{ localize('target', 'គោលដៅ') }}</th>
                                        <th>{{ localize('note', 'ចំណារ') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($letter->actions as $item)
                                        @php
                                            $historyActorUserId = (int) ($item->acted_by ?? 0);
                                            $historyActorName = $historyActorUserId > 0
                                                ? ($userMap[$historyActorUserId] ?? ('#' . $historyActorUserId))
                                                : '-';
                                            $historyActorRole = $historyActorUserId > 0
                                                ? (trim((string) ($userRoleMap[$historyActorUserId] ?? '')) ?: '')
                                                : '';
                                        @endphp
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ optional($item->acted_at)->format('d/m/Y H:i') ?: '-' }}</td>
                                            <td>{{ $actionTypeLabels[$item->action_type] ?? ucfirst(str_replace('_', ' ', (string) $item->action_type)) }}</td>
                                            <td>
                                                <div>{{ $historyActorName }}</div>
                                                @if ($historyActorRole !== '')
                                                    <div class="small text-muted">{{ $historyActorRole }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                @php
                                                    $targetParts = [];
                                                    if (!empty($item->target_user_id)) {
                                                        $targetUserId = (int) $item->target_user_id;
                                                        $targetUserName = $userMap[$targetUserId] ?? ('#' . $targetUserId);
                                                        $targetUserRole = trim((string) ($userRoleMap[$targetUserId] ?? ''));
                                                        $targetParts[] = $targetUserRole !== '' ? ($targetUserName . ' (' . $targetUserRole . ')') : $targetUserName;
                                                    }
                                                    if (!empty($item->target_department_id)) {
                                                        $targetParts[] = $departmentMap[(int) $item->target_department_id] ?? ('#' . $item->target_department_id);
                                                    }
                                                @endphp
                                                {{ !empty($targetParts) ? implode(' | ', $targetParts) : '-' }}
                                            </td>
                                            <td>{{ $item->note ?: '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">{{ localize('no_action_history', 'មិនទាន់មានប្រវត្តិសកម្មភាព') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="attachmentPreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">{{ localize('attachment_preview', 'មើលឯកសារភ្ជាប់') }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="attachment-preview-name fw-semibold mb-2"></div>
                    <div class="attachment-preview-frame border rounded" style="min-height: 70vh;">
                        <iframe class="w-100 h-100 d-none" title="Attachment preview"></iframe>
                        <img class="img-fluid d-none" alt="Attachment preview">
                        <div class="attachment-preview-fallback text-muted p-3 d-none">
                            {{ localize('preview_not_supported', 'មិនគាំទ្រការមើលផ្ទាល់សម្រាប់ប្រភេទឯកសារនេះ។ សូមទាញយកដើម្បីមើល។') }}
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="#" class="btn btn-outline-secondary js-attachment-download" download>
                        {{ localize('download', 'ទាញយក') }}
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        {{ localize('close', 'បិទ') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('css')
    <style>
        .correspondence-highlight-row {
            transition: background-color 0.6s ease;
        }

        .correspondence-action-focus {
            background: #eef6ff;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.12);
        }

        .corr-timeline {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 10px;
        }

        .corr-progress-wrap {
            width: 100%;
            height: 8px;
            border-radius: 999px;
            background: #e9ecef;
            overflow: hidden;
        }

        .corr-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #20c997 0%, #198754 100%);
            transition: width 0.3s ease;
        }

        .corr-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .corr-legend-item {
            font-size: 11px;
            border: 1px solid #dee2e6;
            border-radius: 999px;
            padding: 3px 10px;
            background: #f8f9fa;
            color: #495057;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .corr-legend-item.is-done {
            background: #ecf9f1;
            border-color: #b8e7cd;
            color: #17653f;
        }

        .corr-legend-item.is-current {
            background: #eaf2ff;
            border-color: #8bb6ff;
            color: #0d47a1;
        }

        .corr-step {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 10px;
            background: #f8f9fa;
            min-height: 74px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .corr-step-index {
            width: 28px;
            height: 28px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            margin-bottom: 6px;
            border: 1px solid #adb5bd;
            background: #fff;
            color: #495057;
            font-weight: 600;
        }

        .corr-step-label {
            font-size: 12px;
            font-weight: 600;
            line-height: 1.3;
            color: #495057;
        }

        .corr-step-owner {
            margin-top: 3px;
            font-size: 11px;
            line-height: 1.25;
            color: #6c757d;
        }

        .corr-step.is-done {
            background: #ecf9f1;
            border-color: #b8e7cd;
        }

        .corr-step.is-done .corr-step-index {
            background: #198754;
            color: #fff;
            border-color: #198754;
        }

        .corr-step.is-current {
            background: #eaf2ff;
            border-color: #8bb6ff;
            box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.08) inset;
        }

        .corr-step.is-current .corr-step-index {
            background: #0d6efd;
            color: #fff;
            border-color: #0d6efd;
        }

        .corr-step.is-current .corr-step-label {
            color: #0d47a1;
        }

        .corr-note-list {
            max-height: 280px;
            overflow: auto;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 6px;
            background: #fafbfc;
        }

        .corr-note-item {
            border-bottom: 1px dashed #dee2e6;
            padding: 7px 4px;
            font-size: 12px;
        }

        .corr-note-item:last-child {
            border-bottom: 0;
        }

        .corr-note-head {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            font-size: 11px;
            color: #6c757d;
            margin-bottom: 3px;
        }

        .corr-person-wrap {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .corr-avatar {
            width: 18px;
            height: 18px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-transform: uppercase;
            flex-shrink: 0;
        }

        .corr-avatar.is-default {
            background: #dbeafe;
            color: #0d47a1;
        }

        .corr-avatar.is-head {
            background: #fde68a;
            color: #854d0e;
        }

        .corr-avatar.is-deputy {
            background: #ddd6fe;
            color: #5b21b6;
        }

        .corr-avatar.is-manager {
            background: #bbf7d0;
            color: #166534;
        }

        .corr-note-filters .btn.is-active {
            background: #0d6efd;
            color: #fff;
            border-color: #0d6efd;
        }

        .corr-role-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 6px;
        }

        .corr-role-legend-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            color: #6c757d;
        }
    </style>
@endpush

@push('js')
    <script>
        (function($) {
            "use strict";

            if (!$ || !$.fn || !$.fn.select2) {
                return;
            }

            $('.js-user-search').select2({
                width: '100%',
                allowClear: true,
                placeholder: function() {
                    return $(this).data('placeholder') || 'ជ្រើសរើសអ្នកប្រើ';
                },
                ajax: {
                    url: @json(route('correspondence.users.search')),
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            q: params.term || ''
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: (data && data.results) ? data.results : []
                        };
                    }
                }
            });

            $('.js-user-multi').select2({
                width: '100%',
                allowClear: true,
                closeOnSelect: false,
                placeholder: function() {
                    return $(this).data('placeholder') || 'ជ្រើសរើសអ្នកប្រើ';
                },
                ajax: {
                    url: @json(route('correspondence.users.search')),
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            q: params.term || ''
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: (data && data.results) ? data.results : []
                        };
                    }
                }
            });

            $('.js-org-multi').select2({
                width: '100%',
                allowClear: true,
                closeOnSelect: false,
                placeholder: function() {
                    return $(this).data('placeholder') || 'ជ្រើសរើសអង្គភាព';
                }
            });

        })(window.jQuery);
    </script>
    <script>
        (function() {
            "use strict";
            var focusAction = @json($focusAction !== '' ? $focusAction : null);
            var highlightedRowId = @json($highlightDistributionId > 0 ? 'distribution-row-' . $highlightDistributionId : null);
            var targetElement = null;

            if (highlightedRowId) {
                targetElement = document.getElementById(highlightedRowId);
            }

            if (!targetElement && focusAction) {
                var workflowActionId = 'workflow-action-' + String(focusAction).replace(/_/g, '-');
                targetElement = document.getElementById(workflowActionId);
            }

            if (!targetElement) {
                return;
            }

            setTimeout(function() {
                try {
                    targetElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                } catch (e) {
                    targetElement.scrollIntoView();
                }
            }, 250);
        })();
    </script>
    <script>
        (function() {
            "use strict";

            var buttons = document.querySelectorAll('[data-note-filter]');
            var sections = document.querySelectorAll('.corr-note-section[data-note-type]');

            if (!buttons.length || !sections.length) {
                return;
            }

            function applyFilter(filterType) {
                buttons.forEach(function(btn) {
                    btn.classList.toggle('is-active', btn.getAttribute('data-note-filter') === filterType);
                });

                sections.forEach(function(section) {
                    var type = section.getAttribute('data-note-type');
                    var show = filterType === 'all' || filterType === type;
                    section.classList.toggle('d-none', !show);
                });
            }

            buttons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    applyFilter(btn.getAttribute('data-note-filter') || 'all');
                });
            });

            applyFilter('all');
        })();
    </script>
@endpush


