<div class="modal fade" id="create-salary-scale" data-bs-backdrop="static" data-bs-keyboard="false"
    tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Salary Scale Type</h5>
            </div>
            <form action="{{ route('salary-scales.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="row">
                        @input(['input_name' => 'name_en', 'required' => 'true'])
                        @input(['input_name' => 'name_km'])
                        @radio(['input_name' => 'is_active', 'data_set' => [1 => 'Active', 0 => 'Inactive'], 'value' => 1])
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
