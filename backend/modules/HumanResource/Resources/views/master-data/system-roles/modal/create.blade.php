<div class="modal fade" id="create-system-role" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('system-roles.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ localize('add_responsibility', 'Add Responsibility') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">{{ localize('code', 'Code') }} <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control" required
                            pattern="[a-z_]+" title="Lowercase letters and underscores only"
                            placeholder="e.g. pharmacist" value="{{ old('code') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ localize('name_en', 'Name (EN)') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required value="{{ old('name') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ localize('name_km', 'Name (KM)') }}</label>
                        <input type="text" name="name_km" class="form-control" value="{{ old('name_km') }}">
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">{{ localize('level', 'Level') }} <span class="text-danger">*</span></label>
                            <input type="number" name="level" class="form-control" required min="0" max="255"
                                value="{{ old('level', 5) }}">
                            <small class="text-muted">1 = highest authority</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">{{ localize('can_approve', 'Can Approve') }}</label>
                            <select name="can_approve" class="form-select">
                                <option value="0" {{ old('can_approve', '0') == '0' ? 'selected' : '' }}>No</option>
                                <option value="1" {{ old('can_approve') == '1' ? 'selected' : '' }}>Yes</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">{{ localize('sort_order', 'Sort Order') }}</label>
                            <input type="number" name="sort_order" class="form-control" min="0" max="255"
                                value="{{ old('sort_order', 0) }}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ localize('status', 'Status') }}</label>
                        <select name="is_active" class="form-select">
                            <option value="1" {{ old('is_active', '1') == '1' ? 'selected' : '' }}>{{ localize('active', 'Active') }}</option>
                            <option value="0" {{ old('is_active') == '0' ? 'selected' : '' }}>{{ localize('inactive', 'Inactive') }}</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ localize('close', 'Close') }}</button>
                    <button type="submit" class="btn btn-success">{{ localize('save', 'Save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
