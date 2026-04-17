@php
    $emp = $employee ?? null;
    $extra = $emp?->profileExtra;
    $workPermitValue = (string) old('work_permit', (string) (int) ($emp?->work_permit ?? 0));
@endphp

<div class="border rounded p-3 mb-2 gov-civil-service-card">
    <div class="form-group mb-2 mx-0 row">
        <label class="col-lg-3 col-form-label ps-0">ប័ណ្ណមន្ត្រីរាជការ</label>
        <div class="col-lg-9">
            <div class="d-flex gap-3 mt-1">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="work_permit" id="work_permit_yes" value="1" onchange="toggleCivilServiceCardFields && toggleCivilServiceCardFields();"
                        {{ $workPermitValue === '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="work_permit_yes">{{ localize('yes') }}</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="work_permit" id="work_permit_no" value="0" onchange="toggleCivilServiceCardFields && toggleCivilServiceCardFields();"
                        {{ $workPermitValue !== '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="work_permit_no">{{ localize('no') }}</label>
                </div>
            </div>
        </div>
    </div>

    <div class="form-group mb-2 mx-0 row civil-service-card-fields" @if ($workPermitValue !== '1') hidden @endif>
        <label for="card_no" class="col-lg-3 col-form-label ps-0">លេខប័ណ្ណមន្ត្រីរាជការ</label>
        <div class="col-lg-9">
            <input type="text" name="card_no" id="card_no" class="form-control"
                value="{{ old('card_no', $emp?->card_no) }}" placeholder="បញ្ចូលលេខប័ណ្ណមន្ត្រីរាជការ">
            @if ($errors->has('card_no'))
                <div class="error text-danger text-start">{{ $errors->first('card_no') }}</div>
            @endif
        </div>
    </div>

    <div class="form-group mb-0 mx-0 row civil-service-card-fields" @if ($workPermitValue !== '1') hidden @endif>
        <label for="civil_service_card_expiry_date" class="col-lg-3 col-form-label ps-0">សុពលភាពប័ណ្ណ (ផុតកំណត់)</label>
        <div class="col-lg-9">
            <input type="date" name="civil_service_card_expiry_date" id="civil_service_card_expiry_date"
                class="form-control"
                value="{{ old('civil_service_card_expiry_date', optional($extra?->civil_service_card_expiry_date)->format('Y-m-d')) }}">
            @if ($errors->has('civil_service_card_expiry_date'))
                <div class="error text-danger text-start">{{ $errors->first('civil_service_card_expiry_date') }}</div>
            @endif
        </div>
    </div>
</div>
