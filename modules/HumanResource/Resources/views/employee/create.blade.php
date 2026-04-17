@extends('backend.layouts.app')
@section('title', localize('employee_information'))
@push('css')
    <link rel="stylesheet" href="{{ asset('backend/assets/plugins/tagsinput/bootstrap-tagsinput.css') }}">
    <link rel="stylesheet" href="{{ module_asset('HumanResource/css/employee.css') }}">
@endpush
@section('content')
    @include('humanresource::employee_header')
    <div class="card mb-3 fixed-tab-body">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="fs-17 fw-semi-bold mb-0">{{ localize('employee_information') }}</h6>
                </div>
                <div class="text-end">
                    <div class="actions">
                        <a href="{{ route('employees.index') }}" class="btn btn-success btn-sm"><i class="fa fa-list"></i>&nbsp; {{ localize('employee_list') }}</a>

                    </div>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row justify-content-center">
                <div class="col-md-12 text-center">
                    <form action="{{ route('employees.store') }}" method="POST" class="f1" novalidate
                        enctype="multipart/form-data">
                        @csrf
                        @php
                            $isKhmerUi = app()->getLocale() === 'km';
                            $orgServiceGroupTitle = $isKhmerUi ? 'ព័ត៌មានអង្គភាព និងស្ថានភាពមន្ត្រី' : 'Organization and Service Status';
                            $fullRightGroupTitle = $isKhmerUi ? 'ព័ត៌មានមន្ត្រីពេញសិទ្ធ' : 'Full-right Officer';
                            $fullRightDocsGroupTitle = $isKhmerUi ? 'ព័ត៌មានឯកសារពេញសិទ្ធ' : 'Full-right Supporting Documents';
                            $employeeTypeGroupTitle = $isKhmerUi ? 'ព័ត៌មានប្រភេទបុគ្គលិក' : 'Employee Type';
                            $officialIdLabel = $isKhmerUi ? 'អត្តលេខមន្ត្រី (១០ ខ្ទង់)' : 'Official Staff ID (10 digits)';
                            $officialIdPlaceholder = $isKhmerUi ? 'ឧ. 1234567890' : 'e.g. 1234567890';
                            $officialIdHint = $isKhmerUi
                                ? 'លេខនេះចេញដោយក្រសួងមុខងារ (មិនដូចលេខប័ណ្ណមន្ត្រីរាជការ)'
                                : 'Issued by the civil service ministry (different from civil servant card number).';
                            $employeeTypeLabelMap = $isKhmerUi
                                ? [
                                    'full time' => 'បុគ្គលិកក្របខ័ណ្ឌរដ្ឋ',
                                    'contractual' => 'បុគ្គលិកកិច្ចសន្យា',
                                    'remote' => 'បុគ្គលិកកិច្ចព្រមព្រៀងការងារ',
                                    'intern' => 'បុគ្គលជួលដោយថវិកាហិរញ្ញប្បទាន',
                                ]
                                : [
                                    'full time' => 'State Cadre Employee',
                                    'contractual' => 'Contract Employee',
                                    'remote' => 'Work Agreement Employee',
                                    'intern' => 'Grant-funded Employee',
                                ];
                            $isDisabledOptions = [1 => localize('yes'), 0 => localize('no')];
                            $supervisorTitle = $isKhmerUi ? 'អ្នកគ្រប់គ្រង / ប្រធាន' : 'Supervisor';
                            $cgpLabel = $isKhmerUi ? 'និទ្ទេស / GPA' : 'CGPA / GPA';
                            $telegramLabel = 'Telegram';
                            $facebookLabel = 'Facebook';
                            $telegramPlaceholder = $isKhmerUi ? '@username ឬ លេខទូរសព្ទ Telegram' : '@username or Telegram phone number';
                            $facebookPlaceholder = $isKhmerUi ? 'តំណភ្ជាប់ ឬ ឈ្មោះគណនី Facebook' : 'Facebook profile link or account name';
                            $workStatusManageHint = $isKhmerUi
                                ? 'ស្ថានភាពការងារទូទៅ (សកម្ម/អសកម្ម) គ្រប់គ្រងដាច់ដោយឡែក។'
                                : 'General service state is managed in a dedicated workflow.';
                            $defaultWorkStatusLabel = $isKhmerUi ? 'កំពុងបម្រើការងារ' : 'Active in service';
                            $civilServicePhaseLabel = $isKhmerUi ? 'ស្ថានភាពមន្ត្រីក្របខ័ណ្ឌ' : 'Civil servant phase';
                            $probationStatusLabel = $isKhmerUi ? 'មន្ត្រីចុះកម្មសិក្សា' : 'Probationary officer';
                            $fullRightStatusLabel = $isKhmerUi ? 'មន្ត្រីពេញសិទ្ធ' : 'Full-right officer';
                            $expectedFullRightDateLabel = $isKhmerUi ? 'ថ្ងៃគ្រោងពេញសិទ្ធ (១ឆ្នាំ)' : 'Expected full-right date (1 year)';
                            $civilServantFlowHint = $isKhmerUi
                                ? 'សម្រាប់បុគ្គលិកក្របខ័ណ្ឌរដ្ឋ៖ ចាប់ផ្តើមជាកម្មសិក្សា ហើយអាចពេញសិទ្ធក្រោយការវាយតម្លៃ ១ឆ្នាំ។'
                                : 'For state cadre staff: probation first, then full-right after one-year evaluation.';
                            $currentCivilPhaseLabel = old('is_full_right_officer', '0') === '1' ? $fullRightStatusLabel : $probationStatusLabel;
                            $medicalInformationTitle = $isKhmerUi ? 'ព័ត៌មានវេជ្ជសាស្ត្រ' : 'Medical information';
                        @endphp
                        <div class="f1-steps">
                            <div class="f1-progress">
                                <div class="f1-progress-line" data-now-value="14.2857" data-number-of-steps="7"
                                    style="width: 14.2857%;"></div>
                            </div>
                            <div class="f1-step active">
                                <div class="f1-step-icon"><i class="fa fa-user"></i></div>
                                <p>{{ localize('personal_information') }}</p>
                            </div>
                            <div class="f1-step">
                                <div class="f1-step-icon"><i class="fa fa-info-circle"></i></div>
                                <p>{{ localize('organization_information') }}</p>
                            </div>
                            <div class="f1-step">
                                <div class="f1-step-icon"><i class="fa fa-address-card"></i></div>
                                <p>{{ localize('family_information') }}</p>
                            </div>
                            <div class="f1-step">
                                <div class="f1-step-icon"><i class="fa fa-university"></i></div>
                                <p>{{ localize('bank_information') }}</p>
                            </div>
                            <div class="f1-step">
                                <div class="f1-step-icon"><i class="fa fa-users"></i></div>
                                <p>{{ localize('training_information') }}</p>
                            </div>
                            <div class="f1-step">
                                <div class="f1-step-icon"><i class="fa fa-heartbeat"></i></div>
                                <p>{{ $medicalInformationTitle }}</p>
                            </div>
                            <div class="f1-step">
                                <div class="f1-step-icon"><i class="fa fa-info"></i></div>
                                <p>{{ localize('operation_information') }}</p>
                            </div>
                        </div>
                        <fieldset>
                            <h5 class="mb-3 fw-semi-bold">{{ localize('personal_information') }}</h5>
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <div class="gov-section-card h-100">
                                        <h6 class="gov-section-title">{{ localize('basic_identity') }}</h6>
                                        @input(['input_name' => 'last_name', 'label' => localize('surname_km'), 'additional_class' => 'required-field'])
                                        @input(['input_name' => 'first_name', 'label' => localize('given_name_km'), 'additional_class' => 'required-field'])
                                        @input(['input_name' => 'last_name_latin', 'label' => localize('surname_latin'), 'required' => false])
                                        @input(['input_name' => 'first_name_latin', 'label' => localize('given_name_latin'), 'required' => false])
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="gov-section-card h-100">
                                        <h6 class="gov-section-title">{{ localize('contact_information') }}</h6>
                                        @input(['input_name' => 'email', 'label' => localize('email'), 'type' => 'email', 'additional_id' => 'email', 'required' => false])
                                        @input(['input_name' => 'phone', 'label' => localize('phone'), 'required' => false])
                                        @input(['input_name' => 'alternate_phone', 'label' => localize('alternate_phone'), 'required' => false])
                                        <div class="form-group mb-2 mx-0 row">
                                            <label for="telegram_account" class="col-lg-3 col-form-label ps-0">{{ $telegramLabel }}</label>
                                            <div class="col-lg-9">
                                                <input type="text" name="telegram_account" id="telegram_account" class="form-control"
                                                    value="{{ old('telegram_account') }}" placeholder="{{ $telegramPlaceholder }}" autocomplete="off">
                                                @if ($errors->has('telegram_account'))
                                                    <div class="error text-danger text-start">{{ $errors->first('telegram_account') }}</div>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="form-group mb-2 mx-0 row">
                                            <label for="facebook_account" class="col-lg-3 col-form-label ps-0">{{ $facebookLabel }}</label>
                                            <div class="col-lg-9">
                                                <input type="text" name="facebook_account" id="facebook_account" class="form-control"
                                                    value="{{ old('facebook_account') }}" placeholder="{{ $facebookPlaceholder }}" autocomplete="off">
                                                @if ($errors->has('facebook_account'))
                                                    <div class="error text-danger text-start">{{ $errors->first('facebook_account') }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @include('humanresource::employee.partials.additional-personal')

                            <div class="f1-buttons">
                                <button type="button" class="btn btn-success btn-next">{{ localize('next') }}</button>
                            </div>
                        </fieldset>

                        <fieldset class="gov-org-form">
                            <h5 class="mb-3 fw-semi-bold">{{ localize('organization_information') }}:</h5>
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <div class="gov-section-card h-100">
                                    <h6 class="gov-section-title">{{ localize('organization_information') }}</h6>
                                    <div class="gov-subgroup mb-3">
                                    <h6 class="gov-subgroup-title">{{ $orgServiceGroupTitle }}</h6>
                                    <div class="cust_border form-group mb-3 mx-0 pb-3 row">
                                        <label for="department_id"
                                            class="col-sm-3 col-form-label ps-0">{{ localize('department') }}
                                            <span class="text-danger">*</span>
                                        </label>
                                        <div class="col-lg-9">
                                            <div id="org-unit-cascade" class="mb-2"
                                                data-initial="{{ old('department_id') }}"></div>
                                            <small class="text-muted d-block mb-2">{{ localize('select_parent_from_tree_hint') }}</small>
                                            <select name="department_id" class="form-select required-field org-unit-fallback"
                                                id="department">
                                                <option value="">{{ localize('select_parent_unit') }}</option>
                                                @foreach ($departments as $key => $department)
                                                    <option value="{{ $department->id }}"
                                                        {{ old('department_id') == $department->id ? 'selected' : '' }}>
                                                        {{ $department->label ?? $department->department_name }}</option>
                                                @endforeach
                                            </select>
                                            @if ($errors->has('department_id'))
                                                <div class="error text-danger text-start">
                                                    {{ $errors->first('department_id') }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="cust_border form-group mb-3 mx-0 pb-3 row">
                                        <label for="position"
                                            class="col-sm-3 col-form-label ps-0">{{ localize('position') }}
                                            <span class="text-danger">*</span>
                                        </label>
                                        <div class="col-lg-9">
                                            <select name="position_id" class="form-select required-field">
                                                <option value="">{{ localize('select_position') }}</option>
                                                @foreach ($positions as $key => $position)
                                                    <option value="{{ $position->id }}"
                                                        {{ old('position_id') == $position->id ? 'selected' : '' }}>
                                                        {{ $position->position_name_km ?: $position->position_name }}</option>
                                                @endforeach
                                            </select>
                                            @if ($errors->has('position_id'))
                                                <div class="error text-danger text-start">
                                                    {{ $errors->first('position_id') }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    @input(['input_name' => 'contract_start_date', 'type' => 'date', 'additional_class' => 'contractual'])
                                    @input(['input_name' => 'contract_end_date', 'type' => 'date', 'additional_class' => 'contractual'])
                                    <div class="form-group mb-2 mx-0 row">
                                        <label for="service_start_date" class="col-lg-3 col-form-label ps-0">{{ localize('service_start_date') }}</label>
                                        <div class="col-lg-9">
                                            <input type="date" name="service_start_date" id="service_start_date" class="form-control" value="{{ old('service_start_date') }}">
                                            @if ($errors->has('service_start_date'))
                                                <div class="error text-danger text-start">{{ $errors->first('service_start_date') }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="form-group mb-2 mx-0 row">
                                        <label for="service_state" class="col-lg-3 col-form-label ps-0">{{ localize('service_state') }}</label>
                                        <div class="col-lg-9">
                                            <input type="text" id="service_state" class="form-control" value="{{ localize('active') }}" readonly>
                                            <small class="text-muted d-block mt-1">{{ $workStatusManageHint }}</small>
                                        </div>
                                    </div>
                                    <div class="form-group mb-2 mx-0 row">
                                        <label for="work_status_name" class="col-lg-3 col-form-label ps-0">{{ localize('work_status') }}</label>
                                        <div class="col-lg-9">
                                            <input type="text" id="work_status_name" class="form-control"
                                                value="{{ $defaultWorkStatusLabel }}" readonly>
                                        </div>
                                    </div>
                                    </div>
                                </div>
                                </div>
                            </div>
                            @include('humanresource::employee.partials.org-work-profile')

                            <div class="gov-section-card mb-3">
                                <h6 class="gov-section-title">{{ $fullRightGroupTitle }}</h6>
                                <div class="gov-subgroup mb-2">
                                    <div class="form-group mb-2 mx-0 row">
                                        <label for="civil_service_phase_status" class="col-lg-3 col-form-label ps-0">{{ $civilServicePhaseLabel }}</label>
                                        <div class="col-lg-9">
                                            <input type="text" id="civil_service_phase_status" class="form-control"
                                                value="{{ $currentCivilPhaseLabel }}" data-probation-label="{{ $probationStatusLabel }}"
                                                data-full-right-label="{{ $fullRightStatusLabel }}" readonly>
                                            <small class="text-muted d-block mt-1">{{ $civilServantFlowHint }}</small>
                                        </div>
                                    </div>
                                    <div class="form-group mb-2 mx-0 row">
                                        <label for="probation_expected_date" class="col-lg-3 col-form-label ps-0">{{ $expectedFullRightDateLabel }}</label>
                                        <div class="col-lg-9">
                                            <input type="date" id="probation_expected_date" class="form-control" readonly>
                                        </div>
                                    </div>
                                    <div class="form-group mb-0 mx-0 row">
                                        <label for="is_full_right_officer" class="col-lg-3 col-form-label ps-0">{{ localize('full_right_officer') }} <span class="text-danger">*</span></label>
                                        <div class="col-lg-9">
                                            <select name="is_full_right_officer" id="is_full_right_officer" class="form-select">
                                                <option value="0" {{ old('is_full_right_officer', '0') == '0' ? 'selected' : '' }}>{{ localize('not_full_right_yet') }}</option>
                                                <option value="1" {{ old('is_full_right_officer') == '1' ? 'selected' : '' }}>{{ localize('full_right') }}</option>
                                            </select>
                                            @if ($errors->has('is_full_right_officer'))
                                                <div class="error text-danger text-start">{{ $errors->first('is_full_right_officer') }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="gov-subgroup mb-2">
                                    <h6 class="gov-subgroup-title">{{ $fullRightDocsGroupTitle }}</h6>
                                    <div class="form-group mb-2 mx-0 row">
                                        <label for="full_right_date" class="col-lg-3 col-form-label ps-0">{{ localize('full_right_date') }}</label>
                                        <div class="col-lg-9">
                                            <input type="date" name="full_right_date" id="full_right_date" class="form-control" value="{{ old('full_right_date') }}">
                                            @if ($errors->has('full_right_date'))
                                                <div class="error text-danger text-start">{{ $errors->first('full_right_date') }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="form-group mb-2 mx-0 row">
                                        <label for="legal_document_type" class="col-lg-3 col-form-label ps-0">{{ localize('legal_document_type') }}</label>
                                        <div class="col-lg-9">
                                            <select name="legal_document_type" id="legal_document_type" class="form-select">
                                                <option value="">{{ localize('select_one') }}</option>
                                                <option value="royal_decree" {{ old('legal_document_type') == 'royal_decree' ? 'selected' : '' }}>{{ localize('royal_decree') }}</option>
                                                <option value="sub_decree" {{ old('legal_document_type') == 'sub_decree' ? 'selected' : '' }}>{{ localize('sub_decree') }}</option>
                                                <option value="decision" {{ old('legal_document_type') == 'decision' ? 'selected' : '' }}>{{ localize('decision') }}</option>
                                                <option value="proclamation" {{ old('legal_document_type') == 'proclamation' ? 'selected' : '' }}>{{ localize('proclamation') }}</option>
                                                <option value="deika" {{ old('legal_document_type') == 'deika' ? 'selected' : '' }}>{{ localize('deika') }}</option>
                                                <option value="other" {{ old('legal_document_type') == 'other' ? 'selected' : '' }}>{{ localize('other') }}</option>
                                            </select>
                                            @if ($errors->has('legal_document_type'))
                                                <div class="error text-danger text-start">{{ $errors->first('legal_document_type') }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="form-group mb-2 mx-0 row">
                                        <label for="legal_document_number" class="col-lg-3 col-form-label ps-0">{{ localize('legal_document_number') }}</label>
                                        <div class="col-lg-9">
                                            <input type="text" name="legal_document_number" id="legal_document_number" class="form-control"
                                                value="{{ old('legal_document_number') }}" placeholder="{{ localize('legal_document_number_example') }}">
                                            @if ($errors->has('legal_document_number'))
                                                <div class="error text-danger text-start">{{ $errors->first('legal_document_number') }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="form-group mb-2 mx-0 row">
                                        <label for="legal_document_date" class="col-lg-3 col-form-label ps-0">{{ localize('legal_document_date') }}</label>
                                        <div class="col-lg-9">
                                            <input type="date" name="legal_document_date" id="legal_document_date" class="form-control" value="{{ old('legal_document_date') }}">
                                            @if ($errors->has('legal_document_date'))
                                                <div class="error text-danger text-start">{{ $errors->first('legal_document_date') }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="form-group mb-0 mx-0 row">
                                        <label for="legal_document_subject" class="col-lg-3 col-form-label ps-0">{{ localize('legal_document_subject') }}</label>
                                        <div class="col-lg-9">
                                            <textarea name="legal_document_subject" id="legal_document_subject" rows="2" class="form-control">{{ old('legal_document_subject') }}</textarea>
                                            @if ($errors->has('legal_document_subject'))
                                                <div class="error text-danger text-start">{{ $errors->first('legal_document_subject') }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group mb-2 mx-0 row">
                                    <label for="official_id_10" class="col-lg-3 col-form-label ps-0">{{ $officialIdLabel }}</label>
                                    <div class="col-lg-9">
                                        <input type="text" name="official_id_10" id="official_id_10" maxlength="10" pattern="[0-9]{10}" inputmode="numeric"
                                            class="form-control" value="{{ old('official_id_10') }}" placeholder="{{ $officialIdPlaceholder }}">
                                        <small class="text-muted d-block mt-1">{{ $officialIdHint }}</small>
                                        @if ($errors->has('official_id_10'))
                                            <div class="error text-danger text-start">{{ $errors->first('official_id_10') }}</div>
                                        @endif
                                    </div>
                                </div>
                                @include('humanresource::employee.partials.civil-servant-card-section')
                            </div>

                            <div class="f1-buttons">
                                <button type="button" class="btn btn-previous">{{ localize('previous') }}</button>
                                <button type="button" class="btn btn-success btn-next">{{ localize('next') }}</button>
                            </div>
                        </fieldset>

                        <fieldset>
                            <h5 class="mb-3 fw-semi-bold">{{ localize('family_information') }}:</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <h5>ព័ត៌មានគ្រួសារ</h5>
                                    @php
                                        $statusRows = collect($marital_statuses ?? []);
                                        $findStatusId = static function (array $keywords) use ($statusRows) {
                                            foreach ($statusRows as $status) {
                                                $name = mb_strtolower(trim((string) ($status->name ?? '')));
                                                foreach ($keywords as $keyword) {
                                                    $needle = mb_strtolower(trim((string) $keyword));
                                                    if ($needle !== '' && (str_contains($name, $needle) || $name === $needle)) {
                                                        return $status->id;
                                                    }
                                                }
                                            }
                                            return null;
                                        };

                                        $singleStatusId = $findStatusId(['single', 'នៅលីវ']);
                                        $marriedStatusId = $findStatusId(['married', 'រៀបការ']);
                                        $widowedStatusId = $findStatusId(['widow', 'widowed', 'មេម៉ាយ', 'ពោះម៉ាយ']);
                                        $selectedMaritalStatusId = old('marital_status_id');

                                        $renderedStatusIds = [];
                                        $statusOptions = [];
                                        if (!empty($marriedStatusId)) {
                                            $statusOptions[] = ['id' => $marriedStatusId, 'key' => 'married', 'label' => localize('marital_married')];
                                            $renderedStatusIds[] = (int) $marriedStatusId;
                                        }
                                        if (!empty($singleStatusId)) {
                                            $statusOptions[] = ['id' => $singleStatusId, 'key' => 'single', 'label' => localize('marital_single')];
                                            $renderedStatusIds[] = (int) $singleStatusId;
                                        }
                                        if (!empty($widowedStatusId)) {
                                            $statusOptions[] = ['id' => $widowedStatusId, 'key' => 'widowed', 'label' => localize('marital_widowed_female')];
                                            $renderedStatusIds[] = (int) $widowedStatusId;
                                        }
                                        if (empty($statusOptions)) {
                                            foreach ($statusRows as $status) {
                                                $statusOptions[] = ['id' => $status->id, 'key' => 'other', 'label' => $status->name];
                                                $renderedStatusIds[] = (int) $status->id;
                                            }
                                        }
                                    @endphp
                                    <div class="cust_border form-group mb-3 mx-0 pb-3 row">
                                        <label for="marital_status"
                                            class="col-sm-3 col-form-label ps-0">{{ localize('marital_status') }}</label>
                                        <div class="col-lg-9">
                                            <select name="marital_status_id" id="marital_status_id" class="form-select"
                                                data-single-id="{{ $singleStatusId }}"
                                                data-married-id="{{ $marriedStatusId }}"
                                                data-widowed-id="{{ $widowedStatusId }}">
                                                <option value="">{{ localize('select_marital_status') }}</option>
                                                @foreach ($statusOptions as $statusOption)
                                                    <option
                                                        value="{{ $statusOption['id'] }}"
                                                        data-status-key="{{ $statusOption['key'] }}"
                                                        data-label-male="{{ localize('marital_widowed_male') }}"
                                                        data-label-female="{{ localize('marital_widowed_female') }}"
                                                        {{ (string) $selectedMaritalStatusId === (string) $statusOption['id'] ? 'selected' : '' }}>
                                                        {{ $statusOption['label'] }}
                                                    </option>
                                                @endforeach

                                                @if (!empty($selectedMaritalStatusId) && !in_array((int) $selectedMaritalStatusId, $renderedStatusIds, true))
                                                    @php
                                                        $fallbackStatus = $statusRows->firstWhere('id', (int) $selectedMaritalStatusId);
                                                    @endphp
                                                    @if ($fallbackStatus)
                                                        <option value="{{ $fallbackStatus->id }}" data-status-key="other" selected>{{ $fallbackStatus->name }}</option>
                                                    @endif
                                                @endif
                                            </select>

                                            @if ($errors->has('marital_status_id'))
                                                <div class="error text-danger text-start">
                                                    {{ $errors->first('marital_status_id') }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    <input type="hidden" name="no_of_kids" id="no_of_kids" value="{{ old('no_of_kids', 0) }}">
                                    <input type="hidden" name="spouse_count" id="spouse_count" value="{{ old('spouse_count', 0) }}">
                                    <small class="text-muted d-block offset-3 mb-2">{{ localize('family_summary_auto') }}</small>
                                    @input(['input_name' => 'profile_image', 'type' => 'file', 'accept' => 'image/*', 'tooltip' => localize('Attached Passport Size photo'), 'required' => false])
                                </div>
                                <div class="col-md-6">
                                    <h5>{{ localize('emergency_contact') }}</h5>
                                    @input(['input_name' => 'emergency_contact_person', 'required' => false])
                                    @input(['input_name' => 'emergency_contact_relationship', 'required' => false])
                                    @input(['input_name' => 'emergency_contact', 'required' => false, 'type' => 'number'])
                                </div>
                            </div>
                            @include('humanresource::employee.partials.family-profile')
                            <div class="f1-buttons">
                                <button type="button" class="btn btn-previous">{{ localize('previous') }}</button>
                                <button type="button" class="btn btn-success btn-next">{{ localize('next') }}</button>
                            </div>
                        </fieldset>

                        <fieldset>
                            <h5 class="mb-3 fw-semi-bold">{{ localize('bank_information') }}:</h5>
                            @include('humanresource::employee.partials.bank-profile')
                            <div class="f1-buttons">
                                <button type="button" class="btn btn-previous">{{ localize('previous') }}</button>
                                <button type="button" class="btn btn-success btn-next">{{ localize('next') }}</button>
                            </div>
                        </fieldset>

                        <fieldset>
                            <h5 class="mb-3 fw-semi-bold">{{ localize('training_information') }}:</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-2 mx-0 row">
                                        <label for="degree_name" class="col-lg-3 col-form-label ps-0">{{ localize('degree_name') }}</label>
                                        <div class="col-lg-9">
                                            <input type="text" name="degree_name" id="degree_name"
                                                class="form-control {{ $errors->first('degree_name') ? 'is-invalid' : '' }}"
                                                value="{{ old('degree_name') }}" autocomplete="off">
                                            @if ($errors->has('degree_name'))
                                                <div class="error text-danger text-start">{{ $errors->first('degree_name') }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="form-group mb-2 mx-0 row">
                                        <label for="university_name" class="col-lg-3 col-form-label ps-0">{{ localize('university_name') }}</label>
                                        <div class="col-lg-9">
                                            <input type="text" name="university_name" id="university_name"
                                                class="form-control {{ $errors->first('university_name') ? 'is-invalid' : '' }}"
                                                value="{{ old('university_name') }}" autocomplete="off">
                                            @if ($errors->has('university_name'))
                                                <div class="error text-danger text-start">{{ $errors->first('university_name') }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-2 mx-0 row">
                                        <label for="passing_year" class="col-lg-3 col-form-label ps-0">{{ localize('passing_year') }}</label>
                                        <div class="col-lg-9">
                                            <input type="number" min="1900" max="2100" step="1" name="passing_year" id="passing_year"
                                                class="form-control {{ $errors->first('passing_year') ? 'is-invalid' : '' }}"
                                                value="{{ old('passing_year') }}" autocomplete="off">
                                            @if ($errors->has('passing_year'))
                                                <div class="error text-danger text-start">{{ $errors->first('passing_year') }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="form-group mb-2 mx-0 row">
                                        <label for="cgp" class="col-lg-3 col-form-label ps-0">{{ $cgpLabel }}</label>
                                        <div class="col-lg-9">
                                            <input type="text" name="cgp" id="cgp"
                                                class="form-control {{ $errors->first('cgp') ? 'is-invalid' : '' }}"
                                                value="{{ old('cgp') }}" autocomplete="off">
                                            @if ($errors->has('cgp'))
                                                <div class="error text-danger text-start">{{ $errors->first('cgp') }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @include('humanresource::employee.partials.training-profile')

                            <div id="employee-docs">
                                <div class="row employee_docs">
                                    <div class="col-md-4 mb-3">
                                        <div class="form-group">
                                            <label class="mb-2" for="doc-title">
                                                {{ localize('doc_title') }}</label>
                                            <input type="text" class="form-control" id="doc-title"
                                                placeholder="{{ localize('doc_title') }}"
                                                name="employee_docs[1][document_title]">
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="mb-2" for="doc-file">
                                            {{ localize('file') }}</label>
                                        <input type="file" class="form-control" id="doc-file"
                                            name="employee_docs[1][file]">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="form-group">
                                            <label class="mb-2" for="expiry_date">{{ localize('expiry_date') }}</label>
                                            <input type="date" class="form-control" id="expiry_date"
                                                placeholder="{{ localize('expiry_date') }}"
                                                name="employee_docs[1][expiry_date]">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row text-start mb-3">
                                <div class="col-lg-3">
                                    <button id="add_doc_row" class="btn btn-soft-me btn-primary"><i
                                            class="fa fa-plus"></i>
                                        {{ localize('add_more') }}</button>
                                </div>
                            </div>

                            <div class="f1-buttons">
                                <button type="button" class="btn btn-previous">{{ localize('previous') }}</button>
                                <button type="button" class="btn btn-success btn-next">{{ localize('next') }}</button>
                            </div>
                        </fieldset>

                        <fieldset>
                            <h5 class="mb-3 fw-semi-bold">{{ $medicalInformationTitle }}:</h5>
                            @include('humanresource::employee.partials.medical-profile')
                            <div class="f1-buttons">
                                <button type="button" class="btn btn-previous">{{ localize('previous') }}</button>
                                <button type="button" class="btn btn-success btn-next">{{ localize('next') }}</button>
                            </div>
                        </fieldset>
                        <fieldset>
                            <div class="row">
                                <div class="col-md-6">
                                    <h5 class="my-2">{{ $supervisorTitle }}</h5>
                                    @radio(['input_name' => 'is_supervisor', 'data_set' => $isDisabledOptions, 'value' => 0])
                                </div>
                                <div class="col-md-6">
                                    <h5 class="my-2">{{ localize('user_setup') }}</h5>
                                    @input(['input_name' => 'email', 'type' => 'email', 'required' => false, 'disabled' => true, 'additional_id' => 'employee-email'])
                                    @input(['input_name' => 'password', 'type' => 'password', 'required' => true, 'additional_class' => 'required-field'])
                                </div>
                            </div>
                            <div class="f1-buttons">
                                <button type="button" class="btn btn-previous">{{ localize('previous') }}</button>
                                <button type="submit"
                                    class="btn btn-success btn-submit">{{ localize('submit') }}</button>
                            </div>
                        </fieldset>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('js')
    <script src="{{ asset('backend/assets/plugins/bootstrap-wizard/form.scripts.js') }}"></script>
    <script src="{{ module_asset('HumanResource/js/employee_form_wiz.js') }}"></script>
    <script src="{{ module_asset('HumanResource/js/employee-create.js') }}?v={{ @filemtime(public_path('module-assets/HumanResource/js/employee-create.js')) }}"></script>
    <script>
        window.employeeCascadeConfig = {
            orgUnitTree: @json($org_unit_tree ?? [])
        };
    </script>
    <script src="{{ module_asset('HumanResource/js/employee-cascade.js') }}"></script>
    <script>
        (function($) {
            "use strict";

            function toUiDate(value) {
                if (!value) {
                    return "";
                }

                var text = String(value).trim();
                var iso = text.match(/^(\d{4})-(\d{2})-(\d{2})$/);
                if (iso) {
                    return iso[3] + "/" + iso[2] + "/" + iso[1];
                }

                var mon = text.match(/^(\d{1,2})-([A-Za-z]{3})-(\d{4})$/);
                if (mon) {
                    var map = { jan: "01", feb: "02", mar: "03", apr: "04", may: "05", jun: "06", jul: "07", aug: "08", sep: "09", oct: "10", nov: "11", dec: "12" };
                    var m = map[(mon[2] || "").toLowerCase()];
                    if (m) {
                        return String(parseInt(mon[1], 10)).padStart(2, "0") + "/" + m + "/" + mon[3];
                    }
                }

                return text;
            }

            function toStorageDate(value) {
                if (!value || !String(value).trim()) {
                    return "";
                }

                var t = String(value).trim().replace(/[-.]/g, "/");
                var m = t.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
                if (!m) {
                    return null;
                }

                var dd = String(parseInt(m[1], 10)).padStart(2, "0");
                var mm = String(parseInt(m[2], 10)).padStart(2, "0");
                var yyyy = m[3];

                return yyyy + "-" + mm + "-" + dd;
            }

            function hardFixEmployeeCreateDateFields() {
                var $form = $("form.f1");
                if (!$form.length) {
                    return;
                }

                $form.find('input[type="date"], input.hard-ddmmyyyy-date').each(function() {
                    var $input = $(this);
                    if ($input.data("hardDmyInit")) {
                        return;
                    }

                    var currentValue = toUiDate($input.val());
                    $input.attr("type", "text");
                    $input.addClass("hard-ddmmyyyy-date");
                    $input.attr("placeholder", "DD/MM/YYYY");
                    $input.attr("autocomplete", "off");
                    $input.attr("inputmode", "numeric");

                    if (typeof $.fn.datetimepicker === "function") {
                        $input.datetimepicker({
                            timepicker: false,
                            format: "d/m/Y",
                            scrollInput: false,
                            closeOnDateSelect: true,
                            lang: "km"
                        });
                    } else if (typeof $.fn.datepicker === "function") {
                        $input.datepicker({
                            dateFormat: "dd/mm/yy",
                            changeMonth: true,
                            changeYear: true,
                            showAnim: "slideDown"
                        });
                    }

                    $input.val(currentValue);
                    $input.data("hardDmyInit", true);
                });
            }

            function bindEmployeeCreateDateSubmitGuard() {
                var $form = $("form.f1");
                if (!$form.length || $form.data("hardDmySubmitBind")) {
                    return;
                }

                $form.on("submit", function(e) {
                    var invalid = false;
                    $form.find("input.hard-ddmmyyyy-date").each(function() {
                        var $input = $(this);
                        var converted = toStorageDate($input.val());
                        if (converted === null) {
                            invalid = true;
                            $input.addClass("is-invalid");
                            return;
                        }

                        $input.removeClass("is-invalid");
                        $input.val(converted);
                    });

                    if (invalid) {
                        e.preventDefault();
                        alert("សូមបញ្ចូលកាលបរិច្ឆេទជា DD/MM/YYYY ឲ្យត្រឹមត្រូវ។");
                    }
                });

                $form.data("hardDmySubmitBind", true);
            }

            function hardFixCreatePageDateUi() {
                hardFixEmployeeCreateDateFields();
                bindEmployeeCreateDateSubmitGuard();
            }

            $(document).ready(hardFixCreatePageDateUi);
            $(window).on("load", hardFixCreatePageDateUi);
            $(document).ajaxComplete(hardFixCreatePageDateUi);
            $(document).on("focus", "form.f1 input.hard-ddmmyyyy-date, form.f1 input[type='date']", hardFixCreatePageDateUi);
        })(jQuery);
    </script>
@endpush


