@php
    $emp = $employee ?? null;
    $normalizeDateInput = static function ($value): string {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            try {
                return \Illuminate\Support\Carbon::createFromFormat($format, $text)->format('Y-m-d');
            } catch (\Throwable $e) {
            }
        }

        try {
            return \Illuminate\Support\Carbon::parse($text)->format('Y-m-d');
        } catch (\Throwable $e) {
            return '';
        }
    };

    $familyMemberRows = old('family_members');
    if (!is_array($familyMemberRows)) {
        $familyMemberRows = $emp
            ? $emp->familyMembers->map(function ($r) use ($normalizeDateInput) {
                $row = $r->toArray();
                $row['date_of_birth'] = $normalizeDateInput($row['date_of_birth'] ?? $r->date_of_birth);
                return $row;
            })->toArray()
            : [];
    }
    $familyMemberRows = array_map(static function ($row) use ($normalizeDateInput) {
        if (!is_array($row)) {
            return [];
        }
        $row['date_of_birth'] = $normalizeDateInput($row['date_of_birth'] ?? null);
        return $row;
    }, $familyMemberRows);
    if (empty($familyMemberRows)) {
        $familyMemberRows = [[]];
    }

    $familyAttachmentRows = old('family_attachments');
    if (!is_array($familyAttachmentRows)) {
        $familyAttachmentRows = $emp
            ? $emp->sectionAttachments->where('section', 'family')->map(function ($r) use ($normalizeDateInput) {
                $row = $r->toArray();
                $row['expiry_date'] = $normalizeDateInput($row['expiry_date'] ?? null);
                return $row;
            })->toArray()
            : [];
    }
    $familyAttachmentRows = array_map(static function ($row) use ($normalizeDateInput) {
        if (!is_array($row)) {
            return [];
        }
        $row['expiry_date'] = $normalizeDateInput($row['expiry_date'] ?? null);
        return $row;
    }, $familyAttachmentRows);
    if (empty($familyAttachmentRows)) {
        $familyAttachmentRows = [[]];
    }

        $isKhmerUi = app()->getLocale() === 'km';
    $kh = static function (string $unicode): string {
        $decoded = json_decode('"' . $unicode . '"');
        return is_string($decoded) ? $decoded : '';
    };
    $provinceLabel = $isKhmerUi ? $kh('\u1781\u17c1\u178f\u17d2\u178f/\u179a\u17b6\u1787\u1792\u17b6\u1793\u17b8') : 'Province/Capital';
    $districtLabel = $isKhmerUi ? $kh('\u1780\u17d2\u179a\u17bb\u1784/\u179f\u17d2\u179a\u17bb\u1780/\u1781\u178e\u17d2\u178c') : 'City/District/Khan';
    $communeLabel = $isKhmerUi ? $kh('\u1783\u17bb\u17c6/\u179f\u1784\u17d2\u1780\u17b6\u178f\u17cb') : 'Commune/Sangkat';
    $villageLabel = $isKhmerUi ? $kh('\u1797\u17bc\u1798\u17b7') : 'Village';
    $selectProvinceLabel = $isKhmerUi ? $kh('\u1787\u17d2\u179a\u17be\u179f\u179a\u17be\u179f\u1781\u17c1\u178f\u17d2\u178f/\u179a\u17b6\u1787\u1792\u17b6\u1793\u17b8') : 'Select province/capital';
    $selectDistrictLabel = $isKhmerUi ? $kh('\u1787\u17d2\u179a\u17be\u179f\u179a\u17be\u179f\u1780\u17d2\u179a\u17bb\u1784/\u179f\u17d2\u179a\u17bb\u1780/\u1781\u178e\u17d2\u178c') : 'Select city/district/khan';
    $selectCommuneLabel = $isKhmerUi ? $kh('\u1787\u17d2\u179a\u17be\u179f\u179a\u17be\u179f\u1783\u17bb\u17c6/\u179f\u1784\u17d2\u1780\u17b6\u178f\u17cb') : 'Select commune/sangkat';
    $selectVillageLabel = $isKhmerUi ? $kh('\u1787\u17d2\u179a\u17be\u179f\u179a\u17be\u179f\u1797\u17bc\u1798\u17b7') : 'Select village';
    $khGazetteerPath = public_path('module-assets/HumanResource/data/cambodia_gazetteer.json');
    $khGazetteerVersion = is_file($khGazetteerPath) ? filemtime($khGazetteerPath) : time();
    $khGazetteerUrl = asset('module-assets/HumanResource/data/cambodia_gazetteer.json') . '?v=' . $khGazetteerVersion;

    $familyMembersTitle = $isKhmerUi ? $kh('\u179f\u1798\u17b6\u1787\u17b7\u1780\u1782\u17d2\u179a\u17bd\u179f\u17b6\u179a') : 'Family members';
    $familyAttachmentsTitle = $isKhmerUi ? $kh('\u17af\u1780\u179f\u17b6\u179a\u1797\u17d2\u1787\u17b6\u1794\u17cb (\u1782\u17d2\u179a\u17bd\u179f\u17b6\u179a)') : 'Family attachments';
    $viewFileLabel = $isKhmerUi ? $kh('\u1798\u17be\u179b\u17af\u1780\u179f\u17b6\u179a') : 'View file';
    $maleLabel = $isKhmerUi ? $kh('\u1794\u17d2\u179a\u17bb\u179f') : 'Male';
    $femaleLabel = $isKhmerUi ? $kh('\u179f\u17d2\u179a\u17b8') : 'Female';
    $aliveLabel = $isKhmerUi ? $kh('\u1793\u17c5\u179a\u179f\u17cb') : 'Alive';
    $deceasedLabel = $isKhmerUi ? $kh('\u179f\u17d2\u179b\u17b6\u1794\u17cb') : 'Deceased';
    $placeOfBirthLabel = localize('place_of_birth');
    $birthPlaceHint = $isKhmerUi
        ? 'វាលខាងក្រោមជាទីកន្លែងកំណើត៖ ខេត្ត/រាជធានី > ក្រុង/ស្រុក/ខណ្ឌ > ឃុំ/សង្កាត់ > ភូមិ'
        : 'The following fields are place of birth: Province/Capital > City/District/Khan > Commune/Sangkat > Village';

    $familyAddressLabel = localize('current_address');
    if ($familyAddressLabel === 'current_address') {
        $familyAddressLabel = $isKhmerUi ? $kh('\u17a2\u17b6\u179f\u1799\u178a\u17d2\u178b\u17b6\u1793\u1794\u1785\u17d2\u1785\u17bb\u1794\u17d2\u1794\u1793\u17d2\u1793') : 'Current address';
    }
    $familyAddressHint = $isKhmerUi
        ? $kh('\u179f\u17bc\u1798\u1787\u17d2\u179a\u17be\u179f\u17a2\u17b6\u179f\u1799\u178a\u17d2\u178b\u17b6\u1793\u1794\u1785\u17d2\u1785\u17bb\u1794\u17d2\u1794\u1793\u17d2\u1793\u179a\u1794\u179f\u17cb\u179f\u1798\u17b6\u1787\u17b7\u1780\u1782\u17d2\u179a\u17bd\u179f\u17b6\u179a \u178f\u17b6\u1798\u179b\u17c6\u178a\u17b6\u1794\u17cb \u1781\u17c1\u178f\u17d2\u178f/\u179a\u17b6\u1787\u1792\u17b6\u1793\u17b8 > \u1780\u17d2\u179a\u17bb\u1784/\u179f\u17d2\u179a\u17bb\u1780/\u1781\u178e\u17d2\u178c > \u1783\u17bb\u17c6/\u179f\u1784\u17d2\u1780\u17b6\u178f\u17cb > \u1797\u17bc\u1798\u17b7\u17d4')
        : 'The following fields are current address: Province/Capital > City/District/Khan > Commune/Sangkat > Village';

    $relationOptions = [
        'wife' => localize('family_relation_wife'),
        'husband' => localize('family_relation_husband'),
        'son' => localize('family_relation_son'),
        'daughter' => localize('family_relation_daughter'),
        'mother' => localize('family_relation_mother'),
        'father' => localize('family_relation_father'),
    ];

    $salutationOptions = [
        'boy' => localize('salutation_boy'),
        'girl' => localize('salutation_girl'),
        'mr' => localize('salutation_mr'),
        'miss' => localize('salutation_miss'),
        'mrs' => localize('salutation_mrs'),
        'excellency' => localize('salutation_excellency'),
        'lok_chumteav' => localize('salutation_lok_chumteav'),
    ];

    $normalizeFamilyRelation = static function (?string $value): string {
        $raw = trim((string) $value);
        $normalized = mb_strtolower($raw);

        $map = [
            'wife' => 'wife',
            'ប្រពន្ធ' => 'wife',
            'husband' => 'husband',
            'ប្តី' => 'husband',
            'ប្ដី' => 'husband',
            'son' => 'son',
            'កូនប្រុស' => 'son',
            'daughter' => 'daughter',
            'កូនស្រី' => 'daughter',
            'mother' => 'mother',
            'ម្តាយបង្កើត' => 'mother',
            'ម្ដាយបង្កើត' => 'mother',
            'ម្តាយ' => 'mother',
            'ម្ដាយ' => 'mother',
            'father' => 'father',
            'ឪពុកបង្កើត' => 'father',
            'ឳពុកបង្កើត' => 'father',
            'ឪពុក' => 'father',
            'ឳពុក' => 'father',
        ];

        if (isset($map[$normalized])) {
            return $map[$normalized];
        }

        return $raw;
    };

    $normalizeSalutation = static function (?string $value): string {
        $raw = trim((string) $value);
        $normalized = mb_strtolower($raw);

        $map = [
            'boy' => 'boy',
            'កុមារា' => 'boy',
            'girl' => 'girl',
            'កុមារី' => 'girl',
            'mr' => 'mr',
            'mr.' => 'mr',
            'លោក' => 'mr',
            'miss' => 'miss',
            'ms' => 'miss',
            'ms.' => 'miss',
            'កញ្ញា' => 'miss',
            'mrs' => 'mrs',
            'mrs.' => 'mrs',
            'លោកស្រី' => 'mrs',
            'excellency' => 'excellency',
            'ឯកឧត្តម' => 'excellency',
            'lok chumteav' => 'lok_chumteav',
            'lok_chumteav' => 'lok_chumteav',
            'លោកជំទាវ' => 'lok_chumteav',
        ];

        if (isset($map[$normalized])) {
            return $map[$normalized];
        }

        return $raw;
    };
@endphp

<div class="gov-section-card mb-3">
    <h6 class="gov-section-title">{{ $familyMembersTitle }}</h6>
    <p class="text-muted small mb-2">{{ $familyAddressHint }}</p>
    <div id="family-birth-cascade-config"
        class="d-none"
        data-source-url="{{ $khGazetteerUrl }}"
        data-placeholder-province="{{ $selectProvinceLabel }}"
        data-placeholder-district="{{ $selectDistrictLabel }}"
        data-placeholder-commune="{{ $selectCommuneLabel }}"
        data-placeholder-village="{{ $selectVillageLabel }}"
        data-label-alive="{{ $aliveLabel }}"
        data-label-deceased="{{ $deceasedLabel }}"></div>

    <div class="table-responsive mb-2">
        <table class="table table-bordered" id="family-members-table">
            <thead>
                <tr>
                    <th>{{ localize('family_member_type') }}</th>
                    <th>{{ localize('salutation') }}</th>
                    <th>{{ $isKhmerUi ? 'នាមត្រកូល (ខ្មែរ)' : 'Surname (Khmer)' }}</th>
                    <th>{{ $isKhmerUi ? 'នាមខ្លួន (ខ្មែរ)' : 'Given name (Khmer)' }}</th>
                    <th>{{ $isKhmerUi ? 'នាមត្រកូល (ឡាតាំង)' : 'Surname (Latin)' }}</th>
                    <th>{{ $isKhmerUi ? 'នាមខ្លួន (ឡាតាំង)' : 'Given name (Latin)' }}</th>
                    <th>{{ localize('gender') }}</th>
                    <th>{{ localize('nationality') }}</th>
                    <th>{{ localize('ethnic_group') }}</th>
                    <th>{{ $isKhmerUi ? 'មុខរបរ' : 'Occupation' }}</th>
                    <th>{{ $isKhmerUi ? 'ថ្ងៃខែឆ្នាំកំណើត' : 'Date of birth' }}</th>
                    <th>{{ $familyAddressLabel }} - {{ $provinceLabel }}</th>
                    <th>{{ $familyAddressLabel }} - {{ $districtLabel }}</th>
                    <th>{{ $familyAddressLabel }} - {{ $communeLabel }}</th>
                    <th>{{ $familyAddressLabel }} - {{ $villageLabel }}</th>
                    <th>{{ localize('phone') }}</th>
                    <th>{{ $isKhmerUi ? 'មរណភាព' : 'Deceased' }}</th>
                    <th width="80">{{ localize('action') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($familyMemberRows as $idx => $row)
                    @php
                        $relationValue = $normalizeFamilyRelation($row['relation_type'] ?? '');
                        $salutationValue = $normalizeSalutation($row['salutation'] ?? '');
                    @endphp
                    <tr class="family-member-row">
                        <td>
                            <select name="family_members[{{ $idx }}][relation_type]" class="form-select family-relation-type">
                                <option value="">-- {{ localize('select_one') }} --</option>
                                @foreach ($relationOptions as $value => $label)
                                    <option value="{{ $value }}" {{ (string) $relationValue === (string) $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <select name="family_members[{{ $idx }}][salutation]" class="form-select family-salutation">
                                <option value="">-- {{ localize('select_one') }} --</option>
                                @foreach ($salutationOptions as $value => $label)
                                    <option value="{{ $value }}" {{ (string) $salutationValue === (string) $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </td>
                        <td><input type="text" name="family_members[{{ $idx }}][last_name_km]" class="form-control" value="{{ $row['last_name_km'] ?? '' }}"></td>
                        <td><input type="text" name="family_members[{{ $idx }}][first_name_km]" class="form-control" value="{{ $row['first_name_km'] ?? '' }}"></td>
                        <td><input type="text" name="family_members[{{ $idx }}][last_name_latin]" class="form-control" value="{{ $row['last_name_latin'] ?? '' }}"></td>
                        <td><input type="text" name="family_members[{{ $idx }}][first_name_latin]" class="form-control" value="{{ $row['first_name_latin'] ?? '' }}"></td>
                        <td>
                            <select name="family_members[{{ $idx }}][gender]" class="form-select family-member-gender">
                                <option value="">-</option>
                                <option value="male" {{ ($row['gender'] ?? '') === 'male' ? 'selected' : '' }}>{{ $maleLabel }}</option>
                                <option value="female" {{ ($row['gender'] ?? '') === 'female' ? 'selected' : '' }}>{{ $femaleLabel }}</option>
                            </select>
                        </td>
                        <td><input type="text" name="family_members[{{ $idx }}][nationality]" class="form-control" value="{{ $row['nationality'] ?? '' }}"></td>
                        <td><input type="text" name="family_members[{{ $idx }}][ethnicity]" class="form-control" value="{{ $row['ethnicity'] ?? '' }}"></td>
                        <td><input type="text" name="family_members[{{ $idx }}][occupation]" class="form-control" value="{{ $row['occupation'] ?? '' }}"></td>
                        <td><input type="date" name="family_members[{{ $idx }}][date_of_birth]" class="form-control" value="{{ $row['date_of_birth'] ?? '' }}"></td>
                        <td>
                            <select name="family_members[{{ $idx }}][present_address_state]" class="form-select family-birth-province" data-initial="{{ $row['present_address_state'] ?? ($row['birth_place_state'] ?? '') }}">
                                <option value="">{{ $selectProvinceLabel }}</option>
                            </select>
                        </td>
                        <td>
                            <select name="family_members[{{ $idx }}][present_address_city]" class="form-select family-birth-district" data-initial="{{ $row['present_address_city'] ?? ($row['birth_place_city'] ?? '') }}">
                                <option value="">{{ $selectDistrictLabel }}</option>
                            </select>
                        </td>
                        <td>
                            <select name="family_members[{{ $idx }}][present_address_commune]" class="form-select family-birth-commune" data-initial="{{ $row['present_address_commune'] ?? ($row['birth_place_commune'] ?? '') }}">
                                <option value="">{{ $selectCommuneLabel }}</option>
                            </select>
                        </td>
                        <td>
                            <select name="family_members[{{ $idx }}][present_address_village]" class="form-select family-birth-village" data-initial="{{ $row['present_address_village'] ?? ($row['birth_place_village'] ?? '') }}">
                                <option value="">{{ $selectVillageLabel }}</option>
                            </select>
                        </td>
                        <td><input type="text" name="family_members[{{ $idx }}][phone]" class="form-control" value="{{ $row['phone'] ?? '' }}"></td>
                        <td>
                            <select name="family_members[{{ $idx }}][is_deceased]" class="form-select">
                                <option value="0" {{ (string) ($row['is_deceased'] ?? '0') === '0' ? 'selected' : '' }}>{{ $aliveLabel }}</option>
                                <option value="1" {{ (string) ($row['is_deceased'] ?? '') === '1' ? 'selected' : '' }}>{{ $deceasedLabel }}</option>
                            </select>
                        </td>
                        <td><button type="button" class="btn btn-sm btn-danger repeater-remove">{{ localize('delete') }}</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <button type="button" class="btn btn-sm btn-primary repeater-add" data-target="#family-members-table" data-repeater="family_members">
        + {{ localize('add_more') }}
    </button>
</div>

<div class="gov-section-card mb-3">
    <h6 class="gov-section-title">{{ $familyAttachmentsTitle }}</h6>

    <div class="table-responsive mb-2">
        <table class="table table-bordered" id="family-attachments-table">
            <thead>
                <tr>
                    <th>{{ localize('doc_title') }}</th>
                    <th>{{ localize('file') }}</th>
                    <th>{{ localize('expiry_date') }}</th>
                    <th width="80">{{ localize('action') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($familyAttachmentRows as $idx => $row)
                    <tr>
                        <td><input type="text" name="family_attachments[{{ $idx }}][title]" class="form-control" value="{{ $row['title'] ?? '' }}"></td>
                        <td>
                            <input type="file" name="family_attachments[{{ $idx }}][file]" class="form-control">
                            @if (!empty($row['file_path']))
                                <a href="{{ asset('storage/' . $row['file_path']) }}" target="_blank">{{ $row['file_name'] ?? $viewFileLabel }}</a>
                            @endif
                        </td>
                        <td><input type="date" name="family_attachments[{{ $idx }}][expiry_date]" class="form-control" value="{{ $row['expiry_date'] ?? '' }}"></td>
                        <td><button type="button" class="btn btn-sm btn-danger repeater-remove">{{ localize('delete') }}</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <button type="button" class="btn btn-sm btn-primary repeater-add" data-target="#family-attachments-table" data-repeater="family_attachments">
        + {{ localize('add_more') }}
    </button>
</div>
