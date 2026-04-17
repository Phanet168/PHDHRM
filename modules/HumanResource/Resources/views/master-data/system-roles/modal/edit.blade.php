<div class="modal fade" id="edit-system-role-{{ $role->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('system-roles.update', $role->uuid) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">{{ localize('edit_system_role', 'Edit System Role') }}: {{ $role->code }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">{{ localize('code', 'Code') }} <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control" required
                            pattern="[a-z_]+" title="Lowercase letters and underscores only"
                            value="{{ old('code', $role->code) }}"
                            {{ $role->is_system ? 'readonly' : '' }}>
                        @if ($role->is_system)
                            <small class="text-warning">System role code cannot be changed.</small>
                        @endif
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ localize('name_en', 'Name (EN)') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required
                            value="{{ old('name', $role->name) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ localize('name_km', 'Name (KM)') }}</label>
                        <input type="text" name="name_km" class="form-control"
                            value="{{ old('name_km', $role->name_km) }}">
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">{{ localize('level', 'Level') }} <span class="text-danger">*</span></label>
                            <input type="number" name="level" class="form-control" required min="0" max="255"
                                value="{{ old('level', $role->level) }}">
                            <small class="text-muted">1 = highest</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">{{ localize('can_approve', 'Can Approve') }}</label>
                            <select name="can_approve" class="form-select">
                                <option value="0" {{ old('can_approve', $role->can_approve) == false ? 'selected' : '' }}>No</option>
                                <option value="1" {{ old('can_approve', $role->can_approve) == true ? 'selected' : '' }}>Yes</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">{{ localize('sort_order', 'Sort Order') }}</label>
                            <input type="number" name="sort_order" class="form-control" min="0" max="255"
                                value="{{ old('sort_order', $role->sort_order) }}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ localize('status', 'Status') }}</label>
                        <select name="is_active" class="form-select">
                            <option value="1" {{ old('is_active', $role->is_active) ? 'selected' : '' }}>{{ localize('active', 'Active') }}</option>
                            <option value="0" {{ !old('is_active', $role->is_active) ? 'selected' : '' }}>{{ localize('inactive', 'Inactive') }}</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ localize('close', 'Close') }}</button>
                    <button type="submit" class="btn btn-success">{{ localize('update', 'Update') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
