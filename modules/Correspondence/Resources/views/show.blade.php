@extends('backend.layouts.app')

@section('title', localize('correspondence_detail', 'Letter detail'))

@section('content')
    @php
        $isIncoming = $letter->letter_type === \Modules\Correspondence\Entities\CorrespondenceLetter::TYPE_INCOMING;
        $step = (string) ($letter->current_step ?? '');
        $approved = (string) ($letter->final_decision ?? '') === \Modules\Correspondence\Entities\CorrespondenceLetter::DECISION_APPROVED;
        $currentUserId = (int) auth()->id();
        $isChild = (bool) ($isChildLetter ?? false);
        $parentLetter = $letter->parentLetter;
    @endphp

    <div class="body-content">
        @include('correspondence::_nav')

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">{{ localize('correspondence_detail', 'Letter detail') }}</h6>
                <a href="{{ $isIncoming ? route('correspondence.incoming') : route('correspondence.outgoing') }}" class="btn btn-sm btn-secondary">
                    {{ localize('back', 'Back') }}
                </a>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4"><strong>{{ localize('letter_type', 'Letter type') }}:</strong> {{ $isIncoming ? localize('incoming_letters', 'Incoming') : localize('outgoing_letters', 'Outgoing') }}</div>
                    <div class="col-md-4"><strong>{{ localize('registry_no', 'Registry no') }}:</strong> {{ $letter->registry_no ?: '-' }}</div>
                    <div class="col-md-4"><strong>{{ localize('letter_no', 'Letter no') }}:</strong> {{ $letter->letter_no ?: '-' }}</div>
                    <div class="col-md-12"><strong>{{ localize('subject', 'Subject') }}:</strong> {{ $letter->subject }}</div>
                    <div class="col-md-6"><strong>{{ localize('from_org', 'From organization') }}:</strong> {{ $letter->from_org ?: '-' }}</div>
                    <div class="col-md-6"><strong>{{ localize('to_org', 'To organization') }}:</strong> {{ $letter->to_org ?: '-' }}</div>
                    <div class="col-md-4"><strong>{{ localize('current_step', 'Current step') }}:</strong> {{ $letter->current_step_label }}</div>
                    <div class="col-md-4"><strong>{{ localize('status', 'Status') }}:</strong> {{ $letter->status }}</div>
                    <div class="col-md-4"><strong>{{ localize('final_decision', 'Final decision') }}:</strong> {{ $letter->final_decision ?: '-' }}</div>
                    <div class="col-md-6"><strong>{{ localize('origin_org_unit', 'Origin org unit') }}:</strong> {{ $letter->originDepartment?->department_name ?: '-' }}</div>
                    <div class="col-md-6"><strong>{{ localize('assigned_org_unit', 'Assigned org unit') }}:</strong> {{ $letter->assignedDepartment?->department_name ?: '-' }}</div>
                    @if ($isChild && $parentLetter)
                        <div class="col-md-12">
                            <strong>{{ localize('parent_letter', 'Parent letter') }}:</strong>
                            <a href="{{ route('correspondence.show', $parentLetter->id) }}">#{{ $parentLetter->id }} - {{ $parentLetter->subject }}</a>
                        </div>
                    @endif
                    @if ($letter->childLetters->count() > 0)
                        <div class="col-md-12">
                            <strong>{{ localize('child_letters', 'Child letters') }}:</strong>
                            @foreach ($letter->childLetters as $childLetter)
                                <a href="{{ route('correspondence.show', $childLetter->id) }}" class="badge bg-light text-dark border me-1 mb-1">
                                    #{{ $childLetter->id }} {{ $childLetter->assignedDepartment?->department_name ?: '-' }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                    <div class="col-md-12"><strong>{{ localize('summary', 'Summary') }}:</strong> {{ $letter->summary ?: '-' }}</div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header">
                        <h6 class="mb-0">{{ localize('workflow_actions', 'Workflow actions') }}</h6>
                    </div>
                    <div class="card-body">
                        @if ($isIncoming)
                            @if (in_array($step, [\Modules\Correspondence\Entities\CorrespondenceLetter::STEP_INCOMING_RECEIVED, \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_INCOMING_DELEGATED], true) && $canDelegate)
                                <form method="POST" action="{{ route('correspondence.progress', $letter->id) }}" class="mb-3 border rounded p-2">
                                    @csrf
                                    <input type="hidden" name="action" value="delegate">
                                    <div class="fw-semibold mb-2">{{ localize('delegate_assign', 'Delegate / assign related unit') }}</div>
                                    <div class="mb-2">
                                        <label class="form-label">{{ localize('org_unit', 'Org unit') }}</label>
                                        <select class="form-select form-select-sm" name="assigned_department_id">
                                            <option value="">-- {{ localize('select', 'Select') }} --</option>
                                            @foreach (($orgUnitOptions ?? collect()) as $unit)
                                                <option value="{{ $unit->id }}" {{ (int) $letter->assigned_department_id === (int) $unit->id ? 'selected' : '' }}>
                                                    {{ $unit->path ?? $unit->label ?? ('#' . $unit->id) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">{{ localize('related_user', 'Related user') }}</label>
                                        <select class="form-select form-select-sm js-user-search" name="target_user_id" data-placeholder="{{ localize('select_user', 'Select user') }}">
                                            @if (!empty($letter->current_handler_user_id) && !empty($userMap[(int) $letter->current_handler_user_id]))
                                                <option value="{{ (int) $letter->current_handler_user_id }}" selected>{{ $userMap[(int) $letter->current_handler_user_id] }}</option>
                                            @endif
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">{{ localize('note', 'Note') }}</label>
                                        <textarea class="form-control form-control-sm" name="note" rows="2"></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-primary">{{ localize('save', 'Save') }}</button>
                                </form>
                            @endif

                            @if ($step === \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_INCOMING_DELEGATED && $canOfficeComment)
                                <form method="POST" action="{{ route('correspondence.progress', $letter->id) }}" class="mb-3 border rounded p-2">
                                    @csrf
                                    <input type="hidden" name="action" value="office_comment">
                                    <div class="fw-semibold mb-2">{{ localize('office_comment', 'Office chief comment') }}</div>
                                    <textarea class="form-control form-control-sm mb-2" name="note" rows="3" required></textarea>
                                    <button type="submit" class="btn btn-sm btn-primary">{{ localize('submit', 'Submit') }}</button>
                                </form>
                            @endif

                            @if ($step === \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_INCOMING_OFFICE_COMMENT && $canDeputyReview)
                                <form method="POST" action="{{ route('correspondence.progress', $letter->id) }}" class="mb-3 border rounded p-2">
                                    @csrf
                                    <input type="hidden" name="action" value="deputy_review">
                                    <div class="fw-semibold mb-2">{{ localize('deputy_review', 'Deputy director review') }}</div>
                                    <textarea class="form-control form-control-sm mb-2" name="note" rows="3" required></textarea>
                                    <button type="submit" class="btn btn-sm btn-primary">{{ localize('submit', 'Submit') }}</button>
                                </form>
                            @endif

                            @if ($step === \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_INCOMING_DEPUTY_REVIEW && $canDirectorDecision)
                                <form method="POST" action="{{ route('correspondence.progress', $letter->id) }}" class="mb-3 border rounded p-2">
                                    @csrf
                                    <input type="hidden" name="action" value="director_decision">
                                    <div class="fw-semibold mb-2">{{ localize('director_decision', 'Director decision') }}</div>
                                    <select name="decision" class="form-select form-select-sm mb-2" required>
                                        <option value="">-- {{ localize('select_decision', 'Select decision') }} --</option>
                                        <option value="approved">{{ localize('approved', 'Approved') }}</option>
                                        <option value="rejected">{{ localize('rejected', 'Rejected') }}</option>
                                    </select>
                                    <textarea class="form-control form-control-sm mb-2" name="note" rows="3" placeholder="{{ localize('decision_note', 'Decision note') }}"></textarea>
                                    <button type="submit" class="btn btn-sm btn-primary">{{ localize('submit', 'Submit') }}</button>
                                </form>
                            @endif
                        @endif

                        @if ((!$isIncoming && in_array($step, [\Modules\Correspondence\Entities\CorrespondenceLetter::STEP_OUTGOING_DRAFT, \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_OUTGOING_DISTRIBUTED], true)) || ($isIncoming && $step === \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_INCOMING_DIRECTOR_DECISION && $approved) || ($isIncoming && $step === \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_INCOMING_DISTRIBUTED))
                            @if ($canDistribute)
                                <form method="POST" action="{{ route('correspondence.distribute', $letter->id) }}" class="mb-3 border rounded p-2">
                                    @csrf
                                    <div class="fw-semibold mb-2">{{ localize('distribute_to_recipients', 'Distribute to recipients') }}</div>
                                    <div class="mb-2">
                                        <label class="form-label">{{ localize('target_org_unit', 'Target org unit') }}</label>
                                        <select class="form-select form-select-sm" name="target_department_ids[]" multiple size="8">
                                            @foreach (($orgUnitOptions ?? collect()) as $unit)
                                                <option value="{{ $unit->id }}">{{ $unit->path ?? $unit->label ?? ('#' . $unit->id) }}</option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted">{{ localize('can_select_multiple_units', 'You can select multiple org units.') }}</small>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">{{ localize('target_user', 'Target user') }}</label>
                                        <select class="form-select form-select-sm js-user-search" name="target_user_id" data-placeholder="{{ localize('select_user', 'Select user') }}"></select>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">{{ localize('note', 'Note') }}</label>
                                        <textarea class="form-control form-control-sm" name="note" rows="2"></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-primary">{{ localize('distribute', 'Distribute') }}</button>
                                </form>
                            @endif
                        @endif

                        @if ($canClose && $step !== \Modules\Correspondence\Entities\CorrespondenceLetter::STEP_CLOSED)
                            <form method="POST" action="{{ route('correspondence.progress', $letter->id) }}" class="border rounded p-2">
                                @csrf
                                <input type="hidden" name="action" value="close">
                                <div class="fw-semibold mb-2">{{ localize('close_workflow', 'Close workflow') }}</div>
                                <textarea class="form-control form-control-sm mb-2" name="note" rows="2" placeholder="{{ localize('close_note', 'Close note (optional)') }}"></textarea>
                                <button type="submit" class="btn btn-sm btn-success">{{ localize('close', 'Close') }}</button>
                            </form>
                        @endif

                        @if (!empty($canSendParentFeedback))
                            <form method="POST" action="{{ route('correspondence.feedback_parent', $letter->id) }}" class="mt-3 border rounded p-2">
                                @csrf
                                <div class="fw-semibold mb-2">{{ localize('feedback_to_parent_department', 'Send feedback to parent department') }}</div>
                                <textarea class="form-control form-control-sm mb-2" name="feedback_note" rows="3" required placeholder="{{ localize('feedback_note_required', 'Write feedback to parent department') }}"></textarea>
                                <button type="submit" class="btn btn-sm btn-outline-primary">{{ localize('send_feedback_to_parent', 'Send feedback') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">{{ localize('distribution_status', 'Distribution / acknowledgement / feedback') }}</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ localize('sl', 'SL') }}</th>
                                        <th>{{ localize('target_org_unit', 'Target org unit') }}</th>
                                        <th>{{ localize('target_user', 'Target user') }}</th>
                                        <th>{{ localize('status', 'Status') }}</th>
                                        <th>{{ localize('acknowledged_at', 'Acknowledged at') }}</th>
                                        <th>{{ localize('feedback', 'Feedback') }}</th>
                                        <th>{{ localize('child_letter', 'Child letter') }}</th>
                                        <th>{{ localize('action', 'Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($letter->distributions as $distribution)
                                        @php
                                            $distStatus = (string) ($distribution->status ?? 'pending_ack');
                                            $statusLabel = $distributionStatuses[$distStatus] ?? $distStatus;
                                            $canActDist = ((int) ($distribution->target_user_id ?? 0) === $currentUserId) || $canDistribute;
                                        @endphp
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $distribution->targetDepartment?->department_name ?: '-' }}</td>
                                            <td>{{ !empty($distribution->target_user_id) ? ($userMap[(int) $distribution->target_user_id] ?? ('#' . $distribution->target_user_id)) : '-' }}</td>
                                            <td>{{ $statusLabel }}</td>
                                            <td>{{ optional($distribution->acknowledged_at)->format('d/m/Y H:i') ?: '-' }}</td>
                                            <td>{{ $distribution->feedback_note ?: '-' }}</td>
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
                                            <td>
                                                @if ($canActDist)
                                                    @if ($distStatus === \Modules\Correspondence\Entities\CorrespondenceLetterDistribution::STATUS_PENDING_ACK)
                                                        <form method="POST" action="{{ route('correspondence.acknowledge', $distribution->id) }}" class="mb-1">
                                                            @csrf
                                                            <input type="hidden" name="note" value="{{ localize('received', 'Received') }}">
                                                            <button class="btn btn-sm btn-outline-success w-100">{{ localize('acknowledge', 'Acknowledge') }}</button>
                                                        </form>
                                                    @endif

                                                    @if (in_array($distStatus, [\Modules\Correspondence\Entities\CorrespondenceLetterDistribution::STATUS_ACKNOWLEDGED, \Modules\Correspondence\Entities\CorrespondenceLetterDistribution::STATUS_FEEDBACK_SENT], true))
                                                        <form method="POST" action="{{ route('correspondence.feedback', $distribution->id) }}">
                                                            @csrf
                                                            <textarea name="feedback_note" rows="2" class="form-control form-control-sm mb-1" required>{{ $distribution->feedback_note }}</textarea>
                                                            <button class="btn btn-sm btn-outline-primary w-100">{{ localize('send_feedback', 'Send feedback') }}</button>
                                                        </form>
                                                    @endif
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">{{ localize('no_distribution_records', 'No distribution records') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">{{ localize('action_history', 'Action history') }}</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ localize('sl', 'SL') }}</th>
                                        <th>{{ localize('date', 'Date') }}</th>
                                        <th>{{ localize('action_type', 'Action type') }}</th>
                                        <th>{{ localize('by', 'By') }}</th>
                                        <th>{{ localize('target', 'Target') }}</th>
                                        <th>{{ localize('note', 'Note') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($letter->actions as $item)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ optional($item->acted_at)->format('d/m/Y H:i') ?: '-' }}</td>
                                            <td>{{ $item->action_type }}</td>
                                            <td>{{ !empty($item->acted_by) ? ($userMap[(int) $item->acted_by] ?? ('#' . $item->acted_by)) : '-' }}</td>
                                            <td>
                                                @php
                                                    $targetParts = [];
                                                    if (!empty($item->target_user_id)) {
                                                        $targetParts[] = $userMap[(int) $item->target_user_id] ?? ('#' . $item->target_user_id);
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
                                            <td colspan="6" class="text-center text-muted">{{ localize('no_action_history', 'No action history') }}</td>
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
@endsection

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
                    return $(this).data('placeholder') || 'Select user';
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
        })(window.jQuery);
    </script>
@endpush
