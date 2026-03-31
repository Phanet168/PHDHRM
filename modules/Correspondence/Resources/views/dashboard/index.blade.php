@extends('backend.layouts.app')

@section('title', localize('correspondence_management', 'Administrative correspondence'))

@section('content')
    <div class="body-content">
        @include('correspondence::_nav')

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="p-3 border rounded bg-light h-100">
                    <div class="text-muted">{{ localize('incoming_letters', 'Incoming letters') }}</div>
                    <h3 class="mb-0">{{ $incomingCount }}</h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3 border rounded bg-light h-100">
                    <div class="text-muted">{{ localize('outgoing_letters', 'Outgoing letters') }}</div>
                    <h3 class="mb-0">{{ $outgoingCount }}</h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3 border rounded bg-light h-100">
                    <div class="text-muted">{{ localize('pending_processing', 'Pending/In progress') }}</div>
                    <h3 class="mb-0">{{ $pendingCount }}</h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3 border rounded bg-light h-100">
                    <div class="text-muted">{{ localize('completed', 'Completed') }}</div>
                    <h3 class="mb-0">{{ $completedCount }}</h3>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">{{ localize('correspondence_workflow', 'Workflow summary') }}</h6>
            </div>
            <div class="card-body">
                <ol class="mb-0">
                    <li>{{ localize('incoming_receiver_step', 'Incoming receiver registers letter') }}</li>
                    <li>{{ localize('delegate_step', 'Head/Deputy delegates related unit/person') }}</li>
                    <li>{{ localize('office_comment_step', 'Office chief provides comment') }}</li>
                    <li>{{ localize('deputy_review_step', 'Deputy director reviews and comments') }}</li>
                    <li>{{ localize('director_decision_step', 'Director approves/finalizes') }}</li>
                    <li>{{ localize('distribution_step', 'Letter manager distributes to recipients') }}</li>
                    <li>{{ localize('ack_feedback_step', 'Recipients acknowledge and return feedback') }}</li>
                </ol>
            </div>
        </div>
    </div>
@endsection
