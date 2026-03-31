<!-- Modal -->
<div class="modal fade" id="addNotice" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-labelledby="staticBackdropLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="staticBackdropLabel">
                    {{ localize('new_notice') }}
                </h5>
            </div>
            <form id="leadForm" action="{{ route('notice.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mt-3">
                            <div class="row">
                                <label for="notice_type"
                                    class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('notice_type') }}<span
                                        class="text-danger">*</span></label>
                                <div class="col-sm-9 col-md-12 col-xl-9">
                                    <input type="text" class="form-control" id="notice_type" name="notice_type"
                                        placeholder="{{ localize('notice_type') }}" value="{{ old('notice_type') }}"
                                        required>
                                </div>

                                @if ($errors->has('notice_type'))
                                    <div class="error text-danger m-2">{{ $errors->first('notice_type') }}</div>
                                @endif
                            </div>
                        </div>

                        <div class="col-md-6 mt-3">
                            <div class="row">
                                <label for="notice_descriptiion"
                                    class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('notice_descriptiion') }}<span
                                        class="text-danger">*</span></label>
                                <div class="col-sm-9 col-md-12 col-xl-9">
                                    <textarea class="form-control" id="notice_descriptiion" name="notice_descriptiion" rows="3"
                                        placeholder="{{ localize('notice_descriptiion') }}"
                                        required>{{ old('notice_descriptiion') }}</textarea>
                                    </div>

                                @if ($errors->has('notice_descriptiion'))
                                    <div class="error text-danger m-2">{{ $errors->first('notice_descriptiion') }}</div>
                                @endif
                            </div>
                        </div>

                        <div class="col-md-6 mt-3">
                            <div class="row">
                                <label for="notice_date"
                                    class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('notice_date') }}<span
                                        class="text-danger">*</span></label>
                                <div class="col-sm-9 col-md-12 col-xl-9">
                                    <input type="text" class="form-control date_picker notice-date-picker" id="notice_date" name="notice_date"
                                        placeholder="DD/MM/YYYY" value="{{ old('notice_date') }}"
                                        required>
                                </div>

                                @if ($errors->has('notice_date'))
                                    <div class="error text-danger m-2">{{ $errors->first('notice_date') }}</div>
                                @endif
                            </div>
                        </div>

                        <div class="col-md-6 mt-3">
                            <div class="row">
                                <label for="notice_attachment"
                                    class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('notice_attachment') }}</label>
                                <div class="col-sm-9 col-md-12 col-xl-9">
                                    <input type="file" class="form-control" id="notice_attachment" name="notice_attachment"
                                        placeholder="{{ localize('notice_attachment') }}"
                                        value="{{ old('notice_attachment') }}">
                                </div>

                                @if ($errors->has('notice_attachment'))
                                    <div class="error text-danger m-2">{{ $errors->first('notice_attachment') }}</div>
                                @endif
                            </div>
                        </div>

                        <div class="col-md-6 mt-3">
                            <div class="row">
                                <label for="notice_by"
                                    class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('notice_by') }}<span
                                        class="text-danger">*</span></label>
                                <div class="col-sm-9 col-md-12 col-xl-9">
                                    <input type="text" class="form-control" id="notice_by" name="notice_by"
                                        placeholder="{{ localize('notice_by') }}"
                                        value="{{ old('notice_by', auth()->user()->full_name ?? '') }}" required>
                                </div>

                                @if ($errors->has('notice_by'))
                                    <div class="error text-danger m-2">{{ $errors->first('notice_by') }}</div>
                                @endif
                            </div>
                        </div>

                        <div class="col-md-6 mt-3">
                            <div class="row">
                                <label for="audience_type"
                                    class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('audience', 'Audience') }}<span
                                        class="text-danger">*</span></label>
                                <div class="col-sm-9 col-md-12 col-xl-9">
                                    <select class="form-control notice-audience-type" id="audience_type" name="audience_type" required data-target-prefix="create_notice_target">
                                        <option value="all" {{ old('audience_type', 'all') === 'all' ? 'selected' : '' }}>{{ localize('all_users', 'All users') }}</option>
                                        <option value="users" {{ old('audience_type') === 'users' ? 'selected' : '' }}>{{ localize('selected_users', 'Selected users') }}</option>
                                        <option value="roles" {{ old('audience_type') === 'roles' ? 'selected' : '' }}>{{ localize('selected_roles', 'Selected roles') }}</option>
                                        <option value="departments" {{ old('audience_type') === 'departments' ? 'selected' : '' }}>{{ localize('selected_units', 'Selected units') }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mt-3">
                            <div class="row">
                                <label for="scheduled_at"
                                    class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('scheduled_at', 'Scheduled at') }}</label>
                                <div class="col-sm-9 col-md-12 col-xl-9">
                                    <input type="datetime-local" class="form-control" id="scheduled_at" name="scheduled_at"
                                        value="{{ old('scheduled_at') }}">
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12 mt-3">
                            <div class="row">
                                <label class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('delivery_channels', 'Delivery channels') }}</label>
                                <div class="col-sm-9 col-md-12 col-xl-9">
                                    @php
                                        $channels = old('delivery_channels', ['in_app']);
                                    @endphp
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" id="create_channel_in_app" name="delivery_channels[]" value="in_app"
                                            {{ in_array('in_app', (array) $channels, true) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="create_channel_in_app">{{ localize('in_app_notification', 'In-app notification') }}</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" id="create_channel_telegram" name="delivery_channels[]" value="telegram"
                                            {{ in_array('telegram', (array) $channels, true) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="create_channel_telegram">{{ localize('telegram', 'Telegram') }}</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12 mt-3 notice-audience-target-group" id="create_notice_target_users">
                            <div class="row">
                                <label class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('users', 'Users') }}</label>
                                <div class="col-sm-9 col-md-12 col-xl-9">
                                    <select class="form-control" name="audience_users[]" multiple size="6">
                                        @foreach ($users as $user)
                                            <option value="{{ $user->id }}" {{ in_array($user->id, (array) old('audience_users', [])) ? 'selected' : '' }}>
                                                {{ $user->full_name }} ({{ $user->email }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12 mt-3 notice-audience-target-group" id="create_notice_target_roles">
                            <div class="row">
                                <label class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('roles', 'Roles') }}</label>
                                <div class="col-sm-9 col-md-12 col-xl-9">
                                    <select class="form-control" name="audience_roles[]" multiple size="6">
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->id }}" {{ in_array($role->id, (array) old('audience_roles', [])) ? 'selected' : '' }}>
                                                {{ $role->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12 mt-3 notice-audience-target-group" id="create_notice_target_departments">
                            <div class="row">
                                <label class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('departments', 'Departments') }}</label>
                                <div class="col-sm-9 col-md-12 col-xl-9">
                                    <select class="form-control" name="audience_departments[]" multiple size="6">
                                        @foreach ($departments as $department)
                                            <option value="{{ $department->id }}" {{ in_array($department->id, (array) old('audience_departments', [])) ? 'selected' : '' }}>
                                                {{ $department->label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="workflow_action" value="draft" class="workflow-action-input">
                    <button type="button" class="btn btn-danger"
                        data-bs-dismiss="modal">{{ localize('close') }}</button>
                    <button type="submit" class="btn btn-secondary submit_button set-workflow-action" data-workflow-action="draft">{{ localize('save_draft', 'Save draft') }}</button>
                    <button type="submit" class="btn btn-primary submit_button set-workflow-action" data-workflow-action="submit">{{ localize('submit_for_approval', 'Submit for approval') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
