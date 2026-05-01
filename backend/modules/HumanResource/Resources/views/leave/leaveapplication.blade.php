@extends('backend.layouts.app')
@section('title', localize('leave_application_list'))
@push('css')
@endpush
@section('content')

    @include('humanresource::leave_header')


    <div class="card mb-4 fixed-tab-body att-card">
        @include('backend.layouts.common.validation')
        @include('backend.layouts.common.message')
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="fs-17 fw-semi-bold mb-0">{{ localize('leave_application_list') }}</h6>
                </div>
                <div class="text-end">
                    <div class="actions">
                        <button type="button" class="btn btn-success" data-bs-toggle="collapse"
                            data-bs-target="#flush-collapseOne" aria-expanded="false" aria-controls="flush-collapseOne"> <i
                                class="fas fa-filter"></i> {{ localize('filter') }}</button>

                        @can('create_leave_application')
                            <a href="#" class="btn btn-success" data-bs-toggle="modal"
                                data-bs-target="#addLeaveApplication"><i
                                    class="fa fa-plus-circle"></i>&nbsp;{{ localize('add_leave_application') }}</a>
                            @include('humanresource::leave.livcreate')
                        @endcan
                    </div>
                </div>
            </div>
        </div>


        <div class="card-body">
            <div class="row">
                <div class="col-12">
                    <div class="accordion accordion-flush" id="accordionFlushExample">
                        <div class="accordion-item">
                            <div id="flush-collapseOne" class="accordion-collapse collapse bg-white mb-4"
                                aria-labelledby="flush-headingOne" data-bs-parent="#accordionFlushExample">

                                <div class="row g-3 align-items-end">
                                    <div class="col-md-5 col-lg-4">
                                        <label for="employee_name" class="form-label fw-semibold mb-2">
                                            {{ localize('employee_name') }}
                                        </label>
                                        <select id="employee_name" class="select-basic-single w-100">
                                            <option selected value="">{{ localize('all_employees') }}</option>
                                            @foreach ($employees as $employee)
                                                <option value="{{ $employee->id }}">{{ ucwords($employee->full_name) }}</option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted d-block mt-2">
                                            {{ localize('filter_leave_request_hint', 'ស្វែងរកសំណើសុំច្បាប់តាមឈ្មោះបុគ្គលិក') }}
                                        </small>
                                    </div>
                                    <div class="col-md-7 col-lg-5">
                                        <div class="d-flex flex-wrap gap-2">
                                            <button type="button" id="leave-application-filter" class="btn btn-success">
                                                <i class="fa fa-search me-1"></i>{{ localize('search', 'ស្វែងរក') }}
                                            </button>
                                            <button type="button" id="leave-application-search-reset" class="btn btn-outline-danger">
                                                <i class="fa fa-undo me-1"></i>{{ localize('reset') }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="table_customize">
                {{ $dataTable->table() }}
            </div>
        </div>
    </div>

    <!--Edit Application Modal -->
    <div class="modal fade leave-request-modal" id="edit-application" data-bs-backdrop="static" data-bs-keyboard="false"
        aria-labelledby="staticBackdropLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-fullscreen-lg-down leave-request-modal-dialog">
            <div class="modal-content border-0 shadow overflow-hidden">
                <div class="modal-header leave-request-modal-header">
                    <h5 class="modal-title" id="staticBackdropLabel">
                        {{ localize('edit_leave_application') }}
                    </h5>
                </div>
                <div id="editLeaveApplication">
                </div>
            </div>
        </div>
    </div>

    <!-- Application Approve Modal -->
    <div class="modal fade leave-approve-modal" id="approve-application" data-bs-backdrop="static" data-bs-keyboard="false"
        aria-labelledby="staticBackdropLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-fullscreen-lg-down leave-request-modal-dialog">
            <div class="modal-content border-0 shadow overflow-hidden">
                <div class="modal-header leave-request-modal-header">
                    <h5 class="modal-title" id="staticBackdropLabel">
                        {{ localize('application_approved') }}
                    </h5>
                </div>
                <div id="approveLeaveApplication">
                </div>
            </div>
        </div>
    </div>
@endsection
@push('js')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
    <script src="{{ module_asset('HumanResource/js/hrcommon.js') }}"></script>
@endpush
