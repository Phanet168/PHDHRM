@php
    $emp = $employee ?? null;
    $isKhmerUi = app()->getLocale() === 'km';

    $modelDateValue = static function ($model, string $key): string {
        if (!is_object($model)) {
            return '';
        }

        if (method_exists($model, 'getRawOriginal')) {
            $raw = trim((string) $model->getRawOriginal($key));
            if ($raw !== '') {
                return $raw;
            }
        }

        $value = $model->{$key} ?? null;
        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->format('Y-m-d');
        }

        return trim((string) $value);
    };

    $dateValue = static function ($value): string {
        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->format('Y-m-d');
        }

        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        try {
            return \Illuminate\Support\Carbon::parse($text)->format('Y-m-d');
        } catch (\Throwable $e) {
            return '';
        }
    };

    $workExperienceRows = old('work_experiences');
    if (!is_array($workExperienceRows)) {
        $workExperienceRows = $emp
            ? collect($emp->getRelation('workExperiences') ?? [])->map(function ($r) use ($modelDateValue) {
                return [
                    'id' => $r->id,
                    'sector_category' => $r->sector_category,
                    'start_date' => $modelDateValue($r, 'start_date'),
                    'end_date' => $modelDateValue($r, 'end_date'),
                    'position_title' => $r->position_title,
                    'institution_name' => $r->institution_name,
                    'note' => $r->note,
                ];
            })->toArray()
            : [];
    }
    if (empty($workExperienceRows)) {
        $workExperienceRows = [[]];
    }

    $workExperienceTitle = $isKhmerUi ? 'បទពិសោធការងារ' : 'Work experience';
    $workExperienceHint = $isKhmerUi
        ? 'បុគ្គលិកអាចបំពេញបទពិសោធការងាររបស់ខ្លួនបាន ដើម្បីយកទៅប្រើក្នុងរបាយការណ៍ និង template ជីវប្រវត្តិមន្ត្រីរាជការ។'
        : 'Employees can fill in their own work experience for use in reports and the civil servant biography template.';
    $sectorCategoryLabel = $isKhmerUi ? 'វិស័យ' : 'Sector';
    $positionTitleLabel = $isKhmerUi ? 'មុខតំណែង' : 'Position';
    $workplaceLabel = $isKhmerUi ? 'ក្រសួង/ស្ថាប័ន/អង្គការ' : 'Ministry / institution / organization';
    $startDateLabel = $isKhmerUi ? 'ថ្ងៃចូល' : 'Start date';
    $endDateLabel = $isKhmerUi ? 'ថ្ងៃបញ្ចប់' : 'End date';
    $workExperienceSectorOptions = [
        $isKhmerUi ? 'ជាមួយក្រសួងមហាផ្ទៃ/រដ្ឋបាលថ្នាក់ក្រោមជាតិ' : 'With Ministry of Interior / sub-national administration',
        $isKhmerUi ? 'ជាមួយវិស័យរដ្ឋ' : 'With public sector',
        $isKhmerUi ? 'ជាមួយវិស័យឯកជន ឬ អង្គការក្រៅរដ្ឋាភិបាលនានា' : 'With private sector or NGOs',
    ];
@endphp

<div class="gov-section-card mb-3">
    <h5 class="mb-3 fw-semi-bold">{{ $workExperienceTitle }}</h5>
    <div class="gov-section-note mb-2">{{ $workExperienceHint }}</div>

    <template id="work-experience-sector-options-template">
        <option value="">{{ $isKhmerUi ? 'ជ្រើសរើសវិស័យ' : 'Select sector' }}</option>
        @foreach ($workExperienceSectorOptions as $sectorOption)
            <option value="{{ $sectorOption }}">{{ $sectorOption }}</option>
        @endforeach
    </template>

    <div class="table-responsive mb-2">
        <table class="table table-bordered" id="work-experience-table">
            <thead>
                <tr>
                    <th>{{ $sectorCategoryLabel }}</th>
                    <th>{{ $startDateLabel }}</th>
                    <th>{{ $endDateLabel }}</th>
                    <th>{{ $positionTitleLabel }}</th>
                    <th>{{ $workplaceLabel }}</th>
                    <th>{{ localize('remarks') }}</th>
                    <th width="80">{{ localize('action') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($workExperienceRows as $idx => $row)
                    <tr>
                        <td>
                            <input type="hidden" name="work_experiences[{{ $idx }}][id]" value="{{ $row['id'] ?? '' }}">
                            <select name="work_experiences[{{ $idx }}][sector_category]" class="form-select">
                                <option value="">{{ $isKhmerUi ? 'ជ្រើសរើសវិស័យ' : 'Select sector' }}</option>
                                @foreach ($workExperienceSectorOptions as $sectorOption)
                                    <option value="{{ $sectorOption }}" @selected(($row['sector_category'] ?? '') === $sectorOption)>{{ $sectorOption }}</option>
                                @endforeach
                                @if (!empty($row['sector_category'] ?? '') && !collect($workExperienceSectorOptions)->contains($row['sector_category']))
                                    <option value="{{ $row['sector_category'] }}" selected>{{ $row['sector_category'] }}</option>
                                @endif
                            </select>
                        </td>
                        <td><input type="date" name="work_experiences[{{ $idx }}][start_date]" class="form-control" value="{{ $dateValue($row['start_date'] ?? null) }}"></td>
                        <td><input type="date" name="work_experiences[{{ $idx }}][end_date]" class="form-control" value="{{ $dateValue($row['end_date'] ?? null) }}"></td>
                        <td><input type="text" name="work_experiences[{{ $idx }}][position_title]" class="form-control" value="{{ $row['position_title'] ?? '' }}"></td>
                        <td><input type="text" name="work_experiences[{{ $idx }}][institution_name]" class="form-control" value="{{ $row['institution_name'] ?? '' }}"></td>
                        <td><input type="text" name="work_experiences[{{ $idx }}][note]" class="form-control" value="{{ $row['note'] ?? '' }}"></td>
                        <td><button type="button" class="btn btn-sm btn-danger repeater-remove">{{ localize('delete') }}</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <button type="button" class="btn btn-sm btn-primary repeater-add" data-target="#work-experience-table" data-repeater="work_experiences">
        + {{ localize('add_more') }}
    </button>
</div>
