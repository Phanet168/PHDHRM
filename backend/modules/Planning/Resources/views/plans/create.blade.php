@extends('planning::layouts.app')

@php
    $pageTitle = 'ជំហានទី១: កំណត់សូចនាករ និងគោលដៅ';
@endphp

@section('planning-content')
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1 planning-page-title">ជំហានទី១: កំណត់សូចនាករ និងគោលដៅ</h1>
            <p class="planning-meta mb-0">បញ្ចូលសូចនាករ សម្រេចបានឆ្នាំចាស់ និងគោលដៅ រួចបន្តទៅជំហានទី២ ដើម្បីរៀបចំសកម្មភាព និងថវិកា។</p>
        </div>
        <a href="{{ route('planning.plans.index') }}" class="btn btn-light border">ត្រឡប់ទៅបញ្ជី</a>
    </div>

    @include('planning::plans.partials.wizard-steps', ['currentStep' => 1])

    <form action="{{ route('planning.plans.store') }}" method="post" enctype="multipart/form-data">
        @csrf
        @include('planning::plans.partials.form', ['submitLabel' => 'រក្សាទុក និងបន្តទៅជំហានទី២'])
    </form>
@endsection
