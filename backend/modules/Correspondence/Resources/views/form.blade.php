@extends('backend.layouts.app')

@section('title', localize('create_letter', 'បង្កើតលិខិត'))

@section('content')
    <div class="body-content">
        @include('correspondence::_nav')

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <div class="fw-semibold mb-1">{{ localize('please_check_form_errors', 'សូមពិនិត្យព័ត៌មានបញ្ចូលម្តងទៀត') }}</div>
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $errorMessage)
                        <li>{{ $errorMessage }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0">
                        {{ $type === 'incoming' ? localize('add_incoming_letter', 'បន្ថែមលិខិតចូល') : localize('add_outgoing_letter', 'បន្ថែមលិខិតចេញ') }}
                    </h6>
                    <a href="{{ route('correspondence.help', ['article' => $type === 'incoming' ? 'incoming' : 'outgoing']) }}" class="btn btn-sm btn-outline-info">
                        <i class="fa fa-life-ring me-1"></i>{{ localize('help', 'ជំនួយ') }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form action="{{ route('correspondence.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="letter_type" value="{{ $type }}">

                    @if ($type === 'outgoing')
                        <div class="alert alert-light border">
                            <div class="fw-semibold mb-1">{{ localize('outgoing_form_hint_title', 'ការបង្កើតលិខិតចេញ') }}</div>
                            <div class="small text-muted">{{ localize('outgoing_form_hint_body', 'សូមបំពេញប្រធានបទ អ្នកទទួល To/CC និងឯកសារភ្ជាប់ប្រសិនបើមាន។ បើមិនទាន់ផ្ញើ អាចរក្សាទុកជា Draft សិនបាន។') }}</div>
                        </div>
                    @endif

                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">{{ localize('registry_no', 'លេខចុះបញ្ជី') }}</label>
                            <input type="text" name="registry_no" class="form-control" value="{{ old('registry_no') }}" readonly
                                placeholder="{{ localize('auto_generated_on_save', 'បង្កើតស្វ័យប្រវត្តពេលរក្សាទុក') }} (0001/{{ now()->format('y') }})">
                            <small class="text-muted">{{ localize('auto_generated_on_save', 'បង្កើតស្វ័យប្រវត្តពេលរក្សាទុក') }}</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ localize('letter_no', 'លេខលិខិត') }}</label>
                            <input type="text" name="letter_no" class="form-control" value="{{ old('letter_no') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ localize('letter_date', 'ថ្ងៃលិខិត') }}</label>
                            <input type="date" name="letter_date" class="form-control" value="{{ old('letter_date') }}">
                        </div>
                        @if ($type === 'incoming')
                            <div class="col-md-3">
                                <label class="form-label">{{ localize('received_date', 'ថ្ងៃទទួលលិខិត') }}</label>
                                <input type="date" name="received_date" class="form-control" value="{{ old('received_date') }}">
                            </div>
                        @else
                            <div class="col-md-3">
                                <label class="form-label">{{ localize('sent_date', 'ថ្ងៃផ្ញើចេញ') }}</label>
                                <input type="date" name="sent_date" class="form-control" value="{{ old('sent_date') }}">
                            </div>
                        @endif

                        <div class="col-md-12">
                            <label class="form-label">{{ localize('subject', 'ប្រធានបទ') }} <span class="text-danger">*</span></label>
                            <input type="text" name="subject" class="form-control" value="{{ old('subject') }}" required>
                        </div>

                        @if ($type === 'incoming')
                            <div class="col-md-6">
                                <label class="form-label">{{ localize('from_org', 'អង្គភាពចេញលិខិត') }}</label>
                                <input type="text" name="from_org" class="form-control" value="{{ old('from_org') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ localize('to_org', 'អង្គភាពទទួល') }}</label>
                                <input type="text" name="to_org" class="form-control" value="{{ old('to_org') }}">
                            </div>
                        @endif

                        @if ($type !== 'incoming')
                            <div class="col-12">
                                <div class="border rounded p-3 bg-light">
                                    <div class="fw-semibold mb-2">{{ localize('recipient_setup', 'កំណត់អ្នកទទួល') }}</div>
                                    <div class="small text-muted">{{ localize('recipient_setup_hint', 'សូមជ្រើសអ្នកទទួលសំខាន់ (To) និងអ្នកទទួលចម្លង (CC) តាមអង្គភាព ឬបុគ្គល។') }}</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ localize('to_org_units', 'ទៅអង្គភាព') }}</label>
                                <select class="form-select js-org-multi" name="to_department_ids[]" multiple data-placeholder="{{ localize('select_org_unit', 'ជ្រើសរើសអង្គភាព') }}">
                                    @foreach (($orgUnitOptions ?? collect()) as $unit)
                                        @php
                                            $displayName = $unit->path ?? '';
                                            if ($displayName !== '') {
                                                $parts = explode(' > ', $displayName);
                                                $displayName = trim(end($parts));
                                            } else {
                                                $displayName = $unit->label ?? ('#' . $unit->id);
                                            }
                                        @endphp
                                        <option value="{{ $unit->id }}" {{ collect(old('to_department_ids', []))->contains($unit->id) ? 'selected' : '' }}>
                                            {{ $displayName }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">{{ localize('to_org_unit_hint', 'សូមជ្រើសរើសអង្គភាពទទួលសំខាន់ (To)') }}</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ localize('cc_org_units', 'ជូនចម្លងអង្គភាព') }}</label>
                                <select class="form-select js-org-multi" name="cc_department_ids[]" multiple data-placeholder="{{ localize('select_org_unit', 'ជ្រើសរើសអង្គភាព') }}">
                                    @foreach (($orgUnitOptions ?? collect()) as $unit)
                                        @php
                                            $displayName = $unit->path ?? '';
                                            if ($displayName !== '') {
                                                $parts = explode(' > ', $displayName);
                                                $displayName = trim(end($parts));
                                            } else {
                                                $displayName = $unit->label ?? ('#' . $unit->id);
                                            }
                                        @endphp
                                        <option value="{{ $unit->id }}" {{ collect(old('cc_department_ids', []))->contains($unit->id) ? 'selected' : '' }}>
                                            {{ $displayName }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">{{ localize('cc_org_unit_hint', 'អង្គភាពជូនចម្លង (CC) សម្រាប់ជូនដំណឹងប៉ុណ្ណោះ') }}</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ localize('to_users', 'ទៅបុគ្គល') }}</label>
                                <select class="form-select js-user-multi" name="to_user_ids[]" multiple data-placeholder="{{ localize('select_user', 'ជ្រើសរើសអ្នកប្រើ') }}">
                                    @foreach (($selectedUsers ?? collect()) as $selectedUser)
                                        @if (collect(old('to_user_ids', []))->contains($selectedUser['id']))
                                            <option value="{{ $selectedUser['id'] }}" selected>{{ $selectedUser['text'] }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                <small class="text-muted">{{ localize('to_required_for_send', 'សូមជ្រើសរើសអ្នកទទួលសំខាន់ (To)') }}</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ localize('cc_users', 'ជូនចម្លងបុគ្គល') }}</label>
                                <select class="form-select js-user-multi" name="cc_user_ids[]" multiple data-placeholder="{{ localize('select_user', 'ជ្រើសរើសអ្នកប្រើ') }}">
                                    @foreach (($selectedUsers ?? collect()) as $selectedUser)
                                        @if (collect(old('cc_user_ids', []))->contains($selectedUser['id']))
                                            <option value="{{ $selectedUser['id'] }}" selected>{{ $selectedUser['text'] }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                <small class="text-muted">
                                    {{ localize('cc_info_only', 'អ្នកជូនចម្លង (CC) សម្រាប់ជូនដំណឹងប៉ុណ្ណោះ') }}
                                    {{ localize('notification_link_hint', 'អ្នកទទួលនឹងទទួលការជូនដំណឹង ហើយអាចបើកមើលលិខិត (រួមទាំងឯកសារភ្ជាប់)') }}
                                </small>
                            </div>
                        @endif

                        <div class="col-md-4">
                            <label class="form-label">{{ localize('priority', 'អាទិភាព') }}</label>
                            <select class="form-select" name="priority">
                                <option value="normal" {{ old('priority') === 'normal' ? 'selected' : '' }}>{{ localize('normal', 'ធម្មតា') }}</option>
                                <option value="urgent" {{ old('priority') === 'urgent' ? 'selected' : '' }}>{{ localize('urgent', 'បន្ទាន់') }}</option>
                                <option value="confidential" {{ old('priority') === 'confidential' ? 'selected' : '' }}>{{ localize('confidential', 'សម្ងាត់') }}</option>
                            </select>
                        </div>
                        @if ($type === 'incoming')
                            <div class="col-md-4">
                                <label class="form-label">{{ localize('due_date', 'ថ្ងៃកំណត់') }}</label>
                                <input type="date" name="due_date" class="form-control" value="{{ old('due_date') }}">
                            </div>
                        @endif
                        @if ($type === 'incoming')
                            <div class="col-md-4">
                                <label class="form-label">{{ localize('origin_org_unit', 'អង្គភាពដើម') }}</label>
                                <select class="form-select" name="origin_department_id">
                                    <option value="">-- {{ localize('select', 'ជ្រើសរើស') }} --</option>
                                    @foreach (($orgUnitOptions ?? collect()) as $unit)
                                        @php
                                            $displayName = $unit->path ?? '';
                                            if ($displayName !== '') {
                                                $parts = explode(' > ', $displayName);
                                                $displayName = trim(end($parts));
                                            } else {
                                                $displayName = $unit->label ?? ('#' . $unit->id);
                                            }
                                        @endphp
                                        <option value="{{ $unit->id }}" {{ (int) old('origin_department_id') === (int) $unit->id ? 'selected' : '' }}>
                                            {{ $displayName }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="col-md-12">
                            <label class="form-label">{{ localize('summary', 'ខ្លឹមសារសង្ខេប') }}</label>
                            <textarea name="summary" class="form-control" rows="4">{{ old('summary') }}</textarea>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">{{ localize('attachments', 'ឯកសារភ្ជាប់') }}</label>
                            <input type="file" name="attachments[]" class="form-control" multiple>
                            <small class="text-muted">{{ localize('allowed_file_types', 'អនុញ្ញាត: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG') }}</small>
                        </div>
                    </div>

                    <div class="text-end mt-3 d-flex justify-content-end gap-2">
                        <a href="{{ $type === 'incoming' ? route('correspondence.incoming') : route('correspondence.outgoing') }}" class="btn btn-secondary">
                            {{ localize('cancel', 'បោះបង់') }}
                        </a>
                        @if ($type !== 'incoming')
                            <button type="submit" name="send_action" value="draft" class="btn btn-outline-primary">
                                {{ localize('save_as_draft', 'រក្សាទុកជាសេចក្តីព្រាង') }}
                            </button>
                            <button type="submit" name="send_action" value="send" class="btn btn-success">
                                {{ localize('save_and_send', 'រក្សាទុក និងផ្ញើ') }}
                            </button>
                        @else
                            <button type="submit" class="btn btn-success">{{ localize('save', 'រក្សាទុក') }}</button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('js')
    <script>
        (function($) {
            "use strict";

            if (!$ || !$.fn || !$.fn.select2) {
                return;
            }

            $('.js-user-multi').select2({
                width: '100%',
                allowClear: true,
                closeOnSelect: false,
                placeholder: function() {
                    return $(this).data('placeholder') || 'ជ្រើសរើសអ្នកប្រើ';
                },
                ajax: {
                    url: @json(route('correspondence.users.search')),
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            q: params.term || ''
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: (data && data.results) ? data.results : []
                        };
                    }
                }
            });

            $('.js-org-multi').select2({
                width: '100%',
                allowClear: true,
                closeOnSelect: false,
                placeholder: function() {
                    return $(this).data('placeholder') || 'ជ្រើសរើសអង្គភាព';
                }
            });
        })(window.jQuery);
    </script>
@endpush


