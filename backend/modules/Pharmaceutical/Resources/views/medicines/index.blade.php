@extends('backend.layouts.app')

@section('title', localize('medicines', 'Medicines'))

@section('content')
    <div class="body-content">
        @include('pharmaceutical::_nav')

        <div class="card pharm-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">{{ localize('medicine_list', 'Medicine list') }}</h6>
                <div class="d-flex gap-2">
                    <a href="{{ route('pharmaceutical.help', ['article' => 'medicines']) }}" class="btn btn-sm btn-outline-info">
                        <i class="fa fa-life-ring me-1"></i>{{ localize('help', 'Help') }}
                    </a>
                    @if($canWrite ?? false)
                    <a href="{{ route('pharmaceutical.medicines.create') }}" class="btn btn-sm btn-success">
                        <i class="fa fa-plus me-1"></i>{{ localize('add_medicine', 'Add medicine') }}
                    </a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-2 mb-3">
                    <div class="col-md-4">
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="{{ localize('search', 'Search') }}..." value="{{ $search }}">
                    </div>
                    <div class="col-md-3">
                        <select name="category_id" class="form-select form-select-sm">
                            <option value="">-- {{ localize('all_categories', 'All categories') }} --</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" @selected($cat->id == $categoryId)>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-sm btn-primary w-100">{{ localize('filter', 'Filter') }}</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ localize('sl', 'SL') }}</th>
                                <th>{{ localize('code', 'Code') }}</th>
                                <th>{{ localize('name', 'Name') }}</th>
                                <th>{{ localize('name_kh', 'Name (KH)') }}</th>
                                <th>{{ localize('category', 'Category') }}</th>
                                <th>{{ localize('dosage_form', 'Dosage form') }}</th>
                                <th>{{ localize('strength', 'Strength') }}</th>
                                <th>{{ localize('unit', 'Unit') }}</th>
                                <th>{{ localize('unit_price', 'Unit price') }}</th>
                                <th>{{ localize('action', 'Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($medicines as $med)
                                <tr>
                                    <td>{{ $medicines->firstItem() + $loop->index }}</td>
                                    <td>{{ $med->code }}</td>
                                    <td>{{ $med->name }}</td>
                                    <td>{{ $med->name_kh ?: '-' }}</td>
                                    <td>{{ $med->category?->name ?: '-' }}</td>
                                    <td>{{ $med->dosage_form ?: '-' }}</td>
                                    <td>{{ $med->strength ?: '-' }}</td>
                                    <td>{{ $med->unit }}</td>
                                    <td class="text-end">{{ number_format((float) $med->unit_price, 2) }}</td>
                                    <td>
                                        @if($canWrite ?? false)
                                        <a href="{{ route('pharmaceutical.medicines.edit', $med->id) }}" class="btn btn-sm btn-outline-primary"><i class="fa fa-edit"></i></a>
                                        <form method="POST" action="{{ route('pharmaceutical.medicines.destroy', $med->id) }}" class="d-inline" onsubmit="return confirm('{{ localize('confirm_delete', 'Are you sure?') }}')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger"><i class="fa fa-trash"></i></button>
                                        </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="10" class="text-center text-muted">{{ localize('no_data', 'No data') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $medicines->links() }}</div>
            </div>
        </div>
    </div>
@endsection
