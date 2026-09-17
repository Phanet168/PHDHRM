@extends('backend.layouts.app')
@section('title', 'រៀបចំលិខិតបញ្ជាបេសកកម្ម')
@section('content')
    <meta name="mission-lunar-date-url" content="{{ route('missions.lunar-date') }}">
    @include('humanresource::attendance.missions.nav')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h4>រៀបចំលិខិតបញ្ជាបេសកកម្ម</h4><p class="text-muted mb-0">{{ $mission->title }}</p></div>
        <a href="{{ route('missions.show', $mission->id) }}" class="btn btn-outline-secondary">ត្រឡប់ទៅលម្អិត</a>
    </div>
    @include('backend.layouts.common.validation')
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card"><div class="card-body">
                <form action="{{ route('missions.order.update', $mission->id) }}" method="POST">
                    @csrf @method('PUT')
                    <div class="mb-3"><label class="form-label">លេខលិខិតបញ្ជាបេសកកម្ម</label>
                        <input name="order_number" class="form-control" maxlength="100" value="{{ old('order_number', $mission->order_number) }}"></div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6"><label class="form-label">មធ្យោបាយធ្វើដំណើរ</label>
                            <select name="transport_type_id" class="form-select">
                                <option value="">— ជ្រើសរើស —</option>
                                @foreach($transportTypes as $transport)
                                    <option value="{{ $transport->id }}" @selected(old('transport_type_id', $mission->transport_type_id) == $transport->id)>{{ $transport->name_km }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label">ប្រភពថវិកា</label>
                            <select name="funding_source_id" class="form-select">
                                <option value="">— ជ្រើសរើស —</option>
                                @foreach($fundingSources as $source)
                                    <option value="{{ $source->id }}" @selected(old('funding_source_id', $mission->funding_source_id) == $source->id)>{{ $source->name ?? $source->name_km ?? ('#'.$source->id) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="card mb-3">
                        <div class="card-header bg-light"><span class="fs-5 fw-bold">ព័ត៌មានលិខិត</span></div>
                        <div class="card-body">
                        @php
                            $orderFields = [
                                'issuing_authority' => ['រដ្ឋបាល/ស្ថាប័ន', 255],
                                'issuing_department' => ['អង្គភាពចេញលិខិត', 255],
                                'issue_place' => ['ទីកន្លែងចេញលិខិត', 100],
                                'signatory_title' => ['តួនាទីអ្នកចុះហត្ថលេខា', 255],
                                'signatory_name' => ['ឈ្មោះអ្នកចុះហត្ថលេខា', 255],
                            ];
                        @endphp
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">កាលបរិច្ឆេទចេញលិខិត (សូរិយគតិ)</label>
                                <input type="date" name="order_details[issued_on]" class="form-control" value="{{ old('order_details.issued_on', $details['issued_on'] ?? '') }}"></div>
                            <div class="col-md-6"><label class="form-label">ថ្ងៃចន្ទគតិ</label>
                                <input name="order_details[lunar_date]" class="form-control" maxlength="255" value="{{ old('order_details.lunar_date', $details['lunar_date'] ?? '') }}" placeholder="បំពេញស្វ័យប្រវត្តិពីថ្ងៃចេញលិខិត">
                                <small class="text-muted">បំពេញដោយស្វ័យប្រវត្តិ — អាចកែប្រែបានដោយផ្ទាល់។</small></div>
                            @foreach($orderFields as $key => [$label, $max])
                                <div class="col-md-6"><label class="form-label">{{ $label }}</label>
                                    <input name="order_details[{{ $key }}]" class="form-control" maxlength="{{ $max }}" value="{{ old('order_details.'.$key, $details[$key] ?? '') }}"></div>
                            @endforeach
                            <div class="col-12"><label class="form-label">យោង (លិខិត/ចំណារឯកភាព)</label>
                                <textarea name="order_details[reference]" rows="3" class="form-control" maxlength="2000">{{ old('order_details.reference', $details['reference'] ?? '') }}</textarea></div>
                        </div>
                        </div>
                    </div>
                    <button class="btn btn-primary" type="submit">រក្សាទុកសេចក្ដីព្រាង</button>
                </form>

                @if($mission->assignments->where('status', 'active')->isNotEmpty())
                    <form action="{{ route('missions.order.issue', $mission->id) }}" method="POST" class="mt-3">
                        @csrf
                        <button class="btn btn-success" type="submit" onclick="return confirm('បញ្ជាក់ការចេញលិខិតបញ្ជាបេសកកម្ម? បន្ទាប់ពីនេះព័ត៌មានលិខិតនឹងចាក់សោ។');">
                            <i class="fa fa-stamp me-1"></i>ចេញលិខិត
                        </button>
                    </form>
                @else
                    <div class="alert alert-warning mt-3 mb-0">តម្រូវឲ្យមានមន្ត្រីយ៉ាងហោចណាស់ម្នាក់ក្នុងក្រុមមុននឹងចេញលិខិតបាន។</div>
                @endif
            </div></div>
        </div>
        <div class="col-lg-4">
            <div class="card"><div class="card-body">
                <h6>មន្ត្រីចូលរួម</h6>
                <ul class="list-unstyled mb-0">
                    @foreach($mission->assignments->where('status', 'active') as $assignment)
                        <li class="mb-1">{{ $assignment->name_on_order ?? $assignment->employee?->full_name }} — <span class="text-muted small">{{ $assignment->position_on_order }}</span></li>
                    @endforeach
                </ul>
                <hr>
                <a class="btn btn-sm btn-outline-secondary w-100" href="{{ route('missions.order', $mission->id) }}" target="_blank"><i class="fa fa-eye me-1"></i>មើលសំណុំបែបបទលិខិត</a>
            </div></div>
        </div>
    </div>
@endsection
@push('js')
    <script src="{{ module_asset('HumanResource/js/mission-form.js') }}"></script>
@endpush
