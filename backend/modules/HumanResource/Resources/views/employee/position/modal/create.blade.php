<!-- Modal -->
<div class="modal fade" id="create-position" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-labelledby="staticBackdropLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="staticBackdropLabel">
                    Add Role / Position
                </h5>
            </div>
            <form class="validateForm" action="{{ route('positions.store') }}" method="POST"
                enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="row">
                        <div class="form-group mb-2 mx-0 row">
                            <label for="position_name" class="col-lg-3 col-form-label ps-0">
                                Position Name (EN)
                                <span class="text-danger">*</span>
                            </label>
                            <div class="col-lg-9">
                                <input type="text" required name="position_name" id="position_name"
                                    placeholder="Position Name (EN)" class="form-control" autocomplete="off">
                            </div>
                        </div>

                        <div class="form-group mb-2 mx-0 row">
                            <label for="position_name_km" class="col-lg-3 col-form-label ps-0">
                                Position Name (KM)
                            </label>
                            <div class="col-lg-9">
                                <input type="text" name="position_name_km" id="position_name_km"
                                    placeholder="Position Name (KM)" class="form-control" autocomplete="off">
                            </div>
                        </div>

                        <div class="form-group mb-2 mx-0 row">
                            <label for="position_details" class="col-lg-3 col-form-label ps-0">
                                Position Details
                            </label>

                            <div class="col-lg-9">
                                <input type="text" name="position_details" id="position_details"
                                    value="" placeholder="Position Details"
                                    class="form-control" autocomplete="off">
                            </div>
                        </div>

                        <div class="form-group mb-2 mx-0 row">
                            <label for="position_rank" class="col-lg-3 col-form-label ps-0">
                                Rank
                            </label>

                            <div class="col-lg-9">
                                <input type="number" name="position_rank" id="position_rank"
                                    min="1" max="20" placeholder="Rank"
                                    class="form-control" autocomplete="off">
                            </div>
                        </div>

                        <div class="form-group mb-2 mx-0 row">
                            <label for="budget_amount" class="col-lg-3 col-form-label ps-0">
                                Budget
                            </label>

                            <div class="col-lg-9">
                                <input type="number" name="budget_amount" id="budget_amount"
                                    min="0" step="0.01" placeholder="Budget"
                                    class="form-control" autocomplete="off">
                            </div>
                        </div>

                        @radio(['input_name' => 'is_prov_level', 'data_set' => [1 => 'Provincial Level', 0 => 'Non-Provincial'], 'value' => 1, 'required' => 'true'])
                        @radio(['input_name' => 'is_active', 'data_set' => [1 => 'Active', 0 => 'Inactive'], 'value' => 1, 'required' => 'true'])
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger"
                        data-bs-dismiss="modal">{{ localize('close') }}</button>
                    <button class="btn btn-primary submit_button" id="create_submit">{{ localize('save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
