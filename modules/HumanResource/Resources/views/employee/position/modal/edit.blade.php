<!-- Modal -->
<div class="modal fade" id="update-position-{{ $position->id }}" data-bs-backdrop="static" data-bs-keyboard="false"
    tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="staticBackdropLabel">
                    Edit Role / Position
                </h5>
            </div>
            <form id="leadForm" action="{{ route('positions.update', $positionIdentifier ?? ($position->uuid ?: $position->id)) }}" method="POST"
                enctype="multipart/form-data">
                @method('PATCH')
                @csrf
                <div class="modal-body">
                    <div class="row">
                        @input(['input_name' => 'position_name', 'value' => $position->position_name, 'required' => 'true'])
                        @input(['input_name' => 'position_name_km', 'value' => $position->position_name_km])
                        @input(['input_name' => 'position_details', 'value' => $position->position_details])
                        @input(['input_name' => 'position_rank', 'value' => $position->position_rank, 'type' => 'number'])
                        @input(['input_name' => 'budget_amount', 'value' => $position->budget_amount, 'type' => 'number', 'required' => false, 'label' => 'amount'])
                        @radio(['input_name' => 'is_prov_level', 'data_set' => [1 => 'Provincial Level', 0 => 'Non-Provincial'], 'value' => $position->is_prov_level, 'required' => 'true'])
                        @radio(['input_name' => 'is_active', 'data_set' => [1 => 'Active', 0 => 'Inactive'], 'value' => $position->is_active, 'required' => 'true'])
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger"
                        data-bs-dismiss="modal">{{ localize('close') }}</button>
                    <button class="btn btn-primary" id="update_submit">{{ localize('save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
