@extends('backend.layouts.app')

@section('title', localize('add_medicine', 'Add medicine'))

@section('content')
    <div class="body-content">
        @include('pharmaceutical::_nav')

        <div class="card pharm-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">{{ localize('add_medicine', 'Add medicine') }}</h6>
                <a href="{{ route('pharmaceutical.help', ['article' => 'medicines']) }}" class="btn btn-sm btn-outline-info">
                    <i class="fa fa-life-ring me-1"></i>{{ localize('help', 'Help') }}
                </a>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('pharmaceutical.medicines.store') }}">
                    @csrf
                    @include('pharmaceutical::medicines._form')
                    <div class="mt-3">
                        <button type="submit" class="btn btn-success">{{ localize('save', 'Save') }}</button>
                        <a href="{{ route('pharmaceutical.medicines.index') }}" class="btn btn-secondary">{{ localize('cancel', 'Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
