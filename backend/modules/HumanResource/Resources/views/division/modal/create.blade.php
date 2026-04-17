<!-- Modal -->
<div class="modal fade" id="create-division" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-labelledby="staticBackdropLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="staticBackdropLabel">
                    {{ localize('new_sub_department') }}
                </h5>
            </div>
            <form class="validateForm js-org-unit-form" action="{{ route('divisions.store') }}" method="POST"
                enctype="multipart/form-data"
                data-allowed-parent-map='@json($allowed_parent_types_map ?? [])'>
                @csrf
                <div class="modal-body">
                    <div class="row">
                        <div class="form-group mb-2 mx-0 row">
                            <label for="division_name" class="col-lg-3 col-form-label ps-0">
                                {{ localize('sub_department_name') }}
                                <span class="text-danger">*</span>
                            </label>
                            <div class="col-lg-9">
                                <input type="text" name="division_name"
                                    placeholder="{{ localize('sub_department_name') }}" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-group mb-2 mx-0 row">
                            <label for="unit_type_id" class="col-sm-3 col-form-label ps-0">
                                {{ localize('unit_type') }} <span class="text-danger">*</span>
                            </label>
                            <div class="col-lg-9">
                                <select name="unit_type_id" required class="form-select js-unit-type-select">
                                    <option value="">{{ localize('select_unit_type') }}</option>
                                    @foreach ($unit_types as $unit_type)
                                        <option value="{{ $unit_type->id }}">
                                            {{ $unit_type->display_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group mb-2 mx-0 row">
                            <label for="department" class="col-sm-3 col-form-label ps-0">{{ localize('parent_unit') }}</label>
                            <div class="col-lg-9">
                                <select name="parent_id" class="form-select js-parent-unit-select">
                                    <option value="">{{ localize('select_parent_unit') }}</option>
                                    @foreach ($departments as $parent_option)
                                        <option value="{{ $parent_option->id }}" title="{{ $parent_option->path }}"
                                            data-unit-type-id="{{ $parent_option->unit_type_id }}">
                                            {{ $parent_option->label }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted js-parent-unit-hint">{{ localize('select_parent_from_tree_hint') }}</small>
                                @if ($errors->has('parent_id'))
                                    <div class="error text-danger text-start">{{ $errors->first('parent_id') }}</div>
                                @endif
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
                                        <option value="{{ $ssl_type->id }}">
                                            {{ $ssl_type->display_name }}
                                        </option>
                                    @endforeach
                                </select>
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
                        @radio(['input_name' => 'is_active', 'data_set' => [1 => 'Active', 0 => 'Inactive'], 'value' => 1])
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger"
                        data-bs-dismiss="modal">{{ localize('close') }}</button>
                    <button type="submit" class="btn btn-primary submit_button"
                        id="create_submit">{{ localize('save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
