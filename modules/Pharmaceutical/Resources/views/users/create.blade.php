@extends('backend.layouts.app')

@section('title', localize('add_pharm_user', 'Add Pharmaceutical User'))

@section('content')
    <div class="body-content">
        @include('pharmaceutical::_nav')

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fa fa-user-plus"></i>
                    {{ localize('add_pharm_user', 'Add Pharmaceutical User') }}
                </h5>
            </div>

            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('pharmaceutical.users.store') }}">
                    @csrf

                    <div class="row g-3">
                        {{-- User --}}
                        <div class="col-md-6">
                            <label class="form-label fw-bold">{{ localize('user', 'User') }} <span class="text-danger">*</span></label>
                            <select name="user_id" id="user_id" class="form-select" required>
                                @if (old('user_id'))
                                    <option value="{{ old('user_id') }}" selected>User #{{ old('user_id') }}</option>
                                @endif
                            </select>
                        </div>

                        {{-- Department --}}
                        <div class="col-md-6">
                            <label class="form-label fw-bold">{{ localize('department', 'Department') }} <span class="text-danger">*</span></label>
                            <select name="department_id" class="form-select" required>
                                <option value="">-- {{ localize('select_department', 'Select department') }} --</option>
                                @php
                                    $grouped = $departments->groupBy(fn($d) => match((int)$d->unit_type_id) {
                                        1 => 'PHD', 4 => 'OD', 6 => 'Hospital', 7 => 'HC', default => 'Other'
                                    });
                                @endphp
                                @foreach (['PHD', 'OD', 'Hospital', 'HC'] as $group)
                                    @if (isset($grouped[$group]))
                                        <optgroup label="{{ $group }}">
                                            @foreach ($grouped[$group] as $dept)
                                                <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>
                                                    {{ $dept->department_name }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        {{-- Role --}}
                        <div class="col-md-4">
                            <label class="form-label fw-bold">{{ localize('org_role', 'Role') }} <span class="text-danger">*</span></label>
                            <select name="org_role" class="form-select" required>
                                @foreach ($orgRoles as $role)
                                    @php
                                        $roleLabel = match($role) {
                                            'head' => localize('head', 'Head'),
                                            'deputy_head' => localize('deputy_head', 'Deputy Head'),
                                            'manager' => localize('manager', 'Manager'),
                                            default => $role,
                                        };
                                    @endphp
                                    <option value="{{ $role }}" {{ old('org_role') === $role ? 'selected' : '' }}>
                                        {{ $roleLabel }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Scope --}}
                        <div class="col-md-4">
                            <label class="form-label fw-bold">{{ localize('scope', 'Scope') }} <span class="text-danger">*</span></label>
                            <select name="scope_type" class="form-select" required>
                                @foreach ($scopeOptions as $scope)
                                    @php
                                        $scopeLabel = match($scope) {
                                            'self' => localize('scope_self', 'Self only'),
                                            'self_and_children' => localize('scope_self_children', 'Self + Children'),
                                            default => $scope,
                                        };
                                    @endphp
                                    <option value="{{ $scope }}" {{ old('scope_type', 'self_and_children') === $scope ? 'selected' : '' }}>
                                        {{ $scopeLabel }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Note --}}
                        <div class="col-md-4">
                            <label class="form-label fw-bold">{{ localize('note', 'Note') }}</label>
                            <input type="text" name="note" class="form-control" value="{{ old('note') }}"
                                   placeholder="{{ localize('optional_note', 'Optional note...') }}">
                        </div>
                    </div>

                    {{-- Info box --}}
                    <div class="alert alert-info mt-3">
                        <i class="fa fa-info-circle"></i>
                        <strong>{{ localize('level_explanation', 'Level Explanation') }}:</strong>
                        <ul class="mb-0 mt-1">
                            <li><span class="badge bg-primary">PHD</span> — {{ localize('phd_can_see_all', 'Can see all data') }}</li>
                            <li><span class="badge bg-info">OD</span> — {{ localize('od_can_see_own_children', 'Can see own + child HC data') }}</li>
                            <li><span class="badge bg-warning text-dark">Hospital</span> — {{ localize('hospital_can_see_own', 'Can see own data only') }}</li>
                            <li><span class="badge bg-success">HC</span> — {{ localize('hc_can_see_own', 'Can see own data only') }}</li>
                        </ul>
                    </div>

                    <div class="mt-3 d-flex gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="fa fa-save"></i> {{ localize('save', 'Save') }}
                        </button>
                        <a href="{{ route('pharmaceutical.users.index') }}" class="btn btn-secondary">
                            <i class="fa fa-arrow-left"></i> {{ localize('back', 'Back') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('js')
    <script>
        $(document).ready(function() {
            $('#user_id').select2({
                placeholder: '{{ localize("search_user", "Search user...") }}',
                allowClear: true,
                minimumInputLength: 1,
                ajax: {
                    url: '{{ route("pharmaceutical.users.search") }}',
                    dataType: 'json',
                    delay: 300,
                    data: function(params) {
                        return { q: params.term, limit: 15 };
                    },
                    processResults: function(data) {
                        return { results: data.results || [] };
                    }
                }
            });
        });
    </script>
@endpush
