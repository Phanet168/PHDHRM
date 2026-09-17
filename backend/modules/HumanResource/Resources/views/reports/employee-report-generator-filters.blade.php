<hr class="my-4">
<h6 class="mb-3">ជ្រើសលក្ខខណ្ឌបុគ្គលិក</h6>
<p class="small text-muted">អាចជ្រើសច្រើនក្នុងក្រុមនីមួយៗ។ ទុកទំនេរដើម្បីរួមបញ្ចូលទាំងអស់។</p>
<div class="row g-3">
    @foreach (['skill_name' => 'ជំនាញ', 'employee_grade' => 'ឋានន្តរស័ក្តិ និងថ្នាក់', 'work_status_name' => 'ស្ថានភាពការងារ'] as $key => $label)
        <div class="col-md-4">
            <label for="filter-{{ $key }}" class="form-label">{{ $label }}</label>
            <select id="filter-{{ $key }}" name="{{ $key }}[]" class="form-select" multiple size="4">
                @foreach ($combinationOptions[$key] as $value)
                    <option value="{{ $value }}" @selected(in_array($value, (array) old($key, []), true))>{{ $value }}</option>
                @endforeach
            </select>
            <div class="d-flex gap-2 mt-1">
                <button type="button" class="btn btn-link btn-sm p-0" data-combination="{{ $key }}" data-select="all">ជ្រើសទាំងអស់</button>
                <button type="button" class="btn btn-link btn-sm p-0 text-muted" data-combination="{{ $key }}" data-select="none">សម្អាត</button>
            </div>
        </div>
    @endforeach
    <div class="col-md-6">
        <label for="unit_type_id" class="form-label">ប្រភេទអង្គភាព</label>
        <select id="unit_type_id" name="unit_type_id" class="form-select">
            <option value="">ទាំងអស់</option>
            @foreach ($unitTypes as $type)
                <option value="{{ $type->id }}" @selected((string) old('unit_type_id') === (string) $type->id)>{{ $type->display_name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label for="joining_year" class="form-label">បុគ្គលិកចូលថ្មីក្នុងឆ្នាំ</label>
        <input type="number" min="1900" max="2100" id="joining_year" name="joining_year" class="form-control" value="{{ old('joining_year') }}" placeholder="ឧ. 2026">
    </div>
</div>

<details class="mt-4" id="personal-report-filters">
    <summary class="fw-semibold">ស្វែងរកតាមព័ត៌មានបុគ្គលិក</summary>
    <div class="row g-3 pt-2">
        @foreach (['employee_name' => 'ឈ្មោះ', 'employee_code' => 'លេខបុគ្គលិក ឬអត្តលេខ'] as $key => $label)
            <div class="col-md-6">
                <label for="{{ $key }}" class="form-label">{{ $label }}</label>
                <input id="{{ $key }}" name="{{ $key }}" class="form-control" maxlength="{{ $key === 'employee_code' ? 50 : 150 }}" value="{{ old($key) }}">
            </div>
        @endforeach
        <div class="col-md-6">
            <label for="gender_id" class="form-label">ភេទ</label>
            <select id="gender_id" name="gender_id" class="form-select">
                <option value="">ទាំងអស់</option>
                @foreach ($genders as $gender)
                    <option value="{{ $gender->id }}" @selected((string) old('gender_id') === (string) $gender->id)>{{ match (mb_strtolower(trim($gender->gender_name))) { 'male', 'm' => 'ប្រុស', 'female', 'f' => 'ស្រី', default => $gender->gender_name } }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label for="birth_date" class="form-label">ថ្ងៃខែឆ្នាំកំណើត</label>
            <input type="date" id="birth_date" name="birth_date" class="form-control" value="{{ old('birth_date') }}">
        </div>
        @foreach (['birth_year' => ['ឆ្នាំកំណើត', 1900, 2100], 'age' => ['អាយុបច្ចុប្បន្ន', 0, 120], 'service_years' => ['ចំនួនឆ្នាំបម្រើការងារគិតត្រឹមថ្ងៃនេះ', 0, 80]] as $key => [$label, $min, $max])
            <div class="col-md-4">
                <label for="{{ $key }}" class="form-label">{{ $label }}</label>
                <input type="number" id="{{ $key }}" name="{{ $key }}" min="{{ $min }}" max="{{ $max }}" class="form-control" value="{{ old($key) }}">
            </div>
        @endforeach
    </div>
</details>
<div class="bg-light border rounded p-3 mt-4">
    <div class="small text-muted mb-2">របាយការណ៍ផ្សេងទៀត</div>
    <div class="d-flex flex-wrap gap-3">
        <a href="{{ route('employee-retirements.report') }}">របាយការណ៍ចូលនិវត្តន៍</a>
        <a href="{{ route('employee-pay-promotions.index') }}">របាយការណ៍ដំឡើងថ្នាក់</a>
    </div>
</div>
