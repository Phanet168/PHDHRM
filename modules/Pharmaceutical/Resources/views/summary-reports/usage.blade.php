@extends('backend.layouts.app')

@section('title', localize('medicine_usage_report', 'Medicine Usage Report'))

@section('content')
    <div class="body-content">
        @include('pharmaceutical::_nav')

        <div class="card pharm-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fa fa-prescription-bottle-alt me-1"></i>{{ localize('medicine_usage_report', 'របាយការណ៍ប្រើប្រាស់ឱសថ') }}</h6>
                @if(count($items) > 0)
                <a href="{{ route('pharmaceutical.summary-reports.usage-print', ['dept' => $deptFilter, 'period_start' => $periodStart, 'period_end' => $periodEnd]) }}"
                   target="_blank" class="btn btn-sm btn-outline-success">
                    <i class="fa fa-print me-1"></i>{{ localize('print', 'Print') }}
                </a>
                @endif
            </div>
            <div class="card-body">
                {{-- Filter form --}}
                <form method="GET" class="row g-2 mb-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label mb-1 small fw-semibold">{{ localize('facility', 'Facility') }}</label>
                        <select name="dept" class="form-select form-select-sm">
                            <option value="0" @selected($deptFilter === 0)>{{ localize('all_facilities', 'All facilities') }}</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}" @selected($dept->id === $deptFilter)>{{ $dept->department_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1 small fw-semibold">{{ localize('from', 'From') }}</label>
                        <input type="date" name="period_start" class="form-control form-control-sm" value="{{ $periodStart }}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1 small fw-semibold">{{ localize('to', 'To') }}</label>
                        <input type="date" name="period_end" class="form-control form-control-sm" value="{{ $periodEnd }}" required>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-sm btn-primary w-100"><i class="fa fa-search me-1"></i>{{ localize('generate', 'Generate') }}</button>
                    </div>
                </form>

                @if($reportGenerated && count($items) > 0)
                <div class="mb-2">
                    <span class="badge bg-success fs-6">{{ $facilityName }}</span>
                    <span class="text-muted ms-2">{{ \Carbon\Carbon::parse($periodStart)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($periodEnd)->format('d/m/Y') }}</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px">#</th>
                                <th>{{ localize('medicine', 'Medicine') }}</th>
                                <th>{{ localize('unit', 'Unit') }}</th>
                                <th class="text-end">{{ localize('total_dispensed', 'បរិមាណប្រើប្រាស់') }}</th>
                                <th class="text-end">{{ localize('dispense_count', 'ចំនួនលើកចេញ') }}</th>
                                <th class="text-end">{{ localize('patient_count', 'ចំនួនអ្នកជំងឺ') }}</th>
                                <th class="text-end">{{ localize('current_stock', 'ស្តុកបច្ចុប្បន្ន') }}</th>
                                <th class="text-end">{{ localize('avg_per_dispense', 'មធ្យម/លើក') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $idx => $item)
                            <tr>
                                <td>{{ $idx + 1 }}</td>
                                <td>
                                    <strong>{{ $item['medicine_code'] }}</strong> – {{ $item['medicine_name'] }}
                                    @if($item['medicine_name_kh'])
                                        <br><small class="text-muted">{{ $item['medicine_name_kh'] }}</small>
                                    @endif
                                </td>
                                <td>{{ $item['unit'] }}</td>
                                <td class="text-end fw-bold">{{ number_format($item['total_qty'], 2) }}</td>
                                <td class="text-end">{{ $item['dispense_count'] }}</td>
                                <td class="text-end">{{ $item['patient_count'] }}</td>
                                <td class="text-end {{ $item['current_stock'] <= 0 ? 'text-danger fw-bold' : '' }}">{{ number_format($item['current_stock'], 2) }}</td>
                                <td class="text-end">{{ $item['dispense_count'] > 0 ? number_format($item['total_qty'] / $item['dispense_count'], 2) : '-' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr class="fw-bold">
                                <td colspan="3" class="text-end">{{ localize('total', 'Total') }}</td>
                                <td class="text-end">{{ number_format($totals['total_qty'], 2) }}</td>
                                <td class="text-end">{{ $totals['dispense_count'] }}</td>
                                <td class="text-end">{{ $totals['patient_count'] }}</td>
                                <td class="text-end">{{ number_format($totals['current_stock'], 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @elseif($reportGenerated)
                <div class="alert alert-info">{{ localize('no_usage_data', 'មិនមានទិន្នន័យប្រើប្រាស់ឱសថក្នុងអំឡុងពេលនេះទេ។') }}</div>
                @else
                <div class="alert alert-secondary">{{ localize('select_facility_period', 'សូមជ្រើសរើសមូលដ្ឋានសុខាភិបាល និងកំណត់កាលបរិច្ឆេទ រួចចុច "Generate"។') }}</div>
                @endif
            </div>
        </div>
    </div>
@endsection
