@extends('backend.layouts.app')
@section('title', 'Mission Dashboard')
@section('content')
    @include('humanresource::attendance.missions.nav')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h4>ផ្ទាំងគ្រប់គ្រងបេសកកម្ម</h4><p class="text-muted mb-0">ទិដ្ឋភាពទូទៅនៃសំណើ និងបេសកកម្មទាំងអស់</p></div>
        <div class="d-flex gap-2">
            @if($canCreateRequest)<a href="{{ route('missions.request.create') }}" class="btn btn-primary"><i class="fa fa-file-signature me-1"></i>ស្នើសុំបេសកកម្ម</a>@endif
            @if($canCreateDirect)<a href="{{ route('missions.direct.create') }}" class="btn btn-outline-primary"><i class="fa fa-bolt me-1"></i>បង្កើតដោយផ្ទាល់</a>@endif
        </div>
    </div>

    @php
        $tiles = [
            ['key' => 'mine', 'label' => 'សំណើរបស់ខ្ញុំ', 'icon' => 'fa-user', 'show' => $canCreateRequest],
            ['key' => 'office-head', 'label' => 'រង់ចាំប្រធានការិយាល័យ', 'icon' => 'fa-inbox', 'show' => true],
            ['key' => 'director', 'label' => 'រង់ចាំប្រធានមន្ទីរ', 'icon' => 'fa-inbox', 'show' => true],
            ['key' => 'approved', 'label' => 'សំណើដែលបានអនុម័ត', 'icon' => 'fa-check-circle', 'show' => true],
            ['key' => 'on-mission', 'label' => 'កំពុងបេសកកម្ម', 'icon' => 'fa-route', 'show' => true],
            ['key' => 'pending-report', 'label' => 'រង់ចាំរបាយការណ៍', 'icon' => 'fa-file-alt', 'show' => true],
        ];
    @endphp
    <div class="row g-3 mb-4">
        @foreach($tiles as $tile)
            @if($tile['show'])
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="{{ route('missions.queue', $tile['key']) }}" class="text-decoration-none">
                        <div class="card h-100 text-center py-3">
                            <div class="card-body">
                                <i class="fa {{ $tile['icon'] }} fa-lg text-primary mb-2"></i>
                                <div class="fs-3 fw-bold">{{ $counts[$tile['key']] ?? 0 }}</div>
                                <div class="text-muted small">{{ $tile['label'] }}</div>
                            </div>
                        </div>
                    </a>
                </div>
            @endif
        @endforeach
    </div>

    <div class="card">
        <div class="card-header">សកម្មភាពថ្មីៗ</div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>ចំណងជើង</th><th>គោលដៅ</th><th>កាលបរិច្ឆេទ</th><th>ស្ថានភាព</th><th></th></tr></thead>
            <tbody>
                @forelse($recent as $mission)
                    <tr>
                        <td><a href="{{ route('missions.show', $mission->id) }}">{{ $mission->title }}</a></td>
                        <td>{{ $mission->destination }}</td>
                        <td>{{ $mission->start_date->format('Y-m-d') }} — {{ $mission->end_date->format('Y-m-d') }}</td>
                        <td>@include('humanresource::attendance.missions.status')</td>
                        <td><a class="btn btn-sm btn-outline-primary" href="{{ route('missions.show', $mission->id) }}">មើលលម្អិត</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-5">មិនទាន់មានបេសកកម្មទេ។</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </div>
@endsection
