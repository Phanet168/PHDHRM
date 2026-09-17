@extends('backend.layouts.app')
@section('title', 'ស្នើសុំបេសកកម្ម')
@section('content')
    <meta name="mission-lunar-date-url" content="{{ route('missions.lunar-date') }}">
    @include('humanresource::attendance.missions.nav')
    <div class="d-flex align-items-center gap-3 mb-4">
        <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width:56px;height:56px;">
            <i class="fa fa-file-signature fs-3"></i>
        </div>
        <div>
            <h3 class="mb-0 fw-bold">ស្នើសុំបេសកកម្ម</h3>
            <p class="text-muted mb-0">សំណើនេះនឹងបញ្ជូនទៅប្រធានការិយាល័យ រួចប្រធានមន្ទីរ ដើម្បីឯកភាព និងអនុម័តតាមលំដាប់</p>
        </div>
    </div>
    @include('backend.layouts.common.validation')

    <div class="card mb-3 border-primary-subtle">
        <div class="card-body d-flex align-items-center gap-3">
            <div class="rounded-circle bg-secondary bg-opacity-10 text-secondary d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;">
                <i class="fa fa-user"></i>
            </div>
            <div>
                <div class="text-muted small">អ្នកស្នើសុំ (កំណត់ដោយស្វ័យប្រវត្តិពីគណនីរបស់អ្នក)</div>
                @if($requesterEmployee)
                    <div class="fw-bold">{{ $requesterEmployee->full_name }}
                        @if($requesterEmployee->position)
                            <span class="fw-normal text-muted">— {{ $requesterEmployee->position->position_name_km ?: $requesterEmployee->position->position_name }}</span>
                        @endif
                    </div>
                @else
                    <div class="fw-bold text-warning">{{ auth()->user()->full_name }} <span class="fw-normal text-muted small">(គណនីនេះមិនទាន់ភ្ជាប់ជាមួយកំណត់ត្រាមន្ត្រីទេ សូមទាក់ទងអ្នកគ្រប់គ្រង)</span></div>
                @endif
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-9">
            <form id="mission-request-form" action="{{ route('missions.request.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @include('humanresource::attendance.missions.fields', ['mission' => null, 'hideStatusField' => true, 'hideOrderNumberField' => true])
                @include('humanresource::attendance.missions.extra-fields', ['mission' => null])
            </form>
        </div>
        <div class="col-lg-3">
            <div class="card" style="position: sticky; top: 1rem;">
                <div class="card-header bg-light"><i class="fa fa-route me-2 text-primary"></i><span class="fw-bold">លំហូរអនុម័ត</span></div>
                <div class="card-body">
                    @php
                        $officeHeadNames = $approvalRoute[0]['names'] ?? [];
                        $directorNames = $approvalRoute[1]['names'] ?? [];
                        $steps = [
                            ['label' => 'ដាក់សំណើ', 'names' => null],
                            ['label' => 'ប្រធានការិយាល័យឯកភាព', 'names' => $officeHeadNames, 'editable' => true],
                            ['label' => 'ប្រធានមន្ទីរអនុម័ត', 'names' => $directorNames],
                            ['label' => 'មន្ត្រីគ្រប់គ្រងលិខិតរៀបចំ និងចេញលិខិតបញ្ជាបេសកកម្ម', 'names' => null],
                            ['label' => 'បំពេញបេសកកម្ម និងដាក់របាយការណ៍', 'names' => null],
                        ];
                    @endphp
                    <ol class="list-unstyled mb-0">
                        @foreach($steps as $step)
                            <li class="d-flex align-items-start gap-2 mb-3">
                                <span class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width:24px;height:24px;font-size:.75rem;">{{ $loop->iteration }}</span>
                                <span class="small flex-grow-1">
                                    {{ $step['label'] }}
                                    @if(!empty($step['editable']))
                                        <input type="text" form="mission-request-form" name="order_details[preferred_office_head]" class="form-control form-control-sm mt-1"
                                            value="{{ old('order_details.preferred_office_head', implode(', ', $officeHeadNames)) }}"
                                            placeholder="ឈ្មោះប្រធានការិយាល័យ" maxlength="255">
                                        <small class="text-muted d-block">អាចកែប្រែបានប្រសិនបើមានប្រធានការិយាល័យច្រើននាក់ ឬចង់បញ្ជាក់ផ្សេង</small>
                                    @elseif($step['names'] !== null && !empty($step['names']))
                                        <span class="d-block text-primary fw-bold">{{ implode(', ', $step['names']) }}</span>
                                    @elseif($step['names'] !== null)
                                        <span class="d-block text-danger small">(មិនទាន់មានអ្នកទទួលបន្ទុកកំណត់ទេ)</span>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ol>
                    @if(empty($officeHeadNames))
                        <div class="alert alert-info small mb-0 mt-2">
                            <i class="fa fa-info-circle me-1"></i>
                            គ្មានប្រធានការិយាល័យផ្សេងកំណត់សម្រាប់ការិយាល័យរបស់អ្នកទេ សំណើនេះនឹងបញ្ជូនទៅប្រធានមន្ទីរផ្ទាល់។
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
@push('js')
    <script src="{{ module_asset('HumanResource/js/hrcommon.js') }}"></script>
    <script src="{{ module_asset('HumanResource/js/mission-destination.js') }}"></script>
    <script src="{{ module_asset('HumanResource/js/mission-form.js') }}"></script>
@endpush
