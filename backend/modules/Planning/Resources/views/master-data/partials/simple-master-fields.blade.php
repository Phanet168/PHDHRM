<div class="col-md-3">
    <label class="form-label">កូដ</label>
    <input type="text" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $record->code) }}" required>
    @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="col-md-5">
    <label class="form-label">ឈ្មោះ</label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $record->name) }}" required>
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="col-md-4">
    <label class="form-label">ឈ្មោះខ្មែរ</label>
    <input type="text" name="name_km" class="form-control @error('name_km') is-invalid @enderror" value="{{ old('name_km', $record->name_km) }}">
    @error('name_km')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="col-12">
    <label class="form-label">ពិពណ៌នា</label>
    <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description', $record->description) }}</textarea>
    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
