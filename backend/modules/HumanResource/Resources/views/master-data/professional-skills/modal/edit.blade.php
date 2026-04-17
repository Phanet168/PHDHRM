<div class="modal fade" id="update-professional-skill-{{ $skill->id }}" data-bs-backdrop="static" data-bs-keyboard="false"
    tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Professional Skill</h5>
            </div>
            <form action="{{ route('professional-skills.update', $skill->uuid) }}" method="POST">
                @method('PATCH')
                @csrf
                <div class="modal-body">
                    <div class="row">
                        @input(['input_name' => 'code', 'value' => $skill->code])
                        @input(['input_name' => 'name_km', 'value' => $skill->name_km, 'required' => 'true'])
                        @input(['input_name' => 'name_en', 'value' => $skill->name_en])
                        @input(['input_name' => 'shortcut_km', 'value' => $skill->shortcut_km])
                        @input(['input_name' => 'shortcut_en', 'value' => $skill->shortcut_en])
                        @input(['input_name' => 'retire_age', 'type' => 'number', 'required' => false, 'value' => $skill->retire_age])
                        <div class="form-group mb-2 mx-0 row">
                            <label for="budget_amount_{{ $skill->id }}" class="col-lg-3 col-form-label ps-0">Budget</label>
                            <div class="col-lg-9">
                                <input type="number" name="budget_amount" id="budget_amount_{{ $skill->id }}" min="0"
                                    step="0.01" value="{{ old('budget_amount', $skill->budget_amount) }}"
                                    class="form-control" autocomplete="off">
                            </div>
                        </div>
                        @radio(['input_name' => 'is_active', 'data_set' => [1 => localize('active'), 0 => localize('inactive')], 'value' => $skill->is_active])
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">{{ localize('close') }}</button>
                    <button class="btn btn-primary">{{ localize('save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
