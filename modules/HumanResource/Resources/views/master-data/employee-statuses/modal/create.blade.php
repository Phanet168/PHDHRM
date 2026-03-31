<div class="modal fade" id="create-employee-status" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-labelledby="createEmployeeStatusLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createEmployeeStatusLabel">Add Employee Status</h5>
            </div>
            <form action="{{ route('employee-statuses.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="row">
                        <div class="form-group mb-2 mx-0 row">
                            <label for="status_code" class="col-lg-3 col-form-label ps-0">Code</label>
                            <div class="col-lg-9">
                                <input type="text" name="code" id="status_code" class="form-control"
                                    placeholder="example: active, leave_without_pay" autocomplete="off">
                            </div>
                        </div>

                        <div class="form-group mb-2 mx-0 row">
                            <label for="status_name_km" class="col-lg-3 col-form-label ps-0">Status (KM)</label>
                            <div class="col-lg-9">
                                <input type="text" name="name_km" id="status_name_km" class="form-control"
                                    placeholder="Status Khmer" autocomplete="off">
                            </div>
                        </div>

                        <div class="form-group mb-2 mx-0 row">
                            <label for="status_name_en" class="col-lg-3 col-form-label ps-0">
                                Status (EN)
                                <span class="text-danger">*</span>
                            </label>
                            <div class="col-lg-9">
                                <input type="text" required name="name_en" id="status_name_en" class="form-control"
                                    placeholder="Status English" autocomplete="off">
                            </div>
                        </div>

                        <div class="form-group mb-2 mx-0 row">
                            <label for="status_sort_order" class="col-lg-3 col-form-label ps-0">Sort Order</label>
                            <div class="col-lg-9">
                                <input type="number" min="0" max="9999" name="sort_order" id="status_sort_order"
                                    class="form-control" value="0" autocomplete="off">
                            </div>
                        </div>

                        <div class="form-group mb-2 mx-0 row">
                            <label for="transition_group" class="col-lg-3 col-form-label ps-0">
                                Transition Group
                                <span class="text-danger">*</span>
                            </label>
                            <div class="col-lg-9">
                                <select name="transition_group" id="transition_group" class="form-control" required>
                                    <option value="active" selected>Active</option>
                                    <option value="suspended">Suspended</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>

                        @radio(['input_name' => 'is_active', 'data_set' => [1 => 'Active', 0 => 'Inactive'], 'value' => 1, 'required' => 'true'])
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
