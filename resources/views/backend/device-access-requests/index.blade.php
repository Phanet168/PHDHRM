@extends('backend.layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 d-inline-block me-3">Device Access Requests</h1>
        </div>
        <div class="col-md-4 text-end">
            <span class="badge bg-warning me-2">
                <i class="fas fa-hourglass-half"></i> Pending: {{ $requests->where('status', 'pending')->count() ?? 0 }}
            </span>
            <span class="badge bg-success">
                <i class="fas fa-check-circle"></i> Approved: {{ $requests->where('status', 'approved')->count() ?? 0 }}
            </span>
        </div>
    </div>

    @if($message = Session::get('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ $message }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header bg-light">
            <form method="GET" action="{{ route('device-access-requests.index') }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="status" class="form-label">Status Filter</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Status</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status }}" {{ $currentStatus == $status ? 'selected' : '' }}>
                                {{ ucfirst($status) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           placeholder="Name, Email, Phone, Device, Machine..." value="{{ $searchTerm }}">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Device Info</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $request)
                        <tr>
                            <td><strong>{{ $request->full_name }}</strong></td>
                            <td>{{ $request->email }}</td>
                            <td>{{ $request->phone ?? 'N/A' }}</td>
                            <td>
                                <small class="text-muted">{{ $request->device_summary }}</small><br>
                                <button type="button" class="btn btn-sm btn-outline-secondary mt-1" 
                                        data-bs-toggle="modal" data-bs-target="#deviceModal{{ $request->id }}">
                                    <i class="fas fa-json"></i> View Details
                                </button>
                            </td>
                            <td>
                                @if($request->status === 'pending')
                                    <span class="badge bg-warning">Pending</span>
                                @elseif($request->status === 'approved')
                                    <span class="badge bg-success">Approved</span>
                                @else
                                    <span class="badge bg-danger">Rejected</span>
                                @endif
                            </td>
                            <td>
                                <small>{{ $request->created_at->format('M d, Y H:i') }}</small>
                                @if($request->reviewed_at)
                                    <br><small class="text-muted">Reviewed: {{ $request->reviewed_at->format('M d, Y H:i') }}</small>
                                @endif
                            </td>
                            <td>
                                @if($request->status === 'pending')
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            data-bs-toggle="modal" data-bs-target="#reviewModal{{ $request->id }}">
                                        <i class="fas fa-pen"></i> Review
                                    </button>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>

                        <!-- Device Details Modal -->
                        <div class="modal fade" id="deviceModal{{ $request->id }}" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Device Information</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <h6>Device Summary</h6>
                                        <p class="bg-light p-3 rounded">{{ $request->device_summary }}</p>
                                        
                                        @if($request->device_info)
                                            <h6 class="mt-4">Device Details (JSON)</h6>
                                            <pre class="bg-light p-3 rounded"><code>{{ json_encode($request->device_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</code></pre>
                                        @endif

                                        @if($request->reason)
                                            <h6 class="mt-4">Reason for Access</h6>
                                            <p>{{ $request->reason }}</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Review Modal -->
                        <div class="modal fade" id="reviewModal{{ $request->id }}" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST" action="{{ route('device-access-requests.review', $request->id) }}">
                                        @csrf
                                        <div class="modal-header">
                                            <h5 class="modal-title">Review Device Request</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label for="status{{ $request->id }}" class="form-label">Decision</label>
                                                <select class="form-select" id="status{{ $request->id }}" 
                                                        name="status" required>
                                                    <option value="">-- Choose --</option>
                                                    <option value="approved" class="text-success">Approve</option>
                                                    <option value="rejected" class="text-danger">Reject</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label for="note{{ $request->id }}" class="form-label">Admin Note</label>
                                                <textarea class="form-control" id="note{{ $request->id }}" 
                                                          name="admin_note" rows="4" 
                                                          placeholder="Add notes for this decision..."></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            <button type="submit" class="btn btn-primary">Submit Review</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="fas fa-inbox"></i> No device access requests found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $requests->links() }}
    </div>
</div>

<style>
    .table-hover tbody tr:hover {
        background-color: #f8f9fa;
    }
</style>
@endsection
