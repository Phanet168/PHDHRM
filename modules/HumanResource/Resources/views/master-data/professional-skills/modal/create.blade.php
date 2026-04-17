<div class="modal fade" id="create-professional-skill" data-bs-backdrop="static" data-bs-keyboard="false"
    tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Professional Skill</h5>
            </div>
            <form action="{{ route('professional-skills.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="row">
                        @input(['input_name' => 'code'])
                        @input(['input_name' => 'name_km', 'required' => 'true'])
                        @input(['input_name' => 'name_en'])
                        @input(['input_name' => 'shortcut_km'])
                        @input(['input_name' => 'shortcut_en'])
                        @input(['input_name' => 'retire_age', 'type' => 'number', 'required' => false])
                        <div class="form-group mb-2 mx-0 row">
                            <label for="budget_amount" class="col-lg-3 col-form-label ps-0">Budget</label>
                            <div class="col-lg-9">
                                <input type="number" name="budget_amount" id="budget_amount" min="0" step="0.01"
                                    value="{{ old('budget_amount', 0) }}" class="form-control" autocomplete="off">
                            </div>
                        </div>
                        @radio(['input_name' => 'is_active', 'data_set' => [1 => localize('active'), 0 => localize('inactive')], 'value' => 1])
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
