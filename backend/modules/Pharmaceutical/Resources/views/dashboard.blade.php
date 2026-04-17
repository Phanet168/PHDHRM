@extends('backend.layouts.app')

@section('title', localize('pharmaceutical_management', 'Pharmaceutical Management'))

@section('content')
    <div class="body-content">
        @include('pharmaceutical::_nav')

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="card pharm-card text-center">
                    <div class="card-body">
                        <h3 class="text-success">{{ $medicineCount }}</h3>
                        <div class="small text-muted">{{ localize('total_medicines', 'Total medicines') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card pharm-card text-center">
                    <div class="card-body">
                        <h3 class="text-primary">{{ $distributionCount }}</h3>
                        <div class="small text-muted">{{ localize('total_distributions', 'Total distributions') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card pharm-card text-center">
                    <div class="card-body">
                        <h3 class="text-warning">{{ $pendingDistributions }}</h3>
                        <div class="small text-muted">{{ localize('pending_distributions', 'Pending distributions') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card pharm-card text-center">
                    <div class="card-body">
                        <h3 class="text-info">{{ $reportCount }}</h3>
                        <div class="small text-muted">{{ localize('total_reports', 'Total reports') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            {{-- Recent distributions --}}
            <div class="col-lg-6">
                <div class="card pharm-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">{{ localize('recent_distributions', 'Recent distributions') }}</h6>
                        <a href="{{ route('pharmaceutical.distributions.index') }}" class="btn btn-sm btn-outline-primary">{{ localize('view_all', 'View all') }}</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ localize('reference', 'Ref') }}</th>
                                        <th>{{ localize('from', 'From') }}</th>
                                        <th>{{ localize('to', 'To') }}</th>
                                        <th>{{ localize('date', 'Date') }}</th>
                                        <th>{{ localize('status', 'Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentDistributions as $dist)
                                        <tr>
                                            <td><a href="{{ route('pharmaceutical.distributions.show', $dist->id) }}">{{ $dist->reference_no ?: '#' . $dist->id }}</a></td>
                                            <td>{{ $dist->fromDepartment?->department_name ?: '-' }}</td>
                                            <td>{{ $dist->toDepartment?->department_name ?: '-' }}</td>
                                            <td>{{ optional($dist->distribution_date)->format('d/m/Y') ?: '-' }}</td>
                                            <td><span class="badge bg-secondary">{{ $dist->status_label }}</span></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center text-muted">{{ localize('no_data', 'No data') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Recent reports --}}
            <div class="col-lg-6">
                <div class="card pharm-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">{{ localize('recent_reports', 'Recent reports') }}</h6>
                        <a href="{{ route('pharmaceutical.reports.index') }}" class="btn btn-sm btn-outline-primary">{{ localize('view_all', 'View all') }}</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ localize('reference', 'Ref') }}</th>
                                        <th>{{ localize('facility', 'Facility') }}</th>
                                        <th>{{ localize('period', 'Period') }}</th>
                                        <th>{{ localize('status', 'Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentReports as $rpt)
                                        <tr>
                                            <td><a href="{{ route('pharmaceutical.reports.show', $rpt->id) }}">{{ $rpt->reference_no ?: '#' . $rpt->id }}</a></td>
                                            <td>{{ $rpt->department?->department_name ?: '-' }}</td>
                                            <td>{{ $rpt->period_label ?: '-' }}</td>
                                            <td><span class="badge bg-secondary">{{ $rpt->status }}</span></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-muted">{{ localize('no_data', 'No data') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Alerts --}}
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card pharm-card border-warning">
                    <div class="card-header bg-warning bg-opacity-10">
                        <h6 class="mb-0 text-warning">{{ localize('low_stock_alert', 'Low stock alert') }}</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr><th>{{ localize('facility', 'Facility') }}</th><th>{{ localize('medicine', 'Medicine') }}</th><th>{{ localize('qty', 'Qty') }}</th></tr></thead>
                                <tbody>
                                    @forelse($lowStockItems as $ls)
                                        <tr>
                                            <td>{{ $ls->department?->department_name ?: '-' }}</td>
                                            <td>{{ $ls->medicine?->name ?: '-' }}</td>
                                            <td class="text-warning fw-bold">{{ number_format((float) $ls->quantity) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="text-center text-muted">{{ localize('no_alerts', 'No alerts') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card pharm-card border-danger">
                    <div class="card-header bg-danger bg-opacity-10">
                        <h6 class="mb-0 text-danger">{{ localize('expiring_soon', 'Expiring soon (3 months)') }}</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr><th>{{ localize('facility', 'Facility') }}</th><th>{{ localize('medicine', 'Medicine') }}</th><th>{{ localize('expiry', 'Expiry') }}</th><th>{{ localize('qty', 'Qty') }}</th></tr></thead>
                                <tbody>
                                    @forelse($expiringSoon as $es)
                                        <tr>
                                            <td>{{ $es->department?->department_name ?: '-' }}</td>
                                            <td>{{ $es->medicine?->name ?: '-' }}</td>
                                            <td class="text-danger">{{ optional($es->expiry_date)->format('d/m/Y') ?: '-' }}</td>
                                            <td>{{ number_format((float) $es->quantity) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-muted">{{ localize('no_alerts', 'No alerts') }}</td></tr>
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
