@extends('backend.layouts.app')
@section('title', $title)
@section('content')
    @include('humanresource::reports_header')
    @include('backend.layouts.common.validation')

    <div class="card mb-4 fixed-tab-body">
        <div class="card-header"><h6 class="fs-17 fw-semi-bold mb-0">{{ $title }}</h6></div>
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end mb-3">
                <div class="col-md-3">
                    <label class="form-label">អង្គភាព</label>
                    <select name="workplace_id" class="form-control select-basic-single">
                        <option value="">អង្គភាពទាំងអស់ក្នុងសិទ្ធិគ្រប់គ្រង</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected($selectedDepartmentId == $department->id)>{{ $department->department_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">ឆ្នាំ</label>
                    <input type="number" name="year" class="form-control" value="{{ $selectedYear }}" min="2000" max="2100">
                </div>
                <div class="col-md-2">
                    <label class="form-label">ឆមាស</label>
                    <select name="half" class="form-control select-basic-single">
                        <option value="1" @selected($selectedHalf == 1)>ឆមាសទី១ (មករា–មិថុនា)</option>
                        <option value="2" @selected($selectedHalf == 2)>ឆមាសទី២ (កក្កដា–ធ្នូ)</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary">មើលរបាយការណ៍</button>
                </div>
            </form>
            @include('humanresource::reports.partials.attendance-period-results')
        </div>
    </div>
@endsection
