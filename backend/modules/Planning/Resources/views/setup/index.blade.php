@extends('planning::layouts.app')

@php
    $pageTitle = 'ត្រូវដំឡើង Module Planning';
@endphp

@section('planning-content')
    <div class="planning-hero">
        <span class="planning-kicker">ត្រូវការដំឡើង Planning</span>
        <h1 class="h3 mt-3 mb-2 planning-page-title">តារាងទិន្នន័យរបស់ Planning មិនទាន់បានដំឡើងនៅឡើយ។</h1>
        <p class="planning-meta mb-4">
            Module Planning ថ្មីត្រូវបានផ្ទុកបានត្រឹមត្រូវហើយ ប៉ុន្តែ schema ទិន្នន័យមិនទាន់បាន migrate នៅឡើយ។
            សូមរត់ commands ខាងក្រោមជាមុនសិន រួច refresh ទំព័រនេះម្តងទៀត។
        </p>

        <div class="planning-panel">
            <div class="planning-section-title">Commands ដែលត្រូវរត់</div>
            <pre class="mb-0"><code>php artisan migrate
php artisan module:seed Planning</code></pre>
        </div>
    </div>
@endsection
