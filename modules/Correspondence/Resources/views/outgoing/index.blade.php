@extends('backend.layouts.app')

@section('title', localize('outgoing_letters', 'Outgoing letters'))

@section('content')
    <div class="body-content">
        @include('correspondence::_nav')

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0">{{ localize('outgoing_letters', 'Outgoing letters') }}</h6>
                <form method="GET" class="d-flex gap-2">
                    <input type="text" class="form-control form-control-sm" name="search" value="{{ $search ?? '' }}"
                        placeholder="{{ localize('search', 'Search') }}" style="width: 260px;">
                    <button type="submit" class="btn btn-sm btn-primary">{{ localize('search', 'Search') }}</button>
                </form>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped mb-0 align-middle">
                        <thead>
                            <tr>
                                <th width="5%">{{ localize('sl', 'SL') }}</th>
                                <th>{{ localize('registry_no', 'Registry no') }}</th>
                                <th>{{ localize('letter_no', 'Letter no') }}</th>
                                <th>{{ localize('subject', 'Subject') }}</th>
                                <th>{{ localize('to', 'To') }}</th>
                                <th>{{ localize('letter_date', 'Letter date') }}</th>
                                <th>{{ localize('status', 'Status') }}</th>
                                <th>{{ localize('workflow_step', 'Workflow step') }}</th>
                                <th width="10%">{{ localize('action', 'Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($letters as $index => $letter)
                                <tr>
                                    <td>{{ $letters->firstItem() + $index }}</td>
                                    <td>{{ $letter->registry_no ?: '-' }}</td>
                                    <td>{{ $letter->letter_no ?: '-' }}</td>
                                    <td>{{ $letter->subject }}</td>
                                    <td>{{ $letter->to_org ?: '-' }}</td>
                                    <td>{{ optional($letter->letter_date)->format('d/m/Y') ?: '-' }}</td>
                                    <td>{{ $letter->status }}</td>
                                    <td>{{ $letter->current_step_label }}</td>
                                    <td>
                                        <a href="{{ route('correspondence.show', $letter->id) }}" class="btn btn-sm btn-info">
                                            {{ localize('show', 'Show') }}
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted">
                                        {{ localize('no_data_available', 'No data available') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($letters->hasPages())
                <div class="card-footer">
                    {{ $letters->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
