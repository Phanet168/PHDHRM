@extends('planning::layouts.app')

@php
    $pageTitle = 'កែជំហានទី១: កំណត់សូចនាករ និងគោលដៅ';
@endphp

@section('planning-content')
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1 planning-page-title">កែជំហានទី១: កំណត់សូចនាករ និងគោលដៅ</h1>
            <p class="planning-meta mb-0">កែសម្រួលសូចនាករ និងគោលដៅ រួចបន្តទៅជំហានទី២ ដើម្បីរៀបចំសកម្មភាព និងថវិកា។</p>
        </div>
        <a href="{{ route('planning.plans.show', $plan) }}" class="btn btn-light border">ត្រឡប់ទៅព័ត៌មានផែនការ</a>
    </div>

    @include('planning::plans.partials.wizard-steps', ['currentStep' => 1])

    <form action="{{ route('planning.plans.update', $plan) }}" method="post" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('planning::plans.partials.form', ['submitLabel' => 'រក្សាទុកជំហានទី១'])
    </form>
@endsection
