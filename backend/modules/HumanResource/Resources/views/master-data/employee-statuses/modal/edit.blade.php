<div class="modal fade" id="update-employee-status-{{ $item->id }}" data-bs-backdrop="static" data-bs-keyboard="false"
    tabindex="-1" aria-labelledby="updateEmployeeStatusLabel{{ $item->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateEmployeeStatusLabel{{ $item->id }}">Edit Employee Status</h5>
            </div>
            <form action="{{ route('employee-statuses.update', $item->uuid) }}" method="POST" enctype="multipart/form-data">
                @method('PATCH')
                @csrf
                <div class="modal-body">
                    <div class="row">
                        <div class="form-group mb-2 mx-0 row">
                            <label for="status_code_{{ $item->id }}" class="col-lg-3 col-form-label ps-0">Code</label>
                            <div class="col-lg-9">
                                <input type="text" name="code" id="status_code_{{ $item->id }}" class="form-control"
                                    value="{{ $item->code }}" autocomplete="off">
                            </div>
                        </div>

                        <div class="form-group mb-2 mx-0 row">
                            <label for="status_name_km_{{ $item->id }}" class="col-lg-3 col-form-label ps-0">Status (KM)</label>
                            <div class="col-lg-9">
                                <input type="text" name="name_km" id="status_name_km_{{ $item->id }}" class="form-control"
                                    value="{{ $item->name_km }}" autocomplete="off">
                            </div>
                        </div>

                        <div class="form-group mb-2 mx-0 row">
                            <label for="status_name_en_{{ $item->id }}" class="col-lg-3 col-form-label ps-0">
                                Status (EN)
                                <span class="text-danger">*</span>
                            </label>
                            <div class="col-lg-9">
                                <input type="text" required name="name_en" id="status_name_en_{{ $item->id }}"
                                    class="form-control" value="{{ $item->name_en }}" autocomplete="off">
                            </div>
                        </div>

                        <div class="form-group mb-2 mx-0 row">
                            <label for="status_sort_order_{{ $item->id }}" class="col-lg-3 col-form-label ps-0">Sort Order</label>
                            <div class="col-lg-9">
                                <input type="number" min="0" max="9999" name="sort_order"
                                    id="status_sort_order_{{ $item->id }}" class="form-control"
                                    value="{{ (int) ($item->sort_order ?? 0) }}" autocomplete="off">
                            </div>
                        </div>

                        <div class="form-group mb-2 mx-0 row">
                            <label for="transition_group_{{ $item->id }}" class="col-lg-3 col-form-label ps-0">
                                Transition Group
                                <span class="text-danger">*</span>
                            </label>
                            <div class="col-lg-9">
                                <select name="transition_group" id="transition_group_{{ $item->id }}" class="form-control" required>
                                    <option value="active" {{ $item->transition_group === 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="suspended" {{ $item->transition_group === 'suspended' ? 'selected' : '' }}>Suspended</option>
                                    <option value="inactive" {{ $item->transition_group === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                </select>
                            </div>
                        </div>

                        @radio(['input_name' => 'is_active', 'data_set' => [1 => 'Active', 0 => 'Inactive'], 'value' => $item->is_active ? 1 : 0, 'required' => 'true'])
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
