@extends('backend.layouts.app')
@section('title', 'គ្រប់គ្រងប្រភេទអង្គភាព')
@section('content')
    @include('humanresource::master-data.header')
    @include('backend.layouts.common.validation')

    <div class="card mb-4 fixed-tab-body">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="fs-17 fw-semi-bold mb-0">គ្រប់គ្រងប្រភេទអង្គភាព</h6>
                </div>
                <div class="text-end">
                    @can('create_department')
                        <a href="#" class="btn btn-success btn-sm" data-bs-toggle="modal"
                            data-bs-target="#create-org-unit-type">
                            <i class="fa fa-plus-circle"></i>&nbsp;បន្ថែមប្រភេទអង្គភាព
                        </a>
                        @include('humanresource::master-data.org-unit-types.modal.create')
                    @endcan
                </div>
            </div>
        </div>

        <div class="card-body">
            <div class="alert alert-info mb-3">
                ប្រភេទស្តង់ដារ៖ មន្ទីរសុខាភិបាលខេត្ត, ការិយាល័យ, ផ្នែក, កម្មវិធី, មន្ទីរពេទ្យខេត្ត, មន្ទីរពេទ្យស្រុក,
                ស្រុកប្រតិបត្តិ, មណ្ឌលសុខភាព(មានគ្រែ/គ្មានគ្រែ), ប៉ុស្តិ៍សុខភាព។
            </div>
            <div class="table-responsive">
                <table id="example" class="table display table-bordered table-striped table-hover">
                    <thead>
                        <tr>
                            <th width="5%">ល.រ</th>
                            <th width="14%">កូដ</th>
                            <th width="28%">ប្រភេទអង្គភាព (ខ្មែរ)</th>
                            <th width="22%">Type (EN)</th>
                            <th width="10%">កម្រិតស្តង់ដារ</th>
                            <th width="9%">ស្ថានភាព</th>
                            <th width="12%">សកម្មភាព</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($org_unit_types as $item)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td><code>{{ $item->code }}</code></td>
                                <td>{{ $item->name_km ?: '-' }}</td>
                                <td>{{ $item->name }}</td>
                                <td>{{ (int) $item->sort_order }}</td>
                                <td>
                                    @if ($item->is_active)
                                        <span class="badge bg-success">សកម្ម</span>
                                    @else
                                        <span class="badge bg-danger">អសកម្ម</span>
                                    @endif
                                </td>
                                <td>
                                    @can('update_department')
                                        <a href="#" class="btn btn-primary-soft btn-sm me-1" data-bs-toggle="modal"
                                            data-bs-target="#update-org-unit-type-{{ $item->id }}" title="កែប្រែ">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                        @include('humanresource::master-data.org-unit-types.modal.edit')
                                    @endcan

                                    @can('delete_department')
                                        <a href="javascript:void(0)" class="btn btn-danger-soft btn-sm delete-confirm"
                                            data-bs-toggle="tooltip" title="លុប"
                                            data-route="{{ route('org-unit-types.destroy', $item->id) }}"
                                            data-csrf="{{ csrf_token() }}">
                                            <i class="fa fa-trash"></i>
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

