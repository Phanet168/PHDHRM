@php $med = $medicine ?? null; @endphp

<div class="row g-3">
    <div class="col-md-3">
        <label class="form-label">{{ localize('code', 'Code') }} <span class="text-danger">*</span></label>
        <input type="text" class="form-control @error('code') is-invalid @enderror" name="code" value="{{ old('code', $med?->code) }}" required>
        @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-5">
        <label class="form-label">{{ localize('name', 'Name') }} <span class="text-danger">*</span></label>
        <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $med?->name) }}" required>
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ localize('name_kh', 'Name (KH)') }}</label>
        <input type="text" class="form-control" name="name_kh" value="{{ old('name_kh', $med?->name_kh) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ localize('category', 'Category') }}</label>
        <select class="form-select" name="category_id">
            <option value="">-- {{ localize('select', 'Select') }} --</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}" @selected(old('category_id', $med?->category_id) == $cat->id)>{{ $cat->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ localize('dosage_form', 'Dosage form') }}</label>
        <input type="text" class="form-control" name="dosage_form" value="{{ old('dosage_form', $med?->dosage_form) }}" placeholder="tablet, capsule, syrup...">
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ localize('strength', 'Strength') }}</label>
        <input type="text" class="form-control" name="strength" value="{{ old('strength', $med?->strength) }}" placeholder="500mg, 250mg/5ml...">
    </div>
    <div class="col-md-3">
        <label class="form-label">{{ localize('unit', 'Unit') }} <span class="text-danger">*</span></label>
        <input type="text" class="form-control @error('unit') is-invalid @enderror" name="unit" value="{{ old('unit', $med?->unit) }}" required placeholder="tablet, bottle, box...">
        @error('unit') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-5">
        <label class="form-label">{{ localize('manufacturer', 'Manufacturer') }}</label>
        <input type="text" class="form-control" name="manufacturer" value="{{ old('manufacturer', $med?->manufacturer) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ localize('unit_price', 'Unit price') }}</label>
        <input type="number" step="0.01" min="0" class="form-control" name="unit_price" value="{{ old('unit_price', $med?->unit_price ?? 0) }}">
    </div>
</div>
