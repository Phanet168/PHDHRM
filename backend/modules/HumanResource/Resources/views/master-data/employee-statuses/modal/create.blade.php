<div class="modal fade" id="create-employee-status" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-labelledby="createEmployeeStatusLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createEmployeeStatusLabel">{{ localize('add_employee_status', 'បន្ថែមស្ថានភាពបុគ្គលិក') }}</h5>
            </div>
            <form action="{{ route('employee-statuses.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="row">
                        <div class="form-group mb-2 mx-0 row">
                            <label for="status_code" class="col-lg-3 col-form-label ps-0">{{ localize('code') }}</label>
                            <div class="col-lg-9">
                                <input type="text" name="code" id="status_code" class="form-control"
                                    placeholder="{{ localize('employee_status_code_example', 'ឧទាហរណ៍៖ active, leave_without_pay') }}" autocomplete="off">
                            </div>
                        </div>

                        <div class="form-group mb-2 mx-0 row">
                            <label for="status_name_km" class="col-lg-3 col-form-label ps-0">{{ localize('status_km_label', 'ស្ថានភាព (ខ្មែរ)') }}</label>
                            <div class="col-lg-9">
                                <input type="text" name="name_km" id="status_name_km" class="form-control"
                                    placeholder="{{ localize('status_khmer_placeholder', 'ឈ្មោះស្ថានភាពជាភាសាខ្មែរ') }}" autocomplete="off">
                            </div>
                        </div>

                        <div class="form-group mb-2 mx-0 row">
                            <label for="status_name_en" class="col-lg-3 col-form-label ps-0">
                                {{ localize('status_en_label', 'ស្ថានភាព (អង់គ្លេស)') }}
                                <span class="text-danger">*</span>
                            </label>
                            <div class="col-lg-9">
                                <input type="text" required name="name_en" id="status_name_en" class="form-control"
                                    placeholder="{{ localize('status_english_placeholder', 'ឈ្មោះស្ថានភាពជាភាសាអង់គ្លេស') }}" autocomplete="off">
                            </div>
                        </div>

                        <div class="form-group mb-2 mx-0 row">
                            <label for="status_sort_order" class="col-lg-3 col-form-label ps-0">{{ localize('sort_order') }}</label>
                            <div class="col-lg-9">
                                <input type="number" min="0" max="9999" name="sort_order" id="status_sort_order"
                                    class="form-control" value="0" autocomplete="off">
                            </div>
                        </div>

                        <div class="form-group mb-2 mx-0 row">
                            <label for="transition_group" class="col-lg-3 col-form-label ps-0">
                                {{ localize('transition_group', 'ក្រុមផ្លាស់ប្តូរ') }}
                                <span class="text-danger">*</span>
                            </label>
                            <div class="col-lg-9">
                                <select name="transition_group" id="transition_group" class="form-control" required>
                                    <option value="active" selected>{{ localize('active') }}</option>
                                    <option value="suspended">{{ localize('suspended') }}</option>
                                    <option value="inactive">{{ localize('inactive') }}</option>
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
