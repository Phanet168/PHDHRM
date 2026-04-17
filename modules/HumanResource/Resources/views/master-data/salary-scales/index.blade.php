@extends('backend.layouts.app')
@section('title', 'កំណត់សន្ទស្សន៍ប្រាក់បៀវត្ស')
@section('content')
    @include('humanresource::master-data.header')
    @include('backend.layouts.common.validation')

    <div class="row">
        <div class="col-lg-5">
            <div class="card mb-4 fixed-tab-body">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="fs-17 fw-semi-bold mb-0">ប្រភេទសន្ទស្សន៍ប្រាក់បៀវត្ស (SSL)</h6>
                        @can('create_setup_rules')
                            <a href="#" class="btn btn-success btn-sm" data-bs-toggle="modal"
                                data-bs-target="#create-salary-scale"><i class="fa fa-plus-circle"></i>&nbsp;បន្ថែមប្រភេទសន្ទស្សន៍</a>
                            @include('humanresource::master-data.salary-scales.modal.create')
                        @endcan
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ប្រភេទសន្ទស្សន៍</th>
                                    <th>ស្ថានភាព</th>
                                    <th>សកម្មភាព</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($scales as $scale)
                                    <tr class="{{ $selected_scale && $selected_scale->id === $scale->id ? 'table-primary' : '' }}">
                                        <td>
                                            <strong>{{ $scale->name_km ?: $scale->name_en }}</strong><br>
                                            <small class="text-muted">{{ $scale->name_en ?? '-' }}</small>
                                        </td>
                                        <td>
                                            @if ($scale->is_active)
                                                <span class="badge bg-success">សកម្ម</span>
                                            @else
                                                <span class="badge bg-danger">អសកម្ម</span>
                                            @endif
                                        </td>
                                        <td class="text-nowrap">
                                            <a href="{{ route('salary-scales.index', ['scale' => $scale->uuid]) }}"
                                                class="btn btn-info-soft btn-sm me-1" title="បើកតារាង">
                                                <i class="fa fa-table"></i>
                                            </a>
                                            @can('update_setup_rules')
                                                <a href="#" class="btn btn-primary-soft btn-sm me-1" data-bs-toggle="modal"
                                                    data-bs-target="#update-salary-scale-{{ $scale->id }}" title="កែប្រែ"><i
                                                        class="fa fa-edit"></i></a>
                                                @include('humanresource::master-data.salary-scales.modal.edit')
                                            @endcan
                                            @can('delete_setup_rules')
                                                <a href="javascript:void(0)" class="btn btn-danger-soft btn-sm delete-confirm"
                                                    data-bs-toggle="tooltip" title="លុប"
                                                    data-route="{{ route('salary-scales.destroy', $scale->uuid) }}"
                                                    data-csrf="{{ csrf_token() }}"><i class="fa fa-trash"></i></a>
                                            @endcan
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">មិនទាន់មានប្រភេទសន្ទស្សន៍ប្រាក់បៀវត្ស</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card mb-4 fixed-tab-body">
                <div class="card-header">
                    <h6 class="fs-17 fw-semi-bold mb-0">
                        តារាងសន្ទស្សន៍ប្រាក់បៀវត្ស
                        @if ($selected_scale)
                            - {{ $selected_scale->name_km ?: $selected_scale->name_en }}
                        @endif
                    </h6>
                </div>
                <div class="card-body">
                    @if ($selected_scale)
                        <form action="{{ route('salary-scales.values.update', $selected_scale->uuid) }}" method="POST">
                            @csrf
                            <div class="alert alert-info mb-3">
                                បញ្ចូលសន្ទស្សន៍តាមជំនាញសម្រាប់ប្រភេទនេះ។ បើទុកទទេ នឹងលុបតម្លៃចេញ។
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle">
                                    <thead>
                                        <tr>
                                            <th width="45%">ជំនាញវិជ្ជាជីវៈ</th>
                                            <th width="25%">អក្សរកាត់</th>
                                            <th width="30%">តម្លៃសន្ទស្សន៍</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($skills as $skill)
                                            <tr>
                                                <td>
                                                    <strong>{{ $skill->name_km ?: $skill->name_en }}</strong>
                                                    @if ($skill->name_en)
                                                        <br><small class="text-muted">{{ $skill->name_en }}</small>
                                                    @endif
                                                </td>
                                                <td>{{ $skill->shortcut_km ?: ($skill->shortcut_en ?? '-') }}</td>
                                                <td>
                                                    <input type="number" step="0.01" min="0" name="values[{{ $skill->id }}]"
                                                        value="{{ $value_map[$skill->id] ?? '' }}" class="form-control"
                                                        placeholder="0.00">
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @can('update_setup_rules')
                                <button class="btn btn-primary">{{ localize('save') }}</button>
                            @endcan
                        </form>
                    @else
                        <div class="alert alert-warning mb-0">
                            សូមបង្កើតប្រភេទសន្ទស្សន៍ប្រាក់បៀវត្សជាមុនសិន។
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
