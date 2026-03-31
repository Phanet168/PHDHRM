<div class="modal fade" id="create-user-org-role" data-bs-backdrop="static" data-bs-keyboard="false"
    tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ localize('add_org_role_assignment', 'បន្ថែមការកំណត់តួនាទីតាមអង្គភាព') }}</h5>
            </div>
            <form action="{{ route('user-org-roles.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-light border mb-3">
                        <div class="fw-semibold mb-1">{{ localize('org_role_assign_help_title', 'អត្ថន័យការកំណត់នេះ') }}</div>
                        <div>{{ localize('org_role_assign_help_1', 'Role = តួនាទីអ្នកចុះហត្ថលេខា/អនុម័ត') }}</div>
                        <div>{{ localize('org_role_assign_help_2', 'Scope: តែអង្គភាពខ្លួនឯង ឬរួមទាំងអង្គភាពរង') }}</div>
                    </div>

                    <div class="row">
                        <div class="form-group mb-2 mx-0 row">
                            <label for="user_id" class="col-lg-3 col-form-label ps-0">{{ localize('user', 'អ្នកប្រើប្រាស់') }} <span
                                    class="text-danger">*</span></label>
                            <div class="col-lg-9">
                                <select name="user_id" id="user_id"
                                    class="form-control org-role-user-ajax"
                                    data-placeholder="{{ localize('select_user', 'ជ្រើសអ្នកប្រើប្រាស់') }}" required>
                                    <option value="">{{ localize('select_user', 'ជ្រើសអ្នកប្រើប្រាស់') }}</option>
                                    @if ((int) ($old_user_id ?? 0) > 0 && filled($old_user_text ?? ''))
                                        <option value="{{ $old_user_id }}" selected>{{ $old_user_text }}</option>
                                    @elseif ((int) $selected_user_id > 0 && filled($selected_user_text))
                                        <option value="{{ $selected_user_id }}" selected>{{ $selected_user_text }}</option>
                                    @endif
                                </select>
                                <small class="text-muted">
                                    {{ localize('org_role_user_prefill_hint', 'បើបានជ្រើសអ្នកប្រើប្រាស់ក្នុងតម្រងខាងលើរួច ប្រព័ន្ធនឹងបំពេញជាមុនដោយស្វ័យប្រវត្តិ។') }}
                                </small>
                            </div>
                        </div>

                        <div class="form-group mb-2 mx-0 row">
                            <label for="department_id" class="col-lg-3 col-form-label ps-0">{{ localize('org_unit', 'អង្គភាព') }}
                                <span class="text-danger">*</span></label>
                            <div class="col-lg-9">
                                <select name="department_id" id="department_id" class="form-control select-basic-single" required>
                                    <option value="">{{ localize('select_org_unit', 'ជ្រើសអង្គភាព') }}</option>
                                    @foreach ($departments as $department)
                                        <option value="{{ $department->id }}" {{ (int) old('department_id') === (int) $department->id ? 'selected' : '' }}>
                                            {{ $department->label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="form-group mb-2 mx-0 row">
                            <label for="org_role" class="col-lg-3 col-form-label ps-0">{{ localize('role', 'តួនាទី') }} <span
                                    class="text-danger">*</span></label>
                            <div class="col-lg-9">
                                <select name="org_role" id="org_role" class="form-control select-basic-single" required>
                                    @foreach ($org_role_options as $option)
                                        <option value="{{ $option }}" {{ old('org_role') === $option ? 'selected' : '' }}>
                                            {{ localize('org_role_' . $option, match ($option) {
                                                'head' => 'ប្រធានអង្គភាព',
                                                'deputy_head' => 'អនុប្រធានអង្គភាព',
                                                'manager' => 'អ្នកគ្រប់គ្រង/ប្រធានការិយាល័យ',
                                                default => ucwords(str_replace('_', ' ', $option)),
                                            }) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="form-group mb-2 mx-0 row">
                            <label for="scope_type" class="col-lg-3 col-form-label ps-0">{{ localize('scope', 'វិសាលភាព') }} <span
                                    class="text-danger">*</span></label>
                            <div class="col-lg-9">
                                <select name="scope_type" id="scope_type" class="form-control select-basic-single" required>
                                    @foreach ($scope_options as $option)
                                        <option value="{{ $option }}" {{ old('scope_type', 'self_and_children') === $option ? 'selected' : '' }}>
                                            {{ localize('org_scope_' . $option, match ($option) {
                                                'self' => 'តែអង្គភាពខ្លួនឯង',
                                                'self_and_children' => 'អង្គភាពខ្លួនឯង និងអង្គភាពរង',
                                                default => ucwords(str_replace('_', ' ', $option)),
                                            }) }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">
                                    {{ localize('scope_hint_text', 'បើជ្រើស "អង្គភាពខ្លួនឯង និងអង្គភាពរង" អ្នកប្រើនឹងអាចដំណើរការលើឯកសាររបស់អង្គភាពរងបានផង។') }}
                                </small>
                            </div>
                        </div>

                        <div class="form-group mb-2 mx-0 row">
                            <label for="effective_from" class="col-lg-3 col-form-label ps-0">{{ localize('effective_from', 'មានសុពលភាពចាប់ពី') }}</label>
                            <div class="col-lg-9">
                                <input type="date" name="effective_from" id="effective_from" class="form-control"
                                    value="{{ old('effective_from') }}">
                            </div>
                        </div>

                        <div class="form-group mb-2 mx-0 row">
                            <label for="effective_to" class="col-lg-3 col-form-label ps-0">{{ localize('effective_to', 'មានសុពលភាពដល់') }}</label>
                            <div class="col-lg-9">
                                <input type="date" name="effective_to" id="effective_to" class="form-control"
                                    value="{{ old('effective_to') }}">
                            </div>
                        </div>

                        @radio(['input_name' => 'is_active', 'data_set' => [1 => localize('active', 'សកម្ម'), 0 => localize('inactive', 'អសកម្ម')], 'value' => old('is_active', 1)])

                        <div class="form-group mb-2 mx-0 row">
                            <label for="note" class="col-lg-3 col-form-label ps-0">{{ localize('note', 'កំណត់សម្គាល់') }}</label>
                            <div class="col-lg-9">
                                <textarea name="note" id="note" rows="2" class="form-control">{{ old('note') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">{{ localize('close', 'បិទ') }}</button>
                    <button class="btn btn-primary">{{ localize('save', 'រក្សាទុក') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
