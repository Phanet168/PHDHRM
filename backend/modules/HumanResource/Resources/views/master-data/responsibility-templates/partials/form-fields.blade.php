@php
    $item = $form_item ?? null;
    $selectedActions = collect($item?->action_presets_json ?? old('action_presets', []))
        ->map(fn ($v) => trim(mb_strtolower((string) $v)))
        ->filter()
        ->unique()
        ->values()
        ->all();
@endphp

<div class="row g-3 rt-template-form">
    <div class="col-md-4">
        <label class="form-label">{{ localize('module', 'Module') }} <span class="text-danger">*</span></label>
        <select name="module_key" class="form-control rt-module-key" required>
            <option value="">{{ localize('select_one', 'Select One') }}</option>
            @foreach ($module_options as $moduleKey)
                <option value="{{ $moduleKey }}"
                    @selected(old('module_key', (string) ($item->module_key ?? '')) === (string) $moduleKey)>
                    {{ $moduleKey }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4">
        <label class="form-label">{{ localize('template_key', 'Template key') }} <span class="text-danger">*</span></label>
        <input type="text" name="template_key" class="form-control"
            value="{{ old('template_key', (string) ($item->template_key ?? '')) }}" required>
        <small class="text-muted">{{ localize('template_key_hint', 'Use lowercase key, e.g. corr_office_manager') }}</small>
    </div>

    <div class="col-md-4">
        <label class="form-label">{{ localize('sort_order', 'Sort order') }}</label>
        <input type="number" min="0" max="9999" name="sort_order" class="form-control"
            value="{{ old('sort_order', (int) ($item->sort_order ?? 100)) }}">
    </div>

    <div class="col-md-6">
        <label class="form-label">{{ localize('template_name', 'Template name') }} <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control"
            value="{{ old('name', (string) ($item->name ?? '')) }}" required>
    </div>

    <div class="col-md-6">
        <label class="form-label">{{ localize('template_name_km', 'Template name (KM)') }}</label>
        <input type="text" name="name_km" class="form-control"
            value="{{ old('name_km', (string) ($item->name_km ?? '')) }}">
    </div>

    <div class="col-md-4">
        <label class="form-label">{{ localize('responsibility', 'Responsibility') }} <span class="text-danger">*</span></label>
        <select name="responsibility_id" class="form-control" required>
            <option value="">{{ localize('select_one', 'Select One') }}</option>
            @foreach ($responsibilities as $responsibility)
                <option value="{{ $responsibility->id }}"
                    @selected((int) old('responsibility_id', (int) ($item->responsibility_id ?? 0)) === (int) $responsibility->id)>
                    {{ ($responsibility->name_km ?: $responsibility->name) . ' (' . $responsibility->code . ')' }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4">
        <label class="form-label">{{ localize('default_scope', 'Default scope') }} <span class="text-danger">*</span></label>
        <select name="default_scope_type" class="form-control" required>
            @foreach ($scope_options as $scopeKey)
                <option value="{{ $scopeKey }}"
                    @selected(old('default_scope_type', (string) ($item->default_scope_type ?? 'self_and_children')) === (string) $scopeKey)>
                    {{ $scope_labels[$scopeKey] ?? $scopeKey }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-8">
        <label class="form-label">{{ localize('action_presets', 'Action presets') }}</label>
        <input type="hidden" class="rt-action-presets-selected" value='@json($selectedActions)'>
        <div class="rt-actions-wrap border rounded p-2 bg-light"></div>
    </div>

    <div class="col-md-4">
        <label class="form-label">{{ localize('status', 'Status') }}</label>
        <select name="is_active" class="form-control">
            <option value="1" @selected((int) old('is_active', (int) ($item->is_active ?? 1)) === 1)>{{ localize('active', 'Active') }}</option>
            <option value="0" @selected((int) old('is_active', (int) ($item->is_active ?? 1)) === 0)>{{ localize('inactive', 'Inactive') }}</option>
        </select>
    </div>

    <div class="col-md-12">
        <label class="form-label">{{ localize('note', 'Note') }}</label>
        <textarea name="note" rows="2" class="form-control">{{ old('note', (string) ($item->note ?? '')) }}</textarea>
    </div>
</div>
