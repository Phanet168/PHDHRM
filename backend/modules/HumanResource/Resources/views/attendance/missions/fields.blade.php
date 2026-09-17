@php
    $editing = isset($mission);
@endphp
<div class="card mb-3">
    <div class="card-header bg-light"><i class="fa fa-info-circle me-2 text-primary"></i><span class="fs-5 fw-bold">ព័ត៌មានទូទៅ</span></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="mission-type" class="form-label">ប្រភេទបេសកកម្ម *</label>
                <select id="mission-type" name="mission_type" class="form-select" required>
                    @foreach(\Modules\HumanResource\Entities\Mission::TYPES as $value => $label)
                        <option value="{{ $value }}" @selected(old('mission_type', $mission->mission_type ?? 'inspection') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @unless($hideOrderNumberField ?? false)
                <div class="col-md-6">
                    <label for="mission-order" class="form-label">លេខលិខិតបង្គាប់ការ</label>
                    <input id="mission-order" name="order_number" class="form-control" maxlength="100" value="{{ old('order_number', $mission->order_number ?? '') }}">
                </div>
            @endunless
            <div class="col-12">
                <label for="mission-title" class="form-label">ចំណងជើង *</label>
                <input id="mission-title" name="title" class="form-control" maxlength="255" required value="{{ old('title', $mission->title ?? '') }}">
            </div>
            <div class="col-12">
                <label for="mission-destination" class="form-label">ទីតាំងទៅបំពេញបេសកកម្ម *</label>
                <input id="mission-destination" name="destination" class="form-control mission-destination-text" maxlength="255" required value="{{ old('destination', $mission->destination ?? '') }}">
                <small class="text-muted">ឈ្មោះមណ្ឌល ភូមិ ស្រុក ខេត្ត ឬទីកន្លែងបណ្ដុះបណ្ដាល</small>
            </div>
            <div class="col-sm-6">
                <label for="mission-start" class="form-label">ថ្ងៃចេញដំណើរ *</label>
                <input id="mission-start" type="date" name="start_date" class="form-control" required value="{{ old('start_date', $editing ? $mission->start_date->format('Y-m-d') : '') }}">
            </div>
            <div class="col-sm-6">
                <label for="mission-end" class="form-label">ថ្ងៃត្រឡប់មកវិញ *</label>
                <input id="mission-end" type="date" name="end_date" class="form-control" required value="{{ old('end_date', $editing ? $mission->end_date->format('Y-m-d') : '') }}">
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-light"><i class="fa fa-users me-2 text-primary"></i><span class="fs-5 fw-bold">ក្រុមមន្ត្រីចេញបំពេញបេសកកម្ម</span></div>
    <div class="card-body">
        <label for="mission-team" class="form-label">ជ្រើសរើសមន្ត្រី *</label>
        <select id="mission-team" name="employee_ids[]" class="form-select mission-employee-select" multiple required>
            @foreach($employees as $employee)
                <option value="{{ $employee->id }}" @selected(in_array($employee->id, old('employee_ids', $editing ? $mission->assignments->pluck('employee_id')->all() : [])))>{{ $employee->full_name }} ({{ $employee->employee_id }})</option>
            @endforeach
        </select>
        <small class="text-muted">វាយឈ្មោះ ឬលេខសម្គាល់ ដើម្បីស្វែងរក រួចចុចជ្រើសរើសម្នាក់ៗ។</small>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-light"><i class="fa fa-bullseye me-2 text-primary"></i><span class="fs-5 fw-bold">គោលបំណង</span></div>
    <div class="card-body">
        <textarea id="mission-purpose" name="purpose" class="form-control" rows="4" maxlength="10000" placeholder="រៀបរាប់អំពីគោលបំណងនៃបេសកកម្ម">{{ old('purpose', $mission->purpose ?? '') }}</textarea>
        @unless($hideStatusField ?? false)
            <div class="mt-3">
                <label for="mission-status" class="form-label">ស្ថានភាព</label>
                <select id="mission-status" name="status" class="form-select">
                    <option value="draft" @selected(old('status', $mission->status ?? 'pending') === 'draft')>សេចក្ដីព្រាង</option>
                    <option value="pending" @selected(old('status', $mission->status ?? 'pending') === 'pending')>ស្នើសុំអនុម័ត</option>
                </select>
            </div>
        @endunless
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-light"><i class="fa fa-file-signature me-2 text-primary"></i><span class="fs-5 fw-bold">ព័ត៌មានលិខិតបញ្ជាបេសកកម្ម</span></div>
    <div class="card-body">
        @php
            $orderFields = [
                'issuing_authority' => ['រដ្ឋបាល/ស្ថាប័ន', 'រដ្ឋបាលខេត្តស្ទឹងត្រែង', 255],
                'issuing_department' => ['អង្គភាពចេញលិខិត', 'មន្ទីរសុខាភិបាល', 255],
                'issue_place' => ['ទីកន្លែងចេញលិខិត', 'ស្ទឹងត្រែង', 100],
                'transport' => ['មធ្យោបាយធ្វើដំណើរ', '', 255],
                'funding_source' => ['ប្រភពថវិកា/អ្នកទទួលបន្ទុកចំណាយ', '', 500],
                'signatory_title' => ['តួនាទីអ្នកចុះហត្ថលេខា', 'ប្រធានមន្ទីរសុខាភិបាលខេត្ត', 255],
                'signatory_name' => ['ឈ្មោះអ្នកចុះហត្ថលេខា', '', 255],
            ];
        @endphp
        <div class="row g-3">
            <div class="col-md-6">
                <label for="order-issued" class="form-label">ថ្ងៃចេញលិខិត (សូរិយគតិ)</label>
                <input id="order-issued" type="date" name="order_details[issued_on]" class="form-control" value="{{ old('order_details.issued_on', data_get($mission, 'order_details.issued_on', '')) }}">
            </div>
            <div class="col-md-6">
                <label for="order-lunar" class="form-label">ថ្ងៃចន្ទគតិ</label>
                <input id="order-lunar" name="order_details[lunar_date]" class="form-control" maxlength="255" value="{{ old('order_details.lunar_date', data_get($mission, 'order_details.lunar_date', '')) }}" placeholder="បំពេញស្វ័យប្រវត្តិពីថ្ងៃចេញលិខិត">
                <small class="text-muted">បំពេញដោយស្វ័យប្រវត្តិពី "ថ្ងៃចេញលិខិត" ខាងលើ — អាចកែប្រែបានដោយផ្ទាល់។</small>
            </div>
            @foreach($orderFields as $key => [$label, $default, $max])
                <div class="col-md-6">
                    <label for="order-{{ $key }}" class="form-label">{{ $label }}</label>
                    <input id="order-{{ $key }}" name="order_details[{{ $key }}]" class="form-control" maxlength="{{ $max }}" value="{{ old('order_details.'.$key, data_get($mission, 'order_details.'.$key, $default)) }}">
                </div>
            @endforeach
            <div class="col-12">
                <label for="order-reference" class="form-label">យោង (លិខិត/ចំណារឯកភាព)</label>
                <textarea id="order-reference" name="order_details[reference]" rows="3" class="form-control" maxlength="2000">{{ old('order_details.reference', data_get($mission, 'order_details.reference', '')) }}</textarea>
            </div>
        </div>
        <small class="text-muted d-block mt-2">ឈ្មោះ និងតួនាទីមន្ត្រីត្រូវបានរក្សាទុកពីបញ្ជីមន្ត្រីនៅពេលចាត់តាំង។</small>
    </div>
</div>

<button class="btn btn-primary btn-lg" type="submit"><i class="fa fa-save me-1"></i>រក្សាទុក</button>
