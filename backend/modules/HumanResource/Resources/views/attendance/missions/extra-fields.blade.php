@php($destination = ($mission ?? null)?->destinations->first())
<div class="card mb-3">
    <div class="card-header bg-light"><i class="fa fa-layer-group me-2 text-primary"></i><span class="fs-5 fw-bold">ព័ត៌មានបន្ថែម</span></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">ប្រភេទបេសកកម្ម</label>
                <select name="mission_type_id" class="form-select">
                    <option value="">— ជ្រើសរើស —</option>
                    @foreach($missionTypes as $type)
                        <option value="{{ $type->id }}" @selected(old('mission_type_id') == $type->id)>{{ $type->name_km }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">ប្រភពថវិកា</label>
                <select name="funding_source_id" class="form-select">
                    <option value="">— ជ្រើសរើស —</option>
                    @foreach($fundingSources as $source)
                        <option value="{{ $source->id }}" @selected(old('funding_source_id') == $source->id)>{{ $source->name ?? $source->name_km ?? ('#'.$source->id) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">មធ្យោបាយធ្វើដំណើរ</label>
                <select name="transport_type_id" class="form-select">
                    <option value="">— ជ្រើសរើស —</option>
                    @foreach($transportTypes as $transport)
                        <option value="{{ $transport->id }}" @selected(old('transport_type_id') == $transport->id)>{{ $transport->name_km }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-12">
                <label class="form-label">មធ្យោបាយផ្សេងទៀត (បើមាន)</label>
                <input type="text" name="transport_description" class="form-control" maxlength="255" value="{{ old('transport_description') }}">
            </div>
        </div>
    </div>
</div>

<div class="card mb-3 mission-destination-picker" data-gazetteer-url="{{ asset('module-assets/HumanResource/data/cambodia_gazetteer.json') }}">
    <div class="card-header bg-light"><i class="fa fa-map-marker-alt me-2 text-primary"></i><span class="fs-5 fw-bold">ទីតាំងលម្អិត</span> <span class="text-muted small">(ស្រេចចិត្ត)</span></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">ខេត្ត/រាជធានី</label>
                <select class="form-select mission-destination-province" name="destination_province_code" data-initial="{{ old('destination_province_code', $destination->province_code ?? '') }}"></select>
            </div>
            <div class="col-md-6">
                <label class="form-label">ស្រុក/ខណ្ឌ</label>
                <select class="form-select mission-destination-district" name="destination_district_code" data-initial="{{ old('destination_district_code', $destination->district_code ?? '') }}"></select>
            </div>
            <div class="col-md-6">
                <label class="form-label">ឃុំ/សង្កាត់</label>
                <select class="form-select mission-destination-commune" name="destination_commune_code" data-initial="{{ old('destination_commune_code', $destination->commune_code ?? '') }}"></select>
            </div>
            <div class="col-md-6">
                <label class="form-label">ភូមិ</label>
                <select class="form-select mission-destination-village" name="destination_village_code" data-initial="{{ old('destination_village_code', $destination->village_code ?? '') }}"></select>
            </div>
            <div class="col-md-6">
                <label class="form-label">ឈ្មោះមណ្ឌល/ទីកន្លែងច្បាស់លាស់</label>
                <input type="text" class="form-control mission-destination-venue" name="destination_venue_name" maxlength="255" value="{{ old('destination_venue_name', $destination->venue_name ?? '') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label d-block">វិសាលភាព</label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="destination_scope" id="scope-within" value="within_province" @checked(old('destination_scope', $destination->scope->value ?? 'within_province') === 'within_province')>
                    <label class="form-check-label" for="scope-within">ក្នុងខេត្ត</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="destination_scope" id="scope-outside" value="outside_province" @checked(old('destination_scope', $destination->scope->value ?? '') === 'outside_province')>
                    <label class="form-check-label" for="scope-outside">ក្រៅខេត្ត</label>
                </div>
            </div>
        </div>
        <small class="text-muted">ការជ្រើសរើសទីតាំងខាងលើនឹងបំពេញចូលទៅក្នុងប្រអប់ "ទីតាំងទៅបំពេញបេសកកម្ម" ដោយស្វ័យប្រវត្តិ អាចកែប្រែបានដោយផ្ទាល់។</small>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-light"><i class="fa fa-paperclip me-2 text-primary"></i><span class="fs-5 fw-bold">លិខិតយោង</span> <span class="text-muted small">(ស្រេចចិត្ត)</span></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">លេខលិខិត</label>
                <input type="text" name="reference_number" class="form-control" maxlength="150" value="{{ old('reference_number') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">ស្ថាប័នចេញលិខិត</label>
                <input type="text" name="reference_issuer" class="form-control" maxlength="255" value="{{ old('reference_issuer') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">កាលបរិច្ឆេទលិខិត</label>
                <input type="date" name="reference_date" class="form-control" value="{{ old('reference_date') }}">
            </div>
            <div class="col-md-12">
                <label class="form-label">ឯកសារភ្ជាប់</label>
                <input type="file" name="reference_document" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                <small class="text-muted">ត្រូវភ្ជាប់ឯកសារ ដើម្បីអោយព័ត៌មានលិខិតយោងខាងលើត្រូវបានរក្សាទុក។</small>
            </div>
        </div>
    </div>
</div>
