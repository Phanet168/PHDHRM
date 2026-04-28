@extends('backend.layouts.app')

@section('title', localize('settings', 'ការកំណត់'))

@section('content')
    <div class="body-content">
        @include('correspondence::_nav')

        <div class="card corr-card mb-3">
            <div class="card-header">
                <h6 class="mb-0">{{ localize('correspondence_manager_setting', 'កំណត់អ្នកគ្រប់គ្រងលិខិត') }}</h6>
            </div>
            <div class="card-body">
                <p class="mb-0 text-muted">
                    {{ localize('correspondence_manager_setting_note', 'គោលបំណងផ្ទាំងនេះគឺកំណត់បុគ្គលដែលជាអ្នកគ្រប់គ្រងលិខិតប៉ុណ្ណោះ។') }}
                </p>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">
                <div class="fw-semibold mb-1">{{ localize('please_check_input', 'សូមពិនិត្យទិន្នន័យបញ្ចូលម្ដងទៀត') }}</div>
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card corr-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0">{{ localize('manager_list_and_assign', 'បញ្ជី និងកំណត់អ្នកគ្រប់គ្រងលិខិត') }}</h6>
                @if ($managerTemplateId > 0)
                    <span class="badge bg-light text-dark border">{{ $managerTemplateName }}</span>
                @endif
            </div>
            <div class="card-body">
                @if ($managerTemplateId <= 0)
                    <div class="alert alert-warning mb-0">
                        {{ localize('settings_not_ready', 'មិនទាន់មាន Template សម្រាប់កំណត់។ សូមរត់ migration/seed មុន។') }}
                    </div>
                @else
                    <form method="POST" action="{{ route('correspondence.settings.assign') }}" class="row g-3 mb-3">
                        @csrf

                        <div class="col-12">
                            <label class="form-label mb-1">{{ localize('select_manager_users', 'ជ្រើសអ្នកគ្រប់គ្រងលិខិត (អាចច្រើននាក់)') }}</label>
                            <input type="text" id="corr_settings_user_search" class="form-control form-control-sm mb-2"
                                placeholder="{{ localize('search_user_placeholder', 'ស្វែងរកឈ្មោះ ឬ email...') }}">

                            <div id="corr_settings_user_picker" class="border rounded p-2" style="max-height: 260px; overflow: auto;">
                                @php
                                    $oldUserIds = collect(old('user_ids', []))->map(fn ($id) => (int) $id)->all();
                                @endphp
                                @forelse (($userOptions ?? collect()) as $user)
                                    @php
                                        $userLabel = trim(($user->full_name ?? '') . (!empty($user->email) ? ' (' . $user->email . ')' : ''));
                                    @endphp
                                    <div class="form-check user-picker-item" data-keyword="{{ mb_strtolower($userLabel) }}">
                                        <input class="form-check-input" type="checkbox" name="user_ids[]" value="{{ (int) $user->id }}"
                                            id="user_pick_{{ (int) $user->id }}" @checked(in_array((int) $user->id, $oldUserIds, true))>
                                        <label class="form-check-label" for="user_pick_{{ (int) $user->id }}">{{ $userLabel }}</label>
                                    </div>
                                @empty
                                    <div class="text-muted small">{{ localize('no_data_found', 'មិនមានទិន្នន័យ') }}</div>
                                @endforelse
                            </div>

                            @error('user_ids')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                            @error('user_ids.*')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-8">
                            <label class="form-label mb-1">{{ localize('department_optional', 'អង្គភាព (ជម្រើស)') }}</label>
                            <select name="department_id" class="form-select @error('department_id') is-invalid @enderror">
                                <option value="">{{ localize('auto_from_user', 'ស្វ័យប្រវត្តិពីអ្នកប្រើ') }}</option>
                                @foreach (($departmentOptions ?? collect()) as $department)
                                    <option value="{{ (int) $department->id }}" @selected((int) old('department_id') === (int) $department->id)>
                                        {{ $department->department_name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('department_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" value="1" id="is_primary" name="is_primary" @checked((bool) old('is_primary'))>
                                <label class="form-check-label" for="is_primary">{{ localize('set_primary', 'កំណត់អ្នកជាមេ (Primary)') }}</label>
                            </div>
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-success">
                                <i class="fa fa-save me-1"></i>{{ localize('save', 'រក្សាទុក') }}
                            </button>
                        </div>
                    </form>

                    <hr>

                    <div class="fw-semibold mb-2">{{ localize('current_correspondence_managers', 'អ្នកគ្រប់គ្រងលិខិតបច្ចុប្បន្ន') }}</div>
                    @if ($managerAssignments->isEmpty())
                        <div class="text-muted">{{ localize('no_data_found', 'មិនមានទិន្នន័យ') }}</div>
                    @else
                        <div class="d-flex flex-column gap-2">
                            @foreach ($managerAssignments as $assignment)
                                <div class="border rounded p-2">
                                    <div class="fw-semibold">
                                        {{ $assignment['user_name'] ?? '-' }}
                                        @if (!empty($assignment['is_primary']))
                                            <span class="badge bg-primary ms-1">Primary</span>
                                        @endif
                                    </div>
                                    @if (!empty($assignment['user_email']))
                                        <div class="small text-muted">{{ $assignment['user_email'] }}</div>
                                    @endif
                                    <div class="small text-muted">{{ localize('department', 'អង្គភាព') }}: {{ $assignment['department_name'] ?? '-' }}</div>

                                    <form method="POST"
                                        action="{{ route('correspondence.settings.assignment.delete', (int) ($assignment['id'] ?? 0)) }}"
                                        onsubmit="return confirm('{{ localize('are_you_sure', 'តើអ្នកប្រាកដទេ?') }}');"
                                        class="mt-2">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                            <i class="fa fa-trash me-1"></i>{{ localize('delete', 'លុប') }}
                                        </button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
@endsection

@push('js')
    <script>
        (function() {
            var searchInput = document.getElementById('corr_settings_user_search');
            var picker = document.getElementById('corr_settings_user_picker');
            if (!searchInput || !picker) {
                return;
            }

            var items = Array.prototype.slice.call(picker.querySelectorAll('.user-picker-item'));
            searchInput.addEventListener('input', function() {
                var keyword = String(searchInput.value || '').toLowerCase().trim();
                items.forEach(function(item) {
                    var haystack = String(item.getAttribute('data-keyword') || '');
                    item.style.display = haystack.indexOf(keyword) !== -1 ? '' : 'none';
                });
            });
        })();
    </script>
@endpush
