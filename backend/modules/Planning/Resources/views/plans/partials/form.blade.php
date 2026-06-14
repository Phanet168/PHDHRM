@php
    $existingItems = old('items');

    if ($existingItems === null) {
        $existingItems = $plan->exists
            ? $plan->items->where('item_type', 'indicator_result')->values()->map(function ($item) {
                $indicatorRow = $item->indicators->first();

                return [
                    'responsible_org_unit_id' => $item->responsible_org_unit_id,
                    'indicator_id' => $indicatorRow?->indicator_id,
                    'item_code' => $item->item_code,
                    'title' => $item->title,
                    'description' => $item->description,
                    'baseline_value' => $indicatorRow?->baseline_value,
                    'target_value' => $indicatorRow?->target_value,
                    'achieved_value' => $indicatorRow?->achieved_value,
                    'target_unit' => $indicatorRow?->value_text ?: $item->target_unit,
                    'indicator_note' => $indicatorRow?->note,
                    'created_at' => optional($item->created_at)->format('Y-m-d H:i'),
                ];
            })->toArray()
            : [];
    }

    $currentOrgUnitName = $currentOrgUnit?->name ?? 'មិនមានអង្គភាព';
    $currentOrgUnitId = $currentOrgUnit?->id;
@endphp

<div class="planning-form-section">
    <div class="planning-form-section-header mb-3">
        <div>
            <div class="planning-section-title mb-1">ជំហានទី១: កំណត់សូចនាករ និងគោលដៅ</div>
            <div class="planning-meta">បញ្ចូលឆ្នាំ និងអង្គភាព រួចកំណត់សូចនាករ សម្រេចបានឆ្នាំចាស់ និងគោលដៅរបស់អង្គភាព។</div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-3">
            <label class="form-label">ឆ្នាំផែនការ</label>
            <input type="number" name="year" class="form-control @error('year') is-invalid @enderror" value="{{ old('year', $plan->year ?: now()->year) }}" required>
            @error('year')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-5">
            <label class="form-label">អង្គភាព</label>
            <input type="text" class="form-control" value="{{ $currentOrgUnitName }}" readonly>
            @if ($currentOrgUnitId)
                <input type="hidden" name="org_unit_id" value="{{ $currentOrgUnitId }}">
            @endif
        </div>
        <div class="col-md-4">
            <label class="form-label">កាលបរិច្ឆេទបង្កើត</label>
            <input type="text" class="form-control" value="{{ $plan->exists ? optional($plan->created_at)->format('Y-m-d H:i') : now()->format('Y-m-d H:i') }}" readonly>
        </div>

        <div class="col-md-6">
            <label class="form-label">ចំណងជើងផែនការ</label>
            <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $plan->title) }}" placeholder="ទុកទទេបាន ប្រព័ន្ធនឹងបង្កើតឈ្មោះស្វ័យប្រវត្តិ">
            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">លេខយោង</label>
            <input type="text" name="reference_no" class="form-control @error('reference_no') is-invalid @enderror" value="{{ old('reference_no', $plan->reference_no) }}">
            @error('reference_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-12">
            <label class="form-label">គោលបំណង/កំណត់ចំណាំទូទៅ</label>
            <textarea name="objective" rows="3" class="form-control @error('objective') is-invalid @enderror">{{ old('objective', $plan->objective) }}</textarea>
            @error('objective')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

<div class="planning-form-section">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
        <div>
            <div class="planning-section-title mb-1">តារាងសូចនាករ និងគោលដៅ</div>
            <div class="planning-meta">ចុចប៊ូតុងបន្ថែម ដើម្បីបញ្ចូលសូចនាករ និងគោលដៅថ្មី ហើយទិន្នន័យនឹងបង្ហាញក្នុងតារាងខាងក្រោមភ្លាមៗ។</div>
        </div>
        <button class="btn btn-success" type="button" id="add-plan-item">
            <i class="fa fa-plus me-1"></i>បន្ថែមទិន្នន័យ
        </button>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered align-middle planning-table mb-0">
            <thead class="table-light">
                <tr>
                    <th style="min-width: 140px;">កាលបរិច្ឆេទ</th>
                    <th style="min-width: 280px;">សូចនាករ</th>
                    <th style="min-width: 220px;">អ្នកទទួលខុសត្រូវ</th>
                    <th class="text-end" style="min-width: 150px;">សម្រេចបានឆ្នាំចាស់</th>
                    <th class="text-end" style="min-width: 120px;">គោលដៅ</th>
                    <th class="text-end" style="min-width: 170px;">សម្រេចបានបច្ចុប្បន្ន</th>
                    <th style="min-width: 180px;">ឯកតា/ចំណងជើង</th>
                    <th class="text-end" style="min-width: 140px;">សកម្មភាព</th>
                </tr>
            </thead>
            <tbody id="plan-items-container">
                @foreach ($existingItems as $index => $item)
                    @include('planning::plans.partials.item-card', [
                        'index' => $index,
                        'item' => $item,
                        'orgUnits' => $orgUnits,
                        'indicators' => $indicators,
                        'defaultOrgUnitId' => $currentOrgUnitId,
                    ])
                @endforeach
            </tbody>
        </table>
    </div>

    @error('items')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
    <div id="no-items-placeholder" class="planning-empty mt-3 @if(count($existingItems) > 0) d-none @endif">
        មិនទាន់មានទិន្នន័យក្នុងតារាងទេ។ សូមចុច “បន្ថែមទិន្នន័យ” ដើម្បីចាប់ផ្តើម។
    </div>
</div>

<div class="planning-form-section">
    <div class="planning-section-title">កំណត់ចំណាំពេលដាក់ស្នើ</div>
    <textarea name="submission_note" rows="3" class="form-control">{{ old('submission_note') }}</textarea>
</div>

<div class="d-flex justify-content-between align-items-center gap-2 mb-4">
    <a href="{{ $plan->exists ? route('planning.plans.show', $plan) : route('planning.plans.index') }}" class="btn btn-light border">បោះបង់</a>
    <div class="d-flex gap-2">
        @if($plan->exists)
            <a href="{{ route('planning.plans.micro-plan.edit', $plan) }}" class="btn btn-outline-secondary">ទៅជំហានទី២</a>
        @endif
        <button type="submit" class="btn btn-success">{{ $submitLabel }}</button>
    </div>
</div>

<template id="plan-item-template">
    @include('planning::plans.partials.item-card', [
        'index' => '__INDEX__',
        'item' => [],
        'orgUnits' => $orgUnits,
        'indicators' => $indicators,
        'defaultOrgUnitId' => $currentOrgUnitId,
    ])
</template>

<div class="modal fade" id="planItemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success-subtle">
                <div>
                    <h5 class="modal-title mb-1">បញ្ចូលសូចនាករ និងគោលដៅ</h5>
                    <div class="small text-muted">បំពេញព័ត៌មានខាងក្រោម រួចចុចរក្សាទុក ដើម្បីបញ្ចូលទៅក្នុងតារាង។</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">កាលបរិច្ឆេទបង្កើត</label>
                        <input type="text" class="form-control" data-modal-field="created_at" readonly>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">សូចនាករ</label>
                        <select class="form-select" data-modal-field="indicator_id">
                            <option value="">ជ្រើសរើសសូចនាករ</option>
                            @foreach ($indicators as $indicator)
                                <option value="{{ $indicator->id }}">{{ $indicator->code }} - {{ $indicator->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">អ្នកទទួលខុសត្រូវ</label>
                        <select class="form-select" data-modal-field="responsible_org_unit_id">
                            <option value="">ជ្រើសរើសអង្គភាព</option>
                            @foreach ($orgUnits as $orgUnit)
                                <option value="{{ $orgUnit->id }}" @selected((string) $currentOrgUnitId === (string) $orgUnit->id)>{{ $orgUnit->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">កូដជួរ</label>
                        <input type="text" class="form-control" data-modal-field="item_code">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">ឯកតា</label>
                        <input type="text" class="form-control" data-modal-field="target_unit" placeholder="ឧ. នាក់, %, ដង">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">ចំណងជើង</label>
                        <input type="text" class="form-control" data-modal-field="title" placeholder="ទុកទទេបាន">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">សម្រេចបានឆ្នាំចាស់</label>
                        <input type="number" step="0.01" class="form-control" data-modal-field="baseline_value">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">គោលដៅ</label>
                        <input type="number" step="0.01" class="form-control" data-modal-field="target_value">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">សម្រេចបានបច្ចុប្បន្ន</label>
                        <input type="number" step="0.01" class="form-control" data-modal-field="achieved_value">
                    </div>
                    <div class="col-12">
                        <label class="form-label">សេចក្តីពិពណ៌នា</label>
                        <textarea class="form-control" rows="2" data-modal-field="description"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">កំណត់ចំណាំសូចនាករ</label>
                        <textarea class="form-control" rows="2" data-modal-field="indicator_note"></textarea>
                    </div>
                </div>
                <div class="alert alert-danger mt-3 d-none" id="plan-item-modal-error"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">បោះបង់</button>
                <button type="button" class="btn btn-success" id="save-plan-item-modal">រក្សាទុកទៅតារាង</button>
            </div>
        </div>
    </div>
</div>

@push('planning-scripts')
    <script>
        (() => {
            const container = document.getElementById('plan-items-container');
            const addItemButton = document.getElementById('add-plan-item');
            const itemTemplate = document.getElementById('plan-item-template');
            const placeholder = document.getElementById('no-items-placeholder');
            const modalElement = document.getElementById('planItemModal');
            const modal = new bootstrap.Modal(modalElement);
            const saveButton = document.getElementById('save-plan-item-modal');
            const errorBox = document.getElementById('plan-item-modal-error');
            const modalFields = {
                created_at: modalElement.querySelector('[data-modal-field="created_at"]'),
                indicator_id: modalElement.querySelector('[data-modal-field="indicator_id"]'),
                responsible_org_unit_id: modalElement.querySelector('[data-modal-field="responsible_org_unit_id"]'),
                item_code: modalElement.querySelector('[data-modal-field="item_code"]'),
                title: modalElement.querySelector('[data-modal-field="title"]'),
                baseline_value: modalElement.querySelector('[data-modal-field="baseline_value"]'),
                target_value: modalElement.querySelector('[data-modal-field="target_value"]'),
                achieved_value: modalElement.querySelector('[data-modal-field="achieved_value"]'),
                target_unit: modalElement.querySelector('[data-modal-field="target_unit"]'),
                description: modalElement.querySelector('[data-modal-field="description"]'),
                indicator_note: modalElement.querySelector('[data-modal-field="indicator_note"]'),
            };

            let itemIndex = {{ count($existingItems) }};
            let editingRow = null;

            const defaultOrgUnitId = @json((string) $currentOrgUnitId);
            const indicatorOptionSeed = Array.from(modalFields.indicator_id.options)
                .slice(1)
                .map((option) => ({
                    value: option.value,
                    label: option.textContent.trim(),
                }));

            const updatePlaceholder = () => {
                placeholder.classList.toggle('d-none', container.children.length > 0);
            };

            const formatNumber = (value) => {
                if (value === '' || value === null || typeof value === 'undefined') {
                    return '-';
                }

                const parsed = Number(value);
                return Number.isNaN(parsed) ? value : parsed.toFixed(2);
            };

            const resetModal = () => {
                editingRow = null;
                errorBox.classList.add('d-none');
                errorBox.textContent = '';
                modalFields.created_at.value = new Date().toISOString().slice(0, 16).replace('T', ' ');
                modalFields.indicator_id.value = '';
                modalFields.responsible_org_unit_id.value = defaultOrgUnitId || '';
                modalFields.item_code.value = '';
                modalFields.title.value = '';
                modalFields.baseline_value.value = '';
                modalFields.target_value.value = '';
                modalFields.achieved_value.value = '';
                modalFields.target_unit.value = '';
                modalFields.description.value = '';
                modalFields.indicator_note.value = '';
                refreshIndicatorOptions('');
            };

            const getIndicatorLabel = (indicatorId) => {
                const option = modalFields.indicator_id.querySelector(`option[value="${indicatorId}"]`);
                return option ? option.textContent.trim() : '-';
            };

            const getOrgUnitLabel = (orgUnitId) => {
                const option = modalFields.responsible_org_unit_id.querySelector(`option[value="${orgUnitId}"]`);
                return option ? option.textContent.trim() : '-';
            };

            const getUsedIndicatorIds = (exceptRow = null) => Array.from(container.children)
                .filter((row) => row !== exceptRow)
                .map((row) => row.querySelector('[data-field="indicator_id"]')?.value)
                .filter((value) => value);

            const refreshIndicatorOptions = (selectedIndicatorId = '') => {
                const usedIndicatorIds = getUsedIndicatorIds(editingRow);
                const availableOptions = indicatorOptionSeed.filter((option) => {
                    return !usedIndicatorIds.includes(option.value) || String(option.value) === String(selectedIndicatorId);
                });

                modalFields.indicator_id.innerHTML = '<option value="">ជ្រើសរើសសូចនាករ</option>';
                availableOptions.forEach((option) => {
                    const optionElement = document.createElement('option');
                    optionElement.value = option.value;
                    optionElement.textContent = option.label;
                    if (String(option.value) === String(selectedIndicatorId)) {
                        optionElement.selected = true;
                    }
                    modalFields.indicator_id.appendChild(optionElement);
                });
            };

            const setRowValues = (row, values) => {
                Object.entries(values).forEach(([field, value]) => {
                    const input = row.querySelector(`[data-field="${field}"]`);
                    if (input) {
                        input.value = value ?? '';
                    }
                });

                row.querySelector('[data-display="created_at"]').textContent = values.created_at || '-';
                row.querySelector('[data-display="indicator_name"]').textContent = getIndicatorLabel(values.indicator_id);
                row.querySelector('[data-display="item_code"]').textContent = values.item_code || '-';
                row.querySelector('[data-display="responsible_org_unit_name"]').textContent = getOrgUnitLabel(values.responsible_org_unit_id);
                row.querySelector('[data-display="baseline_value"]').textContent = formatNumber(values.baseline_value);
                row.querySelector('[data-display="target_value"]').textContent = formatNumber(values.target_value);
                row.querySelector('[data-display="achieved_value"]').textContent = formatNumber(values.achieved_value);
                row.querySelector('[data-display="target_unit"]').textContent = values.target_unit || '-';
                row.querySelector('[data-display="title"]').textContent = values.title || '-';
            };

            const collectModalValues = () => ({
                created_at: modalFields.created_at.value.trim(),
                indicator_id: modalFields.indicator_id.value,
                responsible_org_unit_id: modalFields.responsible_org_unit_id.value,
                item_code: modalFields.item_code.value.trim(),
                title: modalFields.title.value.trim(),
                baseline_value: modalFields.baseline_value.value,
                target_value: modalFields.target_value.value,
                achieved_value: modalFields.achieved_value.value,
                target_unit: modalFields.target_unit.value.trim(),
                description: modalFields.description.value.trim(),
                indicator_note: modalFields.indicator_note.value.trim(),
            });

            const validateModal = (values) => {
                if (!values.indicator_id) {
                    return 'សូមជ្រើសរើសសូចនាករ។';
                }

                if (getUsedIndicatorIds(editingRow).includes(values.indicator_id)) {
                    return 'សូចនាករនេះមានរួចហើយ។ មិនអាចបន្ថែមស្ទួនបានទេ។';
                }

                if (!values.responsible_org_unit_id) {
                    return 'សូមជ្រើសរើសអ្នកទទួលខុសត្រូវ។';
                }

                return null;
            };

            const bindRowActions = (row) => {
                row.querySelector('[data-remove-item]').onclick = () => {
                    row.remove();
                    refreshIndicatorOptions('');
                    updatePlaceholder();
                };

                row.querySelector('[data-edit-item]').onclick = () => {
                    editingRow = row;
                    errorBox.classList.add('d-none');
                    Object.keys(modalFields).forEach((field) => {
                        const input = row.querySelector(`[data-field="${field}"]`);
                        modalFields[field].value = input ? input.value : '';
                    });
                    refreshIndicatorOptions(modalFields.indicator_id.value);
                    modal.show();
                };
            };

            addItemButton.addEventListener('click', () => {
                resetModal();
                modal.show();
            });

            saveButton.addEventListener('click', () => {
                const values = collectModalValues();
                const validationMessage = validateModal(values);

                if (validationMessage) {
                    errorBox.textContent = validationMessage;
                    errorBox.classList.remove('d-none');
                    return;
                }

                errorBox.classList.add('d-none');

                if (!editingRow) {
                    const html = itemTemplate.innerHTML.replaceAll('__INDEX__', itemIndex++);
                    container.insertAdjacentHTML('beforeend', html);
                    editingRow = container.lastElementChild;
                    bindRowActions(editingRow);
                }

                setRowValues(editingRow, values);
                modal.hide();
                refreshIndicatorOptions('');
                updatePlaceholder();
            });

            document.querySelector('form').addEventListener('submit', (event) => {
                const indicatorIds = Array.from(container.children)
                    .map((row) => row.querySelector('[data-field="indicator_id"]')?.value)
                    .filter((value) => value);

                const uniqueIds = new Set(indicatorIds);
                if (indicatorIds.length !== uniqueIds.size) {
                    event.preventDefault();
                    alert('សូចនាករមានស្ទួនក្នុងជំហានទី១។ សូមលុបសូចនាករដដែលចេញ មុននឹងរក្សាទុក។');
                }
            });

            modalElement.addEventListener('hidden.bs.modal', () => {
                if (!editingRow) {
                    resetModal();
                }
                refreshIndicatorOptions('');
                errorBox.classList.add('d-none');
            });

            Array.from(container.children).forEach((row) => bindRowActions(row));
            refreshIndicatorOptions('');
            updatePlaceholder();
        })();
    </script>
@endpush
