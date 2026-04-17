<!-- Modal -->
<div class="modal fade" id="create-department" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-labelledby="staticBackdropLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="staticBackdropLabel">{{ localize('new_department') }}</h5>
            </div>
            <form id="orgUnitCreateForm" action="{{ route('departments.store') }}" method="POST" enctype="multipart/form-data"
                class="js-org-unit-form" data-allowed-parent-map='@json($allowed_parent_types_map ?? [])'>
                @csrf
                <div class="modal-body">
                    <div class="row">
                        @input(['input_name' => 'department_name'])
                        <div class="form-group mb-2 mx-0 row">
                            <label for="unit_type_id" class="col-sm-3 col-form-label ps-0">
                                {{ localize('unit_type') }} <span class="text-danger">*</span>
                            </label>
                            <div class="col-lg-9">
                                <select name="unit_type_id" class="form-select js-unit-type-select" required>
                                    <option value="">{{ localize('select_unit_type') }}</option>
                                    @foreach ($unit_types as $unit_type)
                                        <option value="{{ $unit_type->id }}"
                                            {{ old('unit_type_id') == $unit_type->id ? 'selected' : '' }}>
                                            {{ $unit_type->display_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group mb-2 mx-0 row">
                            <label for="ssl_type_id" class="col-sm-3 col-form-label ps-0">
                                {{ localize('standard_staffing_level') }}
                            </label>
                            <div class="col-lg-9">
                                <select name="ssl_type_id" class="form-select">
                                    <option value="">{{ localize('select_standard_staffing_level') }}</option>
                                    @foreach (($ssl_types ?? []) as $ssl_type)
                                        <option value="{{ $ssl_type->id }}"
                                            {{ (int) old('ssl_type_id') === (int) $ssl_type->id ? 'selected' : '' }}>
                                            {{ $ssl_type->display_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group mb-2 mx-0 row">
                            <label for="parent_id" class="col-sm-3 col-form-label ps-0">{{ localize('parent_unit') }}</label>
                            <div class="col-lg-9">
                                <select name="parent_id" class="form-select js-parent-unit-select">
                                    <option value="">{{ localize('select_parent_unit') }}</option>
                                    @foreach ($parent_units as $parent_unit)
                                        <option value="{{ $parent_unit->id }}" title="{{ $parent_unit->path }}"
                                            data-unit-type-id="{{ $parent_unit->unit_type_id }}"
                                            {{ (int) old('parent_id') === (int) $parent_unit->id ? 'selected' : '' }}>
                                            {{ $parent_unit->label }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted js-parent-unit-hint">{{ localize('leave_blank_for_root_unit') }}</small>
                            </div>
                        </div>
                        <div class="form-group mb-2 mx-0 row">
                            <label for="sort_order" class="col-sm-3 col-form-label ps-0">{{ localize('sort_order') }}</label>
                            <div class="col-lg-9">
                                <input type="number" min="0" max="9999" name="sort_order" class="form-control"
                                    placeholder="{{ localize('sort_order_placeholder') }}" value="{{ old('sort_order', 0) }}">
                            </div>
                        </div>
                        <div class="form-group mb-2 mx-0 row">
                            <label for="location_code"
                                class="col-sm-3 col-form-label ps-0">{{ localize('location_code') }}</label>
                            <div class="col-lg-9">
                                <input type="text" name="location_code" class="form-control"
                                    placeholder="{{ localize('location_code') }}" value="{{ old('location_code') }}">
                            </div>
                        </div>
                        <div class="form-group mb-2 mx-0 row">
                            <label for="latitude" class="col-sm-3 col-form-label ps-0">{{ localize('latitude') }}</label>
                            <div class="col-lg-9">
                                <input type="number" step="0.0000001" name="latitude" class="form-control org-latitude"
                                    placeholder="{{ localize('latitude') }}" value="{{ old('latitude') }}">
                            </div>
                        </div>
                        <div class="form-group mb-2 mx-0 row">
                            <label for="longitude"
                                class="col-sm-3 col-form-label ps-0">{{ localize('longitude') }}</label>
                            <div class="col-lg-9">
                                <input type="number" step="0.0000001" name="longitude" class="form-control org-longitude"
                                    placeholder="{{ localize('longitude') }}" value="{{ old('longitude') }}">
                            </div>
                        </div>
                        <div class="form-group mb-2 mx-0 row">
                            <label class="col-sm-3 col-form-label ps-0">{{ localize('map_location') }}</label>
                            <div class="col-lg-9">
                                <div data-map-picker>
                                    <div class="org-map-canvas"></div>
                                    <small class="text-muted">{{ localize('click_on_map_to_set_location') }}</small>
                                </div>
                            </div>
                        </div>
                        @radio(['input_name' => 'is_active', 'data_set' => [1 => localize('active'), 0 => localize('inactive')], 'value' => 1])
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger"
                        data-bs-dismiss="modal">{{ localize('close') }}</button>
                    <button type="submit" class="btn btn-primary submit_button" id="org_unit_create_submit">{{ localize('save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
