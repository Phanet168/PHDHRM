@extends('backend.layouts.app')
@section('title', localize('mission_detail', 'ព័ត៌មានបេសកម្ម'))
@section('content')
    @include('humanresource::attendance_header')

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-semi-bold">
                <i class="fa fa-file-alt text-primary me-1"></i>
                {{ localize('mission_detail', 'ព័ត៌មានបេសកម្ម') }}
            </h6>
            <a href="{{ route('missions.index') }}" class="btn btn-secondary btn-sm">
                <i class="fa fa-arrow-left me-1"></i>{{ localize('back', 'ត្រឡប់ក្រោយ') }}
            </a>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="p-3 border rounded h-100">
                        <h6 class="fw-semi-bold mb-3">{{ localize('general_information', 'ព័ត៌មានទូទៅ') }}</h6>
                        <p class="mb-1"><strong>{{ localize('title', 'ចំណងជើង') }}:</strong> {{ $mission->title }}</p>
                        <p class="mb-1"><strong>{{ localize('destination', 'គោលដៅ') }}:</strong> {{ $mission->destination }}</p>
                        <p class="mb-1"><strong>{{ localize('period', 'រយៈពេល') }}:</strong>
                            {{ optional($mission->start_date)->format('Y-m-d') }} to {{ optional($mission->end_date)->format('Y-m-d') }}
                        </p>
                        <p class="mb-1"><strong>{{ localize('status', 'ស្ថានភាព') }}:</strong> {{ strtoupper($mission->status) }}</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 border rounded h-100">
                        <h6 class="fw-semi-bold mb-3">{{ localize('purpose', 'គោលបំណង') }}</h6>
                        <p class="mb-0 text-muted">{{ $mission->purpose ?: '-' }}</p>
                    </div>
                </div>

                <div class="col-12">
                    <div class="p-3 border rounded">
                        <h6 class="fw-semi-bold mb-3">{{ localize('assigned_employees', 'បុគ្គលិកដែលបានចាត់តាំង') }}</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>{{ localize('sl', 'លរ') }}</th>
                                        <th>{{ localize('employee', 'បុគ្គលិក') }}</th>
                                        <th>{{ localize('status', 'ស្ថានភាព') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($mission->assignments as $assignment)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ optional($assignment->employee)->full_name ?: ('ID: ' . $assignment->employee_id) }}</td>
                                            <td><span class="badge badge-info-soft">{{ strtoupper($assignment->status) }}</span></td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center text-muted py-3">{{ localize('no_assigned_employee', 'មិនមានបុគ្គលិក') }}</td>
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
