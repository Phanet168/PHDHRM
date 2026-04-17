@extends('backend.layouts.app')

@section('title', localize('dispensing_list', 'Dispensing'))

@section('content')
    <div class="body-content">
        @include('pharmaceutical::_nav')

        <div class="card pharm-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fa fa-hand-holding-medical me-1"></i>{{ localize('dispensing_list', 'ការផ្តល់ឱសថដល់អ្នកជំងឺ') }}</h6>
                <div class="d-flex gap-2">
                    <a href="{{ route('pharmaceutical.help', ['article' => 'dispensing']) }}" class="btn btn-sm btn-outline-info">
                        <i class="fa fa-life-ring me-1"></i>{{ localize('help', 'Help') }}
                    </a>
                    @if(in_array($level ?? '', ['hospital', 'hc']))
                        <a href="{{ route('pharmaceutical.dispensings.create') }}" class="btn btn-sm btn-success">
                            <i class="fa fa-plus me-1"></i>{{ localize('new_dispensing', 'ផ្តល់ឱសថថ្មី') }}
                        </a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-2 mb-3">
                    <div class="col-md-4">
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="{{ localize('search', 'Search') }}..." value="{{ $search }}">
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-sm btn-outline-secondary"><i class="fa fa-search"></i></button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>{{ localize('reference_no', 'Reference') }}</th>
                                <th>{{ localize('date', 'Date') }}</th>
                                <th>{{ localize('facility', 'Facility') }}</th>
                                <th>{{ localize('patient_name', 'Patient') }}</th>
                                <th>{{ localize('diagnosis', 'Diagnosis') }}</th>
                                <th>{{ localize('items_count', 'Items') }}</th>
                                <th>{{ localize('dispensed_by', 'By') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($dispensings as $d)
                                <tr>
                                    <td>{{ $loop->iteration + ($dispensings->currentPage()-1) * $dispensings->perPage() }}</td>
                                    <td><code>{{ $d->reference_no }}</code></td>
                                    <td>{{ $d->dispensing_date?->format('d/m/Y') }}</td>
                                    <td>{{ $d->department?->department_name }}</td>
                                    <td>
                                        {{ $d->patient_name }}
                                        @if($d->patient_gender)
                                            <small class="text-muted">({{ $d->patient_gender }}{{ $d->patient_age ? ', '.$d->patient_age.'ឆ្នាំ' : '' }})</small>
                                        @endif
                                    </td>
                                    <td><small>{{ \Illuminate\Support\Str::limit($d->diagnosis, 40) }}</small></td>
                                    <td class="text-center">{{ $d->items_count ?? $d->items->count() }}</td>
                                    <td>{{ $d->dispenser?->full_name ?? '-' }}</td>
                                    <td>
                                        <a href="{{ route('pharmaceutical.dispensings.show', $d->id) }}" class="btn btn-sm btn-outline-primary" title="View">
                                            <i class="fa fa-eye"></i>
                                        </a>
                                        <a href="{{ route('pharmaceutical.dispensings.print', $d->id) }}" target="_blank" class="btn btn-sm btn-outline-success" title="Print">
                                            <i class="fa fa-print"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="text-center text-muted py-3">{{ localize('no_data', 'No data') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $dispensings->links() }}
            </div>
        </div>
    </div>
@endsection
