@extends('backend.layouts.app')

@section('title', localize('create_letter', 'Create letter'))

@section('content')
    <div class="body-content">
        @include('correspondence::_nav')

        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    {{ $type === 'incoming' ? localize('add_incoming_letter', 'Add incoming letter') : localize('add_outgoing_letter', 'Add outgoing letter') }}
                </h6>
            </div>
            <div class="card-body">
                <form action="{{ route('correspondence.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="letter_type" value="{{ $type }}">

                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">{{ localize('registry_no', 'Registry no') }}</label>
                            <input type="text" name="registry_no" class="form-control" value="{{ old('registry_no') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ localize('letter_no', 'Letter no') }}</label>
                            <input type="text" name="letter_no" class="form-control" value="{{ old('letter_no') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ localize('letter_date', 'Letter date') }}</label>
                            <input type="date" name="letter_date" class="form-control" value="{{ old('letter_date') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ localize('received_date', 'Received date') }}</label>
                            <input type="date" name="received_date" class="form-control" value="{{ old('received_date') }}">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">{{ localize('subject', 'Subject') }} <span class="text-danger">*</span></label>
                            <input type="text" name="subject" class="form-control" value="{{ old('subject') }}" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">{{ localize('from_org', 'From organization') }}</label>
                            <input type="text" name="from_org" class="form-control" value="{{ old('from_org') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ localize('to_org', 'To organization') }}</label>
                            <input type="text" name="to_org" class="form-control" value="{{ old('to_org') }}">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">{{ localize('priority', 'Priority') }}</label>
                            <select class="form-select" name="priority">
                                <option value="normal" {{ old('priority') === 'normal' ? 'selected' : '' }}>{{ localize('normal', 'Normal') }}</option>
                                <option value="urgent" {{ old('priority') === 'urgent' ? 'selected' : '' }}>{{ localize('urgent', 'Urgent') }}</option>
                                <option value="confidential" {{ old('priority') === 'confidential' ? 'selected' : '' }}>{{ localize('confidential', 'Confidential') }}</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ localize('due_date', 'Due date') }}</label>
                            <input type="date" name="due_date" class="form-control" value="{{ old('due_date') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ localize('origin_org_unit', 'Origin org unit') }}</label>
                            <select class="form-select" name="origin_department_id">
                                <option value="">-- {{ localize('select', 'Select') }} --</option>
                                @foreach (($orgUnitOptions ?? collect()) as $unit)
                                    <option value="{{ $unit->id }}" {{ (int) old('origin_department_id') === (int) $unit->id ? 'selected' : '' }}>
                                        {{ $unit->path ?? $unit->label ?? ('#' . $unit->id) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">{{ localize('summary', 'Summary') }}</label>
                            <textarea name="summary" class="form-control" rows="4">{{ old('summary') }}</textarea>
                        </div>
                    </div>

                    <div class="text-end mt-3 d-flex justify-content-end gap-2">
                        <a href="{{ $type === 'incoming' ? route('correspondence.incoming') : route('correspondence.outgoing') }}" class="btn btn-secondary">
                            {{ localize('cancel', 'Cancel') }}
                        </a>
                        <button type="submit" class="btn btn-success">{{ localize('save', 'Save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
