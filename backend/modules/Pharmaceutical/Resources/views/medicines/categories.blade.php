@extends('backend.layouts.app')

@section('title', localize('categories', 'Categories'))

@section('content')
    <div class="body-content">
        @include('pharmaceutical::_nav')

        <div class="card pharm-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">{{ localize('medicine_categories', 'Medicine categories') }}</h6>
                <div class="d-flex gap-2">
                    <a href="{{ route('pharmaceutical.help', ['article' => 'categories']) }}" class="btn btn-sm btn-outline-info">
                        <i class="fa fa-life-ring me-1"></i>{{ localize('help', 'Help') }}
                    </a>
                    @if($canWrite ?? false)
                    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                        <i class="fa fa-plus me-1"></i>{{ localize('add_category', 'Add category') }}
                    </button>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ localize('sl', 'SL') }}</th>
                                <th>{{ localize('name', 'Name') }}</th>
                                <th>{{ localize('name_kh', 'Name (KH)') }}</th>
                                <th>{{ localize('description', 'Description') }}</th>
                                <th>{{ localize('medicines_count', 'Medicines') }}</th>
                                <th>{{ localize('action', 'Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($categories as $cat)
                                <tr>
                                    <td>{{ $categories->firstItem() + $loop->index }}</td>
                                    <td>{{ $cat->name }}</td>
                                    <td>{{ $cat->name_kh ?: '-' }}</td>
                                    <td>{{ $cat->description ?: '-' }}</td>
                                    <td>{{ $cat->medicines_count }}</td>
                                    <td>
                                        @if($canWrite ?? false)
                                        <form method="POST" action="{{ route('pharmaceutical.categories.destroy', $cat->id) }}" class="d-inline" onsubmit="return confirm('{{ localize('confirm_delete', 'Are you sure?') }}')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger"><i class="fa fa-trash"></i></button>
                                        </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted">{{ localize('no_data', 'No data') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $categories->links() }}</div>
            </div>
        </div>
    </div>

    {{-- Add category modal --}}
    @if($canWrite ?? false)
    <div class="modal fade" id="addCategoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('pharmaceutical.categories.store') }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title">{{ localize('add_category', 'Add category') }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">{{ localize('name', 'Name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ localize('name_kh', 'Name (KH)') }}</label>
                        <input type="text" name="name_kh" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ localize('description', 'Description') }}</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">{{ localize('save', 'Save') }}</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ localize('close', 'Close') }}</button>
                </div>
            </form>
        </div>
    </div>
    @endif
@endsection
