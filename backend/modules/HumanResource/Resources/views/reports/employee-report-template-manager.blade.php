@extends('backend.layouts.app')
@section('title', 'ការគ្រប់គ្រងរបាយការណ៍បុគ្គលិក')

@push('css')
<style>
    .report-generator .report-intro { background: #eef5ff; border: 1px solid #dce7f6; border-radius: 12px; }
    .report-generator .report-step { width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; background: #e9f1ff; color: #245aab; flex-shrink: 0; }
    .report-generator .report-section { border: 1px solid #e4e9f0; border-radius: 10px; padding: 24px; background: #fff; }
    .report-generator .report-field { display: flex; align-items: start; gap: 10px; padding: 10px; border: 1px solid #e4e9f0; border-radius: 6px; height: 100%; cursor: pointer; }
    .report-generator .report-field:has(input:checked) { background: #f0f6ff; border-color: #a9c9f4; }
    .report-generator .report-field input { flex-shrink: 0; margin-top: 4px; }
    .report-generator .report-summary { position: sticky; top: 24px; }
    .report-generator .report-summary dd { overflow-wrap: anywhere; }
    .report-generator .report-format { flex: 1; min-width: 75px; }
    .report-generator .report-format label { width: 100%; padding: 12px 4px; }
    .report-generator details > summary { cursor: pointer; padding: 12px 0; }
    @media (max-width: 991px) { .report-generator .report-summary { position: static; } }
    @media (max-width: 575px) { .report-generator .report-section { padding: 16px; } }
</style>
@endpush

@section('content')
    @include('humanresource::reports_header')
    @include('backend.layouts.common.validation')
    @php
        $generatorColumns = (array) old('columns', $selectedColumns);
        $templateChoices = $templates->where('is_active', true)->mapWithKeys(fn ($item) => [$item->uuid => ['name' => $item->name, 'columns' => $item->columns]])->all();
        $showSettings = $editingTemplate || old('_template_form');
    @endphp
    <div class="report-generator">
        <div class="report-intro p-4 mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <div class="small text-primary mb-2">{{ 'គ្រប់គ្រងបុគ្គលិក' }} / របាយការណ៍</div>
                <h4 class="mb-2">បង្កើតរបាយការណ៍តាមតម្រូវការ</h4>
                <p class="text-muted mb-0">ជ្រើសព័ត៌មាន និងលក្ខខណ្ឌដែលអ្នកត្រូវការ រួចបង្កើតឯកសារសម្រាប់ទាញយក។</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-primary" id="quick-skill-summary">រាប់ចំនួនតាមអង្គភាព ជំនាញ និងភេទ</button>
                <a href="#template-settings" class="btn btn-outline-primary" id="open-template-settings"><i class="fa fa-cog me-1" aria-hidden="true"></i> គ្រប់គ្រងគំរូ</a>
            </div>
        </div>

        <form id="report-generator-form" method="POST" action="{{ route('reports.employee-report-templates.generate') }}">
            @csrf
            <div class="row g-4">
                <div class="col-lg-8">
                    <section class="report-section mb-4" aria-labelledby="report-step-one">
                        <h6 id="report-step-one" class="d-flex align-items-center gap-2 mb-4"><span class="report-step">១</span> ជ្រើសរបាយការណ៍</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="report-mode" class="form-label">តើអ្នកចង់បានរបាយការណ៍អ្វី?</label>
                                <select id="report-mode" name="mode" class="form-select">
                                    <option value="detail" @selected(old('mode', 'detail') === 'detail')>ព័ត៌មានបុគ្គលិកលម្អិត</option>
                                    <option value="summary" @selected(old('mode') === 'summary')>ចំនួនសរុបតាមក្រុម</option>
                                    <option value="workplace" @selected(old('mode') === 'workplace')>ព័ត៌មានអង្គភាពលម្អិត</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="report-layout" class="form-label">របៀបរៀបចំរបាយការណ៍</label>
                                <select id="report-layout" name="layout" class="form-select">
                                    <option value="plain" @selected(old('layout', 'plain') === 'plain')>បញ្ជីរួម</option>
                                    <option value="structured" @selected(old('layout') === 'structured')>បែងចែកតាមអង្គភាពមេ និងអង្គភាពរង</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="report-preset" class="form-label">ជ្រើសព័ត៌មានដែលត្រៀមរួច</label>
                                <select id="report-preset" class="form-select">
                                    @foreach ($reportTypeOptions as $key => $label)
                                        <option value="{{ $key }}" @selected($key === 'custom')>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="generator-template" class="form-label">ប្រើគំរូដែលបានរក្សាទុក</label>
                                <select id="generator-template" name="template" class="form-select">
                                    <option value="">កំណត់ដោយផ្ទាល់</option>
                                    @foreach ($templates->where('is_active', true) as $template)
                                        <option value="{{ $template->uuid }}" @selected((string) old('template', request('template')) === (string) $template->uuid)>{{ $template->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="report-title" class="form-label">ចំណងជើងរបាយការណ៍ <span class="text-danger">*</span></label>
                                <input id="report-title" name="title" class="form-control" maxlength="150" required value="{{ old('title', $selectedTemplate?->name ?: 'របាយការណ៍បុគ្គលិក') }}">
                            </div>
                        </div>
                        <div id="structure-summary-hint" class="alert alert-info mt-3 mb-0" hidden>
                            ឧទាហរណ៍៖ ជ្រើស «ជំនាញ» ដើម្បីបង្ហាញអង្គភាពនៅជួរដេក និងជំនាញនៅជួរឈរ។ ជ្រើស «បែងចែកប្រុស និងស្រី» ដើម្បីបង្ហាញចំនួនតាមភេទក្រោមអង្គភាពនីមួយៗ។
                            <div class="mt-2">ចំនួនអង្គភាពមេរួមបញ្ចូលអង្គភាពរងរួចហើយ។ កុំបូកចំនួនមេ និងកូនបញ្ចូលគ្នាម្តងទៀត។</div>
                        </div>
                    </section>

                    <section class="report-section mb-4" aria-labelledby="report-step-two">
                        <h6 id="report-step-two" class="d-flex align-items-center gap-2 mb-4"><span class="report-step">២</span> កំណត់លក្ខខណ្ឌ និងព័ត៌មាន</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="department_id" class="form-label">អង្គភាព</label>
                                <select id="department_id" name="department_id" class="form-select">
                                    <option value="">គ្រប់អង្គភាព</option>
                                    @foreach ($departmentTreeOptions as $department)
                                        <option value="{{ $department->id }}" @selected((string) old('department_id', request('department_id')) === (string) $department->id) title="{{ $department->path }}">{{ $department->label }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">រួមបញ្ចូលអង្គភាពរងក្រោមអង្គភាពដែលបានជ្រើស។</div>
                            </div>
                            <div class="col-md-6">
                                <label for="position_id" class="form-label">មុខតំណែង</label>
                                <select id="position_id" name="position_id" class="form-select">
                                    <option value="">គ្រប់មុខតំណែង</option>
                                    @foreach ($positions as $position)
                                        <option value="{{ $position->id }}" @selected((string) old('position_id', request('position_id')) === (string) $position->id)>{{ $position->position_name_km ?: $position->position_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="status" class="form-label">ស្ថានភាព</label>
                                <select id="status" name="status" class="form-select">
                                    <option value="">ទាំងអស់</option>
                                    <option value="active" @selected(old('status', request('status')) === 'active')>{{ 'សកម្ម' }}</option>
                                    <option value="inactive" @selected(old('status', request('status')) === 'inactive')>{{ 'អសកម្ម' }}</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="group_by" class="form-label">បន្ថែមសង្ខេបចំនួនតាមក្រុម</label>
                                <select id="group_by" name="group_by" class="form-select">
                                    <option value="">មិនបន្ថែម</option>
                                    @foreach ($groupByOptions as $key => $label)
                                        <option value="{{ $key }}" @selected(old('group_by', request('group_by')) === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text" id="group-format-hint">តារាងសង្ខេបបន្ថែមមានក្នុងឯកសារអិចសែល និងភីឌីអេហ្វ។</div>
                                <label class="form-check mt-2" id="split-gender-option" hidden>
                                    <input type="checkbox" class="form-check-input" name="split_gender" value="1" @checked(old('split_gender'))>
                                    <span class="form-check-label">បែងចែកប្រុស និងស្រី</span>
                                </label>
                            </div>
                            <div class="col-12">
                                <label for="keyword" class="form-label">ពាក្យស្វែងរក (ជាជម្រើស)</label>
                                <input id="keyword" name="keyword" class="form-control" maxlength="150" value="{{ old('keyword', request('keyword')) }}" placeholder="ឈ្មោះ លេខបុគ្គលិក ទូរសព្ទ ឬអ៊ីមែល">
                            </div>
                        </div>
                        @include('humanresource::reports.employee-report-generator-filters')
                        <div id="report-column-settings">
                        <hr class="my-4">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                            <h6 class="mb-0">ព័ត៌មានដែលត្រូវបញ្ចូលក្នុងរបាយការណ៍</h6>
                            <span class="badge bg-primary" id="selected-column-count" aria-live="polite"></span>
                        </div>
                        <div class="d-flex gap-2 mb-3">
                            <button type="button" class="btn btn-sm btn-outline-primary" data-report-columns="all">ជ្រើសទាំងអស់</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-report-columns="none">ដោះចេញទាំងអស់</button>
                        </div>
                        <div id="report-columns-error" class="alert alert-warning" role="alert" hidden>សូមជ្រើសព័ត៌មានយ៉ាងហោចណាស់មួយសម្រាប់របាយការណ៍។</div>
                        @foreach ($columnGroups as $groupKey => $group)
                            <details class="border-bottom" @if ($loop->first) open @endif>
                                <summary class="fw-semibold">{{ $group['label'] }} <span class="badge bg-light text-dark border ms-2" data-report-group-count="{{ $groupKey }}"></span></summary>
                                <div class="row g-2 pb-3">
                                    @foreach ($group['columns'] as $key)
                                        @if (isset($columnOptions[$key]))
                                            <div class="col-md-6">
                                                <label class="report-field" for="report-column-{{ $key }}">
                                                    <input id="report-column-{{ $key }}" type="checkbox" class="form-check-input" name="columns[]" value="{{ $key }}" data-report-group="{{ $groupKey }}" @checked(in_array($key, $generatorColumns, true))>
                                                    <span>{{ $columnOptions[$key] }}</span>
                                                </label>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </details>
                        @endforeach
                        </div>
                    </section>
                </div>

                <div class="col-lg-4">
                    <section class="report-section report-summary mb-4" aria-labelledby="report-step-three">
                        <h6 id="report-step-three" class="d-flex align-items-center gap-2 mb-4"><span class="report-step">៣</span> បង្កើត និងទាញយក</h6>
                        <dl class="small mb-4">
                            <dt class="text-muted mb-1">ចំណងជើង</dt><dd id="summary-title" class="mb-3"></dd>
                            <dt class="text-muted mb-1">អង្គភាព</dt><dd id="summary-department" class="mb-3"></dd>
                            <dt class="text-muted mb-1">មុខតំណែង</dt><dd id="summary-position" class="mb-3"></dd>
                            <dt class="text-muted mb-1">ស្ថានភាព</dt><dd id="summary-status" class="mb-3"></dd>
                            <dt class="text-muted mb-1">សង្ខេបតាមក្រុម</dt><dd id="summary-group" class="mb-0"></dd>
                        </dl>
                        <fieldset class="mb-3">
                            <legend class="fs-6">ទម្រង់ឯកសារ</legend>
                            <div class="d-flex gap-2">
                                @foreach (['excel' => 'អិចសែល', 'pdf' => 'ភីឌីអេហ្វ', 'csv' => 'ស៊ីអេសវី'] as $key => $label)
                                    <div class="report-format">
                                        <input type="radio" class="btn-check" name="format" id="format-{{ $key }}" value="{{ $key }}" @checked(old('format', 'excel') === $key) required>
                                        <label class="btn btn-outline-primary" for="format-{{ $key }}">{{ $label }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>
                        <p class="small text-muted" id="format-description" aria-live="polite"></p>
                        <button class="btn btn-primary w-100 py-3" type="submit"><i class="fa fa-download me-1" aria-hidden="true"></i> បង្កើត និងទាញយក</button>
                        <button class="btn btn-outline-primary w-100 mt-2" type="submit" name="format" value="view" formtarget="_blank"><i class="fa fa-eye me-1" aria-hidden="true"></i> មើលរបាយការណ៍ និងបោះពុម្ព</button>
                        <a class="btn btn-link text-muted w-100 mt-2" href="{{ route('reports.employee-report-templates.index') }}">កំណត់ឡើងវិញ</a>
                        <div id="report-generation-status" class="small text-primary mt-3" role="status" hidden></div>
                    </section>
                </div>
            </div>
        </form>

        <details id="template-settings" class="report-section mb-4" @if ($showSettings) open @endif>
            <summary class="fw-semibold">គ្រប់គ្រងគំរូរបាយការណ៍ដែលបានរក្សាទុក</summary>
            <p class="small text-muted">រក្សាទុកគំរូព័ត៌មានសម្រាប់របាយការណ៍ដែលអ្នកប្រើញឹកញាប់។</p>
            @include('humanresource::reports.employee-report-template-settings')
        </details>
    </div>
@endsection

@push('js')
<script>
(function () {
    'use strict';
    const form = document.getElementById('report-generator-form');
    const boxes = Array.from(form.querySelectorAll('[name="columns[]"]'));
    const presets = @json($reportPresets);
    const templates = @json($templateChoices);
    const preset = document.getElementById('report-preset');
    const template = document.getElementById('generator-template');
    const title = document.getElementById('report-title');
    const group = document.getElementById('group_by');
    const mode = document.getElementById('report-mode');
    const layout = document.getElementById('report-layout');
    const error = document.getElementById('report-columns-error');
    const status = document.getElementById('report-generation-status');
    const selectedText = id => document.getElementById(id).selectedOptions[0]?.textContent.trim() || '';
    function refresh() {
        const count = boxes.filter(box => box.checked).length;
        document.getElementById('selected-column-count').textContent = count + ' ព័ត៌មានបានជ្រើស';
        form.querySelectorAll('[data-report-group-count]').forEach(badge => {
            const groupBoxes = boxes.filter(box => box.dataset.reportGroup === badge.dataset.reportGroupCount);
            badge.textContent = groupBoxes.filter(box => box.checked).length + '/' + groupBoxes.length;
        });
        if (count) error.hidden = true;
        document.getElementById('summary-title').textContent = title.value || '-' ;
        document.getElementById('summary-department').textContent = selectedText('department_id');
        document.getElementById('summary-position').textContent = selectedText('position_id');
        document.getElementById('summary-status').textContent = selectedText('status');
        const format = form.querySelector('[name="format"]:checked').value;
        group.disabled = mode.value === 'workplace';
        group.required = mode.value === 'summary';
        if (mode.value === 'summary' && !group.value) group.value = 'department';
        layout.disabled = mode.value === 'workplace';
        const structureSummary = mode.value === 'summary' && layout.value === 'structured';
        document.getElementById('structure-summary-hint').hidden = !structureSummary;
        document.querySelector('label[for="group_by"]').textContent = structureSummary ? 'ក្រុមនៅជួរឈរ (ឧ. ជំនាញ)' : 'បន្ថែមសង្ខេបចំនួនតាមក្រុម';
        document.getElementById('report-column-settings').hidden = mode.value !== 'detail';
        boxes.forEach(box => { box.disabled = mode.value !== 'detail'; });
        document.getElementById('split-gender-option').hidden = mode.value !== 'summary';
        document.querySelector('[name="split_gender"]').disabled = mode.value !== 'summary';
        document.getElementById('group-format-hint').hidden = mode.value !== 'detail';
        document.getElementById('selected-column-count').hidden = mode.value !== 'detail';
        document.getElementById('summary-group').textContent = group.disabled ? '—' : selectedText('group_by');
        document.getElementById('format-description').textContent = {
            excel: 'អិចសែល សម្រាប់វិភាគ និងកែសម្រួលទិន្នន័យបន្ត។',
            pdf: 'ភីឌីអេហ្វ សម្រាប់បោះពុម្ព និងចែករំលែករបាយការណ៍។',
            csv: mode.value === 'summary' ? 'ស៊ីអេសវី រួមបញ្ចូលចំនួនសរុបតាមក្រុមដែលបានជ្រើស។' : 'ស៊ីអេសវី សម្រាប់ផ្ទេរទិន្នន័យ។ មិនរួមបញ្ចូលចំណងជើង និងសង្ខេបបន្ថែម។'
        }[format];
    }
    function chooseColumns(columns) {
        boxes.forEach(box => { box.checked = columns.includes(box.value); });
        refresh();
    }
    [mode, layout].forEach(control => control.addEventListener('change', () => {
        if (mode.value === 'summary' && layout.value === 'structured') {
            if (group.querySelector('option[value="skill_name"]') && (!group.value || group.value === 'department')) group.value = 'skill_name';
            document.querySelector('[name="split_gender"]').checked = true;
        }
        refresh();
    }));
    document.getElementById('quick-skill-summary').addEventListener('click', () => {
        mode.value = 'summary';
        layout.value = 'structured';
        group.value = 'skill_name';
        document.querySelector('[name="split_gender"]').checked = true;
        title.value = 'របាយការណ៍ចំនួនបុគ្គលិកតាមអង្គភាព ជំនាញ និងភេទ';
        status.hidden = true;
        refresh();
        document.getElementById('report-step-two').scrollIntoView({ block: 'start', behavior: 'smooth' });
    });
    preset.addEventListener('change', () => {
        template.value = '';
        chooseColumns(presets[preset.value] || []);
        title.value = selectedText('report-preset');
        refresh();
    });
    template.addEventListener('change', () => {
        preset.value = 'custom';
        const choice = templates[template.value];
        if (choice) { title.value = choice.name; chooseColumns(choice.columns || []); }
        refresh();
    });
    form.querySelectorAll('[data-report-columns]').forEach(button => {
        button.addEventListener('click', () => {
            chooseColumns(button.dataset.reportColumns === 'all' ? boxes.map(box => box.value) : []);
            preset.value = 'custom';
            status.hidden = true;
        });
    });
    form.querySelectorAll('[data-combination]').forEach(button => {
        button.addEventListener('click', () => {
            const select = document.getElementById('filter-' + button.dataset.combination);
            Array.from(select.options).forEach(option => { option.selected = button.dataset.select === 'all'; });
            status.hidden = true;
        });
    });
    boxes.forEach(box => box.addEventListener('change', () => { preset.value = 'custom'; }));
    form.addEventListener('input', () => { status.hidden = true; refresh(); });
    form.addEventListener('change', () => { status.hidden = true; refresh(); });
    form.addEventListener('submit', event => {
        if (mode.value === 'detail' && !boxes.some(box => box.checked)) {
            event.preventDefault();
            error.hidden = false;
            error.scrollIntoView({ block: 'center', behavior: 'smooth' });
            return;
        }
        status.hidden = false;
        status.textContent = event.submitter?.value === 'view'
            ? 'កំពុងបើករបាយការណ៍ក្នុងផ្ទាំងថ្មី។'
            : 'កំពុងបង្កើតឯកសារ។ សូមរង់ចាំការទាញយកបន្តិច។';
    });
    document.getElementById('open-template-settings').addEventListener('click', () => {
        document.getElementById('template-settings').open = true;
    });
    const settings = document.getElementById('template-settings');
    function setTemplateColumns(selector, checked) {
        settings.querySelectorAll(selector).forEach(box => { box.checked = checked; });
    }
    settings.querySelector('.js-select-all-columns').addEventListener('click', () => setTemplateColumns('.js-column-checkbox', true));
    settings.querySelector('.js-clear-all-columns').addEventListener('click', () => setTemplateColumns('.js-column-checkbox', false));
    settings.querySelectorAll('.js-select-group, .js-clear-group').forEach(button => {
        button.addEventListener('click', () => setTemplateColumns('.js-column-checkbox[data-group="' + button.dataset.group + '"]', button.classList.contains('js-select-group')));
    });
    refresh();
})();
</script>
@endpush
