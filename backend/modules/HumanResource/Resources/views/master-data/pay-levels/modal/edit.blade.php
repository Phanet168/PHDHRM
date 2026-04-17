<div class="modal fade" id="update-pay-level-{{ $pay_level->id }}" data-bs-backdrop="static" data-bs-keyboard="false"
    tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Pay Level</h5>
            </div>
            <form action="{{ route('pay-levels.update', $pay_level->uuid) }}" method="POST">
                @method('PATCH')
                @csrf
                <div class="modal-body">
                    <div class="row">
                        @input(['input_name' => 'level_code', 'value' => $pay_level->level_code, 'required' => 'true'])
                        @input(['input_name' => 'level_name_km', 'value' => $pay_level->level_name_km, 'required' => 'true'])
                        <div class="form-group mb-2 mx-0 row">
                            <label for="budget_amount_{{ $pay_level->id }}" class="col-lg-3 col-form-label ps-0">Base Budget</label>
                            <div class="col-lg-9">
                                <input type="number" name="budget_amount" id="budget_amount_{{ $pay_level->id }}" min="0"
                                    step="0.01" value="{{ old('budget_amount', $pay_level->budget_amount) }}"
                                    class="form-control" autocomplete="off">
                            </div>
                        </div>
                        @input(['input_name' => 'sort_order', 'type' => 'number', 'value' => $pay_level->sort_order])
                        @radio(['input_name' => 'is_active', 'data_set' => [1 => localize('active'), 0 => localize('inactive')], 'value' => $pay_level->is_active])
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
