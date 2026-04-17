<div class="modal fade" id="create-org-unit-type" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-labelledby="createOrgUnitTypeLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="createOrgUnitTypeLabel">បន្ថែមប្រភេទអង្គភាព</h6>
            </div>
            <form action="{{ route('org-unit-types.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">កូដ <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control" placeholder="health_center_with_bed" required>
                        <small class="text-muted">ប្រើតែអក្សរ a-z, 0-9 និង _</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ឈ្មោះប្រភេទ (ខ្មែរ) <span class="text-danger">*</span></label>
                        <input type="text" name="name_km" class="form-control" placeholder="មណ្ឌលសុខភាពមានគ្រែ" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type (English) <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="Health Center With Bed" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">កម្រិតស្តង់ដារ (លំដាប់)</label>
                        <input type="number" min="0" max="9999" name="sort_order" class="form-control" value="0">
                    </div>
                    <div class="mb-1">
                        <label class="form-label">ស្ថានភាព</label>
                        <select name="is_active" class="form-select" required>
                            <option value="1" selected>សកម្ម</option>
                            <option value="0">អសកម្ម</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">បិទ</button>
                    <button type="submit" class="btn btn-success">រក្សាទុក</button>
                </div>
            </form>
        </div>
    </div>
</div>

