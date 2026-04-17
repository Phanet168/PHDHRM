@extends('backend.layouts.app')

@section('title', localize('dispensing_detail', 'Dispensing Detail'))

@section('content')
    <div class="body-content">
        @include('pharmaceutical::_nav')

        <div class="card pharm-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fa fa-hand-holding-medical me-1"></i>{{ localize('dispensing_detail', 'ព័ត៌មានការផ្តល់ឱសថ') }}</h6>
                <div>
                    <a href="{{ route('pharmaceutical.dispensings.print', $dispensing->id) }}" target="_blank" class="btn btn-sm btn-outline-success me-1">
                        <i class="fa fa-print me-1"></i>{{ localize('print_receipt', 'Print Receipt') }}
                    </a>
                    <a href="{{ route('pharmaceutical.dispensings.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-arrow-left me-1"></i>{{ localize('back', 'Back') }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr><th style="width:40%">{{ localize('reference_no', 'Reference') }}</th><td><code>{{ $dispensing->reference_no }}</code></td></tr>
                            <tr><th>{{ localize('date', 'Date') }}</th><td>{{ $dispensing->dispensing_date?->format('d/m/Y') }}</td></tr>
                            <tr><th>{{ localize('facility', 'Facility') }}</th><td>{{ $dispensing->department?->department_name }}</td></tr>
                            <tr><th>{{ localize('dispensed_by', 'Dispensed by') }}</th><td>{{ $dispensing->dispenser?->full_name ?? '-' }}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr><th style="width:40%">{{ localize('patient_name', 'Patient') }}</th><td>{{ $dispensing->patient_name }}</td></tr>
                            <tr>
                                <th>{{ localize('id_no', 'ID No.') }}</th>
                                <td>{{ $dispensing->patient_id_no ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>{{ localize('gender_age', 'Gender / Age') }}</th>
                                <td>
                                    @if($dispensing->patient_gender === 'M') {{ localize('male', 'ប្រុស') }}
                                    @elseif($dispensing->patient_gender === 'F') {{ localize('female', 'ស្រី') }}
                                    @else - @endif
                                    @if($dispensing->patient_age) / {{ $dispensing->patient_age }} {{ localize('years', 'ឆ្នាំ') }} @endif
                                </td>
                            </tr>
                            <tr><th>{{ localize('diagnosis', 'Diagnosis') }}</th><td>{{ $dispensing->diagnosis ?? '-' }}</td></tr>
                        </table>
                    </div>
                </div>

                @if($dispensing->note)
                    <div class="alert alert-secondary py-2 small">{{ $dispensing->note }}</div>
                @endif

                <h6>{{ localize('dispensed_medicines', 'ឱសថផ្តល់') }}</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>{{ localize('medicine', 'Medicine') }}</th>
                                <th class="text-end">{{ localize('quantity', 'Qty') }}</th>
                                <th>{{ localize('batch_no', 'Batch') }}</th>
                                <th>{{ localize('dosage_instruction', 'Dosage') }}</th>
                                <th class="text-center">{{ localize('duration_days', 'Days') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($dispensing->items as $item)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>
                                        <strong>{{ $item->medicine?->name }}</strong>
                                        @if($item->medicine?->name_kh)
                                            <br><small class="text-muted">{{ $item->medicine->name_kh }}</small>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ number_format($item->quantity, 0) }} {{ $item->medicine?->unit }}</td>
                                    <td>{{ $item->batch_no ?? '-' }}</td>
                                    <td>{{ $item->dosage_instruction ?? '-' }}</td>
                                    <td class="text-center">{{ $item->duration_days ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
