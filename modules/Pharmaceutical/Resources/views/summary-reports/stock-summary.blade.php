@extends('backend.layouts.app')

@section('title', localize('stock_summary_report', 'Stock Summary Report'))

@section('content')
    <div class="body-content">
        @include('pharmaceutical::_nav')

        <div class="card pharm-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fa fa-warehouse me-1"></i>{{ localize('stock_summary_report', 'របាយការណ៍ស្តុកដើមគ្រា-ចុងគ្រា') }}</h6>
                @if(count($items) > 0)
                <a href="{{ route('pharmaceutical.summary-reports.stock-summary-print', ['dept' => $deptFilter, 'period_start' => $periodStart, 'period_end' => $periodEnd]) }}"
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
                    <table class="table table-bordered table-sm table-hover align-middle mb-0" style="font-size:0.85rem">
                        <thead class="table-light">
                            <tr>
                                <th style="width:35px">#</th>
                                <th>{{ localize('medicine', 'Medicine') }}</th>
                                <th>{{ localize('unit', 'Unit') }}</th>
                                <th class="text-end text-primary">{{ localize('opening_stock', 'ស្តុកដើមគ្រា') }}</th>
                                <th class="text-end">{{ localize('received', 'ទទួល') }}</th>
                                <th class="text-end">{{ localize('dispensed', 'ចេញប្រើ') }}</th>
                                <th class="text-end">{{ localize('damaged', 'ខូចខាត') }}</th>
                                <th class="text-end">{{ localize('expired', 'ផុតកំណត់') }}</th>
                                <th class="text-end">{{ localize('adjustment', 'កែតម្រូវ') }}</th>
                                <th class="text-end text-success fw-bold">{{ localize('closing_stock', 'ស្តុកចុងគ្រា') }}</th>
                                <th class="text-end">{{ localize('current_stock', 'ស្តុកពិត') }}</th>
                                <th class="text-end">{{ localize('variance', 'គម្លាត') }}</th>
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
                                <td class="text-end text-primary">{{ number_format($item['opening_stock'], 2) }}</td>
                                <td class="text-end">{{ number_format($item['received_qty'], 2) }}</td>
                                <td class="text-end">{{ number_format($item['dispensed_qty'], 2) }}</td>
                                <td class="text-end {{ $item['damaged_qty'] > 0 ? 'text-danger' : '' }}">{{ number_format($item['damaged_qty'], 2) }}</td>
                                <td class="text-end {{ $item['expired_qty'] > 0 ? 'text-warning' : '' }}">{{ number_format($item['expired_qty'], 2) }}</td>
                                <td class="text-end">{{ number_format($item['adjustment_qty'], 2) }}</td>
                                <td class="text-end text-success fw-bold">{{ number_format($item['closing_stock'], 2) }}</td>
                                <td class="text-end">{{ number_format($item['current_stock'], 2) }}</td>
                                <td class="text-end {{ $item['variance'] != 0 ? ($item['variance'] < 0 ? 'text-danger fw-bold' : 'text-info') : '' }}">
                                    {{ $item['variance'] != 0 ? number_format($item['variance'], 2) : '-' }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr class="fw-bold">
                                <td colspan="3" class="text-end">{{ localize('total', 'Total') }}</td>
                                <td class="text-end text-primary">{{ number_format($totals['opening_stock'], 2) }}</td>
                                <td class="text-end">{{ number_format($totals['received_qty'], 2) }}</td>
                                <td class="text-end">{{ number_format($totals['dispensed_qty'], 2) }}</td>
                                <td class="text-end">{{ number_format($totals['damaged_qty'], 2) }}</td>
                                <td class="text-end">{{ number_format($totals['expired_qty'], 2) }}</td>
                                <td class="text-end">{{ number_format($totals['adjustment_qty'], 2) }}</td>
                                <td class="text-end text-success">{{ number_format($totals['closing_stock'], 2) }}</td>
                                <td class="text-end">{{ number_format($totals['current_stock'], 2) }}</td>
                                <td class="text-end {{ $totals['variance'] != 0 ? 'text-danger' : '' }}">
                                    {{ $totals['variance'] != 0 ? number_format($totals['variance'], 2) : '-' }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                {{-- Legend --}}
                <div class="mt-2 small text-muted">
                    <i class="fa fa-info-circle me-1"></i>
                    {{ localize('stock_formula', 'រូបមន្ត: ស្តុកចុងគ្រា = ស្តុកដើមគ្រា + ទទួល – ចេញប្រើ – ខូចខាត – ផុតកំណត់ + កែតម្រូវ') }}
                    &nbsp;|&nbsp;
                    {{ localize('variance_info', 'គម្លាត = ស្តុកពិត − ស្តុកចុងគ្រា (បើអវិជ្ជមាន = ខ្វះ)') }}
                </div>
                @elseif($reportGenerated)
                <div class="alert alert-info">{{ localize('no_stock_data', 'មិនមានទិន្នន័យស្តុកក្នុងអំឡុងពេលនេះទេ។') }}</div>
                @else
                <div class="alert alert-secondary">{{ localize('select_facility_period', 'សូមជ្រើសរើសមូលដ្ឋានសុខាភិបាល និងកំណត់កាលបរិច្ឆេទ រួចចុច "Generate"។') }}</div>
                @endif
            </div>
        </div>
    </div>
@endsection
