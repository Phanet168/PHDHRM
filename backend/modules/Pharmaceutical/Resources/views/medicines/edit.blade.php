@extends('backend.layouts.app')

@section('title', localize('edit_medicine', 'Edit medicine'))

@section('content')
    <div class="body-content">
        @include('pharmaceutical::_nav')

        <div class="card pharm-card">
            <div class="card-header">
                <h6 class="mb-0">{{ localize('edit_medicine', 'Edit medicine') }}</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('pharmaceutical.medicines.update', $medicine->id) }}">
                    @csrf @method('PUT')
                    @include('pharmaceutical::medicines._form', ['medicine' => $medicine])
                    <div class="mt-3">
                        <button type="submit" class="btn btn-success">{{ localize('update', 'Update') }}</button>
                        <a href="{{ route('pharmaceutical.medicines.index') }}" class="btn btn-secondary">{{ localize('cancel', 'Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
